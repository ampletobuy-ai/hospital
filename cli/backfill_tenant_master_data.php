<?php
/**
 * Backfill master/reference rows from the template DB into an existing tenant DB.
 *
 * Usage:
 *   php cli/backfill_tenant_master_data.php da9f9f43-3db0-4131-97b3-a12b28a224ea
 *   php cli/backfill_tenant_master_data.php --all
 */

define('BASEPATH', true);
define('FCPATH', dirname(__DIR__) . '/');
define('APPPATH', FCPATH . 'application/');

require_once APPPATH . 'helpers/env_helper.php';
require_once APPPATH . 'config/tenancy-config.php';

$arg = isset($argv[1]) ? trim((string) $argv[1]) : '';
if ($arg === '') {
    fwrite(STDERR, "Usage: php cli/backfill_tenant_master_data.php <tenant_uuid|--all>\n");
    exit(1);
}

$host = hospital_env('DB_HOST', '127.0.0.1');
$user = hospital_env('DB_USERNAME', 'root');
$pass = hospital_env('DB_PASSWORD', '');
$port = (int) hospital_env('DB_PORT', '3306');
$template = hospital_env('TENANT_TEMPLATE_DATABASE', hospital_env('DB_DATABASE', 'hospital'));
$prefix = hospital_env('TENANT_DATABASE_PREFIX', 'hospital_');
$central = hospital_env('DB_CENTRAL_DATABASE', 'trackpossystem');

$mysqli = new mysqli($host, $user, $pass, null, $port);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . "\n");
    exit(1);
}

$skipData = array_map('strtolower', (array) $config['skip_template_data_tables']);
$tenants = array();

if ($arg === '--all') {
    $res = $mysqli->query("SELECT id FROM `{$central}`.tenants WHERE product_code = 'hospital'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $tenants[] = $row['id'];
        }
    }
} else {
    $tenants[] = $arg;
}

function quoteIdent($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

foreach ($tenants as $tenantId) {
    $tenantDb = $prefix . $tenantId;
    $check = $mysqli->query('SHOW DATABASES LIKE ' . "'" . $mysqli->real_escape_string($tenantDb) . "'");
    if (!$check || $check->num_rows === 0) {
        echo "SKIP missing DB {$tenantDb}\n";
        continue;
    }

    echo "Backfilling {$tenantDb}...\n";
    $tablesRes = $mysqli->query('SHOW TABLES FROM ' . quoteIdent($template));
    if (!$tablesRes) {
        fwrite(STDERR, "Failed listing template tables\n");
        exit(1);
    }

    $copied = 0;
    $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
    while ($row = $tablesRes->fetch_array()) {
        $table = (string) $row[0];
        if (in_array(strtolower($table), $skipData, true)) {
            continue;
        }

        $qt = quoteIdent($table);
        $qTemplate = quoteIdent($template);
        $qTenant = quoteIdent($tenantDb);

        // Only fill when tenant table is empty (preserve any tenant edits).
        $cntRes = $mysqli->query("SELECT COUNT(*) AS c FROM {$qTenant}.{$qt}");
        $cnt = $cntRes ? (int) $cntRes->fetch_assoc()['c'] : -1;
        if ($cnt !== 0) {
            continue;
        }

        if (!$mysqli->query("INSERT INTO {$qTenant}.{$qt} SELECT * FROM {$qTemplate}.{$qt}")) {
            echo "  WARN {$table}: {$mysqli->error}\n";
            continue;
        }
        $copied++;
        echo "  + {$table}\n";
    }
    $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    echo "Done {$tenantDb} ({$copied} tables seeded)\n";
}

echo "Finished.\n";
