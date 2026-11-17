<?php

namespace App\Helpers;

class Response
{
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header("Location: {$url}");
        }
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        self::json([
            'status' => 'error',
            'message' => $message
        ], $statusCode);
    }

    public static function success(string $message, mixed $data = null, int $statusCode = 200): void
    {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
}
