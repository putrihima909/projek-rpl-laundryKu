<?php

namespace App\Helpers;

class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $value = trim($value, '"\''); // Remove quotes

                self::$variables[$name] = $value;
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$variables[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $envValue = getenv($key);
        if ($envValue !== false) {
            return $envValue;
        }

        return $default;
    }
}
