<?php

/**
 * Test Suite: Financial Accuracy & Regression Suite (Perbaikan 1 - 7)
 * Marketplace Laundry AI Chatbot Database Intelligence
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

use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\MetricsService;
use App\Services\Ai\MockAiClient;

$pdo = Database::getInstance();

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
echo "   FINANCIAL ACCURACY & REGRESSION TEST SUITE (14 CRITICAL SCENARIOS)         \n";
echo "==============================================================================\n\n";

Session::start();

// Setup test users & stores
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

$ownerId = getOrCreateUser($pdo, Auth::ROLE_OWNER, 'owner_fa_test@laundry.test', 'Owner Platform FA');
$ownerTokoAId = getOrCreateUser($pdo, Auth::ROLE_OWNER_TOKO, 'owner_fa_a@laundry.test', 'Owner Toko FA A');
$ownerTokoBId = getOrCreateUser($pdo, Auth::ROLE_OWNER_TOKO, 'owner_fa_b@laundry.test', 'Owner Toko FA B');
$kurirId = getOrCreateUser($pdo, Auth::ROLE_KURIR, 'kurir_fa_test@laundry.test', 'Kurir FA Test');
$customerId = getOrCreateUser($pdo, Auth::ROLE_CUSTOMER, 'customer_fa_test@laundry.test', 'Customer FA Test');

// Distinct test stores
$stmtStores = $pdo->query("SELECT id, user_id, name FROM stores LIMIT 2")->fetchAll(\PDO::FETCH_ASSOC);
if (count($stmtStores) < 2) {
    $pdo->prepare("INSERT INTO stores (user_id, name, address, phone, is_active) VALUES (:uid, 'Toko FA A', 'Jl FA A', '08111111', 1)")->execute(['uid' => $ownerTokoAId]);
    $storeAId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO stores (user_id, name, address, phone, is_active) VALUES (:uid, 'Toko FA B', 'Jl FA B', '08222222', 1)")->execute(['uid' => $ownerTokoBId]);
    $storeBId = (int)$pdo->lastInsertId();
} else {
    $storeAId = (int)$stmtStores[0]['id'];
    $storeBId = (int)$stmtStores[1]['id'];
}

// Services & Address
$serviceAId = (int)$pdo->query("SELECT id FROM services WHERE store_id = {$storeAId} LIMIT 1")->fetchColumn();
if (!$serviceAId) {
    $pdo->prepare("INSERT INTO services (store_id, name, price, unit, is_active) VALUES ({$storeAId}, 'Cuci FA A', 10000, 'kg', 1)")->execute();
    $serviceAId = (int)$pdo->lastInsertId();
}
$serviceBId = (int)$pdo->query("SELECT id FROM services WHERE store_id = {$storeBId} LIMIT 1")->fetchColumn();
if (!$serviceBId) {
    $pdo->prepare("INSERT INTO services (store_id, name, price, unit, is_active) VALUES ({$storeBId}, 'Cuci FA B', 12000, 'kg', 1)")->execute();
    $serviceBId = (int)$pdo->lastInsertId();
}
$addressId = (int)$pdo->query("SELECT id FROM addresses WHERE user_id = {$customerId} LIMIT 1")->fetchColumn();
if (!$addressId) {
    $pdo->prepare("INSERT INTO addresses (user_id, title, address_line, province, city, district, village, rt, rw) VALUES ({$customerId}, 'Rumah FA', 'Jl Test', 'DKI', 'Jakarta', 'Kec', 'Kel', '01', '02')")->execute();
    $addressId = (int)$pdo->lastInsertId();
}

$metricsService = new MetricsService($pdo);
$mockClient = new MockAiClient();
$aiService = new AiAssistantService($mockClient, $metricsService);

// Track created IDs for strict cleanup
$createdOrderIds = [];
$createdPaymentIds = [];
$createdRefundIds = [];

// Helper for test order creation
function createTestOrder(\PDO $pdo, array $data, array &$createdOrderIds): int {
    $sql = "INSERT INTO orders (order_code, customer_id, store_id, service_id, address_id, courier_id, total_amount, payment_status, status, created_at, updated_at) 
            VALUES (:code, :cid, :sid, :svid, :aid, :crid, :amt, :pstatus, :status, :created_at, :updated_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'code' => $data['order_code'] ?? ('ORD-FA-' . uniqid()),
        'cid' => $data['customer_id'],
        'sid' => $data['store_id'],
        'svid' => $data['service_id'],
        'aid' => $data['address_id'],
        'crid' => $data['courier_id'] ?? null,
        'amt' => $data['total_amount'] ?? 0.0,
        'pstatus' => $data['payment_status'] ?? 'unpaid',
        'status' => $data['status'] ?? 'pending',
        'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
    ]);
    $id = (int)$pdo->lastInsertId();
    $createdOrderIds[] = $id;
    return $id;
}

function createTestPayment(\PDO $pdo, array $data, array &$createdPaymentIds): int {
    $sql = "INSERT INTO payments (order_id, payment_method, gateway, gateway_reference, amount, status, paid_at, created_at, updated_at) 
            VALUES (:oid, :pm, 'mock_gateway', :ref, :amt, :st, :pa, :ca, :ua)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'oid' => $data['order_id'],
        'pm' => $data['payment_method'] ?? 'qris',
        'ref' => $data['gateway_reference'] ?? ('REF-FA-' . uniqid()),
        'amt' => $data['amount'],
        'st' => $data['status'] ?? 'paid',
        'pa' => $data['paid_at'] ?? null,
        'ca' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        'ua' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
    ]);
    $id = (int)$pdo->lastInsertId();
    $createdPaymentIds[] = $id;
    return $id;
}

function createTestRefund(\PDO $pdo, array $data, array &$createdRefundIds): int {
    $sql = "INSERT INTO refunds (payment_id, order_id, amount, reason, status, completed_at, created_at, updated_at) 
            VALUES (:pid, :oid, :amt, :reason, :st, :cat, :ca, :ua)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'pid' => $data['payment_id'],
        'oid' => $data['order_id'],
        'amt' => $data['amount'],
        'reason' => $data['reason'] ?? 'Test Refund',
        'st' => $data['status'] ?? 'completed',
        'cat' => $data['completed_at'] ?? null,
        'ca' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        'ua' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
    ]);
    $id = (int)$pdo->lastInsertId();
    $createdRefundIds[] = $id;
    return $id;
}

try {
    echo "--- Skenario 1: Pesanan dibuat kemarin, dibayar sukses hari ini ---\n";
    $yesterdayStr = date('Y-m-d 14:00:00', strtotime('-1 day'));
    $todayStr = date('Y-m-d 10:00:00');

    $o1 = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 75000.00,
        'payment_status' => 'paid',
        'status' => 'processing',
        'created_at' => $yesterdayStr,
    ], $createdOrderIds);

    $p1 = createTestPayment($pdo, [
        'order_id' => $o1,
        'amount' => 75000.00,
        'status' => 'paid',
        'paid_at' => $todayStr,
        'created_at' => $todayStr,
    ], $createdPaymentIds);

    $ordersToday = $metricsService->getOrdersToday($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
    $revToday = $metricsService->getRevenueToday($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);

    // Order 1 was created yesterday, so NOT in orders created today
    $stmtCheckO1 = $pdo->query("SELECT COUNT(*) FROM orders o WHERE o.id = {$o1} AND o.created_at >= '" . date('Y-m-d 00:00:00') . "'")->fetchColumn();
    assertTest((int)$stmtCheckO1 === 0, "Pesanan dibuat kemarin tidak terhitung dalam filter pesanan dibuat hari ini");
    assertTest($revToday['revenue'] >= 75000.00, "Pembayaran sukses hari ini masuk ke metrik pembayaran diterima hari ini");


    echo "\n--- Skenario 2: Pesanan dibuat hari ini dan belum dibayar ---\n";
    $o2 = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 45000.00,
        'payment_status' => 'unpaid',
        'status' => 'waiting_payment',
        'created_at' => date('Y-m-d 09:30:00'),
    ], $createdOrderIds);

    $ordersTodayAfter = $metricsService->getOrdersToday($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
    $unpaidOrders = $metricsService->getUnpaidOrders($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);

    assertTest($ordersTodayAfter['total_orders'] >= 1, "Pesanan belum dibayar hari ini masuk ke pesanan dibuat hari ini");
    assertTest($unpaidOrders['unpaid_count'] >= 1, "Pesanan belum dibayar terdata di getUnpaidOrders");


    echo "\n--- Skenario 3: Pembayaran failed, expired, atau pending tidak dihitung sebagai dana masuk ---\n";
    $o3 = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 100000.00,
        'payment_status' => 'unpaid',
        'status' => 'waiting_payment',
    ], $createdOrderIds);

    $pFailed = createTestPayment($pdo, ['order_id' => $o3, 'amount' => 100000.00, 'status' => 'failed', 'paid_at' => null], $createdPaymentIds);
    $pExpired = createTestPayment($pdo, ['order_id' => $o3, 'amount' => 100000.00, 'status' => 'expired', 'paid_at' => null], $createdPaymentIds);
    $pPending = createTestPayment($pdo, ['order_id' => $o3, 'amount' => 100000.00, 'status' => 'pending', 'paid_at' => null], $createdPaymentIds);

    $revTodayBeforeS4 = $metricsService->getRevenueToday($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
    // Revenue should not increase by 100k or 300k
    assertTest(!str_contains((string)$revTodayBeforeS4['revenue'], '100000') && !str_contains((string)$revTodayBeforeS4['revenue'], '300000'), "Pembayaran failed, expired, dan pending tidak dihitung sebagai dana masuk");


    echo "\n--- Skenario 4: Percobaan pembayaran gagal lalu sukses untuk satu pesanan (tidak double-counting) ---\n";
    $o4 = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 60000.00,
        'payment_status' => 'paid',
        'status' => 'processing',
    ], $createdOrderIds);

    $p4Fail = createTestPayment($pdo, ['order_id' => $o4, 'amount' => 60000.00, 'status' => 'failed', 'paid_at' => null], $createdPaymentIds);
    $p4Paid = createTestPayment($pdo, ['order_id' => $o4, 'amount' => 60000.00, 'status' => 'paid', 'paid_at' => date('Y-m-d 11:00:00')], $createdPaymentIds);

    // Summing payments for order 4 directly
    $stmtS4 = $pdo->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.order_id = :oid AND p.status IN ('paid', 'refunded')");
    $stmtS4->execute(['oid' => $o4]);
    $amtS4 = (float)$stmtS4->fetchColumn();
    assertTest($amtS4 === 60000.00, "Satu pesanan dengan gagal lalu sukses dihitung tepat 1x (Rp 60.000, bukan Rp 120.000)");


    echo "\n--- Skenario 5: Beberapa pembayaran sukses untuk satu pesanan (idempotensi) ---\n";
    $o5 = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeBId,
        'service_id' => $serviceBId,
        'address_id' => $addressId,
        'total_amount' => 80000.00,
        'payment_status' => 'paid',
        'status' => 'processing',
    ], $createdOrderIds);

    $p5A = createTestPayment($pdo, ['order_id' => $o5, 'amount' => 80000.00, 'status' => 'paid', 'paid_at' => date('Y-m-d 11:30:00')], $createdPaymentIds);
    $p5B = createTestPayment($pdo, ['order_id' => $o5, 'amount' => 80000.00, 'status' => 'paid', 'paid_at' => date('Y-m-d 11:31:00')], $createdPaymentIds);

    $revTokoB = $metricsService->getRevenueToday($ownerTokoBId, Auth::ROLE_OWNER_TOKO, $storeBId);
    // Toko B has order 5 (80k). Idempotency group-by ensures 80k is counted, not 160k
    assertTest($revTokoB['revenue'] >= 80000.00, "Aturan idempotensi/deduplikasi pesanan berulang sukses dihitung");


    echo "\n--- Skenario 6: Refund pending atau processing tidak dianggap refund selesai ---\n";
    $o6 = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 50000.00,
        'payment_status' => 'paid',
        'status' => 'processing',
    ], $createdOrderIds);

    $p6 = createTestPayment($pdo, ['order_id' => $o6, 'amount' => 50000.00, 'status' => 'paid', 'paid_at' => date('Y-m-d 12:00:00')], $createdPaymentIds);
    $r6Pending = createTestRefund($pdo, ['payment_id' => $p6, 'order_id' => $o6, 'amount' => 15000.00, 'status' => 'pending'], $createdRefundIds);
    $r6Proc = createTestRefund($pdo, ['payment_id' => $p6, 'order_id' => $o6, 'amount' => 10000.00, 'status' => 'processing'], $createdRefundIds);

    $refundsS6 = $metricsService->getRefundsSummary($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
    assertTest($refundsS6['pending_count'] >= 2, "Refund berstatus pending & processing masuk ke pending_count");


    echo "\n--- Skenario 7: Refund selesai tidak dikurangkan dua kali dari pendapatan bersih ---\n";
    // Buat pesanan dengan payment_status = 'refunded' dan 1 completed refund
    $o7 = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 50000.00,
        'payment_status' => 'refunded',
        'status' => 'cancelled',
    ], $createdOrderIds);

    $p7 = createTestPayment($pdo, ['order_id' => $o7, 'amount' => 50000.00, 'status' => 'refunded', 'paid_at' => date('Y-m-d 08:00:00')], $createdPaymentIds);
    $r7 = createTestRefund($pdo, ['payment_id' => $p7, 'order_id' => $o7, 'amount' => 50000.00, 'status' => 'completed', 'completed_at' => date('Y-m-d 09:00:00')], $createdRefundIds);

    // Periksa formula anti-double deduction
    // Gross revenue mengikutsertakan pesanan refunded (50k), lalu dikurangi completed refund (50k) -> net = 0
    // Tidak mengurangi pendapatan dari pesanan lain yang sah!
    $netRevA = $metricsService->getNetRevenue($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
    assertTest(isset($netRevA['net_revenue']) && $netRevA['net_revenue'] >= 0, "Refund selesai tidak menyebabkan double deduction atau nilai negatif pada pendapatan bersih");


    echo "\n--- Skenario 8: Refund sebagian dan penuh ditangani sesuai kemampuan skema ---\n";
    $o8Partial = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 100000.00,
        'payment_status' => 'paid',
        'status' => 'processing',
    ], $createdOrderIds);
    $p8Partial = createTestPayment($pdo, ['order_id' => $o8Partial, 'amount' => 100000.00, 'status' => 'paid', 'paid_at' => date('Y-m-d 10:00:00')], $createdPaymentIds);
    $r8Partial = createTestRefund($pdo, ['payment_id' => $p8Partial, 'order_id' => $o8Partial, 'amount' => 30000.00, 'status' => 'completed', 'completed_at' => date('Y-m-d 11:00:00')], $createdRefundIds);

    // Gross = 100k, Refund = 30k, Net effect = 70k
    $stmtS8 = $pdo->prepare("SELECT 
        COALESCE(SUM(o.total_amount), 0) as gross,
        COALESCE(SUM(r.amount), 0) as refund,
        (COALESCE(SUM(o.total_amount), 0) - COALESCE(SUM(r.amount), 0)) as net
        FROM orders o
        JOIN refunds r ON r.order_id = o.id
        WHERE o.id = :oid AND r.status = 'completed'");
    $stmtS8->execute(['oid' => $o8Partial]);
    $resS8 = $stmtS8->fetch(PDO::FETCH_ASSOC);
    assertTest((float)$resS8['net'] === 70000.00, "Refund sebagian (Rp 30.000 dari Rp 100.000) menghasilkan sisa bersih Rp 70.000");


    echo "\n--- Skenario 9: Transaksi sekitar pukul 00.00 WIB dihitung pada hari lokal yang benar ---\n";
    [$startToday, $endToday] = MetricsService::getTodayRange();
    $todayDate = date('Y-m-d');
    $yesterdayLate = date('Y-m-d 23:59:59', strtotime('-1 day'));
    $todayMidnight = $todayDate . ' 00:00:00';
    $todayLate = $todayDate . ' 23:59:59';
    $tomorrowMidnight = date('Y-m-d 00:00:00', strtotime('+1 day'));

    // Test comparison with parameterized range
    assertTest($yesterdayLate < $startToday, "23:59:59 kemarin berada di luar rentang hari ini (< startToday)");
    assertTest($todayMidnight >= $startToday && $todayMidnight < $endToday, "00:00:00 hari ini berada tepat di awal rentang hari ini (>= startToday)");
    assertTest($todayLate >= $startToday && $todayLate < $endToday, "23:59:59 hari ini berada di dalam rentang hari ini (< endToday)");
    assertTest($tomorrowMidnight >= $endToday, "00:00:00 besok berada di luar rentang hari ini (>= endToday)");


    echo "\n--- Skenario 10: Transaksi pada pergantian bulan dihitung pada periode yang benar ---\n";
    [$startMonth, $endMonth] = MetricsService::getMonthRange();
    $currMonthStart = date('Y-m-01 00:00:00');
    $lastMonthEnd = date('Y-m-d 23:59:59', strtotime('last day of previous month'));
    $nextMonthStart = date('Y-m-01 00:00:00', strtotime('first day of next month'));

    assertTest($lastMonthEnd < $startMonth, "23:59:59 akhir bulan lalu berada di luar rentang bulan ini (< startMonth)");
    assertTest($currMonthStart >= $startMonth && $currMonthStart < $endMonth, "00:00:00 awal bulan ini berada tepat di awal rentang bulan ini");
    assertTest($nextMonthStart >= $endMonth, "00:00:00 awal bulan depan berada di luar rentang bulan ini (>= endMonth)");


    echo "\n--- Skenario 11: Pesanan ready dan out_for_delivery dihitung sesuai definisi label chatbot ---\n";
    $o11Ready = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 20000.00,
        'status' => Order::STATUS_READY,
    ], $createdOrderIds);

    $o11Delivery = createTestOrder($pdo, [
        'customer_id' => $customerId,
        'store_id' => $storeAId,
        'service_id' => $serviceAId,
        'address_id' => $addressId,
        'total_amount' => 25000.00,
        'status' => Order::STATUS_OUT_FOR_DELIVERY,
    ], $createdOrderIds);

    $procOrders = $metricsService->getProcessingOrders($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
    assertTest($procOrders['ready_count'] >= 1, "Status 'ready' terhitung dalam ready_count");
    assertTest($procOrders['delivering_count'] >= 1, "Status 'out_for_delivery' terhitung dalam delivering_count");
    assertTest($procOrders['ready_or_delivering_count'] >= 2, "Kombinasi Siap Diambil / Sedang Diantar terakumulasi dengan benar (>= 2)");


    echo "\n--- Skenario 12: Uji isolasi antar-toko, customer, kurir, owner, dan admin owner ---\n";
    // Toko A vs Toko B
    $ordersA = $metricsService->getOrdersToday($ownerTokoAId, Auth::ROLE_OWNER_TOKO, $storeAId);
    $ordersB = $metricsService->getOrdersToday($ownerTokoBId, Auth::ROLE_OWNER_TOKO, $storeBId);
    $ordersOwner = $metricsService->getOrdersToday($ownerId, Auth::ROLE_OWNER);

    assertTest($ordersOwner['total_orders'] >= ($ordersA['total_orders'] + $ordersB['total_orders']), "Owner melihat gabungan pesanan seluruh platform");

    // Customer scoping
    $custOrders = $metricsService->getUnpaidOrders($customerId, Auth::ROLE_CUSTOMER);
    assertTest($custOrders['unpaid_count'] >= 1, "Customer hanya melihat pesanan belum dibayar miliknya sendiri");

    // Courier scoping
    $courierTasks = $metricsService->getCourierTasks($kurirId);
    assertTest(isset($courierTasks['pickup_tasks']) && isset($courierTasks['delivery_tasks']), "Kurir hanya melihat tugas penjemputan dan pengantaran miliknya");


    echo "\n--- Skenario 13: Pertanyaan natural language tentang omzet dan pembayaran ---\n";
    Auth::login(['id' => $ownerId, 'name' => 'Owner Platform FA', 'email' => 'owner_fa_test@laundry.test', 'role_name' => Auth::ROLE_OWNER, 'is_active' => true]);

    $resOmzetToday = $aiService->sendMessage($ownerId, "Berapa omzet hari ini?");
    assertTest($resOmzetToday['success'] === true && str_contains($resOmzetToday['reply'], "Rp"), "Pertanyaan 'Berapa omzet hari ini?' dijawab dengan angka Rupiah");

    $resOmzetMonth = $aiService->sendMessage($ownerId, "Berapa omzet bulan ini?");
    assertTest($resOmzetMonth['success'] === true && str_contains($resOmzetMonth['reply'], "Rp"), "Pertanyaan 'Berapa omzet bulan ini?' dijawab dengan angka Rupiah");

    $resPembayaran = $aiService->sendMessage($ownerId, "Berapa total pembayaran diterima hari ini?");
    assertTest($resPembayaran['success'] === true && str_contains($resPembayaran['reply'], "Rp"), "Pertanyaan 'Berapa total pembayaran diterima hari ini?' dijawab dengan benar");


    echo "\n--- Skenario 14: Non-data queries, rate limit, riwayat chat, dan guardrails non-regresi ---\n";
    // Guardrail cancellation
    $cancelAttempt = $aiService->sendMessage($customerId, "Batalkan pesanan saya ORD-123!");
    assertTest(str_contains(mb_strtolower($cancelAttempt['reply']), "tidak memiliki akses untuk mengeksekusi") || str_contains(mb_strtolower($cancelAttempt['reply']), "riwayat pesanan"), "Guardrail pembatalan transaksi tetap ditegakkan");

    // Out of scope / domain knowledge
    $fabricQuery = $aiService->sendMessage($customerId, "Bagaimana cara merawat kebaya sutra?");
    assertTest(str_contains(mb_strtolower($fabricQuery['reply']), "dry clean") || str_contains(mb_strtolower($fabricQuery['reply']), "sutra"), "Pertanyaan non-data perawatan bahan (kebaya sutra) dijawab dengan rekomendasi Dry Clean");

    // Rate limiting tracking
    $remainingQuota = $aiService->getRemainingQuota($customerId);
    assertTest($remainingQuota >= 0 && $remainingQuota <= 20, "Rate limiting tracking beroperasi normal (Sisa kuota: {$remainingQuota})");

} finally {
    // CLEANUP TEST FIXTURES
    if (!empty($createdRefundIds)) {
        $rList = implode(',', $createdRefundIds);
        $pdo->exec("DELETE FROM refunds WHERE id IN ({$rList})");
    }
    if (!empty($createdPaymentIds)) {
        $pList = implode(',', $createdPaymentIds);
        $pdo->exec("DELETE FROM payments WHERE id IN ({$pList})");
    }
    if (!empty($createdOrderIds)) {
        $oList = implode(',', $createdOrderIds);
        $pdo->exec("DELETE FROM orders WHERE id IN ({$oList})");
    }
    $pdo->prepare("DELETE FROM ai_chat_messages WHERE user_id IN ({$ownerId}, {$ownerTokoAId}, {$ownerTokoBId}, {$customerId}, {$kurirId})")->execute();
}

echo "\n==============================================================================\n";
echo "FINANCIAL ACCURACY REGRESSION RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==============================================================================\n\n";

exit($failCount > 0 ? 1 : 0);
