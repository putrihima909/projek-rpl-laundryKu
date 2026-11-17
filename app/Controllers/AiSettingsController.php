<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Env;
use App\Models\Store;
use App\Services\Ai\AiHealthCheck;
use App\Services\Ai\AiSettings;
use Throwable;

class AiSettingsController extends Controller
{
    private AiSettings $aiSettings;
    private AiHealthCheck $healthCheck;

    public function __construct()
    {
        $this->aiSettings = new AiSettings();
        $this->healthCheck = new AiHealthCheck();
    }

    /**
     * Memeriksa role pengguna: diizinkan untuk Admin Toko, Owner Toko, Owner, dan Admin Owner.
     */
    private function checkAccess(): void
    {
        if (!Auth::check() || !in_array(Auth::role(), [
            Auth::ROLE_ADMIN_TOKO,
            Auth::ROLE_OWNER_TOKO,
            Auth::ROLE_OWNER,
            Auth::ROLE_ADMIN_OWNER
        ], true)) {
            $_SESSION['error'] = 'Akses ditolak: Anda tidak memiliki izin untuk mengakses halaman Konfigurasi API Chatbot.';
            $this->redirect('/dashboard');
            exit;
        }
    }

    /**
     * Memeriksa apakah role saat ini adalah pengelola toko (Admin Toko atau Owner Toko).
     */
    private function isStoreRole(): bool
    {
        return in_array(Auth::role(), [Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER_TOKO], true);
    }

    /**
     * Mendapatkan toko terkait untuk user saat ini.
     */
    private function getCurrentStore(): ?array
    {
        try {
            return (new Store())->getStoreByOwner(Auth::id());
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * GET /admin/ai-settings & /admin/store-ai-settings
     * Menampilkan halaman Konfigurasi API Chatbot (Admin Toko & Platform).
     */
    public function index(): void
    {
        $this->checkAccess();

        $isStore = $this->isStoreRole();
        $store = $isStore ? $this->getCurrentStore() : null;
        $storeId = $store ? (int)$store['id'] : 0;

        if ($isStore && $storeId <= 0) {
            // Jika akun admin toko belum memiliki toko terkait
            $_SESSION['warning'] = 'Akun Anda belum dikaitkan dengan cabang toko manapun. Hubungi Platform Owner.';
        }

        // Ambil konfigurasi: khusus toko jika admin toko, atau platform jika owner
        if ($isStore && $storeId > 0) {
            $config = $this->aiSettings->getStoreAiApiConfig($storeId);
            $provider = $config['provider'] ?? 'gemini';
            $model = $config['model'] ?? 'gemini-2.5-flash';
            $baseUrl = $config['base_url'] ?? '';
            $aiEnabled = (bool)($config['ai_enabled'] ?? true);
            $hasApiKey = !empty($config['has_api_key']);
            $keyLast4 = (string)($config['api_key_last4'] ?? '');
            $status = $config['status'] ?? 'ready';
        } else {
            $platformSettings = $this->aiSettings->getAll();
            $provider = $platformSettings['ai_provider'] ?? 'gemini';
            $model = $platformSettings['ai_model'] ?? 'gemini-2.5-flash';
            $baseUrl = $platformSettings['ai_base_url'] ?? '';
            $aiEnabled = (bool)($platformSettings['ai_enabled'] ?? true);
            $hasApiKey = (bool)($platformSettings['has_api_key'] ?? false);
            $keyLast4 = (string)($platformSettings['api_key_status']['last4'] ?? '');
            $status = ($aiEnabled && $hasApiKey) ? 'active' : ($aiEnabled ? 'ready' : 'inactive');
        }

        // Health check dasar
        $health = $this->healthCheck->run(false);

        $allowedRevealRoles = defined('AI_KEY_REVEAL_ROLES') 
            ? AI_KEY_REVEAL_ROLES 
            : \config('ai.key_reveal_roles', ['owner', 'admin_owner', 'owner_toko', 'admin_toko']);
        $canRevealKey = in_array(Auth::role() ?? '', $allowedRevealRoles, true);

        $modelPool = $this->aiSettings->getModelPool(($isStore && $storeId > 0) ? $storeId : null);

        $this->view('admin/ai_settings/index', [
            'isStore'        => $isStore,
            'store'          => $store,
            'provider'       => $provider,
            'model'          => $model,
            'baseUrl'        => $baseUrl,
            'apiBaseUrl'     => $baseUrl,
            'aiEnabled'      => $aiEnabled,
            'hasApiKey'      => $hasApiKey,
            'keyLast4'       => $keyLast4,
            'canRevealKey'   => $canRevealKey,
            'status'         => $status,
            'health'         => $health,
            'dbConnected'    => !empty($health['database']['connected']),
            'csrfToken'      => Csrf::generateToken(),
            'modelPool'      => $modelPool,
        ]);
    }

    /**
     * POST /admin/ai-settings & /admin/store-ai-settings
     * Menyimpan pembaruan konfigurasi API Chatbot.
     */
    public function save(): void
    {
        $this->checkAccess();

        // Validasi CSRF
        $token = $_POST['csrf_token'] ?? ($_POST['_csrf_token'] ?? '');
        if (!Csrf::validate($token)) {
            $_SESSION['error'] = 'Token keamanan (CSRF) tidak valid atau telah kadaluarsa. Silakan muat ulang halaman.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
            return;
        }

        $isStore = $this->isStoreRole();
        $store = $isStore ? $this->getCurrentStore() : null;
        $storeId = $store ? (int)$store['id'] : 0;

        $provider = strtolower(trim((string)($_POST['provider'] ?? 'gemini')));
        $model = trim((string)($_POST['model'] ?? ''));
        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($provider === 'gemini' && str_contains($baseUrl, 'localhost')) {
            $baseUrl = '';
        }
        $isEnabled = isset($_POST['is_enabled']) && ($_POST['is_enabled'] === '1' || $_POST['is_enabled'] === 'on');
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        $clearApiKey = isset($_POST['clear_api_key']) && ($_POST['clear_api_key'] === '1' || $_POST['clear_api_key'] === 'on');

        // Normalisasi model default jika kosong
        if ($model === '') {
            $model = match($provider) {
                'groqcloud' => 'llama-3.3-70b-versatile',
                'openai'    => 'gpt-4o-mini',
                default     => 'gemini-2.5-flash',
            };
        }

        try {
            if ($isStore && $storeId > 0) {
                // Simpan ke store_ai_settings
                $saved = $this->aiSettings->saveStoreAiApiConfig($storeId, [
                    'provider'      => $provider,
                    'model'         => $model,
                    'base_url'      => $baseUrl,
                    'ai_enabled'    => $isEnabled,
                    'api_key'       => $apiKey,
                    'clear_api_key' => $clearApiKey,
                ], Auth::id());

                if ($saved) {
                    $_SESSION['success'] = 'Konfigurasi API Chatbot untuk toko ' . htmlspecialchars($store['name']) . ' berhasil disimpan.';
                } else {
                    $_SESSION['error'] = 'Gagal menyimpan konfigurasi ke database toko.';
                }
            } else {
                // Simpan platform settings (Owner / Admin Owner)
                $this->aiSettings->set('ai_enabled', $isEnabled);
                $this->aiSettings->set('ai_provider', $provider);
                $this->aiSettings->set('ai_model', $model);
                if ($baseUrl !== '') {
                    // Simpan custom base url jika ada
                }

                if ($clearApiKey) {
                    $this->aiSettings->clearApiKey(Auth::id());
                } elseif ($apiKey !== '') {
                    $this->aiSettings->setApiKey($apiKey, Auth::id());
                }

                $_SESSION['success'] = 'Konfigurasi API Chatbot platform berhasil disimpan ke database.';
            }
        } catch (\InvalidArgumentException $e) {
            $_SESSION['error'] = 'Validasi gagal: ' . $e->getMessage();
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Gagal menyimpan konfigurasi: ' . $e->getMessage();
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
    }

    /**
     * POST /admin/ai-settings/add-model & /admin/store-ai-settings/add-model & /platform/settings/add-model
     * Menambahkan model AI baru ke failover pool.
     */
    public function addModel(): void
    {
        $this->checkAccess();

        $token = $_POST['csrf_token'] ?? ($_POST['_csrf_token'] ?? '');
        if (!Csrf::validate($token)) {
            $_SESSION['error'] = 'Token CSRF tidak valid.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
            return;
        }

        $isStore = $this->isStoreRole();
        $store = $isStore ? $this->getCurrentStore() : null;
        $storeId = $store ? (int)$store['id'] : null;

        $provider = strtolower(trim((string)($_POST['provider'] ?? 'gemini')));
        $modelName = trim((string)($_POST['model_name'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        $isPrimary = isset($_POST['is_primary']) && ($_POST['is_primary'] === '1' || $_POST['is_primary'] === 'on');

        if ($modelName === '') {
            $modelName = match($provider) {
                'groqcloud' => 'llama-3.3-70b-versatile',
                'openai'    => 'gpt-4o-mini',
                default     => 'gemini-2.5-flash',
            };
        }

        try {
            $newId = $this->aiSettings->addModelToPool([
                'provider'   => $provider,
                'model_name' => $modelName,
                'label'      => $label,
                'api_key'    => $apiKey,
                'base_url'   => $baseUrl,
                'is_primary' => $isPrimary,
            ], $storeId, Auth::id());

            if ($newId > 0) {
                $_SESSION['success'] = "Model AI baru '{$modelName}' ({$provider}) berhasil ditambahkan ke daftar model cadangan.";
            } else {
                $_SESSION['error'] = 'Gagal menyimpan model AI ke database.';
            }
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
    }

    /**
     * POST /admin/ai-settings/activate-model
     * Mengaktifkan model dari pool sebagai Primary Live Model.
     */
    public function activateModel(): void
    {
        $this->checkAccess();

        $token = $_POST['csrf_token'] ?? ($_POST['_csrf_token'] ?? '');
        if (!Csrf::validate($token)) {
            $_SESSION['error'] = 'Token CSRF tidak valid.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
            return;
        }

        $isStore = $this->isStoreRole();
        $store = $isStore ? $this->getCurrentStore() : null;
        $storeId = $store ? (int)$store['id'] : null;

        $modelId = (int)($_POST['model_id'] ?? 0);
        if ($modelId <= 0) {
            $_SESSION['error'] = 'ID Model tidak valid.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
            return;
        }

        try {
            $ok = $this->aiSettings->setPrimaryModelFromPool($modelId, $storeId, Auth::id());
            if ($ok) {
                $_SESSION['success'] = 'Model AI berhasil diaktifkan sebagai AI Agent Utama (Primary Live Model).';
            } else {
                $_SESSION['error'] = 'Gagal mengaktifkan model AI.';
            }
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
    }

    /**
     * POST /admin/ai-settings/delete-model
     * Menghapus model dari pool.
     */
    public function deleteModel(): void
    {
        $this->checkAccess();

        $token = $_POST['csrf_token'] ?? ($_POST['_csrf_token'] ?? '');
        if (!Csrf::validate($token)) {
            $_SESSION['error'] = 'Token CSRF tidak valid.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
            return;
        }

        $isStore = $this->isStoreRole();
        $store = $isStore ? $this->getCurrentStore() : null;
        $storeId = $store ? (int)$store['id'] : null;

        $modelId = (int)($_POST['model_id'] ?? 0);
        if ($modelId <= 0) {
            $_SESSION['error'] = 'ID Model tidak valid.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
            return;
        }

        try {
            $ok = $this->aiSettings->deleteModelFromPool($modelId, $storeId);
            if ($ok) {
                $_SESSION['success'] = 'Model AI cadangan berhasil dihapus dari daftar.';
            } else {
                $_SESSION['error'] = 'Gagal menghapus model AI.';
            }
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/ai-settings');
    }

    /**
     * POST /admin/ai-settings/test-connection
     * Menguji koneksi langsung ke AI provider yang dipilih menggunakan API key yang dimasukkan atau yang sudah tersimpan.
     */
    public function testConnection(): void
    {
        $this->checkAccess();

        // Validasi CSRF
        $token = $_POST['csrf_token'] ?? ($_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!Csrf::validate($token)) {
            $this->json([
                'success' => false,
                'message' => 'Token CSRF tidak valid atau telah kadaluarsa.'
            ], 403);
            return;
        }

        // Rate limit tes koneksi (cooldown 5 detik per sesi)
        $now = time();
        $lastTest = (int) ($_SESSION['last_ai_test_connection_time'] ?? 0);
        if (($now - $lastTest) < 5) {
            $wait = 5 - ($now - $lastTest);
            $this->json([
                'success' => false,
                'message' => "Mohon tunggu {$wait} detik sebelum melakukan tes koneksi kembali."
            ], 429);
            return;
        }
        $_SESSION['last_ai_test_connection_time'] = $now;

        $provider = strtolower(trim((string)($_POST['provider'] ?? 'gemini')));
        $model = trim((string)($_POST['model'] ?? 'gemini-2.5-flash'));
        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($provider === 'gemini' && str_contains($baseUrl, 'localhost')) {
            $baseUrl = '';
        }
        $formKey = trim((string)($_POST['api_key'] ?? ''));

        // Jika formKey kosong, ambil key tersimpan dari database (toko atau platform)
        $resolvedKey = null;
        if ($formKey !== '') {
            $resolvedKey = $formKey;
        } else {
            if ($this->isStoreRole()) {
                $store = $this->getCurrentStore();
                if ($store && !empty($store['id'])) {
                    $resolvedKey = $this->aiSettings->getStoreDecryptedApiKey((int)$store['id']);
                }
            }
            if ($resolvedKey === null) {
                $resolvedKey = $this->aiSettings->getDecryptedApiKey();
            }
        }

        if (empty($resolvedKey) && $provider !== 'mock') {
            $this->json([
                'success' => false,
                'model'   => $model,
                'message' => 'API Key belum diisi pada form dan belum tersimpan di database. Masukkan API Key terlebih dahulu.'
            ], 200);
            return;
        }

        // Lakukan pengujian langsung melalui AiHealthCheck
        $res = $this->healthCheck->testProvider(Auth::id(), $provider, $model, $resolvedKey, $baseUrl);

        $this->json([
            'success' => (bool)$res['ok'],
            'model'   => $res['model'] ?? $model,
            'latency' => ($res['ms'] ?? 0) . 'ms',
            'message' => $res['pesan'] ?? ($res['ok'] ? 'Koneksi berhasil.' : 'Koneksi gagal.')
        ], 200);
    }

    /**
     * Helper untuk mengirim respons JSON aman dengan header no-store / no-cache.
     */
    protected function revealJsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload);
        if (!defined('TEST_MODE') || !TEST_MODE) {
            exit;
        }
    }

    /**
     * POST /konfigurasi-ai/lihat-key & /admin/ai-settings/reveal-key
     * Melihat/mendekripsi API key dengan verifikasi password dan perlindungan ketat.
     */
    public function revealKey(): void
    {
        // 1. Verifikasi Login Wajib
        if (!Auth::check()) {
            $this->revealJsonResponse([
                'ok' => false,
                'message' => 'Sesi Anda telah berakhir. Silakan login kembali.'
            ], 401);
            return;
        }

        // 2. Verifikasi Method Wajib POST
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            $this->revealJsonResponse([
                'ok' => false,
                'message' => 'Metode permintaan tidak valid. Hanya POST yang diizinkan.'
            ], 405);
            return;
        }

        // 3. Verifikasi CSRF Wajib
        $token = $_POST['csrf_token'] ?? ($_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!Csrf::validate($token)) {
            $this->revealJsonResponse([
                'ok' => false,
                'message' => 'Token keamanan (CSRF) tidak valid atau telah kadaluarsa.'
            ], 403);
            return;
        }

        $userId = (int) Auth::id();
        $userRole = Auth::role() ?? '';
        $db = Database::getInstance();

        // 4. Role Authorization Check (dari konstanta AI_KEY_REVEAL_ROLES / config)
        $allowedRoles = defined('AI_KEY_REVEAL_ROLES') 
            ? AI_KEY_REVEAL_ROLES 
            : \config('ai.key_reveal_roles', ['owner', 'admin_owner', 'owner_toko', 'admin_toko']);

        if (!in_array($userRole, $allowedRoles, true)) {
            $this->revealJsonResponse([
                'ok' => false,
                'message' => 'Peran akun Anda tidak memiliki hak akses untuk melihat API key.'
            ], 403);
            return;
        }

        // 5. Cakupan & Toko (Ditentukan dari sesi server, abaikan store_id dari input)
        $rawScope = strtolower(trim((string)($_POST['cakupan'] ?? ($_POST['scope'] ?? ''))));
        $isStoreStaff = $this->isStoreRole();

        // Tentukan cakupan efektif: staf toko otomatis cakupan 'toko'
        if ($isStoreStaff) {
            $scope = 'toko';
            // Jika role staf toko secara eksplisit meminta cakupan platform: tolak!
            if ($rawScope === 'platform') {
                $this->revealJsonResponse([
                    'ok' => false,
                    'message' => 'Akses ditolak: Staf toko tidak diizinkan mengakses kredensial platform.'
                ], 403);
                return;
            }
        } else {
            // Role platform (owner, admin_owner)
            $scope = ($rawScope === 'toko') ? 'toko' : 'platform';
        }

        $storeId = 0;
        if ($scope === 'toko') {
            $store = $this->getCurrentStore();
            if (!$store || empty($store['id'])) {
                $this->revealJsonResponse([
                    'ok' => false,
                    'message' => 'Toko terkait akun Anda tidak ditemukan.'
                ], 404);
                return;
            }
            $storeId = (int)$store['id'];
        }

        // 6. Rate Limiting 5 Percobaan Salah per User per 10 Menit (ai_key_reveal_attempts)
        if ($db) {
            $db->exec("CREATE TABLE IF NOT EXISTS ai_key_reveal_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45) NULL,
                attempted_at DATETIME NOT NULL,
                INDEX idx_user_attempt (user_id, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM ai_key_reveal_attempts 
                WHERE user_id = :uid AND attempted_at >= (NOW() - INTERVAL 10 MINUTE)");
            $stmtCheck->execute(['uid' => $userId]);
            $failedAttempts = (int) $stmtCheck->fetchColumn();

            if ($failedAttempts >= 5) {
                $this->revealJsonResponse([
                    'ok' => false,
                    'message' => 'Terlalu banyak percobaan salah. Akses melihat kunci dibatasi untuk sementara. Silakan coba kembali dalam 10 menit.'
                ], 429);
                return;
            }
        }

        // 7. Verifikasi Password dengan password_verify terhadap hash user login
        $password = (string)($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmtUser = $db ? $db->prepare("SELECT password FROM users WHERE id = :uid LIMIT 1") : null;
        if ($stmtUser) {
            $stmtUser->execute(['uid' => $userId]);
            $userHash = (string) $stmtUser->fetchColumn();
        } else {
            $userHash = '';
        }

        $isPasswordValid = $userHash !== '' && password_verify($password, $userHash);

        if (empty($password) || empty($userHash) || !$isPasswordValid) {
            // Catat percobaan gagal ke database
            if ($db) {
                $stmtFail = $db->prepare("INSERT INTO ai_key_reveal_attempts (user_id, ip_address, attempted_at) VALUES (:uid, :ip, NOW())");
                $stmtFail->execute(['uid' => $userId, 'ip' => $ip]);
            }

            $this->revealJsonResponse([
                'ok' => false,
                'message' => 'Password yang Anda masukkan tidak valid.'
            ], 401);
            return;
        }

        // Password benar: bersihkan catatan percobaan salah untuk user ini
        if ($db) {
            $stmtClear = $db->prepare("DELETE FROM ai_key_reveal_attempts WHERE user_id = :uid");
            $stmtClear->execute(['uid' => $userId]);
        }

        // 8. Cek APP_KEY & Dekripsi API Key (AES-256-GCM via AiSettings)
        $appKey = (string) Env::get('APP_KEY', '');
        if (trim($appKey) === '' || strlen(trim($appKey)) < 32) {
            $fallbackSecret = (string) Env::get('PAYMENT_MOCK_SECRET', 'smartlaundry_pro_secret_key_32bytes_min_2026');
            if (strlen($fallbackSecret) >= 32) {
                $appKey = hash('sha256', $fallbackSecret . '_ai_encryption_salt');
            }
        }
        if (trim($appKey) === '' || strlen(trim($appKey)) < 32) {
            $this->revealJsonResponse([
                'ok' => false,
                'message' => 'Kunci API tidak dapat didekripsi atau belum dikonfigurasi.'
            ], 400);
            return;
        }

        $decryptedKey = null;
        try {
            if ($scope === 'platform') {
                $decryptedKey = $this->aiSettings->getDecryptedApiKey();
            } else {
                $decryptedKey = $this->aiSettings->getStoreDecryptedApiKey($storeId);
            }
        } catch (\Throwable $e) {
            $decryptedKey = null;
        }

        if ($decryptedKey === null || trim($decryptedKey) === '') {
            $this->revealJsonResponse([
                'ok' => false,
                'message' => 'Kunci API tidak dapat didekripsi atau belum dikonfigurasi.'
            ], 404);
            return;
        }

        // 9. Tulis Audit ke ai_settings_audit (setting_key = 'lihat_api_key', user, role, cakupan, IP)
        // JANGAN menyimpan key di audit, di app.log, atau di pesan error!
        $this->aiSettings->recordAudit(
            'lihat_api_key',
            null,
            "Akses lihat API key [cakupan: {$scope}]",
            $userId,
            $userRole,
            ($scope === 'toko' ? $storeId : null)
        );

        // 10. Kembalikan JSON {ok: true, key: "...", detik: 30}
        $this->revealJsonResponse([
            'ok'    => true,
            'key'   => $decryptedKey,
            'detik' => 30
        ], 200);
    }
}
