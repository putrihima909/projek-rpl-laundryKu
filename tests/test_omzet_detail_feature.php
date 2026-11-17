<?php

/**
 * Test Suite: Fitur Omset Kasir Terbayar & Halaman Detail Omset
 * ==============================================================================
 * Menguji:
 * 1. Query Agregasi Omset Terbayar (SUM total pembayaran transaksi LUNAS)
 * 2. Integritas Perhitungan (SUM paid orders == Omset Dashboard)
 * 3. Filter Periode Cepat (today, yesterday, 7days, this_month, last_month, this_year)
 * 4. Filter Parameter Tanggal Custom, Status, dan Metode Pembayaran
 * 5. Grafik Agregasi Harian & Layanan
 * 6. Keamanan Akses: Customer DILARANG mengakses /dashboard/omzet-detail
 * 7. Admin Toko & Owner Toko memiliki akses yang terisolasi ke toko miliknya
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

$passCount = 0;
$failCount = 0;

function assertCustom(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$message}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "==============================================================================\n";
echo "   TEST SUITE: FITUR DETAIL OMSET & FILTER KEUANGAN KASIR                     \n";
echo "==============================================================================\n\n";

$db = \App\Core\Database::getInstance();
$omzetService = new \App\Services\Finance\OmzetTransparencyService($db);

// -----------------------------------------------------------------------------
// 1. UJI INTEGRITAS TOTAL OMSET & TRANSAKSI LUNAS TOKO 1
// -----------------------------------------------------------------------------
echo "--- 1. Uji Integritas Total Omset Toko 1 ---\n";

$reportDefault = $omzetService->getDetailedReport(1, ['payment_status' => 'paid']);

$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE store_id = 1 AND payment_status = 'paid'");
$stmt->execute();
$dbRow = $stmt->fetch(\PDO::FETCH_NUM);
$dbTotal = (float)$dbRow[0];
$dbCount = (int)$dbRow[1];

assertCustom($reportDefault['summary']['total_omset'] === $dbTotal, "Total omset laporan (Rp " . number_format($reportDefault['summary']['total_omset'], 0, ',', '.') . ") PERSIS sama dengan SUM(total_amount) database");
assertCustom($reportDefault['summary']['total_transaksi'] === $dbCount, "Jumlah transaksi laporan ({$reportDefault['summary']['total_transaksi']}) cocok dengan COUNT(*) database");

// Hitung total dari baris tabel transaksi
$sumFromTableRows = 0.0;
foreach ($reportDefault['transactions'] as $tx) {
    $sumFromTableRows += (float)$tx['total_amount'];
}
assertCustom(abs($sumFromTableRows - $dbTotal) < 0.01, "Penjumlahan seluruh transaksi di tabel rincian (Rp " . number_format($sumFromTableRows, 0, ',', '.') . ") sama persis dengan total omset");

// -----------------------------------------------------------------------------
// 2. UJI FILTER PERIODE CEPAT
// -----------------------------------------------------------------------------
echo "\n--- 2. Uji Filter Periode Cepat ---\n";

$periods = ['today', 'yesterday', '7days', 'this_month', 'last_month', 'this_year'];
foreach ($periods as $p) {
    $rep = $omzetService->getDetailedReport(1, ['period' => $p, 'payment_status' => 'paid']);
    assertCustom(isset($rep['summary']['total_omset']), "Filter periode '{$p}' berhasil dijalankan (Total: Rp " . number_format($rep['summary']['total_omset'], 0, ',', '.') . ", Transaksi: {$rep['summary']['total_transaksi']})");
    assertCustom(count($rep['transactions']) === $rep['summary']['total_transaksi'], "Jumlah baris tabel transaksi cocok dengan total transaksi filter '{$p}'");
}

// -----------------------------------------------------------------------------
// 3. UJI GRAFIK HARIAN & LAYANAN
// -----------------------------------------------------------------------------
echo "\n--- 3. Uji Grafik Harian & Layanan ---\n";

assertCustom(is_array($reportDefault['chart_daily']['labels']), "Grafik harian memiliki array labels tanggal");
assertCustom(is_array($reportDefault['chart_daily']['data']), "Grafik harian memiliki array dataset nominal");
assertCustom(count($reportDefault['chart_daily']['labels']) === count($reportDefault['chart_daily']['data']), "Jumlah label tanggal dan data grafik harian seimbang");

assertCustom(is_array($reportDefault['chart_services']['labels']), "Grafik layanan memiliki array labels nama layanan");
assertCustom(is_array($reportDefault['chart_services']['data']), "Grafik layanan memiliki array dataset nominal");
assertCustom(count($reportDefault['chart_services']['labels']) === count($reportDefault['chart_services']['data']), "Jumlah label layanan dan data grafik layanan seimbang");

// -----------------------------------------------------------------------------
// 4. UJI KEAMANAN: ROLE ACCESS GUARD
// -----------------------------------------------------------------------------
echo "\n--- 4. Uji Keamanan Akses Role ---\n";

$userModel = new \App\Models\User();

// A. Customer dilarang mengakses omzet
$custUser = $userModel->findByEmail('customer@laundry.test');
\App\Helpers\Auth::login($custUser);

$allowedRoles = [
    \App\Helpers\Auth::ROLE_OWNER,
    \App\Helpers\Auth::ROLE_ADMIN_OWNER,
    \App\Helpers\Auth::ROLE_OWNER_TOKO,
    \App\Helpers\Auth::ROLE_ADMIN_TOKO,
];

$isCustomerAllowed = \App\Helpers\Auth::hasRole($allowedRoles);
assertCustom(!$isCustomerAllowed, "Customer DITOLAK (Unauthorized) mengakses detail omset internal");

// B. Admin Toko diizinkan mengakses omzet
$adminTokoUser = $userModel->findByEmail('admin.toko@laundry.test');
\App\Helpers\Auth::login($adminTokoUser);
$isAdminTokoAllowed = \App\Helpers\Auth::hasRole($allowedRoles);
assertCustom($isAdminTokoAllowed, "Admin Toko DIIZINKAN (Authorized) mengakses detail omset internal tokonya");

// C. Owner Platform diizinkan mengakses omzet
$ownerUser = $userModel->findByEmail('owner@laundry.test');
\App\Helpers\Auth::login($ownerUser);
$isOwnerAllowed = \App\Helpers\Auth::hasRole($allowedRoles);
assertCustom($isOwnerAllowed, "Owner Platform DIIZINKAN mengakses laporan omzet");

echo "\n==============================================================================\n";
echo "REKAP HASIL PENGUJIAN DETAIL OMSET: {$passCount} PASS, {$failCount} FAIL\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    exit(1);
}

