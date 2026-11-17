<?php

/**
 * Test Suite: Fitur Konfigurasi API Chatbot Dashboard Admin Toko
 */

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

use App\Core\Database;
use App\Helpers\Auth;
use App\Models\Store;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiHealthCheck;
use App\Services\Ai\AiSettings;
use App\Services\Ai\GeminiAiClient;
use App\Services\Ai\OpenAiCompatibleClient;

$pdo = Database::getInstance();
$aiSettings = new AiSettings($pdo);

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description, ?string $detail = null): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
        if ($detail !== null) {
            echo "         Detail: {$detail}\n";
        }
    }
}

echo "\n==============================================================================\n";
echo "   TEST SUITE: KONFIGURASI API CHATBOT DASHBOARD ADMIN TOKO\n";
echo "==============================================================================\n";

// 1. Verifikasi Struktur Tabel Database store_ai_settings
echo "\n--- 1. Struktur Database store_ai_settings ---\n";
$cols = $pdo->query("SHOW COLUMNS FROM store_ai_settings")->fetchAll(PDO::FETCH_COLUMN);
assertTest(in_array('provider', $cols), "Kolom 'provider' tersedia di store_ai_settings");
assertTest(in_array('model', $cols), "Kolom 'model' tersedia di store_ai_settings");
assertTest(in_array('api_key_enc', $cols), "Kolom 'api_key_enc' tersedia di store_ai_settings");
assertTest(in_array('api_key_last4', $cols), "Kolom 'api_key_last4' tersedia di store_ai_settings");
assertTest(in_array('base_url', $cols), "Kolom 'base_url' tersedia di store_ai_settings");
assertTest(in_array('ai_enabled', $cols), "Kolom 'ai_enabled' tersedia di store_ai_settings");

// 2. Simpan & Enkripsi Kredensial Toko (AES-256-GCM)
echo "\n--- 2. Penyimpanan Kredensial & Enkripsi AES-256 ---\n";
$dummyKey = "gsk_test_api_key_for_groq_1234567890";
$saveOk = $aiSettings->saveStoreAiApiConfig(1, [
    'provider'      => 'groqcloud',
    'model'         => 'llama-3.3-70b-versatile',
    'base_url'      => 'https://api.groq.com/openai/v1',
    'ai_enabled'    => 1,
    'api_key'       => $dummyKey,
    'clear_api_key' => false,
], 4);

assertTest($saveOk === true, "saveStoreAiApiConfig berhasil menyimpan data ke database");

$stmt = $pdo->prepare("SELECT * FROM store_ai_settings WHERE store_id = 1 LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

assertTest($row['provider'] === 'groqcloud', "Provider tersimpan: groqcloud");
assertTest($row['model'] === 'llama-3.3-70b-versatile', "Model tersimpan: llama-3.3-70b-versatile");
assertTest($row['base_url'] === 'https://api.groq.com/openai/v1', "Base URL tersimpan: https://api.groq.com/openai/v1");
assertTest(!empty($row['api_key_enc']), "API Key tersimpan terenkripsi (bukan plaintext)");
assertTest($row['api_key_enc'] !== $dummyKey, "Ciphertext berbeda dari plain key");
assertTest($row['api_key_last4'] === substr($dummyKey, -4), "Last4 tersimpan benar: " . substr($dummyKey, -4));

// 3. Dekripsi Kredensial & Pengambilan Efektif
echo "\n--- 3. Dekripsi Kredensial & Isolasi Pengaturan Toko ---\n";
$decrypted = $aiSettings->getStoreDecryptedApiKey(1);
assertTest($decrypted === $dummyKey, "getStoreDecryptedApiKey() berhasil mendekripsi kembali key asli");

$effective1 = $aiSettings->getEffectiveStoreAiConfig(1);
assertTest($effective1['provider'] === 'groqcloud', "Effective provider Toko 1: groqcloud");
assertTest($effective1['model'] === 'llama-3.3-70b-versatile', "Effective model Toko 1: llama-3.3-70b-versatile");
assertTest($effective1['has_store_key'] === true, "has_store_key = true");

// Toko 2 (tidak disetel) terisolasi dari Toko 1
$effective2 = $aiSettings->getEffectiveStoreAiConfig(2);
assertTest($effective2['provider'] === 'gemini', "Toko 2 terisolasi (provider default: gemini)");
assertTest($effective2['has_store_key'] === false, "Toko 2 tidak mewarisi key khusus Toko 1");

// 4. Resolusi Client pada Chatbot untuk Toko
echo "\n--- 4. Integrasi Chatbot Service dengan Client Provider Toko ---\n";
$service = new AiAssistantService(null, null, null, $aiSettings);
$resolvedClient = $service->resolveClientForStore(1);
assertTest($resolvedClient instanceof OpenAiCompatibleClient, "Chatbot menginstansiasi OpenAiCompatibleClient untuk Toko 1 (GroqCloud)");

// 5. Uji Penyedia OpenAI & Custom Base URL
echo "\n--- 5. Penyedia OpenAI & OpenAI Compatible API ---\n";
$openaiKey = "sk-test-proj-sample_openai_key_99887766";
$aiSettings->saveStoreAiApiConfig(1, [
    'provider'      => 'openai',
    'model'         => 'gpt-4o-mini',
    'base_url'      => 'https://api.openai.com/v1',
    'ai_enabled'    => 1,
    'api_key'       => $openaiKey,
], 4);

$effectiveOpenAi = $aiSettings->getEffectiveStoreAiConfig(1);
assertTest($effectiveOpenAi['provider'] === 'openai', "Provider berganti ke openai");
assertTest($effectiveOpenAi['model'] === 'gpt-4o-mini', "Model berganti ke gpt-4o-mini");
$clientOpenAi = $service->resolveClientForStore(1);
assertTest($clientOpenAi instanceof OpenAiCompatibleClient, "Client resolved ke OpenAiCompatibleClient untuk OpenAI");

// 6. Uji Penyedia Google Gemini Kembali
echo "\n--- 6. Penyedia Google Gemini ---\n";
$geminiKey = "AIzaSyDummyGeminiKeyTest123456789";
$aiSettings->saveStoreAiApiConfig(1, [
    'provider'      => 'gemini',
    'model'         => 'gemini-2.5-flash',
    'base_url'      => '',
    'ai_enabled'    => 1,
    'api_key'       => $geminiKey,
], 4);

$effectiveGemini = $aiSettings->getEffectiveStoreAiConfig(1);
assertTest($effectiveGemini['provider'] === 'gemini', "Provider kembali ke gemini");
$clientGemini = $service->resolveClientForStore(1);
assertTest($clientGemini instanceof GeminiAiClient, "Client resolved ke GeminiAiClient untuk Gemini");

// 7. Uji Navigasi Menu Dashboard Admin Toko
echo "\n--- 7. Verifikasi Navigasi Menu Dashboard Admin Toko ---\n";
$sidebarContent = file_get_contents(__DIR__ . '/../app/Views/layouts/sidebar.php');
assertTest(str_contains($sidebarContent, 'Konfigurasi API Chatbot'), "Menu 'Konfigurasi API Chatbot' ada di sidebar Admin Toko");
assertTest(str_contains($sidebarContent, '/admin/store-ai-settings'), "Link sidebar mengarah ke /admin/store-ai-settings");

$dashboardContent = file_get_contents(__DIR__ . '/../app/Views/dashboard/admin_toko.php');
assertTest(str_contains($dashboardContent, 'Konfigurasi API Chatbot'), "Tombol 'Konfigurasi API Chatbot' ada di dashboard Admin Toko");
assertTest(str_contains($dashboardContent, '/admin/store-ai-settings'), "Link dashboard mengarah ke /admin/store-ai-settings");

// 8. Pembersihan Data Toko 1
$pdo->exec("UPDATE store_ai_settings SET provider = 'gemini', model = 'gemini-2.5-flash', api_key_enc = NULL, api_key_last4 = NULL, base_url = NULL WHERE store_id = 1");

echo "\n==============================================================================\n";
echo "SUMMARY: {$passed} PASS, {$failed} FAIL\n";
echo "==============================================================================\n";

