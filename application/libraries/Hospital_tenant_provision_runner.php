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
        $skipSchema = array_map('strtolower', (array) $this->CI->config->item('skip_template_tables'));
        $skipData = array_map('strtolower', (array) $this->CI->config->item('skip_template_data_tables'));
        $copyWhitelist = array_map('strtolower', (array) $this->CI->config->item('copy_template_data_tables'));
        $mode = strtolower((string) $this->CI->config->item('template_data_copy_mode'));
        if ($mode !== 'whitelist') {
            $mode = 'blacklist';
        }

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
            if (in_array(strtolower($table), $skipSchema, true)) {
                continue;
            }
            $tables[] = $table;
            $qt = $this->quoteDb($table);
            $sql = "CREATE TABLE IF NOT EXISTS {$qTenant}.{$qt} LIKE {$qTemplate}.{$qt}";
            if (!$mysqli->query($sql)) {
                throw new RuntimeException("Clone schema failed for {$table}: " . $mysqli->error);
            }
        }

        $tablesToCopy = array();
        if ($mode === 'whitelist') {
            foreach ($copyWhitelist as $tableName) {
                foreach ($tables as $table) {
                    if (strtolower($table) === $tableName) {
                        $tablesToCopy[] = $table;
                        break;
                    }
                }
            }
        } else {
            foreach ($tables as $table) {
                if (!in_array(strtolower($table), $skipData, true)) {
                    $tablesToCopy[] = $table;
                }
            }
        }

        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tablesToCopy as $match) {
            $qt = $this->quoteDb($match);
            if (!$mysqli->query("TRUNCATE TABLE {$qTenant}.{$qt}")) {
                log_message('error', 'Tenant truncate failed for ' . $match . ': ' . $mysqli->error);
                continue;
            }
            if (!$mysqli->query("INSERT INTO {$qTenant}.{$qt} SELECT * FROM {$qTemplate}.{$qt}")) {
                log_message('error', 'Tenant data copy failed for ' . $match . ': ' . $mysqli->error);
            }
        }
        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    }

    private function applyMigrations($tenantDatabase)
    {
        // Schema is cloned from the template database. Additive CI migration classes
        // are not bootstrapped here (they require CI_Migration). Mark the tenant
        // migrations row at the latest numeric version present on disk when possible.
        try {
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
            $latest = 0;
            foreach ($files as $file) {
                if (preg_match('/(\d+)_/', basename($file), $matches)) {
                    $latest = max($latest, (int) $matches[1]);
                }
            }
            if ($latest > 0) {
                $tenantDb->update('migrations', array('version' => $latest));
            }
        } catch (Throwable $e) {
            log_message('error', 'Hospital tenant migration stamp skipped: ' . $e->getMessage());
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
