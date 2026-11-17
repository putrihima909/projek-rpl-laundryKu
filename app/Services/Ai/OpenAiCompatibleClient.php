<?php

namespace App\Services\Ai;

use App\Helpers\Logger;
use Throwable;

class OpenAiCompatibleClient implements LlmClientInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout;
    private float $temperature;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?string $baseUrl = null,
        ?int $timeout = null,
        ?float $temperature = null
    ) {
        $this->apiKey      = trim((string)($apiKey ?? ''));
        $this->model       = trim((string)($model ?? 'llama-3.3-70b-versatile'));
        $this->baseUrl     = rtrim(trim((string)($baseUrl ?? 'https://api.groq.com/openai/v1')), '/');
        $this->timeout     = $timeout ?? 30;
        $this->temperature = $temperature !== null ? (float)$temperature : 0.7;
    }

    public function generateResponse(string $systemPrompt, array $conversationHistory, string $userMessage): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'reply'   => 'Maaf, layanan AI Chatbot belum dikonfigurasi dengan API key yang aktif. Silakan atur API key pada halaman konfigurasi.',
                'error'   => 'API Key is empty or not configured.'
            ];
        }

        $endpoint = str_ends_with($this->baseUrl, '/chat/completions') 
            ? $this->baseUrl 
            : $this->baseUrl . '/chat/completions';

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemPrompt
            ];
        }

        foreach ($conversationHistory as $turn) {
            $role = ($turn['sender'] === 'assistant') ? 'assistant' : 'user';
            $msgText = trim((string)($turn['message'] ?? ''));
            if ($msgText !== '') {
                $messages[] = [
                    'role'    => $role,
                    'content' => $msgText
                ];
            }
        }

        $messages[] = [
            'role'    => 'user',
            'content' => trim($userMessage)
        ];

        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $this->temperature,
        ];

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $endpoint,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $rawResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($rawResponse === false || !empty($curlError)) {
                Logger::error("OpenAiCompatibleClient: cURL error", ['error' => $curlError, 'endpoint' => $endpoint]);
                return [
                    'success' => false,
                    'reply'   => 'Koneksi terputus ke penyedia AI. Silakan coba beberapa saat lagi.',
                    'error'   => 'cURL error: ' . $curlError
                ];
            }

            $json = json_decode($rawResponse, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                $reply = $json['choices'][0]['message']['content'] ?? null;
                if (!empty($reply)) {
                    return [
                        'success' => true,
                        'reply'   => trim($reply),
                        'error'   => null
                    ];
                }
            }

            $errorMessage = $json['error']['message'] ?? "HTTP error {$httpCode}";
            Logger::error("OpenAiCompatibleClient: API error", ['http_code' => $httpCode, 'message' => $errorMessage]);

            return [
                'success' => false,
                'reply'   => 'Maaf, penyedia AI mengembalikan kendala: ' . $errorMessage,
                'error'   => $errorMessage
            ];

        } catch (Throwable $e) {
            Logger::error("OpenAiCompatibleClient: Exception", ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'reply'   => 'Terjadi kesalahan sistem saat menghubungi AI.',
                'error'   => $e->getMessage()
            ];
        }
    }
}

