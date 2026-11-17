<?php

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

use App\Core\Database;

try {
    $db = Database::getInstance();
    if (!$db) {
        echo "Database connection failed.\n";
        exit(1);
    }

    echo "Checking columns in store_ai_settings...\n";
    $stmt = $db->query("SHOW COLUMNS FROM store_ai_settings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $queries = [];
    if (!in_array('provider', $columns, true)) {
        $queries[] = "ALTER TABLE store_ai_settings ADD COLUMN provider VARCHAR(50) NOT NULL DEFAULT 'gemini' AFTER ai_enabled";
    }
    if (!in_array('model', $columns, true)) {
        $queries[] = "ALTER TABLE store_ai_settings ADD COLUMN model VARCHAR(100) NOT NULL DEFAULT 'gemini-2.5-flash' AFTER provider";
    }
    if (!in_array('api_key_enc', $columns, true)) {
        $queries[] = "ALTER TABLE store_ai_settings ADD COLUMN api_key_enc TEXT NULL AFTER model";
    }
    if (!in_array('api_key_last4', $columns, true)) {
        $queries[] = "ALTER TABLE store_ai_settings ADD COLUMN api_key_last4 VARCHAR(10) NULL AFTER api_key_enc";
    }
    if (!in_array('base_url', $columns, true)) {
        $queries[] = "ALTER TABLE store_ai_settings ADD COLUMN base_url VARCHAR(255) NULL AFTER api_key_last4";
    }

    foreach ($queries as $q) {
        $db->exec($q);
        echo "Executed: {$q}\n";
    }

    echo "Migration completed successfully. Current columns:\n";
    $stmt2 = $db->query("SHOW COLUMNS FROM store_ai_settings");
    print_r($stmt2->fetchAll(PDO::FETCH_COLUMN));

} catch (\Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
