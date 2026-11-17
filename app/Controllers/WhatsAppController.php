<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\PhoneHelper;
use App\Helpers\Session;
use App\Models\Store;
use App\Services\Finance\OmzetTransparencyService;
use App\Services\WhatsApp\Providers\FonnteWhatsAppClient;
use App\Services\WhatsApp\Providers\CloudApiWhatsAppClient;
use App\Services\WhatsApp\WhatsAppNotificationService;
use App\Services\WhatsApp\WhatsAppTemplate;

class WhatsAppController extends Controller
{
    protected WhatsAppNotificationService $waService;

    public function __construct()
    {
        $this->waService = new WhatsAppNotificationService();
    }

    /**
     * Helper to resolve store ID for store-level roles like admin_toko.
     */
    protected function getActiveStoreId(): ?int
    {
        if (Auth::hasRole(Auth::ROLE_ADMIN_TOKO)) {
            $user = Auth::user();
            if (!empty($user['store_id'])) {
                return (int)$user['store_id'];
            }
            $storeModel = new Store();
            $store = $storeModel->getStoreByOwner(Auth::id());
            return $store ? (int)$store['id'] : null;
        }
        return null;
    }

    /**
     * GET /admin/whatsapp
     * WhatsApp Gateway Management & Monitoring Dashboard.
     */
    public function index(): void
    {
        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $settings = null;
        if ($db) {
            $stmt = $db->query("SELECT * FROM whatsapp_settings WHERE id = 1 LIMIT 1");
            $settings = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        }

        if (!$settings) {
            $settings = [
                'provider' => config('whatsapp.provider', 'fonnte'),
                'mode' => env('WHATSAPP_MODE', 'TEST'),
                'phone_number_id' => '',
                'access_token' => config('whatsapp.fonnte.token', ''),
                'business_account_id' => '',
                'phone_number' => '081234567890',
            ];
        }

        $activeStoreId = $this->getActiveStoreId();
        $stats = $this->waService->getStats($activeStoreId);
        $logs = $this->waService->getLogs(50, 0, $activeStoreId);

        $client = $this->waService->getClient();
        $isFonnte = ($client instanceof FonnteWhatsAppClient);
        $deviceInfo = $isFonnte ? $client->checkDeviceStatus() : null;

        // Mask token for safe UI display
        $rawToken = $settings['access_token'] ?? '';
        $maskedToken = '';
        if (!empty($rawToken)) {
            $len = strlen($rawToken);
            if ($len <= 8) {
                $maskedToken = '••••••••';
            } else {
                $maskedToken = substr($rawToken, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($rawToken, -4);
            }
        }

        // Summary omset data for WhatsApp Omset Report feature
        $omzetService = new OmzetTransparencyService($db);
        $omzetSummary = [
            'today' => $omzetService->getDetailedReport($activeStoreId, ['period' => 'today'])['summary'] ?? [],
            'week'  => $omzetService->getDetailedReport($activeStoreId, ['period' => '7days'])['summary'] ?? [],
            'month' => $omzetService->getDetailedReport($activeStoreId, ['period' => 'this_month'])['summary'] ?? [],
        ];

        // Fetch store name if admin_toko, or all stores for owner
        $storeInfo = null;
        $allStores = [];
        $storeModel = new Store();
        if ($activeStoreId !== null) {
            $storeInfo = $storeModel->getStoreById($activeStoreId);
        } else {
            $allStores = $storeModel->getActiveStoresFiltered();
        }

        $isGlobalAdmin = Auth::hasRole([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER]);
        $quickTemplates = WhatsAppTemplate::getQuickTemplates();

        $data = [
            'pageTitle'      => 'WhatsApp Gateway - Multi-Channel API',
            'stats'          => $stats,
            'logs'           => $logs,
            'client'         => $client,
            'isFonnte'       => $isFonnte,
            'deviceInfo'     => $deviceInfo,
            'settings'       => $settings,
            'maskedToken'    => $maskedToken,
            'omzetSummary'   => $omzetSummary,
            'activeStoreId'  => $activeStoreId,
            'storeInfo'      => $storeInfo,
            'allStores'      => $allStores,
            'quickTemplates' => $quickTemplates,
            'isGlobalAdmin'  => $isGlobalAdmin,
            'csrf_token'     => Csrf::generateToken(),
            'user'           => Auth::user(),
            'role'           => Auth::role(),
        ];

        $this->view('whatsapp.index', $data, 'main');
    }

    /**
     * POST /admin/whatsapp/settings
     * Save WhatsApp credentials and operational mode.
     * Restricted strictly to Platform Owner & Admin Owner.
     */
    public function saveSettings(): void
    {
        if (!Auth::hasRole([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])) {
            Session::flash('error', 'Akses ditolak! Admin Toko tidak memiliki izin mengubah pengaturan global WhatsApp.');
            $this->redirect('/admin/whatsapp');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp');
            return;
        }

        $mode = strtoupper(trim($_POST['mode'] ?? 'TEST'));
        if (!in_array($mode, ['TEST', 'PRODUCTION'], true)) {
            $mode = 'TEST';
        }

        $phoneNumberId = trim($_POST['phone_number_id'] ?? '');
        $businessAccountId = trim($_POST['business_account_id'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');
        $newToken = trim($_POST['access_token'] ?? '');

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        if ($db) {
            // If new token is empty or only dots, keep previous token
            $currentToken = '';
            $checkStmt = $db->query("SELECT access_token FROM whatsapp_settings WHERE id = 1 LIMIT 1");
            if ($checkStmt) {
                $currentToken = (string)$checkStmt->fetchColumn();
            }

            $finalToken = (!empty($newToken) && !str_contains($newToken, '•')) ? $newToken : $currentToken;

            $stmt = $db->prepare("
                INSERT INTO whatsapp_settings (id, provider, mode, phone_number_id, access_token, business_account_id, phone_number)
                VALUES (1, 'fonnte', :mode, :pid, :token, :baid, :phone)
                ON DUPLICATE KEY UPDATE 
                    provider = 'fonnte',
                    mode = :mode2,
                    phone_number_id = :pid2,
                    access_token = :token2,
                    business_account_id = :baid2,
                    phone_number = :phone2
            ");

            $stmt->execute([
                'mode'   => $mode,
                'pid'    => $phoneNumberId,
                'token'  => $finalToken,
                'baid'   => $businessAccountId,
                'phone'  => $phoneNumber,
                'mode2'  => $mode,
                'pid2'   => $phoneNumberId,
                'token2' => $finalToken,
                'baid2'  => $businessAccountId,
                'phone2' => $phoneNumber,
            ]);

            Session::flash('success', "Pengaturan WhatsApp berhasil diperbarui! Mode aktif: {$mode}. Token rahasia tersimpan dengan aman.");
        } else {
            Session::flash('error', 'Koneksi database tidak tersedia.');
        }

        $this->redirect('/admin/whatsapp');
    }

    /**
     * POST /admin/whatsapp/send-omzet-report
     * Send structured financial report via WhatsApp to Platform Owner/Admin Owner.
     * Always sends via Fonnte client directly (bypasses DB mode check).
     * Saves actual HTTP status + Fonnte response to whatsapp_logs.
     */
    public function sendOmzetReport(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp');
            return;
        }

        $period = trim($_POST['period'] ?? 'today');
        $validPeriods = ['today' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan'];
        $periodLabel = $validPeriods[$period] ?? 'Harian';

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        $omzetService = new OmzetTransparencyService($db);
        $periodKey = match ($period) {
            'week'  => '7days',
            'month' => 'this_month',
            default => 'today'
        };

        $activeStoreId = $this->getActiveStoreId();
        $report = $omzetService->getDetailedReport($activeStoreId, ['period' => $periodKey]);
        $summary = $report['summary'] ?? [];
        $totalOmset = (float)($summary['total_omset'] ?? 0);
        $totalTx    = (int)($summary['total_transaksi'] ?? 0);
        $rataRata   = (float)($summary['rata_rata'] ?? 0);

        $currentUser = Auth::user();
        $targetPhone = trim($_POST['recipient_phone'] ?? ($currentUser['phone'] ?? '081234567890'));
        $normalizedPhone = PhoneHelper::normalize($targetPhone) ?: preg_replace('/\D/', '', $targetPhone);

        $storePrefix = '';
        if ($activeStoreId !== null) {
            $storeModel = new Store();
            $st = $storeModel->getStoreById($activeStoreId);
            if ($st) {
                $storePrefix = " Outlet: " . ($st['name'] ?? 'Toko') . "\n";
            }
        }

        $dateStr = date('d-m-Y H:i');
        $msgBody = "*LAPORAN OMSET {$periodLabel} - LAUNDRYKU*\n"
                 . $storePrefix
                 . "Tanggal: {$dateStr}\n"
                 . "-----------------------------------\n"
                 . "Total Omset: Rp " . number_format($totalOmset, 0, ',', '.') . "\n"
                 . "Jumlah Transaksi: {$totalTx} Transaksi\n"
                 . "Rata-rata: Rp " . number_format($rataRata, 0, ',', '.') . "\n"
                 . "Status: Terverifikasi Lunas Realtime\n"
                 . "-----------------------------------\n"
                 . "Laporan ini digenerate resmi oleh platform LaundryKu.";

        // Always send via Fonnte client directly — do NOT rely on DB mode setting.
        // FonnteWhatsAppClient reads token from config (env), never hardcoded.
        $client = $this->waService->getClient();
        $res = $client->sendMessage($normalizedPhone, $msgBody, [
            'event_type' => 'omzet_report',
            'user_id'    => Auth::id(),
        ]);

        $isSuccess     = !empty($res['success']);
        $finalStatus   = $isSuccess ? 'sent' : 'failed';
        $httpCode      = $res['http_code'] ?? null;
        $providerResp  = $res['response']  ?? ['error' => $res['error'] ?? 'No response'];
        $providerMsgId = $res['message_id'] ?? null;

        // Strip any sensitive keys before logging
        if (is_array($providerResp)) {
            unset($providerResp['access_token'], $providerResp['token'], $providerResp['api_key']);
        }

        // Write to whatsapp_logs with actual status + response
        if ($db) {
            $stmt = $db->prepare("
                INSERT INTO whatsapp_logs
                    (user_id, store_id, recipient_phone, recipient_role, event_type, message,
                     status, http_code, provider, provider_message_id, provider_response,
                     attempts, idempotency_key, sent_at, created_at)
                VALUES
                    (:uid, :sid, :phone, :role, 'omzet_report', :msg,
                     :st, :hcode, 'fonnte', :pmid, :presp,
                     1, :idem, :sat, NOW())
            ");
            $stmt->execute([
                'uid'   => Auth::id() ?: 1,
                'sid'   => $activeStoreId,
                'phone' => $normalizedPhone,
                'role'  => Auth::role() ?: 'owner',
                'msg'   => $msgBody,
                'st'    => $finalStatus,
                'hcode' => $httpCode,
                'pmid'  => $providerMsgId,
                'presp' => json_encode($providerResp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'idem'  => 'omzet_report:' . uniqid(),
                'sat'   => $isSuccess ? date('Y-m-d H:i:s') : null,
            ]);
        }

        if ($isSuccess) {
            $msgId = $res['message_id'] ?? '-';
            Session::flash('success', "Laporan Omset {$periodLabel} berhasil dikirim via Fonnte ke {$targetPhone}! (ID: {$msgId})");
        } else {
            $errDetail = $res['error'] ?? 'Respons tidak diketahui dari Fonnte API.';
            Session::flash('error', "Gagal mengirim Laporan Omset via Fonnte (HTTP {$httpCode}): {$errDetail}");
        }

        $this->redirect('/admin/whatsapp');
    }

    /**
     * POST /admin/whatsapp/test-send
     * Send WhatsApp test message via Fonnte directly.
     *
     * When POST param `force_send=1` OR when DB mode = PRODUCTION:
     *   → calls FonnteWhatsAppClient, logs actual status (sent/failed) + response
     * When DB mode = TEST and no force_send:
     *   → simulates send, logs test_sent with provider_response note
     */
    public function testSend(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp');
            return;
        }

        $phone = trim($_POST['phone'] ?? '');
        $customMessage = trim($_POST['message'] ?? '');
        $forceSend = !empty($_POST['force_send']) && $_POST['force_send'] === '1';

        if (empty($phone)) {
            Session::flash('error', 'Nomor WhatsApp tujuan wajib diisi.');
            $this->redirect('/admin/whatsapp');
            return;
        }

        if (!PhoneHelper::isValid($phone)) {
            Session::flash('error', "Format nomor telepon '{$phone}' tidak valid. Gunakan format ponsel Indonesia (08... atau 628...).");
            $this->redirect('/admin/whatsapp');
            return;
        }

        $normalizedPhone = PhoneHelper::normalize($phone);

        // Resolve store name for message placeholder sanitization
        $activeStoreId = $this->getActiveStoreId();
        $storeName = 'LaundryKu';
        $storeModel = new Store();
        if ($activeStoreId !== null) {
            $st = $storeModel->getStoreById($activeStoreId);
            if ($st) {
                $storeName = $st['name'] ?? 'LaundryKu';
            }
        } elseif (!empty($_POST['store_name'])) {
            $storeName = trim($_POST['store_name']);
        }

        // Clean and replace any remaining placeholders if user submitted raw template text
        $body = !empty($customMessage) ? $customMessage : "Halo!\n\nIni adalah pesan uji coba dari sistem SMART LAUNDRY (LaundryKu) yang dikirim secara resmi.\n\nWaktu kirim: " . date('Y-m-d H:i:s') . "\nStatus: Berhasil terhubung.";
        $body = WhatsAppTemplate::replacePlaceholders($body, [
            'nama_pelanggan' => trim($_POST['nama_pelanggan'] ?? ''),
            'kode_pesanan'   => trim($_POST['kode_pesanan'] ?? ''),
            'nama_toko'      => $storeName,
            'total'          => trim($_POST['total'] ?? ''),
            'tanggal'        => date('d/m/Y'),
        ]);

        // Validate message length (max 2000 chars)
        if (mb_strlen($body) > 2000) {
            Session::flash('error', 'Panjang isi pesan WhatsApp melebihi batas maksimal (maks. 2000 karakter).');
            $this->redirect('/admin/whatsapp');
            return;
        }

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {}

        // Read mode from DB (TEST or PRODUCTION)
        $mode = 'TEST';
        if ($db) {
            $stmt = $db->query("SELECT mode FROM whatsapp_settings WHERE id = 1 LIMIT 1");
            if ($stmt) {
                $m = $stmt->fetchColumn();
                if ($m) $mode = strtoupper($m);
            }
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        $activeStoreId = $this->getActiveStoreId();

        // Use real Fonnte send if: mode=PRODUCTION, OR user explicitly chose force_send
        $useRealSend = ($mode === 'PRODUCTION') || $forceSend;

        if (!$useRealSend) {
            // TEST MODE simulation: log test_sent with a note, no real HTTP call
            $providerRespNote = json_encode([
                'note'   => 'TEST MODE — pesan tidak dikirim ke Fonnte. Aktifkan PRODUCTION mode atau centang "Kirim Langsung ke Fonnte" untuk pengiriman nyata.',
                'mode'   => $mode,
                'target' => $normalizedPhone,
            ], JSON_UNESCAPED_UNICODE);

            if ($db) {
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_logs
                        (user_id, store_id, recipient_phone, recipient_role, event_type, message,
                         status, http_code, provider, provider_response, attempts, idempotency_key, sent_at, created_at)
                    VALUES
                        (:uid, :sid, :phone, :role, 'manual_test_message', :msg,
                         'test_sent', NULL, 'fonnte', :presp, 1, :idem, NOW(), NOW())
                ");
                $stmt->execute([
                    'uid'   => Auth::id() ?: 1,
                    'sid'   => $activeStoreId,
                    'phone' => $normalizedPhone,
                    'role'  => Auth::role() ?: 'owner',
                    'msg'   => $body,
                    'presp' => $providerRespNote,
                    'idem'  => 'manual_test:' . uniqid(),
                ]);
            }

            $successNotice = "Pesan uji diproses dalam [TEST MODE] ke " . PhoneHelper::formatInternational($phone) . ". Log tercatat: TEST_SENT. Gunakan 'Kirim Langsung ke Fonnte' untuk pengiriman nyata.";
            if ($isAjax) {
                $this->json(['status' => 'success', 'message' => $successNotice, 'mode' => 'TEST']);
                return;
            }
            Session::flash('success', $successNotice);
        } else {
            // REAL SEND via FonnteWhatsAppClient — token dari config/.env, tidak hardcode
            $client = $this->waService->getClient();
            $res = $client->sendMessage($normalizedPhone, $body, [
                'event_type'  => 'manual_test_message',
                'sender_id'   => Auth::id(),
                'sender_name' => Auth::user()['name'] ?? 'Admin',
            ]);

            $isSuccess     = !empty($res['success']);
            $finalStatus   = $isSuccess ? 'sent' : 'failed';
            $httpCode      = $res['http_code'] ?? null;
            $providerResp  = $res['response']  ?? ['error' => $res['error'] ?? 'No response'];
            $providerMsgId = $res['message_id'] ?? null;

            // Strip any sensitive keys before logging
            if (is_array($providerResp)) {
                unset($providerResp['access_token'], $providerResp['token'], $providerResp['api_key']);
            }

            // Write actual result to whatsapp_logs
            if ($db) {
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_logs
                        (user_id, store_id, recipient_phone, recipient_role, event_type, message,
                         status, http_code, provider, provider_message_id, provider_response,
                         attempts, idempotency_key, sent_at, created_at)
                    VALUES
                        (:uid, :sid, :phone, :role, 'manual_test_message', :msg,
                         :st, :hcode, 'fonnte', :pmid, :presp,
                         1, :idem, :sat, NOW())
                ");
                $stmt->execute([
                    'uid'   => Auth::id() ?: 1,
                    'sid'   => $activeStoreId,
                    'phone' => $normalizedPhone,
                    'role'  => Auth::role() ?: 'owner',
                    'msg'   => $body,
                    'st'    => $finalStatus,
                    'hcode' => $httpCode,
                    'pmid'  => $providerMsgId,
                    'presp' => json_encode($providerResp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'idem'  => 'manual_test:' . uniqid(),
                    'sat'   => $isSuccess ? date('Y-m-d H:i:s') : null,
                ]);
            }

            if ($isSuccess) {
                $msgId = $res['message_id'] ?? '-';
                $successNotice = "Pesan uji WhatsApp BERHASIL dikirim ke " . PhoneHelper::formatInternational($phone) . " via Fonnte API! (HTTP {$httpCode}, ID: {$msgId})";
                if ($isAjax) {
                    $this->json(['status' => 'success', 'message' => $successNotice, 'details' => $res]);
                    return;
                }
                Session::flash('success', $successNotice);
            } else {
                $errDetail = $res['error'] ?? 'Respons tidak diketahui dari Fonnte API.';
                $failNotice = "Pengiriman pesan uji Fonnte GAGAL (HTTP {$httpCode}): {$errDetail}";
                if ($isAjax) {
                    $this->json(['status' => 'error', 'message' => $failNotice, 'details' => $res], 400);
                    return;
                }
                Session::flash('error', $failNotice);
            }
        }

        $this->redirect('/admin/whatsapp');
    }

    /**
     * POST /admin/whatsapp/check-device
     * AJAX endpoint to inspect live device connection.
     */
    public function checkDevice(): void
    {
        $client = $this->waService->getClient();
        if ($client instanceof FonnteWhatsAppClient) {
            $deviceInfo = $client->checkDeviceStatus();
            $this->json([
                'status' => 'success',
                'data'   => $deviceInfo,
            ]);
            return;
        }

        $this->json([
            'status'  => 'info',
            'message' => 'Provider aktif: ' . $client->getName(),
        ]);
    }

    /**
     * POST /admin/whatsapp/retry
     * Retry sending a failed WhatsApp log.
     */
    public function retry(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/admin/whatsapp');
            return;
        }

        $logId = (int)($_POST['log_id'] ?? 0);
        if ($logId <= 0) {
            Session::flash('error', 'ID Log tidak valid.');
            $this->redirect('/admin/whatsapp');
            return;
        }

        $result = $this->waService->retryFailed($logId);
        if (!empty($result['success'])) {
            Session::flash('success', "Log #{$logId} berhasil dikirim ulang melalui gateway!");
        } else {
            Session::flash('error', "Gagal mengirim ulang Log #{$logId}: " . ($result['message'] ?? 'Error'));
        }

        $this->redirect('/admin/whatsapp');
    }
}


