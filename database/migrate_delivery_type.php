<?php

/**
 * Migration Script: Add delivery_type to orders table
 * Values: 'pickup' (Ambil di Toko), 'delivery' (Diantar Kurir ke Rumah)
 * Default for all existing rows: 'delivery'
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Helpers/Config.php';

try {
    $pdo = \App\Core\Database::getInstance();
    echo "[MIGRATION] Connected to MySQL database.\n";

    // Check if column already exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'delivery_type'");
    $exists = $checkStmt->fetch();

    if (!$exists) {
        $sql = "ALTER TABLE `orders` 
                ADD COLUMN `delivery_type` ENUM('pickup', 'delivery') NOT NULL DEFAULT 'delivery' 
                AFTER `payment_method`";
        $pdo->exec($sql);
        echo "[MIGRATION] Added `delivery_type` column to `orders` table (DEFAULT 'delivery').\n";
    } else {
        echo "[MIGRATION] Column `delivery_type` already exists in `orders` table.\n";
    }

    echo "[MIGRATION] Migration completed successfully!\n";
} catch (\Throwable $e) {
    echo "[MIGRATION ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

