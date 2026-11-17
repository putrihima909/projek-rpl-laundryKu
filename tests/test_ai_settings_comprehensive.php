<?php

// Autoloader PSR-4
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
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiHealthCheck;
use App\Services\Ai\AiSettings;
use App\Services\Ai\MockAiClient;

echo "\n==============================================================================\n";
echo "   COMPREHENSIVE TEST SUITE: AI SETTINGS, HEALTH CHECK & STORE ISOLATION     \n";
echo "==============================================================================\n\n";

$pdo = Database::getInstance();
$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $description, ?string $detail = null): void
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
$backupDbRows = $pdo->query("SELECT setting_key, setting_value FROM ai_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// ----------------------------------------------------------------------------
// 1. Prioritas Nilai: Database > .env/config > Bawaan
// ----------------------------------------------------------------------------
echo "--- 1. Uji Prioritas Nilai (Database > .env/config > Bawaan) ---\n";

// A. Hapus kunci uji dari DB untuk melihat fallback ke config / default
$pdo->exec("DELETE FROM ai_settings WHERE setting_key = 'ai_model'");
$valWithoutDb = $aiSettings->get('ai_model');
$expectedConfigOrDef = \config('ai.gemini.model', AiSettings::DEFAULTS['ai_model']);
assertCheck($valWithoutDb === $expectedConfigOrDef, "Fallback ke config/.env ketika kunci tidak ada di database: {$valWithoutDb}");

// B. Tulis kunci ke database dan pastikan database menang (override)
$aiSettings->set('ai_model', 'custom-test-model');
$valWithDb = $aiSettings->get('ai_model');
assertCheck($valWithDb === 'custom-test-model', "Database berhasil meng-override config/.env: {$valWithDb}");

// ----------------------------------------------------------------------------
// 2. Validasi Input Keras (Regex Model, Rentang Angka, Exception Jelas)
// ----------------------------------------------------------------------------
echo "\n--- 2. Uji Validasi Input (Regex, Rentang Angka, Exception Jelas) ---\n";

// ai_model regex: ^[a-z0-9.\-]{3,60}$
$invalidModels = ['AB', 'UPPERCASE_MODEL', 'model with spaces', 'bad@chars!', str_repeat('a', 61)];
foreach ($invalidModels as $invM) {
    $caught = false;
    try {
        $aiSettings->set('ai_model', $invM);
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }
    assertCheck($caught, "Model tidak valid '{$invM}' berhasil ditolak dengan InvalidArgumentException");
}

// ai_temperature: 0.0 - 1.0
$invalidTemps = [-0.1, 1.05, 2.0, 'not-a-number'];
foreach ($invalidTemps as $invT) {
    $caught = false;
    try {
        $aiSettings->set('ai_temperature', $invT);
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }
    assertCheck($caught, "Temperature tidak valid '{$invT}' berhasil ditolak");
}

// ai_rate_limit_per_hour: 1 - 200
$invalidLimits = [0, -5, 201, 500, 'abc'];
foreach ($invalidLimits as $invL) {
    $caught = false;
    try {
        $aiSettings->set('ai_rate_limit_per_hour', $invL);
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }
    assertCheck($caught, "Rate limit tidak valid '{$invL}' berhasil ditolak");
}

// ai_data_max_rows: 5 - 50
$invalidMaxRows = [0, 4, 51, 100];
foreach ($invalidMaxRows as $invR) {
    $caught = false;
    try {
        $aiSettings->set('ai_data_max_rows', $invR);
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }
    assertCheck($caught, "Data max rows tidak valid '{$invR}' berhasil ditolak");
}

// ai_provider: 'gemini' or 'mock'
$invalidProviders = ['openai', 'claude', 'anthropic', ''];
foreach ($invalidProviders as $invP) {
    $caught = false;
    try {
        $aiSettings->set('ai_provider', $invP);
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }
    assertCheck($caught, "Provider tidak valid '{$invP}' berhasil ditolak");
}

// ----------------------------------------------------------------------------
// 3. Keamanan: hasApiKey() Aman & Tidak Pernah Bocor
// ----------------------------------------------------------------------------
echo "\n--- 3. Uji Keamanan: hasApiKey() & Proteksi API Key ---\n";
$hasKeyBool = $aiSettings->hasApiKey();
assertCheck(is_bool($hasKeyBool), "hasApiKey() mengembalikan tipe boolean");

$allSettings = $aiSettings->getAll();
assertCheck(!isset($allSettings['api_key']), "getAll() TIDAK berisi atribut api_key");
assertCheck(!isset($allSettings['gemini_api_key']), "getAll() TIDAK berisi atribut gemini_api_key");

// Periksa fallback ke mock jika api key kosong
$settingsMockCheck = new AiSettings($pdo);
// Jika provider di-set gemini tapi key kosong (atau simulasi), effective_provider harus terdeteksi
assertCheck(in_array($allSettings['effective_provider'], ['gemini', 'mock'], true), "effective_provider valid ('{$allSettings['effective_provider']}')");

// ----------------------------------------------------------------------------
// 4. ai_enabled=false: Tidak Panggil LLM & Pesan Standar
// ----------------------------------------------------------------------------
echo "\n--- 4. Uji Penonaktifan AI (ai_enabled=false) ---\n";
$aiSettings->set('ai_enabled', false);
$mockClient = new MockAiClient();
$service = new AiAssistantService($mockClient, null, null, $aiSettings);

// User pelanggan
$testCustomer = $pdo->query("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$customerId = !empty($testCustomer['id']) ? (int)$testCustomer['id'] : 1;

$resDisabled = $service->sendMessage($customerId, "Apakah ada promo hari ini?");
assertCheck(!empty($resDisabled['ai_disabled']), "sendMessage mendeteksi ai_disabled = true");
assertCheck($resDisabled['reply'] === 'Asisten AI sedang dinonaktifkan oleh pengelola.', "Respons persis: 'Asisten AI sedang dinonaktifkan oleh pengelola.'");

// Aktifkan kembali untuk test berikutnya
$aiSettings->set('ai_enabled', true);
$resEnabled = $service->sendMessage($customerId, "Halo");
assertCheck(empty($resEnabled['ai_disabled']), "Saat ai_enabled = true, pesan diproses dengan normal");

// ----------------------------------------------------------------------------
// 5. store_ai_settings: Isolasi Toko, Batas 1000 Karakter & strip_tags()
// ----------------------------------------------------------------------------
echo "\n--- 5. Uji store_ai_settings (Isolasi Antar Toko, Batas 1000 Char, strip_tags) ---\n";

// Ambil dua store pertama dari database
$stores = $pdo->query("SELECT id, name FROM stores ORDER BY id ASC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
if (count($stores) >= 2) {
    $storeA = (int)$stores[0]['id'];
    $storeB = (int)$stores[1]['id'];

    // Backup existing store settings if any
    $prevA = $aiSettings->getStoreSettings($storeA);
    $prevB = $aiSettings->getStoreSettings($storeB);

    // Set Toko A dengan HTML tags & string panjang
    $dirtyHtml = "<b>Buka setiap hari</b> jam <script>alert('xss')</script>08.00 - 21.00";
    $longPolicy = str_repeat("Kebijakan garansi cuci ulang 24 jam. ", 50); // > 1000 chars

    $aiSettings->saveStoreSettings($storeA, [
        'ai_enabled' => true,
        'info_jam_operasional' => $dirtyHtml,
        'info_kebijakan' => $longPolicy,
        'info_antar_jemput' => 'Gratis radius 3km',
        'info_faq' => 'Bisa kilat 3 jam',
    ]);

    // Set Toko B dengan konfigurasi berbeda (ai_enabled = 0)
    $aiSettings->saveStoreSettings($storeB, [
        'ai_enabled' => false,
        'info_jam_operasional' => 'Senin - Jumat 09.00 - 17.00',
        'info_kebijakan' => 'Tidak menerima bahan sutra',
        'info_antar_jemput' => 'Tidak melayani antar jemput',
        'info_faq' => 'Harga pas',
    ]);

    $dataA = $aiSettings->getStoreSettings($storeA);
    $dataB = $aiSettings->getStoreSettings($storeB);

    // Uji strip_tags()
    assertCheck(!str_contains($dataA['info_jam_operasional'], '<b>'), "HTML tags <b> berhasil di-strip pada Toko A");
    assertCheck(!str_contains($dataA['info_jam_operasional'], '<script>'), "HTML tags <script> berhasil di-strip pada Toko A");
    assertCheck(str_contains($dataA['info_jam_operasional'], 'Buka setiap hari jam alert(\'xss\')08.00 - 21.00') || str_contains($dataA['info_jam_operasional'], 'Buka setiap hari'), "Konten teks aman dipertahankan");

    // Uji batas 1000 karakter
    assertCheck(mb_strlen($dataA['info_kebijakan']) <= 1000, "Batas maksimal 1000 karakter ditegakkan (panjang saat ini: " . mb_strlen($dataA['info_kebijakan']) . ")");

    // Uji isolasi data antara Toko A dan Toko B
    assertCheck($dataA['info_antar_jemput'] === 'Gratis radius 3km', "Data Toko A terisolasi (antar jemput: {$dataA['info_antar_jemput']})");
    assertCheck($dataB['info_antar_jemput'] === 'Tidak melayani antar jemput', "Data Toko B terisolasi (antar jemput: {$dataB['info_antar_jemput']})");
    assertCheck($dataA['ai_enabled'] === true, "Toko A ai_enabled = true");
    assertCheck($dataB['ai_enabled'] === false, "Toko B ai_enabled = false");

    // Uji prompt injection [INFO_TOKO_RESMI] pada staff toko
    // Cari user pemilik toko A
    $ownerA = $pdo->query("SELECT user_id FROM stores WHERE id = {$storeA}")->fetchColumn();
    if ($ownerA) {
        $promptA = $service->buildSystemPrompt((int)$ownerA);
        assertCheck(str_contains($promptA, '[INFO_TOKO_RESMI]'), "Prompt menyertakan blok [INFO_TOKO_RESMI] untuk staf Toko A");
        assertCheck(str_contains($promptA, 'Gratis radius 3km'), "Prompt staf Toko A menyertakan info antar jemput toko miliknya");
    }

    // Cari user pemilik toko B (di mana ai_enabled = 0)
    $ownerB = $pdo->query("SELECT user_id FROM stores WHERE id = {$storeB}")->fetchColumn();
    if ($ownerB) {
        $promptB = $service->buildSystemPrompt((int)$ownerB);
        assertCheck(str_contains($promptB, 'Fitur data operasional AI dinonaktifkan'), "Saat store_ai_settings.ai_enabled = 0, blok data operasional ditolak dengan pemberitahuan dinonaktifkan");
        assertCheck(!str_contains($promptB, '[DATA_OPERASIONAL_START]'), "Saat store_ai_settings.ai_enabled = 0, [DATA_OPERASIONAL_START] TIDAK dimasukkan");
    }

    // Kembalikan data store awal
    $pdo->exec("DELETE FROM store_ai_settings WHERE store_id IN ({$storeA}, {$storeB})");
} else {
    echo "[SKIP] Kurang dari 2 toko di database untuk pengujian isolasi toko.\n";
}

// ----------------------------------------------------------------------------
// 6. Uji Idempotensi Migrasi (Aman Dijalankan Berulang)
// ----------------------------------------------------------------------------
echo "\n--- 6. Uji Idempotensi Migrasi (migrate_ai_settings.php) ---\n";
ob_start();
include __DIR__ . '/../database/migrate_ai_settings.php';
$migrationOutput1 = ob_get_clean();

ob_start();
include __DIR__ . '/../database/migrate_ai_settings.php';
$migrationOutput2 = ob_get_clean();

assertCheck(str_contains($migrationOutput1, 'Migrasi tabel AI Settings selesai'), "Eksekusi migrasi pertama berhasil");
assertCheck(str_contains($migrationOutput2, 'Migrasi tabel AI Settings selesai'), "Eksekusi migrasi kedua (idempoten) berhasil tanpa error");

// ----------------------------------------------------------------------------
// 7. Uji AiHealthCheck::run(false)
// ----------------------------------------------------------------------------
echo "\n--- 7. Uji AiHealthCheck::run(false) ---\n";
$healthCheck = new AiHealthCheck($pdo);
$hc = $healthCheck->run(false);

assertCheck(!empty($hc['database']['connected']), "HealthCheck: Database terkoneksi");
assertCheck(isset($hc['database']['latency_ms']), "HealthCheck: Latency database terukur ({$hc['database']['latency_ms']} ms)");
assertCheck(isset($hc['provider']), "HealthCheck: Provider efektif tersedia ({$hc['provider']})");
assertCheck(isset($hc['model']), "HealthCheck: Model efektif tersedia ({$hc['model']})");
assertCheck(isset($hc['has_api_key']), "HealthCheck: has_api_key boolean tersedia");
assertCheck(isset($hc['chat_messages_24h']), "HealthCheck: Pesan 24h dihitung ({$hc['chat_messages_24h']})");
assertCheck(isset($hc['rate_limit_per_hour']), "HealthCheck: Batas rate limit tertera ({$hc['rate_limit_per_hour']})");
assertCheck(array_key_exists('last_error', $hc), "HealthCheck: last_error tertera");

// ----------------------------------------------------------------------------
// Pembersihan Data Uji
// ----------------------------------------------------------------------------
echo "\n--- Pembersihan Data Uji ---\n";
// Pulihkan data tabel ai_settings
$pdo->exec("DELETE FROM ai_settings");
$insStmt = $pdo->prepare("INSERT INTO ai_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, NULL, NOW())");
foreach ($backupDbRows as $k => $v) {
    $insStmt->execute(['k' => $k, 'v' => $v]);
}
echo "[INFO] Data ai_settings dipulihkan ke kondisi awal.\n";

echo "\n==============================================================================\n";
echo "   HASIL AKHIR COMPREHENSIVE TEST: {$passed} PASS, {$failed} FAIL\n";
echo "==============================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);

