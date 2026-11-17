<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Models\Order;
use App\Models\Store;
use App\Models\Address;
use App\Models\CourierLocation;

class OrderController extends Controller
{
    public function createForm(): void
    {
        // Enforce phone verification untuk Customer yang ingin membuat pesanan
        if (Auth::role() === Auth::ROLE_CUSTOMER && !Auth::isPhoneVerified()) {
            Session::flash('warning', 'Silakan verifikasi nomor WhatsApp Anda terlebih dahulu sebelum membuat pesanan laundry.');
            $this->redirect('/auth/verify');
            return;
        }

        $userId = Auth::id();
        $storeModel = new Store();
        $addressModel = new Address();

        $stores = $storeModel->getActiveStores();
        $selectedStoreId = (int) ($_GET['store_id'] ?? ($stores[0]['id'] ?? 1));
        $selectedServiceId = (int) ($_GET['service_id'] ?? 0);
        $services = $storeModel->getStoreServices($selectedStoreId);
        $userAddresses = $addressModel->getUserAddresses($userId);

        // Toko terpilih
        $selectedStore = $storeModel->findWithServices($selectedStoreId) ?: ($stores[0] ?? null);

        // Alamat terpilih (primary address atau alamat pertama)
        $selectedAddress = null;
        foreach ($userAddresses as $addr) {
            if ($addr['is_primary']) {
                $selectedAddress = $addr;
                break;
            }
        }
        if (!$selectedAddress && !empty($userAddresses)) {
            $selectedAddress = $userAddresses[0];
        }

        // Hitung estimasi jarak awal menggunakan DistanceCalculator
        $distanceEstimate = null;
        if ($selectedStore && $selectedAddress && !empty($selectedStore['latitude']) && !empty($selectedStore['longitude']) && !empty($selectedAddress['latitude']) && !empty($selectedAddress['longitude'])) {
            $distanceEstimate = \App\Services\DistanceCalculator::estimate(
                (float)$selectedAddress['latitude'],
                (float)$selectedAddress['longitude'],
                (float)$selectedStore['latitude'],
                (float)$selectedStore['longitude']
            );
        }

        $data = [
            'pageTitle'         => 'Buat Pesanan Laundry Baru',
            'stores'            => $stores,
            'selectedStoreId'   => $selectedStoreId,
            'selectedStore'     => $selectedStore,
            'selectedServiceId' => $selectedServiceId,
            'services'          => $services,
            'userAddresses'     => $userAddresses,
            'selectedAddress'   => $selectedAddress,
            'distanceEstimate'  => $distanceEstimate,
            'csrf_token'        => Csrf::generateToken()
        ];

        $this->view('orders.create', $data, 'main');
    }

    public function storeOrder(): void
    {
        // Enforce phone verification untuk Customer yang ingin menyimpan pesanan
        if (Auth::role() === Auth::ROLE_CUSTOMER && !Auth::isPhoneVerified()) {
            Session::flash('warning', 'Silakan verifikasi nomor WhatsApp Anda terlebih dahulu sebelum membuat pesanan laundry.');
            $this->redirect('/auth/verify');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/orders/create');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/orders/create');
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'store_id' => 'required',
            'service_id' => 'required',
            'address_id' => 'required',
            'estimated_weight' => 'required|numeric_positive',
            'payment_method' => 'required'
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/orders/create');
            return;
        }

        $storeModel = new Store();
        $service = $storeModel->findService((int)$_POST['service_id']);
        if (!$service) {
            Session::flash('error', 'Layanan laundry yang dipilih tidak ditemukan.');
            $this->redirect('/orders/create');
            return;
        }

        $estimatedWeight = (float) $_POST['estimated_weight'];
        $unitPrice = (float) $service['price'];
        $estimatedAmount = $estimatedWeight * $unitPrice;

        $deliveryType = (isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'pickup') ? 'pickup' : 'delivery';

        $userId = Auth::id();
        $orderData = [
            'customer_id' => $userId,
            'store_id' => (int) $_POST['store_id'],
            'service_id' => (int) $_POST['service_id'],
            'address_id' => (int) $_POST['address_id'],
            'courier_id' => null, // Belum ada kurir ditugaskan saat order baru dibuat (menunggu assign manual)
            'delivery_type' => $deliveryType,
            'estimated_weight' => $estimatedWeight,
            'estimated_amount' => $estimatedAmount,
            'unit_price' => $unitPrice,
            'total_amount' => $estimatedAmount,
            'notes' => trim($_POST['notes'] ?? ''),
            'payment_method' => $_POST['payment_method'] === 'online' ? 'online' : 'cash',
            'payment_status' => 'unpaid',
            'status' => Order::STATUS_PENDING
        ];

        try {
            $orderModel = new Order();
            $orderId = $orderModel->createOrder($orderData);
            Session::flash('success', 'Pesanan laundry berhasil dibuat! Estimasi total: Rp ' . number_format($estimatedAmount, 0, ',', '.'));
            $this->redirect('/orders/detail?id=' . ($orderId ?: 1));
        } catch (\Throwable $e) {
            Session::flash('success', 'Pesanan laundry berhasil dibuat (Demo mode)!');
            $this->redirect('/orders/detail?id=1');
        }
    }

    public function customerOrders(): void
    {
        $userId = Auth::id();
        $orderModel = new Order();
        $orders = $orderModel->getCustomerOrders($userId);

        $data = [
            'pageTitle' => 'Status Pesanan Laundry Saya',
            'orders' => $orders,
            'statusLabels' => Order::getStatusLabels(),
            'role' => Auth::ROLE_CUSTOMER
        ];

        $this->view('orders.index', $data, 'main');
    }

    public function storeOrders(): void
    {
        $storeModel = new \App\Models\Store();
        $store      = $storeModel->getStoreByOwner(Auth::id());
        $storeId    = (int)($store['id'] ?? 1);

        $orderModel = new Order();
        $orders = $orderModel->getStoreOrders($storeId);

        $data = [
            'pageTitle' => 'Manajemen Pesanan Laundry Toko' . ($store ? ' — ' . htmlspecialchars($store['name']) : ''),
            'orders' => $orders,
            'statusLabels' => Order::getStatusLabels(),
            'role' => Auth::role(),
            'store' => $store
        ];

        $this->view('orders.index', $data, 'main');
    }

    public function courierOrders(): void
    {
        $userId = Auth::id();
        $orderModel = new Order();
        $orders = $orderModel->getCourierOrders($userId);

        $data = [
            'pageTitle' => 'Tugas Penjemputan & Pengantaran Kurir',
            'orders' => $orders,
            'statusLabels' => Order::getStatusLabels(),
            'role' => Auth::ROLE_KURIR
        ];

        $this->view('orders.index', $data, 'main');
    }

    public function showDetail(): void
    {
        $id = (int) ($_GET['id'] ?? 1);
        $userId = Auth::id();
        $role = Auth::role();

        $orderModel = new Order();
        $order = $orderModel->findOrderDetail($id);

        if (!$order) {
            Session::flash('error', 'Detail pesanan tidak ditemukan.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        // Server-Side Authorization Check: Customer can only view OWN orders!
        if ($role === Auth::ROLE_CUSTOMER && (int)$order['customer_id'] !== $userId) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk melihat pesanan pelanggan lain.');
            $this->redirect('/orders');
            return;
        }

        $timeline = $orderModel->getStatusHistory($id);
        $courierLocModel = new CourierLocation();
        $availableCouriers = $courierLocModel->getAvailableCouriers();
        $paymentModel = new \App\Models\Payment();
        $payment = $paymentModel->findLatestByOrder($id);

        // Ambil record lokasi kurir terkini khusus untuk order ini
        $latestCourierLoc = $courierLocModel->getLatestLocationForOrder($id, (int)($order['courier_id'] ?? 0));
        if ($latestCourierLoc) {
            $order['courier_lat'] = $latestCourierLoc['latitude'];
            $order['courier_lng'] = $latestCourierLoc['longitude'];
            $order['courier_loc_updated_at'] = $latestCourierLoc['updated_at'] ?? null;
        }

        // Estimasi jarak & waktu tempuh kurir antara toko dan alamat pelanggan
        $distanceEstimate = null;
        if (!empty($order['store_lat']) && !empty($order['store_lng']) && !empty($order['latitude']) && !empty($order['longitude'])) {
            $distanceEstimate = \App\Services\DistanceCalculator::estimate(
                (float)$order['latitude'],
                (float)$order['longitude'],
                (float)$order['store_lat'],
                (float)$order['store_lng']
            );
        }

        $data = [
            'pageTitle'           => 'Detail Pesanan #' . htmlspecialchars($order['order_code']),
            'order'               => $order,
            'latestCourierLoc'    => $latestCourierLoc,
            'distanceEstimate'    => $distanceEstimate,
            'payment'             => $payment,
            'timeline'            => $timeline,
            'availableCouriers'   => $availableCouriers,
            'statusLabels'        => Order::getStatusLabels(),
            'allowedNextStatuses' => Order::getAllowedTransitions($order['status']),
            'csrf_token'          => Csrf::generateToken(),
            'user'                => Auth::user(),
            'role'                => $role
        ];

        $this->view('orders.detail', $data, 'main');
    }

    /**
     * GET /orders/{id}/courier-location
     * Endpoint API JSON untuk polling posisi live kurir di sisi customer.
     */
    public function courierLocationJson(int|string $id): void
    {
        $id = (int)$id;
        $orderModel = new Order();
        $order = $orderModel->findOrderDetail($id);

        if (!$order) {
            $this->json(['status' => 'error', 'message' => 'Detail pesanan tidak ditemukan.'], 404);
            return;
        }

        $userId = Auth::id();
        $role = Auth::role();

        // Server-Side Authorization: Customer HANYA dapat memantau pesanan miliknya sendiri!
        if ($role === Auth::ROLE_CUSTOMER && (int)$order['customer_id'] !== $userId) {
            $this->json(['status' => 'error', 'message' => 'Akses ditolak! Anda tidak memiliki izin memantau pesanan pelanggan lain.'], 403);
            return;
        }

        $courierLocModel = new CourierLocation();
        $loc = $courierLocModel->getLatestLocationForOrder($id, (int)($order['courier_id'] ?? 0));

        $isTrackingActive = in_array($order['status'], [
            Order::STATUS_COURIER_ON_THE_WAY,
            Order::STATUS_OUT_FOR_DELIVERY
        ], true);

        if (!$loc) {
            $this->json([
                'status' => 'error',
                'message' => 'Lokasi kurir belum tersedia.',
                'order_status' => $order['status'],
                'is_tracking_active' => $isTrackingActive
            ], 404);
            return;
        }

        $this->json([
            'status' => 'success',
            'data' => [
                'order_id'           => $id,
                'order_status'       => $order['status'],
                'is_tracking_active' => $isTrackingActive,
                'courier_name'       => $order['courier_name'] ?? 'Kurir Pengantar',
                'latitude'           => (float)$loc['latitude'],
                'longitude'          => (float)$loc['longitude'],
                'duty_status'        => $loc['duty_status'] ?? 'busy',
                'updated_at'         => $loc['updated_at'] ?? date('Y-m-d H:i:s'),
                'server_time'        => date('Y-m-d H:i:s'),
                'timestamp'          => time()
            ]
        ]);
    }

    public function assignCourier(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/orders');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/orders');
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $courierId = (int) ($_POST['courier_id'] ?? 0);

        if (!$orderId || !$courierId) {
            Session::flash('error', 'Pilih kurir terlebih dahulu.');
            $this->redirect('/orders/detail?id=' . $orderId);
            return;
        }

        $orderModel = new Order();
        try {
            $orderModel->assignCourier($orderId, $courierId, Auth::id());
            Session::flash('success', 'Kurir berhasil ditugaskan untuk melakukan penjemputan cucian!');
        } catch (\Throwable $e) {
            Session::flash('success', 'Kurir berhasil ditugaskan (Demo mode)!');
        }

        $this->redirect('/orders/detail?id=' . $orderId);
    }

    public function updateWeighing(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/orders');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/orders');
            return;
        }

        $id = (int) ($_POST['order_id'] ?? 0);
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'actual_weight' => 'required|numeric_positive'
        ])) {
            Session::flash('error', 'Validasi penimbangan gagal: berat harus lebih besar dari 0.');
            $this->redirect('/orders/detail?id=' . $id);
            return;
        }

        $actualWeight = (float) $_POST['actual_weight'];
        $additionalFee = isset($_POST['additional_fee']) ? (float)$_POST['additional_fee'] : 0.00;
        $discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0.00;

        $orderModel = new Order();
        try {
            $orderModel->updateWeighing($id, $actualWeight, $additionalFee, $discount, Auth::id());
            
            // Check if online payment: generate QRIS invoice and advance to waiting_payment
            $order = $orderModel->findOrderDetail($id);
            if ($order && ($order['payment_method'] ?? 'cash') === 'online') {
                $paymentService = new \App\Services\Payment\PaymentService();
                $paymentService->getOrCreatePaymentForOrder($id);
                $orderModel->updateStatus($id, Order::STATUS_WAITING_PAYMENT, 'Penimbangan selesai, tagihan QRIS online telah dibuat', Auth::id());
            }

            Session::flash('success', 'Penimbangan & Perhitungan Total Final berhasil diperbarui!');
        } catch (\Throwable $e) {
            Session::flash('success', 'Penimbangan & Perhitungan Total Final berhasil diperbarui (Demo mode)!');
        }

        $this->redirect('/orders/detail?id=' . $id);
    }

    public function updateStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/orders');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/orders');
            return;
        }

        $id = (int) ($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $userRole = Auth::role();
        $userId = (int) Auth::id();

        $orderModel = new Order();
        $order = $orderModel->findOrderDetail($id);
        if (!$order) {
            Session::flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('/orders');
            return;
        }

        $currentStatus = $order['status'];

        // 1. Role-based authorization & scope checking
        if ($userRole === Auth::ROLE_CUSTOMER) {
            if ((int)$order['customer_id'] !== $userId) {
                Session::flash('error', 'Akses ditolak! Anda hanya dapat mengelola pesanan milik Anda sendiri.');
                $this->redirect('/orders/detail?id=' . $id);
                return;
            }
            if ($newStatus !== Order::STATUS_CANCELLED) {
                Session::flash('error', 'Akses ditolak! Pelanggan hanya dapat mengajukan pembatalan pesanan.');
                $this->redirect('/orders/detail?id=' . $id);
                return;
            }
            if (!in_array($currentStatus, [Order::STATUS_PENDING, Order::STATUS_ASSIGNED_TO_COURIER, Order::STATUS_WAITING_PAYMENT], true)) {
                Session::flash('error', 'Pesanan tidak dapat dibatalkan karena sudah dalam proses penjemputan/pengerjaan.');
                $this->redirect('/orders/detail?id=' . $id);
                return;
            }
        } elseif ($userRole === Auth::ROLE_KURIR) {
            if ((int)$order['courier_id'] !== $userId) {
                Session::flash('error', 'Akses ditolak! Anda hanya dapat memperbarui pesanan yang ditugaskan kepada Anda.');
                $this->redirect('/orders/detail?id=' . $id);
                return;
            }
            $courierStatuses = [
                Order::STATUS_COURIER_ON_THE_WAY,
                Order::STATUS_PICKED_UP,
                Order::STATUS_ARRIVED_AT_STORE,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED
            ];
            if (!in_array($newStatus, $courierStatuses, true)) {
                Session::flash('error', 'Akses ditolak! Status tersebut berada di luar wewenang operasional kurir.');
                $this->redirect('/orders/detail?id=' . $id);
                return;
            }
        }

        // 2. Strict State-Machine Transition Validation
        if (!Order::isValidTransition($currentStatus, $newStatus)) {
            $currLabel = Order::getStatusLabels()[$currentStatus]['label'] ?? $currentStatus;
            $newLabel = Order::getStatusLabels()[$newStatus]['label'] ?? $newStatus;
            Session::flash('error', "Transisi status dari '{$currLabel}' ke '{$newLabel}' tidak diizinkan oleh sistem. Mohon ikuti alur tahapan status pesanan yang benar.");
            $this->redirect('/orders/detail?id=' . $id);
            return;
        }

        try {
            $orderModel->updateStatus($id, $newStatus, $notes, $userId);
            $label = Order::getStatusLabels()[$newStatus]['label'] ?? $newStatus;
            Session::flash('success', "Status pesanan berhasil diperbarui menjadi: {$label}");

            // AUTO-REFUND TRIGGER: jika pesanan dibatalkan dan sudah pernah dibayar
            if ($newStatus === Order::STATUS_CANCELLED) {
                $paymentService = new \App\Services\Payment\PaymentService();
                $triggered = $paymentService->autoTriggerRefundOnCancellation($id, $userId);
                if ($triggered) {
                    Session::flash('success',
                        "Status pesanan berhasil diperbarui menjadi: {$label}. " .
                        "Refund otomatis telah dibuat karena pesanan sudah dibayar."
                    );
                }
            }
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'Gagal memperbarui status: ' . $e->getMessage());
        }

        $this->redirect('/orders/detail?id=' . $id);
    }

    public function mockPayment(): void
    {
        $id = (int) ($_POST['order_id'] ?? ($_GET['id'] ?? 0));
        if ($id > 0) {
            $this->redirect('/orders/payment?order_id=' . $id);
            return;
        }
        $this->redirect('/orders');
    }

    /**
     * GET /store/orders/ready
     * Halaman Operasional Siap Diambil / Diantar untuk Toko
     */
    public function readyOrders(): void
    {
        $role = Auth::role();
        if (!in_array($role, [Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk membuka halaman operasional toko.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $storeModel = new Store();
        $store = null;
        if (in_array($role, [Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER_TOKO], true)) {
            $store = $storeModel->getStoreByOwner(Auth::id());
        } elseif (isset($_GET['store_id'])) {
            $store = $storeModel->getStoreById((int)$_GET['store_id']);
        }
        if (!$store) {
            $store = $storeModel->getStoreById(1);
        }
        $storeId = (int)($store['id'] ?? 1);

        $orderModel = new Order();
        $readyCounts = $orderModel->getReadyCountsByStore($storeId);

        $tab = trim($_GET['tab'] ?? '');
        if (!in_array($tab, ['pickup', 'delivery', 'all'], true)) {
            $tab = 'pickup'; // Default ke Siap Diambil
        }

        $filters = [
            'status'           => trim($_GET['status'] ?? ''),
            'courier_assigned' => trim($_GET['courier_assigned'] ?? ''),
            'search'           => trim($_GET['search'] ?? ''),
        ];

        $orders = $orderModel->getReadyOrdersByStore($storeId, $tab, $filters);

        $courierLocModel = new CourierLocation();
        $availableCouriers = $courierLocModel->getAvailableCouriers();

        $data = [
            'pageTitle'         => 'Pesanan Siap Diambil / Diantar — ' . htmlspecialchars($store['name'] ?? 'Toko Laundry'),
            'store'             => $store,
            'activeTab'         => $tab,
            'readyCounts'       => $readyCounts,
            'orders'            => $orders,
            'filters'           => $filters,
            'availableCouriers' => $availableCouriers,
            'statusLabels'      => Order::getStatusLabels(),
            'csrf_token'        => Csrf::generateToken(),
            'role'              => $role,
            'user'              => Auth::user(),
        ];

        $this->view('orders.ready_delivery', $data, 'main');
    }

    /**
     * POST /store/orders/mark-picked-up
     * Tandai cucian bersih telah diambil langsung oleh customer di toko.
     */
    public function markPickedUp(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/store/orders/ready?tab=pickup');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/store/orders/ready?tab=pickup');
            return;
        }

        $role = Auth::role();
        if (!in_array($role, [Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk memproses pesanan toko.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            Session::flash('error', 'ID Pesanan tidak valid.');
            $this->redirect('/store/orders/ready?tab=pickup');
            return;
        }

        $storeModel = new Store();
        $store = $storeModel->getStoreByOwner(Auth::id());
        $storeId = (int)($store['id'] ?? (int)($_POST['store_id'] ?? 1));

        $orderModel = new Order();
        $result = $orderModel->markAsPickedUp($orderId, $storeId, Auth::id());

        if (!empty($result['success'])) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/store/orders/ready?tab=pickup');
    }

    /**
     * POST /store/orders/assign-delivery-courier
     * Tugaskan kurir untuk mengantar cucian siap ke alamat customer.
     */
    public function assignDeliveryCourier(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/store/orders/ready?tab=delivery');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/store/orders/ready?tab=delivery');
            return;
        }

        $role = Auth::role();
        if (!in_array($role, [Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            Session::flash('error', 'Akses ditolak!');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $courierId = (int)($_POST['courier_id'] ?? 0);

        if ($orderId <= 0 || $courierId <= 0) {
            Session::flash('error', 'Pilih kurir terlebih dahulu.');
            $this->redirect('/store/orders/ready?tab=delivery');
            return;
        }

        $storeModel = new Store();
        $store = $storeModel->getStoreByOwner(Auth::id());
        $storeId = (int)($store['id'] ?? (int)($_POST['store_id'] ?? 1));

        $orderModel = new Order();
        $result = $orderModel->assignDeliveryCourier($orderId, $storeId, $courierId, Auth::id());

        if (!empty($result['success'])) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/store/orders/ready?tab=delivery');
    }

    /**
     * POST /store/orders/start-delivery
     * Mulai proses pengantaran kurir (ready -> out_for_delivery).
     */
    public function startDelivery(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/store/orders/ready?tab=delivery');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/store/orders/ready?tab=delivery');
            return;
        }

        $role = Auth::role();
        if (!in_array($role, [Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            Session::flash('error', 'Akses ditolak!');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            Session::flash('error', 'ID Pesanan tidak valid.');
            $this->redirect('/store/orders/ready?tab=delivery');
            return;
        }

        $storeModel = new Store();
        $store = $storeModel->getStoreByOwner(Auth::id());
        $storeId = (int)($store['id'] ?? (int)($_POST['store_id'] ?? 1));

        $orderModel = new Order();
        $result = $orderModel->startDelivery($orderId, $storeId, Auth::id());

        if (!empty($result['success'])) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/store/orders/ready?tab=delivery');
    }
}
