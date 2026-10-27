#!/usr/bin/env php
<?php

/**
 * Upsert central message_templates for product_code=hospital and optionally
 * backfill tenant notification_setting template ids.
 *
 * Usage:
 *   php cli/seed_hospital_message_templates.php
 *   php cli/seed_hospital_message_templates.php --all-tenants
 *   php cli/seed_hospital_message_templates.php --tenant=UUID
 */

require __DIR__ . '/provision_functions.php';

$allTenants = false;
$tenantId = '';
foreach ($argv as $arg) {
    if ($arg === '--all-tenants' || $arg === '--all') {
        $allTenants = true;
    }
    if (strpos($arg, '--tenant=') === 0) {
        $tenantId = trim(substr($arg, 9));
    }
}

try {
    $cfg = hospital_cli_config();
    $central = hospital_cli_central_mysqli($cfg);
    $productCode = 'hospital';
    $prefix = (string) $cfg['tenancy']['tenant_database_prefix'];
    $defaultCfg = $cfg['db']['default'];

    hospital_cli_ensure_central_messaging_tables($central);

    require_once APPPATH . 'config/hospital_message_catalog.php';
    $definitions = hospital_message_catalog_definitions();
    if (!is_array($definitions) || empty($definitions)) {
        throw new RuntimeException('hospital_message_catalog_definitions() returned empty');
    }

    $legacyKeys = array('appointment_booked', 'appointment_reminder', 'prescription_ready');
    $legacyIn = "'" . implode("','", array_map(array($central, 'real_escape_string'), $legacyKeys)) . "'";
    $central->query(
        "DELETE FROM message_templates WHERE product_code = '" . $central->real_escape_string($productCode) . "' AND `key` IN ({$legacyIn})"
    );
    echo 'Removed legacy hospital stub templates: ' . $central->affected_rows . "\n";

    $now = date('Y-m-d H:i:s');
    $inserted = 0;
    $updated = 0;
    foreach ($definitions as $row) {
        $key = (string) $row['key'];
        $channel = (string) $row['channel'];
        $name = (string) $row['name'];
        $body = (string) $row['body'];
        $variablesJson = json_encode(array_values($row['variables']));
        $msg91Name = $productCode . '_' . $key . '_' . $channel;

        $stmt = $central->prepare(
            'SELECT id, name, body, variables, msg91_template_name FROM message_templates WHERE product_code = ? AND `key` = ? AND channel = ? LIMIT 1'
        );
        $stmt->bind_param('sss', $productCode, $key, $channel);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $same = (
                (string) $existing['name'] === $name
                && (string) $existing['body'] === $body
                && (string) $existing['variables'] === $variablesJson
                && (string) $existing['msg91_template_name'] === $msg91Name
            );
            if ($same) {
                continue;
            }
            $upd = $central->prepare(
                'UPDATE message_templates SET name = ?, body = ?, variables = ?, msg91_template_name = ?, msg91_language = ?, updated_at = ? WHERE id = ?'
            );
            $lang = 'en';
            $id = (int) $existing['id'];
            $upd->bind_param('ssssssi', $name, $body, $variablesJson, $msg91Name, $lang, $now, $id);
            $upd->execute();
            $upd->close();
            $updated++;
            continue;
        }

        $ins = $central->prepare(
            'INSERT INTO message_templates (product_code, `key`, name, channel, body, variables, msg91_template_name, msg91_language, msg91_namespace, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)'
        );
        $lang = 'en';
        $status = 'pending';
        $ins->bind_param(
            'sssssssssss',
            $productCode,
            $key,
            $name,
            $channel,
            $body,
            $variablesJson,
            $msg91Name,
            $lang,
            $status,
            $now,
            $now
        );
        $ins->execute();
        $ins->close();
        $inserted++;
    }

    echo "Central templates: inserted={$inserted} updated={$updated} catalog=" . count($definitions) . "\n";

    if (!$allTenants && $tenantId === '') {
        $central->close();
        echo "Done (central only). Pass --all-tenants or --tenant=UUID to backfill notification_setting.\n";
        exit(0);
    }

    $tenants = array();
    if ($allTenants) {
        $productFilter = (string) $cfg['tenancy']['product_code'];
        $stmt = $central->prepare('SELECT tenant_id FROM tenant_subscriptions WHERE product_code = ?');
        $stmt->bind_param('s', $productFilter);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tenants[] = (string) $row['tenant_id'];
        }
        $stmt->close();
    } else {
        $tenants[] = $tenantId;
    }

    $ok = 0;
    $skipped = 0;
    foreach ($tenants as $tid) {
        $dbName = $prefix . $tid;
        $mysqli = @new mysqli($defaultCfg['hostname'], $defaultCfg['username'], $defaultCfg['password'], $dbName);
        if ($mysqli->connect_error) {
            echo "skip {$tid}: cannot open DB {$dbName}\n";
            $skipped++;
            continue;
        }
        if (!hospital_cli_table_exists($mysqli, 'notification_setting')) {
            echo "skip {$tid}: notification_setting missing\n";
            $mysqli->close();
            $skipped++;
            continue;
        }

        $hasWhatsappCol = hospital_cli_column_exists($mysqli, 'notification_setting', 'whatsapp_template_id');
        $hasTemplateCol = hospital_cli_column_exists($mysqli, 'notification_setting', 'template_id');
        if (!$hasTemplateCol && !$hasWhatsappCol) {
            echo "skip {$tid}: no template id columns\n";
            $mysqli->close();
            $skipped++;
            continue;
        }

        $types = array();
        foreach ($definitions as $def) {
            $types[$def['key']] = true;
        }

        // Prefer WhatsApp catalog body for notification_setting.template (email / local send preview).
        // SMS/WhatsApp MSG91 content still comes from central message_templates per channel.
        $templateBodies = array();
        foreach ($definitions as $def) {
            $key = (string) ($def['key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (($def['channel'] ?? '') === 'whatsapp') {
                $templateBodies[$key] = (string) $def['body'];
            } elseif (($def['channel'] ?? '') === 'sms' && !isset($templateBodies[$key])) {
                $templateBodies[$key] = (string) $def['body'];
            }
        }

        $updatedRows = 0;
        foreach (array_keys($types) as $type) {
            $smsId = $productCode . '_' . $type . '_sms';
            $waId = $productCode . '_' . $type . '_whatsapp';
            $sets = array();
            $params = array();
            $bind = '';

            if ($hasTemplateCol) {
                $sets[] = 'template_id = ?';
                $params[] = $smsId;
                $bind .= 's';
            }
            if ($hasWhatsappCol) {
                $sets[] = 'whatsapp_template_id = ?';
                $params[] = $waId;
                $bind .= 's';
            }
            if (isset($templateBodies[$type]) && hospital_cli_column_exists($mysqli, 'notification_setting', 'template')) {
                $sets[] = 'template = ?';
                $params[] = $templateBodies[$type];
                $bind .= 's';
            }
            if (empty($sets)) {
                continue;
            }
            $sql = 'UPDATE notification_setting SET ' . implode(', ', $sets) . ' WHERE type = ?';
            $params[] = $type;
            $bind .= 's';

            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param($bind, ...$params);
            $stmt->execute();
            $updatedRows += $stmt->affected_rows;
            $stmt->close();
        }

        echo "tenant {$tid}: backfilled notification_setting rows_touched={$updatedRows}\n";
        $mysqli->close();
        $ok++;
    }

    $central->close();
    echo "Done. tenants_ok={$ok} skipped={$skipped}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * @param mysqli $mysqli
 * @param string $table
 * @param string $column
 */
function hospital_cli_column_exists(mysqli $mysqli, $table, $column)
{
    $table = $mysqli->real_escape_string($table);
    $column = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");

    return $result && $result->num_rows > 0;
}

/**
 * Create central messaging tables if retail-pos migrations have not been run yet.
 */
function hospital_cli_ensure_central_messaging_tables(mysqli $central)
{
    if (!hospital_cli_table_exists($central, 'message_templates')) {
        $ok = $central->query(
            "CREATE TABLE message_templates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_code VARCHAR(32) NOT NULL DEFAULT 'pos',
                `key` VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                channel VARCHAR(16) NOT NULL,
                body TEXT NOT NULL,
                variables JSON NULL,
                msg91_template_name VARCHAR(128) NOT NULL,
                msg91_language VARCHAR(16) NOT NULL DEFAULT 'en',
                msg91_namespace VARCHAR(255) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY message_templates_product_key_channel_unique (product_code, `key`, channel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        if (!$ok) {
            throw new RuntimeException('Failed creating message_templates: ' . $central->error);
        }
        echo "Created message_templates\n";
    }

    if (!hospital_cli_table_exists($central, 'tenant_message_preferences')) {
        $ok = $central->query(
            "CREATE TABLE tenant_message_preferences (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id VARCHAR(64) NOT NULL,
                product_code VARCHAR(32) NOT NULL DEFAULT 'pos',
                sms_enabled TINYINT(1) NOT NULL DEFAULT 1,
                whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 1,
                disabled_template_keys JSON NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY tenant_message_preferences_tenant_product_unique (tenant_id, product_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        if (!$ok) {
            throw new RuntimeException('Failed creating tenant_message_preferences: ' . $central->error);
        }
        echo "Created tenant_message_preferences\n";
    }

    if (!hospital_cli_table_exists($central, 'message_usage')) {
        $ok = $central->query(
            "CREATE TABLE message_usage (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id VARCHAR(64) NOT NULL,
                product_code VARCHAR(32) NOT NULL DEFAULT 'pos',
                channel VARCHAR(16) NOT NULL,
                template_key VARCHAR(64) NOT NULL,
                recipient VARCHAR(32) NOT NULL,
                segments SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                provider_message_id VARCHAR(255) NULL,
                status VARCHAR(16) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY message_usage_tenant_id_index (tenant_id),
                KEY message_usage_channel_index (channel),
                KEY message_usage_status_index (status),
                KEY message_usage_tenant_channel_created_index (tenant_id, channel, created_at),
                KEY message_usage_product_tenant_channel_created_index (product_code, tenant_id, channel, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        if (!$ok) {
            throw new RuntimeException('Failed creating message_usage: ' . $central->error);
        }
        echo "Created message_usage\n";
    }
}
