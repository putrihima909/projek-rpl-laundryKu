<?php

/**
 * Automated Test Suite: Tahap 10 — AI Chat Assistant, Rate Limiting & Guardrail System
 */

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

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\User;
use App\Models\Order;
use App\Models\AiChatMessage;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\MockAiClient;
use App\Services\Ai\GeminiAiClient;
use App\Services\Ai\LlmClientInterface;
use App\Controllers\AiChatController;

$pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
]);

// Ensure test table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `sender` ENUM('user', 'assistant') NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_ai_chat_user_test` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $description, ?string $detail = null): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . ($detail ? " -> " . $detail : "") . "\n";
        $failCount++;
    }
}

echo "\n==============================================================================\n";
echo "   TAHAP 10: AI ASSISTANT, GUARDRAILS, RATE LIMIT & ORDER CONTEXT SUITE       \n";
echo "==============================================================================\n\n";

// Ensure test user exists (Customer)
$stmt = $pdo->query("SELECT u.id, u.email, r.name as role, u.name as full_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer' LIMIT 1");
$testUser = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$testUser) {
    $roleStmt = $pdo->query("SELECT id FROM roles WHERE name = 'customer' LIMIT 1");
    $roleRow = $roleStmt->fetch(\PDO::FETCH_ASSOC);
    $roleId = $roleRow ? (int)$roleRow['id'] : 6;
    $pdo->prepare("INSERT INTO users (role_id, name, email, password, phone, is_active) VALUES (:rid, 'Customer Test AI', 'customer_ai@example.com', 'hash', '08123456789', 1)")
        ->execute(['rid' => $roleId]);
    $testUserId = (int)$pdo->lastInsertId();
} else {
    $testUserId = (int)$testUser['id'];
}

// Reset chat messages for test user
$pdo->prepare("DELETE FROM ai_chat_messages WHERE user_id = :uid")->execute(['uid' => $testUserId]);

// ==========================================
// 1. Auth Protection & Security Tests
// ==========================================
echo "--- 1. Auth Protection & Endpoint Security ---\n";
Session::start();
Auth::logout(); // Ensure unauthenticated

// Intercept output from controller
function callControllerJson(callable $fn): array {
    ob_start();
    try {
        $fn();
    } catch (\Exception $e) {
        // Handle exception if thrown
    }
    $output = ob_get_clean();
    $httpCode = http_response_code();
    $json = json_decode($output, true);
    return ['code' => $httpCode, 'body' => $output, 'json' => $json];
}

$mockClient = new MockAiClient();
$service = new AiAssistantService($mockClient);
$controller = new AiChatController($service);

// 1.1 Unauthenticated POST /api/ai/chat
$_POST = ['message' => 'Halo'];
$res1 = callControllerJson(fn() => $controller->chat());
assertTest(($res1['code'] === 401 || ($res1['json']['status'] ?? '') === 'error') && ($res1['json']['success'] ?? null) === false, "POST /api/ai/chat returns 401 Unauthorized when unauthenticated");

// 1.2 Unauthenticated GET /api/ai/history
$res2 = callControllerJson(fn() => $controller->history());
assertTest(($res2['code'] === 401 || ($res2['json']['status'] ?? '') === 'error') && ($res2['json']['success'] ?? null) === false, "GET /api/ai/history returns 401 Unauthorized when unauthenticated");

// 1.3 Unauthenticated POST /api/ai/clear
$res3 = callControllerJson(fn() => $controller->clear());
assertTest(($res3['code'] === 401 || ($res3['json']['status'] ?? '') === 'error') && ($res3['json']['success'] ?? null) === false, "POST /api/ai/clear returns 401 Unauthorized when unauthenticated");

// Now simulate user login
Auth::login([
    'id' => $testUserId,
    'name' => 'Customer Test AI',
    'email' => 'customer_ai@example.com',
    'role_name' => 'customer',
    'is_active' => true
]);

// 1.4 Empty message validation
$_POST = ['message' => '   '];
$res4 = callControllerJson(fn() => $controller->chat());
assertTest(($res4['code'] === 400 || ($res4['json']['status'] ?? '') === 'error') && ($res4['json']['success'] ?? null) === false, "POST /api/ai/chat rejects empty message with 400 Bad Request");


// ==========================================
// 2. Service & Chat Flow Tests
// ==========================================
echo "\n--- 2. Service & Normal Conversation Flow ---\n";

$mockClient->setForcedReply("Halo! Saya siap membantu rekomendasi layanan laundry untuk Anda.");
$chatResult = $service->sendMessage($testUserId, "Halo, apa saja layanan yang tersedia?");

assertTest($chatResult['success'] === true, "sendMessage returns success: true");
assertTest(str_contains($chatResult['reply'], "rekomendasi layanan laundry"), "sendMessage returns assistant reply correctly");
assertTest(isset($chatResult['rate_limit']['remaining']), "sendMessage returns rate limit quota metadata");

// Verify messages are saved to database
$history = $service->getHistory($testUserId, 10);
assertTest(count($history) === 2, "Database records both user message and assistant reply");
assertTest($history[0]['sender'] === 'user' && $history[0]['message'] === 'Halo, apa saja layanan yang tersedia?', "User message recorded accurately in chronological order");
assertTest($history[1]['sender'] === 'assistant' && str_contains($history[1]['message'], 'rekomendasi layanan laundry'), "Assistant reply recorded accurately");


// ==========================================
// 3. System Prompt & Active Order Context
// ==========================================
echo "\n--- 3. System Prompt & Active Order Context Injection ---\n";

// Reset forced reply so mock logic handles scenarios
$mockClient->setForcedReply(null);

$sysPrompt = $service->buildSystemPrompt($testUserId);
assertTest(str_contains($sysPrompt, "ATURAN DAN BATASAN KETAT (GUARDRAILS)"), "System prompt contains strict guardrails section");
assertTest(str_contains($sysPrompt, "DILARANG KERAS berpura-pura atau mengklaim melakukan aksi/transaksi"), "System prompt explicitly bans fake transaction execution");
assertTest(str_contains($sysPrompt, "KATALOG LAYANAN UMUM PLATFORM"), "System prompt injects service catalog definitions");
assertTest(str_contains($sysPrompt, "[ORDER_INFO_START]"), "System prompt includes dynamic order context tag");


// ==========================================
// 4. PRD Scenario: Urgent & High Volume Order
// ==========================================
echo "\n--- 4. PRD Skenario: Cucian Banyak & Selesai Besok ---\n";

$prdQuestion = "Baju saya banyak dan ingin selesai besok, pilih layanan apa?";
$prdResult = $service->sendMessage($testUserId, $prdQuestion);

assertTest($prdResult['success'] === true, "PRD question processed successfully");
assertTest(
    str_contains(mb_strtolower($prdResult['reply']), "express") || str_contains(mb_strtolower($prdResult['reply']), "kilat"),
    "AI recommends Express / Kilat 1-Day service for urgent & large laundry volume",
    $prdResult['reply']
);
assertTest(
    str_contains(mb_strtolower($prdResult['reply']), "24 jam") || str_contains(mb_strtolower($prdResult['reply']), "1 hari"),
    "AI explains 24-hour turnaround timeframe",
    $prdResult['reply']
);

// 4.2 Natural query tests (avoiding static welcome greeting loop)
$orderQueryMsg = "berapa pesanan hari ini";
$orderQueryResult = $service->sendMessage($testUserId, $orderQueryMsg);
assertTest(
    !str_contains($orderQueryResult['reply'], "Halo! Saya adalah Smart Laundry AI Assistant. Ada yang bisa saya bantu"),
    "Query 'berapa pesanan hari ini' does NOT repeat initial welcome greeting"
);
assertTest(
    str_contains(mb_strtolower($orderQueryResult['reply']), "pesanan") || str_contains(mb_strtolower($orderQueryResult['reply']), "ringkasan"),
    "Query 'berapa pesanan hari ini' returns status or order info"
);

$affirmationMsg = "ya";
$affirmationResult = $service->sendMessage($testUserId, $affirmationMsg);
assertTest(
    !str_contains($affirmationResult['reply'], "Halo! Saya adalah Smart Laundry AI Assistant. Ada yang bisa saya bantu"),
    "Short affirmation 'ya' does NOT repeat initial welcome greeting"
);
assertTest(
    str_contains(mb_strtolower($affirmationResult['reply']), "ada yang bisa saya bantu lagi"),
    "Short affirmation 'ya' returns natural conversational response"
);

$customQueryMsg = "lokasi outlet terbanyak di daerah mana";
$customQueryResult = $service->sendMessage($testUserId, $customQueryMsg);
assertTest(
    str_contains($customQueryResult['reply'], "lokasi outlet terbanyak"),
    "Unmapped custom input echoes context dynamically in response fallback"
);


// ==========================================
// 5. Guardrail: Transaction Execution Denial
// ==========================================
echo "\n--- 5. Guardrail: Transaction Denial & Proper User Direction ---\n";

// 5.1 Cancellation attempt
$cancelMsg = "Tolong batalkan pesanan saya sekarang!";
$cancelResult = $service->sendMessage($testUserId, $cancelMsg);
assertTest(
    !str_contains(mb_strtolower($cancelResult['reply']), "saya sudah batalkan") &&
    !str_contains(mb_strtolower($cancelResult['reply']), "pesanan anda telah dibatalkan"),
    "AI does NOT pretend to execute order cancellation"
);
assertTest(
    str_contains(mb_strtolower($cancelResult['reply']), "riwayat pesanan") || str_contains(mb_strtolower($cancelResult['reply']), "batalkan pesanan"),
    "AI directs user to the platform UI to cancel the order"
);

// 5.2 Refund execution attempt
$refundMsg = "Saya minta refund uang kembali ke rekening saya.";
$refundResult = $service->sendMessage($testUserId, $refundMsg);
assertTest(
    !str_contains(mb_strtolower($refundResult['reply']), "saya sudah transfer") &&
    !str_contains(mb_strtolower($refundResult['reply']), "refund telah diproses"),
    "AI does NOT pretend to process refunds directly"
);
assertTest(
    str_contains(mb_strtolower($refundResult['reply']), "refund"),
    "AI explains refund policy and navigation"
);

// 5.3 Out-of-domain query
$offTopicMsg = "Bagaimana resep membuat kue bolu coklat?";
$offTopicResult = $service->sendMessage($testUserId, $offTopicMsg);
assertTest(
    str_contains(mb_strtolower($offTopicResult['reply']), "hanya dapat membantu") ||
    str_contains(mb_strtolower($offTopicResult['reply']), "layanan laundry") ||
    str_contains(mb_strtolower($offTopicResult['reply']), "luar topik"),
    "AI politely declines out-of-scope / non-laundry queries"
);


// ==========================================
// 6. Fabric Care Expertise
// ==========================================
echo "\n--- 6. Fabric & Garment Care Knowledge ---\n";

$silkMsg = "Bagaimana cara mencuci jas dan kebaya sutra?";
$silkResult = $service->sendMessage($testUserId, $silkMsg);
assertTest(
    str_contains(mb_strtolower($silkResult['reply']), "dry clean"),
    "AI recommends Dry Clean for delicate fabrics (silk, suits, kebaya)"
);


// ==========================================
// 7. Rate Limiting Protection (429 HTTP)
// ==========================================
echo "\n--- 7. Rate Limiting Enforcement (20 msg / hour) ---\n";

// Check remaining quota
$quotaBefore = $service->getRemainingQuota($testUserId);
assertTest($quotaBefore <= 20, "Quota tracking operates within 20 msg/hour ceiling (Current remaining: {$quotaBefore})");

// Simulate inserting 25 messages in the last 30 minutes
for ($i = 0; $i < 22; $i++) {
    $pdo->prepare("INSERT INTO ai_chat_messages (user_id, sender, message, created_at) VALUES (:uid, 'user', :msg, NOW())")
        ->execute(['uid' => $testUserId, 'msg' => "Spam msg {$i}"]);
}

assertTest($service->isRateLimited($testUserId) === true, "isRateLimited correctly identifies quota exhaustion");
assertTest($service->getRemainingQuota($testUserId) === 0, "getRemainingQuota returns 0 when limit is exceeded");

$_POST = ['message' => 'Pesan ke-21'];
$resRateLimit = callControllerJson(fn() => $controller->chat());
assertTest($resRateLimit['code'] === 429, "POST /api/ai/chat returns HTTP 429 Too Many Requests when rate limited");
assertTest($resRateLimit['json']['status'] === 'rate_limited', "Response contains status: rate_limited");
assertTest($resRateLimit['json']['rate_limit']['remaining'] === 0, "Response indicates 0 quota remaining");


// ==========================================
// 8. Network / API Failure Resilience
// ==========================================
echo "\n--- 8. API Failure & Network Drop Resilience ---\n";

// Clean rate limit spam for testing fallback
$pdo->prepare("DELETE FROM ai_chat_messages WHERE user_id = :uid")->execute(['uid' => $testUserId]);

$failingMock = new MockAiClient(true); // simulate failure
$failService = new AiAssistantService($failingMock);

$failResult = $failService->sendMessage($testUserId, "Tanya layanan saat API down");
assertTest($failResult['success'] === false, "Service handles failure gracefully with success: false");
assertTest(!empty($failResult['reply']), "Service provides polite user-friendly fallback response");
assertTest(!str_contains($failResult['reply'], "Exception") && !str_contains($failResult['reply'], "Stack trace"), "Fallback does NOT expose raw error stack traces to users");


// ==========================================
// 9. Clear Conversation History
// ==========================================
echo "\n--- 9. Clear Conversation History ---\n";

// Add a few messages
$service->sendMessage($testUserId, "Pesan sebelum clear");
assertTest(count($service->getHistory($testUserId, 10)) > 0, "History has messages before clear");

$resClear = callControllerJson(fn() => $controller->clear());
assertTest($resClear['code'] === 200 && ($resClear['json']['success'] ?? null) === true, "POST /api/ai/clear returns 200 OK");
assertTest(count($service->getHistory($testUserId, 10)) === 0, "User history successfully emptied in database");


// ==========================================
// 10. Gemini Client Structure & Payload Test
// ==========================================
echo "\n--- 10. Gemini API Client Contract Verification ---\n";

$geminiClient = new GeminiAiClient('TEST_KEY_DUMMY', 'gemini-1.5-flash', 'http://127.0.0.1:9999/dummy', 1);
assertTest($geminiClient instanceof LlmClientInterface, "GeminiAiClient properly implements LlmClientInterface");

$emptyKeyClient = new GeminiAiClient('', 'gemini-1.5-flash');
$emptyKeyRes = $emptyKeyClient->generateResponse("sys", [], "test");
assertTest($emptyKeyRes['success'] === false && str_contains($emptyKeyRes['error'], 'not configured'), "Gemini client gracefully handles missing API key");

// 10.2 Verify Logger records warning on silent fallback when provider is gemini with missing key
$logFile = \App\Helpers\Logger::getLogPath();
\App\Helpers\Env::set('AI_PROVIDER', 'gemini');
\App\Helpers\Env::set('GEMINI_API_KEY', '');
\App\Helpers\Config::clear();

$dummySvc = new AiAssistantService();
assertTest($dummySvc->getClient() instanceof MockAiClient, "Service falls back to MockAiClient when GEMINI_API_KEY is empty");

$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
assertTest(
    str_contains($logContent, "GEMINI_API_KEY is empty/missing"),
    "Logger records explicit warning in app.log when falling back from missing GEMINI_API_KEY"
);


// ==========================================
// 11. Logged-in User Identity Context & Profile Guardrails
// ==========================================
echo "\n--- 11. User Identity Profile Context & Safe Guardrails ---\n";

\App\Helpers\Env::set('AI_PROVIDER', 'mock');
\App\Helpers\Config::clear();
$idTestMock = new MockAiClient();
$idTestService = new AiAssistantService($idTestMock);

// (a) profil user login masuk ke system prompt
$testUserName = $testUser['full_name'];
$testUserEmail = $testUser['email'];

$sysPromptA = $idTestService->buildSystemPrompt($testUserId);
assertTest(str_contains($sysPromptA, "[USER_PROFILE_START]") && str_contains($sysPromptA, "[USER_PROFILE_END]"), "System prompt contains user profile context tags");
assertTest(str_contains($sysPromptA, $testUserName), "System prompt contains logged-in user full name ({$testUserName})");
assertTest(str_contains($sysPromptA, "Customer"), "System prompt contains translated role name (Customer)");
assertTest(str_contains($sysPromptA, $testUserEmail), "System prompt contains logged-in user email ({$testUserEmail})");

// (b) password / google_id / hash tidak ada di prompt
assertTest(
    !str_contains(strtolower($sysPromptA), 'google_id') &&
    !str_contains($sysPromptA, '$2y$') &&
    !str_contains(strtolower($sysPromptA), 'password_hash') &&
    !str_contains($sysPromptA, 'token'),
    "Sensitive credentials (password hash, google_id, tokens) are NEVER included in system prompt"
);

// (c) user A tidak bisa mendapat data profil user B & pertanyaan user lain ditolak
$stmtB = $pdo->query("SELECT u.id, u.name, u.email FROM users u WHERE u.id != {$testUserId} AND u.is_active = 1 LIMIT 1");
$userB = $stmtB->fetch(\PDO::FETCH_ASSOC);
if (!$userB) {
    $pdo->prepare("INSERT INTO users (role_id, name, email, password, phone, is_active) VALUES (5, 'Kurir Budi Test', 'budi_kurir@example.com', 'hash', '08999999999', 1)")->execute();
    $userBId = (int)$pdo->lastInsertId();
    $userB = ['id' => $userBId, 'name' => 'Kurir Budi Test', 'email' => 'budi_kurir@example.com'];
}
assertTest(!str_contains($sysPromptA, $userB['name']) && !str_contains($sysPromptA, $userB['email']), "User A system prompt does NOT contain User B data");

$otherUserQueries = [
    "siapa user lain",
    "siapa dia",
    "data si X",
    "data orang lain",
    "profil akun orang lain"
];
foreach ($otherUserQueries as $oQuery) {
    $otherUserRes = $idTestService->sendMessage($testUserId, $oQuery);
    assertTest(
        str_contains(mb_strtolower($otherUserRes['reply']), "privasi") || str_contains(mb_strtolower($otherUserRes['reply']), "keamanan"),
        "AI refuses request about other user ('{$oQuery}')"
    );
}

// (d) Variasi pertanyaan identitas (aku, saya, gue, gw, -ku) & pengecekan nama user
$identityVariations = [
    "aku siapa"            => "kamu",
    "siapa aku"            => "kamu",
    "aku ini siapa"        => "kamu",
    "saya ini siapa"       => "Anda",
    "gue siapa"            => "kamu",
    "siapa gue"            => "kamu",
    "nama aku siapa"       => "kamu",
    "kamu tau aku siapa"   => "kamu",
    "data akunku apa"      => "kamu",
    "akunku apa"           => "kamu",
    "role aku apa"         => "kamu",
    "aku login sebagai apa"=> "kamu",
    "saya siapa"           => "Anda",
    "nama saya siapa"      => "Anda",
    "akun saya apa"        => "Anda"
];

foreach ($identityVariations as $queryText => $expectedPronoun) {
    $res = $idTestService->sendMessage($testUserId, $queryText);
    $reply = $res['reply'] ?? '';
    assertTest(
        $res['success'] === true && str_contains($reply, $testUserName),
        "Query '{$queryText}' returns user name '{$testUserName}'"
    );
    if ($expectedPronoun === 'kamu') {
        assertTest(
            str_contains($reply, "Kamu terdaftar") || str_contains($reply, "kamu"),
            "Query '{$queryText}' uses casual tone ('kamu')"
        );
    } else {
        assertTest(
            str_contains($reply, "Anda terdaftar") || str_contains($reply, "Anda"),
            "Query '{$queryText}' uses formal tone ('Anda')"
        );
    }
}

// Test Store Owner Profile (includes store name)
$stmtOwner = $pdo->query("SELECT u.id, u.name, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'owner_toko' LIMIT 1");
$ownerRow = $stmtOwner->fetch(\PDO::FETCH_ASSOC);
if ($ownerRow) {
    $ownerPrompt = $idTestService->buildSystemPrompt((int)$ownerRow['id']);
    assertTest(str_contains($ownerPrompt, "Owner Toko"), "Store owner prompt identifies role as 'Owner Toko'");
    $ownerWhoami = $idTestService->sendMessage((int)$ownerRow['id'], "saya siapa");
    assertTest(str_contains($ownerWhoami['reply'], $ownerRow['name']), "Store owner asking 'saya siapa' receives reply with their name");
}


// Identity question tests
$pdo->prepare("DELETE FROM ai_chat_messages WHERE user_id = :uid")->execute(['uid' => $testUserId]);
        // Test assistant identity queries
        $identityQueries = [
            'anda siapa',
            'kamu siapa',
            'siapa anda',
            'siapa kamu',
            'namamu siapa',
            'nama kamu siapa'
        ];
        foreach ($identityQueries as $q) {
            $res = $service->sendMessage($testUserId, $q);
            assertTest($res['success'] === true && str_contains($res['reply'], 'Asisten LaundryKu'), "Assistant identity query '$q' returns Asisten LaundryKu");
        }
        // Ensure user identity queries still return user name
        $userIdentityQueries = [
            'saya siapa',
            'aku siapa'
        ];
        foreach ($userIdentityQueries as $q) {
            $res = $service->sendMessage($testUserId, $q);
            assertTest($res['success'] === true && str_contains($res['reply'], $testUserName), "User identity query '$q' returns user name '{$testUserName}'");
        }

// ==========================================
// 8. Integration: Operational Data Staff Context & AiDataProvider Guardrails
// ==========================================
echo "\n--- 8. Operational Data Staff Context & AiDataProvider Guardrails ---\n";

$stmtAdminToko = $pdo->query("SELECT u.id, u.name, u.email, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = 'admin.toko@laundry.test' LIMIT 1");
$adminTokoUser = $stmtAdminToko->fetch(\PDO::FETCH_ASSOC);

$stmtOwner = $pdo->query("SELECT u.id, u.name, u.email, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('owner', 'admin_owner') LIMIT 1");
$ownerTestUser = $stmtOwner->fetch(\PDO::FETCH_ASSOC);

$stmtCustomer = $pdo->query("SELECT u.id, u.name, u.email, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer' LIMIT 1");
$custTestUser = $stmtCustomer->fetch(\PDO::FETCH_ASSOC);

$stmtKurir = $pdo->query("SELECT u.id, u.name, u.email, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'kurir' LIMIT 1");
$kurirTestUser = $stmtKurir->fetch(\PDO::FETCH_ASSOC);

if ($adminTokoUser && $ownerTestUser && $custTestUser && $kurirTestUser) {
    $opTestService = new AiAssistantService(new MockAiClient());

    // 8.1 System prompt untuk admin_toko memuat blok dengan angka toko 1 sesuai database dan tidak memuat angka platform
    Auth::login(['id' => (int)$adminTokoUser['id'], 'name' => $adminTokoUser['name'], 'email' => $adminTokoUser['email'], 'role_name' => $adminTokoUser['role'], 'is_active' => true]);
    $adminTokoPrompt = $opTestService->buildSystemPrompt((int)$adminTokoUser['id']);
    
    assertTest(str_contains($adminTokoPrompt, '[DATA_OPERASIONAL_START]'), "System prompt admin_toko memuat blok DATA_OPERASIONAL_START");
    assertTest(str_contains($adminTokoPrompt, 'CleanMaster Laundry Express') || str_contains($adminTokoPrompt, 'Toko:'), "System prompt admin_toko memuat nama toko");
    assertTest(str_contains($adminTokoPrompt, 'Khusus Toko:'), "System prompt admin_toko memuat bagian Khusus Toko");
    assertTest(!str_contains($adminTokoPrompt, 'Khusus Platform:'), "System prompt admin_toko TIDAK memuat bagian Khusus Platform");
    assertTest(!str_contains($adminTokoPrompt, '3 Toko Omset Tertinggi'), "System prompt admin_toko TIDAK memuat angka platform top 3 toko");

    // 8.2 System prompt untuk owner memuat blok cakupan platform
    Auth::login(['id' => (int)$ownerTestUser['id'], 'name' => $ownerTestUser['name'], 'email' => $ownerTestUser['email'], 'role_name' => $ownerTestUser['role'], 'is_active' => true]);
    $ownerPrompt = $opTestService->buildSystemPrompt((int)$ownerTestUser['id']);
    
    assertTest(str_contains($ownerPrompt, '[DATA_OPERASIONAL_START]'), "System prompt owner memuat blok DATA_OPERASIONAL_START");
    assertTest(str_contains($ownerPrompt, 'Cakupan Data: Seluruh Platform'), "System prompt owner memuat cakupan Seluruh Platform");
    assertTest(str_contains($ownerPrompt, 'Khusus Platform:'), "System prompt owner memuat bagian Khusus Platform");
    assertTest(!str_contains($ownerPrompt, 'Khusus Toko:'), "System prompt owner TIDAK memuat bagian Khusus Toko");

    // 8.3 System prompt untuk customer dan kurir tidak memuat blok
    Auth::login(['id' => (int)$custTestUser['id'], 'name' => $custTestUser['name'], 'email' => $custTestUser['email'], 'role_name' => $custTestUser['role'], 'is_active' => true]);
    $custPrompt = $opTestService->buildSystemPrompt((int)$custTestUser['id']);
    assertTest(!str_contains($custPrompt, '[DATA_OPERASIONAL_START]'), "System prompt customer TIDAK memuat blok DATA_OPERASIONAL");

    Auth::login(['id' => (int)$kurirTestUser['id'], 'name' => $kurirTestUser['name'], 'email' => $kurirTestUser['email'], 'role_name' => $kurirTestUser['role'], 'is_active' => true]);
    $kurirPrompt = $opTestService->buildSystemPrompt((int)$kurirTestUser['id']);
    assertTest(!str_contains($kurirPrompt, '[DATA_OPERASIONAL_START]'), "System prompt kurir TIDAK memuat blok DATA_OPERASIONAL");

    // 8.4 Blok tidak memuat email, nomor telepon, hash password, google_id, atau token
    preg_match('/\[DATA_OPERASIONAL_START\](.*?)\[DATA_OPERASIONAL_END\]/s', $ownerPrompt, $opOwnerMatches);
    $ownerOpBlock = $opOwnerMatches[1] ?? '';
    assertTest(!str_contains($ownerOpBlock, '@laundry.test') && !str_contains($ownerOpBlock, '0812'), "Blok data operasional owner bebas dari email dan nomor telepon");
    assertTest(!str_contains($ownerOpBlock, '$2y$') && !str_contains($ownerOpBlock, 'password') && !str_contains($ownerOpBlock, 'token'), "Blok data operasional owner bebas dari password hash/token");

    preg_match('/\[DATA_OPERASIONAL_START\](.*?)\[DATA_OPERASIONAL_END\]/s', $adminTokoPrompt, $opTokoMatches);
    $tokoOpBlock = $opTokoMatches[1] ?? '';
    assertTest(!str_contains($tokoOpBlock, '@laundry.test') && !str_contains($tokoOpBlock, '0812'), "Blok data operasional toko bebas dari email dan nomor telepon");
    assertTest(!str_contains($tokoOpBlock, '$2y$') && !str_contains($tokoOpBlock, 'password') && !str_contains($tokoOpBlock, 'token'), "Blok data operasional toko bebas dari password hash/token");

    // 8.5 Dengan MockAiClient:
    $dbDirectTokoOmset = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE store_id = 1 AND payment_status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn();
    $dbDirectOwnerOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    $expectedTokoOmsetFmt = 'Rp' . number_format($dbDirectTokoOmset, 0, ',', '.');
    $expectedTokoOmsetFmtSpaced = 'Rp ' . number_format($dbDirectTokoOmset, 0, ',', '.');

    // 'berapa omset hari ini' sebagai admin_toko menjawab angka yang sama dengan database
    Auth::login(['id' => (int)$adminTokoUser['id'], 'name' => $adminTokoUser['name'], 'email' => $adminTokoUser['email'], 'role_name' => $adminTokoUser['role'], 'is_active' => true]);
    $resToko = $opTestService->sendMessage((int)$adminTokoUser['id'], "berapa omset hari ini");
    assertTest($resToko['success'] === true, "Query omset hari ini oleh admin_toko sukses");
    assertTest(str_contains($resToko['reply'], $expectedTokoOmsetFmt) || str_contains($resToko['reply'], $expectedTokoOmsetFmtSpaced), "Jawaban omset admin_toko memuat angka omset database toko ({$expectedTokoOmsetFmt}): " . $resToko['reply']);

    // 'Berapa pesanan hari ini' sebagai owner sama dengan database
    Auth::login(['id' => (int)$ownerTestUser['id'], 'name' => $ownerTestUser['name'], 'email' => $ownerTestUser['email'], 'role_name' => $ownerTestUser['role'], 'is_active' => true]);
    $resOwner = $opTestService->sendMessage((int)$ownerTestUser['id'], "berapa pesanan hari ini");
    assertTest($resOwner['success'] === true, "Query pesanan hari ini oleh owner sukses");
    assertTest(str_contains($resOwner['reply'], (string)$dbDirectOwnerOrders) && str_contains($resOwner['reply'], "pesanan"), "Jawaban pesanan owner memuat angka pesanan database ({$dbDirectOwnerOrders}): " . $resOwner['reply']);

    // Sebagai customer, ditolak
    Auth::login(['id' => (int)$custTestUser['id'], 'name' => $custTestUser['name'], 'email' => $custTestUser['email'], 'role_name' => $custTestUser['role'], 'is_active' => true]);
    $resCust = $opTestService->sendMessage((int)$custTestUser['id'], "berapa omset hari ini");
    assertTest($resCust['success'] === true, "Query customer dieksekusi dengan aman");
    assertTest(str_contains(mb_strtolower($resCust['reply']), 'tidak memiliki akses'), "Customer meminta omset ditolak karena tidak memiliki akses: " . $resCust['reply']);
}

// Cleanup
$pdo->prepare("DELETE FROM ai_chat_messages WHERE user_id = :uid")->execute(['uid' => $testUserId]);

echo "\n==============================================================================\n";
echo "TOTAL RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==============================================================================\n\n";

exit($failCount > 0 ? 1 : 0);
