<?php

require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

try {
    $db = Database::getInstance();

    echo "1. Memeriksa dan membuat tabel store_whatsapp_bot_settings...\n";
    $sql1 = "CREATE TABLE IF NOT EXISTS store_whatsapp_bot_settings (
        store_id INT NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        fallback_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT NULL,
        CONSTRAINT fk_bot_settings_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        CONSTRAINT fk_bot_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql1);
    echo "Tabel store_whatsapp_bot_settings BERHASIL dibuat/diverifikasi.\n";

    echo "\n2. Memeriksa dan membuat tabel store_whatsapp_bot_responses...\n";
    $sql2 = "CREATE TABLE IF NOT EXISTS store_whatsapp_bot_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        keyword VARCHAR(100) NOT NULL,
        response TEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT NULL,
        UNIQUE KEY uq_store_keyword (store_id, keyword),
        INDEX idx_bot_resp_store (store_id),
        INDEX idx_bot_resp_active (store_id, is_active),
        CONSTRAINT fk_bot_resp_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        CONSTRAINT fk_bot_resp_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql2);
    echo "Tabel store_whatsapp_bot_responses BERHASIL dibuat/diverifikasi.\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

