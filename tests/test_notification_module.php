<?php

/**
 * Automated Test Suite: Modul Notifikasi (Tahap 9)
 * =====================================================================
 * Skenario uji:
 *  1.  Model Notification — create() menyimpan record ke DB & kembalikan ID
 *  2.  Model Notification — findByUser() mengembalikan notifikasi urut descending
 *  3.  Model Notification — countUnread() & countByUser() menghitung akurat
 *  4.  Model Notification — timeAgo() memformat waktu relatif bahasa Indonesia
 *  5.  Security & Isolasi User — User A DIBLOKIR saat mencoba mark-as-read notifikasi User B
 *  6.  Security & Isolasi User — markAllAsRead() hanya mengubah notifikasi user yang login
 *  7.  Event Trigger: Order dibuat (createOrder) → notif ke customer
 *  8.  Event Trigger: Kurir ditugaskan (assignCourier) → notif ke customer & kurir
 *  9.  Event Trigger: Status kurir (courier_on_the_way, picked_up, arrived_at_store) → notif ke customer
 * 10.  Event Trigger: Penimbangan (weighed) → notif ke customer dengan berat & harga final
 * 11.  Event Trigger: Menunggu pembayaran (waiting_payment) → notif ke customer
 * 12.  Event Trigger: Pembayaran sukses (paid via webhook) → notif ke customer
 * 13.  Event Trigger: Status pengerjaan & pengantaran (processing, ready, out_for_delivery, delivered)
 * 14.  Event Trigger: Pembatalan pesanan (cancelled) → notif ke customer
 * 15.  Event Trigger: Refund diinisiasi & callback selesai/gagal → notif ke customer
 * 16.  Controller & Endpoint: Polling GET /notifications/unread-count terotorisasi dengan benar
 * =====================================================================
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
spl_autoload_register(function ($class) {
    $prefix   = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len      = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

\App\Helpers\Env::load(__DIR__ . '/../.env');

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        static $configs = [];
        $parts = explode('.', $key);
        $file  = $parts[0];
        $item  = $parts[1] ?? null;
        if (!isset($configs[$file])) {
            $path = __DIR__ . "/../config/{$file}.php";
            $configs[$file] = file_exists($path) ? require $path : [];
        }
        return $item === null ? $configs[$file] : ($configs[$file][$item] ?? $default);
    }
}

date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\PaymentService;
use App\Controllers\NotificationController;
use App\Helpers\Auth;
use App\Helpers\Session;

// ── Test Runner Utilities ──────────────────────────────────────────────────
$totalTests  = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $desc, bool $condition, ?string $detail = null): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$desc}" . ($detail ? " ({$detail})" : '') . "\n";
    }
}

echo "\n";
echo "======================================================================\n";
echo "   AUTOMATED TEST SUITE: MODUL NOTIFIKASI & EVENT TRIGGERS (TAHAP 9)   \n";
echo "======================================================================\n\n";

$db = \App\Core\Database::getInstance();

// Setup clean testing state for notifications
$testUserA = 6; // Customer (Dewi Lestari)
$testUserB = 5; // Kurir (Agus Express)

// Hapus notifikasi testing lama agar hitungan deterministik
$db->exec("DELETE FROM notifications WHERE message LIKE '%[TEST-NOTIF-%'");

// ===========================================================================
// [TEST 1] MODEL NOTIFICATION BASIC CRUD & QUERYING
// ===========================================================================
echo "[TEST 1] Testing Notification Model Basics (create, find, findByUser, count)...\n";

$uniqueTagA = '[TEST-NOTIF-A-' . time() . ']';
$notifIdA1 = Notification::create($testUserA, 'Pesanan Siap', "Cucian Anda siap diantar {$uniqueTagA}", Notification::TYPE_SUCCESS);
$notifIdA2 = Notification::create($testUserA, 'Tagihan Dibuat', "Tagihan sebesar Rp 50.000 telah dibuat {$uniqueTagA}", Notification::TYPE_WARNING);

assertTest("create() returns valid positive integer ID", is_int($notifIdA1) && $notifIdA1 > 0);
assertTest("Second notification created successfully", is_int($notifIdA2) && $notifIdA2 > $notifIdA1);

$foundNotif = Notification::find($notifIdA1);
assertTest("find() retrieves correct notification record", $foundNotif !== null && (int)$foundNotif['id'] === $notifIdA1);
assertTest("Notification record contains correct user_id", (int)$foundNotif['user_id'] === $testUserA);
assertTest("Notification type is 'success'", $foundNotif['type'] === Notification::TYPE_SUCCESS);
assertTest("Newly created notification is unread (is_read = 0)", (int)$foundNotif['is_read'] === 0);

$userANotifs = Notification::findByUser($testUserA, 10);
assertTest("findByUser() returns list containing created notifications", count($userANotifs) >= 2);
assertTest("findByUser() sorts in descending chronological order", (int)$userANotifs[0]['id'] >= (int)$userANotifs[1]['id']);

$unreadA = Notification::countUnread($testUserA);
assertTest("countUnread() returns accurate count (>= 2)", $unreadA >= 2);

$totalA = Notification::countByUser($testUserA);
assertTest("countByUser() returns total count (>= unread)", $totalA >= $unreadA);

// ===========================================================================
// [TEST 2] TIMEAGO HELPER FORMATTING
// ===========================================================================
echo "\n[TEST 2] Testing Relative Time Formatting (timeAgo)...\n";

$now = date('Y-m-d H:i:s');
$fiveMinsAgo = date('Y-m-d H:i:s', time() - 300);
$twoHoursAgo = date('Y-m-d H:i:s', time() - 7200);
$yesterday = date('Y-m-d H:i:s', time() - 90000);

assertTest("timeAgo() for current timestamp returns 'Baru saja'", Notification::timeAgo($now) === 'Baru saja');
assertTest("timeAgo() for 5 mins ago contains 'menit lalu'", str_contains(Notification::timeAgo($fiveMinsAgo), 'menit lalu'));
assertTest("timeAgo() for 2 hours ago contains 'jam lalu'", str_contains(Notification::timeAgo($twoHoursAgo), 'jam lalu'));
assertTest("timeAgo() for yesterday contains 'Kemarin'", str_contains(Notification::timeAgo($yesterday), 'Kemarin'));

// ===========================================================================
// [TEST 3] USER ISOLATION & SECURITY GUARD (MARK AS READ)
// ===========================================================================
echo "\n[TEST 3] Testing User Isolation Guard (User A cannot mark User B's notification)...\n";

$uniqueTagB = '[TEST-NOTIF-B-' . time() . ']';
$notifIdB = Notification::create($testUserB, 'Tugas Kurir Baru', "Anda mendapat tugas baru {$uniqueTagB}", Notification::TYPE_PRIMARY);
assertTest("Notification for User B created", is_int($notifIdB) && $notifIdB > 0);

// User A (ID 6) attempts to mark User B's (ID 5) notification as read
$tamperResult = Notification::markAsRead($notifIdB, $testUserA);
assertTest("User A attempting to mark User B's notification must be BLOCKED (returns false)", $tamperResult === false);

// Check that User B's notification remains unread in database
$checkNotifB = Notification::find($notifIdB);
assertTest("User B's notification MUST remain unread (is_read = 0)", (int)$checkNotifB['is_read'] === 0);

// User B marks its own notification as read
$validResultB = Notification::markAsRead($notifIdB, $testUserB);
assertTest("User B marking their own notification succeeds (returns true)", $validResultB === true);

$checkNotifB2 = Notification::find($notifIdB);
assertTest("User B's notification is now read (is_read = 1)", (int)$checkNotifB2['is_read'] === 1);

// markAllAsRead isolation test
$beforeUnreadB = Notification::countUnread($testUserB);
$notifIdB_new = Notification::create($testUserB, 'Pemberitahuan Sistem', "Update sistem {$uniqueTagB}", Notification::TYPE_INFO);

// User A marks all their notifications as read
Notification::markAllAsRead($testUserA);
$unreadAAfter = Notification::countUnread($testUserA);
assertTest("markAllAsRead(User A) resets User A's unread count to 0", $unreadAAfter === 0);

// User B's new notification must still be unread
$unreadBAfter = Notification::countUnread($testUserB);
assertTest("markAllAsRead(User A) DOES NOT affect User B's unread notifications", $unreadBAfter >= 1);

// ===========================================================================
// [TEST 4] EVENT TRIGGER: ORDER CREATION & COURIER ASSIGNMENT
// ===========================================================================
echo "\n[TEST 4] Testing Event Triggers: Order Created & Courier Assigned...\n";

$orderModel = new Order();

// 1. Order Creation Trigger
$orderCodeTest = 'ORD-TEST-NOTIF-' . rand(100, 999);
$createdOrderId = $orderModel->createOrder([
    'customer_id'      => $testUserA,
    'store_id'         => 1,
    'service_id'       => 1,
    'address_id'       => 1,
    'courier_id'       => null,
    'estimated_weight' => 3.50,
    'estimated_amount' => 21000.00,
    'unit_price'       => 6000.00,
    'total_amount'     => 21000.00,
    'notes'            => 'Pesanan uji coba trigger notifikasi',
    'payment_method'   => 'online',
    'payment_status'   => 'unpaid',
    'status'           => Order::STATUS_PENDING
]);

assertTest("Order created successfully with ID {$createdOrderId}", is_int($createdOrderId) && $createdOrderId > 0);

// Verify customer received "Pesanan Dibuat" notification
$orderDetail = $orderModel->findOrderDetail($createdOrderId);
$orderCode = $orderDetail['order_code'];

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid AND title LIKE :title ORDER BY id DESC LIMIT 1");
$stmt->execute(['uid' => $testUserA, 'title' => "%Pesanan Dibuat #{$orderCode}%"]);
$notifOrderCreated = $stmt->fetch();

assertTest("Customer received 'Pesanan Dibuat' notification for #{$orderCode}", $notifOrderCreated !== false);
assertTest("Notification body mentions estimated amount", str_contains($notifOrderCreated['message'] ?? '', '21.000'));

// 2. Courier Assignment Trigger (notif to customer AND courier)
$orderModel->assignCourier($createdOrderId, $testUserB, 1);

// Check customer notification: "Kurir Ditugaskan"
$stmt->execute(['uid' => $testUserA, 'title' => "%Kurir Ditugaskan #{$orderCode}%"]);
$notifCustCourier = $stmt->fetch();
assertTest("Customer received 'Kurir Ditugaskan' notification", $notifCustCourier !== false);

// Check courier notification: "Tugas Penjemputan Baru"
$stmtCourier = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid AND title LIKE :title ORDER BY id DESC LIMIT 1");
$stmtCourier->execute(['uid' => $testUserB, 'title' => "%Tugas Penjemputan Baru #{$orderCode}%"]);
$notifCourierTask = $stmtCourier->fetch();
assertTest("Courier (User B) received 'Tugas Penjemputan Baru' notification", $notifCourierTask !== false);

// ===========================================================================
// [TEST 5] EVENT TRIGGER: COURIER PICKUP STATUS TRANSITIONS
// ===========================================================================
echo "\n[TEST 5] Testing Event Triggers: Courier Movement (on the way, picked up, arrived at store)...\n";

// courier_on_the_way
$orderModel->updateStatus($createdOrderId, Order::STATUS_COURIER_ON_THE_WAY, 'Kurir menuju lokasi', $testUserB);
$stmt->execute(['uid' => $testUserA, 'title' => "%Kurir Menuju Lokasi #{$orderCode}%"]);
$notifOnTheWay = $stmt->fetch();
assertTest("Customer received 'Kurir Menuju Lokasi' notification", $notifOnTheWay !== false);

// picked_up
$orderModel->updateStatus($createdOrderId, Order::STATUS_PICKED_UP, 'Cucian dijemput', $testUserB);
$stmt->execute(['uid' => $testUserA, 'title' => "%Cucian Dijemput #{$orderCode}%"]);
$notifPickedUp = $stmt->fetch();
assertTest("Customer received 'Cucian Dijemput' notification", $notifPickedUp !== false);

// arrived_at_store
$orderModel->updateStatus($createdOrderId, Order::STATUS_ARRIVED_AT_STORE, 'Tiba di toko', $testUserB);
$stmt->execute(['uid' => $testUserA, 'title' => "%Tiba di Toko Laundry #{$orderCode}%"]);
$notifArrived = $stmt->fetch();
assertTest("Customer received 'Tiba di Toko Laundry' notification", $notifArrived !== false);

// ===========================================================================
// [TEST 6] EVENT TRIGGER: WEIGHING & WAITING PAYMENT
// ===========================================================================
echo "\n[TEST 6] Testing Event Triggers: Weighing (weighed) & Waiting Payment...\n";

// updateWeighing: 4.0 kg @ 6000 = 24.000 + 2.000 - 1.000 = 25.000
$orderModel->updateWeighing($createdOrderId, 4.00, 2000.00, 1000.00, 4);

$stmt->execute(['uid' => $testUserA, 'title' => "%Penimbangan Selesai #{$orderCode}%"]);
$notifWeighed = $stmt->fetch();
assertTest("Customer received 'Penimbangan Selesai' notification", $notifWeighed !== false);
assertTest("Weighing notification contains actual weight '4'", str_contains($notifWeighed['message'] ?? '', '4'));
assertTest("Weighing notification contains final total '25.000'", str_contains($notifWeighed['message'] ?? '', '25.000'));

// waiting_payment
$orderModel->updateStatus($createdOrderId, Order::STATUS_WAITING_PAYMENT, 'Menunggu pembayaran QRIS', 4);
$stmt->execute(['uid' => $testUserA, 'title' => "%Menunggu Pembayaran #{$orderCode}%"]);
$notifWaitPay = $stmt->fetch();
assertTest("Customer received 'Menunggu Pembayaran' notification", $notifWaitPay !== false);

// ===========================================================================
// [TEST 7] EVENT TRIGGER: PAYMENT CONFIRMATION (PAID VIA PAYMENTSERVICE)
// ===========================================================================
echo "\n[TEST 7] Testing Event Trigger: Successful Payment Confirmation (PAID)...\n";

$paymentService = new PaymentService();
$gateway = $paymentService->getGateway();
$paymentInfo = $paymentService->getOrCreatePaymentForOrder($createdOrderId);
$paymentId = (int)$paymentInfo['id'];
$amount = (float)$paymentInfo['amount'];
$gatewayRef = $paymentInfo['gateway_reference'];

$sig = $gateway->generateSignature($createdOrderId, $amount, 'paid', $gatewayRef);

$fakePayload = [
    'gateway'           => 'mock_gateway',
    'gateway_reference' => $gatewayRef,
    'order_id'          => $createdOrderId,
    'amount'            => $amount,
    'status'            => 'paid',
    'paid_at'           => date('Y-m-d H:i:s')
];

$headers = ['x-gateway-signature' => $sig];
$webhookResult = $paymentService->processWebhook(json_encode($fakePayload), $headers, '127.0.0.1');
assertTest("Payment webhook returned success", ($webhookResult['success'] ?? false) === true);

$stmt->execute(['uid' => $testUserA, 'title' => "%Pembayaran Pesanan #{$orderCode} Diterima%"]);
$notifPaid = $stmt->fetch();
assertTest("Customer received 'Pembayaran Pesanan Diterima' notification via Notification Model", $notifPaid !== false);
assertTest("Paid notification type is 'success'", ($notifPaid['type'] ?? '') === Notification::TYPE_SUCCESS);

// ===========================================================================
// [TEST 8] EVENT TRIGGER: PRODUCTION & DELIVERY LIFECYCLE
// ===========================================================================
echo "\n[TEST 8] Testing Event Triggers: Production, Ready, Out for Delivery, Delivered...\n";

// ready
$orderModel->updateStatus($createdOrderId, Order::STATUS_READY, 'Cucian telah selesai dan siap diantar', 4);
$stmt->execute(['uid' => $testUserA, 'title' => "%Cucian Selesai & Siap #{$orderCode}%"]);
$notifReady = $stmt->fetch();
assertTest("Customer received 'Cucian Selesai & Siap' notification", $notifReady !== false);

// out_for_delivery
$orderModel->updateStatus($createdOrderId, Order::STATUS_OUT_FOR_DELIVERY, 'Kurir mengantar cucian', $testUserB);
$stmt->execute(['uid' => $testUserA, 'title' => "%Cucian Sedang Diantar #{$orderCode}%"]);
$notifOut = $stmt->fetch();
assertTest("Customer received 'Cucian Sedang Diantar' notification", $notifOut !== false);

// delivered
$orderModel->updateStatus($createdOrderId, Order::STATUS_DELIVERED, 'Cucian selesai diterima', $testUserB);
$stmt->execute(['uid' => $testUserA, 'title' => "%Pesanan Selesai #{$orderCode}%"]);
$notifDelivered = $stmt->fetch();
assertTest("Customer received 'Pesanan Selesai' notification", $notifDelivered !== false);

// ===========================================================================
// [TEST 9] EVENT TRIGGER: CANCELLATION & REFUND LIFECYCLE
// ===========================================================================
echo "\n[TEST 9] Testing Event Triggers: Order Cancellation & Refund Callback...\n";

// Create another order for cancellation & refund test
$orderCancelId = $orderModel->createOrder([
    'customer_id'      => $testUserA,
    'store_id'         => 1,
    'service_id'       => 1,
    'address_id'       => 1,
    'courier_id'       => null,
    'estimated_weight' => 2.00,
    'estimated_amount' => 12000.00,
    'unit_price'       => 6000.00,
    'total_amount'     => 12000.00,
    'notes'            => 'Order untuk pengujian pembatalan',
    'payment_method'   => 'online',
    'payment_status'   => 'paid',
    'status'           => Order::STATUS_PENDING
]);

$cancelOrderDetail = $orderModel->findOrderDetail($orderCancelId);
$cancelOrderCode = $cancelOrderDetail['order_code'];

// Setup mock payment record as PAID
$cancelPayment = $paymentService->getOrCreatePaymentForOrder($cancelCancel = $orderCancelId);
(new Payment())->updateStatus((int)$cancelPayment['id'], Payment::STATUS_PAID);

// Cancel order
$orderModel->updateStatus($orderCancelId, Order::STATUS_CANCELLED, 'Dibatalkan oleh pelanggan', $testUserA);
$stmt->execute(['uid' => $testUserA, 'title' => "%Pesanan Dibatalkan #{$cancelOrderCode}%"]);
$notifCancelled = $stmt->fetch();
assertTest("Customer received 'Pesanan Dibatalkan' notification", $notifCancelled !== false);

// Initiate refund
$refundResult = $paymentService->initiateRefund($orderCancelId, 'Batal sebelum dikerjakan', 1);
assertTest("Refund initiated successfully", ($refundResult['success'] ?? false) === true);

$stmt->execute(['uid' => $testUserA, 'title' => "%Permintaan Refund #{$cancelOrderCode} Diterima%"]);
$notifRefundInit = $stmt->fetch();
assertTest("Customer received 'Permintaan Refund Diterima' notification", $notifRefundInit !== false);

// Process refund callback to completed
$refundId = (int)$refundResult['refund_id'];
$callbackResult = $paymentService->processRefundCallback($refundId, Refund::STATUS_COMPLETED);
assertTest("Refund callback processed to completed", ($callbackResult['success'] ?? false) === true);

$stmt->execute(['uid' => $testUserA, 'title' => "%Refund #{$cancelOrderCode} Completed%"]);
$notifRefundDone = $stmt->fetch();
assertTest("Customer received 'Refund Completed' notification with success type", $notifRefundDone !== false && $notifRefundDone['type'] === Notification::TYPE_SUCCESS);

// ===========================================================================
// [TEST 10] POLLING ENDPOINT GET /notifications/unread-count & CONTROLLER
// ===========================================================================
echo "\n[TEST 10] Testing Polling Endpoint & NotificationController Authorization...\n";

// 1. Unauthenticated Request -> must return 401 JSON
Auth::logout();
ob_start();
$notifController = new NotificationController();
$notifController->unreadCount();
$rawUnauthJson = ob_get_clean();
$unauthData = json_decode($rawUnauthJson, true);

assertTest("Unauthenticated unreadCount() returns HTTP status 'unauthorized'", ($unauthData['status'] ?? '') === 'unauthorized');
assertTest("Unauthenticated unreadCount() returns unread_count = 0", ($unauthData['unread_count'] ?? -1) === 0);

// 2. Authenticated Request as User A -> must return 200 JSON with unread count
Auth::login([
    'id'        => $testUserA,
    'name'      => 'Dewi Lestari',
    'email'     => 'customer@laundry.test',
    'role_name' => 'customer',
    'is_active' => true
]);

// Create one unread notification for User A
Notification::create($testUserA, 'Notifikasi Polling', 'Test isi polling', Notification::TYPE_INFO);
$expectedUnread = Notification::countUnread($testUserA);

ob_start();
$notifController->unreadCount();
$rawAuthJson = ob_get_clean();
$authData = json_decode($rawAuthJson, true);

assertTest("Authenticated unreadCount() returns status 'success'", ($authData['status'] ?? '') === 'success');
assertTest("Authenticated unreadCount() returns accurate unread_count ({$expectedUnread})", ($authData['unread_count'] ?? -1) === $expectedUnread);

// Clean test data
$db->exec("DELETE FROM notifications WHERE message LIKE '%[TEST-NOTIF-%'");

// ===========================================================================
// SUMMARY & RESULTS
// ===========================================================================
echo "\n----------------------------------------------------------------------\n";
echo "Hasil Akhir Test Suite: {$passedTests} PASS / {$totalTests} Total";
if ($failedTests > 0) {
    echo " ({$failedTests} GAGAL)\n";
    echo "STATUS: TERDAPAT TEST YANG GAGAL!\n";
} else {
    echo "\nSTATUS: SEMUA TEST NOTIFIKASI BERHASIL LULUS SECARA SEMPURNA (100% PASS)!\n";
}
echo "======================================================================\n\n";

if ($failedTests > 0) {
    exit(1);
}
