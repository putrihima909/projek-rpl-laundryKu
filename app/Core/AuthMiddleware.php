<?php

namespace App\Core;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Response;

class AuthMiddleware extends Middleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(): bool
    {
        // 1. Server-side Authentication Verification
        if (!Auth::check()) {
            Session::flash('error', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.');
            $baseUrl = config('app.url', 'http://localhost/rpl/public');
            Response::redirect($baseUrl . '/login');
            return false;
        }

        // 2. Server-side Authorization Verification (Role Check)
        if (!empty($this->allowedRoles) && !Auth::hasRole($this->allowedRoles)) {
            Session::flash('error', 'Akses ditolak! Anda tidak memiliki izin untuk membuka halaman tersebut.');
            
            // Redirect to user's authorized role dashboard
            $redirectUrl = Auth::getRedirectUrl();
            Response::redirect($redirectUrl);
            return false;
        }

        return true;
    }
}
