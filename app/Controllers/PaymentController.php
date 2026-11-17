<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use App\Services\Payment\MockPaymentGateway;

class PaymentController extends Controller
{
    private PaymentService $paymentService;
    private Order $orderModel;
    private Payment $paymentModel;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
        $this->orderModel = new Order();
        $this->paymentModel = new Payment();
    }

    /**
     * Display QRIS Payment Page for an Order.
     * Route: GET /orders/payment?order_id=...
     */
    public function showQris(): void
    {
        $orderId = (int) ($_GET['order_id'] ?? ($_GET['id'] ?? 0));
        if ($orderId <= 0) {
            Session::flash('error', 'ID Pesanan tidak valid.');
            $this->redirect('/orders');
            return;
        }

        $order = $this->orderModel->findOrderDetail($orderId);
        if (!$order) {
            Session::flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('/orders');
            return;
        }

        $user = Auth::user();
        $role = Auth::role();

        // Server-Side Authorization: Only Customer who owns the order or Store/Platform Admins
        if ($role === Auth::ROLE_CUSTOMER && (int)$order['customer_id'] !== Auth::id()) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk mengakses pembayaran pesanan ini.');
            $this->redirect('/orders');
            return;
        }

        // Check if customer can pay: payment method must be online
        if ($order['payment_method'] !== 'online') {
            Session::flash('error', 'Pesanan ini menggunakan metode pembayaran Tunai (Cash), tidak memerlukan QRIS.');
            $this->redirect('/orders/detail?id=' . $orderId);
            return;
        }

        // Get or Create Payment
        $payment = $this->paymentService->getOrCreatePaymentForOrder($orderId);
        if (!$payment) {
            Session::flash('error', 'Gagal memproses tagihan pembayaran QRIS.');
            $this->redirect('/orders/detail?id=' . $orderId);
            return;
        }

        $expiredTimestamp = !empty($payment['expired_at']) ? strtotime($payment['expired_at']) : time() + 3600;
        $remainingSeconds = max(0, $expiredTimestamp - time());

        $data = [
            'pageTitle' => 'Pembayaran QRIS - Pesanan #' . htmlspecialchars($order['order_code']),
            'order' => $order,
            'payment' => $payment,
            'remainingSeconds' => $remainingSeconds,
            'user' => $user,
            'role' => $role,
            'csrf_token' => Csrf::generateToken(),
        ];

        $this->view('payments.qris', $data, 'main');
    }

    /**
     * Check payment status (Ajax Polling from client).
     * Strictly read-only! Never mutates payment or order status.
     * Route: GET /api/payment/status?order_id=...
     */
    public function checkStatusAjax(): void
    {
        $orderId = (int) ($_GET['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid order ID'], 400);
            return;
        }

        $order = $this->orderModel->findOrderDetail($orderId);
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Order not found'], 404);
            return;
        }

        // Authorization check
        $role = Auth::role();
        if ($role === Auth::ROLE_CUSTOMER && (int)$order['customer_id'] !== Auth::id()) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $payment = $this->paymentModel->findLatestByOrder($orderId);
        $isPaid = ($payment && $payment['status'] === Payment::STATUS_PAID) || ($order['payment_status'] === 'paid');
        $isExpired = $payment && $payment['status'] === Payment::STATUS_EXPIRED;

        $this->json([
            'success' => true,
            'order_id' => $orderId,
            'payment_status' => $payment['status'] ?? 'pending',
            'order_payment_status' => $order['payment_status'] ?? 'unpaid',
            'order_status' => $order['status'] ?? 'pending',
            'is_paid' => $isPaid,
            'is_expired' => $isExpired,
            'paid_at' => $payment['paid_at'] ?? null,
            'gateway_reference' => $payment['gateway_reference'] ?? null
        ]);
    }

    /**
     * Handle incoming Payment Gateway Webhook.
     * Route: POST /webhook/payment
     */
    public function handleWebhook(): void
    {
        $rawBody = file_get_contents('php://input');
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        // Normalize header keys
        $normalizedHeaders = [];
        foreach ($headers as $key => $val) {
            $normalizedHeaders[strtolower($key)] = $val;
        }

        // Fallback for Apache/Nginx environment variables
        if (!isset($normalizedHeaders['x-gateway-signature']) && isset($_SERVER['HTTP_X_GATEWAY_SIGNATURE'])) {
            $normalizedHeaders['x-gateway-signature'] = $_SERVER['HTTP_X_GATEWAY_SIGNATURE'];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $result = $this->paymentService->processWebhook($rawBody, $normalizedHeaders, $ip);

        $this->json($result, $result['http_code'] ?? 200);
    }

    /**
     * Development Simulator Endpoint for testing webhook triggers.
     * Route: POST /payment/mock-gateway/simulate
     */
    public function simulateWebhook(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $mode = trim($_POST['mode'] ?? 'success'); // success, failed, expired, invalid_signature, amount_mismatch, duplicate

        $payment = $this->paymentModel->findLatestByOrder($orderId);
        if (!$payment && $orderId > 0) {
            $payment = $this->paymentService->getOrCreatePaymentForOrder($orderId);
        }

        if (!$payment) {
            $this->json(['success' => false, 'message' => 'Payment record not found for order ' . $orderId], 404);
            return;
        }

        $gateway = $this->paymentService->getGateway();
        if (!($gateway instanceof MockPaymentGateway)) {
            $gateway = new MockPaymentGateway();
        }

        $amount = (float) $payment['amount'];
        $gatewayRef = $payment['gateway_reference'] ?? ('MOCK-QRIS-' . $orderId);

        // Customize simulation parameters based on selected mode
        $status = match ($mode) {
            'failed' => 'failed',
            'expired' => 'expired',
            default => 'paid',
        };

        $simulatedAmount = $amount;
        if ($mode === 'amount_mismatch') {
            $simulatedAmount = $amount + 15000.00; // Intentionally mismatch
        }

        // Generate signature
        $validSignature = $gateway->generateSignature($orderId, $simulatedAmount, $status, $gatewayRef);
        $signatureToUse = ($mode === 'invalid_signature') ? 'tampered_invalid_signature_hash_12345' : $validSignature;

        $payload = [
            'event' => 'payment.notification',
            'gateway' => $gateway->getName(),
            'gateway_reference' => $gatewayRef,
            'order_id' => $orderId,
            'amount' => $simulatedAmount,
            'status' => $status,
            'paid_at' => date('Y-m-d H:i:s'),
            'signature' => $signatureToUse
        ];

        $headers = [
            'x-gateway-signature' => $signatureToUse,
            'content-type' => 'application/json'
        ];

        $rawBody = json_encode($payload);
        $result = $this->paymentService->processWebhook($rawBody, $headers, '127.0.0.1 (Simulator)');

        $this->json([
            'simulator_mode' => $mode,
            'dispatched_payload' => $payload,
            'gateway_response' => $result
        ], $result['http_code'] ?? 200);
    }
}
