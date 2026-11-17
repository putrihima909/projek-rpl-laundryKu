<?php

/**
 * Runner Pembuktian Pertanyaan AI:
 * Menjalankan 3 pertanyaan ("berapa omset hari ini", "berapa pesanan hari ini", "berapa pelanggan aktif")
 * untuk 3 akun:
 * 1. admin.toko@laundry.test (Role: admin_toko)
 * 2. admin.owner@laundry.test (Role: admin_owner)
 * 3. customer@laundry.test (Role: customer)
 *
 * Menguji dengan MockAiClient dan GeminiAiClient live (jika API key terisi).
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
use App\Helpers\Session;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\MockAiClient;
use App\Services\Ai\GeminiAiClient;

Session::start();
$db = Database::getInstance();

$accounts = [
    'admin_toko' => 'admin.toko@laundry.test',
    'admin_owner' => 'admin.owner@laundry.test',
    'customer' => 'customer@laundry.test',
];

$questions = [
    "berapa omset hari ini",
    "berapa pesanan hari ini",
    "berapa pelanggan aktif",
];

$users = [];
foreach ($accounts as $key => $email) {
    $stmt = $db->prepare("SELECT u.id, u.name, u.email, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = :em LIMIT 1");
    $stmt->execute(['em' => $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        die("User {$email} not found.\n");
    }
    $users[$key] = $u;
}

echo "==============================================================================\n";
echo "A. PENGUJIAN JAWABAN MENTAH: FALLBACK MOCK AI CLIENT\n";
echo "==============================================================================\n";

$mockService = new AiAssistantService(new MockAiClient());

foreach ($users as $key => $u) {
    echo "\n------------------------------------------------------------------------------\n";
    echo "AKUN: {$u['email']} | ROLE: {$u['role']} (ID: {$u['id']})\n";
    echo "------------------------------------------------------------------------------\n";

    Auth::login([
        'id' => (int)$u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'role_name' => $u['role'],
        'is_active' => true
    ]);

    foreach ($questions as $q) {
        $res = $mockService->sendMessage((int)$u['id'], $q);
        echo ">>> USER: \"{$q}\"\n";
        echo ">>> AI:\n" . trim($res['reply']) . "\n\n";
    }
}

echo "\n==============================================================================\n";
echo "B. PENGUJIAN JAWABAN MENTAH: GOOGLE GEMINI API LIVE\n";
echo "==============================================================================\n";

$apiKey = (string) \config('ai.gemini.api_key', '');
if (empty($apiKey)) {
    echo "GEMINI_API_KEY tidak terkonfigurasi. Pengujian live Gemini dilewati.\n";
} else {
    echo "GEMINI_API_KEY terdeteksi aktif. Model: " . \config('ai.gemini.model') . "\n";
    $geminiClient = new GeminiAiClient();
    $geminiService = new AiAssistantService($geminiClient);

    foreach ($users as $key => $u) {
        echo "\n------------------------------------------------------------------------------\n";
        echo "AKUN: {$u['email']} | ROLE: {$u['role']} (ID: {$u['id']}) via Gemini Live\n";
        echo "------------------------------------------------------------------------------\n";

        Auth::login([
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role_name' => $u['role'],
            'is_active' => true
        ]);

        foreach ($questions as $q) {
            $res = $geminiService->sendMessage((int)$u['id'], $q);
            echo ">>> USER: \"{$q}\"\n";
            if ($res['success']) {
                echo ">>> GEMINI LIVE:\n" . trim($res['reply']) . "\n\n";
            } else {
                echo ">>> GEMINI ERROR: " . ($res['error'] ?? 'Unknown error') . "\n";
                echo ">>> FALLBACK REPLY:\n" . trim($res['reply']) . "\n\n";
            }
            // Sleep briefly to respect API rate limits
            usleep(600000); // 600ms
        }
    }
}

