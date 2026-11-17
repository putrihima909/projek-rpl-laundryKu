<?php

/**
 * Script Verifikasi AiDataProvider:
 * Memeriksa getStaffContext untuk admin.owner@laundry.test dan admin.toko@laundry.test,
 * lalu membandingkan omset dan jumlah pesanan dengan query langsung ke database.
 */

// 1. PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

// 2. Load Environment
\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Helpers/Config.php';

use App\Core\Database;
use App\Models\Store;
use App\Services\Ai\AiDataProvider;

echo "==============================================================================\n";
echo "       VERIFIKASI SUMBER DATA AI ASSISTANT (AiDataProvider)                   \n";
echo "==============================================================================\n\n";

$db = Database::getInstance();
$provider = new AiDataProvider($db);

$totalPass = 0;
$totalFail = 0;

function assertMatch(string $label, $expected, $actual): void {
    global $totalPass, $totalFail;
    $match = ($expected === $actual || (is_numeric($expected) && is_numeric($actual) && abs((float)$expected - (float)$actual) < 0.001));
    if ($match) {
        echo "  [PASS] {$label}: " . json_encode($actual) . "\n";
        $totalPass++;
    } else {
        echo "  [FAIL] {$label}: Expected " . json_encode($expected) . ", Got " . json_encode($actual) . "\n";
        $totalFail++;
    }
}

// -----------------------------------------------------------------------------
// 1. Verifikasi admin.owner@laundry.test (Cakupan Platform)
// -----------------------------------------------------------------------------
echo "------------------------------------------------------------------------------\n";
echo "1. UJI AKUN: admin.owner@laundry.test (Role: admin_owner - Cakupan Platform)\n";
echo "------------------------------------------------------------------------------\n";

$stmt = $db->prepare("SELECT u.id, u.email, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = :email LIMIT 1");
$stmt->execute(['email' => 'admin.owner@laundry.test']);
$ownerUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ownerUser) {
    echo "ERROR: Akun admin.owner@laundry.test tidak ditemukan di database!\n";
    exit(1);
}

$ownerContext = $provider->getStaffContext((int)$ownerUser['id'], $ownerUser['role_name']);

echo "\n>>> HASIL getStaffContext (admin.owner):\n";
echo json_encode($ownerContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

echo ">>> Verifikasi Angka vs Query Langsung Database:\n";
// Direct DB queries (Platform)
$dbDirectTotalOmset = (float) $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$dbDirectTotalOrders = (int) $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$dbDirectOrdersToday = (int) $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$dbDirectOmsetToday = (float) $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn();
$dbDirectOrdersMonth = (int) $db->query("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();
$dbDirectOmsetMonth = (float) $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();

assertMatch("Cakupan adalah 'platform'", 'platform', $ownerContext['cakupan'] ?? null);
assertMatch("Store ID adalah NULL", null, $ownerContext['store_id'] ?? null);
assertMatch("Total Omset Platform (Dashboard criteria)", $dbDirectTotalOmset, $ownerContext['total_omset'] ?? null);
assertMatch("Total Pesanan Platform (Dashboard criteria)", $dbDirectTotalOrders, $ownerContext['total_pesanan'] ?? null);
assertMatch("Pesanan Hari Ini Platform", $dbDirectOrdersToday, $ownerContext['pesanan_hari_ini'] ?? null);
assertMatch("Omset Hari Ini Platform", $dbDirectOmsetToday, $ownerContext['omset_hari_ini'] ?? null);
assertMatch("Pesanan Bulan Ini Platform", $dbDirectOrdersMonth, $ownerContext['pesanan_bulan_ini'] ?? null);
assertMatch("Omset Bulan Ini Platform", $dbDirectOmsetMonth, $ownerContext['omset_bulan_ini'] ?? null);

// -----------------------------------------------------------------------------
// 2. Verifikasi admin.toko@laundry.test (Cakupan Toko)
// -----------------------------------------------------------------------------
echo "\n------------------------------------------------------------------------------\n";
echo "2. UJI AKUN: admin.toko@laundry.test (Role: admin_toko - Cakupan Toko)\n";
echo "------------------------------------------------------------------------------\n";

$stmt = $db->prepare("SELECT u.id, u.email, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = :email LIMIT 1");
$stmt->execute(['email' => 'admin.toko@laundry.test']);
$storeUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$storeUser) {
    echo "ERROR: Akun admin.toko@laundry.test tidak ditemukan di database!\n";
    exit(1);
}

$storeModel = new Store();
$resolvedStore = $storeModel->getStoreByOwner((int)$storeUser['id']);
$expectedStoreId = $resolvedStore ? (int)$resolvedStore['id'] : null;

$storeContext = $provider->getStaffContext((int)$storeUser['id'], $storeUser['role_name']);

echo "\n>>> HASIL getStaffContext (admin.toko):\n";
echo json_encode($storeContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

echo ">>> Verifikasi Angka vs Query Langsung Database:\n";
// Direct DB queries (Store)
$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE store_id = :sid AND payment_status = 'paid'");
$stmt->execute(['sid' => $expectedStoreId]);
$dbDirectStoreOmset = (float) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid");
$stmt->execute(['sid' => $expectedStoreId]);
$dbDirectStoreOrders = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid AND DATE(created_at) = CURDATE()");
$stmt->execute(['sid' => $expectedStoreId]);
$dbDirectStoreOrdersToday = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE store_id = :sid AND payment_status = 'paid' AND DATE(created_at) = CURDATE()");
$stmt->execute(['sid' => $expectedStoreId]);
$dbDirectStoreOmsetToday = (float) $stmt->fetchColumn();

assertMatch("Cakupan adalah 'toko'", 'toko', $storeContext['cakupan'] ?? null);
assertMatch("Store ID sesuai", $expectedStoreId, $storeContext['store_id'] ?? null);
assertMatch("Nama Toko terisi", $resolvedStore['name'], $storeContext['nama_toko'] ?? null);
assertMatch("Total Omset Toko (Dashboard criteria)", $dbDirectStoreOmset, $storeContext['total_omset'] ?? null);
assertMatch("Total Pesanan Toko (Dashboard criteria)", $dbDirectStoreOrders, $storeContext['total_pesanan'] ?? null);
assertMatch("Pesanan Hari Ini Toko", $dbDirectStoreOrdersToday, $storeContext['pesanan_hari_ini'] ?? null);
assertMatch("Omset Hari Ini Toko", $dbDirectStoreOmsetToday, $storeContext['omset_hari_ini'] ?? null);

// -----------------------------------------------------------------------------
// 3. Verifikasi Keamanan: Penolakan Role Non-Staff (Customer & Kurir)
// -----------------------------------------------------------------------------
echo "\n------------------------------------------------------------------------------\n";
echo "3. UJI KEAMANAN: Penolakan Akses Role Non-Staff (Customer & Kurir)\n";
echo "------------------------------------------------------------------------------\n";

$customerContext = $provider->getStaffContext(6, 'customer');
assertMatch("Role customer harus mengembalikan NULL", null, $customerContext);

$kurirContext = $provider->getStaffContext(5, 'kurir');
assertMatch("Role kurir harus mengembalikan NULL", null, $kurirContext);

// -----------------------------------------------------------------------------
// 4. Verifikasi Privasi: Tidak Ada Data Pribadi User
// -----------------------------------------------------------------------------
echo "\n------------------------------------------------------------------------------\n";
echo "4. UJI PRIVASI DATA: Memastikan Tidak Ada Kebocoran Data Pribadi\n";
echo "------------------------------------------------------------------------------\n";

function checkNoPrivateData(array $data, array $forbiddenKeys = ['password', 'email', 'phone', 'token', 'google_id', 'avatar']): bool {
    foreach ($data as $k => $v) {
        if (in_array(strtolower((string)$k), $forbiddenKeys, true)) {
            return false;
        }
        if (is_array($v) && !checkNoPrivateData($v, $forbiddenKeys)) {
            return false;
        }
    }
    return true;
}

$safeOwner = checkNoPrivateData($ownerContext);
assertMatch("Context admin.owner bebas dari data pribadi (email/phone/password/token)", true, $safeOwner);

$safeStore = checkNoPrivateData($storeContext);
assertMatch("Context admin.toko bebas dari data pribadi (email/phone/password/token)", true, $safeStore);

// -----------------------------------------------------------------------------
// RINGKASAN HASIL
// -----------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "HASIL AKHIR: {$totalPass} PASSED, {$totalFail} FAILED\n";
echo "==============================================================================\n";

if ($totalFail > 0) {
    exit(1);
}
exit(0);

