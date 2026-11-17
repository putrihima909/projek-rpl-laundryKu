<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Logger;
use App\Helpers\PhoneHelper;
use App\Services\WhatsApp\Providers\FonnteWhatsAppClient;

/**
 * WhatsAppWebhookController
 * Endpoint publik untuk menerima pesan masuk dari Fonnte (Incoming Webhook).
 *
 * Catatan Keamanan:
 * - Tidak memerlukan AuthMiddleware (Fonnte memanggil endpoint ini langsung via HTTP POST).
 * - Identifikasi toko (store_id) WAJIB ditentukan berdasarkan nomor gateway WhatsApp
 *   yang terdaftar pada database (store_whatsapp_settings), BUKAN dari payload liar.
 * - Proteksi anti-loop: jika nomor pengirim sama dengan nomor gateway toko, jangan balas.
 * - Token API Fonnte tidak pernah disimpan ke dalam log.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * POST /webhook/fonnte
     */
    public function handle(): void
    {
        // 1. Validasi HTTP Method (wajib POST)
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['status' => 'error', 'message' => 'Method Not Allowed'], 405);
            return;
        }

        // 2. Baca Payload (JSON atau Form-Urlencoded dari Fonnte)
        $rawInput = file_get_contents('php://input');
        $payload = [];

        if (!empty($rawInput)) {
            $json = json_decode($rawInput, true);
            if (is_array($json)) {
                $payload = $json;
            }
        }

        if (empty($payload) && !empty($_POST)) {
            $payload = $_POST;
        }

        // Tangani payload kosong
        if (empty($payload)) {
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'Empty payload ignored']);
            return;
        }

        // 3. Ekstrak Field Utama Fonnte: sender, message, device
        $senderRaw  = trim((string)($payload['sender'] ?? $payload['from'] ?? ''));
        $messageRaw = trim((string)($payload['message'] ?? $payload['text'] ?? ''));
        $deviceRaw  = trim((string)($payload['device'] ?? ''));

        // Jika tidak ada nomor pengirim atau pesan kosong, abaikan dengan 200 OK
        if (empty($senderRaw) || empty($messageRaw)) {
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'Missing sender or message']);
            return;
        }

        // 4. Normalisasi nomor pengirim Indonesia
        $senderNormalized = PhoneHelper::normalize($senderRaw);
        if (!$senderNormalized || !PhoneHelper::isValid($senderNormalized)) {
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'Invalid sender phone format']);
            return;
        }

        $db = null;
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {
            Logger::error("WhatsApp Webhook DB connection failed: " . $e->getMessage());
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'Database error logged']);
            return;
        }

        // 5. Cari store_whatsapp_settings berdasarkan nomor device gateway toko
        //    Prioritas 1: match by device/phone_number
        //    Prioritas 2: jika hanya 1 toko yang mengonfigurasi WA, gunakan toko tersebut
        $storeSettings = null;
        if (!empty($deviceRaw)) {
            $normalizedDevice = PhoneHelper::normalize($deviceRaw);
            $stmt = $db->prepare('
                SELECT * FROM store_whatsapp_settings 
                WHERE enabled = 1 
                  AND (phone_number = :dev OR phone_number = :norm)
                LIMIT 1
            ');
            $stmt->execute(['dev' => $deviceRaw, 'norm' => $normalizedDevice]);
            $storeSettings = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        // Fallback pencarian jika device match belum ditemukan:
        if (!$storeSettings) {
            // Ambil toko pertama yang aktif WA gateway-nya
            $stmt = $db->query('SELECT * FROM store_whatsapp_settings WHERE enabled = 1 AND access_token IS NOT NULL AND access_token != "" ORDER BY store_id ASC LIMIT 1');
            $storeSettings = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        }

        if (!$storeSettings) {
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'No active store gateway registered']);
            return;
        }

        $storeId = (int)$storeSettings['store_id'];
        $gatewayPhone = PhoneHelper::normalize($storeSettings['phone_number'] ?? '');

        // 6. Proteksi Anti-Loop: Jangan membalas pesan dari nomor gateway sendiri
        if (!empty($gatewayPhone) && $senderNormalized === $gatewayPhone) {
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'Anti-loop: message from gateway self ignored']);
            return;
        }

        // 7. Catat pesan incoming ke whatsapp_logs secara aman (tanpa token)
        try {
            $stmtLogIncoming = $db->prepare("
                INSERT INTO whatsapp_logs
                    (user_id, store_id, recipient_phone, recipient_role, event_type, message,
                     status, http_code, provider, provider_response, attempts, idempotency_key, sent_at, created_at)
                VALUES
                    (1, :sid, :phone, 'customer', 'incoming_webhook_message', :msg,
                     'received', 200, 'fonnte', :presp, 1, :idem, NOW(), NOW())
            ");
            $stmtLogIncoming->execute([
                'sid'   => $storeId,
                'phone' => $senderNormalized,
                'msg'   => $messageRaw,
                'presp' => json_encode(['sender' => $senderNormalized, 'device' => $deviceRaw]),
                'idem'  => 'incoming:' . uniqid(),
            ]);
        } catch (\Throwable $e) {
            Logger::error("Failed logging incoming WA message: " . $e->getMessage());
        }

        // 8. Cek apakah Chat Bot aktif untuk toko ini (store_whatsapp_bot_settings.enabled)
        $stmtBotSettings = $db->prepare('SELECT * FROM store_whatsapp_bot_settings WHERE store_id = :sid LIMIT 1');
        $stmtBotSettings->execute(['sid' => $storeId]);
        $botSettings = $stmtBotSettings->fetch(\PDO::FETCH_ASSOC);

        if (empty($botSettings['enabled'])) {
            // Bot toko nonaktif, tidak membalas
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'Bot is disabled for this store']);
            return;
        }

        // 9. Cari keyword yang cocok pada store_whatsapp_bot_responses (is_active = 1)
        //    Pencocokan keyword case-insensitive (exact match atau contains)
        $cleanMessage = mb_strtolower(trim($messageRaw));

        $stmtResponses = $db->prepare('
            SELECT * FROM store_whatsapp_bot_responses 
            WHERE store_id = :sid AND is_active = 1 
            ORDER BY CHAR_LENGTH(keyword) DESC
        ');
        $stmtResponses->execute(['sid' => $storeId]);
        $activeResponses = $stmtResponses->fetchAll(\PDO::FETCH_ASSOC);

        $replyMessage = null;
        $matchedKeyword = null;

        foreach ($activeResponses as $resp) {
            $kw = mb_strtolower(trim($resp['keyword']));
            if ($cleanMessage === $kw || str_contains($cleanMessage, $kw)) {
                $replyMessage = $resp['response'];
                $matchedKeyword = $resp['keyword'];
                break;
            }
        }

        // 10. Jika keyword tidak ditemukan, gunakan fallback_message jika diisi
        if ($replyMessage === null && !empty($botSettings['fallback_message'])) {
            $replyMessage = trim($botSettings['fallback_message']);
            $matchedKeyword = '__fallback__';
        }

        // Jika tidak ada balasan sama sekali, selesai dengan 200 OK
        if (empty($replyMessage)) {
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'No keyword matched and no fallback']);
            return;
        }

        // 11. Kirim balasan menggunakan FonnteWhatsAppClient dengan kredensial toko
        $token = trim((string)($storeSettings['access_token'] ?? ''));
        if (empty($token)) {
            http_response_code(200);
            $this->json(['status' => 'ok', 'message' => 'Store token empty, cannot send reply']);
            return;
        }

        $fonnteClient = new FonnteWhatsAppClient([
            'token'      => $token,
            'api_url'    => $storeSettings['api_url'] ?? 'https://api.fonnte.com/send',
            'device_url' => 'https://api.fonnte.com/device',
            'timeout'    => 20,
        ]);

        $mode = strtoupper($storeSettings['mode'] ?? 'TEST');

        if ($mode === 'TEST') {
            // TEST MODE: Catat balasan bot sebagai 'test_sent' tanpa memotong kuota riil
            try {
                $stmtLogReply = $db->prepare("
                    INSERT INTO whatsapp_logs
                        (user_id, store_id, recipient_phone, recipient_role, event_type, message,
                         status, http_code, provider, provider_response, attempts, idempotency_key, sent_at, created_at)
                    VALUES
                        (1, :sid, :phone, 'customer', 'bot_auto_reply', :msg,
                         'test_sent', NULL, 'fonnte', :presp, 1, :idem, NOW(), NOW())
                ");
                $stmtLogReply->execute([
                    'sid'   => $storeId,
                    'phone' => $senderNormalized,
                    'msg'   => $replyMessage,
                    'presp' => json_encode(['keyword' => $matchedKeyword, 'mode' => 'TEST']),
                    'idem'  => 'bot_reply:' . uniqid(),
                ]);
            } catch (\Throwable $e) {
                Logger::error("Failed logging bot simulated reply: " . $e->getMessage());
            }

            http_response_code(200);
            $this->json([
                'status'  => 'success',
                'mode'    => 'TEST',
                'keyword' => $matchedKeyword,
                'message' => 'Bot auto-reply simulated (TEST_SENT)',
            ]);
            return;
        }

        // PRODUCTION MODE: Kirim nyata via Fonnte API
        $sendResult = $fonnteClient->sendMessage($senderNormalized, $replyMessage, [
            'event_type' => 'bot_auto_reply',
            'keyword'    => $matchedKeyword,
            'store_id'   => $storeId,
        ]);

        $isSuccess     = !empty($sendResult['success']);
        $finalStatus   = $isSuccess ? 'sent' : 'failed';
        $httpCode      = $sendResult['http_code'] ?? null;
        $providerResp  = $sendResult['response'] ?? [];
        $providerMsgId = $sendResult['message_id'] ?? null;

        // Bersihkan token dari log
        if (is_array($providerResp)) {
            unset($providerResp['access_token'], $providerResp['token'], $providerResp['api_key']);
        }

        try {
            $stmtLogReply = $db->prepare("
                INSERT INTO whatsapp_logs
                    (user_id, store_id, recipient_phone, recipient_role, event_type, message,
                     status, http_code, provider, provider_message_id, provider_response,
                     attempts, idempotency_key, sent_at, created_at)
            VALUES
                    (1, :sid, :phone, 'customer', 'bot_auto_reply', :msg,
                     :st, :hcode, 'fonnte', :pmid, :presp, 1, :idem, :sat, NOW())
            ");
            $stmtLogReply->execute([
                'sid'   => $storeId,
                'phone' => $senderNormalized,
                'msg'   => $replyMessage,
                'st'    => $finalStatus,
                'hcode' => $httpCode,
                'pmid'  => $providerMsgId,
                'presp' => json_encode($providerResp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'idem'  => 'bot_reply:' . uniqid(),
                'sat'   => $isSuccess ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error("Failed logging bot real reply: " . $e->getMessage());
        }

        http_response_code(200);
        $this->json([
            'status'     => 'success',
            'mode'       => 'PRODUCTION',
            'sent'       => $isSuccess,
            'keyword'    => $matchedKeyword,
            'message_id' => $providerMsgId,
        ]);
    }
}

