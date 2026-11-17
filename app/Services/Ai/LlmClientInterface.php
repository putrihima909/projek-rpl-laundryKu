<?php

namespace App\Services\Ai;

interface LlmClientInterface
{
    /**
     * Generate an AI response.
     *
     * @param string $systemPrompt System instructions, dynamic order context, catalog info, and strict guardrails.
     * @param array  $conversationHistory Previous conversation turns: [['sender' => 'user'|'assistant', 'message' => '...'], ...]
     * @param string $userMessage The latest user prompt.
     * @return array Result containing: ['success' => bool, 'reply' => string, 'error' => ?string]
     */
    public function generateResponse(string $systemPrompt, array $conversationHistory, string $userMessage): array;
}
