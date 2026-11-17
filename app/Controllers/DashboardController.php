<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Services\Finance\OmzetTransparencyService;
use App\Services\WhatsApp\WhatsAppNotificationService;
use App\Services\WhatsApp\Providers\FonnteWhatsAppClient;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->redirect(Auth::getRedirectUrl());
    }

    public function owner(): void
    {
        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $userCount = 0;
        $totalStores = 0;
        $activeStores = 0;
        $totalOmset = 0.0;
        $omsetTxCount = 0;
        $totalOrders = 0;

        if ($db) {
            $userCount = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $totalStores = (int) $db->query("SELECT COUNT(*) FROM stores")->fetchColumn();
            $activeStores = (int) $db->query("SELECT COUNT(*) FROM stores WHERE is_active = 1")->fetchColumn();
            $orderModel = new Order();
            $platformStats = $orderModel->getPlatformCardStats();
            $totalOmset = $platformStats['total_omset'];
            $omsetTxCount = $platformStats['omset_tx_count'];
            $totalOrders = $platformStats['total_orders'];
        } else {
            $userCount = 8;
            $totalStores = 2;
            $activeStores = 2;
            $totalOmset = 29800.0;
            $omsetTxCount = 1;
            $totalOrders = 1;
        }

        $omzetService = new OmzetTransparencyService($db);
        $transparency = $omzetService->getOmzetTransparencyData(null);

        // WhatsApp stats for dashboard widget (owner only)
        $waService  = new WhatsAppNotificationService();
        $waStats    = $waService->getStats();
        $waClient   = $waService->getClient();
        $waIsFonnte = ($waClient instanceof FonnteWhatsAppClient);
        $waDevice   = $waIsFonnte ? ($waStats['device'] ?? null) : null;

        // Chatbot stats for owner dashboard
        $botStats = [
            'enabled'          => 0,
            'active_keywords'  => 0,
            'total_responses'  => 0,
            'fallback_enabled' => false,
        ];
        if ($db) {
            $botEnabledCount = (int) $db->query("SELECT COUNT(*) FROM store_whatsapp_bot_settings WHERE enabled = 1")->fetchColumn();
            $totalKwActive   = (int) $db->query("SELECT COUNT(*) FROM store_whatsapp_bot_responses WHERE is_active = 1")->fetchColumn();
            $totalResponses  = (int) $db->query("SELECT COUNT(*) FROM store_whatsapp_bot_responses")->fetchColumn();
            $botStats = [
                'enabled'          => $botEnabledCount > 0 ? 1 : 0,
                'active_stores'    => $botEnabledCount,
                'active_keywords'  => $totalKwActive,
                'total_responses'  => $totalResponses,
                'fallback_enabled' => true,
            ];
        }

        $data = [
            'pageTitle'   => 'Dashboard Owner Platform',
            'user'        => Auth::user(),
            'role'        => Auth::ROLE_OWNER,
            'roleDisplay' => 'Owner Platform',
            'userCount'   => $userCount,
            'transparency'=> $transparency,
            'waStats'     => $waStats,
            'waDevice'    => $waDevice,
            'waIsFonnte'  => $waIsFonnte,
            'botStats'    => $botStats,
            'stats' => [
                ['title' => 'Total Pengguna', 'value' => $userCount . ' User', 'icon' => 'fa-users', 'color' => 'primary', 'trend' => 'Platform Terintegrasi'],
                ['title' => 'Toko Terdaftar', 'value' => $totalStores . ' Toko', 'icon' => 'fa-store', 'color' => 'success', 'trend' => $activeStores . ' Toko Aktif'],
                [
                    'title' => 'Total Omset Terbayar', 
                    'value' => 'Rp ' . number_format($totalOmset, 0, ',', '.'), 
                    'icon' => 'fa-chart-line', 
                    'color' => 'info', 
                    'trend' => $omsetTxCount . ' Transaksi Terbayar', 
                    'sub_info' => $omsetTxCount . ' transaksi • Periode: Semua',
                    'link' => '/dashboard/omzet-detail',
                    'has_transparency' => true
                ],
                ['title' => 'Keamanan RBAC', 'value' => 'Server-Side Active', 'icon' => 'fa-shield-halved', 'color' => 'warning', 'trend' => 'Enforced via Middleware']
            ]
        ];

        $this->view('dashboard.owner', $data, 'main');
    }

    public function adminOwner(): void
    {
        if (!Auth::hasRole(Auth::ROLE_ADMIN_OWNER)) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk membuka halaman tersebut.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $pendingStores = 0;
        $totalOmset = 0.0;
        $omsetTxCount = 0;
        $userCount = 0;
        $totalOrders = 0;

        if ($db) {
            $pendingStores = (int) $db->query("SELECT COUNT(*) FROM stores WHERE is_active = 0")->fetchColumn();
            $orderModel = new Order();
            $platformStats = $orderModel->getPlatformCardStats();
            $totalOmset = $platformStats['total_omset'];
            $omsetTxCount = $platformStats['omset_tx_count'];
            $userCount = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $totalOrders = $platformStats['total_orders'];
        } else {
            $pendingStores = 0;
            $totalOmset = 29800.0;
            $omsetTxCount = 1;
            $userCount = 8;
            $totalOrders = 1;
        }

        $data = [
            'pageTitle'   => 'Dashboard Admin Owner',
            'user'        => Auth::user(),
            'role'        => Auth::ROLE_ADMIN_OWNER,
            'roleDisplay' => 'Admin Owner',
            'waStats'     => (new WhatsAppNotificationService())->getStats(),
            'stats' => [
                ['title' => 'Toko Non-Aktif', 'value' => $pendingStores . ' Toko', 'icon' => 'fa-file-signature', 'color' => 'warning', 'trend' => $pendingStores === 0 ? 'Semua toko aktif' : 'Perlu ditinjau'],
                [
                    'title'    => 'Total Omset Platform',
                    'value'    => 'Rp ' . number_format($totalOmset, 0, ',', '.'),
                    'icon'     => 'fa-receipt',
                    'color'    => 'success',
                    'trend'    => $omsetTxCount . ' Transaksi Terbayar',
                    'sub_info' => $omsetTxCount . ' transaksi • Periode: Semua',
                    'link'     => '/dashboard/omzet-detail'
                ],
                ['title' => 'User Terdaftar', 'value' => $userCount . ' Akun', 'icon' => 'fa-user-plus', 'color' => 'info', 'trend' => 'Akun Terverifikasi'],
                ['title' => 'Monitoring Pesanan', 'value' => $totalOrders . ' Pesanan', 'icon' => 'fa-basket-shopping', 'color' => 'primary', 'trend' => 'Terintegrasi']
            ]
        ];

        $this->view('dashboard.admin_owner', $data, 'main');
    }

    public function ownerToko(): void
    {
        $storeModel = new Store();
        $store = $storeModel->getStoreByOwner(Auth::id());

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $omsetToko = 0.0;
        $omsetTxCount = 0;
        $orderCount = 0;
        $serviceCount = 0;
        $rating = 4.8;

        if ($store && $db) {
            $sid = (int) $store['id'];
            $orderModel = new Order();
            $storeStats = $orderModel->getOwnerTokoCardStats($sid);
            $omsetToko = $storeStats['omset_toko'];
            $omsetTxCount = $storeStats['omset_tx_count'];
            $orderCount = $storeStats['total_orders'];

            $stmt = $db->prepare("SELECT COUNT(*) FROM services WHERE store_id = :sid AND is_active = 1");
            $stmt->execute(['sid' => $sid]);
            $serviceCount = (int) $stmt->fetchColumn();

            $rating = (float) ($store['rating'] ?? 4.8);
        }

        $omzetService = new OmzetTransparencyService($db);
        $storeId = $store ? (int)$store['id'] : null;
        $transparency = $omzetService->getOmzetTransparencyData($storeId);

        $data = [
            'pageTitle' => 'Dashboard Owner Toko' . ($store ? ' — ' . htmlspecialchars($store['name']) : ''),
            'user' => Auth::user(),
            'store' => $store,
            'role' => Auth::ROLE_OWNER_TOKO,
            'roleDisplay' => 'Owner Toko',
            'transparency' => $transparency,
            'stats' => [
                [
                    'title' => 'Omset Toko (Terbayar)', 
                    'value' => 'Rp ' . number_format($omsetToko, 0, ',', '.'), 
                    'icon' => 'fa-wallet', 
                    'color' => 'success', 
                    'trend' => $omsetTxCount . ' Transaksi Terbayar', 
                    'sub_info' => $omsetTxCount . ' transaksi • Toko Ini',
                    'link' => '/dashboard/omzet-detail',
                    'has_transparency' => true
                ],
                ['title' => 'Total Pesanan Toko', 'value' => $orderCount . ' Order', 'icon' => 'fa-basket-shopping', 'color' => 'primary', 'trend' => 'Masuk ke Toko Ini'],
                ['title' => 'Layanan Aktif', 'value' => $serviceCount . ' Jenis', 'icon' => 'fa-tags', 'color' => 'info', 'trend' => 'Katalog Toko Ini'],
                ['title' => 'Rating Pelanggan Toko', 'value' => number_format($rating, 1) . ' ★', 'icon' => 'fa-star', 'color' => 'warning', 'trend' => 'Kepuasan Sangat Baik']
            ]
        ];

        $this->view('dashboard.owner_toko', $data, 'main');
    }

    public function adminToko(): void
    {
        if (!Auth::hasRole(Auth::ROLE_ADMIN_TOKO)) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk membuka halaman tersebut.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $storeModel = new Store();
        $store = $storeModel->getStoreByOwner(Auth::id());

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $needProcess = 0;
        $readyCount = 0;
        $readyPickup = 0;
        $readyDelivery = 0;
        $readyTotal = 0;
        $deliveredCount = 0;
        $omsetToko = 0.0;

        if ($store && $db) {
            $sid = (int) $store['id'];
            $orderModel = new Order();
            $cardStats = $orderModel->getAdminTokoCardStats($sid);
            $needProcess = $cardStats['need_process'];
            $readyPickup = $cardStats['ready_pickup'];
            $readyDelivery = $cardStats['ready_delivery'];
            $readyTotal = $cardStats['ready_total'];
            $readyCount = $readyTotal;
            $deliveredCount = $cardStats['delivered_count'];
            $omsetToko = $cardStats['omset_toko'];
            $omsetTxCount = $cardStats['omset_tx_count'];
        }

        // WhatsApp stats for admin toko dashboard widget (isolated to store)
        $waService = new WhatsAppNotificationService();
        $activeStoreId = $store ? (int)$store['id'] : null;
        $waStats = $waService->getStats($activeStoreId);
        $waClient = $waService->getClient();
        $waIsFonnte = ($waClient instanceof FonnteWhatsAppClient);
        $waDevice = $waIsFonnte ? ($waStats['device'] ?? null) : null;

        // Chatbot stats for admin toko dashboard
        $botStats = [
            'enabled'          => 0,
            'active_keywords'  => 0,
            'total_responses'  => 0,
            'fallback_enabled' => false,
        ];
        if ($db && $activeStoreId !== null) {
            $stmtBotSet = $db->prepare('SELECT enabled, fallback_message FROM store_whatsapp_bot_settings WHERE store_id = :sid LIMIT 1');
            $stmtBotSet->execute(['sid' => $activeStoreId]);
            $bRow = $stmtBotSet->fetch(\PDO::FETCH_ASSOC);

            $stmtKwAct = $db->prepare('SELECT COUNT(*) FROM store_whatsapp_bot_responses WHERE store_id = :sid AND is_active = 1');
            $stmtKwAct->execute(['sid' => $activeStoreId]);
            $actCount = (int) $stmtKwAct->fetchColumn();

            $stmtKwTot = $db->prepare('SELECT COUNT(*) FROM store_whatsapp_bot_responses WHERE store_id = :sid');
            $stmtKwTot->execute(['sid' => $activeStoreId]);
            $totCount = (int) $stmtKwTot->fetchColumn();

            $botStats = [
                'enabled'          => !empty($bRow['enabled']) ? 1 : 0,
                'active_keywords'  => $actCount,
                'total_responses'  => $totCount,
                'fallback_enabled' => !empty($bRow['fallback_message']),
            ];
        }

        $data = [
            'pageTitle' => 'Dashboard Admin Toko' . ($store ? ' — ' . htmlspecialchars($store['name']) : ''),
            'user' => Auth::user(),
            'store' => $store,
            'role' => Auth::ROLE_ADMIN_TOKO,
            'roleDisplay' => 'Admin Toko',
            'readyPickup' => $readyPickup,
            'readyDelivery' => $readyDelivery,
            'readyTotal' => $readyTotal,
            'waStats' => $waStats,
            'waDevice' => $waDevice,
            'waIsFonnte' => $waIsFonnte,
            'botStats' => $botStats,
            'stats' => [
                ['title' => 'Pesanan Perlu Diproses', 'value' => $needProcess . ' Order', 'icon' => 'fa-spinner', 'color' => 'warning', 'trend' => 'Diproses Kasir/Toko', 'link' => '/store/orders'],
                [
                    'title' => 'Siap Diambil / Diantar', 
                    'value' => $readyTotal . ' Paket', 
                    'icon' => 'fa-box', 
                    'color' => 'info', 
                    'trend' => 'Siap Diambil: ' . $readyPickup . ' • Siap Diantar: ' . $readyDelivery,
                    'link' => '/store/orders/ready',
                    'detail' => "Siap Diambil: {$readyPickup} | Siap Diantar: {$readyDelivery}"
                ],
                ['title' => 'Selesai Terkirim', 'value' => $deliveredCount . ' Order', 'icon' => 'fa-circle-check', 'color' => 'success', 'trend' => 'Pesanan Selesai', 'link' => '/store/orders'],
                [
                    'title' => 'OMSET KASIR TERBAYAR', 
                    'value' => 'Rp ' . number_format($omsetToko, 0, ',', '.'), 
                    'icon' => 'fa-cash-register', 
                    'color' => 'primary', 
                    'trend' => 'Toko Ini', 
                    'sub_info' => ($omsetTxCount ?? 0) . ' transaksi • Periode: Semua',
                    'link' => '/dashboard/omzet-detail'
                ]
            ]
        ];

        $this->view('dashboard.admin_toko', $data, 'main');
    }

    public function kurir(): void
    {
        $userId = Auth::id();
        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $pickupCount = 0;
        $deliveryCount = 0;
        $deliveredCount = 0;

        if ($db) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE courier_id = :cid AND status IN ('assigned_to_courier', 'courier_on_the_way')");
            $stmt->execute(['cid' => $userId]);
            $pickupCount = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE courier_id = :cid AND status = 'out_for_delivery'");
            $stmt->execute(['cid' => $userId]);
            $deliveryCount = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE courier_id = :cid AND status = 'delivered'");
            $stmt->execute(['cid' => $userId]);
            $deliveredCount = (int) $stmt->fetchColumn();
        }

        $data = [
            'pageTitle' => 'Dashboard Kurir',
            'user' => Auth::user(),
            'role' => Auth::ROLE_KURIR,
            'roleDisplay' => 'Kurir / Jasa Antar',
            'stats' => [
                ['title' => 'Tugas Pickup Hari Ini', 'value' => $pickupCount . ' Lokasi', 'icon' => 'fa-truck-arrow-right', 'color' => 'primary', 'trend' => 'Perlu Dijemput'],
                ['title' => 'Tugas Delivery', 'value' => $deliveryCount . ' Paket', 'icon' => 'fa-house-chimney-user', 'color' => 'warning', 'trend' => 'Dalam Pengantaran'],
                ['title' => 'Selesai Terkirim', 'value' => $deliveredCount . ' Paket', 'icon' => 'fa-circle-check', 'color' => 'success', 'trend' => 'Sukses Diantar'],
                ['title' => 'Rating Kurir', 'value' => '4.95 ★', 'icon' => 'fa-thumbs-up', 'color' => 'info', 'trend' => 'Performa Kurir']
            ]
        ];

        $this->view('dashboard.kurir', $data, 'main');
    }

    public function customer(): void
    {
        $userId = Auth::id();
        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $activeOrders = 0;
        $totalOrders = 0;
        $addressCount = 0;

        if ($db) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = :cid AND status NOT IN ('delivered', 'cancelled')");
            $stmt->execute(['cid' => $userId]);
            $activeOrders = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = :cid");
            $stmt->execute(['cid' => $userId]);
            $totalOrders = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = :cid");
            $stmt->execute(['cid' => $userId]);
            $addressCount = (int) $stmt->fetchColumn();
        }

        $data = [
            'pageTitle' => 'Dashboard Customer',
            'user' => Auth::user(),
            'role' => Auth::ROLE_CUSTOMER,
            'roleDisplay' => 'Customer',
            'stats' => [
                ['title' => 'Laundry Dalam Proses', 'value' => $activeOrders . ' Pesanan', 'icon' => 'fa-basket-shopping', 'color' => 'primary', 'trend' => 'Sedang Diproses'],
                ['title' => 'Riwayat Pesanan Saya', 'value' => $totalOrders . ' Order', 'icon' => 'fa-history', 'color' => 'info', 'trend' => 'Total Cucian'],
                ['title' => 'Poin Reward Saya', 'value' => '250 Poin', 'icon' => 'fa-gift', 'color' => 'warning', 'trend' => 'Voucher Potongan Rp 10.000'],
                ['title' => 'Alamat Tersimpan', 'value' => $addressCount . ' Alamat', 'icon' => 'fa-location-dot', 'color' => 'secondary', 'trend' => 'Buku Alamat']
            ]
        ];

        $this->view('dashboard.customer', $data, 'main');
    }

    /**
     * Halaman Detail Omset Toko / Platform secara lengkap, transparan, dan interaktif.
     */
    public function omzetDetail(): void
    {
        $role = Auth::role();
        // Hanya staf/pemilik yang berhak melihat omzet. Customer dilarang keras!
        $allowedRoles = [
            Auth::ROLE_OWNER,
            Auth::ROLE_ADMIN_OWNER,
            Auth::ROLE_OWNER_TOKO,
            Auth::ROLE_ADMIN_TOKO,
        ];

        if (!Auth::hasRole($allowedRoles)) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk melihat laporan omzet internal.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $userId = Auth::id();
        $storeId = null;
        $store = null;
        $storeModel = new Store();

        if (in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            $store = $storeModel->getStoreByOwner($userId);
            if (!$store) {
                // Jangan fallback ke store lain jika user tidak memiliki toko
                Session::flash('error', 'Akun Anda belum ditugaskan ke toko manapun.');
                $this->redirect(Auth::getRedirectUrl());
                return;
            }
            $storeId = (int)$store['id'];
        }

        // Ambil filter dari query parameter
        $filters = [
            'period'         => trim($_GET['period'] ?? ''),
            'start_date'     => trim($_GET['start_date'] ?? ''),
            'end_date'       => trim($_GET['end_date'] ?? ''),
            'payment_status' => trim($_GET['payment_status'] ?? 'paid'),
            'payment_method' => trim($_GET['payment_method'] ?? ''),
        ];

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $omzetService = new OmzetTransparencyService($db);
        $reportData = $omzetService->getDetailedReport($storeId, $filters);

        $data = [
            'pageTitle'       => 'Detail Omset' . ($store ? ' — ' . $store['name'] : ' Platform'),
            'user'            => Auth::user(),
            'store'           => $store,
            'role'            => $role,
            'report'          => $reportData,
            'filters'         => $reportData['filters_applied'],
            'summary'         => $reportData['summary'],
            'transactions'    => $reportData['transactions'],
            'chart_daily'     => $reportData['chart_daily'],
            'chart_services'  => $reportData['chart_services'],
            'method_breakdown'=> $reportData['method_breakdown']
        ];

        $this->view('dashboard.omzet_detail', $data, 'main');
    }
}
