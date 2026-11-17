<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Services\Ai\AiAssistantService;

class AiChatController extends Controller
{
    private AiAssistantService $service;

    public function __construct(?AiAssistantService $service = null)
    {
        $this->service = $service ?? new AiAssistantService();
    }

    /**
     * POST /api/ai/chat
     * Receives customer message, queries AI service, and returns structured JSON response.
     */
    public function chat(): void
    {
        // Start output buffering to catch any premature output/notices/warnings
        ob_start();

        if (!Auth::check()) {
            ob_end_clean();
            $this->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Unauthorized: Silakan login terlebih dahulu untuk menggunakan AI Assistant.'
            ], 401);
            return;
        }

        $userId = (int) Auth::id();

        // Read input from POST or JSON payload
        $rawInput = file_get_contents('php://input');
        $json = is_string($rawInput) && !empty($rawInput) ? json_decode($rawInput, true) : null;

        $userMessage = trim($_POST['message'] ?? '');
        if ($userMessage === '' && is_array($json)) {
            $userMessage = trim($json['message'] ?? ($json['text'] ?? ($json['content'] ?? ($json['prompt'] ?? ''))));
        }

        if ($userMessage === '') {
            ob_end_clean();
            $this->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Pesan tidak boleh kosong.'
            ], 400);
            return;
        }

        try {
            $result = $this->service->sendMessage($userId, $userMessage);
        } catch (\Throwable $e) {
            \App\Helpers\Logger::error("AiChatController: Exception saat memproses chat - " . get_class($e) . ": " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->json([
                'status'     => 'error',
                'success'    => false,
                'reply'      => 'Maaf, terjadi kesalahan saat memproses jawaban. Silakan coba beberapa saat lagi.',
                'message'    => 'Maaf, terjadi kesalahan saat memproses jawaban. Silakan coba beberapa saat lagi.',
                'rate_limit' => [
                    'remaining' => $this->service->getRemainingQuota($userId),
                    'limit'     => (int) \config('ai.rate_limit_per_hour', 20)
                ]
            ], 200);
            return;
        }

        // Clean buffer before returning valid JSON
        $buffered = ob_get_clean();
        if (!empty($buffered)) {
            \App\Helpers\Logger::warning("AiChatController: Output PHP terdeteksi sebelum JSON: " . strip_tags($buffered));
        }

        if (!empty($result['ai_disabled'])) {
            $this->json([
                'status'       => 'disabled',
                'success'      => false,
                'ai_disabled'  => true,
                'reply'        => $result['reply'],
                'message'      => $result['reply'],
                'rate_limit'   => $result['rate_limit']
            ], 200);
            return;
        }

        if (!empty($result['rate_limited'])) {
            $this->json([
                'status'       => 'rate_limited',
                'success'      => false,
                'reply'        => $result['reply'],
                'message'      => $result['reply'],
                'rate_limit'   => $result['rate_limit']
            ], 429);
            return;
        }

        $this->json([
            'status'     => $result['success'] ? 'success' : 'warning',
            'success'    => $result['success'],
            'reply'      => $result['reply'],
            'rate_limit' => $result['rate_limit']
        ]);
    }

    /**
     * GET /api/ai/history
     * Returns recent conversation history for authenticated user.
     */
    public function history(): void
    {
        if (!Auth::check()) {
            $this->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
            return;
        }

        $userId = (int) Auth::id();
        $history = $this->service->getHistory($userId, 30);
        $remaining = $this->service->getRemainingQuota($userId);
        $limit = (int) \config('ai.rate_limit_per_hour', 20);

        $this->json([
            'status'     => 'success',
            'success'    => true,
            'messages'   => $history,
            'rate_limit' => [
                'remaining' => $remaining,
                'limit'     => $limit
            ]
        ]);
    }

    /**
     * POST /api/ai/clear
     * Clears all conversation history for the current user.
     */
    public function clear(): void
    {
        if (!Auth::check()) {
            $this->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
            return;
        }

        $userId = (int) Auth::id();
        $this->service->clearHistory($userId);

        $this->json([
            'status'  => 'success',
            'success' => true,
            'message' => 'Histori percakapan berhasil dihapus.'
        ]);
    }
}
