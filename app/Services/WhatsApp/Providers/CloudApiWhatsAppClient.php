<?php

namespace App\Services\WhatsApp\Providers;

use App\Services\WhatsApp\WhatsAppClientInterface;
use App\Helpers\Config;

class CloudApiWhatsAppClient implements WhatsAppClientInterface
{
    protected string $apiUrl;
    protected string $phoneNumberId;
    protected string $accessToken;
    protected string $businessAccountId;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? Config::get('whatsapp.cloud_api', []);
        $this->apiUrl = rtrim($cfg['api_url'] ?? 'https://graph.facebook.com/v19.0', '/');
        $this->phoneNumberId = trim((string)($cfg['phone_number_id'] ?? ''));
        $this->accessToken = trim((string)($cfg['access_token'] ?? ''));
        $this->businessAccountId = trim((string)($cfg['business_account_id'] ?? ''));
    }

    public function getName(): string
    {
        return 'cloud_api';
    }

    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }

    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'response' => null,
                'error' => 'WhatsApp Cloud API phone_number_id or access_token is not configured in .env',
            ];
        }

        $endpoint = "{$this->apiUrl}/{$this->phoneNumberId}/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipientPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'response' => null,
                'error' => 'PHP curl extension is not enabled.',
            ];
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'response' => ['curl_error' => $curlError],
                'error' => 'cURL Error: ' . $curlError,
            ];
        }

        $decoded = json_decode($responseBody, true) ?: ['raw' => $responseBody];

        if ($httpCode >= 200 && $httpCode < 300 && !empty($decoded['messages'][0]['id'])) {
            return [
                'success' => true,
                'provider' => $this->getName(),
                'message_id' => $decoded['messages'][0]['id'],
                'status' => 'sent',
                'response' => $decoded,
                'error' => null,
            ];
        }

        $errorMessage = $decoded['error']['message'] ?? ("HTTP {$httpCode} response from WhatsApp Cloud API");
        return [
            'success' => false,
            'provider' => $this->getName(),
            'message_id' => null,
            'status' => 'failed',
            'response' => $decoded,
            'error' => $errorMessage,
        ];
    }
}

