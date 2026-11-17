<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Models\Store;

/**
 * WhatsAppBotController
 * Manajemen Chat Bot WhatsApp per Toko (Khusus Admin Toko).
 *
 * RBAC:
 *  - ADMIN_TOKO : Mengelola bot untuk toko miliknya sendiri
 *  - OWNER / ADMIN_OWNER : Dapat memantau/mengakses jika toko aktif dipilih
 *  - Lainnya : Ditolak
 */
class WhatsAppBotController extends Controller
{
    private const ALLOWED_ROLES = [
        Auth::ROLE_ADMIN_TOKO,
        Auth::ROLE_OWNER,
        Auth::ROLE_ADMIN_OWNER,
    ];

    /**
     * Validasi akses pengguna.
     */
    private function requireAccess(): void
    {
        if (!Auth::check() || !Auth::hasRole(self::ALLOWED_ROLES)) {
            Session::flash('error', 'Akses ditolak: Hanya Admin Toko yang dapat mengelola Chat Bot WhatsApp Toko.');
            $this->redirect(Auth::getRedirectUrl());
            exit;
        }
    }

    /**
     * Dapatkan store_id terverifikasi dari session pengguna.
     * Untuk Owner/Admin Owner: dapat memilih toko via ?store_id=X atau toko pertama.
     */
    private function getVerifiedStoreId(): int
    {
        $this->requireAccess();

        $user = Auth::user();
        $isGlobalAdmin = Auth::hasRole([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER]);

        if ($isGlobalAdmin) {
            $requestedStoreId = !empty($_GET['store_id']) ? (int) $_GET['store_id'] : null;
            if (!$requestedStoreId && !empty($_POST['target_store_id'])) {
                $requestedStoreId = (int) $_POST['target_store_id'];
            }
            if ($requestedStoreId) {
                return $requestedStoreId;
            }
            // Fallback: ambil toko pertama yang aktif
            $storeModel = new Store();
            $stores = $storeModel->getActiveStoresFiltered();
            if (!empty($stores[0]['id'])) {
                return (int) $stores[0]['id'];
            }
        }

        $storeId = !empty($user['store_id']) ? (int) $user['store_id'] : null;

        if (!$storeId) {
            $storeModel = new Store();
            $store = $storeModel->getStoreByOwner(Auth::id());
            if ($store) {
                $storeId = (int) $store['id'];
            }
        }

        if (!$storeId) {
            Session::flash('error', 'Akun Anda belum dikaitkan dengan toko mana pun.');
            $this->redirect(Auth::getRedirectUrl());
            exit;
        }

        return $storeId;
    }

    /**
     * GET /admin/whatsapp-bot
     * Halaman manajemen keyword dan respons chatbot toko.
     */
    public function index(): void
    {
        $storeId = $this->getVerifiedStoreId();
        $db = Database::getInstance();

        // Ambil info toko
        $storeModel = new Store();
        $store = $storeModel->getStoreById($storeId);

        // Ambil pengaturan bot toko
        $stmtSettings = $db->prepare('SELECT * FROM store_whatsapp_bot_settings WHERE store_id = :sid LIMIT 1');
        $stmtSettings->execute(['sid' => $storeId]);
        $botSettings = $stmtSettings->fetch(\PDO::FETCH_ASSOC);

        if (!$botSettings) {
            $botSettings = [
                'store_id'         => $storeId,
                'enabled'          => 0,
                'fallback_message' => 'Halo! Terima kasih telah menghubungi kami. Pesan Anda akan segera dibalas oleh staf kami.',
            ];
        }

        // Ambil daftar keyword responses toko
        $stmtResponses = $db->prepare('SELECT * FROM store_whatsapp_bot_responses WHERE store_id = :sid ORDER BY id DESC');
        $stmtResponses->execute(['sid' => $storeId]);
        $responses = $stmtResponses->fetchAll(\PDO::FETCH_ASSOC);

        // Hitung statistik
        $totalResponses = count($responses);
        $activeKeywords = 0;
        foreach ($responses as $r) {
            if (!empty($r['is_active'])) {
                $activeKeywords++;
            }
        }

        // Ambil semua toko jika Owner untuk selector toko
        $allStores = [];
        $isGlobalAdmin = Auth::hasRole([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER]);
        if ($isGlobalAdmin) {
            $allStores = $storeModel->getActiveStoresFiltered();
        }

        $data = [
            'pageTitle'      => 'Chat Bot WhatsApp Toko — ' . htmlspecialchars($store['name'] ?? 'Toko'),
            'user'           => Auth::user(),
            'role'           => Auth::role(),
            'store'          => $store,
            'isGlobalAdmin'  => $isGlobalAdmin,
            'allStores'      => $allStores,
            'botSettings'    => $botSettings,
            'responses'      => $responses,
            'totalResponses' => $totalResponses,
            'activeKeywords' => $activeKeywords,
            'csrf_token'     => Csrf::generateToken(),
        ];

        $this->view('admin.whatsapp_bot.index', $data, 'main');
    }

    /**
     * POST /admin/whatsapp-bot/settings
     * Simpan status aktif bot dan pesan fallback.
     */
    public function saveSettings(): void
    {
        $storeId = $this->getVerifiedStoreId();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        $fallbackMessage = trim($_POST['fallback_message'] ?? '');
        $userId = Auth::id();

        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO store_whatsapp_bot_settings
                (store_id, enabled, fallback_message, updated_by, updated_at)
            VALUES
                (:sid, :enabled, :fallback, :uid, NOW())
            ON DUPLICATE KEY UPDATE
                enabled          = VALUES(enabled),
                fallback_message = VALUES(fallback_message),
                updated_by       = VALUES(updated_by),
                updated_at       = NOW()
        ');

        $stmt->execute([
            'sid'      => $storeId,
            'enabled'  => $enabled,
            'fallback' => $fallbackMessage,
            'uid'      => $userId,
        ]);

        Session::flash('success', 'Pengaturan Chat Bot berhasil diperbarui.');
        $this->redirect('/admin/whatsapp-bot');
    }

    /**
     * POST /admin/whatsapp-bot/responses/store
     * Menambahkan keyword + balasan baru.
     */
    public function store(): void
    {
        $storeId = $this->getVerifiedStoreId();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $keyword  = strtolower(trim($_POST['keyword'] ?? ''));
        $response = trim($_POST['response'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $userId   = Auth::id();

        if (empty($keyword) || empty($response)) {
            Session::flash('error', 'Keyword dan balasan otomatis wajib diisi.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $db = Database::getInstance();

        // Cek duplikasi keyword pada toko ini
        $stmtCheck = $db->prepare('SELECT id FROM store_whatsapp_bot_responses WHERE store_id = :sid AND keyword = :kw LIMIT 1');
        $stmtCheck->execute(['sid' => $storeId, 'kw' => $keyword]);
        if ($stmtCheck->fetch()) {
            Session::flash('error', "Keyword '{$keyword}' sudah terdaftar untuk toko Anda. Silakan edit respons yang ada.");
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $stmt = $db->prepare('
            INSERT INTO store_whatsapp_bot_responses
                (store_id, keyword, response, is_active, updated_by, created_at, updated_at)
            VALUES
                (:sid, :kw, :resp, :active, :uid, NOW(), NOW())
        ');
        $stmt->execute([
            'sid'    => $storeId,
            'kw'     => $keyword,
            'resp'   => $response,
            'active' => $isActive,
            'uid'    => $userId,
        ]);

        Session::flash('success', "Keyword '{$keyword}' berhasil ditambahkan ke Chat Bot.");
        $this->redirect('/admin/whatsapp-bot');
    }

    /**
     * POST /admin/whatsapp-bot/responses/update
     * Memperbarui keyword + response + status.
     */
    public function update(): void
    {
        $storeId = $this->getVerifiedStoreId();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $id       = (int) ($_POST['id'] ?? 0);
        $keyword  = strtolower(trim($_POST['keyword'] ?? ''));
        $response = trim($_POST['response'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $userId   = Auth::id();

        if ($id <= 0 || empty($keyword) || empty($response)) {
            Session::flash('error', 'Data input tidak lengkap.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $db = Database::getInstance();

        // Pastikan response adalah milik toko yang sedang login
        $stmtCheck = $db->prepare('SELECT id FROM store_whatsapp_bot_responses WHERE id = :id AND store_id = :sid LIMIT 1');
        $stmtCheck->execute(['id' => $id, 'sid' => $storeId]);
        if (!$stmtCheck->fetch()) {
            Session::flash('error', 'Data respons tidak ditemukan atau Anda tidak memiliki akses.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        // Cek jika keyword diubah ke keyword yang sudah ada di toko ini
        $stmtDup = $db->prepare('SELECT id FROM store_whatsapp_bot_responses WHERE store_id = :sid AND keyword = :kw AND id != :id LIMIT 1');
        $stmtDup->execute(['sid' => $storeId, 'kw' => $keyword, 'id' => $id]);
        if ($stmtDup->fetch()) {
            Session::flash('error', "Keyword '{$keyword}' sudah digunakan pada respons lain.");
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $stmt = $db->prepare('
            UPDATE store_whatsapp_bot_responses
            SET keyword    = :kw,
                response   = :resp,
                is_active  = :active,
                updated_by = :uid,
                updated_at = NOW()
            WHERE id = :id AND store_id = :sid
        ');
        $stmt->execute([
            'kw'     => $keyword,
            'resp'   => $response,
            'active' => $isActive,
            'uid'    => $userId,
            'id'     => $id,
            'sid'    => $storeId,
        ]);

        Session::flash('success', "Keyword '{$keyword}' berhasil diperbarui.");
        $this->redirect('/admin/whatsapp-bot');
    }

    /**
     * POST /admin/whatsapp-bot/responses/delete
     * Menghapus keyword.
     */
    public function delete(): void
    {
        $storeId = $this->getVerifiedStoreId();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'ID respons tidak valid.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM store_whatsapp_bot_responses WHERE id = :id AND store_id = :sid');
        $stmt->execute(['id' => $id, 'sid' => $storeId]);

        Session::flash('success', 'Keyword dan respons otomatis berhasil dihapus.');
        $this->redirect('/admin/whatsapp-bot');
    }

    /**
     * POST /admin/whatsapp-bot/responses/toggle
     * Toggle status aktif/nonaktif keyword.
     */
    public function toggle(): void
    {
        $storeId = $this->getVerifiedStoreId();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'ID respons tidak valid.');
            $this->redirect('/admin/whatsapp-bot');
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE store_whatsapp_bot_responses
            SET is_active  = IF(is_active = 1, 0, 1),
                updated_at = NOW()
            WHERE id = :id AND store_id = :sid
        ');
        $stmt->execute(['id' => $id, 'sid' => $storeId]);

        Session::flash('success', 'Status keyword berhasil diperbarui.');
        $this->redirect('/admin/whatsapp-bot');
    }
}

