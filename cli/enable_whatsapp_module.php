#!/usr/bin/env php
<?php

/**
 * Ensure WhatsApp Messaging module + WhatsApp Setting privilege exist on tenant DBs.
 *
 * Usage:
 *   php cli/enable_whatsapp_module.php --tenant=UUID
 *   php cli/enable_whatsapp_module.php --all
 */

require __DIR__ . '/provision_functions.php';

$tenantId = '';
$all = false;
foreach ($argv as $arg) {
    if ($arg === '--all') {
        $all = true;
    }
    if (strpos($arg, '--tenant=') === 0) {
        $tenantId = trim(substr($arg, 9));
    }
}

if (!$all && $tenantId === '') {
    fwrite(STDERR, "Usage: php cli/enable_whatsapp_module.php --tenant=UUID | --all\n");
    exit(1);
}

try {
    $cfg = hospital_cli_config();
    $central = hospital_cli_central_mysqli($cfg);
    $productCode = (string) $cfg['tenancy']['product_code'];
    $prefix = (string) $cfg['tenancy']['tenant_database_prefix'];
    $defaultCfg = $cfg['db']['default'];

    $tenants = array();
    if ($all) {
        if (!hospital_cli_table_exists($central, 'tenant_subscriptions')) {
            throw new RuntimeException('tenant_subscriptions table missing');
        }
        $stmt = $central->prepare('SELECT tenant_id, plan_code, metadata FROM tenant_subscriptions WHERE product_code = ?');
        $stmt->bind_param('s', $productCode);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tenants[] = $row;
        }
        $stmt->close();
    } else {
        $stmt = $central->prepare('SELECT tenant_id, plan_code, metadata FROM tenant_subscriptions WHERE product_code = ? AND tenant_id = ? LIMIT 1');
        $stmt->bind_param('ss', $productCode, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            throw new RuntimeException('Subscription not found for tenant ' . $tenantId);
        }
        $tenants[] = $row;
    }

    $ok = 0;
    $skipped = 0;
    foreach ($tenants as $sub) {
        $tid = (string) $sub['tenant_id'];
        $payload = array('plan_code' => (string) ($sub['plan_code'] ?? ''));
        $features = hospital_cli_resolve_plan_features($payload);
        $allowed = true;
        if (is_array($features) && array_key_exists('whatsapp_sms', $features)) {
            $v = $features['whatsapp_sms'];
            $allowed = !($v === false || $v === null || $v === 0 || $v === '0');
        }
        if (!$allowed) {
            echo "skip {$tid}: plan does not include whatsapp_sms\n";
            $skipped++;
            continue;
        }

        $dbName = $prefix . $tid;
        $mysqli = @new mysqli($defaultCfg['hostname'], $defaultCfg['username'], $defaultCfg['password'], $dbName);
        if ($mysqli->connect_error) {
            echo "skip {$tid}: cannot open DB {$dbName}\n";
            $skipped++;
            continue;
        }

        echo "tenant {$tid} ({$dbName}): enabling WhatsApp module\n";
        hospital_cli_ensure_whatsapp_module_rows($mysqli);
        hospital_cli_sync_plan_permissions($mysqli, $payload, $tid);
        $mysqli->close();
        $ok++;
    }

    $central->close();
    echo "Done. enabled={$ok} skipped={$skipped}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
