<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[MIGRATION] Connected to MySQL database.\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Create `refunds` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `refunds` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `payment_id` INT NOT NULL,
            `order_id` INT NOT NULL,
            `amount` DECIMAL(12, 2) NOT NULL,
            `reason` TEXT NOT NULL,
            `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            `gateway_reference` VARCHAR(100) NULL UNIQUE,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `completed_at` DATETIME NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            INDEX `idx_refunds_payment` (`payment_id`),
            INDEX `idx_refunds_order` (`order_id`),
            INDEX `idx_refunds_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[MIGRATION] `refunds` table checked/created successfully.\n";

    // 2. Create `payment_events` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `payment_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `payment_id` INT NULL,
            `refund_id` INT NULL,
            `order_id` INT NULL,
            `event_type` VARCHAR(100) NOT NULL,
            `payload` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pe_payment` (`payment_id`),
            INDEX `idx_pe_refund` (`refund_id`),
            INDEX `idx_pe_order` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[MIGRATION] `payment_events` table checked/created successfully.\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "[MIGRATION] All refund & payment_events migrations finished successfully!\n";

} catch (Exception $e) {
    echo "[MIGRATION ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
