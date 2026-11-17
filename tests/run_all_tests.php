<?php

/**
 * Master Test Runner: Runs all automated test suites across all project modules.
 */

/**
 * Known friendly descriptions for standard suites; others get auto-generated title.
 */
$knownTitles = [
    'test_store_isolation.php'             => 'Tahap 3: Marketplace & Multi-Tenancy Store Isolation',
    'test_stage4_and_5_audit.php'          => 'Tahap 4 & 5: Form Dynamic & Dashboard Aggregations',
    'test_security_and_statemachine.php'   => 'Tahap 6: Security, Auth & State Machine Guard',
    'test_maps_and_courier_location.php'   => 'Tahap 7: Courier Location & Duty Status Module',
    'test_payment_module.php'              => 'Tahap 8A: Online Payment Gateway (QRIS & Webhook)',
    'test_tracking_and_distance_matrix.php'=> 'Tahap 8B: Live Tracking Polling & Distance Calculator',
    'test_refund_module.php'               => 'Tahap 8C: Refund Module & Audit Events',
    'test_notification_module.php'         => 'Tahap 9: Notification Model, Triggers & Polling UI',
    'test_ai_assistant_module.php'         => 'Tahap 10: AI Chat Assistant & Guardrail System',
    'test_http_endpoints.php'              => 'Audit Endpoint HTTP & Routing Smoke Test',
    'test_omzet_transparency.php'          => 'Transparansi Asal-Usul & Perhitungan Omzet',
    'test_whatsapp_notifications.php'      => 'Sistem Notifikasi WhatsApp Otomatis',
    'test_ready_delivery_orders.php'       => 'Operasional Siap Diambil & Siap Diantar (Admin Toko)',
    'test_ai_key_reveal.php'               => 'Fitur Lihat API Key Terproteksi Password'
];

// Dynamically discover all test_*.php files in tests/
$allFiles = glob(__DIR__ . '/test_*.php');
sort($allFiles);

$testFiles = [];
foreach ($allFiles as $filePath) {
    $basename = basename($filePath);
    $desc = $knownTitles[$basename] ?? ucwords(str_replace(['test_', '_', '.php'], ['', ' ', ''], $basename));
    $testFiles[$basename] = $desc;
}


echo "\n";
echo "==============================================================================\n";
echo "                      LAUNDRYKU - MASTER TEST RUNNER                          \n";
echo "==============================================================================\n\n";

$suiteResults = [];
$totalGlobalPass = 0;
$totalGlobalFail = 0;

foreach ($testFiles as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (!file_exists($fullPath)) {
        continue;
    }

    echo ">>> MENJALANKAN SUITE: {$description} ({$file})...\n";
    $output = [];
    $returnVar = 0;
    exec("c:\\xampp\\php\\php.exe \"{$fullPath}\"", $output, $returnVar);

    $outText = implode("\n", $output);
    
    // Count passes and fails
    $passCount = substr_count($outText, '[PASS]');
    $failCount = substr_count($outText, '[FAIL]');

    // Special regex for suites that output "X PASS / Y Total" or "TOTAL RESULTS: X PASSED"
    if ($passCount === 0) {
        if (preg_match('/(\d+)\s+PASSED/i', $outText, $m)) {
            $passCount = (int)$m[1];
        } elseif (preg_match('/(\d+)\s+PASS\s*\//i', $outText, $m)) {
            $passCount = (int)$m[1];
        }
    }
    if ($failCount === 0) {
        if (preg_match('/(\d+)\s+FAILED/i', $outText, $m)) {
            $failCount = (int)$m[1];
        }
    }

    $suiteResults[] = [
        'file' => $file,
        'desc' => $description,
        'pass' => $passCount,
        'fail' => $failCount,
        'status' => $returnVar === 0 && $failCount === 0 ? 'SUCCESS' : 'FAILED'
    ];

    $totalGlobalPass += $passCount;
    $totalGlobalFail += $failCount;

    echo "    Hasil: {$passCount} PASS, {$failCount} FAIL (Status: " . ($returnVar === 0 ? "OK" : "ERROR") . ")\n\n";
}

echo "==============================================================================\n";
echo "                         REKAPITULASI HASIL SEMUA SUITE                       \n";
echo "==============================================================================\n";
printf("%-50s | %-8s | %-8s | %-8s\n", "Nama Test Suite", "Pass", "Fail", "Status");
echo str_repeat("-", 80) . "\n";

foreach ($suiteResults as $r) {
    printf("%-50s | %-8d | %-8d | %-8s\n", substr($r['desc'], 0, 50), $r['pass'], $r['fail'], $r['status']);
}

echo str_repeat("=", 80) . "\n";
printf("%-50s | %-8d | %-8d | %-8s\n", "TOTAL AKUMULASI SISTEM", $totalGlobalPass, $totalGlobalFail, ($totalGlobalFail === 0 ? "100% PASS" : "ADA GAGAL"));
echo "==============================================================================\n\n";

if ($totalGlobalFail > 0) {
    exit(1);
}
