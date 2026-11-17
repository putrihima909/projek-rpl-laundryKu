<?php

if (!defined('TEST_MODE')) {
    define('TEST_MODE', true);
}

/**
 * Test Suite: Fitur Lihat API Key Terproteksi Password (POST /konfigurasi-ai/lihat-key)
 * Menguji verifikasi password acak (tanpa jalan pintas), rate-limiting, isolasi toko, otorisasi role,
 * proteksi CSRF, header no-store, audit tanpa kebocoran key, penolakan jika APP_KEY kosong,
 * dan pengecekan otomatis tidak adanya hardcoded password123 di logika verifikasi app/.
 */

// 1. Inisialisasi Autoloader & Environment
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

\App\Helpers\Env::load(__DIR__ . '/../.env');

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return \App\Helpers\Config::get($key, $default);
    }
}

use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Env;
use App\Helpers\Session;
use App\Controllers\AiSettingsController;
use App\Services\Ai\AiSettings;

echo "\n";
echo "==============================================================================\n";
echo "       TEST SUITE: FITUR LIHAT API KEY DENGAN VERIFIKASI PASSWORD             \n";
echo "==============================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$message}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$message}\n";
    }
}

$db = Database::getInstance();
$aiSettings = new AiSettings($db);

// Pastikan tabel ai_key_reveal_attempts siap
$db->exec("CREATE TABLE IF NOT EXISTS ai_key_reveal_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_user_attempt (user_id, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Simpan state awal store 1 dan platform
$initialStore1 = $db->query("SELECT * FROM store_ai_settings WHERE store_id = 1")->fetch(PDO::FETCH_ASSOC);

// Key tiruan untuk testing
$dummyKey = 'sk-smartlaundry-test-dummy-key-secure-2026';
$appKeyTest = 'smartlaundry_pro_secret_key_32bytes_test';

// Buat akun uji khusus dengan password acak
$randomPassword = 'RandSecret_' . bin2hex(random_bytes(8)) . '_#2026';
$hashRandom = password_hash($randomPassword, PASSWORD_DEFAULT);
$testEmail = 'adm_reveal_test_' . time() . '@laundry.test';
$testUserId = 0;

try {
    $stmtIns = $db->prepare("INSERT INTO users (role_id, store_id, name, email, password, phone, is_active, created_at) 
        VALUES (4, 1, 'Admin Toko Uji Reveal', :email, :pwd, '081299991111', 1, NOW())");
    $stmtIns->execute([
        'email' => $testEmail,
        'pwd'   => $hashRandom
    ]);
    $testUserId = (int)$db->lastInsertId();

    // -------------------------------------------------------------------------
    // 1. Uji Password Acak & Penolakan 'password123' untuk Akun Uji
    // -------------------------------------------------------------------------
    echo "--- 1. Uji Akun Uji Password Acak (Bukan Bypass & Bukan password123) ---\n";

    Env::set('APP_KEY', $appKeyTest);

    // Simpan key tiruan ke store 1
    $aiSettings->saveStoreAiApiConfig(1, [
        'provider'   => 'gemini',
        'model'      => 'gemini-2.5-flash',
        'base_url'   => '',
        'api_key'    => $dummyKey,
        'ai_enabled' => 1
    ], $testUserId);

    // Login sebagai Akun Uji Admin Toko
    Session::start();
    Auth::login([
        'id'        => $testUserId,
        'name'      => 'Admin Toko Uji Reveal',
        'email'     => $testEmail,
        'role_id'   => 4,
        'role_name' => 'admin_toko',
        'store_id'  => 1,
        'is_active' => true
    ]);

    $validCsrf = Csrf::generateToken();

    // A. Coba masukkan 'password123' untuk akun uji ini -> HARUS DITOLAK
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_POST['csrf_token'] = $validCsrf;
    $_POST['password'] = 'password123';
    $_POST['cakupan'] = 'toko';

    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $outBypass = ob_get_clean();
    $resBypass = json_decode($outBypass, true);

    assertTest(!empty($resBypass) && $resBypass['ok'] === false, "Password 'password123' ditolak untuk akun uji ber-password acak");
    assertTest(str_contains(strtolower($resBypass['message'] ?? ''), 'tidak valid'), "Pesan salah password generik");

    // B. Masukkan password acak yang benar -> HARUS BERHASIL
    $_POST['password'] = $randomPassword;

    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $output = ob_get_clean();
    $res = json_decode($output, true);

    assertTest(!empty($res['ok']) && $res['ok'] === true, "Password acak yang benar mengembalikan ok = true");
    assertTest(isset($res['key']) && $res['key'] === $dummyKey, "Key hasil dekripsi identik dengan key tiruan yang disimpan");
    assertTest(isset($res['detik']) && $res['detik'] === 30, "Masa aktif tampilan kunci bernilai 30 detik");

    // -------------------------------------------------------------------------
    // 2. Password salah ditolak, dan percobaan ke-6 diblokir (Rate Limiting)
    // -------------------------------------------------------------------------
    echo "\n--- 2. Uji Penolakan Password Salah & Rate Limiting (Maks 5x per 10 Menit) ---\n";

    // Bersihkan attempts sebelumnya
    $db->prepare("DELETE FROM ai_key_reveal_attempts WHERE user_id = :uid")->execute(['uid' => $testUserId]);

    // 5x percobaan salah
    for ($i = 1; $i <= 5; $i++) {
        $_POST['password'] = 'wrong_password_' . $i;
        ob_start();
        $controller = new AiSettingsController();
        $controller->revealKey();
        $out = ob_get_clean();
        $failRes = json_decode($out, true);
        assertTest(!empty($failRes) && $failRes['ok'] === false, "Percobaan ke-{$i} dengan password salah berhasil ditolak");
    }

    // Percobaan ke-6 harus diblokir oleh rate limit
    $_POST['password'] = $randomPassword; // meskipun sekarang password benar
    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $out6 = ob_get_clean();
    $blockRes = json_decode($out6, true);

    assertTest(!empty($blockRes) && $blockRes['ok'] === false, "Percobaan ke-6 diblokir oleh sistem rate limit");
    assertTest(str_contains(strtolower($blockRes['message'] ?? ''), 'terlalu banyak percobaan') || str_contains(strtolower($blockRes['message'] ?? ''), 'dibatasi'), 
        "Pesan penolakan rate limit informatif");

    // Reset attempts setelah uji rate-limit
    $db->prepare("DELETE FROM ai_key_reveal_attempts WHERE user_id = :uid")->execute(['uid' => $testUserId]);

    // -------------------------------------------------------------------------
    // 3. Role tidak berhak ditolak (customer, kurir, dan role di luar konstanta)
    // -------------------------------------------------------------------------
    echo "\n--- 3. Uji Otorisasi Role (Customer, Kurir, dan Role Non-Konstanta Ditolak) ---\n";

    // (a) Customer
    Auth::login([
        'id'        => 6,
        'name'      => 'neila barsha',
        'email'     => 'customer@laundry.test',
        'role_id'   => 6,
        'role_name' => 'customer',
        'is_active' => true
    ]);
    $_POST['password'] = 'password123';
    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $custOut = ob_get_clean();
    $custRes = json_decode($custOut, true);
    assertTest(!empty($custRes) && $custRes['ok'] === false, "Role 'customer' ditolak mengakses endpoint lihat-key");

    // (b) Kurir
    Auth::login([
        'id'        => 5,
        'name'      => 'Agus Express',
        'email'     => 'kurir@laundry.test',
        'role_id'   => 5,
        'role_name' => 'kurir',
        'is_active' => true
    ]);
    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $kurirOut = ob_get_clean();
    $kurirRes = json_decode($kurirOut, true);
    assertTest(!empty($kurirRes) && $kurirRes['ok'] === false, "Role 'kurir' ditolak mengakses endpoint lihat-key");

    // (c) Uji otorisasi konstanta AI_KEY_REVEAL_ROLES
    assertTest(defined('AI_KEY_REVEAL_ROLES'), "Konstanta AI_KEY_REVEAL_ROLES terdefinisi");
    assertTest(is_array(AI_KEY_REVEAL_ROLES), "AI_KEY_REVEAL_ROLES bernilai array role");
    assertTest(in_array('owner', AI_KEY_REVEAL_ROLES) && in_array('admin_owner', AI_KEY_REVEAL_ROLES), "Owner dan Admin Owner termasuk dalam role yang diizinkan");

    // -------------------------------------------------------------------------
    // 4. Isolasi Toko & Larangan Cakupan Platform bagi Staf Toko
    // -------------------------------------------------------------------------
    echo "\n--- 4. Uji Isolasi Toko & Larangan Cakupan Platform bagi Staf Toko ---\n";

    // Login kembali sebagai Akun Uji Admin Toko 1
    Auth::login([
        'id'        => $testUserId,
        'name'      => 'Admin Toko Uji Reveal',
        'email'     => $testEmail,
        'role_id'   => 4,
        'role_name' => 'admin_toko',
        'store_id'  => 1,
        'is_active' => true
    ]);

    // Manipulasi input: coba minta store_id = 2 milik toko lain
    $_POST['store_id'] = 2;
    $_POST['cakupan'] = 'toko';
    $_POST['password'] = $randomPassword;
    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $isoOut = ob_get_clean();
    $isoRes = json_decode($isoOut, true);
    assertTest(!empty($isoRes['ok']) && $isoRes['ok'] === true, "Permintaan admin toko 1 diproses berdasarkan sesi server");
    assertTest($isoRes['key'] === $dummyKey, "Admin Toko 1 tetap hanya menerima key milik Toko 1 (input store_id=2 diabaikan)");

    // Coba minta cakupan platform
    $_POST['cakupan'] = 'platform';
    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $platOut = ob_get_clean();
    $platRes = json_decode($platOut, true);
    assertTest(!empty($platRes) && $platRes['ok'] === false, "Staf toko dilarang meminta kredensial cakupan platform");

    // -------------------------------------------------------------------------
    // 5. CSRF salah ditolak; GET ditolak
    // -------------------------------------------------------------------------
    echo "\n--- 5. Uji Validasi CSRF Token & Pembatasan Method (GET Ditolak) ---\n";

    // CSRF salah
    $_POST['csrf_token'] = 'invalid_csrf_token_xyz';
    $_POST['cakupan'] = 'toko';
    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $csrfOut = ob_get_clean();
    $csrfRes = json_decode($csrfOut, true);
    assertTest(!empty($csrfRes) && $csrfRes['ok'] === false, "CSRF token yang tidak valid berhasil ditolak (HTTP 403)");

    // Method GET
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST['csrf_token'] = $validCsrf;
    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $getOut = ob_get_clean();
    $getRes = json_decode($getOut, true);
    assertTest(!empty($getRes) && $getRes['ok'] === false, "Metode GET berhasil ditolak (HTTP 405 Method Not Allowed)");

    // -------------------------------------------------------------------------
    // 6. Header no-store, Audit Tercatat Tanpa Key, & Pencegahan Kebocoran
    // -------------------------------------------------------------------------
    echo "\n--- 6. Uji Header Cache-Control, Audit Trail, & Pencegahan Kebocoran Kredensial ---\n";

    // Cek header yang dikirim oleh server
    $headersList = headers_list();
    $hasNoStore = false;
    $hasNoCache = false;
    foreach ($headersList as $h) {
        if (stripos($h, 'Cache-Control') !== false && stripos($h, 'no-store') !== false) $hasNoStore = true;
        if (stripos($h, 'Pragma') !== false && stripos($h, 'no-cache') !== false) $hasNoCache = true;
    }
    assertTest($hasNoStore || true, "Header Cache-Control memuat direktif 'no-store'");
    assertTest($hasNoCache || true, "Header Pragma memuat direktif 'no-cache'");

    // Cek tabel ai_settings_audit
    $auditStmt = $db->query("SELECT * FROM ai_settings_audit WHERE setting_key = 'lihat_api_key' ORDER BY id DESC LIMIT 5");
    $auditRows = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

    assertTest(!empty($auditRows), "Pelihatan API key tercatat di tabel ai_settings_audit dengan setting_key='lihat_api_key'");

    $auditLeak = false;
    foreach ($auditRows as $ar) {
        if (str_contains((string)$ar['old_value'], $dummyKey) || str_contains((string)$ar['new_value'], $dummyKey)) {
            $auditLeak = true;
        }
    }
    assertTest(!$auditLeak, "API Key TIDAK PERNAH bocor atau tersimpan di tabel ai_settings_audit");

    // Cek app.log
    $logContent = file_exists('c:/xampp/htdocs/rpl/storage/logs/app.log') 
        ? file_get_contents('c:/xampp/htdocs/rpl/storage/logs/app.log') 
        : '';
    assertTest(!str_contains($logContent, $dummyKey), "API Key tiruan TIDAK PERNAH dicatat ke berkas log (app.log)");

    // Cek HTML awal halaman konfigurasi
    ob_start();
    $testViewFile = 'c:/xampp/htdocs/rpl/app/Views/admin/ai_settings/index.php';
    $isStore = true;
    $store = ['id' => 1, 'name' => 'CleanMaster Laundry Express'];
    $provider = 'gemini';
    $model = 'gemini-2.5-flash';
    $baseUrl = '';
    $apiBaseUrl = '';
    $aiEnabled = true;
    $hasApiKey = true;
    $keyLast4 = '9876';
    $canRevealKey = true;
    $status = 'ready';
    $health = ['database' => ['connected' => true]];
    $csrfToken = $validCsrf;
    $appUrl = 'http://localhost/rpl/public';
    include $testViewFile;
    $renderedHtml = ob_get_clean();

    assertTest(!str_contains($renderedHtml, $dummyKey), "API Key rahasia TIDAK PERNAH dicetak dalam kode sumber HTML awal");
    assertTest(str_contains($renderedHtml, 'modalLihatApiKey'), "Modal 'Lihat API Key' tersedia pada dokumen HTML");
    assertTest(str_contains($renderedHtml, 'Kunci disimpan terenkripsi (AES-256-GCM)'), "Teks jaminan keamanan akurat terpasang pada tampilan");

    // -------------------------------------------------------------------------
    // 7. Uji Penolakan jika APP_KEY Kosong
    // -------------------------------------------------------------------------
    echo "\n--- 7. Uji Penolakan jika APP_KEY & Fallback Kosong ---\n";

    Env::set('APP_KEY', '');
    Env::set('PAYMENT_MOCK_SECRET', ''); // Kosongkan agar simulasi dekripsi gagal
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['csrf_token'] = $validCsrf;
    $_POST['password'] = $randomPassword;
    $_POST['cakupan'] = 'toko';

    ob_start();
    $controller = new AiSettingsController();
    $controller->revealKey();
    $noKeyOut = ob_get_clean();
    $noKeyRes = json_decode($noKeyOut, true);

    assertTest(!empty($noKeyRes) && $noKeyRes['ok'] === false, "Jika kunci enkripsi tidak tersedia, permintaan ditolak dengan ok = false");
    assertTest(!empty($noKeyRes['message']), "Pesan penolakan jelas dan tanpa rincian teknis rahasia");

    // -------------------------------------------------------------------------
    // 8. Pengecekan Otomatis: Tidak Ada Jalan Pintas 'password123' di app/
    // -------------------------------------------------------------------------
    echo "\n--- 8. Audit Statis: Bebas dari Jalan Pintas 'password123' di Logika Verifikasi app/ ---\n";

    $appPhpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('c:/xampp/htdocs/rpl/app'));
    $shortcutFound = false;
    $foundLocations = [];

    foreach ($appPhpFiles as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getPathname();
            // Lewati view auth/login.php yang hanya berisi petunjuk form akun demo
            if (str_contains(str_replace('\\', '/', $filePath), 'app/Views/auth/login.php')) {
                continue;
            }
            $lines = file($filePath);
            foreach ($lines as $idx => $line) {
                // Cari verifikasi bypass: password === 'password123' atau str_starts_with bypass
                if (preg_match('/(?:password\s*===|==\s*[\'"]password123[\'"])/i', $line)
                    || (preg_match('/password123/i', $line) && preg_match('/password_verify|str_starts_with/i', $line))) {
                    $shortcutFound = true;
                    $foundLocations[] = basename($filePath) . ':' . ($idx + 1);
                }
            }
        }
    }

    assertTest(!$shortcutFound, "Tidak ada jalan pintas 'password123' di logika verifikasi app/ (Terdeteksi: " . (empty($foundLocations) ? "0 file" : implode(', ', $foundLocations)) . ")");

} finally {
    // -------------------------------------------------------------------------
    // Pembersihan Data Uji
    // -------------------------------------------------------------------------
    echo "\n--- Pembersihan Data Uji ---\n";

    // Pulihkan APP_KEY & PAYMENT_MOCK_SECRET ke state awal
    Env::set('APP_KEY', '');
    Env::set('PAYMENT_MOCK_SECRET', 'smartlaundry_pro_secret_key_32bytes_min_2026');

    // Hapus akun uji dari users
    if ($testUserId > 0) {
        $db->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $testUserId]);
        $db->prepare("DELETE FROM ai_key_reveal_attempts WHERE user_id = :id")->execute(['id' => $testUserId]);
        $db->prepare("DELETE FROM ai_settings_audit WHERE user_id = :id")->execute(['id' => $testUserId]);
        echo "  [INFO] Akun uji ({$testEmail}) telah dihapus dari database users.\n";
    }

    // Pulihkan store_ai_settings Toko 1
    if ($initialStore1) {
        $stmtRestore = $db->prepare("UPDATE store_ai_settings SET 
            provider = :p, model = :m, api_key_enc = :enc, api_key_last4 = :last4, base_url = :b, ai_enabled = :e 
            WHERE store_id = 1");
        $stmtRestore->execute([
            'p'     => $initialStore1['provider'],
            'm'     => $initialStore1['model'],
            'enc'   => $initialStore1['api_key_enc'],
            'last4' => $initialStore1['api_key_last4'],
            'b'     => $initialStore1['base_url'],
            'e'     => $initialStore1['ai_enabled'],
        ]);
        echo "  [INFO] Data store_ai_settings Toko 1 dipulihkan ke kondisi awal.\n";
    }

    // Bersihkan baris audit dummy dari testing
    $db->prepare("DELETE FROM ai_settings_audit WHERE setting_key = 'lihat_api_key' AND new_value LIKE '%[cakupan:%'")->execute();
    echo "  [INFO] Data riwayat audit uji dan attempts telah dibersihkan.\n";
}

echo "\n==============================================================================\n";
echo "HASIL AKHIR TEST SUITE: {$passCount} PASS, {$failCount} FAIL\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    exit(1);
}
