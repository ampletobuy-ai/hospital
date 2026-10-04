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

    $stmt = $mysqli->prepare('UPDATE sch_settings SET name = ?, base_url = ?, folder_path = ?, saas_key = ?, image = ?, mini_logo = ?, app_logo = ? WHERE id = 1');
    $image = 'qubex_track_logo.png';
    $miniLogo = 'qubex_track_app.png';
    $appLogo = 'qubex_track_app.png';
    $stmt->bind_param('sssssss', $hospitalName, $baseUrl, $folderPath, $tenantId, $image, $miniLogo, $appLogo);
    $stmt->execute();
    $stmt->close();

    // Product branding defaults for new tenants (Qubex Track).
    $mysqli->query("UPDATE front_cms_settings SET logo = './uploads/hospital_content/logo/qubex_track_logo.png', fav_icon = './uploads/hospital_content/logo/qubex_track_favicon.png' WHERE id = 1");

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

    hospital_cli_sync_plan_permissions($mysqli, $payload, $tenantId);
    $mysqli->close();
}

/**
 * Disable plan-locked modules and clear matching roles_permissions after clone/bootstrap.
 *
 * @param array<string, mixed> $payload
 */
function hospital_cli_sync_plan_permissions(mysqli $mysqli, array $payload, $tenantId)
{
    $features = hospital_cli_resolve_plan_features($payload);
    if ($features === null) {
        return;
    }

    $moduleMap = array(
        'opd' => 'opd',
        'patient' => 'patient_registration',
        'appointment' => 'appointment',
        'bill' => 'billing',
        'ipd' => 'ipd',
        'pharmacy' => 'pharmacy',
        'pathology' => 'laboratory',
        'radiology' => 'laboratory',
        'blood_bank' => 'ipd',
        'ambulance' => 'ipd',
        'live_consultation' => 'ipd',
        'inventory' => 'inventory',
        'tpa_management' => 'tpa_insurance',
        'referral' => 'doctor_commission',
        'whatsapp_messaging' => 'whatsapp_sms',
        'communicate' => 'whatsapp_sms',
        'duty_roster' => 'duty_roster',
        'front_cms' => 'customization',
    );

    $can = function ($key) use ($features) {
        if (!array_key_exists($key, $features)) {
            return true;
        }
        $value = $features[$key];

        return !($value === false || $value === null || $value === 0 || $value === '0');
    };

    if (hospital_cli_table_exists($mysqli, 'permission_group')) {
        $result = $mysqli->query('SELECT id, short_code, is_active FROM permission_group');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $short = (string) ($row['short_code'] ?? '');
                if ($short === '' || !isset($moduleMap[$short])) {
                    continue;
                }
                $allowed = $can($moduleMap[$short]);
                if ($short === 'front_cms') {
                    $allowed = $can('customization') && !in_array((string) ($features['customization'] ?? ''), array('limited', '0', ''), true);
                }
                $active = $allowed ? 1 : 0;
                $id = (int) $row['id'];
                $mysqli->query("UPDATE permission_group SET is_active = {$active} WHERE id = {$id}");
            }
            $result->free();
        }
    }

    if (hospital_cli_table_exists($mysqli, 'permission_patient')) {
        $result = $mysqli->query('SELECT id, permission_group_short_code FROM permission_patient');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $short = (string) ($row['permission_group_short_code'] ?? '');
                if ($short === '' || !isset($moduleMap[$short])) {
                    continue;
                }
                $allowed = $can($moduleMap[$short]) ? 1 : 0;
                $id = (int) $row['id'];
                $mysqli->query("UPDATE permission_patient SET is_active = {$allowed} WHERE id = {$id}");
            }
            $result->free();
        }
    }

    // Clear role grants for categories that belong to disabled modules (prefix / exact map subset).
    if (hospital_cli_table_exists($mysqli, 'permission_category') && hospital_cli_table_exists($mysqli, 'roles_permissions')) {
        $lockedPrefixes = array();
        if (!$can('pharmacy')) {
            $lockedPrefixes = array_merge($lockedPrefixes, array('pharmacy_', 'medicine', 'import_medicine', 'dosage_', 'stock_report', 'expiry_medicine'));
        }
        if (!$can('ipd')) {
            $lockedPrefixes = array_merge($lockedPrefixes, array('ipd', 'bed', 'floor', 'nurse_note', 'consultant_register', 'discharged_patients', 'blood_', 'ambulance', 'live_consult', 'live_meeting'));
        }
        if (!$can('laboratory')) {
            $lockedPrefixes = array_merge($lockedPrefixes, array('pathology_', 'radiology_'));
        }
        if (!$can('tpa_insurance')) {
            $lockedPrefixes = array_merge($lockedPrefixes, array('tpa_', 'organisation'));
        }
        if (!$can('doctor_commission')) {
            $lockedPrefixes = array_merge($lockedPrefixes, array('referral_'));
        }
        if (!$can('whatsapp_sms')) {
            $lockedPrefixes = array_merge($lockedPrefixes, array('email_sms', 'sms_setting', 'send_credential'));
        }
        if (!$can('duty_roster')) {
            $lockedPrefixes = array_merge($lockedPrefixes, array('duty_roster', 'roster_'));
        }

        if (!empty($lockedPrefixes)) {
            $result = $mysqli->query('SELECT id, short_code FROM permission_category');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $code = (string) ($row['short_code'] ?? '');
                    if ($code === '') {
                        continue;
                    }
                    $locked = false;
                    foreach ($lockedPrefixes as $prefix) {
                        if ($code === $prefix || strpos($code, $prefix) === 0) {
                            $locked = true;
                            break;
                        }
                    }
                    if (!$locked) {
                        continue;
                    }
                    $catId = (int) $row['id'];
                    $mysqli->query(
                        "UPDATE roles_permissions SET can_view = 0, can_add = 0, can_edit = 0, can_delete = 0 WHERE perm_cat_id = {$catId}"
                    );
                }
                $result->free();
            }
        }
    }
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>|null null = do not enforce
 */
function hospital_cli_resolve_plan_features(array $payload)
{
    if (isset($payload['features']) && is_array($payload['features'])) {
        return $payload['features'];
    }

    include APPPATH . 'config/hospital_portal.php';
    $plans = isset($config['hospital_plans']) && is_array($config['hospital_plans'])
        ? $config['hospital_plans']
        : array();

    $planCode = (string) ($payload['plan_code'] ?? '');
    if ($planCode === '' || $planCode === 'trial') {
        $planCode = isset($config['default_plan_code']) ? (string) $config['default_plan_code'] : 'hospital_business';
    }

    if ($planCode !== '' && isset($plans[$planCode]['features']) && is_array($plans[$planCode]['features'])) {
        return $plans[$planCode]['features'];
    }

    return null;
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
