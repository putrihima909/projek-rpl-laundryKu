<?php

namespace App\Models;

use App\Core\Model;

class Payment extends Model
{
    protected string $table = 'payments';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_EXPIRED  = 'expired';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public function findByReference(string $reference): ?array
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare("SELECT * FROM payments WHERE gateway_reference = :ref LIMIT 1");
        $stmt->execute(['ref' => $reference]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findLatestByOrder(int $orderId): ?array
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = :oid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['oid' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createPayment(array $data): int
    {
        if (!$this->db) return 1;

        $fields = [
            'order_id' => $data['order_id'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'qris',
            'gateway' => $data['gateway'] ?? 'mock_gateway',
            'gateway_reference' => $data['gateway_reference'] ?? null,
            'qr_string' => $data['qr_string'] ?? null,
            'status' => $data['status'] ?? self::STATUS_PENDING,
            'expired_at' => $data['expired_at'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
        ];

        return $this->create($fields);
    }

    public function updateStatus(int $paymentId, string $status, ?string $paidAt = null): bool
    {
        if (!$this->db) return true;

        if ($status === self::STATUS_PAID && $paidAt === null) {
            $paidAt = date('Y-m-d H:i:s');
        }

        if ($paidAt !== null) {
            $sql = "UPDATE payments SET status = :st, paid_at = :pa, updated_at = NOW() WHERE id = :id";
            $params = [
                'st' => $status,
                'pa' => $paidAt,
                'id' => $paymentId
            ];
        } else {
            $sql = "UPDATE payments SET status = :st, updated_at = NOW() WHERE id = :id";
            $params = [
                'st' => $status,
                'id' => $paymentId
            ];
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function logEvent(array $logData): int
    {
        if (!$this->db) return 1;

        $sql = "INSERT INTO payment_logs (payment_id, order_id, gateway, event_type, payload, status, notes, ip_address, created_at)
                VALUES (:pid, :oid, :gw, :evt, :pld, :st, :nt, :ip, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pid' => $logData['payment_id'] ?? null,
            'oid' => $logData['order_id'] ?? null,
            'gw' => $logData['gateway'] ?? 'mock_gateway',
            'evt' => $logData['event_type'] ?? 'unknown',
            'pld' => is_array($logData['payload']) ? json_encode($logData['payload']) : (string)$logData['payload'],
            'st' => $logData['status'] ?? 'info',
            'nt' => $logData['notes'] ?? null,
            'ip' => $logData['ip_address'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getLogsForOrder(int $orderId): array
    {
        if (!$this->db) return [];

        $stmt = $this->db->prepare("SELECT * FROM payment_logs WHERE order_id = :oid ORDER BY id DESC");
        $stmt->execute(['oid' => $orderId]);
        return $stmt->fetchAll();
    }
}
