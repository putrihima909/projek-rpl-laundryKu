<?php

namespace App\Services\Payment;

class MockPaymentGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $merchantName;
    private string $merchantCity;
    private string $merchantId;

    public function __construct(?string $secretKey = null)
    {
        $mockConfig = \config('payment.mock', []);
        $this->secretKey = $secretKey ?? ($mockConfig['secret_key'] ?? 'mock_secret_key_laundry_2026_xyz');
        $this->merchantName = $mockConfig['merchant_name'] ?? 'LaundryKu';
        $this->merchantCity = $mockConfig['merchant_city'] ?? 'Jakarta Selatan';
        $this->merchantId = $mockConfig['merchant_id'] ?? 'ID1020304050607';
    }

    public function getName(): string
    {
        return 'mock_gateway';
    }

    public function createCharge(array $params): array
    {
        $orderId = (int) ($params['order_id'] ?? 0);
        $orderCode = (string) ($params['order_code'] ?? 'ORD-' . $orderId);
        $amount = (float) ($params['amount'] ?? 0.00);

        // Generate unique gateway reference ID
        $gatewayRef = 'MOCK-QRIS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));

        // Expiration: Exactly 1 hour from now (in consistent timezone Asia/Jakarta)
        $expiryHours = (int) \config('payment.expiry_hours', 1);
        $expiredAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));

        // Generate authentic EMVCo QRIS payload string
        $qrString = $this->buildEmvcoQrisString($gatewayRef, $amount);

        return [
            'gateway' => $this->getName(),
            'gateway_reference' => $gatewayRef,
            'qr_string' => $qrString,
            'qr_url' => null,
            'amount' => $amount,
            'expired_at' => $expiredAt,
            'raw' => [
                'merchant' => $this->merchantName,
                'city' => $this->merchantCity,
                'order_code' => $orderCode,
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        // Extract signature from header 'X-Gateway-Signature' or fallback payload 'signature'
        $providedSig = $headers['x-gateway-signature'] 
            ?? $headers['X-Gateway-Signature'] 
            ?? ($headers['HTTP_X_GATEWAY_SIGNATURE'] ?? null);

        if (!$providedSig && isset($payload['signature'])) {
            $providedSig = $payload['signature'];
        }

        if (empty($providedSig)) {
            return false;
        }

        $orderId = (int) ($payload['order_id'] ?? 0);
        $amount = number_format((float)($payload['amount'] ?? 0), 2, '.', '');
        $status = (string) ($payload['status'] ?? '');
        $gatewayRef = (string) ($payload['gateway_reference'] ?? ($payload['transaction_id'] ?? ''));

        $expectedSig = $this->generateSignature($orderId, $amount, $status, $gatewayRef);

        return hash_equals($expectedSig, (string) $providedSig);
    }

    public function generateSignature(int $orderId, string|float $amount, string $status, string $gatewayRef): string
    {
        $amountStr = is_numeric($amount) ? number_format((float)$amount, 2, '.', '') : (string)$amount;
        $signString = "{$orderId}|{$amountStr}|{$status}|{$gatewayRef}";
        return hash_hmac('sha256', $signString, $this->secretKey);
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'gateway' => $this->getName(),
            'gateway_reference' => $payload['gateway_reference'] ?? ($payload['transaction_id'] ?? null),
            'order_id' => isset($payload['order_id']) ? (int)$payload['order_id'] : null,
            'amount' => isset($payload['amount']) ? (float)$payload['amount'] : 0.00,
            'status' => strtolower((string)($payload['status'] ?? 'pending')),
            'paid_at' => $payload['paid_at'] ?? date('Y-m-d H:i:s'),
            'signature' => $payload['signature'] ?? null,
            'raw' => $payload
        ];
    }

    /**
     * Build authentic standard EMVCo QRIS Payload string.
     * Follows Bank Indonesia / ASPI QRIS technical specifications.
     */
    private function buildEmvcoQrisString(string $reference, float $amount): string
    {
        $amountStr = number_format($amount, 0, '', '');
        $cleanMerchant = substr(strtoupper(preg_replace('/[^A-Z0-9 ]/', '', $this->merchantName)), 0, 25);
        $cleanCity = substr(strtoupper(preg_replace('/[^A-Z0-9 ]/', '', $this->merchantCity)), 0, 15);

        // EMVCo Data Elements:
        // 00: Payload Format Indicator
        $data = "000201";
        // 01: Point of Initiation Method (12: Dynamic QR with fixed amount)
        $data .= "010212";
        // 26: Merchant Account Information (Domestic QRIS ID)
        $maiSub = "0014ID.GO.QRIS.WWW01189360099900000100010215" . $this->merchantId;
        $data .= "26" . str_pad((string)strlen($maiSub), 2, '0', STR_PAD_LEFT) . $maiSub;
        // 52: Merchant Category Code (MCC 5999 = Miscellaneous Retail)
        $data .= "52045999";
        // 53: Transaction Currency (360 = IDR)
        $data .= "5303360";
        // 54: Transaction Amount
        $data .= "54" . str_pad((string)strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;
        // 58: Country Code (ID)
        $data .= "5802ID";
        // 59: Merchant Name
        $data .= "59" . str_pad((string)strlen($cleanMerchant), 2, '0', STR_PAD_LEFT) . $cleanMerchant;
        // 60: Merchant City
        $data .= "60" . str_pad((string)strlen($cleanCity), 2, '0', STR_PAD_LEFT) . $cleanCity;
        // 62: Additional Data Field (Reference / Order)
        $refSub = "01" . str_pad((string)strlen($reference), 2, '0', STR_PAD_LEFT) . $reference;
        $data .= "62" . str_pad((string)strlen($refSub), 2, '0', STR_PAD_LEFT) . $refSub;
        // 63: CRC16 Checksum prefix
        $dataWithPrefix = $data . "6304";

        $crc = $this->calculateCrc16Ccitt($dataWithPrefix);
        return $dataWithPrefix . $crc;
    }

    /**
     * Calculate 16-bit CRC (CCITT-FALSE: poly 0x1021, init 0xFFFF).
     */
    private function calculateCrc16Ccitt(string $data): string
    {
        $crc = 0xFFFF;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
