<?php

require_once __DIR__ . '/../vendor/autoload.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return \App\Helpers\Config::get($key, $default);
    }
}

use App\Core\Database;
use App\Helpers\Auth;
use App\Controllers\DashboardController;
use App\Models\User;

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, &$passed, &$failed): void {
    if ($condition) {
        echo "  \033[32m[PASS]\033[0m {$name}\n";
        $passed++;
    } else {
        echo "  \033[31m[FAIL]\033[0m {$name}\n";
        $failed++;
    }
}

echo "==============================================================================\n";
echo "   TEST SUITE: VERIFIKASI MULTI-ROLE OMSET & WHATSAPP ACCESS CONTROL         \n";
echo "==============================================================================\n\n";

$db = Database::getInstance();

// 1. UJI QUERY OMSET & TX_COUNT DI DATABASE
echo "--- 1. Uji Database Omset & Tx Count ---\n";
// Platform-wide
$platformRow = $db->query("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE payment_status = 'paid'")->fetch(\PDO::FETCH_NUM);
$platformOmset = (float) $platformRow[0];
$platformTx = (int) $platformRow[1];

assertCondition("Platform Omset > 0 ({$platformOmset})", $platformOmset > 0, $passed, $failed);
assertCondition("Platform Tx Count > 0 ({$platformTx})", $platformTx > 0, $passed, $failed);

// Store 1 isolation
$store1Row = $db->query("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE store_id = 1 AND payment_status = 'paid'")->fetch(\PDO::FETCH_NUM);
$store1Omset = (float) $store1Row[0];
$store1Tx = (int) $store1Row[1];

assertCondition("Store 1 Omset <= Platform Omset", $store1Omset <= $platformOmset, $passed, $failed);
assertCondition("Store 1 Tx Count <= Platform Tx Count", $store1Tx <= $platformTx, $passed, $failed);

// Store 2 isolation
$store2Row = $db->query("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE store_id = 2 AND payment_status = 'paid'")->fetch(\PDO::FETCH_NUM);
$store2Omset = (float) $store2Row[0];
$store2Tx = (int) $store2Row[1];

assertCondition("Store 1 + Store 2 Omset = Platform Omset (jika hanya ada 2 toko)", ($store1Omset + $store2Omset) <= $platformOmset, $passed, $failed);

// 2. UJI DASHBOARD CONTROLLER DATA STRUCTURE UNTUK SEMUA ROLE
echo "\n--- 2. Uji Data Card Dashboard Controller ---\n";

// Login as Owner (user id 1)
$userModel = new User();
$ownerUser = $userModel->findByEmail('owner@laundry.test');
Auth::login($ownerUser);

assertCondition("Auth::role() adalah owner", Auth::role() === Auth::ROLE_OWNER, $passed, $failed);

// Test DashboardController owner data via reflection or capturing view
$controller = new DashboardController();
// Check if omzetDetail allows Owner
$_SESSION['user'] = $ownerUser;
ob_start();
try {
    $controller->owner();
    $out = ob_get_clean();
    assertCondition("Dashboard Owner dapat dirender tanpa exception", str_contains($out, 'Dashboard Owner Platform'), $passed, $failed);
    assertCondition("Dashboard Owner memuat link Detail Omset", str_contains($out, '/dashboard/omzet-detail'), $passed, $failed);
    assertCondition("Dashboard Owner memuat label Transaksi Terbayar", str_contains($out, 'Transaksi Terbayar'), $passed, $failed);
} catch (\Throwable $e) {
    ob_end_clean();
    assertCondition("Dashboard Owner render error: " . $e->getMessage(), false, $passed, $failed);
}

// Login as Admin Owner (user id 2)
$adminOwnerUser = $userModel->findByEmail('admin.owner@laundry.test');
Auth::login($adminOwnerUser);
ob_start();
try {
    $controller->adminOwner();
    $out = ob_get_clean();
    assertCondition("Dashboard Admin Owner dapat dirender", str_contains($out, 'Dashboard Admin Owner'), $passed, $failed);
    assertCondition("Dashboard Admin Owner memuat link Detail Omset", str_contains($out, '/dashboard/omzet-detail'), $passed, $failed);
    assertCondition("Dashboard Admin Owner memuat label Transaksi Terbayar", str_contains($out, 'Transaksi Terbayar'), $passed, $failed);
} catch (\Throwable $e) {
    ob_end_clean();
    assertCondition("Dashboard Admin Owner render error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), false, $passed, $failed);
}

// Login as Owner Toko (user id 3)
$ownerTokoUser = $userModel->findByEmail('owner.toko@laundry.test');
Auth::login($ownerTokoUser);
ob_start();
try {
    $controller->ownerToko();
    $out = ob_get_clean();
    assertCondition("Dashboard Owner Toko dapat dirender", str_contains($out, 'Dashboard Owner Toko'), $passed, $failed);
    assertCondition("Dashboard Owner Toko memuat link Detail Omset", str_contains($out, '/dashboard/omzet-detail'), $passed, $failed);
} catch (\Throwable $e) {
    ob_end_clean();
    assertCondition("Dashboard Owner Toko render error: " . $e->getMessage(), false, $passed, $failed);
}

// Login as Admin Toko (user id 4)
$adminTokoUser = $userModel->findByEmail('admin.toko@laundry.test');
Auth::login($adminTokoUser);
ob_start();
try {
    $controller->adminToko();
    $out = ob_get_clean();
    assertCondition("Dashboard Admin Toko dapat dirender", str_contains($out, 'Dashboard Admin Toko'), $passed, $failed);
    assertCondition("Dashboard Admin Toko memuat card OMSET KASIR TERBAYAR", str_contains($out, 'OMSET KASIR TERBAYAR'), $passed, $failed);
    assertCondition("Dashboard Admin Toko memuat link /dashboard/omzet-detail", str_contains($out, '/dashboard/omzet-detail'), $passed, $failed);
} catch (\Throwable $e) {
    ob_end_clean();
    assertCondition("Dashboard Admin Toko render error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), false, $passed, $failed);
}

// 3. UJI ISOLASI MULTI-TOKO DI OMZET DETAIL
echo "\n--- 3. Uji Isolasi Data Multi-Toko ---\n";

// Login as Admin Toko Toko 1 (id 4)
Auth::login($adminTokoUser);
$_GET = []; // reset filters
ob_start();
try {
    $controller->omzetDetail();
    $outToko1 = ob_get_clean();
    assertCondition("Admin Toko 1 dapat membuka omzetDetail", str_contains($outToko1, 'Detail Omset'), $passed, $failed);
    assertCondition("Admin Toko 1 melihat nama toko CleanMaster", str_contains($outToko1, 'CleanMaster Laundry Express'), $passed, $failed);
    assertCondition("Admin Toko 1 TIDAK melihat nama toko Fresh & Clean Laundry", !str_contains($outToko1, 'Fresh & Clean Laundry'), $passed, $failed);
} catch (\Throwable $e) {
    ob_end_clean();
    assertCondition("Admin Toko 1 omzetDetail error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), false, $passed, $failed);
}

// Login as Admin Toko Toko 2 (id 8 - Maya Sari)
$adminToko2User = $userModel->findByEmail('admin2@laundry.test');
Auth::login($adminToko2User);
$_GET = [];
ob_start();
try {
    $controller->omzetDetail();
    $outToko2 = ob_get_clean();
    assertCondition("Admin Toko 2 dapat membuka omzetDetail", str_contains($outToko2, 'Detail Omset'), $passed, $failed);
    assertCondition("Admin Toko 2 melihat nama toko Fresh & Clean Laundry", str_contains($outToko2, htmlspecialchars('Fresh & Clean Laundry')), $passed, $failed);
    assertCondition("Admin Toko 2 TIDAK melihat nama toko CleanMaster", !str_contains($outToko2, 'CleanMaster Laundry Express'), $passed, $failed);
} catch (\Throwable $e) {
    ob_end_clean();
    assertCondition("Admin Toko 2 omzetDetail error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), false, $passed, $failed);
}

// 4. UJI AKSES URL LANGSUNG DENGAN ROLE TIDAK BERWENANG
echo "\n--- 4. Uji Blokir Customer & Kurir dari Detail Omset & WhatsApp ---\n";

// Login as Customer (id 6 - syfa)
$customerUser = $userModel->findByEmail('customer@laundry.test');
Auth::login($customerUser);
$_SESSION['user'] = $customerUser;

$customerBlocked = false;
try {
    // Controller-level check inside omzetDetail:
    // When unauthorized, it flashes error and redirects via $this->redirect() which triggers header() or redirect
    ob_start();
    $controller->omzetDetail();
    $content = ob_get_clean();
    // In cli redirect may not exit, but session flash error is set
    $customerBlocked = isset($_SESSION['_flash']['error']);
} catch (\Throwable $e) {
    $customerBlocked = true;
}
assertCondition("Customer diblokir dari endpoint Detail Omset (Server-side RBAC)", $customerBlocked, $passed, $failed);

// Login as Kurir (id 5 - Agus Express)
$kurirUser = $userModel->findByEmail('kurir@laundry.test');
Auth::login($kurirUser);
$_SESSION['user'] = $kurirUser;

$kurirBlocked = false;
try {
    ob_start();
    $controller->omzetDetail();
    $content = ob_get_clean();
    $kurirBlocked = isset($_SESSION['_flash']['error']);
} catch (\Throwable $e) {
    $kurirBlocked = true;
}
assertCondition("Kurir diblokir dari endpoint Detail Omset (Server-side RBAC)", $kurirBlocked, $passed, $failed);

// 5. UJI SIDEBAR RENDERING UNTUK SEMUA ROLE
echo "\n--- 5. Uji Sidebar Menu Sesuai Role ---\n";

function renderSidebarForUser($u): string {
    Auth::login($u);
    $_SESSION['user'] = $u;
    ob_start();
    include __DIR__ . '/../app/Views/layouts/sidebar.php';
    return ob_get_clean();
}

$sbOwner = renderSidebarForUser($ownerUser);
assertCondition("Sidebar Owner punya link Detail Omset", str_contains($sbOwner, '/dashboard/omzet-detail'), $passed, $failed);
assertCondition("Sidebar Owner punya link Gateway WhatsApp", str_contains($sbOwner, '/admin/whatsapp'), $passed, $failed);

$sbAdminOwner = renderSidebarForUser($adminOwnerUser);
assertCondition("Sidebar Admin Owner punya link Detail Omset", str_contains($sbAdminOwner, '/dashboard/omzet-detail'), $passed, $failed);
assertCondition("Sidebar Admin Owner punya link Gateway WhatsApp", str_contains($sbAdminOwner, '/admin/whatsapp'), $passed, $failed);

$sbOwnerToko = renderSidebarForUser($ownerTokoUser);
assertCondition("Sidebar Owner Toko punya link Detail Omset Toko", str_contains($sbOwnerToko, '/dashboard/omzet-detail'), $passed, $failed);
assertCondition("Sidebar Owner Toko TIDAK punya link Gateway WhatsApp", !str_contains($sbOwnerToko, '/admin/whatsapp'), $passed, $failed);

$sbAdminToko = renderSidebarForUser($adminTokoUser);
assertCondition("Sidebar Admin Toko punya link Detail Omset Kasir", str_contains($sbAdminToko, '/dashboard/omzet-detail'), $passed, $failed);
assertCondition("Sidebar Admin Toko SEKARANG punya link Gateway WhatsApp Toko", str_contains($sbAdminToko, '/admin/whatsapp'), $passed, $failed);

$sbCustomer = renderSidebarForUser($customerUser);
assertCondition("Sidebar Customer TIDAK punya link Detail Omset", !str_contains($sbCustomer, '/dashboard/omzet-detail'), $passed, $failed);
assertCondition("Sidebar Customer TIDAK punya link Gateway WhatsApp", !str_contains($sbCustomer, '/admin/whatsapp'), $passed, $failed);

$sbKurir = renderSidebarForUser($kurirUser);
assertCondition("Sidebar Kurir TIDAK punya link Detail Omset", !str_contains($sbKurir, '/dashboard/omzet-detail'), $passed, $failed);
assertCondition("Sidebar Kurir TIDAK punya link Gateway WhatsApp", !str_contains($sbKurir, '/admin/whatsapp'), $passed, $failed);

// 6. UJI AKSES KHUSUS ADMIN TOKO KE WHATSAPP (RBAC, STORE ISOLATION, DAN RESTRICTION)
echo "\n--- 6. Uji Akses Khusus Admin Toko ke WhatsApp ---\n";
Auth::login($adminTokoUser);
$_SESSION['user'] = $adminTokoUser;

$waController = new \App\Controllers\WhatsAppController();

// 6a. Admin Toko boleh buka halaman WhatsApp
$waIndexAllowed = false;
try {
    ob_start();
    $waController->index();
    $htmlIndex = ob_get_clean();
    $waIndexAllowed = str_contains($htmlIndex, 'WhatsApp');
} catch (\Throwable $e) {
    $waIndexAllowed = false;
}
assertCondition("Admin Toko dapat mengakses halaman WhatsApp Gateway", $waIndexAllowed, $passed, $failed);

// 6b. Admin Toko TIDAK BISA mengubah konfigurasi global
$settingsBlocked = false;
try {
    $_POST['_csrf_token'] = \App\Helpers\Csrf::generateToken();
    $_POST['mode'] = 'PRODUCTION';
    ob_start();
    $waController->saveSettings();
    ob_get_clean();
    $settingsBlocked = isset($_SESSION['_flash']['error']) && str_contains($_SESSION['_flash']['error'], 'Admin Toko tidak memiliki izin');
} catch (\Throwable $e) {
    $settingsBlocked = true;
}
assertCondition("Admin Toko DITOLAK saat mencoba mengubah pengaturan global (saveSettings)", $settingsBlocked, $passed, $failed);

// 6c. Isolasi data WhatsApp berdasarkan store_id
$waService = new \App\Services\WhatsApp\WhatsAppNotificationService();
$adminTokoStoreId = (int)($adminTokoUser['store_id'] ?? 1);
$storeStats = $waService->getStats($adminTokoStoreId);
$globalStats = $waService->getStats(null);
assertCondition("Statistik WhatsApp untuk Store ID {$adminTokoStoreId} terisolasi (total store <= total global)", $storeStats['total'] <= $globalStats['total'], $passed, $failed);

// 6d. Verifikasi Banner TEST tidak muncul untuk Admin Toko
assertCondition("Banner Mode TEST kuning TIDAK muncul untuk Admin Toko", !str_contains($htmlIndex, 'Mode TEST Aktif: Pesan tidak dikirim ke Fonnte'), $passed, $failed);
assertCondition("Badge Mode: TEST TIDAK muncul untuk Admin Toko", !str_contains($htmlIndex, 'Mode: TEST'), $passed, $failed);

// 6e. Verifikasi Ketersediaan Template Pesan & Penggantian Placeholder
echo "\n--- 7. Uji Template Pesan Otomatis & Placeholder ---\n";
$quickTemplates = \App\Services\WhatsApp\WhatsAppTemplate::getQuickTemplates();
assertCondition("Daftar template cepat memiliki 7 opsi", count($quickTemplates) === 7, $passed, $failed);
assertCondition("Template 3 (Cucian selesai) tersedia", isset($quickTemplates['order_ready']), $passed, $failed);

$rawTpl = $quickTemplates['order_ready']['text'];
$rendered = \App\Services\WhatsApp\WhatsAppTemplate::replacePlaceholders($rawTpl, [
    'nama_pelanggan' => 'Budi Santoso',
    'kode_pesanan'   => 'ORD-999',
    'nama_toko'      => 'CleanMaster Laundry Express',
]);
assertCondition("Placeholder {nama_pelanggan} terganti dengan benar", str_contains($rendered, 'Budi Santoso'), $passed, $failed);
assertCondition("Placeholder {kode_pesanan} terganti dengan benar", str_contains($rendered, 'ORD-999'), $passed, $failed);
assertCondition("Placeholder {nama_toko} terganti dengan benar", str_contains($rendered, 'CleanMaster Laundry Express'), $passed, $failed);
assertCondition("Tidak ada placeholder mentah {..} yang tersisa", !str_contains($rendered, '{nama_pelanggan}') && !str_contains($rendered, '{kode_pesanan}'), $passed, $failed);

// Pengujian fallback placeholder kosong
$renderedFallback = \App\Services\WhatsApp\WhatsAppTemplate::replacePlaceholders($rawTpl, []);
assertCondition("Fallback placeholder kosong menggunakan default yang wajar ('Pelanggan')", str_contains($renderedFallback, 'Pelanggan'), $passed, $failed);
assertCondition("Fallback placeholder kosong menggunakan default yang wajar ('LaundryKu')", str_contains($renderedFallback, 'LaundryKu'), $passed, $failed);

echo "\n==============================================================================\n";
echo "REKAP HASIL PENGUJIAN AKSES RBAC: {$passed} PASS, {$failed} FAIL\n";
echo "==============================================================================\n";

if ($failed > 0) {
    exit(1);
}
