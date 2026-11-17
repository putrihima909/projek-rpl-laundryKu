<?php

namespace App\Helpers;

class Logger
{
    public const LEVEL_DEBUG   = 'DEBUG';
    public const LEVEL_INFO    = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR   = 'ERROR';

    private static ?string $logPath = null;

    /**
     * Get or set the default log file path.
     */
    public static function getLogPath(): string
    {
        if (self::$logPath === null) {
            $dir = __DIR__ . '/../../storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            self::$logPath = $dir . '/app.log';
        }
        return self::$logPath;
    }

    public static function setLogPath(string $path): void
    {
        self::$logPath = $path;
    }

    /**
     * Write log entry with level, message, and structured context.
     */
    public static function log(string $level, string $message, array $context = []): bool
    {
        $logFile = self::getLogPath();
        $date = date('Y-m-d H:i:s');
        $env = strtoupper((string) \config('app.env', 'PRODUCTION'));
        $level = strtoupper($level);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userId = Auth::check() ? Auth::id() : 'GUEST';

        $contextStr = !empty($context) ? ' | CONTEXT: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line = sprintf("[%s] [%s.%s] [IP:%s] [USER:%s] %s%s\n", $date, $env, $level, $ip, $userId, $message, $contextStr);

        return (bool) @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): bool
    {
        return self::log(self::LEVEL_INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): bool
    {
        return self::log(self::LEVEL_WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): bool
    {
        return self::log(self::LEVEL_ERROR, $message, $context);
    }

    public static function debug(string $message, array $context = []): bool
    {
        if (!\config('app.debug', false)) {
            return false;
        }
        return self::log(self::LEVEL_DEBUG, $message, $context);
    }
}
