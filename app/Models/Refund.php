<?php

namespace App\Models;

use App\Core\Model;

class Refund extends Model
{
    protected string $table = 'refunds';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /**
     * Create a new refund record.
     */
    public function createRefund(array $data): int
    {
        if (!$this->db) return 0;

        $fields = [
            'payment_id'        => $data['payment_id'],
            'order_id'          => $data['order_id'],
            'amount'            => $data['amount'],
            'reason'            => $data['reason'],
            'status'            => $data['status']            ?? self::STATUS_PENDING,
            'gateway_reference' => $data['gateway_reference'] ?? null,
            'created_by'        => $data['created_by']        ?? null,
        ];

        return (int) $this->create($fields);
    }

    /**
     * Update refund status (and optionally completed_at).
     */
    public function updateStatus(int $refundId, string $status, ?string $gatewayReference = null): bool
    {
        if (!$this->db) return true;

        $completedAt = in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)
            ? date('Y-m-d H:i:s')
            : null;

        $sql = "UPDATE refunds 
                SET status = :st,
                    gateway_reference = COALESCE(:gref, gateway_reference),
                    completed_at = :cat,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'st'   => $status,
            'gref' => $gatewayReference,
            'cat'  => $completedAt,
            'id'   => $refundId,
        ]);
    }

    /**
     * Find all refunds for a given order.
     */
    public function findByOrder(int $orderId): array
    {
        if (!$this->db) return [];

        $stmt = $this->db->prepare(
            "SELECT r.*, u.name AS created_by_name
             FROM refunds r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.order_id = :oid
             ORDER BY r.id DESC"
        );
        $stmt->execute(['oid' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Find all refunds scoped to a specific store (for store isolation).
     */
    public function findByStore(int $storeId, string $status = ''): array
    {
        if (!$this->db) return [];

        $sql = "SELECT r.*, o.order_code, o.store_id,
                       u.name AS customer_name, ub.name AS created_by_name
                FROM refunds r
                JOIN orders o ON o.id = r.order_id
                JOIN users u  ON u.id = o.customer_id
                LEFT JOIN users ub ON ub.id = r.created_by
                WHERE o.store_id = :sid";

        $params = ['sid' => $storeId];

        if ($status !== '') {
            $sql   .= " AND r.status = :st";
            $params['st'] = $status;
        }

        $sql .= " ORDER BY r.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find all refunds platform-wide (for owner/admin_owner dashboards).
     */
    public function findAll(string $status = ''): array
    {
        if (!$this->db) return [];

        $sql = "SELECT r.*, o.order_code, o.store_id, s.name AS store_name,
                       u.name AS customer_name, ub.name AS created_by_name
                FROM refunds r
                JOIN orders  o  ON o.id  = r.order_id
                JOIN stores  s  ON s.id  = o.store_id
                JOIN users   u  ON u.id  = o.customer_id
                LEFT JOIN users ub ON ub.id = r.created_by";

        $params = [];
        if ($status !== '') {
            $sql    .= " WHERE r.status = :st";
            $params['st'] = $status;
        }

        $sql .= " ORDER BY r.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Check whether a pending/processing refund already exists for a payment.
     */
    public function hasActiveRefund(int $paymentId): bool
    {
        if (!$this->db) return false;

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM refunds
             WHERE payment_id = :pid AND status IN ('pending', 'processing')"
        );
        $stmt->execute(['pid' => $paymentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Log a refund-related event in payment_events.
     */
    public function logEvent(array $data): int
    {
        if (!$this->db) return 1;

        $sql = "INSERT INTO payment_events (payment_id, refund_id, order_id, event_type, payload, created_at)
                VALUES (:pid, :rid, :oid, :evt, :pld, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pid' => $data['payment_id'] ?? null,
            'rid' => $data['refund_id']  ?? null,
            'oid' => $data['order_id']   ?? null,
            'evt' => $data['event_type'] ?? 'refund.event',
            'pld' => is_array($data['payload'] ?? null)
                     ? json_encode($data['payload'])
                     : (string) ($data['payload'] ?? '{}'),
        ]);

        return (int) $this->db->lastInsertId();
    }
}
