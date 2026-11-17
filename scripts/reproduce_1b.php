<?php
require_once __DIR__ . '/../vendor/autoload.php';
// If vendor/autoload doesn't exist, use public/index autoloader:
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

use App\Services\Ai\AiAssistantService;
use App\Services\Ai\MockAiClient;

echo "=== TEST 1B: AiAssistantService::sendMessage() ===\n\n";

$users = [
    'admin.toko' => 4,
    'customer'   => 6,
];

// --- 1. Real provider (Gemini or whatever effective provider is configured) ---
echo "--- 1. Using Default / Resolved Client (Provider from settings/env) ---\n";
foreach ($users as $name => $uid) {
    echo "\n[User: $name (ID: $uid) | Prompt: 'siapa saya']\n";
    try {
        $service = new AiAssistantService();
        $clientClass = get_class($service->getClient());
        echo "Active Client Class: $clientClass\n";
        $res = $service->sendMessage($uid, "siapa saya");
        echo "Result Success: " . ($res['success'] ? 'true' : 'false') . "\n";
        echo "Reply: " . ($res['reply'] ?? 'NULL') . "\n";
        if (isset($res['error'])) {
            echo "Error: " . $res['error'] . "\n";
        }
    } catch (\Throwable $e) {
        echo "CAUGHT EXCEPTION!\n";
        echo "Class: " . get_class($e) . "\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File:Line: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
    }
}

// --- 2. Explicit MockAiClient ---
echo "\n--- 2. Using Explicit MockAiClient ---\n";
foreach ($users as $name => $uid) {
    echo "\n[User: $name (ID: $uid) | Prompt: 'siapa saya']\n";
    try {
        $mockClient = new MockAiClient();
        $service = new AiAssistantService($mockClient);
        $res = $service->sendMessage($uid, "siapa saya");
        echo "Result Success: " . ($res['success'] ? 'true' : 'false') . "\n";
        echo "Reply: " . ($res['reply'] ?? 'NULL') . "\n";
        if (isset($res['error'])) {
            echo "Error: " . $res['error'] . "\n";
        }
    } catch (\Throwable $e) {
        echo "CAUGHT EXCEPTION!\n";
        echo "Class: " . get_class($e) . "\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File:Line: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
    }
}

