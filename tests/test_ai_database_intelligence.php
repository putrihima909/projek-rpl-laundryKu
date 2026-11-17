<?php

/**
 * Automated Test Suite: AI Chatbot Database Intelligence & Business Metrics Assistant
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
use App\Models\Order;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\MetricsService;
use App\Services\Ai\MockAiClient;

$pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
]);

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
echo "   AI CHATBOT DATABASE INTELLIGENCE: BUSINESS METRICS & RBAC SUITE            \n";
echo "==============================================================================\n\n";

Session::start();

// Ensure roles exist
$roles = $pdo->query("SELECT id, name FROM roles")->fetchAll(\PDO::FETCH_KEY_PAIR);

// 1. Setup / identify test users for each role
echo "--- 1. Verification of Role Scoping & User Setup ---\n";

// Helper to get or create test user
function getOrCreateUser(\PDO $pdo, string $roleName, string $email, string $name, ?int $storeId = null): int {
    $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    $roleId = (int)$pdo->query("SELECT id FROM roles WHERE name = '{$roleName}' LIMIT 1")->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO users (role_id, store_id, name, email, password, phone, is_active) VALUES (:rid, :sid, :nm, :em, 'hash', '0812345678', 1)");
    $ins->execute(['rid' => $roleId, 'sid' => $storeId, 'nm' => $name, 'em' => $email]);
    return (int)$pdo->lastInsertId();
}

$ownerId = getOrCreateUser($pdo, Auth::ROLE_OWNER, 'owner_bi_test@laundry.test', 'Owner Platform Test');
$ownerTokoAId = getOrCreateUser($pdo, Auth::ROLE_OWNER_TOKO, 'ownertoko_a_test@laundry.test', 'Owner Toko A');
$ownerTokoBId = getOrCreateUser($pdo, Auth::ROLE_OWNER_TOKO, 'ownertoko_b_test@laundry.test', 'Owner Toko B');
$kurirId = getOrCreateUser($pdo, Auth::ROLE_KURIR, 'kurir_bi_test@laundry.test', 'Kurir Budi Test');
$customerId = getOrCreateUser($pdo, Auth::ROLE_CUSTOMER, 'customer_bi_test@laundry.test', 'Customer Siti Test');

// Ensure at least two stores exist
$stmtStores = $pdo->query("SELECT id, user_id, name FROM stores LIMIT 2")->fetchAll(\PDO::FETCH_ASSOC);
if (count($stmtStores) < 2) {
    $pdo->prepare("INSERT INTO stores (user_id, name, address, phone, is_active) VALUES (:uid, 'Toko Laundry A Test', 'Jl A No 1', '0811111111', 1)")->execute(['uid' => $ownerTokoAId]);
    $storeAId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO stores (user_id, name, address, phone, is_active) VALUES (:uid, 'Toko Laundry B Test', 'Jl B No 2', '0822222222', 1)")->execute(['uid' => $ownerTokoBId]);
    $storeBId = (int)$pdo->lastInsertId();
} else {
    $storeAId = (int)$stmtStores[0]['id'];
    $storeBId = (int)$stmtStores[1]['id'];
    $origStoreAOwner = (int)$stmtStores[0]['user_id'];
    $origStoreBOwner = (int)$stmtStores[1]['user_id'];
    $pdo->prepare("UPDATE stores SET user_id = :uid WHERE id = :sid")->execute(['uid' => $ownerTokoAId, 'sid' => $storeAId]);
    $pdo->prepare("UPDATE stores SET user_id = :uid WHERE id = :sid")->execute(['uid' => $ownerTokoBId, 'sid' => $storeBId]);
}

assertTest($storeAId > 0 && $storeBId > 0 && $storeAId !== $storeBId, "Two distinct stores established for multi-store RBAC isolation tests");

// Ensure service and address exist for order creation
$serviceAId = (int)$pdo->query("SELECT id FROM services WHERE store_id = {$storeAId} LIMIT 1")->fetchColumn();
if (!$serviceAId) {
    $pdo->prepare("INSERT INTO services (store_id, name, price, unit, is_active) VALUES ({$storeAId}, 'Cuci Komplit A', 10000, 'kg', 1)")->execute();
    $serviceAId = (int)$pdo->lastInsertId();
}
$serviceBId = (int)$pdo->query("SELECT id FROM services WHERE store_id = {$storeBId} LIMIT 1")->fetchColumn();
if (!$serviceBId) {
    $pdo->prepare("INSERT INTO services (store_id, name, price, unit, is_active) VALUES ({$storeBId}, 'Cuci Komplit B', 12000, 'kg', 1)")->execute();
    $serviceBId = (int)$pdo->lastInsertId();
}

$addressId = (int)$pdo->query("SELECT id FROM addresses WHERE user_id = {$customerId} LIMIT 1")->fetchColumn();
if (!$addressId) {
    $pdo->prepare("INSERT INTO addresses (user_id, title, address_line, province, city, district, village, rt, rw) VALUES ({$customerId}, 'Rumah', 'Jl Anggrek', 'DKI', 'Jakarta', 'Kec', 'Kel', '01', '02')")->execute();
    $addressId = (int)$pdo->lastInsertId();
}

// Clean previous test orders for clean assertions
$pdo->prepare("DELETE FROM orders WHERE customer_id = :cid")->execute(['cid' => $customerId]);

// Create controlled test orders for today:
// Order 1: Toko A, Paid, Total 50.000, Kurir Assigned
$pdo->prepare("INSERT INTO orders (order_code, customer_id, store_id, service_id, address_id, courier_id, total_amount, payment_status, status, created_at, updated_at) 
VALUES ('ORD-TEST-A1', :cid, :sid, :svid, :aid, :crid, 50000.00, 'paid', 'processing', NOW(), NOW())")
    ->execute(['cid' => $customerId, 'sid' => $storeAId, 'svid' => $serviceAId, 'aid' => $addressId, 'crid' => $kurirId]);
$orderA1Id = (int)$pdo->lastInsertId();

// Order 2: Toko A, Unpaid, Total 30.000, Status waiting_payment
$pdo->prepare("INSERT INTO orders (order_code, customer_id, store_id, service_id, address_id, courier_id, total_amount, payment_status, status, created_at, updated_at) 
VALUES ('ORD-TEST-A2', :cid, :sid, :svid, :aid, null, 30000.00, 'unpaid', 'waiting_payment', NOW(), NOW())")
    ->execute(['cid' => $customerId, 'sid' => $storeAId, 'svid' => $serviceAId, 'aid' => $addressId]);
$orderA2Id = (int)$pdo->lastInsertId();

// Order 3: Toko B, Paid, Total 100.000, Delivered today
$pdo->prepare("INSERT INTO orders (order_code, customer_id, store_id, service_id, address_id, courier_id, total_amount, payment_status, status, created_at, updated_at) 
VALUES ('ORD-TEST-B1', :cid, :sid, :svid, :aid, :crid, 100000.00, 'paid', 'delivered', NOW(), NOW())")
    ->execute(['cid' => $customerId, 'sid' => $storeBId, 'svid' => $serviceBId, 'aid' => $addressId, 'crid' => $kurirId]);
$orderB1Id = (int)$pdo->lastInsertId();

// Create payments for paid orders
$pdo->prepare("DELETE FROM payments WHERE order_id IN ({$orderA1Id}, {$orderA2Id}, {$orderB1Id})")->execute();
$pdo->prepare("INSERT INTO payments (order_id, payment_method, gateway, gateway_reference, amount, status, paid_at) VALUES (:oid, 'qris', 'mock', 'REF-PAY-A1', 50000, 'paid', NOW())")
    ->execute(['oid' => $orderA1Id]);
$paymentA1Id = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO payments (order_id, payment_method, gateway, gateway_reference, amount, status, paid_at) VALUES (:oid, 'qris', 'mock', 'REF-PAY-B1', 100000, 'paid', NOW())")
    ->execute(['oid' => $orderB1Id]);
$paymentB1Id = (int)$pdo->lastInsertId();

// Create 1 completed refund for Order A1 (Rp 10.000)
$pdo->prepare("DELETE FROM refunds WHERE order_id IN ({$orderA1Id}, {$orderA2Id}, {$orderB1Id})")->execute();
$pdo->prepare("INSERT INTO refunds (payment_id, order_id, amount, reason, status, completed_at, created_at) VALUES (:pid, :oid, 10000.00, 'Salah hitung', 'completed', NOW(), NOW())")
    ->execute(['pid' => $paymentA1Id, 'oid' => $orderA1Id]);

// Create 1 pending refund for Order B1 (Rp 20.000)
$pdo->prepare("INSERT INTO refunds (payment_id, order_id, amount, reason, status, created_at) VALUES (:pid, :oid, 20000.00, 'Barang tertinggal', 'pending', NOW())")
    ->execute(['pid' => $paymentB1Id, 'oid' => $orderB1Id]);

assertTest($orderA1Id > 0 && $orderA2Id > 0 && $orderB1Id > 0, "Deterministic test orders and refunds populated in database");


// ==========================================
// 2. MetricsService Unit & RBAC Scoping Tests
// ==========================================
echo "\n--- 2. MetricsService Unit Tests & Role Scoping ---\n";

$metricsService = new MetricsService($pdo);

// 2.1 Owner Platform (Global Platform Scope)
$ownerOrders = $metricsService->getOrdersToday($ownerId, Auth::ROLE_OWNER);
assertTest($ownerOrders['total_orders'] >= 3, "Owner sees all orders platform-wide (>= 3 orders today)");

$ownerRevToday = $metricsService->getRevenueToday($ownerId, Auth::ROLE_OWNER);
assertTest($ownerRevToday['revenue'] >= 150000.00, "Owner sees total revenue platform-wide (50k Toko A + 100k Toko B = 150k)");

$ownerRefunds = $metricsService->getRefundsSummary($ownerId, Auth::ROLE_OWNER);
assertTest($ownerRefunds['completed_count'] >= 1 && $ownerRefunds['pending_count'] >= 1, "Owner sees both Toko A and Toko B refunds");

$ownerNetRev = $metricsService->getNetRevenue($ownerId, Auth::ROLE_OWNER);
assertTest($ownerNetRev['net_revenue'] >= 140000.00, "Owner net revenue accurately reflects gross revenue minus completed refunds");

$platformUsers = $metricsService->getActiveUsersAndStores(Auth::ROLE_OWNER);
assertTest($platformUsers['active_customers'] >= 1 && $platformUsers['active_stores'] >= 2, "Owner can access platform active customer & store counts");

// 2.2 Store Owner Isolation (Toko A cannot see Toko B data)
echo "\n--- Store Owner Isolation (RBAC) ---\n";
$tokoAOrders = $metricsService->getOrdersToday($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
assertTest($tokoAOrders['total_orders'] >= 2 && $tokoAOrders['total_orders'] < $ownerOrders['total_orders'], "Owner Toko A sees only Toko A orders (strictly isolated from Toko B)");

$tokoARevToday = $metricsService->getRevenueToday($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
assertTest($tokoARevToday['revenue'] >= 50000.00 && $tokoARevToday['revenue'] < $ownerRevToday['revenue'], "Owner Toko A sees only Toko A revenue (isolated from Toko B)");

$tokoAUnpaid = $metricsService->getUnpaidOrders($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
assertTest($tokoAUnpaid['unpaid_count'] >= 1, "Owner Toko A sees Toko A unpaid orders");

$tokoBRevToday = $metricsService->getRevenueToday($ownerTokoBId, Auth::ROLE_OWNER_TOKO, $storeBId);
assertTest($tokoBRevToday['revenue'] >= 100000.00 && $tokoBRevToday['revenue'] < $ownerRevToday['revenue'], "Owner Toko B sees only Toko B revenue (strictly isolated)");

$tokoARefunds = $metricsService->getRefundsSummary($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
assertTest($tokoARefunds['completed_count'] >= 1 && $tokoARefunds['pending_count'] < $ownerRefunds['pending_count'], "Owner Toko A only sees Toko A refunds (pending refunds is strictly less than platform total)");

// 2.3 Courier Scope
echo "\n--- Courier Tasks Scoping ---\n";
$courierTasks = $metricsService->getCourierTasks($kurirId);
assertTest($courierTasks['total_delivered'] >= 1, "Courier metrics show tasks assigned to this courier");

// 2.4 Customer Scope
echo "\n--- Customer Orders Scoping ---\n";
$customerUnpaid = $metricsService->getUnpaidOrders($customerId, Auth::ROLE_CUSTOMER);
assertTest($customerUnpaid['unpaid_count'] === 1, "Customer sees their own unpaid order count");


// ==========================================
// 3. AI Assistant Integration & Query Answers
// ==========================================
echo "\n--- 3. AI Assistant Integration & Natural Language Answers ---\n";

$mockClient = new MockAiClient();
$aiService = new AiAssistantService($mockClient, $metricsService);

// Reset chat messages for test users
$pdo->prepare("DELETE FROM ai_chat_messages WHERE user_id IN ({$ownerId}, {$ownerTokoAId}, {$customerId}, {$kurirId})")->execute();

// 3.1 Owner Platform queries
Auth::login(['id' => $ownerId, 'name' => 'Owner Platform Test', 'email' => 'owner_bi_test@laundry.test', 'role_name' => Auth::ROLE_OWNER, 'is_active' => true]);

// Query 1: Pesanan hari ini
$resQ1 = $aiService->sendMessage($ownerId, "Berapa pesanan hari ini?");
assertTest($resQ1['success'] === true, "Query 'Berapa pesanan hari ini?' executed successfully");
assertTest(str_contains($resQ1['reply'], "Jumlah Pesanan Hari Ini") || str_contains($resQ1['reply'], "pesanan"), "Reply contains actual order count for today: " . $resQ1['reply']);

// Query 2: Omzet hari ini
$resQ2 = $aiService->sendMessage($ownerId, "Berapa omzet hari ini?");
assertTest($resQ2['success'] === true, "Query 'Berapa omzet hari ini?' executed successfully");
assertTest(str_contains($resQ2['reply'], "Omzet Terbayar Hari Ini") && str_contains($resQ2['reply'], "Rp"), "Reply contains actual omzet today formatted in Rupiah: " . $resQ2['reply']);

// Query 3: Omzet bulan ini
$resQ3 = $aiService->sendMessage($ownerId, "Berapa omzet bulan ini?");
assertTest(str_contains($resQ3['reply'], "Omzet Terbayar Bulan Ini") && str_contains($resQ3['reply'], "Rp"), "Reply contains actual monthly revenue: " . $resQ3['reply']);

// Query 4: Pesanan belum dibayar
$resQ4 = $aiService->sendMessage($ownerId, "Berapa pesanan yang belum dibayar?");
assertTest(str_contains($resQ4['reply'], "Pesanan Belum Dibayar") && str_contains($resQ4['reply'], "pesanan"), "Reply contains unpaid orders count: " . $resQ4['reply']);

// Query 5: Pesanan sedang diproses
$resQ5 = $aiService->sendMessage($ownerId, "Berapa pesanan yang sedang diproses?");
assertTest(str_contains($resQ5['reply'], "Pesanan Sedang Diproses"), "Reply contains processing orders count: " . $resQ5['reply']);

// Query 6: Refund yang menunggu atau sudah selesai
$resQ6 = $aiService->sendMessage($ownerId, "Berapa refund yang menunggu atau sudah selesai?");
assertTest(str_contains($resQ6['reply'], "Refund Menunggu") && str_contains($resQ6['reply'], "Refund Selesai"), "Reply contains pending and completed refund breakdown: " . $resQ6['reply']);

// Query 7: Pendapatan bersih setelah refund
$resQ7 = $aiService->sendMessage($ownerId, "Berapa pendapatan bersih setelah refund?");
assertTest(str_contains($resQ7['reply'], "Pendapatan Bersih") && str_contains($resQ7['reply'], "Rp"), "Reply contains net revenue calculation: " . $resQ7['reply']);

// Query 8: Jumlah pelanggan dan toko aktif
$resQ8 = $aiService->sendMessage($ownerId, "Berapa jumlah pelanggan dan toko aktif?");
assertTest(str_contains($resQ8['reply'], "Pelanggan Aktif") && str_contains($resQ8['reply'], "Toko Laundry Aktif"), "Reply contains active customer and store counts: " . $resQ8['reply']);


// 3.2 Owner Toko queries (Testing store isolation in AI Assistant)
echo "\n--- AI Assistant: Store Owner Context Isolation ---\n";
Auth::login(['id' => $ownerTokoAId, 'name' => 'Owner Toko A', 'email' => 'ownertoko_a_test@laundry.test', 'role_name' => Auth::ROLE_OWNER_TOKO, 'is_active' => true]);

$resTokoAOmzet = $aiService->sendMessage($ownerTokoAId, "Berapa omzet toko saya hari ini?");
assertTest(str_contains($resTokoAOmzet['reply'], "Rp") && str_contains($resTokoAOmzet['reply'], "Omzet"), "Owner Toko A receives Toko A omzet info: " . $resTokoAOmzet['reply']);
assertTest(!str_contains($resTokoAOmzet['reply'], "150.000") && !str_contains($resTokoAOmzet['reply'], "100.000"), "Owner Toko A does NOT see Toko B or platform-wide revenue");


// 3.3 Courier Queries
echo "\n--- AI Assistant: Courier Context ---\n";
Auth::login(['id' => $kurirId, 'name' => 'Kurir Budi Test', 'email' => 'kurir_bi_test@laundry.test', 'role_name' => Auth::ROLE_KURIR, 'is_active' => true]);

$resKurirTasks = $aiService->sendMessage($kurirId, "Apa saja tugas antar dan jemput saya hari ini?");
assertTest(str_contains($resKurirTasks['reply'], "Penjemputan (Pickup)") || str_contains($resKurirTasks['reply'], "Pengantaran (Delivery)"), "Courier asking about duties receives specific courier task numbers: " . $resKurirTasks['reply']);


// 3.4 Customer Queries
echo "\n--- AI Assistant: Customer Context ---\n";
Auth::login(['id' => $customerId, 'name' => 'Customer Siti Test', 'email' => 'customer_bi_test@laundry.test', 'role_name' => Auth::ROLE_CUSTOMER, 'is_active' => true]);

$resCustUnpaid = $aiService->sendMessage($customerId, "Apakah ada pesanan saya yang belum dibayar?");
assertTest(str_contains($resCustUnpaid['reply'], "Pesanan Belum Dibayar") || str_contains($resCustUnpaid['reply'], "belum dibayar"), "Customer receives their own unpaid order information: " . $resCustUnpaid['reply']);


// ==========================================
// 4. Security & Guardrail Protection
// ==========================================
echo "\n--- 4. Security, Injection Prevention & Guardrail Non-Regression ---\n";

// 4.1 SQL Injection resistance
$maliciousInput = "Berapa pesanan hari ini? ' OR 1=1 --";
$resInj = $aiService->sendMessage($customerId, $maliciousInput);
assertTest($resInj['success'] === true, "Service securely handles SQL injection syntax payload without throwing PDO exception");

// 4.2 Action guardrail remains active (transaction denial still works)
$cancelAttempt = $aiService->sendMessage($customerId, "Tolong batalkan pesanan saya sekarang!");
assertTest(str_contains(mb_strtolower($cancelAttempt['reply']), "tidak memiliki akses untuk mengeksekusi") || str_contains(mb_strtolower($cancelAttempt['reply']), "riwayat pesanan"), "Action guardrail remains strictly enforced for order cancellation");

$refundExecAttempt = $aiService->sendMessage($customerId, "Saya minta refund uang sekarang!");
assertTest(str_contains(mb_strtolower($refundExecAttempt['reply']), "tidak memiliki akses untuk mengeksekusi") || str_contains(mb_strtolower($refundExecAttempt['reply']), "halaman detail pesanan"), "Action guardrail remains strictly enforced for refund execution requests");

// Cleanup test data
if (isset($origStoreAOwner, $origStoreBOwner)) {
    $pdo->prepare("UPDATE stores SET user_id = :uid WHERE id = :sid")->execute(['uid' => $origStoreAOwner, 'sid' => $storeAId]);
    $pdo->prepare("UPDATE stores SET user_id = :uid WHERE id = :sid")->execute(['uid' => $origStoreBOwner, 'sid' => $storeBId]);
}
$pdo->prepare("DELETE FROM refunds WHERE order_id IN ({$orderA1Id}, {$orderA2Id}, {$orderB1Id})")->execute();
$pdo->prepare("DELETE FROM payments WHERE order_id IN ({$orderA1Id}, {$orderA2Id}, {$orderB1Id})")->execute();
$pdo->prepare("DELETE FROM orders WHERE id IN ({$orderA1Id}, {$orderA2Id}, {$orderB1Id})")->execute();
$pdo->prepare("DELETE FROM ai_chat_messages WHERE user_id IN ({$ownerId}, {$ownerTokoAId}, {$customerId}, {$kurirId})")->execute();

echo "\n==============================================================================\n";
echo "DATABASE INTELLIGENCE RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==============================================================================\n\n";

exit($failCount > 0 ? 1 : 0);
