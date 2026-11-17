<?php

namespace App\Services\WhatsApp;

interface WhatsAppClientInterface
{
    /**
     * Send a WhatsApp message.
     *
     * @param string $recipientPhone Normalized destination phone number (e.g. 6281234567890)
     * @param string $message Plain text message content
     * @param array $metadata Optional context (event_type, order_code, recipient_role, etc.)
     * @return array [
     *     'success' => bool,
     *     'provider' => string,
     *     'message_id' => string|null,
     *     'status' => 'sent'|'failed'|'queued',
     *     'response' => array|string,
     *     'error' => string|null
     * ]
     */
    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array;

    /**
     * Get provider identifier name.
     *
     * @return string
     */
    public function getName(): string;
}

