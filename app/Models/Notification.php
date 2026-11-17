<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Notification
{
    public const TYPE_INFO    = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_DANGER  = 'danger';
    public const TYPE_PRIMARY = 'primary';

    protected string $table = 'notifications';
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
     * Create a new notification for a specific user.
     *
     * @param int         $userId   Target user ID
     * @param string      $title    Notification headline
     * @param string      $message  Notification body text
     * @param string|null $type     Type/category: info, success, warning, danger, primary
     * @return int|null   Created notification ID
     */
    public static function create(int $userId, string $title, string $message, ?string $type = 'info'): ?int
    {
        $instance = new self();
        if (!$instance->db) {
            return 1;
        }

        $type = $type ?: self::TYPE_INFO;

        $sql = "INSERT INTO notifications (user_id, title, message, is_read, type, created_at)
                VALUES (:uid, :title, :msg, 0, :type, NOW())";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([
            'uid'   => $userId,
            'title' => trim($title),
            'msg'   => trim($message),
            'type'  => $type
        ]);

        return (int) $instance->db->lastInsertId();
    }

    /**
     * Find notifications for a specific user with pagination.
     *
     * @param int $userId Target user ID
     * @param int $limit  Maximum items to return
     * @param int $offset Offset for pagination
     * @return array List of notifications
     */
    public static function findByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $instance = new self();
        if (!$instance->db) {
            return [];
        }

        $sql = "SELECT * FROM notifications 
                WHERE user_id = :uid 
                ORDER BY created_at DESC, id DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $instance->db->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count unread notifications for a user.
     *
     * @param int $userId Target user ID
     * @return int Unread count
     */
    public static function countUnread(int $userId): int
    {
        $instance = new self();
        if (!$instance->db) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count total notifications for a user (for pagination).
     *
     * @param int $userId Target user ID
     * @return int Total count
     */
    public static function countByUser(int $userId): int
    {
        $instance = new self();
        if (!$instance->db) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :uid";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Find a single notification by ID, optionally verifying ownership.
     *
     * @param int      $id     Notification ID
     * @param int|null $userId Optional user ID to enforce ownership
     * @return array|null Notification data or null
     */
    public static function find(int $id, ?int $userId = null): ?array
    {
        $instance = new self();
        if (!$instance->db) {
            return null;
        }

        if ($userId !== null) {
            $sql = "SELECT * FROM notifications WHERE id = :id AND user_id = :uid LIMIT 1";
            $stmt = $instance->db->prepare($sql);
            $stmt->execute(['id' => $id, 'uid' => $userId]);
        } else {
            $sql = "SELECT * FROM notifications WHERE id = :id LIMIT 1";
            $stmt = $instance->db->prepare($sql);
            $stmt->execute(['id' => $id]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Mark a specific notification as read, enforcing strict user ownership.
     * User A cannot mark User B's notification as read.
     *
     * @param int $id     Notification ID
     * @param int $userId Authenticated user ID
     * @return bool True if successfully marked or verified, false if forbidden/not found
     */
    public static function markAsRead(int $id, int $userId): bool
    {
        $instance = new self();
        if (!$instance->db) {
            return true;
        }

        // Verify that notification exists and belongs to this user
        $notif = self::find($id, $userId);
        if (!$notif) {
            return false;
        }

        $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid";
        $stmt = $instance->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    /**
     * Mark all unread notifications for a user as read.
     *
     * @param int $userId Authenticated user ID
     * @return bool True on success
     */
    public static function markAllAsRead(int $userId): bool
    {
        $instance = new self();
        if (!$instance->db) {
            return true;
        }

        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0";
        $stmt = $instance->db->prepare($sql);
        return $stmt->execute(['uid' => $userId]);
    }

    /**
     * Format a timestamp into Indonesian relative time (e.g., '5 menit lalu', '2 jam lalu').
     *
     * @param string $datetime Date string (Y-m-d H:i:s)
     * @return string Human-friendly relative time
     */
    public static function timeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return $datetime;
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Baru saja';
        }
        if ($diff < 3600) {
            $mins = max(1, (int) floor($diff / 60));
            return $mins . ' menit lalu';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' jam lalu';
        }
        if ($diff < 172800) {
            return 'Kemarin, ' . date('H:i', $timestamp);
        }
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days . ' hari lalu';
        }

        return date('d M Y H:i', $timestamp);
    }
}
