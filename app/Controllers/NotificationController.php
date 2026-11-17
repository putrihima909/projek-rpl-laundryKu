<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Models\Notification;
use App\Models\Order;

class NotificationController extends Controller
{
    /**
     * GET /notifications
     * Full notification listing page with pagination and filter support.
     */
    public function index(): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Silakan login untuk melihat notifikasi.');
            $this->redirect('/login');
            return;
        }

        $userId = (int) Auth::id();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $totalItems = Notification::countByUser($userId);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $notifications = Notification::findByUser($userId, $limit, $offset);
        $unreadCount = Notification::countUnread($userId);

        $data = [
            'pageTitle'     => 'Notifikasi Saya',
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'totalItems'    => $totalItems,
            'totalPages'    => $totalPages,
            'currentPage'   => $page,
            'csrf_token'    => Csrf::generateToken(),
            'user'          => Auth::user(),
            'role'          => Auth::role()
        ];

        $this->view('notifications.index', $data, 'main');
    }

    /**
     * GET /notifications/unread-count
     * Polling API endpoint returning unread notification counter in JSON.
     */
    public function unreadCount(): void
    {
        if (!Auth::check()) {
            $this->json([
                'status'       => 'unauthorized',
                'message'      => 'User is not logged in',
                'unread_count' => 0
            ], 401);
            return;
        }

        $userId = (int) Auth::id();
        $unreadCount = Notification::countUnread($userId);

        $this->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
            'server_time'  => date('Y-m-d H:i:s'),
            'timestamp'    => time()
        ]);
    }

    /**
     * POST /notifications/read
     * Mark a specific notification as read with strict user ownership validation.
     */
    public function markRead(): void
    {
        if (!Auth::check()) {
            $this->handleUnauthenticated();
            return;
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => 'Token CSRF tidak valid.'], 403);
                return;
            }
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect($_POST['redirect_to'] ?? '/notifications');
            return;
        }

        $id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
        $userId = (int) Auth::id();

        if ($id <= 0) {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => 'ID notifikasi tidak valid.'], 400);
                return;
            }
            $this->redirect('/notifications');
            return;
        }

        $success = Notification::markAsRead($id, $userId);

        if (!$success) {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => 'Notifikasi tidak ditemukan atau bukan milik Anda.'], 403);
                return;
            }
            Session::flash('error', 'Akses ditolak: Notifikasi tidak ditemukan atau bukan milik Anda.');
            $this->redirect('/notifications');
            return;
        }

        if ($isAjax) {
            $this->json([
                'status'       => 'success',
                'message'      => 'Notifikasi berhasil ditandai telah dibaca.',
                'unread_count' => Notification::countUnread($userId)
            ]);
            return;
        }

        $this->redirect($_POST['redirect_to'] ?? '/notifications');
    }

    /**
     * POST /notifications/mark-all-read
     * Mark all unread notifications for the authenticated user as read.
     */
    public function markAllRead(): void
    {
        if (!Auth::check()) {
            $this->handleUnauthenticated();
            return;
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => 'Token CSRF tidak valid.'], 403);
                return;
            }
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect($_POST['redirect_to'] ?? '/notifications');
            return;
        }

        $userId = (int) Auth::id();
        Notification::markAllAsRead($userId);

        if ($isAjax) {
            $this->json([
                'status'       => 'success',
                'message'      => 'Semua notifikasi berhasil ditandai telah dibaca.',
                'unread_count' => 0
            ]);
            return;
        }

        Session::flash('success', 'Semua notifikasi berhasil ditandai telah dibaca.');
        $this->redirect($_POST['redirect_to'] ?? '/notifications');
    }

    /**
     * GET /notifications/open?id={id}
     * Mark notification as read and redirect to related resource (e.g. order detail).
     */
    public function openAndRedirect(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        $userId = (int) Auth::id();

        if ($id <= 0) {
            $this->redirect('/notifications');
            return;
        }

        $notif = Notification::find($id, $userId);
        if (!$notif) {
            Session::flash('error', 'Notifikasi tidak ditemukan.');
            $this->redirect('/notifications');
            return;
        }

        // Mark as read
        Notification::markAsRead($id, $userId);

        // Check if there is an order code in title or message to redirect intelligently
        $fullText = ($notif['title'] ?? '') . ' ' . ($notif['message'] ?? '');
        if (preg_match('/(ORD-\d{8}-\d{3})/', $fullText, $matches)) {
            $orderCode = $matches[1];
            $db = (new Notification())->getDb() ?? \App\Core\Database::getInstance();
            if ($db) {
                $stmt = $db->prepare("SELECT id FROM orders WHERE order_code = :code LIMIT 1");
                $stmt->execute(['code' => $orderCode]);
                $orderId = $stmt->fetchColumn();
                if ($orderId) {
                    $this->redirect('/orders/detail?id=' . (int)$orderId);
                    return;
                }
            }
        }

        $this->redirect('/notifications');
    }

    /**
     * POST /notifications/whatsapp-preference
     * Toggle or set WhatsApp notification opt-in/opt-out status for authenticated user.
     */
    public function updateWhatsAppPreference(): void
    {
        if (!Auth::check()) {
            $this->handleUnauthenticated();
            return;
        }

        $userId = (int) Auth::id();
        $enabled = isset($_POST['enabled']) ? (int)(bool)$_POST['enabled'] : 1;

        $db = \App\Core\Database::getInstance();
        if ($db) {
            $stmt = $db->prepare("UPDATE users SET wa_notifications_enabled = :en WHERE id = :uid");
            $stmt->execute(['en' => $enabled, 'uid' => $userId]);
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            $this->json([
                'status' => 'success',
                'wa_notifications_enabled' => (bool)$enabled,
                'message' => $enabled ? 'Notifikasi WhatsApp diaktifkan' : 'Notifikasi WhatsApp dinonaktifkan',
            ]);
            return;
        }

        Session::flash('success', $enabled ? 'Notifikasi WhatsApp diaktifkan.' : 'Notifikasi WhatsApp dinonaktifkan.');
        $this->redirect('/notifications');
    }

    private function handleUnauthenticated(): void
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            $this->json(['status' => 'unauthorized', 'message' => 'Silakan login terlebih dahulu.'], 401);
        } else {
            Session::flash('error', 'Silakan login terlebih dahulu.');
            $this->redirect('/login');
        }
    }
}
