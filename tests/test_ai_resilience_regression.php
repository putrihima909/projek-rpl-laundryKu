<?php

/**
 * TEST SUITE: AI RESILIENCE & REGRESSION TEST
 *
 * Menguji ketahanan AiSettings, fallback konfigurasi, dan non-regresi sistem AI:
 * 1. sendMessage() tetap menjawab ketika query ai_settings melempar exception / tabel tidak ada.
 * 2. sendMessage() tetap menjawab ketika APP_KEY kosong.
 * 3. sendMessage() tetap menjawab ketika key terenkripsi di database rusak.
 * 4. Jawaban "saya siapa" dan "kamu siapa" tetap benar untuk semua role (Owner, Admin Toko, Kurir, Customer).
 * 5. Penolakan coding/pemrograman tetap bekerja konsisten.
 * 6. Admin Toko tetap bisa menanyakan omset dan pesanan tokonya.
 * 7. Pembersihan data uji secara tuntas.
 */

// 1. Setup Autoloader & Helpers
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
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
use App\Helpers\Env;
use App\Models\AiChatMessage;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiSettings;
use App\Services\Ai\MockAiClient;

$totalPass = 0;
$totalFail = 0;

function assertCheck(bool $condition, string $description, ?string $detail = null): void {
    global $totalPass, $totalFail;
    if ($condition) {
        $totalPass++;
        echo "[PASS] {$description}\n";
    } else {
        $totalFail++;
        echo "[FAIL] {$description}\n";
        if ($detail) {
            echo "       Detail: {$detail}\n";
        }
    }
}

echo "==============================================================================\n";
echo "   AI RESILIENCE & REGRESSION TEST SUITE                                     \n";
echo "==============================================================================\n\n";

$pdo = Database::getInstance();
$chatModel = new AiChatMessage();

// Ambil sample user untuk tiap role
$roleUsers = [];
$stmt = $pdo->query("SELECT u.id, u.name, u.email, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('owner', 'admin_toko', 'kurir', 'customer')");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $roleUsers[$row['role_name']] = $row;
}

$ownerUser    = $roleUsers['owner'] ?? null;
$adminUser    = $roleUsers['admin_toko'] ?? null;
$kurirUser    = $roleUsers['kurir'] ?? null;
$customerUser = $roleUsers['customer'] ?? null;

$initialMessageCount = (int) $pdo->query("SELECT COUNT(*) FROM ai_chat_messages")->fetchColumn();

// ----------------------------------------------------------------------------
// 1. Uji Ketahanan: Query ai_settings melempar exception / tabel tidak ada
// ----------------------------------------------------------------------------
echo "--- 1. Uji Ketahanan: AiSettings Exception / DB Error ---\n";

class BrokenAiSettings extends AiSettings {
    public function __construct() {}
    public function get(string $key): mixed {
        throw new \PDOException("Table 'ai_settings' doesn't exist (Simulated).");
    }
    public function getAll(): array {
        throw new \PDOException("Table 'ai_settings' doesn't exist (Simulated).");
    }
    public function getDecryptedApiKey(): ?string {
        throw new \PDOException("Table 'ai_settings' doesn't exist (Simulated).");
    }
}

try {
    $brokenSettings = new BrokenAiSettings();
    $serviceBroken = new AiAssistantService(new MockAiClient(), null, null, $brokenSettings);
    $resBroken = $serviceBroken->sendMessage($customerUser['id'], "Halo, apakah layanan cuci komplit tersedia?");
    assertCheck($resBroken['success'] === true, "sendMessage() tetap menjawab sukses ketika AiSettings melempar exception");
    assertCheck(!empty($resBroken['reply']), "sendMessage() mengembalikan balasan non-kosong saat AiSettings error");
} catch (\Throwable $e) {
    assertCheck(false, "sendMessage() tidak boleh melempar exception saat AiSettings error", $e->getMessage());
}

// ----------------------------------------------------------------------------
// 2. Uji Ketahanan: APP_KEY Kosong
// ----------------------------------------------------------------------------
echo "\n--- 2. Uji Ketahanan: APP_KEY Kosong di .env ---\n";
$originalAppKey = Env::get('APP_KEY');
$_ENV['APP_KEY'] = '';
putenv('APP_KEY=');

try {
    $aiSettingsEmptyAppKey = new AiSettings($pdo);
    $decrypted = $aiSettingsEmptyAppKey->getDecryptedApiKey();
    $envConfigKey = \config('ai.gemini.api_key', '');
    assertCheck($decrypted === (trim($envConfigKey) !== '' ? trim($envConfigKey) : null), "Saat APP_KEY kosong, getDecryptedApiKey() aman fallback ke .env/config");

    $serviceEmptyAppKey = new AiAssistantService(new MockAiClient(), null, null, $aiSettingsEmptyAppKey);
    $resEmptyKey = $serviceEmptyAppKey->sendMessage($customerUser['id'], "siapa saya");
    assertCheck($resEmptyKey['success'] === true, "sendMessage() tetap menjawab sukses saat APP_KEY kosong");
    assertCheck(str_contains(mb_strtolower($resEmptyKey['reply']), mb_strtolower($customerUser['name'])), "Jawaban 'siapa saya' tetap memuat nama customer saat APP_KEY kosong");
} finally {
    $_ENV['APP_KEY'] = $originalAppKey;
    putenv("APP_KEY={$originalAppKey}");
}

// ----------------------------------------------------------------------------
// 3. Uji Ketahanan: Key Terenkripsi di Database Rusak / Corrupted
// ----------------------------------------------------------------------------
echo "\n--- 3. Uji Ketahanan: Key Terenkripsi di Database Rusak ---\n";

// Simpan ciphertext rusak sementara
$origDbEnc = $pdo->query("SELECT setting_value FROM ai_settings WHERE setting_key = 'gemini_api_key_enc'")->fetchColumn();
$pdo->exec("INSERT INTO ai_settings (setting_key, setting_value, updated_at) VALUES ('gemini_api_key_enc', 'INVALID_CORRUPTED_BASE64_CIPHERTEXT!!!', NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

try {
    $aiSettingsCorrupted = new AiSettings($pdo);
    $decryptedCorrupt = $aiSettingsCorrupted->getDecryptedApiKey();
    assertCheck($decryptedCorrupt === (trim($envConfigKey) !== '' ? trim($envConfigKey) : null), "Saat ciphertext DB rusak, getDecryptedApiKey() aman fallback ke .env/config tanpa crash");

    $serviceCorrupt = new AiAssistantService(new MockAiClient(), null, null, $aiSettingsCorrupted);
    $resCorrupt = $serviceCorrupt->sendMessage($customerUser['id'], "Halo!");
    assertCheck($resCorrupt['success'] === true, "sendMessage() tetap sukses saat ciphertext database rusak");
} finally {
    if ($origDbEnc !== false && $origDbEnc !== null) {
        $stmtRestore = $pdo->prepare("UPDATE ai_settings SET setting_value = :v WHERE setting_key = 'gemini_api_key_enc'");
        $stmtRestore->execute(['v' => $origDbEnc]);
    } else {
        $pdo->exec("DELETE FROM ai_settings WHERE setting_key = 'gemini_api_key_enc'");
    }
}

// ----------------------------------------------------------------------------
// 4. Non-Regresi: 'saya siapa' & 'kamu siapa' untuk semua role
// ----------------------------------------------------------------------------
echo "\n--- 4. Non-Regresi: Identitas Pengguna & Asisten untuk Semua Role ---\n";
$normalService = new AiAssistantService(new MockAiClient());

$testRoles = [
    'Owner'      => $ownerUser,
    'Admin Toko' => $adminUser,
    'Kurir'      => $kurirUser,
    'Customer'   => $customerUser,
];

foreach ($testRoles as $roleTitle => $uData) {
    if (!$uData) continue;
    $uid = (int) $uData['id'];
    $uName = $uData['name'];

    // Test 'saya siapa'
    $resSaya = $normalService->sendMessage($uid, "siapa saya");
    assertCheck($resSaya['success'] === true, "Role {$roleTitle}: 'siapa saya' berhasil dijawab");
    assertCheck(str_contains(mb_strtolower($resSaya['reply']), mb_strtolower($uName)), "Role {$roleTitle}: 'siapa saya' memuat nama user ({$uName})");
    assertCheck(str_contains(mb_strtolower($resSaya['reply']), mb_strtolower($roleTitle)), "Role {$roleTitle}: 'siapa saya' memuat nama role ({$roleTitle})");

    // Test 'kamu siapa'
    $resKamu = $normalService->sendMessage($uid, "kamu siapa");
    assertCheck($resKamu['success'] === true, "Role {$roleTitle}: 'kamu siapa' berhasil dijawab");
    assertCheck(str_contains($resKamu['reply'], "Asisten LaundryKu"), "Role {$roleTitle}: 'kamu siapa' mengidentifikasi diri sebagai 'Asisten LaundryKu'");
}

// ----------------------------------------------------------------------------
// 5. Non-Regresi: Penolakan Coding / Pemrograman
// ----------------------------------------------------------------------------
echo "\n--- 5. Non-Regresi: Penolakan Permintaan Coding ---\n";

$codingPrompts = [
    "Tuliskan fungsi python untuk mengurutkan array",
    "Buatkan kode javascript untuk validasi form",
    "Tolong buatkan script PHP untuk query database mysql",
    "Bantu saya debug error code 500 ini dong",
];

foreach ($codingPrompts as $prompt) {
    $resCoding = $normalService->sendMessage($customerUser['id'], $prompt);
    assertCheck($resCoding['success'] === true, "Query coding diproses aman: '{$prompt}'");
    assertCheck(
        str_contains(mb_strtolower($resCoding['reply']), "coding") ||
        str_contains(mb_strtolower($resCoding['reply']), "pemrograman") ||
        str_contains(mb_strtolower($resCoding['reply']), "tidak bisa membantu") ||
        str_contains(mb_strtolower($resCoding['reply']), "seputar layanan laundry"),
        "AI menolak pertanyaan coding dengan sopan"
    );
}

// ----------------------------------------------------------------------------
// 6. Non-Regresi: Admin Toko Menanyakan Omset & Pesanan Tokonya
// ----------------------------------------------------------------------------
echo "\n--- 6. Non-Regresi: Admin Toko Menanyakan Omset & Pesanan Toko ---\n";

if ($adminUser) {
    $adminId = (int) $adminUser['id'];

    // Pertanyaan Omset Hari Ini
    $resOmset = $normalService->sendMessage($adminId, "Berapa omzet hari ini?");
    assertCheck($resOmset['success'] === true, "Admin Toko: Pertanyaan omzet hari ini berhasil dijawab");
    assertCheck(str_contains($resOmset['reply'], "Rp"), "Admin Toko: Balasan omzet memuat format mata uang Rupiah (Rp)");
    assertCheck(str_contains(mb_strtolower($resOmset['reply']), "omzet") || str_contains(mb_strtolower($resOmset['reply']), "pendapatan"), "Admin Toko: Balasan memuat istilah omzet/pendapatan");

    // Pertanyaan Pesanan Hari Ini
    $resPesanan = $normalService->sendMessage($adminId, "Berapa pesanan hari ini?");
    assertCheck($resPesanan['success'] === true, "Admin Toko: Pertanyaan pesanan hari ini berhasil dijawab");
    assertCheck(str_contains(mb_strtolower($resPesanan['reply']), "pesanan"), "Admin Toko: Balasan memuat informasi pesanan");
}

// ----------------------------------------------------------------------------
// 7. Pembersihan Data Uji
// ----------------------------------------------------------------------------
echo "\n--- 7. Pembersihan Data Uji ---\n";
// Hapus pesan-pesan chat yang dibuat selama pengujian ini
$finalMessageCount = (int) $pdo->query("SELECT COUNT(*) FROM ai_chat_messages")->fetchColumn();
$newMessages = $finalMessageCount - $initialMessageCount;
if ($newMessages > 0) {
    $pdo->exec("DELETE FROM ai_chat_messages ORDER BY id DESC LIMIT {$newMessages}");
    echo "[INFO] Berhasil membersihkan {$newMessages} pesan chat data uji.\n";
}

$cleanedCount = (int) $pdo->query("SELECT COUNT(*) FROM ai_chat_messages")->fetchColumn();
assertCheck($cleanedCount === $initialMessageCount, "Jumlah record ai_chat_messages kembali persis seperti semula ({$initialMessageCount})");

echo "\n==============================================================================\n";
echo "   HASIL AKHIR RESILIENCE & REGRESSION TEST: {$totalPass} PASS, {$totalFail} FAIL\n";
echo "==============================================================================\n";

if ($totalFail > 0) {
    exit(1);
}

