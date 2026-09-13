<?php

function hospital_cli_config()
{
    if (!defined('APPPATH')) {
        define('APPPATH', dirname(__DIR__) . '/application/');
    }
    if (!defined('FCPATH')) {
        define('FCPATH', dirname(__DIR__) . '/');
    }
    if (!defined('BASEPATH')) {
        define('BASEPATH', dirname(__DIR__) . '/system/');
    }
    if (!defined('ENVIRONMENT')) {
        define('ENVIRONMENT', getenv('CI_ENV') ?: 'production');
    }

    require_once APPPATH . 'helpers/env_helper.php';
    hospital_env_boot(dirname(__DIR__));

    include APPPATH . 'config/database.php';
    include APPPATH . 'config/tenancy-config.php';

    return array(
        'db' => $db,
        'tenancy' => $config,
    );
}

function hospital_cli_central_mysqli(array $cfg)
{
    $central = $cfg['db']['central'];
    $mysqli = new mysqli($central['hostname'], $central['username'], $central['password'], $central['database']);
    if ($mysqli->connect_error) {
        throw new RuntimeException('Central DB connection failed: ' . $mysqli->connect_error);
    }

    return $mysqli;
}

function hospital_cli_table_exists(mysqli $mysqli, $table)
{
    $table = $mysqli->real_escape_string($table);
    $result = $mysqli->query("SHOW TABLES LIKE '{$table}'");

    return $result && $result->num_rows > 0;
}

function hospital_cli_get_pending_jobs(mysqli $central, $productCode, $limit = 5)
{
    if (!hospital_cli_table_exists($central, 'tenant_provision_jobs')) {
        return array();
    }

    $stmt = $central->prepare(
        'SELECT * FROM tenant_provision_jobs WHERE product_code = ? AND status = ? ORDER BY created_at ASC LIMIT ?'
    );
    $status = 'pending';
    $stmt->bind_param('ssi', $productCode, $status, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $jobs = array();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['payload'])) {
            $decoded = json_decode((string) $row['payload'], true);
            if (is_array($decoded)) {
                $row['payload'] = $decoded;
            }
        }
        $jobs[] = $row;
    }
    $stmt->close();

    return $jobs;
}

function hospital_cli_update_job(mysqli $central, $tenantId, $productCode, array $data)
{
    $sets = array();
    $values = array();
    $types = '';
    foreach ($data as $key => $value) {
        $sets[] = "`{$key}` = ?";
        $values[] = $value;
        $types .= 's';
    }
    $values[] = date('Y-m-d H:i:s');
    $types .= 's';
    $sets[] = '`updated_at` = ?';
    $values[] = $tenantId;
    $types .= 's';
    $values[] = $productCode;
    $types .= 's';

    $sql = 'UPDATE tenant_provision_jobs SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND product_code = ?';
    $stmt = $central->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

function hospital_cli_quote_db($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function hospital_cli_run_pipeline(array $cfg, $tenantId, array $payload)
{
    $default = $cfg['db']['default'];
    $templateDatabase = (string) $cfg['tenancy']['template_database'];
    $prefix = (string) $cfg['tenancy']['tenant_database_prefix'];
    $tenantDatabase = $prefix . $tenantId;
    $copyData = (array) ($cfg['tenancy']['copy_template_data_tables'] ?? array());

    if ($templateDatabase === '' || $tenantDatabase === '') {
        throw new RuntimeException('Template or tenant database name missing.');
    }

    $mysqli = new mysqli($default['hostname'], $default['username'], $default['password']);
    if ($mysqli->connect_error) {
        throw new RuntimeException('DDL connection failed: ' . $mysqli->connect_error);
    }

    $qTenant = hospital_cli_quote_db($tenantDatabase);
    if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS {$qTenant} CHARACTER SET utf8 COLLATE utf8_general_ci")) {
        throw new RuntimeException('CREATE DATABASE failed: ' . $mysqli->error);
    }

    $qTemplate = hospital_cli_quote_db($templateDatabase);
    $existing = $mysqli->query("SHOW TABLES FROM {$qTenant}");
    if ($existing) {
        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        while ($row = $existing->fetch_array()) {
            $table = $row[0];
            $mysqli->query('DROP TABLE IF EXISTS ' . $qTenant . '.' . hospital_cli_quote_db($table));
        }
        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    }

    $tablesResult = $mysqli->query("SHOW TABLES FROM {$qTemplate}");
    $tables = array();
    while ($tablesResult && ($row = $tablesResult->fetch_array())) {
        $table = (string) $row[0];
        $tables[] = $table;
        $qt = hospital_cli_quote_db($table);
        if (!$mysqli->query("CREATE TABLE IF NOT EXISTS {$qTenant}.{$qt} LIKE {$qTemplate}.{$qt}")) {
            throw new RuntimeException("Clone failed for {$table}: " . $mysqli->error);
        }
    }

    foreach ($copyData as $tableName) {
        $match = null;
        foreach ($tables as $table) {
            if (strcasecmp($table, (string) $tableName) === 0) {
                $match = $table;
                break;
            }
        }
        if ($match === null) {
            continue;
        }
        $qt = hospital_cli_quote_db($match);
        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        $mysqli->query("TRUNCATE TABLE {$qTenant}.{$qt}");
        $mysqli->query("INSERT INTO {$qTenant}.{$qt} SELECT * FROM {$qTemplate}.{$qt}");
        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    }

    hospital_cli_bootstrap_tenant($default, $tenantDatabase, $tenantId, $payload);
    $mysqli->close();
}

function hospital_cli_bootstrap_tenant(array $defaultCfg, $tenantDatabase, $tenantId, array $payload)
{
    $mysqli = new mysqli($defaultCfg['hostname'], $defaultCfg['username'], $defaultCfg['password'], $tenantDatabase);
    if ($mysqli->connect_error) {
        throw new RuntimeException('Tenant bootstrap connection failed: ' . $mysqli->connect_error);
    }

    $hospitalName = trim((string) ($payload['hospital_name'] ?? $payload['property_name'] ?? $payload['store_name'] ?? 'Qubex Track Hospital'));
    $adminEmail = strtolower(trim((string) ($payload['portal_admin_email'] ?? hospital_env('HOSPITAL_ADMIN_EMAIL', 'admin@example.com'))));
    $plainPassword = trim((string) ($payload['password_plain'] ?? hospital_env('HOSPITAL_ADMIN_PASSWORD', 'admin123')));

    $folderPath = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/';
    $baseUrl = hospital_env('HOSPITAL_BASE_URL', 'http://localhost/hospital/');

    $stmt = $mysqli->prepare('UPDATE sch_settings SET name = ?, base_url = ?, folder_path = ?, saas_key = ? WHERE id = 1');
    $stmt->bind_param('ssss', $hospitalName, $baseUrl, $folderPath, $tenantId);
    $stmt->execute();
    $stmt->close();

    $mysqli->query('DELETE FROM staff_roles');
    $mysqli->query('DELETE FROM staff');

    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $name = 'Super Admin';
    $employeeId = '9001';
    $langId = 4;
    $isActive = 1;
    $userId = 0;
    $stmt = $mysqli->prepare(
        'INSERT INTO staff (employee_id, lang_id, name, email, password, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issssii', $employeeId, $langId, $name, $adminEmail, $hash, $isActive, $userId);
    $stmt->execute();
    $staffId = (int) $stmt->insert_id;
    $stmt->close();

    if ($staffId > 0) {
        $roleId = 7;
        $active = 1;
        $stmt = $mysqli->prepare('INSERT INTO staff_roles (role_id, staff_id, is_active) VALUES (?, ?, ?)');
        $stmt->bind_param('iii', $roleId, $staffId, $active);
        $stmt->execute();
        $stmt->close();
    }

    $root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/';
    $uploadDirs = array(
        $root . 'uploads/staff_id_card/barcodes',
        $root . 'uploads/staff_id_card/qrcode',
        $root . 'uploads/patient_id_card/barcodes',
        $root . 'uploads/patient_id_card/qrcode',
        $root . 'uploads/staff_images',
        $root . 'uploads/staff_documents',
        $root . 'uploads/patient_images',
    );
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);
    }

    $mysqli->close();
}

function hospital_cli_mark_tenant_ready(mysqli $central, $tenantId)
{
    $now = date('Y-m-d H:i:s');
    $data = json_encode(array('provisioned_at' => date('c')));
    $stmt = $central->prepare('UPDATE tenants SET data = ?, updated_at = ? WHERE id = ?');
    $stmt->bind_param('sss', $data, $now, $tenantId);
    $stmt->execute();
    $stmt->close();
}

function hospital_cli_process_job(array $cfg, mysqli $central, array $job)
{
    $productCode = (string) $cfg['tenancy']['product_code'];
    $tenantId = (string) ($job['tenant_id'] ?? '');
    $payload = is_array($job['payload'] ?? null) ? $job['payload'] : array();

    hospital_cli_update_job($central, $tenantId, $productCode, array(
        'status' => 'processing',
        'attempts' => (string) ((int) ($job['attempts'] ?? 0) + 1),
        'started_at' => date('Y-m-d H:i:s'),
        'last_error' => null,
    ));

    try {
        hospital_cli_run_pipeline($cfg, $tenantId, $payload);
        hospital_cli_update_job($central, $tenantId, $productCode, array(
            'status' => 'ready',
            'finished_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ));
        hospital_cli_mark_tenant_ready($central, $tenantId);
    } catch (Exception $e) {
        hospital_cli_update_job($central, $tenantId, $productCode, array(
            'status' => 'failed',
            'finished_at' => date('Y-m-d H:i:s'),
            'last_error' => substr($e->getMessage(), 0, 2000),
        ));
        throw $e;
    }
}
