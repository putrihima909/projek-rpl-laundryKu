<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AiChatMessage
{
    public const SENDER_USER = 'user';
    public const SENDER_ASSISTANT = 'assistant';

    protected string $table = 'ai_chat_messages';
    protected ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            $this->db = null;
        }
    }

    /**
     * Store a chat message in database.
     */
    public function saveMessage(int $userId, string $sender, string $message): int
    {
        if (!$this->db) {
            return 0;
        }

        $sql = "INSERT INTO {$this->table} (user_id, sender, message, created_at)
                VALUES (:user_id, :sender, :message, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'sender' => $sender,
            'message' => $message,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get recent conversation history for a user in chronological order.
     */
    public function getRecentHistory(int $userId, int $limit = 20): array
    {
        if (!$this->db) {
            return [];
        }

        $sql = "SELECT id, user_id, sender, message, created_at
                FROM {$this->table}
                WHERE user_id = :user_id
                ORDER BY id DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Reverse so it's in chronological order (oldest to newest)
        return array_reverse($rows);
    }

    /**
     * Count messages sent by user within the last 1 hour (for rate limiting).
     */
    public function countUserMessagesInLastHour(int $userId): int
    {
        if (!$this->db) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as total
                FROM {$this->table}
                WHERE user_id = :user_id
                  AND sender = 'user'
                  AND created_at >= (NOW() - INTERVAL 1 HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Clear all conversation history for a user.
     */
    public function clearUserHistory(int $userId): bool
    {
        if (!$this->db) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
}
