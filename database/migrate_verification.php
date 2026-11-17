<?php
/**
 * Migration: Tambah kolom verifikasi akun Customer ke tabel users
 *
 * Kolom baru:
 *   - phone_verified_at   : Timestamp saat nomor WA berhasil diverifikasi
 *   - verification_otp    : OTP terakhir (disimpan sebagai bcrypt hash)
 *   - otp_expires_at      : Masa berlaku OTP (10 menit)
 *   - otp_attempts        : Jumlah percobaan OTP yang salah (maks 5)
 *   - otp_sent_at         : Waktu OTP terakhir dikirim (rate-limit 60 detik)
 *
 * Catatan:
 *   - Akun lama yang sudah aktif diisi phone_verified_at = created_at agar
 *     tidak terkunci, karena verifikasi belum ada sebelumnya.
 *   - Hanya untuk customer baru: wajib verifikasi sebelum bisa pesan.
 *   - Nomor WhatsApp TIDAK diubah oleh migrasi ini.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

$db = \App\Core\Database::getInstance();
$pdo = $db;

echo "=== MIGRATION: User Verification Fields ===\n\n";

// 1. Cek apakah kolom sudah ada
$checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone_verified_at'")->fetch();
$checkOtp = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_otp'")->fetch();
if ($checkCol && $checkOtp) {
    echo "[SKIP] Semua kolom sudah ada. Migration sudah dijalankan sebelumnya.\n";
    exit(0);
}

// 2. Tambah kolom baru
$migrations = [
    "ALTER TABLE users ADD COLUMN phone_verified_at DATETIME NULL DEFAULT NULL AFTER wa_notifications_enabled",
    "ALTER TABLE users ADD COLUMN verification_otp VARCHAR(255) NULL DEFAULT NULL AFTER phone_verified_at",
    "ALTER TABLE users ADD COLUMN otp_expires_at DATETIME NULL DEFAULT NULL AFTER verification_otp",
    "ALTER TABLE users ADD COLUMN otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER otp_expires_at",
    "ALTER TABLE users ADD COLUMN otp_sent_at DATETIME NULL DEFAULT NULL AFTER otp_attempts",
];

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
    } catch (\Throwable $e) {
        echo "[ERR] " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 3. Akun lama (non-customer atau yang sudah ada sebelum fitur ini):
//    Set phone_verified_at = created_at agar tidak terkunci.
//    Hanya untuk user yang sudah is_active = 1.
$backfill = "UPDATE users u
             JOIN roles r ON u.role_id = r.id
             SET u.phone_verified_at = u.created_at
             WHERE u.is_active = 1
               AND u.phone_verified_at IS NULL";
$affected = $pdo->exec($backfill);
echo "\n[BACKFILL] $affected akun lama ditandai terverifikasi (phone_verified_at = created_at)\n";
echo "           Alasan: akun dibuat sebelum fitur verifikasi ada.\n";

// 4. Tambah index untuk query cepat
try {
    $pdo->exec("ALTER TABLE users ADD INDEX idx_phone_verified (phone_verified_at)");
    echo "[OK] Index idx_phone_verified ditambahkan.\n";
} catch (\Throwable $e) {
    echo "[SKIP] Index mungkin sudah ada: " . $e->getMessage() . "\n";
}

echo "\n=== MIGRATION SELESAI ===\n";
echo "Kolom baru:\n";
$cols = $pdo->query("SHOW COLUMNS FROM users LIKE '%otp%'")->fetchAll(\PDO::FETCH_ASSOC);
$vCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone_verified_at'")->fetchAll(\PDO::FETCH_ASSOC);
foreach (array_merge($vCol, $cols) as $c) {
    echo "  - {$c['Field']} ({$c['Type']}) NULL={$c['Null']} Default={$c['Default']}\n";
}
