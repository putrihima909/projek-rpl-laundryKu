<?php

namespace App\Services\WhatsApp\Providers;

use App\Services\WhatsApp\WhatsAppClientInterface;

class MockWhatsAppClient implements WhatsAppClientInterface
{
    /** @var array<int, array> */
    protected static array $sentMessages = [];

    protected static bool $simulateFailure = false;
    protected static string $simulateFailureMessage = 'Mock gateway simulated network failure';
    protected static int $simulateTemporaryFailures = 0; // Number of times to fail before succeeding

    public function getName(): string
    {
        return 'mock';
    }

    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array
    {
        // Check temporary failures simulation (for retry testing)
        if (self::$simulateTemporaryFailures > 0) {
            self::$simulateTemporaryFailures--;
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'response' => [
                    'error' => [
                        'message' => 'Simulated temporary 503 Service Unavailable',
                        'code' => 503,
                    ]
                ],
                'error' => 'Simulated temporary 503 Service Unavailable',
            ];
        }

        // Check hard simulated failure
        if (self::$simulateFailure) {
            return [
                'success' => false,
                'provider' => $this->getName(),
                'message_id' => null,
                'status' => 'failed',
                'response' => [
                    'error' => [
                        'message' => self::$simulateFailureMessage,
                        'code' => 500,
                    ]
                ],
                'error' => self::$simulateFailureMessage,
            ];
        }

        $mockId = 'wamid.MOCK' . date('YmdHis') . bin2hex(random_bytes(4));
        $record = [
            'id' => $mockId,
            'to' => $recipientPhone,
            'message' => $message,
            'metadata' => $metadata,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        self::$sentMessages[] = $record;

        return [
            'success' => true,
            'provider' => $this->getName(),
            'message_id' => $mockId,
            'status' => 'sent',
            'response' => [
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => $recipientPhone, 'wa_id' => $recipientPhone]],
                'messages' => [['id' => $mockId]],
            ],
            'error' => null,
        ];
    }

    public static function getSentMessages(): array
    {
        return self::$sentMessages;
    }

    public static function getLastMessage(): ?array
    {
        if (empty(self::$sentMessages)) {
            return null;
        }
        return self::$sentMessages[count(self::$sentMessages) - 1];
    }

    public static function clear(): void
    {
        self::$sentMessages = [];
        self::$simulateFailure = false;
        self::$simulateTemporaryFailures = 0;
        self::$simulateFailureMessage = 'Mock gateway simulated network failure';
    }

    public static function setSimulateFailure(bool $fail, string $message = 'Mock gateway simulated network failure'): void
    {
        self::$simulateFailure = $fail;
        self::$simulateFailureMessage = $message;
    }

    public static function setTemporaryFailures(int $count): void
    {
        self::$simulateTemporaryFailures = $count;
    }
}

