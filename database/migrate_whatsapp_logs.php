<?php

/**
 * Migration: Create `whatsapp_logs` table and add opt-in preference support.
 */

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[MIGRATION] Connected to MySQL database.\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Create `whatsapp_logs` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `notification_id` INT NULL,
            `user_id` INT NOT NULL,
            `recipient_phone` VARCHAR(30) NOT NULL,
            `recipient_role` VARCHAR(30) NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `order_code` VARCHAR(50) NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('pending', 'sent', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
            `http_code` INT NULL,
            `provider` VARCHAR(50) NOT NULL,
            `provider_message_id` VARCHAR(100) NULL,
            `provider_response` TEXT NULL,
            `attempts` INT NOT NULL DEFAULT 1,
            `idempotency_key` VARCHAR(120) NULL UNIQUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `sent_at` DATETIME NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_wa_user` (`user_id`),
            INDEX `idx_wa_event` (`event_type`),
            INDEX `idx_wa_order` (`order_code`),
            INDEX `idx_wa_status` (`status`),
            INDEX `idx_wa_idempotency` (`idempotency_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[MIGRATION] `whatsapp_logs` table created/verified.\n";

    // 2. Add `wa_notifications_enabled` column to `users` if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'wa_notifications_enabled'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `wa_notifications_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`");
        echo "[MIGRATION] Added `wa_notifications_enabled` column to `users` table.\n";
    } else {
        echo "[MIGRATION] Column `wa_notifications_enabled` already exists in `users`.\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "[MIGRATION] WhatsApp notification migration completed successfully!\n";

} catch (Exception $e) {
    echo "[MIGRATION ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

