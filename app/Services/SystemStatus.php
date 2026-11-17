<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\Config;
use App\Helpers\Env;
use PDO;
use Throwable;

class SystemStatus
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getInstance();
            } catch (Throwable $e) {
                $this->db = null;
            }
        }
    }

    /**
     * Mengambil informasi status sistem secara lengkap (hanya baca).
     * TIDAK PERNAH memuat atau mengembalikan password, API key, token, atau isi mentah .env.
     */
    public function getStatus(): array
    {
        // 1. Lingkungan PHP & Server
        $versiPhp = PHP_VERSION;
        $softwareServer = $_SERVER['SERVER_SOFTWARE'] ?? php_uname('s');
        $appEnv = (string) \config('app.env', 'production');
        $appDebug = (bool) \config('app.debug', false);

        $httpsAktif = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        $zonaWaktu = date_default_timezone_get();

        // 2. Database Status, Response Time & Name
        $dbStatus = [
            'terhubung'        => false,
            'status'           => 'Terputus',
            'waktu_respons_ms' => null,
            'nama_database'    => null,
        ];

        if ($this->db) {
            $startDb = microtime(true);
            try {
                $stmt = $this->db->query("SELECT 1");
                if ($stmt && $stmt->fetchColumn() == 1) {
                    $elapsed = round((microtime(true) - $startDb) * 1000, 2);
                    $dbNameStmt = $this->db->query("SELECT DATABASE()");
                    $dbName = $dbNameStmt ? $dbNameStmt->fetchColumn() : null;

                    $dbStatus = [
                        'terhubung'        => true,
                        'status'           => 'Terhubung',
                        'waktu_respons_ms' => $elapsed,
                        'nama_database'    => $dbName ?: null,
                    ];
                }
            } catch (Throwable $e) {
                $dbStatus['status'] = 'Error: ' . $e->getMessage();
            }
        }

        // 3. Jumlah Pesanan Tercatat
        $jumlahPesanan = 0;
        if ($this->db) {
            try {
                $stmtOrders = $this->db->query("SELECT COUNT(*) FROM orders");
                $jumlahPesanan = (int)$stmtOrders->fetchColumn();
            } catch (Throwable $e) {
                $jumlahPesanan = 0;
            }
        }

        // 4. File app.log: Jumlah Error 24 Jam Terakhir & Ukuran File
        $logPath = dirname(__DIR__, 2) . '/storage/logs/app.log';
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        $logErrorCount24h = 0;
        $logUkuranBytes = 0;
        $logUkuranFormatted = '0 B';

        if (file_exists($logPath)) {
            $logUkuranBytes = (int) filesize($logPath);
            $logUkuranFormatted = $this->formatBytes($logUkuranBytes);

            // Hitung error dalam 24 jam terakhir
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                $cutoff = time() - 86400; // 24 jam lalu
                foreach ($lines as $line) {
                    if (str_contains($line, '.ERROR]') || str_contains($line, '[ERROR]')) {
                        // Cek timestamp format [YYYY-MM-DD HH:MM:SS]
                        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
                            $lineTime = strtotime($m[1]);
                            if ($lineTime >= $cutoff) {
                                $logErrorCount24h++;
                            }
                        } else {
                            $logErrorCount24h++;
                        }
                    }
                }
            }
        }

        $storageLogsWritable = is_dir($logDir) && is_writable($logDir);

        // 5. Backups: Waktu Backup Terakhir & Hak Akses Tulis
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        $waktuBackupTerakhir = 'Belum ada backup';
        $storageBackupsWritable = is_dir($backupDir) && is_writable($backupDir);

        if (is_dir($backupDir)) {
            $files = scandir($backupDir);
            $latestMtime = 0;
            if ($files) {
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..' || $f === '.gitignore' || $f === '.gitkeep') {
                        continue;
                    }
                    $filePath = $backupDir . '/' . $f;
                    $mtime = filemtime($filePath);
                    if ($mtime > $latestMtime) {
                        $latestMtime = $mtime;
                    }
                }
            }
            if ($latestMtime > 0) {
                $waktuBackupTerakhir = date('Y-m-d H:i:s', $latestMtime);
            }
        }

        // 6. Versi Aplikasi dari Config (jika tidak ada tampilkan "Tidak diset")
        $appVersionRaw = \config('app.version');
        $versiAplikasi = (!empty($appVersionRaw) && is_string($appVersionRaw)) ? trim($appVersionRaw) : 'Tidak diset';

        return [
            'versi_php'                   => $versiPhp,
            'software_server'             => $softwareServer,
            'app_env'                     => $appEnv,
            'app_debug'                   => $appDebug,
            'https_aktif'                 => $httpsAktif,
            'zona_waktu'                  => $zonaWaktu,
            'database_status'             => $dbStatus['status'],
            'database_waktu_respons_ms'   => $dbStatus['waktu_respons_ms'],
            'nama_database'               => $dbStatus['nama_database'],
            'jumlah_pesanan'              => $jumlahPesanan,
            'jumlah_error_24_jam'         => $logErrorCount24h,
            'ukuran_file_app_log'         => $logUkuranFormatted,
            'ukuran_bytes_app_log'        => $logUkuranBytes,
            'waktu_backup_terakhir'       => $waktuBackupTerakhir,
            'storage_logs_bisa_ditulis'   => $storageLogsWritable,
            'storage_backups_bisa_ditulis'=> $storageBackupsWritable,
            'versi_aplikasi'              => $versiAplikasi,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        $val = round($bytes / pow(1024, $i), 2);
        return $val . ' ' . $units[$i];
    }
}

