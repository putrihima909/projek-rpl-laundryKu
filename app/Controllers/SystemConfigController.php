<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Env;
use App\Helpers\Session;
use App\Models\Store;
use App\Services\WhatsApp\WhatsAppNotificationService;
use App\Services\WhatsApp\Providers\FonnteWhatsAppClient;

/**
 * SystemConfigController
 * Pusat Konfigurasi Terpadu: WhatsApp Gateway, AI Assistant, Sistem & Keamanan.
 *
 * RBAC:
 *  - OWNER      : akses penuh, semua toko
 *  - ADMIN_TOKO : akses read + test, terbatas ke toko sendiri
 *  - Lainnya    : ditolak
 */
class SystemConfigController extends Controller
{
    private const ALLOWED_ROLES = [
        Auth::ROLE_OWNER,
        Auth::ROLE_ADMIN_OWNER,
        Auth::ROLE_ADMIN_TOKO,
    ];

    /** Validasi akses; redirect jika tidak diizinkan. */
    private function requireAccess(): void
    {
        if (!Auth::check() || !Auth::hasRole(self::ALLOWED_ROLES)) {
            Session::flash('error', 'Akses ditolak. Halaman ini hanya untuk Owner dan Admin Toko.');
            $this->redirect(Auth::getRedirectUrl());
            exit;
        }
    }

    /** Ambil store_id aktif berdasarkan role. */
    private function getActiveStoreId(): ?int
    {
        if (Auth::hasRole(Auth::ROLE_ADMIN_TOKO)) {
            $user = Auth::user();
            if (!empty($user['store_id'])) {
                return (int) $user['store_id'];
            }
            $storeModel = new Store();
            $store = $storeModel->getStoreByOwner(Auth::id());
            return $store ? (int) $store['id'] : null;
        }
        return null; // Owner/AdminOwner = platform-wide
    }

    /**
     * GET /admin/system-config
     * Halaman Pusat Konfigurasi Terpadu.
     */
    public function index(): void
    {
        $this->requireAccess();

        $db = null;
        try { $db = Database::getInstance(); } catch (\Throwable $e) {}

        $role          = Auth::role();
        $isGlobalAdmin = Auth::hasRole([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER]);
        $activeStoreId = $this->getActiveStoreId();

        // ── Store info ───────────────────────────────────────────────────────
        $storeModel = new Store();
        $store      = $activeStoreId ? $storeModel->getStoreById($activeStoreId) : null;

        // ── WhatsApp stats ───────────────────────────────────────────────────
        $waService  = new WhatsAppNotificationService();
        $waStats    = $waService->getStats($activeStoreId);
        $waClient   = $waService->getClient();
        $waIsFonnte = ($waClient instanceof FonnteWhatsAppClient);

        // Ambil settings WA dari DB (tidak tampilkan token mentah)
        $waSettings = null;
        $isStoreConfig = false;

        if ($db) {
            if ($activeStoreId !== null) {
                // ADMIN TOKO: ambil dari store_whatsapp_settings
                $stmt = $db->prepare('SELECT * FROM store_whatsapp_settings WHERE store_id = :sid LIMIT 1');
                $stmt->execute(['sid' => $activeStoreId]);
                $waSettings = $stmt->fetch(\PDO::FETCH_ASSOC);
                $isStoreConfig = true;

                if (!$waSettings) {
                    $waSettings = [
                        'store_id'     => $activeStoreId,
                        'provider'     => 'fonnte',
                        'mode'         => 'TEST',
                        'api_url'      => 'https://api.fonnte.com/send',
                        'access_token' => '',
                        'phone_number' => $store['phone'] ?? '',
                        'enabled'      => 0,
                    ];
                }
            } else {
                // OWNER: ambil dari tabel global whatsapp_settings
                $stmt = $db->query('SELECT * FROM whatsapp_settings WHERE id = 1 LIMIT 1');
                $waSettings = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
                $isStoreConfig = false;
            }
        }
        // Mask token: tampilkan 4 char pertama + ••• + 4 char terakhir
        $rawToken    = $waSettings['access_token'] ?? '';
        $maskedToken = '';
        if (!empty($rawToken)) {
            $len = strlen($rawToken);
            $maskedToken = $len <= 8
                ? str_repeat('•', $len)
                : substr($rawToken, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($rawToken, -4);
        }

        // ── System summary data ──────────────────────────────────────────────
        $dbStatus  = $db ? 'Online' : 'Offline';
        $dbName    = Env::get('DB_DATABASE', 'marketplace_laundry');
        $appName   = Env::get('APP_NAME', 'LaundryKu');
        $appEnv    = Env::get('APP_ENV', 'production');
        $appVer    = Env::get('APP_VERSION', '1.0.0');
        $aiProvider = ucfirst(Env::get('AI_PROVIDER', 'gemini'));
        $aiModel    = Env::get('GEMINI_MODEL', Env::get('AI_MODEL', 'gemini-2.0-flash'));

        // ── Chatbot data (per toko jika Admin Toko) ─────────────────────────
        $botEnabled    = 0;
        $activeKwCount = 0;
        $totalKwCount  = 0;

        if ($db && $activeStoreId !== null) {
            $stmtBotSet = $db->prepare('SELECT enabled FROM store_whatsapp_bot_settings WHERE store_id = :sid LIMIT 1');
            $stmtBotSet->execute(['sid' => $activeStoreId]);
            $botEnabled = (int) ($stmtBotSet->fetchColumn() ?: 0);

            $stmtKwAct = $db->prepare('SELECT COUNT(*) FROM store_whatsapp_bot_responses WHERE store_id = :sid AND is_active = 1');
            $stmtKwAct->execute(['sid' => $activeStoreId]);
            $activeKwCount = (int) $stmtKwAct->fetchColumn();

            $stmtKwTot = $db->prepare('SELECT COUNT(*) FROM store_whatsapp_bot_responses WHERE store_id = :sid');
            $stmtKwTot->execute(['sid' => $activeStoreId]);
            $totalKwCount = (int) $stmtKwTot->fetchColumn();
        }

        // ── Build status badges ──────────────────────────────────────────────
        $waConf   = !empty($waSettings['access_token']);
        $waConn   = !empty($waStats['device']['connected'] ?? false);
        $waPhone  = $waStats['device']['device'] ?? ($waSettings['phone_number'] ?? '—');
        $waProvider = htmlspecialchars($waSettings['provider'] ?? 'fonnte');

        $data = [
            'pageTitle'     => 'Pusat Konfigurasi Sistem',
            'user'          => Auth::user(),
            'role'          => $role,
            'store'         => $store,
            'isGlobalAdmin' => $isGlobalAdmin,
            'isStoreConfig' => $isStoreConfig,
            'activeStoreId' => $activeStoreId,
            'csrf_token'    => Csrf::generateToken(),
            // WhatsApp
            'waStats'       => $waStats,
            'waSettings'    => $waSettings,
            'maskedToken'   => $maskedToken,
            'waIsFonnte'    => $waIsFonnte,
            'waConf'        => $waConf,
            'waConn'        => $waConn,
            'waPhone'       => $waPhone,
            'waProvider'    => $waProvider,
            // Chat Bot
            'botEnabled'    => $botEnabled,
            'activeKwCount' => $activeKwCount,
            'totalKwCount'  => $totalKwCount,
            // System
            'dbStatus'      => $dbStatus,
            'dbName'        => $dbName,
            'appName'       => $appName,
            'appEnv'        => $appEnv,
            'appVer'        => $appVer,
            'aiProvider'    => $aiProvider,
            'aiModel'       => $aiModel,
        ];

        $this->view('admin.system_config.index', $data, 'main');
    }
}

