<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Get the name/identifier of this payment gateway.
     */
    public function getName(): string;

    /**
     * Create a charge / QRIS invoice for an order.
     * 
     * @param array $params Contains: order_id, order_code, amount, customer_name, customer_email, customer_phone
     * @return array Contains: gateway_reference, qr_string, qr_url, expired_at, raw
     */
    public function createCharge(array $params): array;

    /**
     * Verify incoming webhook signature/authentication.
     * 
     * @param array $payload Incoming parsed webhook payload
     * @param array $headers Incoming HTTP request headers
     * @return bool True if validly authenticated and signed
     */
    public function verifySignature(array $payload, array $headers): bool;

    /**
     * Parse webhook payload into standardized format.
     * 
     * @param array $payload
     * @return array Contains: gateway_reference, order_id, amount, status, signature, raw
     */
    public function parseWebhook(array $payload): array;
}
