#!/usr/bin/env php
<?php

/**
 * Re-sync tenant modules + roles_permissions to the current subscription plan.
 *
 * Usage:
 *   php cli/sync_tenant_plan_permissions.php --tenant=UUID
 *   php cli/sync_tenant_plan_permissions.php --all
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
    fwrite(STDERR, "Usage: php cli/sync_tenant_plan_permissions.php --tenant=UUID | --all\n");
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
    foreach ($tenants as $sub) {
        $tid = (string) $sub['tenant_id'];
        $dbName = $prefix . $tid;
        $payload = array('plan_code' => (string) ($sub['plan_code'] ?? ''));
        if (!empty($sub['metadata'])) {
            $meta = json_decode((string) $sub['metadata'], true);
            if (is_array($meta) && isset($meta['features']) && is_array($meta['features'])) {
                $payload['features'] = $meta['features'];
            }
        }

        $mysqli = @new mysqli($defaultCfg['hostname'], $defaultCfg['username'], $defaultCfg['password'], $dbName);
        if ($mysqli->connect_error) {
            echo "skip {$tid}: cannot open DB {$dbName}\n";
            continue;
        }

        hospital_cli_sync_plan_permissions($mysqli, $payload, $tid);
        $mysqli->close();
        echo "synced {$tid} ({$payload['plan_code']})\n";
        $ok++;
    }

    $central->close();
    echo "Done. synced={$ok}\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
