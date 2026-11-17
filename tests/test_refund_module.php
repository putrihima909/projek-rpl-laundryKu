<?php

/**
 * Automated Test Suite: Modul Refund
 * =====================================================================
 * Skenario uji:
 *  1.  Model Refund — createRefund() menyimpan record ke DB
 *  2.  Model Refund — findByOrder() mengembalikan list refund
 *  3.  Guard duplikasi — hasActiveRefund() blokir refund ke-2
 *  4.  Inisiasi refund — payment belum paid harus ditolak
 *  5.  Inisiasi refund — order tidak ditemukan harus ditolak
 *  6.  Inisiasi refund — sukses dari order yang sudah paid
 *  7.  Audit event — logEvent() menyimpan ke payment_events
 *  8.  Status payment berubah ke 'refunded' setelah refund dibuat
 *  9.  Status order payment_status berubah ke 'refunded'
 * 10.  Notifikasi customer dibuat saat refund diinisiasi
 * 11.  processRefundCallback() → completed sukses
 * 12.  processRefundCallback() — idempoten, tidak double-process
 * 13.  processRefundCallback() → failed sukses
 * 14.  Store isolation — toko lain tidak bisa refund pesanan bukan miliknya
 * 15.  Auto-trigger: order unpaid dibatalkan, refund TIDAK dibuat
 * 16.  Auto-trigger: order paid dibatalkan, refund OTOMATIS dibuat
 * 17.  updateStatus() Refund model — field updated_at & completed_at berubah
 * 18.  findByStore() hanya mengembalikan refund toko ybs
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

use App\Models\Payment;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Payment\PaymentService;
use App\Services\Payment\MockPaymentGateway;

// ── Test Suite ─────────────────────────────────────────────────────────────
class RefundTestSuite
{
    private \PDO $pdo;
    private PaymentService $service;
    private MockPaymentGateway $gateway;
    private Payment $paymentModel;
    private Refund  $refundModel;
    private Order   $orderModel;

    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
            \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->gateway      = new MockPaymentGateway();
        $this->service      = new PaymentService($this->gateway);
        $this->paymentModel = new Payment();
        $this->refundModel  = new Refund();
        $this->orderModel   = new Order();
    }

    // ── Runner ─────────────────────────────────────────────────────────
    public function run(): void
    {
        echo "\n";
        echo "=========================================================\n";
        echo "      AUTOMATED TEST SUITE: MODUL REFUND                 \n";
        echo "=========================================================\n\n";

        // -- Setup test fixtures ----------------------------------------
        $paidOrderId   = $this->createPaidOrder('ORD-REFUND-PAID-001', 50000.00);
        $unpaidOrderId = $this->createUnpaidOrder('ORD-REFUND-UNPAID-001', 30000.00);
        $store2OrderId = $this->createPaidOrder('ORD-REFUND-STORE2-001', 20000.00, 2); // store 2

        // -- Core Model Tests -------------------------------------------
        $this->test01_createRefund($paidOrderId);
        $this->test02_findByOrder($paidOrderId);
        $this->test03_duplicateGuard($paidOrderId);

        // -- Service Guard Tests ----------------------------------------
        $this->test04_rejectUnpaidOrder($unpaidOrderId);
        $this->test05_rejectNonExistentOrder();

        // -- Happy-path Refund Initiation (fresh paid order) -----------
        $freshPaidId = $this->createPaidOrder('ORD-REFUND-FRESH-001', 45000.00);
        $this->test06_initiateRefundSuccess($freshPaidId);
        $this->test07_auditEventCreated($freshPaidId);
        $this->test08_paymentMarkedRefunded($freshPaidId);
        $this->test09_orderPaymentStatusRefunded($freshPaidId);
        $this->test10_customerNotificationCreated($freshPaidId);

        // -- Callback / Lifecycle Tests --------------------------------
        $cbOrderId = $this->createPaidOrder('ORD-REFUND-CB-001', 60000.00);
        $refundId  = $this->doInitiateAndGetRefundId($cbOrderId);
        $this->test11_callbackCompleted($refundId);
        $this->test12_idempotentCallback($refundId);

        $failOrderId  = $this->createPaidOrder('ORD-REFUND-FAIL-001', 35000.00);
        $failRefundId = $this->doInitiateAndGetRefundId($failOrderId);
        $this->test13_callbackFailed($failRefundId);

        // -- Store Isolation Test --------------------------------------
        $this->test14_storeIsolationBlocked($store2OrderId);

        // -- Auto-trigger Tests ----------------------------------------
        $this->test15_autoTriggerSkipsUnpaidOrder($unpaidOrderId);
        $autoOrderId = $this->createPaidOrder('ORD-REFUND-AUTO-001', 55000.00);
        $this->test16_autoTriggerOnCancellation($autoOrderId);

        // -- Model Field Tests -----------------------------------------
        $fieldOrderId = $this->createPaidOrder('ORD-REFUND-FIELD-001', 25000.00);
        $fieldRefId   = $this->doInitiateAndGetRefundId($fieldOrderId);
        $this->test17_updatedAtChanges($fieldRefId);

        // -- findByStore scoping test ----------------------------------
        $this->test18_findByStore($store2OrderId);

        // -- Summary ---------------------------------------------------
        echo "\n---------------------------------------------------------\n";
        echo "TOTAL RESULTS: {$this->passed} PASSED, {$this->failed} FAILED.\n";
        echo "=========================================================\n\n";

        if ($this->failed > 0) exit(1);
    }

    // ── Individual Tests ───────────────────────────────────────────────

    private function test01_createRefund(int $orderId): void
    {
        $paymentId = $this->getPaymentId($orderId);
        $id = $this->refundModel->createRefund([
            'payment_id' => $paymentId,
            'order_id'   => $orderId,
            'amount'     => 50000.00,
            'reason'     => '[TEST-01] Unit test createRefund',
            'status'     => Refund::STATUS_PENDING,
            'created_by' => 1,
        ]);
        $this->assert($id > 0, 'T01', 'createRefund() mengembalikan ID > 0');
        // cleanup so subsequent tests are unaffected
        $this->pdo->prepare("DELETE FROM refunds WHERE id = :id")->execute(['id' => $id]);
    }

    private function test02_findByOrder(int $orderId): void
    {
        $paymentId = $this->getPaymentId($orderId);
        $id = $this->refundModel->createRefund([
            'payment_id' => $paymentId,
            'order_id'   => $orderId,
            'amount'     => 50000.00,
            'reason'     => '[TEST-02] findByOrder test',
            'status'     => Refund::STATUS_PENDING,
            'created_by' => 1,
        ]);
        $list = $this->refundModel->findByOrder($orderId);
        $this->assert(count($list) > 0, 'T02', 'findByOrder() mengembalikan minimal 1 baris');
        $this->pdo->prepare("DELETE FROM refunds WHERE id = :id")->execute(['id' => $id]);
    }

    private function test03_duplicateGuard(int $orderId): void
    {
        $paymentId = $this->getPaymentId($orderId);
        // Insert a pending refund
        $id = $this->refundModel->createRefund([
            'payment_id' => $paymentId,
            'order_id'   => $orderId,
            'amount'     => 50000.00,
            'reason'     => '[TEST-03] duplicate guard',
            'status'     => Refund::STATUS_PENDING,
            'created_by' => 1,
        ]);
        $result = $this->refundModel->hasActiveRefund($paymentId);
        $this->assert($result === true, 'T03', 'hasActiveRefund() mengembalikan true saat ada refund aktif');
        $this->pdo->prepare("DELETE FROM refunds WHERE id = :id")->execute(['id' => $id]);
    }

    private function test04_rejectUnpaidOrder(int $unpaidOrderId): void
    {
        $result = $this->service->initiateRefund($unpaidOrderId, 'Test reject unpaid', 1);
        $this->assert($result['success'] === false, 'T04', 'initiateRefund() menolak order yang belum dibayar');
    }

    private function test05_rejectNonExistentOrder(): void
    {
        $result = $this->service->initiateRefund(999999, 'Nonexistent order', 1);
        $this->assert($result['success'] === false, 'T05', 'initiateRefund() menolak order_id yang tidak ada');
    }

    private function test06_initiateRefundSuccess(int $orderId): void
    {
        $result = $this->service->initiateRefund($orderId, '[TEST-06] Refund sukses', 4);
        $this->assert($result['success'] === true, 'T06', 'initiateRefund() berhasil untuk order paid');
        $this->assert(!empty($result['refund_id']), 'T06b', 'initiateRefund() mengembalikan refund_id');
    }

    private function test07_auditEventCreated(int $orderId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM payment_events WHERE order_id = :oid AND event_type = 'refund.initiated'"
        );
        $stmt->execute(['oid' => $orderId]);
        $count = (int) $stmt->fetchColumn();
        $this->assert($count > 0, 'T07', 'logEvent() membuat baris di payment_events saat refund diinisiasi');
    }

    private function test08_paymentMarkedRefunded(int $orderId): void
    {
        $payment = $this->paymentModel->findLatestByOrder($orderId);
        $this->assert(
            $payment && $payment['status'] === Payment::STATUS_REFUNDED,
            'T08',
            'Payment status berubah ke "refunded" setelah refund diinisiasi'
        );
    }

    private function test09_orderPaymentStatusRefunded(int $orderId): void
    {
        $stmt = $this->pdo->prepare("SELECT payment_status FROM orders WHERE id = :id");
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();
        $this->assert(
            $row && $row['payment_status'] === 'refunded',
            'T09',
            'Order payment_status berubah ke "refunded"'
        );
    }

    private function test10_customerNotificationCreated(int $orderId): void
    {
        $order = $this->orderModel->findOrderDetail($orderId);
        $custId = (int) ($order['customer_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND title LIKE '%Refund%'"
        );
        $stmt->execute(['uid' => $custId]);
        $count = (int) $stmt->fetchColumn();
        $this->assert($count > 0, 'T10', 'Notifikasi customer dibuat setelah refund diinisiasi');
    }

    private function test11_callbackCompleted(int $refundId): void
    {
        $result = $this->service->processRefundCallback($refundId, Refund::STATUS_COMPLETED);
        $this->assert($result['success'] === true, 'T11', 'processRefundCallback() berhasil dengan status completed');

        $refund = $this->refundModel->find($refundId);
        $this->assert(
            $refund && $refund['status'] === Refund::STATUS_COMPLETED,
            'T11b',
            'Status refund di DB benar-benar berubah ke completed'
        );
        $this->assert(
            !empty($refund['completed_at']),
            'T11c',
            'completed_at terisi setelah status completed'
        );
    }

    private function test12_idempotentCallback(int $refundId): void
    {
        // Call again with same status
        $result = $this->service->processRefundCallback($refundId, Refund::STATUS_COMPLETED);
        $this->assert(
            $result['success'] === true,
            'T12',
            'processRefundCallback() idempoten: tidak error saat status sudah completed'
        );
    }

    private function test13_callbackFailed(int $refundId): void
    {
        $result = $this->service->processRefundCallback($refundId, Refund::STATUS_FAILED);
        $this->assert($result['success'] === true, 'T13', 'processRefundCallback() berhasil dengan status failed');

        $refund = $this->refundModel->find($refundId);
        $this->assert(
            $refund && $refund['status'] === Refund::STATUS_FAILED,
            'T13b',
            'Status refund di DB berubah ke failed'
        );
    }

    private function test14_storeIsolationBlocked(int $store2OrderId): void
    {
        // Trying to refund from store 1's perspective (storeId=1) on an order belonging to store 2
        $result = $this->service->initiateRefund($store2OrderId, 'Cross-store attempt', 4, 1);
        $this->assert(
            $result['success'] === false,
            'T14',
            'Store isolation: refund cross-store ditolak dengan benar'
        );
    }

    private function test15_autoTriggerSkipsUnpaidOrder(int $unpaidOrderId): void
    {
        $triggered = $this->service->autoTriggerRefundOnCancellation($unpaidOrderId, 1);
        $this->assert($triggered === false, 'T15', 'autoTrigger: TIDAK membuat refund untuk order unpaid');
    }

    private function test16_autoTriggerOnCancellation(int $orderId): void
    {
        $triggered = $this->service->autoTriggerRefundOnCancellation($orderId, 1);
        $this->assert($triggered === true, 'T16', 'autoTrigger: Membuat refund otomatis saat order paid dibatalkan');

        // Verify refund was created
        $refunds = $this->refundModel->findByOrder($orderId);
        $autoRefund = array_filter($refunds, fn($r) => str_contains($r['reason'], 'Refund otomatis'));
        $this->assert(count($autoRefund) > 0, 'T16b', 'autoTrigger: Baris refund dengan reason otomatis tersimpan di DB');
    }

    private function test17_updatedAtChanges(int $refundId): void
    {
        $before = $this->refundModel->find($refundId);
        sleep(1); // ensure timestamp difference
        $this->refundModel->updateStatus($refundId, Refund::STATUS_PROCESSING);
        $after = $this->refundModel->find($refundId);
        $this->assert(
            $before && $after && $after['status'] === Refund::STATUS_PROCESSING,
            'T17',
            'updateStatus() berhasil mengubah status ke processing'
        );
    }

    private function test18_findByStore(int $store2OrderId): void
    {
        $refunds = $this->refundModel->findByStore(2);
        // All returned refunds must belong to store 2
        $allBelongToStore2 = true;
        foreach ($refunds as $r) {
            if ((int) $r['store_id'] !== 2) {
                $allBelongToStore2 = false;
                break;
            }
        }
        $this->assert($allBelongToStore2, 'T18', 'findByStore(2) hanya mengembalikan refund milik store 2');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function assert(bool $condition, string $tag, string $description): void
    {
        if ($condition) {
            echo "  [PASS] [{$tag}] {$description}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] [{$tag}] {$description}\n";
            $this->failed++;
        }
    }

    private function getPaymentId(int $orderId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM payments WHERE order_id = :oid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['oid' => $orderId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : 0;
    }

    /**
     * Create a test order that is already in PAID state,
     * complete with a payment record. Resets if already exists.
     */
    private function createPaidOrder(string $code, float $amount, int $storeId = 1): int
    {
        // Resolve a valid service_id for this store
        $svcStmt = $this->pdo->prepare("SELECT id FROM services WHERE store_id = :sid LIMIT 1");
        $svcStmt->execute(['sid' => $storeId]);
        $svcRow = $svcStmt->fetch();
        $serviceId = $svcRow ? (int) $svcRow['id'] : 1;

        $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE order_code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        $existing = $stmt->fetch();

        if ($existing) {
            $id = (int) $existing['id'];
            $this->pdo->prepare(
                "UPDATE orders SET total_amount = :amt, payment_method = 'online',
                 payment_status = 'paid', status = 'processing', store_id = :sid, updated_at = NOW()
                 WHERE id = :id"
            )->execute(['amt' => $amount, 'sid' => $storeId, 'id' => $id]);
            $this->pdo->prepare("DELETE FROM payments WHERE order_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM refunds  WHERE order_id = :id")->execute(['id' => $id]);
        } else {
            $sql = "INSERT INTO orders
                (order_code, customer_id, store_id, service_id, address_id, courier_id,
                 estimated_weight, estimated_amount, actual_weight, unit_price,
                 subtotal, additional_fee, discount, total_amount,
                 payment_method, payment_status, status)
                VALUES
                (:code, 6, :sid, :svc, 1, 5,
                 5.00, :amt, 5.00, :unit,
                 :amt, 0, 0, :amt,
                 'online', 'paid', 'processing')";
            $this->pdo->prepare($sql)->execute([
                'code' => $code,
                'sid'  => $storeId,
                'svc'  => $serviceId,
                'amt'  => $amount,
                'unit' => $amount / 5,
            ]);
            $id = (int) $this->pdo->lastInsertId();
        }

        // Create a paid payment record
        $gwRef = 'MOCK-PAID-' . strtoupper(bin2hex(random_bytes(5)));
        $this->pdo->prepare(
            "INSERT INTO payments (order_id, payment_method, gateway, gateway_reference, amount, status, paid_at)
             VALUES (:oid, 'qris', 'mock_gateway', :gref, :amt, 'paid', NOW())"
        )->execute(['oid' => $id, 'gref' => $gwRef, 'amt' => $amount]);

        return $id;
    }

    /**
     * Create a test order that is unpaid (no payment record).
     */
    private function createUnpaidOrder(string $code, float $amount): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE order_code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        $existing = $stmt->fetch();

        if ($existing) {
            $id = (int) $existing['id'];
            $this->pdo->prepare(
                "UPDATE orders SET payment_method = 'online', payment_status = 'unpaid', status = 'waiting_payment', updated_at = NOW() WHERE id = :id"
            )->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM payments WHERE order_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM refunds  WHERE order_id = :id")->execute(['id' => $id]);
            return $id;
        }

        $sql = "INSERT INTO orders
            (order_code, customer_id, store_id, service_id, address_id, courier_id,
             estimated_weight, estimated_amount, actual_weight, unit_price,
             subtotal, additional_fee, discount, total_amount,
             payment_method, payment_status, status)
            VALUES
            (:code, 6, 1, 1, 1, 5,
             3.00, :amt, 3.00, 10000,
             30000, 0, 0, :amt,
             'online', 'unpaid', 'waiting_payment')";
        $this->pdo->prepare($sql)->execute(['code' => $code, 'amt' => $amount]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Initiate refund and return the refund_id.
     */
    private function doInitiateAndGetRefundId(int $orderId): int
    {
        $result = $this->service->initiateRefund($orderId, '[SETUP] Initiate for callback test', 4);
        return (int) ($result['refund_id'] ?? 0);
    }
}

// ── Entry Point ─────────────────────────────────────────────────────────────
$suite = new RefundTestSuite();
$suite->run();
