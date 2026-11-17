<?php

/**
 * Test Suite: Dashboard Card Numbers Alignment with AI Assistant
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
date_default_timezone_set(\config('app.timezone', 'Asia/Jakarta'));

use App\Core\Database;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiDataProvider;

$db = Database::getInstance();
$maxMsgIdBefore = (int) $db->query("SELECT COALESCE(MAX(id), 0) FROM ai_chat_messages")->fetchColumn();
$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $message, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
        if ($details) {
            echo "         Detail: {$details}\n";
        }
    }
}

echo "==============================================================================\n";
echo "   TEST SUITE: DASHBOARD CARDS & AI ASSISTANT NUMBERS ALIGNMENT               \n";
echo "==============================================================================\n\n";

// --- 1. Audit Card Numbers vs Context Data Provider ---
echo "--- [1] Role: Admin Toko (User ID 4, Store ID 1) ---\n";
$orderModel = new Order();
$adminCards = $orderModel->getAdminTokoCardStats(1);
$dataProvider = new AiDataProvider($db);
$ctxAdmin = $dataProvider->getStaffContext(4, 'admin_toko');

assertCheck(isset($ctxAdmin['kartu_dashboard']['admin_toko']), "Context contains 'kartu_dashboard.admin_toko'");
assertCheck($ctxAdmin['kartu_dashboard']['admin_toko']['need_process'] === $adminCards['need_process'], 
    "Perlu Diproses matches: {$adminCards['need_process']} Order", 
    "Expected {$adminCards['need_process']}, got " . ($ctxAdmin['kartu_dashboard']['admin_toko']['need_process'] ?? 'null'));

assertCheck($ctxAdmin['kartu_dashboard']['admin_toko']['ready_total'] === $adminCards['ready_total'], 
    "Siap Diambil/Diantar matches: {$adminCards['ready_total']} Paket", 
    "Expected {$adminCards['ready_total']}, got " . ($ctxAdmin['kartu_dashboard']['admin_toko']['ready_total'] ?? 'null'));

assertCheck($ctxAdmin['kartu_dashboard']['admin_toko']['delivered_count'] === $adminCards['delivered_count'], 
    "Selesai Terkirim matches: {$adminCards['delivered_count']} Order", 
    "Expected {$adminCards['delivered_count']}, got " . ($ctxAdmin['kartu_dashboard']['admin_toko']['delivered_count'] ?? 'null'));

assertCheck((float)$ctxAdmin['kartu_dashboard']['admin_toko']['omset_toko'] === (float)$adminCards['omset_toko'], 
    "OMSET KASIR TERBAYAR matches: Rp " . number_format($adminCards['omset_toko'], 0, ',', '.'), 
    "Expected {$adminCards['omset_toko']}, got " . ($ctxAdmin['kartu_dashboard']['admin_toko']['omset_toko'] ?? 'null'));

assertCheck($ctxAdmin['pesanan_aktif_sekarang'] === ($adminCards['need_process'] + $adminCards['ready_total']), 
    "Pesanan Aktif Saat Ini strictly distinguished: {$ctxAdmin['pesanan_aktif_sekarang']} = 21 (perlu diproses) + 1 (siap diantar)", 
    "Expected 22, got {$ctxAdmin['pesanan_aktif_sekarang']}");


echo "\n--- [2] Role: Owner Toko (User ID 10, Store ID 1) ---\n";
$ownerTokoCards = $orderModel->getOwnerTokoCardStats(1);
$ctxOwnerToko = $dataProvider->getStaffContext(10, 'owner_toko');

assertCheck(isset($ctxOwnerToko['kartu_dashboard']['owner_toko']), "Context contains 'kartu_dashboard.owner_toko'");
assertCheck((float)$ctxOwnerToko['kartu_dashboard']['owner_toko']['omset_toko'] === (float)$ownerTokoCards['omset_toko'], 
    "Omset Toko Terbayar matches: Rp " . number_format($ownerTokoCards['omset_toko'], 0, ',', '.'));
assertCheck($ctxOwnerToko['kartu_dashboard']['owner_toko']['total_orders'] === $ownerTokoCards['total_orders'], 
    "Total Pesanan Toko matches: {$ownerTokoCards['total_orders']} Order");


echo "\n--- [3] Role: Platform Owner & Admin Owner ---\n";
$platformCards = $orderModel->getPlatformCardStats();
$ctxOwner = $dataProvider->getStaffContext(1, 'owner');
$ctxAdminOwner = $dataProvider->getStaffContext(2, 'admin_owner');

assertCheck(isset($ctxOwner['kartu_dashboard']['platform']), "Context Owner contains 'kartu_dashboard.platform'");
assertCheck((float)$ctxOwner['kartu_dashboard']['platform']['total_omset'] === (float)$platformCards['total_omset'], 
    "Total Omset Terbayar matches: Rp " . number_format($platformCards['total_omset'], 0, ',', '.'));
assertCheck($ctxOwner['kartu_dashboard']['platform']['total_orders'] === $platformCards['total_orders'], 
    "Total Orders Platform matches: {$platformCards['total_orders']} Order");

assertCheck(isset($ctxAdminOwner['kartu_dashboard']['platform']), "Context Admin Owner contains 'kartu_dashboard.platform'");
assertCheck((float)$ctxAdminOwner['kartu_dashboard']['platform']['total_omset'] === (float)$platformCards['total_omset'], 
    "Total Omset Platform matches: Rp " . number_format($platformCards['total_omset'], 0, ',', '.'));


echo "\n--- [4] AI Natural Language Inquiries via MockAiClient ---\n";
$mockClient = new \App\Services\Ai\MockAiClient();
$aiService = new AiAssistantService($mockClient);

// Query A: "ada berapa pesanan yang perlu di proses"
$resA = $aiService->sendMessage(4, "ada berapa pesanan yang perlu di proses");
assertCheck($resA['success'] === true, "Query A executed successfully");
assertCheck(str_contains($resA['reply'], (string)$adminCards['need_process']), 
    "Query A answers with exact card 'Pesanan Perlu Diproses': {$adminCards['need_process']} order", 
    "Reply was: {$resA['reply']}");
assertCheck(!str_contains($resA['reply'], "22 pesanan"), 
    "Query A does NOT substitute with 22 pesanan (Pesanan Aktif Saat Ini)");

// Query B: "berapa pesanan yang siap diantar?"
$resB = $aiService->sendMessage(4, "berapa pesanan yang siap diantar?");
assertCheck($resB['success'] === true, "Query B executed successfully");
assertCheck((stripos($resB['reply'], "Siap Diantar") !== false || stripos($resB['reply'], "Siap Diambil") !== false) && str_contains($resB['reply'], (string)$adminCards['ready_total']), 
    "Query B answers with exact card 'Siap Diambil / Diantar': {$adminCards['ready_total']} paket", 
    "Reply was: {$resB['reply']}");

// Query C: "berapa pesanan yang sudah selesai terkirim?"
$resC = $aiService->sendMessage(4, "berapa pesanan yang sudah selesai terkirim?");
assertCheck($resC['success'] === true, "Query C executed successfully");
assertCheck(str_contains($resC['reply'], "Selesai Terkirim") && str_contains($resC['reply'], (string)$adminCards['delivered_count']), 
    "Query C answers with exact card 'Selesai Terkirim': {$adminCards['delivered_count']} order", 
    "Reply was: {$resC['reply']}");

// Query D: "berapa omset kasir terbayar toko?"
$resD = $aiService->sendMessage(4, "berapa omset kasir terbayar toko?");
assertCheck($resD['success'] === true, "Query D executed successfully");
$formattedOmset = number_format($adminCards['omset_toko'], 0, ',', '.');
assertCheck(stripos($resD['reply'], "Omset Kasir Terbayar") !== false && str_contains($resD['reply'], $formattedOmset), 
    "Query D answers with exact card 'OMSET KASIR TERBAYAR': Rp {$formattedOmset}", 
    "Reply was: {$resD['reply']}");

// Query E: "berapa pesanan aktif saat ini?"
$resE = $aiService->sendMessage(4, "berapa pesanan aktif saat ini?");
assertCheck($resE['success'] === true, "Query E executed successfully");
assertCheck(str_contains($resE['reply'], "Pesanan Aktif Saat Ini") && str_contains($resE['reply'], (string)$ctxAdmin['pesanan_aktif_sekarang']), 
    "Query E correctly answers active orders with 22 pesanan and explicit definition", 
    "Reply was: {$resE['reply']}");


echo "\n--- [5] Non-Regression: Existing AI Guardrails & Role Security ---\n";
// Customer cannot see staff operational cards
$customerRes = $aiService->sendMessage(6, "ada berapa pesanan toko yang perlu di proses");
assertCheck(str_contains(strtolower($customerRes['reply'] ?? ''), "tidak memiliki akses") || str_contains(strtolower($customerRes['reply'] ?? ''), "hanya dapat"), "Customer asking operational card is politely rejected");

// Coding question refusal
$codeRes = $aiService->sendMessage(4, "buatkan script python untuk loop data");
assertCheck(str_contains($codeRes['reply'], "hanya dapat membantu") || str_contains($codeRes['reply'], "LaundryKu") || str_contains(strtolower($codeRes['reply'] ?? ''), "maaf"), 
    "Coding question refusal intact");

// User identity check
$idRes = $aiService->sendMessage(4, "siapa saya?");
assertCheck(str_contains(strtolower($idRes['reply'] ?? ''), "putri hima") || str_contains(strtolower($idRes['reply'] ?? ''), "admin toko"), 
    "User identity query intact");

// Cleanup test chat messages created during this test
$db->prepare("DELETE FROM ai_chat_messages WHERE id > :max_id")->execute(['max_id' => $maxMsgIdBefore]);

echo "\n==============================================================================\n";
echo "SUMMARY: {$passed} PASS, {$failed} FAIL\n";
echo "==============================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
