<?php

namespace App\Services\Ai;

use App\Core\Database;
use App\Helpers\Auth;
use App\Models\Store;
use App\Models\User;
use DateTime;
use DateTimeZone;
use PDO;
use Throwable;

class MetricsService
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getInstance();
            } catch (Throwable $e) {
                $this->db = null;
            }
        }
    }

    public function getDb(): ?PDO
    {
        return $this->db;
    }

    /**
     * Resolves the application timezone (Asia/Jakarta, UTC+7).
     */
    public static function getTimezone(): DateTimeZone
    {
        return new DateTimeZone('Asia/Jakarta');
    }

    /**
     * Returns inclusive-exclusive timestamp bounds for today in Asia/Jakarta:
     * [Y-m-d 00:00:00, Y-m-d 00:00:00 (tomorrow)]
     */
    public static function getTodayRange(): array
    {
        $tz = self::getTimezone();
        $start = (new DateTime('today', $tz))->format('Y-m-d 00:00:00');
        $end = (new DateTime('tomorrow', $tz))->format('Y-m-d 00:00:00');
        return [$start, $end];
    }

    /**
     * Returns inclusive-exclusive timestamp bounds for the current month in Asia/Jakarta:
     * [Y-m-01 00:00:00, first day of next month 00:00:00]
     */
    public static function getMonthRange(): array
    {
        $tz = self::getTimezone();
        $start = (new DateTime('first day of this month 00:00:00', $tz))->format('Y-m-d 00:00:00');
        $end = (new DateTime('first day of next month 00:00:00', $tz))->format('Y-m-d 00:00:00');
        return [$start, $end];
    }

    /**
     * Resolves the store ID for a given user if they are an Owner Toko or Admin Toko.
     */
    public function resolveUserStore(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $storeModel = new Store();
            return $storeModel->getStoreByOwner($userId);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get order counts created today based on orders.created_at within Asia/Jakarta calendar day.
     * Also computes the order-based revenue for orders created today that are paid.
     */
    public function getOrdersToday(int $userId, string $role, ?int $storeId = null): array
    {
        [$startDate, $endDate] = self::getTodayRange();
        $today = (new DateTime('today', self::getTimezone()))->format('Y-m-d');

        if (!$this->db) {
            return [
                'date' => $today,
                'total_orders' => 0,
                'pending_orders' => 0,
                'processing_orders' => 0,
                'ready_orders' => 0,
                'delivering_orders' => 0,
                'delivered_orders' => 0,
                'cancelled_orders' => 0,
                'paid_orders_count' => 0,
                'paid_orders_amount' => 0.0,
            ];
        }

        $conditions = [
            "o.created_at >= :start_date",
            "o.created_at < :end_date"
        ];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $this->applyRoleScope($conditions, $params, $userId, $role, $storeId);

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN o.status IN ('assigned_to_courier', 'courier_on_the_way', 'picked_up', 'arrived_at_store', 'weighed', 'waiting_payment', 'paid', 'processing') THEN 1 ELSE 0 END) as processing_orders,
                    SUM(CASE WHEN o.status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
                    SUM(CASE WHEN o.status = 'out_for_delivery' THEN 1 ELSE 0 END) as delivering_orders,
                    SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders_count,
                    COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as paid_orders_amount
                FROM orders o
                WHERE {$whereClause}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'date' => $today,
                'total_orders' => (int)($row['total_orders'] ?? 0),
                'pending_orders' => (int)($row['pending_orders'] ?? 0),
                'processing_orders' => (int)($row['processing_orders'] ?? 0),
                'ready_orders' => (int)($row['ready_orders'] ?? 0),
                'delivering_orders' => (int)($row['delivering_orders'] ?? 0),
                'delivered_orders' => (int)($row['delivered_orders'] ?? 0),
                'cancelled_orders' => (int)($row['cancelled_orders'] ?? 0),
                'paid_orders_count' => (int)($row['paid_orders_count'] ?? 0),
                'paid_orders_amount' => (float)($row['paid_orders_amount'] ?? 0.0),
            ];
        } catch (Throwable $e) {
            return [
                'date' => $today,
                'total_orders' => 0,
                'pending_orders' => 0,
                'processing_orders' => 0,
                'ready_orders' => 0,
                'delivering_orders' => 0,
                'delivered_orders' => 0,
                'cancelled_orders' => 0,
                'paid_orders_count' => 0,
                'paid_orders_amount' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get revenue / payments received today.
     * Uses payments table and actual paid_at timestamp within Asia/Jakarta calendar day.
     * Also includes order-based revenue for orders created today that are paid.
     */
    public function getRevenueToday(int $userId, string $role, ?int $storeId = null): array
    {
        [$startDate, $endDate] = self::getTodayRange();
        $today = (new DateTime('today', self::getTimezone()))->format('Y-m-d');

        if (!$this->db) {
            return [
                'date' => $today,
                'revenue' => 0.0,
                'paid_orders_count' => 0,
                'order_revenue_today' => 0.0,
                'order_paid_count' => 0,
            ];
        }

        // 1. Transaction-based: payments received today based on payments.paid_at
        // Filter: payments with status IN ('paid', 'refunded') that succeeded today.
        // Idempotency: group by order_id so multiple paid attempts for a single order do not double count.
        $conditionsPay = [
            "p.paid_at >= :start_date",
            "p.paid_at < :end_date",
            "p.status IN ('paid', 'refunded')"
        ];
        $paramsPay = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        $this->applyRoleScope($conditionsPay, $paramsPay, $userId, $role, $storeId);
        $wherePay = implode(' AND ', $conditionsPay);

        $sqlPay = "SELECT 
                        COALESCE(SUM(sub.paid_amount), 0) as total_received,
                        COUNT(sub.order_id) as total_payments
                   FROM (
                       SELECT 
                           o.id as order_id,
                           MAX(p.amount) as paid_amount
                       FROM payments p
                       JOIN orders o ON o.id = p.order_id
                       WHERE {$wherePay}
                       GROUP BY o.id
                   ) sub";

        // 2. Order-based: orders created today that have payment_status = 'paid'
        $conditionsOrder = [
            "o.created_at >= :start_date",
            "o.created_at < :end_date",
            "o.payment_status = 'paid'"
        ];
        $paramsOrder = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        $this->applyRoleScope($conditionsOrder, $paramsOrder, $userId, $role, $storeId);
        $whereOrder = implode(' AND ', $conditionsOrder);

        $sqlOrder = "SELECT 
                         COALESCE(SUM(o.total_amount), 0) as order_revenue,
                         COUNT(*) as order_paid_count
                     FROM orders o
                     WHERE {$whereOrder}";

        try {
            $stmtPay = $this->db->prepare($sqlPay);
            $stmtPay->execute($paramsPay);
            $rowPay = $stmtPay->fetch(PDO::FETCH_ASSOC) ?: [];

            $stmtOrder = $this->db->prepare($sqlOrder);
            $stmtOrder->execute($paramsOrder);
            $rowOrder = $stmtOrder->fetch(PDO::FETCH_ASSOC) ?: [];

            $paymentsReceived = (float)($rowPay['total_received'] ?? 0.0);
            $paymentsCount = (int)($rowPay['total_payments'] ?? 0);
            $orderRevenue = (float)($rowOrder['order_revenue'] ?? 0.0);
            $orderCount = (int)($rowOrder['order_paid_count'] ?? 0);

            return [
                'date' => $today,
                'revenue' => $paymentsReceived, // Pembayaran diterima hari ini (transaksi dana masuk)
                'paid_orders_count' => $paymentsCount,
                'order_revenue_today' => $orderRevenue, // Nilai pesanan dibuat hari ini yang terbayar
                'order_paid_count' => $orderCount,
            ];
        } catch (Throwable $e) {
            return [
                'date' => $today,
                'revenue' => 0.0,
                'paid_orders_count' => 0,
                'order_revenue_today' => 0.0,
                'order_paid_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get omzet / payments received for the current month in Asia/Jakarta.
     * Uses payments table with paid_at timestamp.
     */
    public function getRevenueThisMonth(int $userId, string $role, ?int $storeId = null): array
    {
        [$startMonth, $endMonth] = self::getMonthRange();
        $monthName = (new DateTime('now', self::getTimezone()))->format('F Y');

        if (!$this->db) {
            return [
                'month' => $monthName,
                'revenue' => 0.0,
                'paid_orders_count' => 0,
                'order_revenue_month' => 0.0,
                'order_paid_count' => 0,
            ];
        }

        $conditionsPay = [
            "p.paid_at >= :start_month",
            "p.paid_at < :end_month",
            "p.status IN ('paid', 'refunded')"
        ];
        $paramsPay = [
            'start_month' => $startMonth,
            'end_month' => $endMonth,
        ];
        $this->applyRoleScope($conditionsPay, $paramsPay, $userId, $role, $storeId);
        $wherePay = implode(' AND ', $conditionsPay);

        $sqlPay = "SELECT 
                        COALESCE(SUM(sub.paid_amount), 0) as total_received,
                        COUNT(sub.order_id) as total_payments
                   FROM (
                       SELECT 
                           o.id as order_id,
                           MAX(p.amount) as paid_amount
                       FROM payments p
                       JOIN orders o ON o.id = p.order_id
                       WHERE {$wherePay}
                       GROUP BY o.id
                   ) sub";

        $conditionsOrder = [
            "o.created_at >= :start_month",
            "o.created_at < :end_month",
            "o.payment_status = 'paid'"
        ];
        $paramsOrder = [
            'start_month' => $startMonth,
            'end_month' => $endMonth,
        ];
        $this->applyRoleScope($conditionsOrder, $paramsOrder, $userId, $role, $storeId);
        $whereOrder = implode(' AND ', $conditionsOrder);

        $sqlOrder = "SELECT 
                         COALESCE(SUM(o.total_amount), 0) as order_revenue,
                         COUNT(*) as order_paid_count
                     FROM orders o
                     WHERE {$whereOrder}";

        try {
            $stmtPay = $this->db->prepare($sqlPay);
            $stmtPay->execute($paramsPay);
            $rowPay = $stmtPay->fetch(PDO::FETCH_ASSOC) ?: [];

            $stmtOrder = $this->db->prepare($sqlOrder);
            $stmtOrder->execute($paramsOrder);
            $rowOrder = $stmtOrder->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'month' => $monthName,
                'revenue' => (float)($rowPay['total_received'] ?? 0.0),
                'paid_orders_count' => (int)($rowPay['total_payments'] ?? 0),
                'order_revenue_month' => (float)($rowOrder['order_revenue'] ?? 0.0),
                'order_paid_count' => (int)($rowOrder['order_paid_count'] ?? 0),
            ];
        } catch (Throwable $e) {
            return [
                'month' => $monthName,
                'revenue' => 0.0,
                'paid_orders_count' => 0,
                'order_revenue_month' => 0.0,
                'order_paid_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get orders that are unpaid.
     */
    public function getUnpaidOrders(int $userId, string $role, ?int $storeId = null): array
    {
        if (!$this->db) {
            return ['unpaid_count' => 0, 'total_unpaid_amount' => 0.0];
        }

        $conditions = ["o.payment_status = 'unpaid'", "o.status != 'cancelled'"];
        $params = [];

        $this->applyRoleScope($conditions, $params, $userId, $role, $storeId);

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT COUNT(*) as unpaid_count, COALESCE(SUM(COALESCE(o.total_amount, o.estimated_amount, 0)), 0) as total_amount 
                FROM orders o 
                WHERE {$whereClause}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'unpaid_count' => (int)($row['unpaid_count'] ?? 0),
                'total_unpaid_amount' => (float)($row['total_amount'] ?? 0.0),
            ];
        } catch (Throwable $e) {
            return ['unpaid_count' => 0, 'total_unpaid_amount' => 0.0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get orders currently in process and break down statuses including ready and out_for_delivery.
     */
    public function getProcessingOrders(int $userId, string $role, ?int $storeId = null): array
    {
        if (!$this->db) {
            return [
                'processing_count' => 0,
                'wash_process_count' => 0,
                'ready_count' => 0,
                'delivering_count' => 0,
                'ready_or_delivering_count' => 0
            ];
        }

        $conditions = [
            "o.status IN ('pending', 'assigned_to_courier', 'courier_on_the_way', 'picked_up', 'arrived_at_store', 'weighed', 'waiting_payment', 'paid', 'processing', 'ready', 'out_for_delivery')"
        ];
        $params = [];

        $this->applyRoleScope($conditions, $params, $userId, $role, $storeId);

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT 
                    COUNT(*) as total_active,
                    SUM(CASE WHEN o.status = 'ready' THEN 1 ELSE 0 END) as ready_count,
                    SUM(CASE WHEN o.status = 'out_for_delivery' THEN 1 ELSE 0 END) as delivering_count,
                    SUM(CASE WHEN o.status IN ('ready', 'out_for_delivery') THEN 1 ELSE 0 END) as ready_or_delivering_count,
                    SUM(CASE WHEN o.status NOT IN ('ready', 'out_for_delivery') THEN 1 ELSE 0 END) as wash_process_count
                FROM orders o 
                WHERE {$whereClause}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'processing_count' => (int)($row['total_active'] ?? 0),
                'wash_process_count' => (int)($row['wash_process_count'] ?? 0),
                'ready_count' => (int)($row['ready_count'] ?? 0),
                'delivering_count' => (int)($row['delivering_count'] ?? 0),
                'ready_or_delivering_count' => (int)($row['ready_or_delivering_count'] ?? 0),
            ];
        } catch (Throwable $e) {
            return [
                'processing_count' => 0,
                'wash_process_count' => 0,
                'ready_count' => 0,
                'delivering_count' => 0,
                'ready_or_delivering_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get refund statistics (pending, completed, and completed today/month).
     */
    public function getRefundsSummary(int $userId, string $role, ?int $storeId = null): array
    {
        [$startDate, $endDate] = self::getTodayRange();
        [$startMonth, $endMonth] = self::getMonthRange();

        if (!$this->db) {
            return [
                'pending_count' => 0,
                'pending_amount' => 0.0,
                'completed_count' => 0,
                'completed_amount' => 0.0,
                'completed_today_count' => 0,
                'completed_today_amount' => 0.0,
                'completed_month_count' => 0,
                'completed_month_amount' => 0.0,
                'total_count' => 0,
                'total_amount' => 0.0
            ];
        }

        $conditions = [];
        $params = [
            'start_today_c' => $startDate,
            'end_today_c' => $endDate,
            'start_today_a' => $startDate,
            'end_today_a' => $endDate,
            'start_month_c' => $startMonth,
            'end_month_c' => $endMonth,
            'start_month_a' => $startMonth,
            'end_month_a' => $endMonth,
        ];

        if (in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            if ($storeId !== null && $storeId > 0) {
                $conditions[] = "o.store_id = :sid";
                $params['sid'] = $storeId;
            } else {
                return [
                    'pending_count' => 0,
                    'pending_amount' => 0.0,
                    'completed_count' => 0,
                    'completed_amount' => 0.0,
                    'completed_today_count' => 0,
                    'completed_today_amount' => 0.0,
                    'completed_month_count' => 0,
                    'completed_month_amount' => 0.0,
                    'total_count' => 0,
                    'total_amount' => 0.0,
                    'no_store' => true
                ];
            }
        } elseif ($role === Auth::ROLE_CUSTOMER) {
            $conditions[] = "o.customer_id = :cid";
            $params['cid'] = $userId;
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";
        $sql = "SELECT 
                    SUM(CASE WHEN r.status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending_count,
                    COALESCE(SUM(CASE WHEN r.status IN ('pending', 'processing') THEN r.amount ELSE 0 END), 0) as pending_amount,
                    SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.amount ELSE 0 END), 0) as completed_amount,
                    SUM(CASE WHEN r.status = 'completed' AND r.completed_at >= :start_today_c AND r.completed_at < :end_today_c THEN 1 ELSE 0 END) as completed_today_count,
                    COALESCE(SUM(CASE WHEN r.status = 'completed' AND r.completed_at >= :start_today_a AND r.completed_at < :end_today_a THEN r.amount ELSE 0 END), 0) as completed_today_amount,
                    SUM(CASE WHEN r.status = 'completed' AND r.completed_at >= :start_month_c AND r.completed_at < :end_month_c THEN 1 ELSE 0 END) as completed_month_count,
                    COALESCE(SUM(CASE WHEN r.status = 'completed' AND r.completed_at >= :start_month_a AND r.completed_at < :end_month_a THEN r.amount ELSE 0 END), 0) as completed_month_amount,
                    COUNT(r.id) as total_count,
                    COALESCE(SUM(r.amount), 0) as total_amount
                FROM refunds r
                JOIN orders o ON o.id = r.order_id
                {$whereClause}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'pending_count' => (int)($row['pending_count'] ?? 0),
                'pending_amount' => (float)($row['pending_amount'] ?? 0.0),
                'completed_count' => (int)($row['completed_count'] ?? 0),
                'completed_amount' => (float)($row['completed_amount'] ?? 0.0),
                'completed_today_count' => (int)($row['completed_today_count'] ?? 0),
                'completed_today_amount' => (float)($row['completed_today_amount'] ?? 0.0),
                'completed_month_count' => (int)($row['completed_month_count'] ?? 0),
                'completed_month_amount' => (float)($row['completed_month_amount'] ?? 0.0),
                'total_count' => (int)($row['total_count'] ?? 0),
                'total_amount' => (float)($row['total_amount'] ?? 0.0),
            ];
        } catch (Throwable $e) {
            return [
                'pending_count' => 0,
                'pending_amount' => 0.0,
                'completed_count' => 0,
                'completed_amount' => 0.0,
                'completed_today_count' => 0,
                'completed_today_amount' => 0.0,
                'completed_month_count' => 0,
                'completed_month_amount' => 0.0,
                'total_count' => 0,
                'total_amount' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get net revenue after deducting completed refunds.
     * 
     * FORMULA & ANTI-DOUBLE DEDUCTION RATIONALE:
     * In this application, when a refund is initiated/completed, orders.payment_status is updated to 'refunded'.
     * If gross revenue were calculated using only orders WHERE payment_status = 'paid', refunded orders
     * would already be excluded from the gross revenue. Subtracting completed refunds from that figure
     * would deduct the refund value TWICE (once by exclusion, and once by subtraction).
     *
     * To ensure mathematically correct accounting:
     * 1. Gross Revenue = Total value of all orders that were successfully paid (payment_status IN ('paid', 'refunded')).
     * 2. Completed Refunds = Total completed refunds (refunds WHERE status = 'completed').
     *    Pending or processing refunds are NOT deducted because funds have not left the account.
     * 3. Net Revenue = max(0.0, Gross Revenue - Completed Refunds).
     *
     * Supports both partial refunds and full refunds transparently.
     */
    public function getNetRevenue(int $userId, string $role, ?int $storeId = null): array
    {
        if (!$this->db) {
            return [
                'gross_revenue' => 0.0,
                'refunded_amount' => 0.0,
                'net_revenue' => 0.0,
                'formula' => 'Net Revenue = Total Gross Paid Orders (paid + refunded) - Total Completed Refunds',
            ];
        }

        // 1. Gross revenue (total paid orders including those later refunded)
        $conditionsOrder = ["o.payment_status IN ('paid', 'refunded')"];
        $paramsOrder = [];
        $this->applyRoleScope($conditionsOrder, $paramsOrder, $userId, $role, $storeId);
        $whereOrder = implode(' AND ', $conditionsOrder);

        $grossRevenue = 0.0;
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE {$whereOrder}");
            $stmt->execute($paramsOrder);
            $grossRevenue = (float)$stmt->fetchColumn();
        } catch (Throwable $e) {}

        // 2. Refunded amount (completed refunds only)
        $refundsData = $this->getRefundsSummary($userId, $role, $storeId);
        $refundedAmount = (float)($refundsData['completed_amount'] ?? 0.0);
        $netRevenue = max(0.0, $grossRevenue - $refundedAmount);

        // Period-specific net revenue (today and this month)
        $revToday = $this->getRevenueToday($userId, $role, $storeId);
        $grossToday = (float)($revToday['revenue'] ?? 0.0);
        $refundToday = (float)($refundsData['completed_today_amount'] ?? 0.0);
        $netToday = max(0.0, $grossToday - $refundToday);

        $revMonth = $this->getRevenueThisMonth($userId, $role, $storeId);
        $grossMonth = (float)($revMonth['revenue'] ?? 0.0);
        $refundMonth = (float)($refundsData['completed_month_amount'] ?? 0.0);
        $netMonth = max(0.0, $grossMonth - $refundMonth);

        return [
            'gross_revenue' => $grossRevenue,
            'refunded_amount' => $refundedAmount,
            'net_revenue' => $netRevenue,
            'formula' => 'Net Revenue = Total Gross Paid Orders (paid + refunded) - Total Completed Refunds',
            'today' => [
                'gross_received' => $grossToday,
                'refunded_completed' => $refundToday,
                'net' => $netToday,
            ],
            'this_month' => [
                'gross_received' => $grossMonth,
                'refunded_completed' => $refundMonth,
                'net' => $netMonth,
            ]
        ];
    }

    /**
     * Get active customers and stores (Platform Owner/Admin only).
     */
    public function getActiveUsersAndStores(string $role): array
    {
        if (!in_array($role, [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true) || !$this->db) {
            return [
                'active_customers' => 0,
                'active_stores' => 0,
                'total_stores' => 0,
                'active_couriers' => 0
            ];
        }

        try {
            $customerStmt = $this->db->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer' AND u.is_active = 1");
            $activeCustomers = (int)$customerStmt->fetchColumn();

            $storeActiveStmt = $this->db->query("SELECT COUNT(*) FROM stores WHERE is_active = 1");
            $activeStores = (int)$storeActiveStmt->fetchColumn();

            $storeTotalStmt = $this->db->query("SELECT COUNT(*) FROM stores");
            $totalStores = (int)$storeTotalStmt->fetchColumn();

            $courierStmt = $this->db->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'kurir' AND u.is_active = 1");
            $activeCouriers = (int)$courierStmt->fetchColumn();

            return [
                'active_customers' => $activeCustomers,
                'active_stores' => $activeStores,
                'total_stores' => $totalStores,
                'active_couriers' => $activeCouriers
            ];
        } catch (Throwable $e) {
            return [
                'active_customers' => 0,
                'active_stores' => 0,
                'total_stores' => 0,
                'active_couriers' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get courier tasks (pickup & delivery).
     */
    public function getCourierTasks(int $courierId): array
    {
        if (!$this->db || $courierId <= 0) {
            return [
                'pickup_tasks' => 0,
                'delivery_tasks' => 0,
                'delivered_today' => 0,
                'total_delivered' => 0
            ];
        }

        [$startDate, $endDate] = self::getTodayRange();

        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    SUM(CASE WHEN o.status IN ('assigned_to_courier', 'courier_on_the_way') THEN 1 ELSE 0 END) as pickup_tasks,
                    SUM(CASE WHEN o.status = 'out_for_delivery' THEN 1 ELSE 0 END) as delivery_tasks,
                    SUM(CASE WHEN o.status = 'delivered' AND o.updated_at >= :start_date AND o.updated_at < :end_date THEN 1 ELSE 0 END) as delivered_today,
                    SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as total_delivered
                 FROM orders o 
                 WHERE o.courier_id = :cid"
            );
            $stmt->execute([
                'cid' => $courierId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'pickup_tasks' => (int)($row['pickup_tasks'] ?? 0),
                'delivery_tasks' => (int)($row['delivery_tasks'] ?? 0),
                'delivered_today' => (int)($row['delivered_today'] ?? 0),
                'total_delivered' => (int)($row['total_delivered'] ?? 0),
            ];
        } catch (Throwable $e) {
            return [
                'pickup_tasks' => 0,
                'delivery_tasks' => 0,
                'delivered_today' => 0,
                'total_delivered' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Collect all metrics relevant to the given user and role into a structured array.
     */
    public function getAllMetricsForRole(int $userId, string $role): array
    {
        $store = null;
        $storeId = null;

        if (in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            $store = $this->resolveUserStore($userId);
            $storeId = $store ? (int)$store['id'] : null;
        }

        $ordersToday = $this->getOrdersToday($userId, $role, $storeId);
        $revenueToday = $this->getRevenueToday($userId, $role, $storeId);
        $revenueMonth = $this->getRevenueThisMonth($userId, $role, $storeId);
        $unpaid = $this->getUnpaidOrders($userId, $role, $storeId);
        $processing = $this->getProcessingOrders($userId, $role, $storeId);
        $refunds = $this->getRefundsSummary($userId, $role, $storeId);
        $netRevenue = $this->getNetRevenue($userId, $role, $storeId);

        $platformStats = [];
        if (in_array($role, [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            $platformStats = $this->getActiveUsersAndStores($role);
        }

        $courierStats = [];
        if ($role === Auth::ROLE_KURIR) {
            $courierStats = $this->getCourierTasks($userId);
        }

        return [
            'role' => $role,
            'user_id' => $userId,
            'store_id' => $storeId,
            'store_name' => $store['name'] ?? null,
            'orders_today' => $ordersToday,
            'revenue_today' => $revenueToday,
            'revenue_this_month' => $revenueMonth,
            'unpaid_orders' => $unpaid,
            'processing_orders' => $processing,
            'refunds' => $refunds,
            'net_revenue' => $netRevenue,
            'platform_stats' => $platformStats,
            'courier_stats' => $courierStats,
        ];
    }

    /**
     * Format metrics as a structured context block to inject into system prompt.
     */
    public function formatMetricsContext(array $metrics, string $role, ?string $storeName = null): string
    {
        $ot = $metrics['orders_today'] ?? [];
        $rt = $metrics['revenue_today'] ?? [];
        $rm = $metrics['revenue_this_month'] ?? [];
        $up = $metrics['unpaid_orders'] ?? [];
        $pr = $metrics['processing_orders'] ?? [];
        $rf = $metrics['refunds'] ?? [];
        $nr = $metrics['net_revenue'] ?? [];

        $revTodayFormatted = 'Rp ' . number_format((float)($rt['revenue'] ?? 0), 0, ',', '.');
        $ordRevTodayFormatted = 'Rp ' . number_format((float)($ot['paid_orders_amount'] ?? 0), 0, ',', '.');
        $revMonthFormatted = 'Rp ' . number_format((float)($rm['revenue'] ?? 0), 0, ',', '.');
        $netRevFormatted = 'Rp ' . number_format((float)($nr['net_revenue'] ?? 0), 0, ',', '.');
        $unpaidAmountFormatted = 'Rp ' . number_format((float)($up['total_unpaid_amount'] ?? 0), 0, ',', '.');
        $refundPendingFormatted = 'Rp ' . number_format((float)($rf['pending_amount'] ?? 0), 0, ',', '.');
        $refundCompletedFormatted = 'Rp ' . number_format((float)($rf['completed_amount'] ?? 0), 0, ',', '.');

        $out = "\n\n[BUSINESS_METRICS_START]\n";
        $out .= "DATA AKTUAL DATABASE SISTEM (Hanya gunakan angka-angka ini saat pengguna bertanya tentang data/statistik bisnis, JANGAN mengarang angka lain):\n";

        if (in_array($role, [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            $ps = $metrics['platform_stats'] ?? [];
            $out .= "CAKUPAN: Seluruh Platform LaundryKu (Owner Platform)\n";
            $out .= "- Pesanan Hari Ini: {$ot['total_orders']} pesanan (Pending: {$ot['pending_orders']}, Sedang Diproses: {$ot['processing_orders']}, Selesai: {$ot['delivered_orders']}, Batal: {$ot['cancelled_orders']})\n";
            $out .= "- Nilai Pesanan Hari Ini: {$ordRevTodayFormatted} ({$ot['paid_orders_count']} pesanan dibuat hari ini terbayar)\n";
            $out .= "- Omzet Terbayar Hari Ini: {$revTodayFormatted} ({$rt['paid_orders_count']} transaksi terbayar)\n";
            $out .= "- Omzet Terbayar Bulan Ini: {$revMonthFormatted} ({$rm['paid_orders_count']} transaksi terbayar)\n";
            $out .= "- Pesanan Belum Dibayar: {$up['unpaid_count']} pesanan (Total Nilai: {$unpaidAmountFormatted})\n";
            $out .= "- Pesanan Sedang Diproses: {$pr['processing_count']} pesanan (Dalam Proses Cuci: {$pr['wash_process_count']}, Siap Diantar: {$pr['ready_count']}, Sedang Diantar: {$pr['delivering_count']})\n";
            $out .= "- Refund Menunggu / Diproses: {$rf['pending_count']} refund ({$refundPendingFormatted})\n";
            $out .= "- Refund Selesai: {$rf['completed_count']} refund ({$refundCompletedFormatted})\n";
            $out .= "- Pendapatan Bersih (Net Revenue): {$netRevFormatted} (Setelah dipotong refund selesai)\n";
            $out .= "- Total Pelanggan Aktif: {$ps['active_customers']} pelanggan\n";
            $out .= "- Toko Laundry Aktif: {$ps['active_stores']} toko (Total Terdaftar: {$ps['total_stores']} toko)\n";
            $out .= "- Kurir Aktif: {$ps['active_couriers']} kurir\n";
        } elseif (in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            $tokoLabel = !empty($storeName) ? $storeName : ($metrics['store_name'] ?? 'Toko Anda');
            $readyOrDelivering = $pr['ready_or_delivering_count'] ?? ($pr['ready_count'] + $pr['delivering_count']);
            $out .= "CAKUPAN TOKO: {$tokoLabel} (Khusus Toko Ini Saja)\n";
            $out .= "- Pesanan Masuk Hari Ini: {$ot['total_orders']} pesanan (Pending: {$ot['pending_orders']}, Diproses: {$ot['processing_orders']}, Selesai: {$ot['delivered_orders']})\n";
            $out .= "- Nilai Pesanan Toko Hari Ini: {$ordRevTodayFormatted} ({$ot['paid_orders_count']} pesanan dibuat hari ini terbayar)\n";
            $out .= "- Omzet Toko Hari Ini: {$revTodayFormatted} ({$rt['paid_orders_count']} transaksi terbayar)\n";
            $out .= "- Omzet Toko Bulan Ini: {$revMonthFormatted} ({$rm['paid_orders_count']} transaksi terbayar)\n";
            $out .= "- Pesanan Belum Dibayar: {$up['unpaid_count']} pesanan (Nilai: {$unpaidAmountFormatted})\n";
            $out .= "- Pesanan Sedang Dikerjakan: {$pr['processing_count']} pesanan (Siap Diambil/Diantar: {$readyOrDelivering})\n";
            $out .= "- Refund Toko Ini (Menunggu): {$rf['pending_count']} refund ({$refundPendingFormatted})\n";
            $out .= "- Refund Toko Ini (Selesai): {$rf['completed_count']} refund ({$refundCompletedFormatted})\n";
            $out .= "- Pendapatan Bersih Toko: {$netRevFormatted}\n";
        } elseif ($role === Auth::ROLE_KURIR) {
            $cs = $metrics['courier_stats'] ?? [];
            $out .= "CAKUPAN: Tugas Kurir Pengguna Ini\n";
            $out .= "- Tugas Penjemputan (Pickup) Aktif: {$cs['pickup_tasks']} tugas\n";
            $out .= "- Tugas Pengantaran (Delivery) Aktif: {$cs['delivery_tasks']} tugas\n";
            $out .= "- Tugas Selesai Hari Ini: {$cs['delivered_today']} paket\n";
            $out .= "- Total Paket Berhasil Diantar: {$cs['total_delivered']} paket\n";
        } elseif ($role === Auth::ROLE_CUSTOMER) {
            $out .= "CAKUPAN: Riwayat Pesanan Customer Ini\n";
            $out .= "- Pesanan Aktif Sedang Diproses: {$pr['processing_count']} pesanan\n";
            $out .= "- Pesanan Belum Dibayar: {$up['unpaid_count']} pesanan ({$unpaidAmountFormatted})\n";
            $out .= "- Total Pesanan Dibuat Hari Ini: {$ot['total_orders']} pesanan\n";
        }

        $out .= "[BUSINESS_METRICS_END]\n";
        return $out;
    }

    /**
     * Detects specific business query intent from user's message.
     */
    public function detectQueryIntent(string $message): ?string
    {
        $clean = mb_strtolower(trim($message));
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);
        $clean = trim(preg_replace('/\s+/u', ' ', $clean));

        // 1. Pesanan hari ini
        if (preg_match('/(pesanan|order).*hari ini/i', $clean) || preg_match('/hari ini.*(pesanan|order)/i', $clean)) {
            return 'orders_today';
        }

        // 2. Omzet / pembayaran hari ini
        if (preg_match('/(omzet|omset|pendapatan|penghasilan|pembayaran|dana masuk).*hari ini/i', $clean) || 
            preg_match('/hari ini.*(omzet|omset|pendapatan|pembayaran|dana masuk)/i', $clean)) {
            return 'revenue_today';
        }

        // 3. Omzet / pembayaran bulan ini
        if (preg_match('/(omzet|omset|pendapatan|penghasilan|pembayaran|dana masuk).*(bulan ini|sebulan)/i', $clean) || 
            preg_match('/(bulan ini).*?(omzet|omset|pendapatan|pembayaran|dana masuk)/i', $clean)) {
            return 'revenue_month';
        }

        // 4. Belum dibayar / unpaid
        if (preg_match('/(belum dibayar|belum bayar|menunggu pembayaran|unpaid)/i', $clean)) {
            return 'unpaid_orders';
        }

        // 5. Sedang diproses / dikerjakan
        if (preg_match('/(sedang diproses|dalam proses|perlu diproses|sedang dicuci|antrian|sedang dikerjakan)/i', $clean)) {
            return 'processing_orders';
        }

        // 6. Pendapatan bersih / Net revenue
        if (preg_match('/(pendapatan bersih|omzet bersih|omset bersih|profit bersih|keuntungan bersih|setelah refund)/i', $clean)) {
            return 'net_revenue';
        }

        // 7. Refund
        if (preg_match('/(refund|pengembalian dana|uang kembali)/i', $clean) && !preg_match('/(minta|ajukan|batalkan|kembalikan)/i', $clean)) {
            return 'refunds';
        }

        // 8. Pelanggan dan Toko aktif
        if (preg_match('/(pelanggan|customer|user|pengguna).*?(aktif|terdaftar|jumlah|berapa)/i', $clean) ||
            preg_match('/(toko|outlet|mitra).*?(aktif|terdaftar|jumlah|berapa)/i', $clean)) {
            return 'users_stores';
        }

        // 9. Tugas kurir (pickup / antar)
        if (preg_match('/(tugas|pickup|antar|jemput|delivery).*?(kurir|saya|hari ini)/i', $clean) ||
            preg_match('/(tugas saya|tugas kurir)/i', $clean)) {
            return 'courier_tasks';
        }

        // 10. Ringkasan umum bisnis / statistik
        if (preg_match('/(ringkasan|laporan|statistik|performa|dashboard|rekap).*?(bisnis|toko|hari ini|bulan ini|penjualan)/i', $clean)) {
            return 'metrics_overview';
        }

        return null;
    }

    /**
     * Internal helper to apply role-based scope to queries.
     */
    private function applyRoleScope(array &$conditions, array &$params, int $userId, string $role, ?int $storeId): void
    {
        if (in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            if ($storeId !== null && $storeId > 0) {
                $conditions[] = "o.store_id = :sid";
                $params['sid'] = $storeId;
            } else {
                // If user belongs to store role but has no store associated, force 0 matches
                $conditions[] = "1 = 0";
            }
        } elseif ($role === Auth::ROLE_KURIR) {
            $conditions[] = "o.courier_id = :cid";
            $params['cid'] = $userId;
        } elseif ($role === Auth::ROLE_CUSTOMER) {
            $conditions[] = "o.customer_id = :cid";
            $params['cid'] = $userId;
        }
        // Owner and Admin Owner have global scope (no extra conditions)
    }
}
