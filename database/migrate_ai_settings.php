<?php

/**
 * Migration Script: AI Settings & Store AI Settings Tables
 */

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Helpers/Config.php';

try {
    $pdo = \App\Core\Database::getInstance();
    echo "Connected to database successfully.\n";

    // 1. Table ai_settings
    $sqlAiSettings = "CREATE TABLE IF NOT EXISTS `ai_settings` (
        `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `setting_value` TEXT NULL,
        `updated_by` INT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_ai_settings_updated_by` (`updated_by`),
        CONSTRAINT `fk_ai_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sqlAiSettings);
    echo "Table `ai_settings` created or already exists.\n";

    // 2. Table store_ai_settings
    $sqlStoreAiSettings = "CREATE TABLE IF NOT EXISTS `store_ai_settings` (
        `store_id` INT NOT NULL PRIMARY KEY,
        `ai_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `info_jam_operasional` TEXT NULL,
        `info_antar_jemput` TEXT NULL,
        `info_kebijakan` TEXT NULL,
        `info_faq` TEXT NULL,
        `updated_by` INT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_store_ai_updated_by` (`updated_by`),
        CONSTRAINT `fk_store_ai_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_store_ai_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sqlStoreAiSettings);
    echo "Table `store_ai_settings` created or already exists.\n";

    // 3. Table ai_settings_audit
    $sqlAudit = "CREATE TABLE IF NOT EXISTS `ai_settings_audit` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `role` VARCHAR(50) NOT NULL,
        `store_id` INT NULL,
        `setting_key` VARCHAR(100) NOT NULL,
        `old_value` TEXT NULL,
        `new_value` TEXT NULL,
        `ip` VARCHAR(45) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ai_audit_user` (`user_id`),
        INDEX `idx_ai_audit_store` (`store_id`),
        INDEX `idx_ai_audit_key_created` (`setting_key`, `created_at`),
        CONSTRAINT `fk_ai_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_ai_audit_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sqlAudit);
    echo "Table `ai_settings_audit` created or already exists.\n";
    echo "Migrasi tabel AI Settings selesai.\n";

} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
