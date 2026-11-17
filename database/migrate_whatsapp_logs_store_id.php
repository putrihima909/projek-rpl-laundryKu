<?php

/**
 * Migration: Add `store_id` column to `whatsapp_logs` table for Store Isolation.
 * Preserves all previous migrations and data.
 */

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[MIGRATION] Connected to MySQL database.\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Check if `store_id` column already exists
    $cols = $pdo->query("SHOW COLUMNS FROM `whatsapp_logs` LIKE 'store_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("
            ALTER TABLE `whatsapp_logs` 
            ADD COLUMN `store_id` INT NULL AFTER `user_id`,
            ADD INDEX `idx_wa_store` (`store_id`)
        ");
        echo "[MIGRATION] Added `store_id` column and index to `whatsapp_logs` successfully.\n";
    } else {
        echo "[MIGRATION] Column `store_id` already exists in `whatsapp_logs`.\n";
    }

    // Backfill store_id from orders table where order_code is present
    $pdo->exec("
        UPDATE `whatsapp_logs` wl
        JOIN `orders` o ON wl.order_code = o.order_code
        SET wl.store_id = o.store_id
        WHERE wl.store_id IS NULL AND wl.order_code IS NOT NULL
    ");
    echo "[MIGRATION] Backfilled existing order-related logs with store_id.\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "[MIGRATION] Migration completed successfully!\n";

} catch (Exception $e) {
    echo "[MIGRATION ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

