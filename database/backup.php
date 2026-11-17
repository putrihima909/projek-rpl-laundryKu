<?php

/**
 * Database Backup Utility: LaundryKu
 * Exports all MySQL database tables, schemas, and records to storage/backups/
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
date_default_timezone_set(\config('app.timezone', 'Asia/Jakarta'));

use App\Helpers\Logger;

$host = \config('database.host', '127.0.0.1');
$port = \config('database.port', 3306);
$dbname = \config('database.database', 'marketplace_laundry');
$user = \config('database.username', 'root');
$pass = \config('database.password', '');

$backupDir = __DIR__ . '/../storage/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
}

$timestamp = date('Y-m-d_H-i-s');
$filename = "backup_{$dbname}_{$timestamp}.sql";
$targetFile = $backupDir . '/' . $filename;

echo "==============================================================================\n";
echo "                   DATABASE BACKUP UTILITY - LAUNDRYKU                        \n";
echo "==============================================================================\n";
echo "Target Database : {$dbname} @ {$host}:{$port}\n";
echo "Output File     : {$filename}\n";
echo "Directory       : {$backupDir}\n\n";

$startTime = microtime(true);

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    ]);

    $tables = [];
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $out = "-- =====================================================================\n";
    $out .= "-- LAUNDRYKU DATABASE BACKUP\n";
    $out .= "-- Database: `{$dbname}`\n";
    $out .= "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Host: {$host}:{$port}\n";
    $out .= "-- Tables Count: " . count($tables) . "\n";
    $out .= "-- =====================================================================\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $out .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $out .= "SET NAMES utf8mb4;\n\n";

    $totalRows = 0;

    foreach ($tables as $table) {
        echo ">>> Memproses tabel: `{$table}`... ";

        // 1. Structure
        $out .= "\n-- -------------------------------------------------------------\n";
        $out .= "-- Struktur tabel: `{$table}`\n";
        $out .= "-- -------------------------------------------------------------\n";
        $out .= "DROP TABLE IF EXISTS `{$table}`;\n";

        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $createSql = $createStmt['Create Table'] ?? '';
        $out .= $createSql . ";\n\n";

        // 2. Data
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll();
        $rowCount = count($rows);
        $totalRows += $rowCount;

        if ($rowCount > 0) {
            $out .= "-- Data untuk tabel: `{$table}` ({$rowCount} baris)\n";
            $out .= "LOCK TABLES `{$table}` WRITE;\n";

            $columns = array_keys($rows[0]);
            $colList = implode('`, `', $columns);

            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                $valRows = [];
                foreach ($chunk as $r) {
                    $escaped = array_map(function ($val) use ($pdo) {
                        if ($val === null) return 'NULL';
                        return $pdo->quote($val);
                    }, array_values($r));
                    $valRows[] = "(" . implode(', ', $escaped) . ")";
                }
                $out .= "INSERT INTO `{$table}` (`{$colList}`) VALUES \n" . implode(",\n", $valRows) . ";\n";
            }
            $out .= "UNLOCK TABLES;\n\n";
        }

        echo "({$rowCount} baris diekspor)\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $out .= "-- Backup selesai pada " . date('Y-m-d H:i:s') . "\n";

    file_put_contents($targetFile, $out);

    $duration = round(microtime(true) - $startTime, 3);
    $fileSizeBytes = filesize($targetFile);
    $fileSizeKB = round($fileSizeBytes / 1024, 2);

    Logger::info("Database backup completed successfully", [
        'database' => $dbname,
        'filename' => $filename,
        'tables' => count($tables),
        'total_rows' => $totalRows,
        'size_kb' => $fileSizeKB,
        'duration_seconds' => $duration
    ]);

    echo "\n==============================================================================\n";
    echo "STATUS        : SUKSES (100% Berhasil)\n";
    echo "Total Tabel   : " . count($tables) . "\n";
    echo "Total Data    : {$totalRows} baris\n";
    echo "Ukuran File   : {$fileSizeKB} KB ({$fileSizeBytes} bytes)\n";
    echo "Waktu Eksekusi: {$duration} detik\n";
    echo "Lokasi File   : {$targetFile}\n";
    echo "==============================================================================\n\n";

} catch (\Throwable $e) {
    Logger::error("Database backup failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    echo "\n[ERROR] Backup gagal: " . $e->getMessage() . "\n";
    exit(1);
}
