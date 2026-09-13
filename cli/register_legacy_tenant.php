#!/usr/bin/env php
<?php

chdir(dirname(__DIR__));
require __DIR__ . '/provision_functions.php';
$cfg = hospital_cli_config();

$tenantId = '';
$adminEmail = hospital_env('HOSPITAL_ADMIN_EMAIL', 'ampletobuy@gmail.com');
$hospitalName = hospital_env('HOSPITAL_PRODUCT_NAME', 'Qubex Track Hospital');
$planCode = 'hospital_business';

foreach ($argv as $arg) {
    if (strpos($arg, '--tenant=') === 0) {
        $tenantId = trim(substr($arg, 9));
    }
    if (strpos($arg, '--email=') === 0) {
        $adminEmail = trim(substr($arg, 8));
    }
}

if ($tenantId === '') {
    $tenantId = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
}

$dbCfg = $cfg['db'];
$defaultCfg = $dbCfg['default'];
$prefix = (string) $cfg['tenancy']['tenant_database_prefix'];
$tenantDbName = $prefix . $tenantId;
$currentDb = (string) $defaultCfg['database'];

$mysqli = new mysqli($defaultCfg['hostname'], $defaultCfg['username'], $defaultCfg['password']);
if ($mysqli->connect_error) {
    fwrite(STDERR, "DB connection failed: {$mysqli->connect_error}\n");
    exit(1);
}

// Rename current install DB to tenant DB if not already prefixed.
if ($currentDb !== $tenantDbName) {
    $qCurrent = '`' . str_replace('`', '``', $currentDb) . '`';
    $qTenant = '`' . str_replace('`', '``', $tenantDbName) . '`';
    $exists = $mysqli->query("SHOW DATABASES LIKE '" . $mysqli->real_escape_string($tenantDbName) . "'");
    if ($exists && $exists->num_rows === 0) {
        if (!$mysqli->query("CREATE DATABASE {$qTenant} CHARACTER SET utf8 COLLATE utf8_general_ci")) {
            fwrite(STDERR, "Failed creating tenant DB: {$mysqli->error}\n");
            exit(1);
        }
        $tables = $mysqli->query("SHOW TABLES FROM {$qCurrent}");
        while ($tables && ($row = $tables->fetch_array())) {
            $table = $row[0];
            $qt = '`' . str_replace('`', '``', $table) . '`';
            $mysqli->query("CREATE TABLE {$qTenant}.{$qt} LIKE {$qCurrent}.{$qt}");
            $mysqli->query("INSERT INTO {$qTenant}.{$qt} SELECT * FROM {$qCurrent}.{$qt}");
        }
        echo "Copied {$currentDb} -> {$tenantDbName}\n";
    }
}

$tenantConn = new mysqli($defaultCfg['hostname'], $defaultCfg['username'], $defaultCfg['password'], $tenantDbName);
if ($tenantConn->connect_error) {
    fwrite(STDERR, "Tenant DB connection failed: {$tenantConn->connect_error}\n");
    exit(1);
}
$tenantConn->query("UPDATE sch_settings SET saas_key = '" . $tenantConn->real_escape_string($tenantId) . "' WHERE id = 1");

$centralCfg = $dbCfg['central'];
$central = hospital_cli_central_mysqli($cfg);
if (!hospital_cli_table_exists($central, 'tenants')) {
    fwrite(STDERR, "Central DB is missing SaaS tables. Run retail-pos central migrations first:\n");
    fwrite(STDERR, "  cd ../retail-pos && php artisan migrate --database=central --path=database/migrations/central\n");
    exit(1);
}

$now = date('Y-m-d H:i:s');
$emailEsc = $central->real_escape_string(strtolower($adminEmail));
$nameEsc = $central->real_escape_string($hospitalName);
$idEsc = $central->real_escape_string($tenantId);
$dataJson = $central->real_escape_string(json_encode(array(
    'store_name' => $hospitalName,
    'hospital_name' => $hospitalName,
    'product_code' => 'hospital',
    'provisioned_at' => date('c'),
)));

$central->query("INSERT INTO tenants (id, created_at, updated_at, data) VALUES ('{$idEsc}', '{$now}', '{$now}', '{$dataJson}')
    ON DUPLICATE KEY UPDATE updated_at = '{$now}', data = '{$dataJson}'");

$central->query("INSERT INTO tenant_user_mappings (tenant_id, email, product_code, is_primary_admin, created_at, updated_at)
    VALUES ('{$idEsc}', '{$emailEsc}', 'hospital', 1, '{$now}', '{$now}')
    ON DUPLICATE KEY UPDATE updated_at = '{$now}'");

$metadata = $central->real_escape_string(json_encode(array(
    'signup_source' => 'legacy_migration',
    'product_code' => 'hospital',
    'no_of_patient' => 5000,
    'no_of_staff' => 15,
    'storage_mb' => 20480,
)));
$central->query("INSERT INTO tenant_subscriptions (tenant_id, plan_code, billing_cycle, status, current_period_start, current_period_end, max_users, max_warehouses, product_code, metadata, created_at, updated_at)
    VALUES ('{$idEsc}', '{$planCode}', 'annual', 'active', '{$now}', DATE_ADD('{$now}', INTERVAL 1 YEAR), 15, 1, 'hospital', '{$metadata}', '{$now}', '{$now}')
    ON DUPLICATE KEY UPDATE updated_at = '{$now}'");

$payload = $central->real_escape_string(json_encode(array('hospital_name' => $hospitalName)));
$central->query("INSERT INTO tenant_provision_jobs (tenant_id, product_code, status, attempts, payload, created_at, updated_at)
    VALUES ('{$idEsc}', 'hospital', 'ready', 0, '{$payload}', '{$now}', '{$now}')
    ON DUPLICATE KEY UPDATE status = 'ready', updated_at = '{$now}'");

echo "Legacy tenant registered:\n";
echo "  tenant_id: {$tenantId}\n";
echo "  tenant_db: {$tenantDbName}\n";
echo "  admin_email: {$adminEmail}\n";
echo "Enable TENANCY_ENABLED=true in .env and set TENANT_TEMPLATE_DATABASE to a fresh template copy.\n";

$tenantConn->close();
$central->close();
$mysqli->close();
