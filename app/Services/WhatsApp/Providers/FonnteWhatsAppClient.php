<?php

namespace App\Services\WhatsApp\Providers;

use App\Helpers\Config;
use App\Helpers\PhoneHelper;
use App\Services\WhatsApp\WhatsAppClientInterface;

class FonnteWhatsAppClient implements WhatsAppClientInterface
{
    protected string $apiUrl;
    protected string $deviceUrl;
    protected string $token;
    protected int $timeout;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? Config::get('whatsapp.fonnte', []);
        $this->apiUrl = rtrim((string)($cfg['api_url'] ?? 'https://api.fonnte.com/send'), '/');
        $this->deviceUrl = rtrim((string)($cfg['device_url'] ?? 'https://api.fonnte.com/device'), '/');
        $this->token = trim((string)($cfg['token'] ?? ''));
        $this->timeout = max(5, (int)($cfg['timeout'] ?? 30));
    }

    public function getName(): string
    {
        return 'fonnte';
    }

    /**
     * Check if Fonnte API token is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    /**
     * Return masked token for safe UI display (e.g. fonn_****************ab12).
     */
    public function getMaskedToken(): string
    {
        if (empty($this->token)) {
            return '(Belum dikonfigurasi)';
        }

        $len = strlen($this->token);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($this->token, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($this->token, -4);
    }

    /**
     * Check live Fonnte device and account status via POST https://api.fonnte.com/device.
     *
     * @return array [
     *     'configured' => bool,
     *     'connected' => bool,
     *     'device' => string|null,
     *     'name' => string|null,
     *     'package' => string|null,
     *     'quota' => string|null,
     *     'expired' => string|null,
     *     'status' => bool,
     *     'reason' => string|null,
     *     'raw' => array
     * ]
     */
    public function checkDeviceStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'connected' => false,
                'device' => null,
                'name' => null,
                'package' => null,
                'quota' => null,
                'expired' => null,
                'status' => false,
                'reason' => 'Token API Fonnte belum diisi di .env (WHATSAPP_API_TOKEN)',
                'raw' => [],
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'configured' => true,
                'connected' => false,
                'device' => null,
                'name' => null,
                'package' => null,
                'quota' => null,
                'expired' => null,
                'status' => false,
                'reason' => 'PHP cURL extension belum aktif di server',
                'raw' => [],
            ];
        }

        $ch = curl_init($this->deviceUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->token,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return $this->parseDeviceResponse($httpCode, (string)$responseBody, (string)$curlError);
    }

    /**
     * Parse Fonnte API device profile response.
     *
     * @param int $httpCode
     * @param string $responseBody
     * @param string $curlError
     * @return array
     */
    public function parseDeviceResponse(int $httpCode, string $responseBody, string $curlError = ''): array
    {
        if (!empty($curlError)) {
            return [
                'configured' => true,
                'connected' => false,
                'device' => null,
                'name' => null,
                'package' => null,
                'quota' => null,
                'expired' => null,
                'status' => false,
                'reason' => 'cURL connection error: ' . $curlError,
                'raw' => ['curl_error' => $curlError],
            ];
        }

        $decoded = json_decode($responseBody, true) ?: [];

        $isSuccess = ($httpCode >= 200 && $httpCode < 300) && (!empty($decoded['status']));
        $deviceStatus = strtolower((string)($decoded['device_status'] ?? ''));
        $isConnected = $isSuccess && in_array($deviceStatus, ['connect', 'connected'], true);

        return [
            'configured' => true,
            'connected' => $isConnected,
            'device' => $decoded['device'] ?? null,
            'name' => $decoded['name'] ?? null,
            'package' => $decoded['package'] ?? null,
            'quota' => (string)($decoded['quota'] ?? '-'),
            'expired' => $decoded['expired'] ?? null,
            'status' => $isSuccess,
            'reason' => $decoded['reason'] ?? ($isConnected ? 'Terkoneksi' : ($deviceStatus ?: "HTTP {$httpCode}")),
            'raw' => $decoded,
        ];
    }

    /**
     * Send real WhatsApp message via Fonnte POST https://api.fonnte.com/send.
     *
     * @param string $recipientPhone
     * @param string $message
     * @param array $metadata
     * @return array
     */
    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'http_code' => 0,
                'response' => ['status' => false, 'reason' => 'Token API Fonnte belum diisi pada file .env (WHATSAPP_API_TOKEN).'],
                'error' => 'Token API Fonnte belum dikonfigurasi pada file .env (WHATSAPP_API_TOKEN).',
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'http_code' => 0,
                'response' => ['status' => false, 'reason' => 'Ekstensi PHP cURL tidak aktif.'],
                'error' => 'Ekstensi PHP cURL tidak aktif pada server.',
            ];
        }

        // Fonnte accepts destination number format 08... or 628...
        // Use normalized 628... or formatNational 08...
        $cleanTarget = PhoneHelper::normalize($recipientPhone) ?? preg_replace('/\D/', '', $recipientPhone);

        $postData = [
            'target' => $cleanTarget,
            'message' => $message,
            'countryCode' => '62',
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->token,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        return $this->parseSendResponse($httpCode, (string)$responseBody, (string)$curlError, (int)$curlErrno);
    }

    /**
     * Parse Fonnte API send response.
     *
     * @param int $httpCode
     * @param string $responseBody
     * @param string $curlError
     * @param int $curlErrno
     * @return array
     */
    public function parseSendResponse(int $httpCode, string $responseBody, string $curlError = '', int $curlErrno = 0): array
    {
        if (!empty($curlError)) {
            $isTimeout = ($curlErrno === CURLE_OPERATION_TIMEDOUT || str_contains(strtolower($curlError), 'timed out'));
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'http_code' => $isTimeout ? 408 : 0,
                'response' => ['curl_error' => $curlError, 'curl_errno' => $curlErrno],
                'error' => 'cURL Error (' . $curlErrno . '): ' . $curlError,
            ];
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            $decoded = ['raw_response' => substr($responseBody, 0, 500)];
        }

        $isFonnteSuccess = ($httpCode >= 200 && $httpCode < 300) && (!empty($decoded['status']) && $decoded['status'] === true);

        $messageId = null;
        if (!empty($decoded['id'])) {
            $messageId = is_array($decoded['id']) ? (string)$decoded['id'][0] : (string)$decoded['id'];
        }

        if ($isFonnteSuccess) {
            return [
                'success' => true,
                'provider' => $this->getName(),
                'message_id' => $messageId ?: ('fonnte-' . bin2hex(random_bytes(4))),
                'status' => 'sent',
                'http_code' => $httpCode,
                'response' => $decoded,
                'error' => null,
            ];
        }

        $errorMessage = $decoded['reason'] ?? ($decoded['message'] ?? ("Fonnte API responded with HTTP {$httpCode}"));

        return [
            'success' => false,
            'provider' => $this->getName(),
            'message_id' => $messageId,
            'status' => 'failed',
            'http_code' => $httpCode,
            'response' => $decoded,
            'error' => $errorMessage,
        ];
    }
}
