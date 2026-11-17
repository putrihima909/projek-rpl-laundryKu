<?php

/**
 * Automated Test Suite for Online Payment Module & QRIS Webhook
 * Scenarios:
 * 1. Test unauthorized webhook (Invalid signature -> 401)
 * 2. Test amount mismatch (Nominal mismatch -> 400)
 * 3. Test failed payment (Webhook status: failed -> 200, db: failed)
 * 4. Test expired payment (Webhook status: expired -> 200, db: expired)
 * 5. Test successful payment (Webhook status: paid -> 200, db: paid, order: processing)
 * 6. Test duplicate webhook (Idempotency -> 200, ignored duplicate)
 */

// 1. Register PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

// 2. Load Environment Variables (.env)
\App\Helpers\Env::load(__DIR__ . '/../.env');

// 3. Helper Config Function
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        static $configs = [];
        $parts = explode('.', $key);
        $file = $parts[0];
        $item = $parts[1] ?? null;

        if (!isset($configs[$file])) {
            $path = __DIR__ . "/../config/{$file}.php";
            $configs[$file] = file_exists($path) ? require $path : [];
        }

        return $item === null ? $configs[$file] : ($configs[$file][$item] ?? $default);
    }
}

// 4. Set Timezone
date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use App\Services\Payment\MockPaymentGateway;

class PaymentTestSuite
{
    private \PDO $pdo;
    private PaymentService $service;
    private MockPaymentGateway $gateway;
    private Payment $paymentModel;
    private Order $orderModel;

    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
        $this->gateway = new MockPaymentGateway();
        $this->service = new PaymentService($this->gateway);
        $this->paymentModel = new Payment();
        $this->orderModel = new Order();
    }

    public function run(): void
    {
        echo "\n=======================================================\n";
        echo "   AUTOMATED TEST SUITE: MODUL ONLINE PAYMENT & QRIS   \n";
        echo "=======================================================\n\n";

        // Setup test orders
        $orderMainId = $this->createOrResetTestOrder('ORD-TEST-MAIN-001', 29800.00);
        $orderFailId = $this->createOrResetTestOrder('ORD-TEST-FAIL-001', 50000.00);
        $orderExpId  = $this->createOrResetTestOrder('ORD-TEST-EXP-001', 75000.00);

        // Test 1: Unauthorized Webhook
        $this->testUnauthorizedWebhook($orderMainId);

        // Test 2: Amount Mismatch
        $this->testAmountMismatch($orderMainId);

        // Test 3: Failed Payment
        $this->testFailedPayment($orderFailId);

        // Test 4: Expired Payment
        $this->testExpiredPayment($orderExpId);

        // Test 5: Successful Payment
        $this->testSuccessfulPayment($orderMainId);

        // Test 6: Duplicate Webhook (Idempotency)
        $this->testDuplicateWebhook($orderMainId);

        echo "\n-------------------------------------------------------\n";
        echo "TOTAL RESULTS: {$this->passed} PASSED, {$this->failed} FAILED.\n";
        echo "=======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function createOrResetTestOrder(string $code, float $amount): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE order_code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        $existing = $stmt->fetch();

        if ($existing) {
            $id = (int) $existing['id'];
            // Reset status
            $this->pdo->prepare("UPDATE orders SET total_amount = :amt, payment_method = 'online', payment_status = 'unpaid', status = 'waiting_payment' WHERE id = :id")
                ->execute(['amt' => $amount, 'id' => $id]);
            // Clean old payments
            $this->pdo->prepare("DELETE FROM payments WHERE order_id = :id")->execute(['id' => $id]);
            return $id;
        }

        // Insert new order
        $sql = "INSERT INTO orders (order_code, customer_id, store_id, service_id, address_id, courier_id, estimated_weight, estimated_amount, actual_weight, unit_price, subtotal, additional_fee, discount, total_amount, payment_method, payment_status, status)
                VALUES (:code, 6, 1, 1, 1, 5, 4.8, :amt, 4.8, 6000, :amt, 0, 0, :amt, 'online', 'unpaid', 'waiting_payment')";
        $this->pdo->prepare($sql)->execute(['code' => $code, 'amt' => $amount]);
        return (int) $this->pdo->lastInsertId();
    }

    private function testUnauthorizedWebhook(int $orderId): void
    {
        echo "[TEST 1] Testing Unauthorized Webhook (Invalid Signature)...\n";

        $payment = $this->service->getOrCreatePaymentForOrder($orderId);
        $amount = (float) $payment['amount'];
        $gatewayRef = $payment['gateway_reference'];

        $payload = [
            'gateway' => 'mock_gateway',
            'gateway_reference' => $gatewayRef,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'paid'
        ];

        // Tampered signature
        $headers = ['x-gateway-signature' => 'invalid_tampered_signature_hash_999'];
        $res = $this->service->processWebhook(json_encode($payload), $headers, '127.0.0.1');

        $this->assert($res['http_code'] === 401, "HTTP Code should be 401 Unauthorized, got {$res['http_code']}");
        $this->assert($res['success'] === false, "Success flag should be false");

        // Assert payment in DB is still pending
        $dbPayment = $this->paymentModel->find((int)$payment['id']);
        $this->assert($dbPayment['status'] === Payment::STATUS_PENDING, "Payment status must remain 'pending'");
    }

    private function testAmountMismatch(int $orderId): void
    {
        echo "[TEST 2] Testing Amount Mismatch...\n";

        $payment = $this->service->getOrCreatePaymentForOrder($orderId);
        $tamperedAmount = 10000.00; // Expected 29800.00
        $gatewayRef = $payment['gateway_reference'];

        // Valid signature for the tampered amount
        $sig = $this->gateway->generateSignature($orderId, $tamperedAmount, 'paid', $gatewayRef);

        $payload = [
            'gateway' => 'mock_gateway',
            'gateway_reference' => $gatewayRef,
            'order_id' => $orderId,
            'amount' => $tamperedAmount,
            'status' => 'paid'
        ];

        $headers = ['x-gateway-signature' => $sig];
        $res = $this->service->processWebhook(json_encode($payload), $headers, '127.0.0.1');

        $this->assert($res['http_code'] === 400, "HTTP Code should be 400 Bad Request on amount mismatch, got {$res['http_code']}");
        $this->assert($res['success'] === false, "Success flag should be false");

        // Assert payment in DB is still pending
        $dbPayment = $this->paymentModel->find((int)$payment['id']);
        $this->assert($dbPayment['status'] === Payment::STATUS_PENDING, "Payment status must remain 'pending' on amount mismatch");
    }

    private function testFailedPayment(int $orderId): void
    {
        echo "[TEST 3] Testing Failed Payment Webhook...\n";

        $payment = $this->service->getOrCreatePaymentForOrder($orderId);
        $amount = (float) $payment['amount'];
        $gatewayRef = $payment['gateway_reference'];

        $sig = $this->gateway->generateSignature($orderId, $amount, 'failed', $gatewayRef);

        $payload = [
            'gateway' => 'mock_gateway',
            'gateway_reference' => $gatewayRef,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'failed'
        ];

        $headers = ['x-gateway-signature' => $sig];
        $res = $this->service->processWebhook(json_encode($payload), $headers, '127.0.0.1');

        $this->assert($res['http_code'] === 200, "HTTP Code should be 200 for processed failed webhook, got {$res['http_code']}");

        $dbPayment = $this->paymentModel->find((int)$payment['id']);
        $this->assert($dbPayment['status'] === Payment::STATUS_FAILED, "Payment status in DB must become 'failed'");

        $dbOrder = $this->orderModel->findOrderDetail($orderId);
        $this->assert($dbOrder['payment_status'] === 'unpaid', "Order payment status must remain 'unpaid'");
    }

    private function testExpiredPayment(int $orderId): void
    {
        echo "[TEST 4] Testing Expired Payment Webhook...\n";

        $payment = $this->service->getOrCreatePaymentForOrder($orderId);
        $amount = (float) $payment['amount'];
        $gatewayRef = $payment['gateway_reference'];

        $sig = $this->gateway->generateSignature($orderId, $amount, 'expired', $gatewayRef);

        $payload = [
            'gateway' => 'mock_gateway',
            'gateway_reference' => $gatewayRef,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'expired'
        ];

        $headers = ['x-gateway-signature' => $sig];
        $res = $this->service->processWebhook(json_encode($payload), $headers, '127.0.0.1');

        $this->assert($res['http_code'] === 200, "HTTP Code should be 200 for expired webhook, got {$res['http_code']}");

        $dbPayment = $this->paymentModel->find((int)$payment['id']);
        $this->assert($dbPayment['status'] === Payment::STATUS_EXPIRED, "Payment status in DB must become 'expired'");
    }

    private function testSuccessfulPayment(int $orderId): void
    {
        echo "[TEST 5] Testing Successful Payment (QRIS Paid)...\n";

        $payment = $this->service->getOrCreatePaymentForOrder($orderId);
        $amount = (float) $payment['amount'];
        $gatewayRef = $payment['gateway_reference'];

        $sig = $this->gateway->generateSignature($orderId, $amount, 'paid', $gatewayRef);

        $payload = [
            'gateway' => 'mock_gateway',
            'gateway_reference' => $gatewayRef,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s')
        ];

        $headers = ['x-gateway-signature' => $sig];
        $res = $this->service->processWebhook(json_encode($payload), $headers, '127.0.0.1');

        $this->assert($res['http_code'] === 200, "HTTP Code should be 200 OK, got {$res['http_code']}");
        $this->assert($res['success'] === true, "Success flag must be true");

        // Assert payment in DB is PAID
        $dbPayment = $this->paymentModel->find((int)$payment['id']);
        $this->assert($dbPayment['status'] === Payment::STATUS_PAID, "Payment status in DB must be 'paid'");
        $this->assert(!empty($dbPayment['paid_at']), "Payment paid_at must be populated timestamp");

        // Assert order in DB is paid & processing
        $dbOrder = $this->orderModel->findOrderDetail($orderId);
        $this->assert($dbOrder['payment_status'] === 'paid', "Order payment_status must be 'paid'");
        $this->assert($dbOrder['status'] === Order::STATUS_PROCESSING, "Order status must transition to 'processing'");

        // Assert status history recorded
        $histories = $this->orderModel->getStatusHistory($orderId);
        $latestHistory = end($histories);
        $this->assert($latestHistory['status'] === Order::STATUS_PAID, "Latest order history status must be 'paid'");

        // Assert customer notification created
        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['uid' => $dbOrder['customer_id']]);
        $notif = $stmt->fetch();
        $this->assert(!empty($notif) && str_contains($notif['title'], 'Pembayaran'), "Customer notification must be recorded");
    }

    private function testDuplicateWebhook(int $orderId): void
    {
        echo "[TEST 6] Testing Duplicate Webhook (Idempotency Guarantee)...\n";

        $payment = $this->paymentModel->findLatestByOrder($orderId);
        $amount = (float) $payment['amount'];
        $gatewayRef = $payment['gateway_reference'];

        $sig = $this->gateway->generateSignature($orderId, $amount, 'paid', $gatewayRef);

        $payload = [
            'gateway' => 'mock_gateway',
            'gateway_reference' => $gatewayRef,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s')
        ];

        $headers = ['x-gateway-signature' => $sig];
        
        // Send duplicate webhook
        $res = $this->service->processWebhook(json_encode($payload), $headers, '127.0.0.1');

        $this->assert($res['http_code'] === 200, "Duplicate webhook must return HTTP 200 OK without failing, got {$res['http_code']}");
        $this->assert(isset($res['already_paid']) && $res['already_paid'] === true, "Response must recognize already_paid flag for idempotency");

        // Check payment_logs table for duplicate_ignored event
        $stmt = $this->pdo->prepare("SELECT * FROM payment_logs WHERE payment_id = :pid AND event_type = 'webhook.duplicate_ignored' ORDER BY id DESC LIMIT 1");
        $stmt->execute(['pid' => $payment['id']]);
        $log = $stmt->fetch();
        $this->assert(!empty($log), "An audit log with 'webhook.duplicate_ignored' must be recorded in payment_logs");
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "   \033[32m[PASS]\033[0m {$message}\n";
        } else {
            $this->failed++;
            echo "   \033[31m[FAIL]\033[0m {$message}\n";
        }
    }
}

// Run test suite
$test = new PaymentTestSuite();
$test->run();
