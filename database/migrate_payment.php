<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=marketplace_laundry', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[MIGRATION] Connected to MySQL database.\n";

    // 1. Create or Upgrade `payments` table
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Check if payments table needs migration
    $stmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'gateway_reference'");
    $hasGatewayRef = $stmt->fetch();

    if (!$hasGatewayRef) {
        echo "[MIGRATION] Upgrading `payments` table...\n";
        $pdo->exec("
            ALTER TABLE `payments` 
            MODIFY COLUMN `payment_method` VARCHAR(50) NOT NULL DEFAULT 'qris',
            ADD COLUMN `gateway` VARCHAR(50) NOT NULL DEFAULT 'mock_gateway' AFTER `payment_method`,
            ADD COLUMN `gateway_reference` VARCHAR(100) NULL UNIQUE AFTER `gateway`,
            ADD COLUMN `qr_string` TEXT NULL AFTER `gateway_reference`,
            MODIFY COLUMN `status` ENUM('pending', 'paid', 'expired', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
            ADD COLUMN `expired_at` DATETIME NULL AFTER `status`;
        ");
        echo "[MIGRATION] `payments` table upgraded successfully.\n";
    } else {
        echo "[MIGRATION] `payments` table already up-to-date.\n";
    }

    // 2. Create `payment_logs` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `payment_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `payment_id` INT NULL,
            `order_id` INT NULL,
            `gateway` VARCHAR(50) NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `payload` TEXT NOT NULL,
            `status` VARCHAR(50) NOT NULL,
            `notes` TEXT NULL,
            `ip_address` VARCHAR(45) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_payment_logs_order` (`order_id`),
            INDEX `idx_payment_logs_payment` (`payment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[MIGRATION] `payment_logs` table checked/created successfully.\n";

    // 3. Ensure `stores` has `latitude` and `longitude`
    $stmtStore = $pdo->query("SHOW COLUMNS FROM stores LIKE 'latitude'");
    if (!$stmtStore->fetch()) {
        $pdo->exec("
            ALTER TABLE `stores`
            ADD COLUMN `latitude` DECIMAL(10, 8) NULL DEFAULT -6.22974650 AFTER `address`,
            ADD COLUMN `longitude` DECIMAL(11, 8) NULL DEFAULT 106.81644900 AFTER `latitude`;
        ");
        $pdo->exec("UPDATE `stores` SET `latitude` = -6.22974650, `longitude` = 106.81644900 WHERE `latitude` IS NULL");
        echo "[MIGRATION] Added latitude/longitude to `stores`.\n";
    }

    // 4. Ensure `courier_locations` has `order_id` and `created_at`
    $stmtCourier = $pdo->query("SHOW COLUMNS FROM courier_locations LIKE 'order_id'");
    if (!$stmtCourier->fetch()) {
        $pdo->exec("
            ALTER TABLE `courier_locations`
            ADD COLUMN `order_id` INT NULL AFTER `courier_id`,
            ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `longitude`;
        ");
        echo "[MIGRATION] Added order_id/created_at to `courier_locations`.\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "[MIGRATION] All database migrations completed successfully!\n";

} catch (Exception $e) {
    echo "[MIGRATION ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
