<?php

namespace App\Services\Ai;

class GeminiAiClient implements LlmClientInterface
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;
    private int $timeout;
    private float $temperature;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $apiUrl = null, ?int $timeout = null, ?float $temperature = null)
    {
        $resolvedKey = null;
        try {
            $resolvedKey = (new AiSettings())->getDecryptedApiKey();
        } catch (\Throwable $e) {
            \App\Helpers\Logger::warning("GeminiAiClient: Exception saat membaca API key dari AiSettings.", ['error' => $e->getMessage()]);
        }
        $this->apiKey      = $apiKey ?? $resolvedKey ?? (string) \config('ai.gemini.api_key', '');
        $rawModel          = $model ?? (string) \config('ai.gemini.model', 'gemini-3.5-flash-lite');
        $this->model       = $this->normalizeModel($rawModel);
        $this->apiUrl      = $apiUrl ?? (string) \config('ai.gemini.api_url', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->timeout     = $timeout ?? (int) \config('ai.gemini.timeout_seconds', 30);
        $this->temperature = $temperature !== null ? (float)$temperature : (float) \config('ai.temperature', 0.7);
    }

    private function normalizeModel(string $model): string
    {
        $trimmed = trim($model);
        $map = [
            'gemini-1.5-flash'      => 'gemini-3.5-flash-lite',
            'gemini-1.5-flash-8b'   => 'gemini-3.5-flash-lite',
            'gemini-1.5-pro'        => 'gemini-3.5-flash',
            'gemini-2.0-flash'      => 'gemini-3.5-flash-lite',
            'gemini-2.5-flash'      => 'gemini-3.5-flash-lite',
            'gemini-2.5-flash-lite' => 'gemini-3.5-flash-lite',
            'gemini-1.0-pro'        => 'gemini-3.5-flash',
            'gemini-flash'          => 'gemini-3.5-flash-lite',
        ];
        return $map[$trimmed] ?? ($trimmed ?: 'gemini-3.5-flash-lite');
    }

    public function generateResponse(string $systemPrompt, array $conversationHistory, string $userMessage): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'reply' => 'Maaf, layanan AI Assistant belum dikonfigurasi dengan API key yang aktif. Silakan hubungi admin.',
                'error' => 'GEMINI_API_KEY is not configured.'
            ];
        }

        // Try primary model
        $result = $this->requestModel($this->model, $systemPrompt, $conversationHistory, $userMessage);

        // If primary model hit high-demand (503) or not found (404), attempt graceful fallback to fast model
        if (!$result['success'] && isset($result['http_code']) && in_array($result['http_code'], [404, 503])) {
            $fallbackModel = 'gemini-3.5-flash-lite';
            if ($this->model !== $fallbackModel && str_contains($this->apiUrl, 'generativelanguage.googleapis.com')) {
                $retryResult = $this->requestModel($fallbackModel, $systemPrompt, $conversationHistory, $userMessage);
                if ($retryResult['success']) {
                    return $retryResult;
                }
            }
        }

        unset($result['http_code']);
        return $result;
    }

    private function requestModel(string $model, string $systemPrompt, array $conversationHistory, string $userMessage): array
    {
        // Construct contents array according to Gemini REST API format
        $contents = [];

        foreach ($conversationHistory as $turn) {
            $role = ($turn['sender'] === 'assistant') ? 'model' : 'user';
            $msgText = trim((string)$turn['message']);
            if ($msgText !== '') {
                $contents[] = [
                    'role' => $role,
                    'parts' => [
                        ['text' => $msgText]
                    ]
                ];
            }
        }

        // Add the current user message
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => trim($userMessage)]
            ]
        ];

        $generationConfig = [
            'temperature' => $this->temperature,
            'maxOutputTokens' => 800,
        ];

        // Disable heavy thinking pauses only for models that support thinking
        if (!str_contains($model, 'lite')) {
            $generationConfig['thinkingConfig'] = [
                'thinkingBudget' => 0
            ];
        }

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'contents' => $contents,
            'generationConfig' => $generationConfig
        ];

        $endpoint = rtrim($this->apiUrl, '/') . '/' . $model . ':generateContent?key=' . urlencode($this->apiKey);

        $jsonPayload = json_encode($payload);

        // Execute request using cURL or fallback to stream context
        $responseBody = null;
        $httpCode = 0;
        $curlError = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            // Check for Windows XAMPP CA bundle if needed
            $caBundle = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
            if (file_exists($caBundle)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }

            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAccept: application/json\r\nConnection: close\r\n",
                    'content' => $jsonPayload,
                    'timeout' => $this->timeout,
                    'ignore_errors' => true
                ]
            ]);
            $responseBody = @file_get_contents($endpoint, false, $context);
            if (isset($http_response_header[0])) {
                preg_match('/HTTP\/\S*\s+(\d{3})/', $http_response_header[0], $matches);
                $httpCode = isset($matches[1]) ? (int)$matches[1] : 500;
            }
        }

        if ($responseBody === false || ($httpCode === 0 && !empty($curlError))) {
            return [
                'success' => false,
                'reply' => 'Maaf, koneksi ke asisten AI sedang mengalami kendala jaringan. Silakan coba kembali dalam beberapa saat.',
                'error' => $curlError ?: 'Network or connection error.',
                'http_code' => $httpCode
            ];
        }

        $decoded = json_decode((string)$responseBody, true);

        if ($httpCode !== 200) {
            $errMsg = $decoded['error']['message'] ?? "HTTP Error {$httpCode}";
            
            if ($httpCode === 429) {
                $userFriendly = 'Asisten AI sedang melayani banyak permintaan. Silakan tunggu sejenak dan coba lagi.';
            } elseif ($httpCode === 400 || $httpCode === 403) {
                $userFriendly = 'Maaf, konfigurasi otentikasi AI sedang bermasalah. Silakan hubungi admin.';
            } else {
                $userFriendly = 'Maaf, asisten AI sedang tidak dapat merespons saat ini. Silakan coba kembali nanti.';
            }

            return [
                'success' => false,
                'reply' => $userFriendly,
                'error' => "Gemini API error ({$httpCode}): {$errMsg}",
                'http_code' => $httpCode
            ];
        }

        $replyText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty($replyText)) {
            return [
                'success' => false,
                'reply' => 'Maaf, asisten AI tidak menghasilkan jawaban. Silakan ajukan pertanyaan lain.',
                'error' => 'Empty candidate response from Gemini API.',
                'http_code' => 200
            ];
        }

        return [
            'success' => true,
            'reply' => trim($replyText),
            'error' => null,
            'http_code' => 200
        ];
    }
}
