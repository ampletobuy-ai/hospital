<?php
/**
 * Upgrade legacy sch_settings seed (USD / $) to product defaults (INR / ₹).
 *
 * Updates the tenant template DB and every hospital_* tenant that is still
 * on the install seed values. Tenants that already chose another currency
 * are left untouched.
 *
 * Usage:
 *   php cli/fix_currency_inr_defaults.php
 *   php cli/fix_currency_inr_defaults.php --dry-run
 */

define('BASEPATH', true);
define('FCPATH', dirname(__DIR__) . '/');
define('APPPATH', FCPATH . 'application/');

require_once APPPATH . 'helpers/env_helper.php';

$dryRun = in_array('--dry-run', $argv, true);

$host = hospital_env('DB_HOST', '127.0.0.1');
$user = hospital_env('DB_USERNAME', 'root');
$pass = hospital_env('DB_PASSWORD', '');
$port = (int) hospital_env('DB_PORT', '3306');
$template = hospital_env('TENANT_TEMPLATE_DATABASE', hospital_env('DB_DATABASE', 'hospital'));
$prefix = hospital_env('TENANT_DATABASE_PREFIX', 'hospital_');
$central = hospital_env('DB_CENTRAL_DATABASE', 'trackpossystem');
$product = hospital_env('TENANCY_PRODUCT_CODE', 'hospital');

$mysqli = new mysqli($host, $user, $pass, null, $port);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

// Match install seed $ even when currency was already flipped to INR.
$sql = "UPDATE sch_settings
        SET currency = 'INR',
            currency_symbol = '₹',
            timezone = IF(timezone = 'UTC' OR timezone IS NULL OR timezone = '', 'Asia/Kolkata', timezone),
            date_format = IF(date_format = 'm/d/Y' OR date_format IS NULL OR date_format = '', 'd/m/Y', date_format)
        WHERE id = 1
          AND currency_symbol = '\$'
          AND (currency = 'USD' OR currency = 'INR' OR currency = '' OR currency IS NULL)";

$targets = array($template);

$centralCheck = $mysqli->query('SHOW DATABASES LIKE ' . "'" . $mysqli->real_escape_string($central) . "'");
if ($centralCheck && $centralCheck->num_rows > 0) {
    $subsExists = $mysqli->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = '" . $mysqli->real_escape_string($central) . "'
           AND table_name = 'tenant_subscriptions' LIMIT 1"
    );
    if ($subsExists && $subsExists->num_rows > 0) {
        $res = $mysqli->query(
            "SELECT tenant_id FROM `{$mysqli->real_escape_string($central)}`.tenant_subscriptions
             WHERE product_code = '" . $mysqli->real_escape_string($product) . "'"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $targets[] = $prefix . $row['tenant_id'];
            }
        }
    }
}

// Also catch orphan hospital_* DBs that match the prefix.
$dbRes = $mysqli->query('SHOW DATABASES LIKE ' . "'" . $mysqli->real_escape_string($prefix) . "%'");
if ($dbRes) {
    while ($row = $dbRes->fetch_array()) {
        $targets[] = $row[0];
    }
}

$targets = array_values(array_unique($targets));
$updated = 0;
$skipped = 0;

foreach ($targets as $dbName) {
    $check = $mysqli->query('SHOW DATABASES LIKE ' . "'" . $mysqli->real_escape_string($dbName) . "'");
    if (!$check || $check->num_rows === 0) {
        echo "SKIP missing {$dbName}\n";
        $skipped++;
        continue;
    }

    $tableCheck = $mysqli->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = '" . $mysqli->real_escape_string($dbName) . "'
           AND table_name = 'sch_settings' LIMIT 1"
    );
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo "SKIP no sch_settings in {$dbName}\n";
        $skipped++;
        continue;
    }

    $rowRes = $mysqli->query(
        "SELECT currency, currency_symbol FROM `{$mysqli->real_escape_string($dbName)}`.sch_settings WHERE id = 1 LIMIT 1"
    );
    $row = $rowRes ? $rowRes->fetch_assoc() : null;
    if (!$row) {
        echo "SKIP empty sch_settings in {$dbName}\n";
        $skipped++;
        continue;
    }

    $currency = (string) ($row['currency'] ?? '');
    $symbol = (string) ($row['currency_symbol'] ?? '');
    $isLegacySeed = ($symbol === '$' && ($currency === 'USD' || $currency === 'INR' || $currency === ''));
    if (!$isLegacySeed) {
        echo "OK   {$dbName} already {$currency} / {$symbol}\n";
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "DRY  would update {$dbName} {$currency}/$ → INR/₹\n";
        $updated++;
        continue;
    }

    $scopedSql = str_replace('UPDATE sch_settings', "UPDATE `{$mysqli->real_escape_string($dbName)}`.sch_settings", $sql);
    if (!$mysqli->query($scopedSql)) {
        fwrite(STDERR, "FAIL {$dbName}: {$mysqli->error}\n");
        continue;
    }
    echo "FIX  {$dbName} {$currency}/$ → INR/₹ (affected={$mysqli->affected_rows})\n";
    $updated++;
}

$mysqli->close();
echo ($dryRun ? 'Dry-run' : 'Done') . ": updated={$updated} skipped={$skipped}\n";
