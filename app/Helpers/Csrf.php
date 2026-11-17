<?php

namespace App\Helpers;

class Csrf
{
    public static function generateToken(): string
    {
        Session::start();
        if (!Session::has('_csrf_token')) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
        }
        return Session::get('_csrf_token');
    }

    public static function generate(): string
    {
        return self::generateToken();
    }

    public static function field(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(?string $token): bool
    {
        Session::start();
        $storedToken = Session::get('_csrf_token');
        if (!$storedToken || !$token) {
            return false;
        }
        return hash_equals($storedToken, $token);
    }
}
