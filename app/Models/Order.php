<?php

namespace App\Models;

use App\Core\Model;
use App\Services\WhatsApp\WhatsAppNotificationService;

class Order extends Model
{
    protected string $table = 'orders';

    public const STATUS_PENDING              = 'pending';
    public const STATUS_ASSIGNED_TO_COURIER = 'assigned_to_courier';
    public const STATUS_COURIER_ON_THE_WAY  = 'courier_on_the_way';
    public const STATUS_PICKED_UP            = 'picked_up';
    public const STATUS_ARRIVED_AT_STORE     = 'arrived_at_store';
    public const STATUS_WEIGHED              = 'weighed';
    public const STATUS_WAITING_PAYMENT      = 'waiting_payment';
    public const STATUS_PAID                 = 'paid';
    public const STATUS_PROCESSING           = 'processing';
    public const STATUS_READY                = 'ready';
    public const STATUS_OUT_FOR_DELIVERY     = 'out_for_delivery';
    public const STATUS_DELIVERED            = 'delivered';
    public const STATUS_CANCELLED            = 'cancelled';

    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [
            self::STATUS_ASSIGNED_TO_COURIER,
            self::STATUS_CANCELLED
        ],
        self::STATUS_ASSIGNED_TO_COURIER => [
            self::STATUS_COURIER_ON_THE_WAY,
            self::STATUS_CANCELLED
        ],
        self::STATUS_COURIER_ON_THE_WAY => [
            self::STATUS_PICKED_UP,
            self::STATUS_CANCELLED
        ],
        self::STATUS_PICKED_UP => [
            self::STATUS_ARRIVED_AT_STORE,
            self::STATUS_CANCELLED
        ],
        self::STATUS_ARRIVED_AT_STORE => [
            self::STATUS_WEIGHED,
            self::STATUS_CANCELLED
        ],
        self::STATUS_WEIGHED => [
            self::STATUS_WAITING_PAYMENT,
            self::STATUS_PROCESSING,
            self::STATUS_CANCELLED
        ],
        self::STATUS_WAITING_PAYMENT => [
            self::STATUS_PAID,
            self::STATUS_PROCESSING,
            self::STATUS_CANCELLED
        ],
        self::STATUS_PAID => [
            self::STATUS_PROCESSING,
            self::STATUS_CANCELLED
        ],
        self::STATUS_PROCESSING => [
            self::STATUS_READY,
            self::STATUS_CANCELLED
        ],
        self::STATUS_READY => [
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED
        ],
        self::STATUS_OUT_FOR_DELIVERY => [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED
        ],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => []
    ];

    public static function isValidTransition(string $currentStatus, string $newStatus): bool
    {
        if ($currentStatus === $newStatus) {
            return true;
        }
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        return in_array($newStatus, $allowed, true);
    }

    public static function getAllowedTransitions(string $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING              => ['label' => 'Pending (Pesanan dibuat)', 'badge' => 'warning'],
            self::STATUS_ASSIGNED_TO_COURIER => ['label' => 'Kurir Ditugaskan', 'badge' => 'info'],
            self::STATUS_COURIER_ON_THE_WAY  => ['label' => 'Kurir Menuju Lokasi', 'badge' => 'info'],
            self::STATUS_PICKED_UP            => ['label' => 'Cucian Dijemput Kurir', 'badge' => 'primary'],
            self::STATUS_ARRIVED_AT_STORE     => ['label' => 'Tiba di Toko Laundry', 'badge' => 'primary'],
            self::STATUS_WEIGHED              => ['label' => 'Ditimbang & Dihitung Toko', 'badge' => 'warning'],
            self::STATUS_WAITING_PAYMENT      => ['label' => 'Menunggu Pembayaran', 'badge' => 'warning'],
            self::STATUS_PAID                 => ['label' => 'Pembayaran Diterima', 'badge' => 'success'],
            self::STATUS_PROCESSING           => ['label' => 'Sedang Dicuci / Diproses', 'badge' => 'primary'],
            self::STATUS_READY                => ['label' => 'Cucian Bersih & Siap', 'badge' => 'info'],
            self::STATUS_OUT_FOR_DELIVERY     => ['label' => 'Kurir Mengantar ke Rumah', 'badge' => 'info'],
            self::STATUS_DELIVERED            => ['label' => 'Selesai (Diterima)', 'badge' => 'success'],
            self::STATUS_CANCELLED            => ['label' => 'Dibatalkan', 'badge' => 'danger'],
        ];
    }

    public function createOrder(array $data): ?int
    {
        if (!$this->db) return 1;

        $orderCode = 'ORD-' . date('Ymd') . '-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT);
        $data['order_code'] = $orderCode;

        $orderId = $this->create($data);

        $this->addStatusHistory($orderId, self::STATUS_PENDING, 'Pesanan dibuat oleh Customer (Estimasi berat: ' . $data['estimated_weight'] . ' kg)', $data['customer_id']);

        // Notification: Order Created
        Notification::create(
            (int)$data['customer_id'],
            "Pesanan Dibuat #{$orderCode}",
            "Pesanan laundry Anda berhasil dibuat dengan estimasi total Rp " . number_format((float)($data['estimated_amount'] ?? $data['total_amount'] ?? 0), 0, ',', '.') . ". Menunggu penugasan kurir penjemputan.",
            Notification::TYPE_INFO
        );

        // WhatsApp Notification: Customer & Store Owner
        try {
            $waService = new WhatsAppNotificationService();
            $orderDetail = $this->findOrderDetail($orderId);
            if ($orderDetail) {
                $waService->sendCustomerNotification((int)$data['customer_id'], 'order_created', $orderDetail);
                if (!empty($data['store_id'])) {
                    $waService->sendStoreOwnerNotification((int)$data['store_id'], 'store_new_order', $orderDetail);
                }
            }
        } catch (\Throwable $e) {
            error_log("[Order::createOrder] WhatsApp dispatch error: " . $e->getMessage());
        }

        return $orderId;
    }

    public function assignCourier(int $orderId, int $courierId, int $assignedBy): bool
    {
        if ($this->db) {
            $stmt = $this->db->prepare("UPDATE orders SET courier_id = :crid, status = :st WHERE id = :oid");
            $stmt->execute([
                'crid' => $courierId,
                'st' => self::STATUS_ASSIGNED_TO_COURIER,
                'oid' => $orderId
            ]);
        }

        $courierUser = (new User())->find($courierId);
        $courierName = $courierUser['name'] ?? 'Kurir';

        $notes = "Kurir " . $courierName . " ditugaskan untuk melakukan penjemputan cucian";
        $this->addStatusHistory($orderId, self::STATUS_ASSIGNED_TO_COURIER, $notes, $assignedBy);

        $order = $this->findOrderDetail($orderId);
        $orderCode = $order['order_code'] ?? "ORD-{$orderId}";
        $customerId = (int)($order['customer_id'] ?? 0);

        // 1. Notification to Customer
        if ($customerId > 0) {
            Notification::create(
                $customerId,
                "Kurir Ditugaskan #{$orderCode}",
                "Kurir {$courierName} telah ditugaskan untuk menjemput cucian Anda.",
                Notification::TYPE_INFO
            );
        }

        // 2. Notification to Courier
        Notification::create(
            $courierId,
            "Tugas Penjemputan Baru #{$orderCode}",
            "Anda mendapat tugas penjemputan cucian baru untuk pesanan #{$orderCode}.",
            Notification::TYPE_PRIMARY
        );

        // WhatsApp Notification: Customer & Courier
        try {
            $waService = new WhatsAppNotificationService();
            if ($customerId > 0) {
                $waService->sendCustomerNotification($customerId, 'order_assigned_to_courier', array_merge($order ?: [], [
                    'courier_name' => $courierName,
                    'courier_phone' => $courierUser['phone'] ?? null,
                ]));
            }
            $waService->sendCourierNotification($courierId, 'courier_task_assigned', array_merge($order ?: [], [
                'courier_name' => $courierName,
            ]));
        } catch (\Throwable $e) {
            error_log("[Order::assignCourier] WhatsApp dispatch error: " . $e->getMessage());
        }

        return true;
    }

    public function getCustomerOrders(int $customerId): array
    {
        if (!$this->db) {
            return [$this->getDemoOrder()];
        }

        $sql = "SELECT o.*, s.name as store_name, sv.name as service_name, a.address_line, a.city, c.name as courier_name, c.phone as courier_phone 
                FROM orders o
                JOIN stores s ON o.store_id = s.id
                JOIN services sv ON o.service_id = sv.id
                JOIN addresses a ON o.address_id = a.id
                LEFT JOIN users c ON o.courier_id = c.id
                WHERE o.customer_id = :cid
                ORDER BY o.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $customerId]);
        return $stmt->fetchAll();
    }

    public function getStoreOrders(int $storeId): array
    {
        if (!$this->db) {
            return [$this->getDemoOrder()];
        }

        $sql = "SELECT o.*, u.name as customer_name, u.phone as customer_phone, sv.name as service_name, a.address_line, c.name as courier_name 
                FROM orders o
                JOIN users u ON o.customer_id = u.id
                JOIN services sv ON o.service_id = sv.id
                JOIN addresses a ON o.address_id = a.id
                LEFT JOIN users c ON o.courier_id = c.id
                WHERE o.store_id = :sid
                ORDER BY o.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $storeId]);
        return $stmt->fetchAll();
    }

    public function getCourierOrders(int $courierId): array
    {
        if (!$this->db) {
            return [$this->getDemoOrder()];
        }

        $sql = "SELECT o.*, u.name as customer_name, u.phone as customer_phone, s.name as store_name, a.address_line, a.detail_address, a.latitude as pickup_lat, a.longitude as pickup_lng 
                FROM orders o
                JOIN users u ON o.customer_id = u.id
                JOIN stores s ON o.store_id = s.id
                JOIN addresses a ON o.address_id = a.id
                WHERE o.courier_id = :crid OR o.status IN ('pending', 'assigned_to_courier', 'ready')
                ORDER BY o.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['crid' => $courierId]);
        return $stmt->fetchAll();
    }

    public function getAllOrders(): array
    {
        if (!$this->db) {
            return [$this->getDemoOrder()];
        }

        $sql = "SELECT o.*, u.name as customer_name, s.name as store_name, sv.name as service_name, c.name as courier_name 
                FROM orders o
                JOIN users u ON o.customer_id = u.id
                JOIN stores s ON o.store_id = s.id
                JOIN services sv ON o.service_id = sv.id
                LEFT JOIN users c ON o.courier_id = c.id
                ORDER BY o.id DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function findOrderDetail(int $orderId): ?array
    {
        if (!$this->db) {
            return $this->getDemoOrder();
        }

        $sql = "SELECT o.*, 
                       u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
                       s.name as store_name, s.address as store_address, s.phone as store_phone,
                       s.latitude as store_lat, s.longitude as store_lng,
                       sv.name as service_name, sv.price as service_price, sv.unit as service_unit,
                       a.title as address_title, a.address_line, a.province, a.city, a.district, a.village, a.rt, a.rw, a.detail_address, a.latitude, a.longitude,
                       c.name as courier_name, c.phone as courier_phone,
                       cl.duty_status as courier_duty_status, cl.latitude as courier_lat, cl.longitude as courier_lng
                FROM orders o
                JOIN users u ON o.customer_id = u.id
                JOIN stores s ON o.store_id = s.id
                JOIN services sv ON o.service_id = sv.id
                JOIN addresses a ON o.address_id = a.id
                LEFT JOIN users c ON o.courier_id = c.id
                LEFT JOIN courier_locations cl ON c.id = cl.courier_id
                WHERE o.id = :oid LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['oid' => $orderId]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function updateWeighing(int $orderId, float $actualWeight, float $additionalFee, float $discount, int $updatedBy): bool
    {
        $order = $this->findOrderDetail($orderId);
        if (!$order) return false;

        $unitPrice = (float) $order['service_price'];
        $subtotal = $actualWeight * $unitPrice;
        $totalAmount = $subtotal + $additionalFee - $discount;
        if ($totalAmount < 0) $totalAmount = 0;

        $data = [
            'actual_weight' => $actualWeight,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'additional_fee' => $additionalFee,
            'discount' => $discount,
            'total_amount' => $totalAmount,
            'status' => self::STATUS_WEIGHED
        ];

        if ($this->db) {
            $stmt = $this->db->prepare("UPDATE orders SET actual_weight = :actual_weight, unit_price = :unit_price, subtotal = :subtotal, additional_fee = :additional_fee, discount = :discount, total_amount = :total_amount, status = :status WHERE id = :id");
            $data['id'] = $orderId;
            $stmt->execute($data);
        }

        $unit = $order['service_unit'] ?? 'kg';
        $notes = sprintf("Penimbangan selesai: %.2f %s. Subtotal: Rp %s, Biaya Tambahan: Rp %s, Diskon: Rp %s. Total Final: Rp %s",
            $actualWeight, $unit,
            number_format($subtotal, 0, ',', '.'),
            number_format($additionalFee, 0, ',', '.'),
            number_format($discount, 0, ',', '.'),
            number_format($totalAmount, 0, ',', '.')
        );

        $this->addStatusHistory($orderId, self::STATUS_WEIGHED, $notes, $updatedBy);

        // Notification: Weighed
        $customerId = (int)($order['customer_id'] ?? 0);
        $orderCode = $order['order_code'] ?? "ORD-{$orderId}";
        if ($customerId > 0) {
            Notification::create(
                $customerId,
                "Penimbangan Selesai #{$orderCode}",
                "Cucian Anda telah ditimbang: {$actualWeight} {$unit}. Total biaya final: Rp " . number_format($totalAmount, 0, ',', '.') . ".",
                Notification::TYPE_WARNING
            );

            // WhatsApp Notification: Weighed
            try {
                $waService = new WhatsAppNotificationService();
                $waService->sendCustomerNotification($customerId, 'order_weighed', array_merge($order, [
                    'actual_weight' => $actualWeight,
                    'total_amount' => $totalAmount,
                    'order_code' => $orderCode,
                ]));
            } catch (\Throwable $e) {
                error_log("[Order::updateWeighing] WhatsApp dispatch error: " . $e->getMessage());
            }
        }

        return true;
    }

    public function updateStatus(int $orderId, string $newStatus, ?string $notes = null, ?int $createdBy = null, bool $enforceTransition = true): bool
    {
        if ($enforceTransition) {
            $currentOrder = $this->find($orderId);
            if ($currentOrder) {
                $currentStatus = $currentOrder['status'] ?? self::STATUS_PENDING;
                if (!self::isValidTransition($currentStatus, $newStatus)) {
                    $currLabel = self::getStatusLabels()[$currentStatus]['label'] ?? $currentStatus;
                    $newLabel = self::getStatusLabels()[$newStatus]['label'] ?? $newStatus;
                    throw new \InvalidArgumentException("Transisi status dari '{$currLabel}' ke '{$newLabel}' tidak diizinkan oleh sistem.");
                }
            }
        }

        if ($this->db) {
            $stmt = $this->db->prepare("UPDATE orders SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $newStatus, 'id' => $orderId]);
        }

        $statusLabel = self::getStatusLabels()[$newStatus]['label'] ?? $newStatus;
        $logNotes = $notes ?: "Status laundry diperbarui menjadi: " . $statusLabel;
        $this->addStatusHistory($orderId, $newStatus, $logNotes, $createdBy);

        // Notification: Status specific triggers for customer
        $order = $this->findOrderDetail($orderId);
        if ($order && !empty($order['customer_id'])) {
            $customerId = (int)$order['customer_id'];
            $orderCode = $order['order_code'] ?? "ORD-{$orderId}";

            switch ($newStatus) {
                case self::STATUS_COURIER_ON_THE_WAY:
                    Notification::create(
                        $customerId,
                        "Kurir Menuju Lokasi #{$orderCode}",
                        "Kurir sedang dalam perjalanan menuju alamat penjemputan cucian Anda.",
                        Notification::TYPE_INFO
                    );
                    break;

                case self::STATUS_PICKED_UP:
                    Notification::create(
                        $customerId,
                        "Cucian Dijemput #{$orderCode}",
                        "Cucian Anda telah berhasil dijemput oleh kurir dan sedang dibawa menuju toko laundry.",
                        Notification::TYPE_INFO
                    );
                    break;

                case self::STATUS_ARRIVED_AT_STORE:
                    Notification::create(
                        $customerId,
                        "Tiba di Toko Laundry #{$orderCode}",
                        "Cucian Anda telah tiba di toko laundry dan menunggu proses penimbangan.",
                        Notification::TYPE_INFO
                    );
                    break;

                case self::STATUS_WAITING_PAYMENT:
                    $total = (float)($order['total_amount'] ?? $order['estimated_amount'] ?? 0);
                    Notification::create(
                        $customerId,
                        "Menunggu Pembayaran #{$orderCode}",
                        "Tagihan pembayaran online QRIS pesanan #{$orderCode} sebesar Rp " . number_format($total, 0, ',', '.') . " siap dibayar.",
                        Notification::TYPE_WARNING
                    );
                    break;

                case self::STATUS_PROCESSING:
                    Notification::create(
                        $customerId,
                        "Cucian Sedang Diproses #{$orderCode}",
                        "Cucian Anda sedang dalam proses pencucian dan perawatan oleh toko laundry.",
                        Notification::TYPE_PRIMARY
                    );
                    break;

                case self::STATUS_READY:
                    Notification::create(
                        $customerId,
                        "Cucian Selesai & Siap #{$orderCode}",
                        "Cucian Anda telah selesai dicuci dan siap untuk diantar kembali.",
                        Notification::TYPE_SUCCESS
                    );
                    break;

                case self::STATUS_OUT_FOR_DELIVERY:
                    Notification::create(
                        $customerId,
                        "Cucian Sedang Diantar #{$orderCode}",
                        "Kurir sedang dalam perjalanan mengantarkan cucian bersih ke alamat Anda.",
                        Notification::TYPE_INFO
                    );
                    break;

                case self::STATUS_DELIVERED:
                    Notification::create(
                        $customerId,
                        "Pesanan Selesai #{$orderCode}",
                        "Cucian bersih Anda telah berhasil diantar dan diterima. Terima kasih telah menggunakan layanan kami!",
                        Notification::TYPE_SUCCESS
                    );
                    break;

                case self::STATUS_CANCELLED:
                    Notification::create(
                        $customerId,
                        "Pesanan Dibatalkan #{$orderCode}",
                        "Pesanan laundry #{$orderCode} telah dibatalkan.",
                        Notification::TYPE_DANGER
                    );
                    break;
            }

            // WhatsApp Notification: Status specific triggers for customer, store owner, and courier
            try {
                $waService = new WhatsAppNotificationService();
                $storeId = (int)($order['store_id'] ?? 0);
                $courierId = (int)($order['courier_id'] ?? 0);
                $orderData = array_merge($order, [
                    'status_label' => $statusLabel,
                    'notes' => $logNotes,
                    'order_code' => $orderCode,
                ]);

                switch ($newStatus) {
                    case self::STATUS_COURIER_ON_THE_WAY:
                        $waService->sendCustomerNotification($customerId, 'order_courier_on_the_way', $orderData);
                        break;

                    case self::STATUS_PICKED_UP:
                        $waService->sendCustomerNotification($customerId, 'order_picked_up', $orderData);
                        if ($storeId > 0) {
                            $waService->sendStoreOwnerNotification($storeId, 'store_status_changed', $orderData);
                        }
                        break;

                    case self::STATUS_ARRIVED_AT_STORE:
                        $waService->sendCustomerNotification($customerId, 'order_arrived_at_store', $orderData);
                        if ($storeId > 0) {
                            $waService->sendStoreOwnerNotification($storeId, 'store_status_changed', $orderData);
                        }
                        break;

                    case self::STATUS_WAITING_PAYMENT:
                        $waService->sendCustomerNotification($customerId, 'order_waiting_payment', $orderData);
                        break;

                    case self::STATUS_PROCESSING:
                        $waService->sendCustomerNotification($customerId, 'order_processing', $orderData);
                        break;

                    case self::STATUS_READY:
                        $waService->sendCustomerNotification($customerId, 'order_ready', $orderData);
                        if ($courierId > 0) {
                            $waService->sendCourierNotification($courierId, 'courier_order_ready', $orderData);
                        }
                        break;

                    case self::STATUS_OUT_FOR_DELIVERY:
                        $waService->sendCustomerNotification($customerId, 'order_out_for_delivery', $orderData);
                        break;

                    case self::STATUS_DELIVERED:
                        $waService->sendCustomerNotification($customerId, 'order_delivered', $orderData);
                        if ($storeId > 0) {
                            $waService->sendStoreOwnerNotification($storeId, 'store_status_changed', $orderData);
                        }
                        break;

                    case self::STATUS_CANCELLED:
                        $waService->sendCustomerNotification($customerId, 'order_cancelled', $orderData);
                        if ($storeId > 0) {
                            $waService->sendStoreOwnerNotification($storeId, 'store_order_cancelled', $orderData);
                        }
                        if ($courierId > 0) {
                            $waService->sendCourierNotification($courierId, 'courier_task_cancelled', $orderData);
                        }
                        break;
                }
            } catch (\Throwable $e) {
                error_log("[Order::updateStatus] WhatsApp dispatch error: " . $e->getMessage());
            }
        }

        return true;
    }

    public function addStatusHistory(int $orderId, string $status, ?string $notes, ?int $createdBy): void
    {
        if ($this->db) {
            $stmt = $this->db->prepare("INSERT INTO order_status_histories (order_id, status, notes, created_by) VALUES (:oid, :st, :nt, :cb)");
            $stmt->execute([
                'oid' => $orderId,
                'st' => $status,
                'nt' => $notes,
                'cb' => $createdBy
            ]);
        }
    }

    public function getStatusHistory(int $orderId): array
    {
        if (!$this->db) {
            return [
                ['id' => 1, 'order_id' => $orderId, 'status' => 'pending', 'notes' => 'Pesanan dibuat oleh Customer (Estimasi 5 kg)', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
                ['id' => 2, 'order_id' => $orderId, 'status' => 'assigned_to_courier', 'notes' => 'Kurir Agus Express ditugaskan menjemput cucian', 'created_at' => date('Y-m-d H:i:s', strtotime('-1.5 hours'))],
                ['id' => 3, 'order_id' => $orderId, 'status' => 'courier_on_the_way', 'notes' => 'Kurir dalam perjalanan menuju lokasi penjemputan customer', 'created_at' => date('Y-m-d H:i:s', strtotime('-1.2 hours'))],
                ['id' => 4, 'order_id' => $orderId, 'status' => 'picked_up', 'notes' => 'Cucian berhasil dijemput dari lokasi pelanggan', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
                ['id' => 5, 'order_id' => $orderId, 'status' => 'arrived_at_store', 'notes' => 'Cucian tiba di toko CleanMaster Laundry', 'created_at' => date('Y-m-d H:i:s', strtotime('-45 mins'))]
            ];
        }

        $sql = "SELECT h.*, u.name as actor_name 
                FROM order_status_histories h 
                LEFT JOIN users u ON h.created_by = u.id 
                WHERE h.order_id = :oid 
                ORDER BY h.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['oid' => $orderId]);
        return $stmt->fetchAll();
    }

    private function getDemoOrder(): array
    {
        return [
            'id' => 1,
            'order_code' => 'ORD-20260903-001',
            'customer_id' => 6,
            'customer_name' => 'Dewi Lestari',
            'customer_email' => 'customer@laundry.test',
            'customer_phone' => '081234567895',
            'store_id' => 1,
            'store_name' => 'CleanMaster Laundry Express',
            'store_address' => 'Jl. Merdeka No. 45, Jakarta Selatan',
            'store_phone' => '081299887766',
            'store_lat' => '-6.22974650',
            'store_lng' => '106.81644900',
            'service_id' => 1,
            'service_name' => 'Cuci Komplit Reguler',
            'service_price' => '6000.00',
            'service_unit' => 'kg',
            'address_id' => 1,
            'address_title' => 'Rumah Utama',
            'address_line' => 'Jl. Mawar Indah No. 12',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'district' => 'Kebayoran Baru',
            'village' => 'Gandaria Utara',
            'rt' => '003',
            'rw' => '005',
            'detail_address' => 'Rumah pagar hitam sebelah warung',
            'latitude' => '-6.24430000',
            'longitude' => '106.79300000',
            'courier_id' => 5,
            'courier_name' => 'Agus Express',
            'courier_phone' => '081234567894',
            'courier_duty_status' => 'available',
            'courier_lat' => '-6.24430000',
            'courier_lng' => '106.79300000',
            'estimated_weight' => '5.00',
            'estimated_amount' => '30000.00',
            'actual_weight' => '4.80',
            'unit_price' => '6000.00',
            'subtotal' => '28800.00',
            'additional_fee' => '2000.00',
            'discount' => '1000.00',
            'total_amount' => '29800.00',
            'notes' => 'Tolong lipat rapi dan beri wewangi lavender',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'status' => 'assigned_to_courier',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ];
    }

    /**
     * Get counts for ready to pickup and ready to deliver orders for a specific store.
     *
     * @param int $storeId
     * @return array ['ready_pickup' => int, 'ready_delivery' => int, 'total' => int, 'delivering' => int]
     */
    public function getReadyCountsByStore(int $storeId): array
    {
        if (!$this->db) {
            return [
                'ready_pickup'   => 0,
                'ready_delivery' => 0,
                'delivering'     => 0,
                'total'          => 0,
            ];
        }

        $sql = "SELECT 
                    SUM(CASE WHEN o.status = 'ready' AND o.delivery_type = 'pickup' THEN 1 ELSE 0 END) AS ready_pickup,
                    SUM(CASE WHEN (o.status = 'ready' AND o.delivery_type = 'delivery') OR o.status = 'out_for_delivery' THEN 1 ELSE 0 END) AS ready_delivery,
                    SUM(CASE WHEN o.status = 'out_for_delivery' THEN 1 ELSE 0 END) AS delivering,
                    SUM(CASE WHEN o.status = 'ready' OR o.status = 'out_for_delivery' THEN 1 ELSE 0 END) AS total
                FROM orders o
                WHERE o.store_id = :sid";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sid' => $storeId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            return [
                'ready_pickup'   => (int)($row['ready_pickup'] ?? 0),
                'ready_delivery' => (int)($row['ready_delivery'] ?? 0),
                'delivering'     => (int)($row['delivering'] ?? 0),
                'total'          => (int)($row['total'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'ready_pickup'   => 0,
                'ready_delivery' => 0,
                'delivering'     => 0,
                'total'          => 0,
            ];
        }
    }

    /**
     * Get detailed list of ready orders for a store with filters.
     *
     * @param int $storeId
     * @param string|null $tab 'pickup', 'delivery', or 'all'
     * @param array $filters ['search' => string, 'status' => string, 'courier_assigned' => string]
     * @return array
     */
    public function getReadyOrdersByStore(int $storeId, ?string $tab = null, array $filters = []): array
    {
        if (!$this->db) {
            return [];
        }

        $conditions = ["o.store_id = :sid"];
        $params = ['sid' => $storeId];

        if ($tab === 'pickup') {
            $conditions[] = "o.status = 'ready' AND o.delivery_type = 'pickup'";
        } elseif ($tab === 'delivery') {
            $conditions[] = "((o.status = 'ready' AND o.delivery_type = 'delivery') OR o.status = 'out_for_delivery')";
        } else {
            $conditions[] = "(o.status = 'ready' OR o.status = 'out_for_delivery')";
        }

        // Filter: Specific status
        if (!empty($filters['status'])) {
            $conditions[] = "o.status = :status_filter";
            $params['status_filter'] = $filters['status'];
        }

        // Filter: Courier assigned
        if (!empty($filters['courier_assigned'])) {
            if ($filters['courier_assigned'] === 'yes') {
                $conditions[] = "o.courier_id IS NOT NULL";
            } elseif ($filters['courier_assigned'] === 'no') {
                $conditions[] = "o.courier_id IS NULL";
            }
        }

        // Filter: Search keyword (order code, customer name, phone)
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $conditions[] = "(o.order_code LIKE :search1 OR u.name LIKE :search2 OR u.phone LIKE :search3)";
            $params['search1'] = $search;
            $params['search2'] = $search;
            $params['search3'] = $search;
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT o.*,
                       u.name AS customer_name,
                       u.phone AS customer_phone,
                       u.email AS customer_email,
                       s.name AS service_name,
                       s.unit AS service_unit,
                       st.name AS store_name,
                       a.title AS address_title,
                       a.address_line,
                       a.detail_address,
                       a.city,
                       a.latitude AS delivery_lat,
                       a.longitude AS delivery_lng,
                       c.name AS courier_name,
                       c.phone AS courier_phone,
                       cl.duty_status AS courier_duty_status,
                       TIMESTAMPDIFF(HOUR, o.updated_at, NOW()) AS hours_waiting,
                       TIMESTAMPDIFF(DAY, o.updated_at, NOW()) AS days_waiting
                FROM orders o
                JOIN users u ON o.customer_id = u.id
                JOIN services s ON o.service_id = s.id
                JOIN stores st ON o.store_id = st.id
                LEFT JOIN addresses a ON o.address_id = a.id
                LEFT JOIN users c ON o.courier_id = c.id
                LEFT JOIN courier_locations cl ON c.id = cl.courier_id
                WHERE {$whereClause}
                ORDER BY o.updated_at ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log("[Order::getReadyOrdersByStore] Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark an order as picked up by customer at the store outlet.
     * Transitions status from 'ready' -> 'delivered'.
     *
     * @param int $orderId
     * @param int $storeId
     * @param int $userId Admin/Store user performing the action
     * @return array
     */
    public function markAsPickedUp(int $orderId, int $storeId, int $userId): array
    {
        $order = $this->findOrderDetail($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];
        }

        if ((int)$order['store_id'] !== $storeId) {
            return ['success' => false, 'message' => 'Akses ditolak: Pesanan bukan milik toko Anda.'];
        }

        if ($order['status'] !== self::STATUS_READY) {
            return ['success' => false, 'message' => 'Hanya pesanan berstatus Siap (ready) yang dapat ditandai sudah diambil.'];
        }

        if (($order['delivery_type'] ?? 'delivery') !== 'pickup') {
            return ['success' => false, 'message' => 'Pesanan ini dialokasikan untuk diantar kurir, bukan diambil di toko.'];
        }

        try {
            $notes = 'Cucian bersih telah diambil langsung oleh customer di toko laundry';
            $this->updateStatus($orderId, self::STATUS_DELIVERED, $notes, $userId);
            return ['success' => true, 'message' => 'Pesanan #' . $order['order_code'] . ' berhasil ditandai telah diambil oleh customer!'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal mengubah status: ' . $e->getMessage()];
        }
    }

    /**
     * Assign a courier to a ready delivery order.
     *
     * @param int $orderId
     * @param int $storeId
     * @param int $courierId
     * @param int $assignedBy
     * @return array
     */
    public function assignDeliveryCourier(int $orderId, int $storeId, int $courierId, int $assignedBy): array
    {
        $order = $this->findOrderDetail($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];
        }

        if ((int)$order['store_id'] !== $storeId) {
            return ['success' => false, 'message' => 'Akses ditolak: Pesanan bukan milik toko Anda.'];
        }

        if ($order['status'] !== self::STATUS_READY && $order['status'] !== self::STATUS_OUT_FOR_DELIVERY) {
            return ['success' => false, 'message' => 'Kurir pengantaran hanya dapat ditugaskan untuk pesanan siap atau dalam pengantaran.'];
        }

        $courier = (new User())->find($courierId);
        if (!$courier || (int)($courier['role_id'] ?? 0) !== 5) {
            return ['success' => false, 'message' => 'Kurir yang dipilih tidak valid.'];
        }

        try {
            if ($this->db) {
                $stmt = $this->db->prepare("UPDATE orders SET courier_id = :cid, updated_at = NOW() WHERE id = :oid");
                $stmt->execute(['cid' => $courierId, 'oid' => $orderId]);
            }

            $courierName = $courier['name'] ?? 'Kurir';
            $notes = "Kurir {$courierName} ditugaskan untuk mengantar cucian bersih ke alamat customer";
            $this->addStatusHistory($orderId, $order['status'], $notes, $assignedBy);

            // Notify courier
            Notification::create(
                $courierId,
                "Tugas Pengantaran Cucian #{$order['order_code']}",
                "Anda ditugaskan mengantar cucian bersih pesanan #{$order['order_code']} ke {$order['address_line']}.",
                Notification::TYPE_PRIMARY
            );

            return ['success' => true, 'message' => "Kurir {$courierName} berhasil ditugaskan untuk pesanan #{$order['order_code']}!"];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal menugaskan kurir: ' . $e->getMessage()];
        }
    }

    /**
     * Start delivery for a ready order.
     * Transitions status from 'ready' -> 'out_for_delivery'.
     *
     * @param int $orderId
     * @param int $storeId
     * @param int $userId
     * @return array
     */
    public function startDelivery(int $orderId, int $storeId, int $userId): array
    {
        $order = $this->findOrderDetail($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];
        }

        if ((int)$order['store_id'] !== $storeId) {
            return ['success' => false, 'message' => 'Akses ditolak: Pesanan bukan milik toko Anda.'];
        }

        if ($order['status'] !== self::STATUS_READY) {
            return ['success' => false, 'message' => 'Hanya pesanan berstatus Siap (ready) yang dapat diberangkatkan.'];
        }

        if (empty($order['courier_id'])) {
            return ['success' => false, 'message' => 'Tugaskan kurir terlebih dahulu sebelum memulai pengantaran.'];
        }

        try {
            $courierName = $order['courier_name'] ?? 'Kurir';
            $notes = "Kurir {$courierName} berangkat mengantarkan cucian bersih ke alamat customer";
            $this->updateStatus($orderId, self::STATUS_OUT_FOR_DELIVERY, $notes, $userId);
            return ['success' => true, 'message' => 'Pesanan #' . $order['order_code'] . ' kini dalam status Pengantaran ke customer!'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal mengubah status: ' . $e->getMessage()];
        }
    }

    /**
     * Mengambil metrik statistik kartu dashboard Admin Toko secara terpusat:
     * 1. Pesanan Perlu Diproses (status IN pending..processing)
     * 2. Siap Diambil / Diantar (ready pickup vs delivery via getReadyCountsByStore)
     * 3. Selesai Terkirim (status = delivered)
     * 4. OMSET KASIR TERBAYAR (payment_status = paid)
     *
     * @param int $storeId
     * @return array
     */
    public function getAdminTokoCardStats(int $storeId): array
    {
        $default = [
            'need_process'    => 0,
            'ready_pickup'    => 0,
            'ready_delivery'  => 0,
            'ready_total'     => 0,
            'delivered_count' => 0,
            'omset_toko'      => 0.0,
            'omset_tx_count'  => 0,
        ];

        if (!$this->db) {
            return $default;
        }

        try {
            // 1. Pesanan Perlu Diproses
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid AND status IN ('pending', 'assigned_to_courier', 'courier_on_the_way', 'picked_up', 'arrived_at_store', 'weighed', 'waiting_payment', 'paid', 'processing')");
            $stmt->execute(['sid' => $storeId]);
            $needProcess = (int) $stmt->fetchColumn();

            // 2. Siap Diambil / Diantar
            $readyCounts = $this->getReadyCountsByStore($storeId);

            // 3. Selesai Terkirim
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid AND status = 'delivered'");
            $stmt->execute(['sid' => $storeId]);
            $deliveredCount = (int) $stmt->fetchColumn();

            // 4. OMSET KASIR TERBAYAR
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE store_id = :sid AND payment_status = 'paid'");
            $stmt->execute(['sid' => $storeId]);
            $omsetRow = $stmt->fetch(\PDO::FETCH_NUM);
            $omsetToko = (float) ($omsetRow[0] ?? 0.0);
            $omsetTxCount = (int) ($omsetRow[1] ?? 0);

            return [
                'need_process'    => $needProcess,
                'ready_pickup'    => $readyCounts['ready_pickup'],
                'ready_delivery'  => $readyCounts['ready_delivery'],
                'ready_total'     => $readyCounts['total'],
                'delivered_count' => $deliveredCount,
                'omset_toko'      => $omsetToko,
                'omset_tx_count'  => $omsetTxCount,
            ];
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Mengambil metrik statistik kartu dashboard Owner Toko seputar pesanan & omset:
     * 1. Omset Toko (Terbayar) (payment_status = paid)
     * 2. Total Pesanan Toko (all-time)
     *
     * @param int $storeId
     * @return array
     */
    public function getOwnerTokoCardStats(int $storeId): array
    {
        $default = [
            'omset_toko'     => 0.0,
            'omset_tx_count' => 0,
            'total_orders'   => 0,
        ];

        if (!$this->db) {
            return $default;
        }

        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE store_id = :sid AND payment_status = 'paid'");
            $stmt->execute(['sid' => $storeId]);
            $omsetRow = $stmt->fetch(\PDO::FETCH_NUM);

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid");
            $stmt->execute(['sid' => $storeId]);
            $orderCount = (int) $stmt->fetchColumn();

            return [
                'omset_toko'     => (float) ($omsetRow[0] ?? 0.0),
                'omset_tx_count' => (int) ($omsetRow[1] ?? 0),
                'total_orders'   => $orderCount,
            ];
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Mengambil metrik statistik kartu dashboard Owner Platform & Admin Owner:
     * 1. Total Omset Platform (payment_status = paid)
     * 2. Total Pesanan Platform (all-time)
     *
     * @return array
     */
    public function getPlatformCardStats(): array
    {
        $default = [
            'total_omset'    => 0.0,
            'omset_tx_count' => 0,
            'total_orders'   => 0,
        ];

        if (!$this->db) {
            return $default;
        }

        try {
            $omsetRow = $this->db->query("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE payment_status = 'paid'")->fetch(\PDO::FETCH_NUM);
            $totalOrders = (int) $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

            return [
                'total_omset'    => (float) ($omsetRow[0] ?? 0.0),
                'omset_tx_count' => (int) ($omsetRow[1] ?? 0),
                'total_orders'   => $totalOrders,
            ];
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Mengambil rincian jumlah pesanan per status sepanjang masa (all-time).
     *
     * @param int|null $storeId
     * @return array
     */
    public function getOrdersCountPerStatusAllTime(?int $storeId = null): array
    {
        $result = [];
        if (!$this->db) {
            return $result;
        }

        try {
            $params = [];
            $where = '';
            if ($storeId !== null) {
                $where = ' WHERE store_id = :sid';
                $params['sid'] = $storeId;
            }
            $stmt = $this->db->prepare("SELECT status, COUNT(*) as cnt FROM orders{$where} GROUP BY status");
            $stmt->execute($params);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $result[$row['status']] = (int) $row['cnt'];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $result;
    }
}
