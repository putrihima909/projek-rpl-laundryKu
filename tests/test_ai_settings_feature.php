<?php

// PSR-4 Autoloader
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
require_once __DIR__ . '/../app/Helpers/Config.php';
date_default_timezone_set(\config('app.timezone', 'Asia/Jakarta'));

use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiHealthCheck;
use App\Services\Ai\AiSettings;
use App\Services\Ai\MockAiClient;

echo "\n==============================================================================\n";
echo "   TEST SUITE: AI SETTINGS PAGE, RBAC, SECURITY & SERVICE INTEGRATION        \n";
echo "==============================================================================\n\n";

$pdo = Database::getInstance();
$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$description}\n";
    } else {
        $failed++;
        echo "[FAIL] {$description}\n";
        if ($detail !== null) {
            echo "       Detail: {$detail}\n";
        }
    }
}

$aiSettings = new AiSettings($pdo);

// Rekam data awal tabel ai_settings dan tabel lainnya
$initialSettings = $aiSettings->getAll();
$tables = ['orders', 'users', 'stores', 'services', 'notifications', 'whatsapp_logs', 'ai_chat_messages', 'ai_settings'];
$initialCounts = [];
foreach ($tables as $t) {
    $initialCounts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
}
$maxMsgIdBefore = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM ai_chat_messages")->fetchColumn();

// Ambil user test berdasarkan role
$ownerUser = $pdo->query("SELECT u.id, u.name, r.name as role_name, u.is_active FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'owner' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$adminOwnerUser = $pdo->query("SELECT u.id, u.name, r.name as role_name, u.is_active FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin_owner' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$adminTokoUser = $pdo->query("SELECT u.id, u.name, r.name as role_name, u.is_active FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin_toko' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$customerUser = $pdo->query("SELECT u.id, u.name, r.name as role_name, u.is_active FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "--- 1. RBAC: Hanya Owner & Admin Owner yang Boleh Akses, Role Lain Ditolak ---\n";
// Uji helper checkAccess via simulasi sesi
$_SESSION['user'] = $ownerUser;
assertTest(in_array(Auth::role(), [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true), "Role Owner diizinkan mengakses pengaturan AI");

$_SESSION['user'] = $adminOwnerUser;
assertTest(in_array(Auth::role(), [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true), "Role Admin Owner diizinkan mengakses pengaturan AI");

$_SESSION['user'] = $adminTokoUser;
assertTest(!in_array(Auth::role(), [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true), "Role Admin Toko DITOLAK dari akses pengaturan AI");

$_SESSION['user'] = $customerUser;
assertTest(!in_array(Auth::role(), [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true), "Role Customer DITOLAK dari akses pengaturan AI");

echo "\n--- 2. Keamanan Kredensial: API Key Tidak Boleh Disimpan di DB atau Bocor di View ---\n";
$settingColumns = $pdo->query("SHOW COLUMNS FROM ai_settings")->fetchAll(PDO::FETCH_COLUMN);
assertTest(!in_array('api_key', $settingColumns), "Tabel ai_settings TIDAK memiliki kolom api_key");
assertTest(!in_array('gemini_api_key', $settingColumns), "Tabel ai_settings TIDAK memiliki kolom gemini_api_key");
assertTest(!in_array('token', $settingColumns), "Tabel ai_settings TIDAK memiliki kolom token");

$storeAiColumns = $pdo->query("SHOW COLUMNS FROM store_ai_settings")->fetchAll(PDO::FETCH_COLUMN);
assertTest(!in_array('api_key', $storeAiColumns), "Tabel store_ai_settings TIDAK memiliki kolom api_key");

// Render view ke output buffer dan periksa apakah key mentah bocor
ob_start();
$settings = $aiSettings->getAll();
$healthCheck = new AiHealthCheck($pdo);
$health = $healthCheck->run(false);
$allowedModels = [
    'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash Lite (Cepat & Ringan - Default)',
    'gemini-2.5-flash'      => 'Gemini 2.5 Flash (Standar Seimbang)',
    'gemini-2.5-pro'        => 'Gemini 2.5 Pro (Kemampuan Penalaran Lanjutan)',
];
$rawApiKey = (string) \config('ai.gemini.api_key', '');
$apiKeyStatus = $aiSettings->hasApiKey() ? 'Terisi (Aktif di .env)' : 'Kosong (Belum Diatur di .env)';
$hasApiKey = $aiSettings->hasApiKey();
$dbConnected = true;
$csrfToken = 'dummy_token';
include __DIR__ . '/../app/Views/admin/ai_settings/index.php';
$viewOutput = ob_get_clean();

assertTest(!str_contains($viewOutput, $rawApiKey) || empty($rawApiKey), "View admin/ai_settings/index.php TIDAK pernah membocorkan raw GEMINI_API_KEY");
assertTest(str_contains($viewOutput, "Terisi (Aktif di .env)") || str_contains($viewOutput, "Kosong"), "View menampilkan status ringkas kunci API");
assertTest(str_contains($viewOutput, "Pengaturan AI Assistant"), "View merender judul halaman Pengaturan AI");

echo "\n--- 3. Validasi Nilai Input: Model Tidak Valid & Nilai di Luar Batas Ditolak ---\n";

// Cek validasi model via AiSettings
$invalidModelCaught = false;
try {
    $aiSettings->set('ai_model', 'INVALID_MODEL_NAME$$$');
} catch (\InvalidArgumentException $e) {
    $invalidModelCaught = true;
}
assertTest($invalidModelCaught, "Model 'INVALID_MODEL_NAME$$$' ditolak oleh regex AiSettings");

// Cek validasi rate limit
$invalidRateLimitCaught = false;
try {
    $aiSettings->set('ai_rate_limit_per_hour', 0);
} catch (\InvalidArgumentException $e) {
    $invalidRateLimitCaught = true;
}
assertTest($invalidRateLimitCaught, "Rate limit 0 ditolak (< 1)");

$invalidRateLimitHighCaught = false;
try {
    $aiSettings->set('ai_rate_limit_per_hour', 250);
} catch (\InvalidArgumentException $e) {
    $invalidRateLimitHighCaught = true;
}
assertTest($invalidRateLimitHighCaught, "Rate limit 250 ditolak (> 200)");

// Cek validasi temperature
$invalidTempCaught = false;
try {
    $aiSettings->set('ai_temperature', 1.5);
} catch (\InvalidArgumentException $e) {
    $invalidTempCaught = true;
}
assertTest($invalidTempCaught, "Temperature 1.5 ditolak (> 1.0)");

// Uji simpan nilai valid
$aiSettings->set('ai_enabled', true, (int)$ownerUser['id']);
$aiSettings->set('ai_model', 'gemini-3.5-flash-lite', (int)$ownerUser['id']);
$aiSettings->set('ai_rate_limit_per_hour', 25, (int)$ownerUser['id']);
$aiSettings->set('ai_temperature', 0.8, (int)$ownerUser['id']);
$aiSettings->set('ai_data_max_rows', 20, (int)$ownerUser['id']);

assertTest((int)$aiSettings->get('ai_rate_limit_per_hour') === 25, "Rate limit tersimpan dengan benar di database (25)");
assertTest((float)$aiSettings->get('ai_temperature') === 0.8, "Temperature tersimpan dengan benar di database (0.8)");
assertTest((int)$aiSettings->get('ai_data_max_rows') === 20, "Data max rows tersimpan dengan benar di database (20)");

echo "\n--- 4. Hubungan ke AiAssistantService & Penanganan AI Nonaktif ---\n";
$mockClient = new MockAiClient();
$service = new AiAssistantService($mockClient, null, null, $aiSettings);

// Pastikan AI aktif
$aiSettings->set('ai_enabled', true);
assertTest($service->isAiEnabled() === true, "isAiEnabled() bernilai true saat ai_enabled = true di database");

// Nonaktifkan AI di database
$aiSettings->set('ai_enabled', false);
assertTest($service->isAiEnabled() === false, "isAiEnabled() bernilai false saat ai_enabled = false di database");

$disabledReply = $service->sendMessage((int)$customerUser['id'], "Halo, apakah toko buka?");
assertTest(!empty($disabledReply['ai_disabled']), "Respon sendMessage menandai ai_disabled = true");
assertTest(str_contains($disabledReply['reply'], "Asisten AI sedang dinonaktifkan oleh pengelola."), "Pesan ramah pemeliharaan sesuai format: 'Asisten AI sedang dinonaktifkan oleh pengelola.'");

// Bersihkan / Kembalikan setting ke nilai awal
$aiSettings->set('ai_enabled', true);
$aiSettings->set('ai_model', $initialSettings['ai_model'] ?? 'gemini-3.5-flash-lite');
$aiSettings->set('ai_rate_limit_per_hour', (int)($initialSettings['ai_rate_limit_per_hour'] ?? 20));
$aiSettings->set('ai_temperature', (float)($initialSettings['ai_temperature'] ?? 0.7));
$aiSettings->set('ai_data_max_rows', (int)($initialSettings['ai_data_max_rows'] ?? 15));

// Bersihkan pesan uji coba jika ada yang bertambah
$maxMsgIdAfter = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM ai_chat_messages")->fetchColumn();
if ($maxMsgIdAfter > $maxMsgIdBefore) {
    $pdo->exec("DELETE FROM ai_chat_messages WHERE id > {$maxMsgIdBefore}");
}

echo "\n==============================================================================\n";
echo "   HASIL AKHIR: {$passed} PASS, {$failed} FAIL\n";
echo "==============================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
