<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Order;
use App\Models\User;
use App\Models\Refund;
use App\Models\Notification;
use App\Services\WhatsApp\WhatsAppNotificationService;

class PaymentService
{
    private PaymentGatewayInterface $gateway;
    private Payment $paymentModel;
    private Order $orderModel;
    private Refund $refundModel;

    public function __construct(?PaymentGatewayInterface $gateway = null)
    {
        $this->gateway      = $gateway ?? $this->resolveGateway();
        $this->paymentModel = new Payment();
        $this->orderModel   = new Order();
        $this->refundModel  = new Refund();
    }

    private function resolveGateway(): PaymentGatewayInterface
    {
        $default = \config('payment.default', 'mock_gateway');
        return match ($default) {
            'mock_gateway' => new MockPaymentGateway(),
            default => new MockPaymentGateway(),
        };
    }

    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Get active valid payment for order, or generate a new QRIS charge.
     */
    public function getOrCreatePaymentForOrder(int $orderId): ?array
    {
        $order = $this->orderModel->findOrderDetail($orderId);
        if (!$order) {
            return null;
        }

        // Check if there is an existing payment
        $existing = $this->paymentModel->findLatestByOrder($orderId);

        if ($existing) {
            // If already paid, return it immediately
            if ($existing['status'] === Payment::STATUS_PAID) {
                return $existing;
            }

            // Check if pending and still valid (within 1 hour)
            if ($existing['status'] === Payment::STATUS_PENDING && !empty($existing['expired_at'])) {
                $now = time();
                $expiredTime = strtotime($existing['expired_at']);
                if ($expiredTime > $now) {
                    return $existing;
                } else {
                    // Mark as expired in database
                    $this->paymentModel->updateStatus((int)$existing['id'], Payment::STATUS_EXPIRED);
                }
            }
        }

        // Amount calculation
        $amount = (float) $order['total_amount'];
        if ($amount <= 0) {
            $amount = (float) $order['estimated_amount'];
        }

        // Create new charge from gateway
        $charge = $this->gateway->createCharge([
            'order_id' => $order['id'],
            'order_code' => $order['order_code'],
            'amount' => $amount,
            'customer_name' => $order['customer_name'] ?? 'Customer',
            'customer_email' => $order['customer_email'] ?? 'customer@laundry.test',
            'customer_phone' => $order['customer_phone'] ?? '081234567890',
        ]);

        $paymentId = $this->paymentModel->createPayment([
            'order_id' => $order['id'],
            'amount' => $amount,
            'payment_method' => 'qris',
            'gateway' => $this->gateway->getName(),
            'gateway_reference' => $charge['gateway_reference'],
            'qr_string' => $charge['qr_string'],
            'status' => Payment::STATUS_PENDING,
            'expired_at' => $charge['expired_at'],
            'paid_at' => null,
        ]);

        // Audit log creation event
        $this->paymentModel->logEvent([
            'payment_id' => $paymentId,
            'order_id' => $order['id'],
            'gateway' => $this->gateway->getName(),
            'event_type' => 'charge.created',
            'payload' => $charge,
            'status' => 'success',
            'notes' => 'Tagihan QRIS baru berhasil dibuat dengan batas waktu 1 jam.',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        // If order was in weighed status, ensure it is in waiting_payment status
        if ($order['status'] === Order::STATUS_WEIGHED) {
            $this->orderModel->updateStatus($orderId, Order::STATUS_WAITING_PAYMENT, 'Menunggu pembayaran QRIS dari customer', $order['customer_id']);
        }

        return $this->paymentModel->find($paymentId);
    }

    /**
     * Process incoming webhook server-to-server with strict security & idempotency.
     * 
     * @param string $rawBody Raw incoming payload
     * @param array $headers Incoming request headers
     * @param string|null $ip Client IP address
     * @return array [ 'http_code' => int, 'success' => bool, 'message' => string, 'data' => array ]
     */
    public function processWebhook(string $rawBody, array $headers, ?string $ip = null): array
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        // 1. Decode JSON
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->paymentModel->logEvent([
                'gateway' => $this->gateway->getName(),
                'event_type' => 'webhook.malformed',
                'payload' => $rawBody,
                'status' => 'failed',
                'notes' => 'Payload webhook bukan format JSON valid.',
                'ip_address' => $ip
            ]);

            return [
                'http_code' => 400,
                'success' => false,
                'message' => 'Bad Request: Malformed JSON payload'
            ];
        }

        // 2. Verify Signature / Authentication
        if (!$this->gateway->verifySignature($payload, $headers)) {
            $this->paymentModel->logEvent([
                'order_id' => $payload['order_id'] ?? null,
                'gateway' => $this->gateway->getName(),
                'event_type' => 'webhook.unauthorized',
                'payload' => $payload,
                'status' => 'unauthorized',
                'notes' => 'Verifikasi signature HMAC gateway gagal. Permintaan ditolak.',
                'ip_address' => $ip
            ]);

            return [
                'http_code' => 401,
                'success' => false,
                'message' => 'Unauthorized: Invalid signature'
            ];
        }

        // 3. Parse Webhook payload into normalized structure
        $parsed = $this->gateway->parseWebhook($payload);
        $gatewayRef = $parsed['gateway_reference'];
        $orderId = $parsed['order_id'];
        $webhookAmount = (float) $parsed['amount'];
        $webhookStatus = strtolower($parsed['status']);

        // 4. Match Order / Payment in database
        $payment = null;
        if (!empty($gatewayRef)) {
            $payment = $this->paymentModel->findByReference($gatewayRef);
        }
        if (!$payment && !empty($orderId)) {
            $payment = $this->paymentModel->findLatestByOrder($orderId);
        }

        if (!$payment) {
            $this->paymentModel->logEvent([
                'order_id' => $orderId,
                'gateway' => $this->gateway->getName(),
                'event_type' => 'webhook.not_found',
                'payload' => $payload,
                'status' => 'not_found',
                'notes' => "Data transaksi pembayaran tidak ditemukan (Ref: {$gatewayRef}, Order ID: {$orderId}).",
                'ip_address' => $ip
            ]);

            return [
                'http_code' => 404,
                'success' => false,
                'message' => 'Payment record not found'
            ];
        }

        $paymentId = (int) $payment['id'];
        $targetOrderId = (int) $payment['order_id'];
        $expectedAmount = (float) $payment['amount'];

        // 5. Verify Amount Match
        if (abs($expectedAmount - $webhookAmount) > 0.01) {
            $this->paymentModel->logEvent([
                'payment_id' => $paymentId,
                'order_id' => $targetOrderId,
                'gateway' => $this->gateway->getName(),
                'event_type' => 'webhook.amount_mismatch',
                'payload' => $payload,
                'status' => 'failed',
                'notes' => "Nominal tidak cocok! Tagihan: Rp {$expectedAmount}, Diterima Webhook: Rp {$webhookAmount}.",
                'ip_address' => $ip
            ]);

            return [
                'http_code' => 400,
                'success' => false,
                'message' => "Amount mismatch: expected {$expectedAmount}, but received {$webhookAmount}"
            ];
        }

        // 6. Check Idempotency: If already marked as PAID, do NOT re-process!
        if ($payment['status'] === Payment::STATUS_PAID) {
            $this->paymentModel->logEvent([
                'payment_id' => $paymentId,
                'order_id' => $targetOrderId,
                'gateway' => $this->gateway->getName(),
                'event_type' => 'webhook.duplicate_ignored',
                'payload' => $payload,
                'status' => 'ignored',
                'notes' => 'Webhook duplikat diterima. Pembayaran sudah lunas sebelumnya. Permintaan diabaikan secara idempoten.',
                'ip_address' => $ip
            ]);

            return [
                'http_code' => 200,
                'success' => true,
                'message' => 'Payment already processed (idempotent)',
                'already_paid' => true,
                'payment_id' => $paymentId
            ];
        }

        // 7. Process Status Transition
        if (in_array($webhookStatus, ['paid', 'success', 'settlement', 'capture'], true)) {
            $paidAt = $parsed['paid_at'] ?? date('Y-m-d H:i:s');

            // Update payment record to PAID
            $this->paymentModel->updateStatus($paymentId, Payment::STATUS_PAID, $paidAt);

            // Update order record
            $db = $this->paymentModel->getDb();
            if ($db) {
                $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing', updated_at = NOW() WHERE id = :id");
                $stmt->execute(['id' => $targetOrderId]);
            }

            // Record status history
            $orderDetail = $this->orderModel->findOrderDetail($targetOrderId);
            $orderCode = $orderDetail['order_code'] ?? "ORD-{$targetOrderId}";
            $customerId = (int) ($orderDetail['customer_id'] ?? 6);

            $notes = sprintf(
                "Pembayaran online QRIS sebesar Rp %s berhasil diverifikasi via Payment Gateway Webhook (Ref: %s)",
                number_format($expectedAmount, 0, ',', '.'),
                $payment['gateway_reference'] ?? $gatewayRef
            );
            $this->orderModel->addStatusHistory($targetOrderId, Order::STATUS_PAID, $notes, null);

            // Create customer notification using Notification Model
            Notification::create(
                $customerId,
                "Pembayaran Pesanan #{$orderCode} Diterima",
                "Pembayaran QRIS sebesar Rp " . number_format($expectedAmount, 0, ',', '.') . " berhasil dikonfirmasi. Cucian Anda sedang diproses oleh toko laundry.",
                Notification::TYPE_SUCCESS
            );

            // WhatsApp Notification: Customer & Store Owner
            try {
                $waService = new WhatsAppNotificationService();
                $waData = array_merge($orderDetail ?: [], [
                    'amount' => $expectedAmount,
                    'payment_method' => 'QRIS',
                    'order_code' => $orderCode,
                ]);
                $waService->sendCustomerNotification($customerId, 'payment_success', $waData);
                $storeId = (int)($orderDetail['store_id'] ?? 0);
                if ($storeId > 0) {
                    $waService->sendStoreOwnerNotification($storeId, 'store_payment_verified', $waData);
                }
            } catch (\Throwable $e) {
                error_log("[PaymentService] WhatsApp payment success dispatch error: " . $e->getMessage());
            }

            // Log event in payment_logs
            $this->paymentModel->logEvent([
                'payment_id' => $paymentId,
                'order_id' => $targetOrderId,
                'gateway' => $this->gateway->getName(),
                'event_type' => 'payment.paid',
                'payload' => $payload,
                'status' => 'processed',
                'notes' => 'Status pembayaran berhasil diubah menjadi PAID via webhook gateway.',
                'ip_address' => $ip
            ]);

            return [
                'http_code' => 200,
                'success' => true,
                'message' => 'Payment successfully processed and verified as PAID',
                'payment_id' => $paymentId,
                'order_id' => $targetOrderId
            ];
        } elseif ($webhookStatus === 'failed') {
            $this->paymentModel->updateStatus($paymentId, Payment::STATUS_FAILED);

            $this->paymentModel->logEvent([
                'payment_id' => $paymentId,
                'order_id' => $targetOrderId,
                'gateway' => $this->gateway->getName(),
                'event_type' => 'payment.failed',
                'payload' => $payload,
                'status' => 'processed',
                'notes' => 'Pembayaran ditandai FAILED oleh gateway.',
                'ip_address' => $ip
            ]);

            // WhatsApp Notification: Payment Failed
            try {
                $waService = new WhatsAppNotificationService();
                $orderDetail = $this->orderModel->findOrderDetail($targetOrderId);
                $customerId = (int)($orderDetail['customer_id'] ?? 0);
                if ($customerId > 0) {
                    $waService->sendCustomerNotification($customerId, 'payment_failed', array_merge($orderDetail ?: [], [
                        'amount' => (float)$payment['amount'],
                        'order_code' => $orderDetail['order_code'] ?? "ORD-{$targetOrderId}",
                    ]));
                }
            } catch (\Throwable $e) {
                error_log("[PaymentService] WhatsApp payment failed dispatch error: " . $e->getMessage());
            }

            return [
                'http_code' => 200,
                'success' => true,
                'message' => 'Payment marked as FAILED',
                'payment_id' => $paymentId
            ];
        } elseif ($webhookStatus === 'expired') {
            $this->paymentModel->updateStatus($paymentId, Payment::STATUS_EXPIRED);

            $this->paymentModel->logEvent([
                'payment_id' => $paymentId,
                'order_id' => $targetOrderId,
                'gateway' => $this->gateway->getName(),
                'event_type' => 'payment.expired',
                'payload' => $payload,
                'status' => 'processed',
                'notes' => 'Pembayaran ditandai EXPIRED oleh gateway.',
                'ip_address' => $ip
            ]);

            return [
                'http_code' => 200,
                'success' => true,
                'message' => 'Payment marked as EXPIRED',
                'payment_id' => $paymentId
            ];
        }

        // Unknown status
        return [
            'http_code' => 200,
            'success'   => true,
            'message'   => "Webhook received with unhandled status: {$webhookStatus}"
        ];
    }

    // ===========================================================================
    // MODUL REFUND
    // ===========================================================================

    /**
     * Initiate a refund for a paid order.
     *
     * Guard checks:
     *  - Payment must exist and be in PAID or REFUNDED state
     *  - Order must be associated to the requesting store (store isolation)
     *  - No active (pending/processing) refund may already exist
     *
     * @param int    $orderId    Order to refund
     * @param string $reason     Reason for refund
     * @param int    $createdBy  User ID initiating the refund
     * @param int|null $storeId  If non-null, verifies order belongs to this store
     * @return array { success, message, refund_id? }
     */
    public function initiateRefund(int $orderId, string $reason, int $createdBy, ?int $storeId = null): array
    {
        // 1. Load order
        $order = $this->orderModel->findOrderDetail($orderId);
        if (!$order) {
            return ['success' => false, 'message' => "Pesanan #{$orderId} tidak ditemukan."];
        }

        // 2. Store isolation guard
        if ($storeId !== null && (int) $order['store_id'] !== $storeId) {
            return ['success' => false, 'message' => 'Akses ditolak: pesanan ini bukan milik toko Anda.'];
        }

        // 3. Payment must be paid
        $payment = $this->paymentModel->findLatestByOrder($orderId);
        if (!$payment || $payment['status'] !== Payment::STATUS_PAID) {
            return ['success' => false, 'message' => 'Refund hanya dapat dilakukan untuk pesanan yang sudah dibayar.'];
        }

        // 4. Duplicate guard
        if ($this->refundModel->hasActiveRefund((int) $payment['id'])) {
            return ['success' => false, 'message' => 'Permintaan refund untuk pesanan ini sudah ada dan sedang diproses.'];
        }

        $paymentId = (int) $payment['id'];
        $amount    = (float) $payment['amount'];

        // 5. Create refund record
        $refundId = $this->refundModel->createRefund([
            'payment_id' => $paymentId,
            'order_id'   => $orderId,
            'amount'     => $amount,
            'reason'     => $reason,
            'status'     => Refund::STATUS_PENDING,
            'created_by' => $createdBy,
        ]);

        // 6. Mark payment as refunded
        $this->paymentModel->updateStatus($paymentId, Payment::STATUS_REFUNDED);

        // 7. Update order payment_status to refunded
        $db = $this->paymentModel->getDb();
        if ($db) {
            $stmt = $db->prepare("UPDATE orders SET payment_status = 'refunded', updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $orderId]);
        }

        // 8. Audit event
        $this->refundModel->logEvent([
            'payment_id' => $paymentId,
            'refund_id'  => $refundId,
            'order_id'   => $orderId,
            'event_type' => 'refund.initiated',
            'payload'    => [
                'refund_id'  => $refundId,
                'amount'     => $amount,
                'reason'     => $reason,
                'created_by' => $createdBy,
                'initiated_at' => date('Y-m-d H:i:s'),
            ],
        ]);

        // 9. Customer notification using Notification Model
        $orderCode  = $order['order_code'] ?? "ORD-{$orderId}";
        $customerId = (int) ($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            Notification::create(
                $customerId,
                "Permintaan Refund #{$orderCode} Diterima",
                "Permintaan pengembalian dana sebesar Rp " . number_format($amount, 0, ',', '.') . " sedang diproses. Alasan: {$reason}",
                Notification::TYPE_INFO
            );

            // WhatsApp Notification: Customer & Store Owner
            try {
                $waService = new WhatsAppNotificationService();
                $waData = array_merge($order, [
                    'amount' => $amount,
                    'reason' => $reason,
                    'order_code' => $orderCode,
                ]);
                $waService->sendCustomerNotification($customerId, 'refund_submitted', $waData);
                $storeId = (int)($order['store_id'] ?? 0);
                if ($storeId > 0) {
                    $waService->sendStoreOwnerNotification($storeId, 'store_refund_requested', $waData);
                }
            } catch (\Throwable $e) {
                error_log("[PaymentService::initiateRefund] WhatsApp dispatch error: " . $e->getMessage());
            }
        }

        return [
            'success'   => true,
            'message'   => 'Permintaan refund berhasil dibuat dan sedang diproses.',
            'refund_id' => $refundId,
            'amount'    => $amount,
        ];
    }

    /**
     * Process refund callback / simulate gateway confirmation.
     *
     * Transitions: pending → processing → completed (or failed)
     *
     * @param int    $refundId    Refund record ID
     * @param string $callbackStatus  'completed' or 'failed'
     * @param string|null $gatewayReference  Optional gateway ref for completed refund
     * @return array { success, message }
     */
    public function processRefundCallback(int $refundId, string $callbackStatus, ?string $gatewayReference = null): array
    {
        $refund = $this->refundModel->find($refundId);
        if (!$refund) {
            return ['success' => false, 'message' => "Refund ID {$refundId} tidak ditemukan."];
        }

        $allowed = [Refund::STATUS_COMPLETED, Refund::STATUS_FAILED];
        if (!in_array($callbackStatus, $allowed, true)) {
            return ['success' => false, 'message' => "Status refund tidak valid: {$callbackStatus}."];
        }

        // Idempotency
        if ($refund['status'] === $callbackStatus) {
            return ['success' => true, 'message' => "Refund sudah berada di status {$callbackStatus} (idempoten)."];
        }

        // Move to processing first, then to final status
        $this->refundModel->updateStatus($refundId, Refund::STATUS_PROCESSING);

        $gwRef = $gatewayReference ?? ('MOCK-REFUND-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4))));
        $this->refundModel->updateStatus($refundId, $callbackStatus, $gwRef);

        $orderId   = (int) $refund['order_id'];
        $paymentId = (int) $refund['payment_id'];
        $amount    = (float) $refund['amount'];

        // Audit event
        $this->refundModel->logEvent([
            'payment_id' => $paymentId,
            'refund_id'  => $refundId,
            'order_id'   => $orderId,
            'event_type' => "refund.{$callbackStatus}",
            'payload'    => [
                'refund_id'         => $refundId,
                'status'            => $callbackStatus,
                'gateway_reference' => $gwRef,
                'amount'            => $amount,
                'processed_at'      => date('Y-m-d H:i:s'),
            ],
        ]);

        // Customer notification using Notification Model
        $order     = $this->orderModel->findOrderDetail($orderId);
        $orderCode = $order['order_code'] ?? "ORD-{$orderId}";
        $custId    = (int) ($order['customer_id'] ?? 0);
        $type      = $callbackStatus === Refund::STATUS_COMPLETED ? Notification::TYPE_SUCCESS : Notification::TYPE_DANGER;
        $msg = $callbackStatus === Refund::STATUS_COMPLETED
            ? "Dana sebesar Rp " . number_format($amount, 0, ',', '.') . " untuk pesanan #{$orderCode} telah dikembalikan ke metode pembayaran Anda. (Ref: {$gwRef})"
            : "Proses pengembalian dana untuk pesanan #{$orderCode} gagal. Silakan hubungi layanan pelanggan.";

        if ($custId > 0) {
            Notification::create(
                $custId,
                "Refund #{$orderCode} " . ucfirst($callbackStatus),
                $msg,
                $type
            );

            // WhatsApp Notification: Customer & Store Owner
            try {
                $waService = new WhatsAppNotificationService();
                $waData = array_merge($order ?: [], [
                    'amount' => $amount,
                    'gateway_reference' => $gwRef,
                    'refund_status' => $callbackStatus,
                    'order_code' => $orderCode,
                ]);

                if ($callbackStatus === Refund::STATUS_COMPLETED) {
                    $waService->sendCustomerNotification($custId, 'refund_completed', $waData);
                } else {
                    $waService->sendCustomerNotification($custId, 'refund_failed', $waData);
                }

                $storeId = (int)($order['store_id'] ?? 0);
                if ($storeId > 0) {
                    $waService->sendStoreOwnerNotification($storeId, 'store_refund_processed', $waData);
                }
            } catch (\Throwable $e) {
                error_log("[PaymentService::processRefundCallback] WhatsApp dispatch error: " . $e->getMessage());
            }
        }

        return [
            'success'           => true,
            'message'           => "Refund berhasil diperbarui ke status {$callbackStatus}.",
            'gateway_reference' => $gwRef,
        ];
    }

    /**
     * Auto-trigger refund when a PAID order is cancelled.
     * Called from OrderController::updateStatus when cancelling a paid order.
     *
     * @return bool Whether a refund was automatically initiated
     */
    public function autoTriggerRefundOnCancellation(int $orderId, int $cancelledBy): bool
    {
        $order = $this->orderModel->findOrderDetail($orderId);
        if (!$order || $order['payment_status'] !== 'paid') {
            return false;
        }

        $payment = $this->paymentModel->findLatestByOrder($orderId);
        if (!$payment || $payment['status'] !== Payment::STATUS_PAID) {
            return false;
        }

        // Avoid double trigger
        if ($this->refundModel->hasActiveRefund((int) $payment['id'])) {
            return false;
        }

        $result = $this->initiateRefund(
            $orderId,
            'Refund otomatis: Pesanan dibatalkan setelah pembayaran dikonfirmasi.',
            $cancelledBy
        );

        return $result['success'];
    }
}
