<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Models\Refund;
use App\Models\Order;
use App\Services\Payment\PaymentService;

class RefundController extends Controller
{
    private PaymentService $paymentService;
    private Refund $refundModel;
    private Order  $orderModel;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
        $this->refundModel    = new Refund();
        $this->orderModel     = new Order();
    }

    // -------------------------------------------------------------------------
    // LIST REFUNDS — toko-level view (owner_toko / admin_toko)
    // Route: GET /refunds
    // -------------------------------------------------------------------------
    public function index(): void
    {
        $role    = Auth::role();
        $userId  = Auth::id();
        $user    = Auth::user();

        $storeId = $this->resolveStoreId($role, $userId);

        // Owners see all platform-wide, toko roles see only their store's
        if (in_array($role, [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            $refunds = $this->refundModel->findAll();
        } elseif ($storeId) {
            $refunds = $this->refundModel->findByStore($storeId);
        } else {
            Session::flash('error', 'Anda tidak terhubung ke toko manapun.');
            $this->redirect('/dashboard');
            return;
        }

        $data = [
            'pageTitle' => 'Daftar Permintaan Refund',
            'refunds'   => $refunds,
            'user'      => $user,
            'role'      => $role,
        ];

        $this->view('refunds.index', $data, 'main');
    }

    // -------------------------------------------------------------------------
    // INITIATE REFUND (POST from store admin / order detail page)
    // Route: POST /refunds/initiate
    // -------------------------------------------------------------------------
    public function initiate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        // CSRF check
        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
            $this->redirect('/refunds');
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $reason  = trim($_POST['reason'] ?? '');
        $role    = Auth::role();
        $userId  = Auth::id();

        if ($orderId <= 0 || $reason === '') {
            Session::flash('error', 'ID Pesanan dan alasan refund wajib diisi.');
            $this->redirect('/orders/detail?id=' . $orderId);
            return;
        }

        // Determine storeId for isolation (null = platform-wide roles bypass)
        $storeId = null;
        if (in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            $storeId = $this->resolveStoreId($role, $userId);
            if (!$storeId) {
                Session::flash('error', 'Anda tidak terhubung ke toko manapun.');
                $this->redirect('/dashboard');
                return;
            }
        } elseif (!in_array($role, [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER], true)) {
            Session::flash('error', 'Anda tidak memiliki izin untuk mengajukan refund.');
            $this->redirect('/orders/detail?id=' . $orderId);
            return;
        }

        $result = $this->paymentService->initiateRefund($orderId, $reason, $userId, $storeId);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/orders/detail?id=' . $orderId);
    }

    // -------------------------------------------------------------------------
    // CONFIRM REFUND — Simulate gateway callback (approve/reject)
    // Route: POST /refunds/confirm
    // -------------------------------------------------------------------------
    public function confirm(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $role = Auth::role();
        if (!in_array($role, [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $refundId = (int) ($_POST['refund_id'] ?? 0);
        $action   = trim($_POST['action'] ?? ''); // 'complete' or 'fail'

        if ($refundId <= 0 || !in_array($action, ['complete', 'fail'], true)) {
            $this->json(['success' => false, 'message' => 'Parameter tidak valid.'], 400);
            return;
        }

        // Store isolation: toko-level roles may only touch their own store's refunds
        if (in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            $refund  = $this->refundModel->find($refundId);
            $storeId = $this->resolveStoreId($role, Auth::id());
            if ($refund) {
                $order = $this->orderModel->findOrderDetail((int) $refund['order_id']);
                if (!$order || (int) $order['store_id'] !== $storeId) {
                    $this->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
                    return;
                }
            }
        }

        $callbackStatus = $action === 'complete' ? Refund::STATUS_COMPLETED : Refund::STATUS_FAILED;
        $result = $this->paymentService->processRefundCallback($refundId, $callbackStatus);

        $this->json($result, $result['success'] ? 200 : 400);
    }

    // -------------------------------------------------------------------------
    // API: Refund list for a specific order (AJAX)
    // Route: GET /api/refunds?order_id=...
    // -------------------------------------------------------------------------
    public function listForOrder(): void
    {
        $orderId = (int) ($_GET['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid order_id'], 400);
            return;
        }

        $role    = Auth::role();
        $userId  = Auth::id();

        // Customers can only see refunds on their own orders
        if ($role === Auth::ROLE_CUSTOMER) {
            $order = $this->orderModel->findOrderDetail($orderId);
            if (!$order || (int) $order['customer_id'] !== $userId) {
                $this->json(['success' => false, 'message' => 'Forbidden'], 403);
                return;
            }
        }

        $refunds = $this->refundModel->findByOrder($orderId);
        $this->json(['success' => true, 'data' => $refunds]);
    }

    // -------------------------------------------------------------------------
    // HELPER: Resolve store_id for currently logged-in toko role user
    // -------------------------------------------------------------------------
    private function resolveStoreId(string $role, int $userId): ?int
    {
        if (!in_array($role, [Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO], true)) {
            return null;
        }

        try {
            $db = \App\Core\Database::getInstance();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$db) return null;

        $stmt = $db->prepare("SELECT store_id FROM users WHERE id = :uid LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['store_id'] : null;
    }
}
