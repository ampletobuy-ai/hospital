#!/usr/bin/env php
<?php

/**
 * Process pending hospital tenant provision jobs from shared central DB.
 * Usage: php cli/process_provision_jobs.php [--limit=5]
 */

require __DIR__ . '/provision_functions.php';

$limit = 5;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

try {
    $cfg = hospital_cli_config();
    $central = hospital_cli_central_mysqli($cfg);
    $productCode = (string) $cfg['tenancy']['product_code'];
    $jobs = hospital_cli_get_pending_jobs($central, $productCode, $limit);

    if (empty($jobs)) {
        echo "No hospital provision jobs to process.\n";
        exit(0);
    }

    $ok = 0;
    $failed = 0;
    foreach ($jobs as $job) {
        $tenantId = (string) ($job['tenant_id'] ?? '');
        echo "Processing tenant {$tenantId}…\n";
        try {
            hospital_cli_process_job($cfg, $central, $job);
            echo "  ready\n";
            $ok++;
        } catch (Exception $e) {
            echo '  failed: ' . $e->getMessage() . "\n";
            $failed++;
        }
    }

    echo "Done. ready={$ok} failed={$failed}\n";
    $central->close();
    exit($failed > 0 ? 1 : 0);
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
