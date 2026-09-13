<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Hospital_tenant_provision_runner
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library(array('central_db', 'tenant_context', 'hospital_tenant_bootstrap'));
        $this->CI->load->config('tenancy-config');
    }

    /**
     * @param array<string, mixed> $job
     */
    public function runForJob(array $job)
    {
        $productCode = (string) $this->CI->config->item('product_code');
        if ((string) ($job['product_code'] ?? '') !== $productCode) {
            throw new RuntimeException('Provision job product_code mismatch.');
        }

        $tenantId = (string) ($job['tenant_id'] ?? '');
        if ($tenantId === '') {
            throw new RuntimeException('Missing tenant_id on provision job.');
        }

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : array();

        $this->CI->central_db->updateProvisionJob($tenantId, $productCode, array(
            'status' => 'processing',
            'attempts' => (int) ($job['attempts'] ?? 0) + 1,
            'started_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ));

        try {
            $this->runPipeline($tenantId, $payload);
            $this->CI->central_db->updateProvisionJob($tenantId, $productCode, array(
                'status' => 'ready',
                'finished_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ));
            $this->CI->central_db->markTenantProvisioned($tenantId);
        } catch (Exception $e) {
            $this->CI->central_db->updateProvisionJob($tenantId, $productCode, array(
                'status' => 'failed',
                'finished_at' => date('Y-m-d H:i:s'),
                'last_error' => substr($e->getMessage(), 0, 2000),
            ));
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function runPipeline($tenantId, array $payload = array())
    {
        $templateDatabase = (string) $this->CI->config->item('template_database');
        $tenantDatabase = (string) $this->CI->tenant_context->resolveDatabaseName($tenantId);

        if ($templateDatabase === '' || $tenantDatabase === '') {
            throw new RuntimeException('Template or tenant database name missing.');
        }
        if ($templateDatabase === $tenantDatabase) {
            throw new RuntimeException('Template database cannot equal tenant database.');
        }

        $mysqli = $this->ddlConnection();
        $this->createDatabaseIfMissing($mysqli, $tenantDatabase);
        $this->cloneSchema($mysqli, $templateDatabase, $tenantDatabase);
        $this->applyMigrations($tenantDatabase);
        $this->bootstrapTenant($tenantDatabase, $tenantId, $payload);
    }

    private function ddlConnection()
    {
        include APPPATH . 'config/database.php';
        $cfg = $db['default'];
        $mysqli = new mysqli($cfg['hostname'], $cfg['username'], $cfg['password'], null, 3306);
        if ($mysqli->connect_error) {
            throw new RuntimeException('DDL connection failed: ' . $mysqli->connect_error);
        }

        return $mysqli;
    }

    private function createDatabaseIfMissing(mysqli $mysqli, $databaseName)
    {
        $quoted = '`' . str_replace('`', '``', $databaseName) . '`';
        if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS {$quoted} CHARACTER SET utf8 COLLATE utf8_general_ci")) {
            throw new RuntimeException('CREATE DATABASE failed: ' . $mysqli->error);
        }
    }

    private function cloneSchema(mysqli $mysqli, $templateDatabase, $tenantDatabase)
    {
        $skip = array_map('strtolower', (array) $this->CI->config->item('skip_template_tables'));
        $copyData = array_map('strtolower', (array) $this->CI->config->item('copy_template_data_tables'));
        $qTemplate = $this->quoteDb($templateDatabase);
        $qTenant = $this->quoteDb($tenantDatabase);

        $existing = $mysqli->query("SHOW TABLES FROM {$qTenant}");
        if ($existing) {
            $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
            while ($row = $existing->fetch_array()) {
                $table = $row[0];
                $mysqli->query("DROP TABLE IF EXISTS {$qTenant}." . $this->quoteDb($table));
            }
            $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
        }

        $tablesResult = $mysqli->query("SHOW TABLES FROM {$qTemplate}");
        if (!$tablesResult) {
            throw new RuntimeException('Failed reading template tables.');
        }

        $tables = array();
        while ($row = $tablesResult->fetch_array()) {
            $table = (string) $row[0];
            if (in_array(strtolower($table), $skip, true)) {
                continue;
            }
            $tables[] = $table;
            $qt = $this->quoteDb($table);
            $sql = "CREATE TABLE IF NOT EXISTS {$qTenant}.{$qt} LIKE {$qTemplate}.{$qt}";
            if (!$mysqli->query($sql)) {
                throw new RuntimeException("Clone schema failed for {$table}: " . $mysqli->error);
            }
        }

        foreach ($copyData as $tableName) {
            $match = null;
            foreach ($tables as $table) {
                if (strtolower($table) === strtolower((string) $tableName)) {
                    $match = $table;
                    break;
                }
            }
            if ($match === null) {
                continue;
            }
            $qt = $this->quoteDb($match);
            $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
            $mysqli->query("TRUNCATE TABLE {$qTenant}.{$qt}");
            $mysqli->query("INSERT INTO {$qTenant}.{$qt} SELECT * FROM {$qTemplate}.{$qt}");
            $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function applyMigrations($tenantDatabase)
    {
        $this->CI->tenant_context->initialize(str_replace((string) $this->CI->config->item('tenant_database_prefix'), '', $tenantDatabase));
        $config = $this->CI->tenant_context->tenantDatabaseConfig();
        $config['database'] = $tenantDatabase;
        $tenantDb = $this->CI->load->database($config, true);

        if (!$tenantDb->table_exists('migrations')) {
            return;
        }

        $migrationPath = APPPATH . 'migrations/';
        if (!is_dir($migrationPath)) {
            return;
        }

        $files = glob($migrationPath . '*.php');
        if (!$files) {
            return;
        }
        sort($files);

        $applied = $tenantDb->get('migrations')->row_array();
        $currentVersion = (int) ($applied['version'] ?? 0);

        foreach ($files as $file) {
            if (!preg_match('/(\d+)_/', basename($file), $matches)) {
                continue;
            }
            $version = (int) $matches[1];
            if ($version <= $currentVersion) {
                continue;
            }

            require_once $file;
            $class = 'Migration_' . ucfirst(str_replace('.php', '', basename($file)));
            if (!class_exists($class)) {
                $class = basename($file, '.php');
                $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $class)));
            }
            foreach (get_declared_classes() as $declared) {
                if (stripos($declared, 'Migration') !== false && is_subclass_of($declared, 'CI_Migration')) {
                    // skip
                }
            }

            // CI migrations use numeric filenames — update version row if schema already applied via clone.
            $tenantDb->where('version <', $version);
            $tenantDb->update('migrations', array('version' => $version));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function bootstrapTenant($tenantDatabase, $tenantId, array $payload)
    {
        include APPPATH . 'config/database.php';
        $config = $db['default'];
        $config['database'] = $tenantDatabase;
        $config['pconnect'] = false;
        $tenantDb = $this->CI->load->database($config, true);
        $this->CI->hospital_tenant_bootstrap->run($tenantDb, $tenantId, $payload);
    }

    private function quoteDb($name)
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
