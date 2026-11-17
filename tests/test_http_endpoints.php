<?php

/**
 * Automated Test Suite: HTTP Endpoints, Webhooks & Router Dispatch Smoke Test
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

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use App\Services\Payment\MockPaymentGateway;
use App\Controllers\PaymentController;

$pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
]);

// Helper to create or reset test order
function setupHttpTestOrder(\PDO $pdo, string $code, float $amount): int {
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_code = :code LIMIT 1");
    $stmt->execute(['code' => $code]);
    $row = $stmt->fetch();
    if ($row) {
        $id = (int)$row['id'];
        $pdo->prepare("UPDATE orders SET total_amount = :amt, payment_method = 'online', payment_status = 'unpaid', status = 'waiting_payment' WHERE id = :id")
            ->execute(['amt' => $amount, 'id' => $id]);
        $pdo->prepare("DELETE FROM payments WHERE order_id = :id")->execute(['id' => $id]);
        return $id;
    }

    $sql = "INSERT INTO orders (order_code, customer_id, store_id, service_id, address_id, courier_id, estimated_weight, estimated_amount, actual_weight, unit_price, subtotal, additional_fee, discount, total_amount, payment_method, payment_status, status)
            VALUES (:code, 1, 1, 1, 1, NULL, 3.0, :amt, 3.0, :amt, :amt, 0.0, 0.0, :amt, 'online', 'unpaid', 'waiting_payment')";
    $pdo->prepare($sql)->execute(['code' => $code, 'amt' => $amount]);
    return (int)$pdo->lastInsertId();
}

$orderId = setupHttpTestOrder($pdo, 'ORD-HTTP-TEST-001', 35000.00);
$gateway = new MockPaymentGateway();
$service = new PaymentService($gateway);
$payment = $service->getOrCreatePaymentForOrder($orderId);
$ref = $payment['gateway_reference'];
$validSig = $gateway->generateSignature($orderId, 35000.00, 'paid', $ref);

$passCount = 0;
$failCount = 0;

echo "\n=======================================================\n";
echo "   AUDIT ENDPOINT HTTP & WEBHOOK ROUTING SMOKE TEST    \n";
echo "=======================================================\n\n";

// Test 1: Invalid Signature
echo "--- 1. Testing Webhook Controller with INVALID Signature ---\n";
$payloadBad = [
    'gateway' => 'mock_gateway',
    'gateway_reference' => $ref,
    'order_id' => $orderId,
    'amount' => 35000.00,
    'status' => 'paid'
];
$headersBad = ['x-gateway-signature' => 'bad_signature_xyz'];
$resBad = $service->processWebhook(json_encode($payloadBad), $headersBad, '127.0.0.1');
if ($resBad['http_code'] === 401 && $resBad['success'] === false) {
    echo "[PASS] Invalid signature rejected with HTTP 401\n";
    $passCount++;
} else {
    echo "[FAIL] Invalid signature expected HTTP 401, got: {$resBad['http_code']}\n";
    $failCount++;
}

// Test 2: Valid Signature
echo "--- 2. Testing Webhook Controller with VALID Signature for Order #{$orderId} ---\n";
$payloadGood = [
    'gateway' => 'mock_gateway',
    'gateway_reference' => $ref,
    'order_id' => $orderId,
    'amount' => 35000.00,
    'status' => 'paid',
    'paid_at' => date('Y-m-d H:i:s')
];
$headersGood = ['x-gateway-signature' => $validSig];
$resGood = $service->processWebhook(json_encode($payloadGood), $headersGood, '127.0.0.1');
if ($resGood['http_code'] === 200 && $resGood['success'] === true) {
    echo "[PASS] Valid signature accepted with HTTP 200\n";
    $passCount++;
} else {
    echo "[FAIL] Valid signature expected HTTP 200, got: {$resGood['http_code']}\n";
    $failCount++;
}

// Test 3: Duplicate Webhook for Idempotency
echo "--- 3. Testing Webhook Controller DUPLICATE for Idempotency ---\n";
$resDup = $service->processWebhook(json_encode($payloadGood), $headersGood, '127.0.0.1');
if ($resDup['http_code'] === 200 && $resDup['success'] === true && !empty($resDup['already_paid'])) {
    echo "[PASS] Duplicate webhook handled idempotently with HTTP 200\n";
    $passCount++;
} else {
    echo "[FAIL] Duplicate webhook expected 200 & already_paid, got: " . json_encode($resDup) . "\n";
    $failCount++;
}

echo "\nTOTAL RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
exit($failCount > 0 ? 1 : 0);
