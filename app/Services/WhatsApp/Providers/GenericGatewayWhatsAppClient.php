<?php

namespace App\Services\WhatsApp\Providers;

use App\Services\WhatsApp\WhatsAppClientInterface;
use App\Helpers\Config;

class GenericGatewayWhatsAppClient implements WhatsAppClientInterface
{
    protected string $endpoint;
    protected string $apiKey;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? Config::get('whatsapp.generic_gateway', []);
        $this->endpoint = trim((string)($cfg['endpoint'] ?? ''));
        $this->apiKey = trim((string)($cfg['api_key'] ?? ''));
    }

    public function getName(): string
    {
        return 'generic_gateway';
    }

    public function isConfigured(): bool
    {
        return !empty($this->endpoint) && !empty($this->apiKey);
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
                'error' => 'Generic WhatsApp gateway endpoint or api_key is not configured in .env',
            ];
        }

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

        $payload = [
            'target' => $recipientPhone,
            'message' => $message,
            'metadata' => $metadata,
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->apiKey,
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

        if ($httpCode >= 200 && $httpCode < 300) {
            $msgId = $decoded['id'] ?? $decoded['message_id'] ?? ('gw-' . bin2hex(random_bytes(4)));
            return [
                'success' => true,
                'provider' => $this->getName(),
                'message_id' => $msgId,
                'status' => 'sent',
                'response' => $decoded,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'provider' => $this->getName(),
            'message_id' => null,
            'status' => 'failed',
            'response' => $decoded,
            'error' => $decoded['message'] ?? ("HTTP {$httpCode} gateway error"),
        ];
    }
}

