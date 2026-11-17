<?php

namespace App\Helpers {
    class Config
    {
        private static array $configs = [];

        public static function get(string $key, mixed $default = null): mixed
        {
            // Backward compatibility alias for 'url'
            if ($key === 'url') {
                return self::get('app.url', $default);
            }

            $parts = explode('.', $key);
            $file = array_shift($parts);

            if (!isset(self::$configs[$file])) {
                $path = __DIR__ . "/../../config/{$file}.php";
                if (file_exists($path)) {
                    self::$configs[$file] = require $path;
                } else {
                    return $default;
                }
            }

            $current = self::$configs[$file];
            if (!is_array($current)) {
                return $default;
            }

            if (empty($parts)) {
                return !empty($current) ? $current : $default;
            }

            foreach ($parts as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return $default;
                }
                $current = $current[$segment];
            }

            return $current ?? $default;
        }

        public static function clear(): void
        {
            self::$configs = [];
        }
    }
}

namespace {
    if (!function_exists('config')) {
        function config(string $key, mixed $default = null): mixed
        {
            return \App\Helpers\Config::get($key, $default);
        }
    }
}
