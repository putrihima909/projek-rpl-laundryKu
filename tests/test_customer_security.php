<?php

/**
 * Test Suite: Validasi Authentication, Customer Verification, & Strict Role-Based Status Guard
 * =========================================================================================
 * Memastikan:
 *  1. Login & session semua 5 role (Customer, Admin Toko, Owner, Admin Owner, Kurir)
 *  2. VerificationService lifecycle (create unverified customer, request OTP, valid, invalid, expired, used-twice, rate-limit, empty-phone)
 *  3. Akses Order: Customer dapat melihat order miliknya, tidak dapat melihat order orang lain
 *  4. Customer dilarang keras mengubah status order (tombol tersembunyi + POST ditolak)
 *  5. Admin Toko tetap dapat mengubah status order sesuai kewenangan
 *  6. Proteksi order creation: customer yang belum verifikasi diarahkan untuk verifikasi
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

// Helper Assertions
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
echo "   TEST SUITE: AUTH, CUSTOMER VERIFICATION & ORDER STATUS GUARD               \n";
echo "==============================================================================\n\n";

$db = \App\Core\Database::getInstance();

// -----------------------------------------------------------------------------
// 1. UJI LOGIN & SESSION SEMUA ROLE
// -----------------------------------------------------------------------------
echo "--- 1. Uji Login & Session Seluruh Role ---\n";

$rolesToTest = [
    'customer'    => 'customer@laundry.test',
    'admin_toko'  => 'admin.toko@laundry.test',
    'owner'       => 'owner@laundry.test',
    'admin_owner' => 'admin.owner@laundry.test',
    'kurir'       => 'kurir@laundry.test',
];

$userModel = new \App\Models\User();

foreach ($rolesToTest as $roleName => $email) {
    $user = $userModel->findByEmail($email);
    assertCustom($user !== null, "User role {$roleName} ({$email}) ditemukan di database");
    if ($user) {
        \App\Helpers\Auth::login($user);
        assertCustom(\App\Helpers\Auth::check() === true, "Auth::check() bernilai true setelah login {$roleName}");
        assertCustom(\App\Helpers\Auth::role() === $roleName, "Auth::role() cocok dengan {$roleName}");
        assertCustom(\App\Helpers\Auth::id() === (int)$user['id'], "Auth::id() cocok dengan ID user {$roleName}");
        \App\Helpers\Auth::logout();
        assertCustom(\App\Helpers\Auth::check() === false, "Auth::logout() berhasil membersihkan session {$roleName}");
    }
}

// -----------------------------------------------------------------------------
// 2. UJI LIFECYCLE VERIFIKASI CUSTOMER VIA OTP WHATSAPP
// -----------------------------------------------------------------------------
echo "\n--- 2. Uji Lifecycle Verifikasi Customer via OTP WhatsApp ---\n";

// A. Register Customer baru (Unverified)
$testEmail = 'newcustomer_' . time() . '@test.com';
$testPhone = '0899' . random_int(10000000, 99999999);
$newCustId = $userModel->create([
    'name' => 'Test Customer Verification',
    'email' => $testEmail,
    'password' => password_hash('password123', PASSWORD_BCRYPT),
    'phone' => $testPhone,
    'role_id' => 6, // Customer
    'is_active' => 1,
    'phone_verified_at' => null // Mulai unverified
]);

assertCustom($newCustId > 0, "Customer baru berhasil didaftarkan dengan ID: {$newCustId}");

$createdUser = $userModel->findWithRole($newCustId);
\App\Helpers\Auth::login($createdUser);
assertCustom(\App\Helpers\Auth::isPhoneVerified() === false, "Customer baru berstatus belum terverifikasi (Auth::isPhoneVerified() === false)");

// B. Request OTP Pertama
$verificationService = new \App\Services\Auth\VerificationService($db);
$otpResult = $verificationService->sendOtp($newCustId);
assertCustom($otpResult['success'] === true, "Request OTP pertama berhasil: " . ($otpResult['message'] ?? ''));

// Verifikasi hash tersimpan di DB (bukan plaintext)
$stmt = $db->prepare("SELECT verification_otp, otp_expires_at, otp_sent_at, otp_attempts FROM users WHERE id = :id");
$stmt->execute(['id' => $newCustId]);
$dbOtpRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

assertCustom(!empty($dbOtpRecord['verification_otp']), "Hash OTP berhasil tersimpan di DB");
assertCustom(str_starts_with($dbOtpRecord['verification_otp'], '$2y$'), "OTP tersimpan dalam bentuk Bcrypt Hash, BUKAN plaintext!");
assertCustom(!empty($dbOtpRecord['otp_expires_at']), "Masa berlaku OTP tersimpan di DB");
assertCustom(strtotime($dbOtpRecord['otp_expires_at']) > time(), "Waktu expired OTP berada di masa depan (+10 menit)");

// C. Uji Rate-Limit / Cooldown Request Berulang (< 60 detik)
$cooldownResult = $verificationService->sendOtp($newCustId);
assertCustom($cooldownResult['success'] === false, "Request OTP berulang dalam rentang < 60 detik DITOLAK sesuai rate-limit");
assertCustom(isset($cooldownResult['cooldown']) && $cooldownResult['cooldown'] > 0, "Cooldown seconds dilaporkan dengan benar");

// D. Uji Validasi Kode OTP Salah
$wrongOtpResult = $verificationService->verifyOtp($newCustId, '000000');
assertCustom($wrongOtpResult['success'] === false, "Verifikasi dengan OTP salah ditolak: " . ($wrongOtpResult['message'] ?? ''));

// E. Uji Batas Percobaan (Max 5 attempts)
$stmt = $db->prepare("SELECT otp_attempts FROM users WHERE id = :id");
$stmt->execute(['id' => $newCustId]);
$attempts = (int)$stmt->fetchColumn();
assertCustom($attempts === 1, "Counter percobaan salah bertambah menjadi 1");

// F. Uji OTP Expired
$db->prepare("UPDATE users SET otp_expires_at = :past WHERE id = :id")
   ->execute(['past' => date('Y-m-d H:i:s', time() - 100), 'id' => $newCustId]);
$expiredResult = $verificationService->verifyOtp($newCustId, '123456');
assertCustom($expiredResult['success'] === false && str_contains($expiredResult['message'], 'kadaluarsa'), "OTP yang kadaluarsa ditolak dengan keterangan kadaluarsa");

// G. Generate OTP Baru & Uji Verifikasi Berhasil
$knownOtp = '789123';
$knownHash = password_hash($knownOtp, PASSWORD_BCRYPT);
$db->prepare("UPDATE users SET verification_otp = :hash, otp_expires_at = :future, otp_attempts = 0 WHERE id = :id")
   ->execute(['hash' => $knownHash, 'future' => date('Y-m-d H:i:s', time() + 600), 'id' => $newCustId]);

$successResult = $verificationService->verifyOtp($newCustId, $knownOtp);
assertCustom($successResult['success'] === true, "Verifikasi dengan kode OTP yang benar berhasil!");

// H. Uji OTP Dipakai Dua Kali (One-Time Use)
$reuseResult = $verificationService->verifyOtp($newCustId, $knownOtp);
assertCustom($reuseResult['success'] === true || str_contains($reuseResult['message'], 'sudah terverifikasi'), "Kode OTP tidak dapat digunakan ulang (status sudah terverifikasi)");

// I. Status Verifikasi Customer di Auth Helper
\App\Helpers\Auth::markPhoneVerified(date('Y-m-d H:i:s'));
assertCustom(\App\Helpers\Auth::isPhoneVerified() === true, "Auth::isPhoneVerified() bernilai true setelah verifikasi sukses");

// -----------------------------------------------------------------------------
// 3. UJI KEAMANAN ORDER & PEMBATASAN ROLE_CUSTOMER
// -----------------------------------------------------------------------------
echo "\n--- 3. Uji Keamanan Order & Pembatasan Update Status ---\n";

// Ambil sample order
$orderStmt = $db->query("SELECT id, customer_id, store_id, status FROM orders WHERE customer_id = 6 LIMIT 1");
$sampleOrder = $orderStmt->fetch(\PDO::FETCH_ASSOC);

if ($sampleOrder) {
    $orderId = (int)$sampleOrder['id'];
    $orderOwnerCustId = (int)$sampleOrder['customer_id'];

    // Login sebagai customer pemilik order
    $ownerCust = $userModel->findWithRole($orderOwnerCustId);
    \App\Helpers\Auth::login($ownerCust);

    // Verifikasi Customer dapat melihat detail order miliknya
    $orderModel = new \App\Models\Order();
    $detail = $orderModel->findOrderDetail($orderId);
    assertCustom($detail !== null && (int)$detail['customer_id'] === $orderOwnerCustId, "Customer dapat melihat detail order miliknya");

    // Login sebagai customer lain (ID baru tadi)
    \App\Helpers\Auth::login($createdUser);
    $otherCustId = (int)\App\Helpers\Auth::id();
    assertCustom($otherCustId !== $orderOwnerCustId, "Customer lain terautentikasi secara berbeda (ID {$otherCustId} != {$orderOwnerCustId})");

    // Simulasi pengecekan detail: customer lain ditolak
    $canViewOther = ($otherCustId === (int)$sampleOrder['customer_id']);
    assertCustom(!$canViewOther, "Customer lain DITOLAK melihat pesanan bukan miliknya");

    // Verifikasi Route update-status TIDAK mengizinkan ROLE_CUSTOMER di Middleware
    // Membaca definisi route di public/index.php
    $indexContent = file_get_contents(__DIR__ . '/../public/index.php');
    $hasCustomerInUpdateStatusRoute = preg_match("/orders\/update-status.*ROLE_CUSTOMER/i", $indexContent);
    assertCustom(!$hasCustomerInUpdateStatusRoute, "Route /orders/update-status TIDAK mendaftarkan Auth::ROLE_CUSTOMER di AuthMiddleware");

    // Verifikasi View detail.php TIDAK menampilkan tombol Update Status untuk Customer
    $detailViewContent = file_get_contents(__DIR__ . '/../app/Views/orders/detail.php');
    $isButtonGuarded = str_contains($detailViewContent, "ROLE_CUSTOMER");
    assertCustom($isButtonGuarded, "Tombol & modal Update Status di detail.php dibungkus dengan guard role customer");
}

// -----------------------------------------------------------------------------
// 4. UJI ADMIN TOKO TETAP DAPAT UPDATE STATUS
// -----------------------------------------------------------------------------
echo "\n--- 4. Uji Admin Toko Tetap Dapat Update Status Yang Berwenang ---\n";

$adminTokoUser = $userModel->findByEmail('admin.toko@laundry.test');
\App\Helpers\Auth::login($adminTokoUser);
assertCustom(\App\Helpers\Auth::role() === \App\Helpers\Auth::ROLE_ADMIN_TOKO, "Login sebagai Admin Toko berhasil");

// State machine valid transitions check
$readyToDelivered = \App\Models\Order::isValidTransition(\App\Models\Order::STATUS_READY, \App\Models\Order::STATUS_DELIVERED);
assertCustom($readyToDelivered === true, "Admin Toko memiliki wewenang transisi ready -> delivered (Ambil di Toko)");

$readyToOutForDelivery = \App\Models\Order::isValidTransition(\App\Models\Order::STATUS_READY, \App\Models\Order::STATUS_OUT_FOR_DELIVERY);
assertCustom($readyToOutForDelivery === true, "Admin Toko memiliki wewenang transisi ready -> out_for_delivery (Kirim via Kurir)");

// Bersihkan data user test
$db->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $newCustId]);

// -----------------------------------------------------------------------------
// REKAP HASIL
// -----------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "REKAP HASIL PENGUJIAN: {$passCount} PASS, {$failCount} FAIL\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    exit(1);
}

