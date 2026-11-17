<?php
/**
 * Automated Test Suite: Fitur Transparansi Asal-Usul & Perhitungan Omzet LaundryKu
 */

declare(strict_types=1);

// PSR-4 Autoloader
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Helpers/Config.php';

use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Services\Finance\OmzetTransparencyService;
use App\Models\Store;

$passed = 0;
$failed = 0;
$errors = [];

function assertTest(string $label, bool $condition, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
        $errors[] = $label . ($detail ? ": {$detail}" : '');
    }
}

function captureWarnings(callable $fn): array {
    $caught = [];
    set_error_handler(function (int $errno, string $errstr) use (&$caught): bool {
        $caught[] = $errstr;
        return true;
    }, E_ALL);
    $fn();
    restore_error_handler();
    return $caught;
}

echo "\n==============================================================================\n";
echo "   TEST SUITE: FITUR TRANSPARANSI ASAL-USUL PERHITUNGAN OMZET LAUNDRYKU      \n";
echo "==============================================================================\n\n";

$db = Database::getInstance();
$service = new OmzetTransparencyService($db);

// -----------------------------------------------------------------------------
// 1. Uji Akurasi Agregasi Platform & Rekonsiliasi Daftar Transaksi
// -----------------------------------------------------------------------------
echo "--- 1. Uji Akurasi Omzet Platform vs Sum Daftar Transaksi ---\n";
$platformData = $service->getOmzetTransparencyData(null);

assertTest("Platform data berhasil diambil", !empty($platformData));
assertTest("Cakupan adalah platform (is_platform = true)", $platformData['is_platform'] === true);

$omzetNominal = $platformData['omzet_terbayar_aktif']['nominal'] ?? 0;
$omzetCount = $platformData['omzet_terbayar_aktif']['count'] ?? 0;
assertTest("Omzet terbayar aktif lebih dari 0", $omzetNominal > 0);
assertTest("Jumlah pesanan lunas lebih dari 0", $omzetCount > 0);

// Sum manual dari daftar transaksi
$sumFromList = 0.0;
foreach ($platformData['transaksi_pembentuk_omzet'] as $tx) {
    $sumFromList += (float)$tx['total_amount'];
}
assertTest("Total nominal agregasi sama persis dengan sum daftar transaksi", abs($omzetNominal - $sumFromList) < 0.01, "Agregasi: {$omzetNominal}, Sum List: {$sumFromList}");
assertTest("Jumlah pesanan agregasi sama persis dengan count daftar transaksi", $omzetCount === count($platformData['transaksi_pembentuk_omzet']));

// -----------------------------------------------------------------------------
// 2. Uji Pemisahan Refund (Completed vs Belum Selesai)
// -----------------------------------------------------------------------------
echo "\n--- 2. Uji Pemisahan Refund Selesai vs Belum Selesai ---\n";
$refundSelesai = $platformData['refund_selesai'];
$refundBelum = $platformData['refund_belum_selesai'];

assertTest("Refund completed memiliki nominal valid", $refundSelesai['nominal'] >= 0);
assertTest("Refund completed memiliki count valid", $refundSelesai['count'] >= 0);

// Cek pemisahan status belum selesai
assertTest("Refund pending terpisah", isset($refundBelum['pending']['count']) && isset($refundBelum['pending']['amount']));
assertTest("Refund processing terpisah", isset($refundBelum['processing']['count']) && isset($refundBelum['processing']['amount']));
assertTest("Refund failed terpisah", isset($refundBelum['failed']['count']) && isset($refundBelum['failed']['amount']));

// Verifikasi langsung ke database
$dbRefundCompleted = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'completed'")->fetchColumn();
assertTest("Refund selesai service cocok dengan database aktual", abs($refundSelesai['nominal'] - $dbRefundCompleted) < 0.01);

$dbRefundPending = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'pending'")->fetchColumn();
assertTest("Refund pending service cocok dengan database aktual", abs($refundBelum['pending']['amount'] - $dbRefundPending) < 0.01);

// -----------------------------------------------------------------------------
// 3. Uji Transaksi yang Dikecualikan & Alasannya
// -----------------------------------------------------------------------------
echo "\n--- 3. Uji Transaksi yang Dikecualikan ---\n";
$dikecualikan = $platformData['transaksi_dikecualikan'];

assertTest("Unpaid orders terdata", isset($dikecualikan['unpaid']));
assertTest("Unpaid orders memiliki alasan yang jelas", !empty($dikecualikan['unpaid']['alasan']));
assertTest("Refunded orders terdata", isset($dikecualikan['refunded']));
assertTest("Refunded orders memiliki alasan yang jelas", !empty($dikecualikan['refunded']['alasan']));
assertTest("Cancelled orders terdata", isset($dikecualikan['cancelled']));

// -----------------------------------------------------------------------------
// 4. Uji Isolasi Data Antar-Toko (RBAC Enforcement)
// -----------------------------------------------------------------------------
echo "\n--- 4. Uji Isolasi Data Toko (Owner Toko 1 vs Toko 2) ---\n";
$store1Data = $service->getOmzetTransparencyData(1);
$store2Data = $service->getOmzetTransparencyData(2);

assertTest("Store 1 is_platform bernilai false", $store1Data['is_platform'] === false);
assertTest("Store 2 is_platform bernilai false", $store2Data['is_platform'] === false);

$s1Nominal = $store1Data['omzet_terbayar_aktif']['nominal'];
$s2Nominal = $store2Data['omzet_terbayar_aktif']['nominal'];

assertTest("Omzet Store 1 tidak sama dengan Omzet Store 2", $s1Nominal !== $s2Nominal, "Store 1: {$s1Nominal}, Store 2: {$s2Nominal}");
assertTest("Jumlah Omzet Toko 1 + Toko 2 sama persis dengan Omzet Platform", abs(($s1Nominal + $s2Nominal) - $omzetNominal) < 0.01, "S1+S2: " . ($s1Nominal + $s2Nominal) . ", Platform: {$omzetNominal}");

// Pastikan transaksi Store 1 tidak memuat data Toko 2
$hasLeak = false;
foreach ($store1Data['transaksi_pembentuk_omzet'] as $tx) {
    if (($tx['store_name'] ?? '') === 'Fresh & Clean Laundry') {
        $hasLeak = true;
    }
}
assertTest("Store 1 tidak membocorkan transaksi Toko 2", !$hasLeak);

// -----------------------------------------------------------------------------
// 5. Uji Rendering View Dashboard (Modal & Tombol Transparansi)
// -----------------------------------------------------------------------------
echo "\n--- 5. Uji Rendering View Dashboard (Bebas Warning / Notice) ---\n";

// A. View Owner Platform
$warns = captureWarnings(function () use ($platformData) {
    $user = ['id' => 1, 'name' => 'Budi Santoso'];
    $userCount = 18;
    $totalStores = 2;
    $activeStores = 2;
    $transparency = $platformData;
    $stats = [
        ['title' => 'Total Omset Terbayar', 'value' => 'Rp 1.352.600', 'icon' => 'fa-chart-line', 'color' => 'info', 'trend' => 'Transaksi', 'has_transparency' => true]
    ];
    ob_start();
    include __DIR__ . '/../app/Views/dashboard/owner.php';
    $html = ob_get_clean();
    assertTest("View owner.php memuat modal omzetDetailModal", str_contains($html, 'id="omzetDetailModal"'));
    assertTest("View owner.php memuat tombol Lihat Rincian Perhitungan", str_contains($html, 'Lihat Rincian Perhitungan'));
});
assertTest("Rendering view owner.php bebas PHP Warning/Notice", count($warns) === 0, count($warns) > 0 ? implode('; ', $warns) : '');

// B. View Owner Toko
$warns = captureWarnings(function () use ($store1Data) {
    $user = ['id' => 3, 'name' => 'Hendra Wijaya'];
    $store = ['id' => 1, 'name' => 'CleanMaster Laundry Express', 'address' => 'Jl. Merdeka No. 45'];
    $transparency = $store1Data;
    $stats = [
        ['title' => 'Omset Toko (Terbayar)', 'value' => 'Rp 1.332.600', 'icon' => 'fa-wallet', 'color' => 'success', 'trend' => 'Akumulasi', 'has_transparency' => true]
    ];
    ob_start();
    include __DIR__ . '/../app/Views/dashboard/owner_toko.php';
    $html = ob_get_clean();
    assertTest("View owner_toko.php memuat modal omzetDetailModal", str_contains($html, 'id="omzetDetailModal"'));
    assertTest("View owner_toko.php memuat tombol Lihat Rincian Perhitungan", str_contains($html, 'Lihat Rincian Perhitungan'));
});
assertTest("Rendering view owner_toko.php bebas PHP Warning/Notice", count($warns) === 0, count($warns) > 0 ? implode('; ', $warns) : '');

// -----------------------------------------------------------------------------
// Ringkasan
// -----------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "HASIL SUITE: {$passed} PASS, {$failed} FAIL\n";
echo "==============================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);

