<?php

namespace App\Helpers;

class Auth
{
    public const ROLE_OWNER       = 'owner';
    public const ROLE_ADMIN_OWNER = 'admin_owner';
    public const ROLE_OWNER_TOKO  = 'owner_toko';
    public const ROLE_ADMIN_TOKO  = 'admin_toko';
    public const ROLE_KURIR       = 'kurir';
    public const ROLE_CUSTOMER    = 'customer';

    public static function login(array $user): void
    {
        Session::set('user', [
            'id'               => (int) $user['id'],
            'name'             => $user['name'],
            'email'            => $user['email'],
            'role_id'          => (int) ($user['role_id'] ?? 0),
            'role_name'        => $user['role_name'] ?? self::ROLE_CUSTOMER,
            'role_display'     => $user['display_name'] ?? $user['role_display'] ?? ucfirst(str_replace('_', ' ', $user['role_name'] ?? 'Customer')),
            'phone'            => $user['phone'] ?? '',
            'avatar'           => $user['avatar'] ?? '',
            'is_active'        => (bool) ($user['is_active'] ?? true),
            'phone_verified_at'=> $user['phone_verified_at'] ?? null,
        ]);
    }

    public static function logout(): void
    {
        Session::remove('user');
    }

    public static function check(): bool
    {
        return Session::has('user') && Session::get('user')['is_active'] === true;
    }

    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user['id'] ?? null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role_name'] ?? null;
    }

    public static function roleDisplay(): ?string
    {
        $user = self::user();
        return $user['role_display'] ?? ucfirst(str_replace('_', ' ', self::role() ?? 'Customer'));
    }

    /**
     * Apakah akun sudah diverifikasi nomor WhatsApp-nya?
     * - Non-customer selalu dianggap terverifikasi (staff).
     * - Customer wajib punya phone_verified_at tidak NULL.
     */
    public static function isPhoneVerified(): bool
    {
        if (!self::check()) {
            return false;
        }
        // Staff roles selalu dianggap terverifikasi
        if (self::role() !== self::ROLE_CUSTOMER) {
            return true;
        }
        $user = self::user();
        return !empty($user['phone_verified_at']);
    }

    /**
     * Perbarui status verifikasi di session setelah OTP berhasil.
     */
    public static function markPhoneVerified(string $verifiedAt): void
    {
        $user = self::user();
        if ($user) {
            $user['phone_verified_at'] = $verifiedAt;
            Session::set('user', $user);
        }
    }

    public static function hasRole(string|array $roles): bool
    {
        if (!self::check()) {
            return false;
        }

        $currentRole = self::role();
        if (is_array($roles)) {
            return in_array($currentRole, $roles, true);
        }

        return $currentRole === $roles;
    }

    public static function getRedirectUrl(): string
    {
        $baseUrl = config('app.url', 'http://localhost/rpl/public');
        if (!self::check()) {
            return rtrim($baseUrl, '/') . '/login';
        }

        $role = self::role();
        $path = match ($role) {
            self::ROLE_OWNER       => '/dashboard/owner',
            self::ROLE_ADMIN_OWNER => '/dashboard/admin-owner',
            self::ROLE_OWNER_TOKO  => '/dashboard/owner-toko',
            self::ROLE_ADMIN_TOKO  => '/dashboard/admin-toko',
            self::ROLE_KURIR       => '/dashboard/kurir',
            self::ROLE_CUSTOMER    => '/dashboard/customer',
            default                => '/login'
        };

        return rtrim($baseUrl, '/') . $path;
    }
}

