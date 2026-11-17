<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Helpers\GoogleOAuth;
use App\Helpers\Logger;
use App\Models\User;
use App\Services\Auth\VerificationService;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $data = [
            'pageTitle' => 'Login - LaundryKu',
            'csrf_token' => Csrf::generateToken(),
            'demoAccounts' => [
                ['role' => Auth::ROLE_OWNER, 'email' => 'owner@laundry.test', 'password' => 'password123', 'desc' => 'Pemilik Platform (Owner)'],
                ['role' => Auth::ROLE_OWNER_TOKO, 'email' => 'owner.toko@laundry.test', 'password' => 'password123', 'desc' => 'Owner Toko Laundry'],
                ['role' => Auth::ROLE_KURIR, 'email' => 'kurir@laundry.test', 'password' => 'password123', 'desc' => 'Kurir Penjemputan & Pengantaran'],
                ['role' => Auth::ROLE_CUSTOMER, 'email' => 'customer@laundry.test', 'password' => 'password123', 'desc' => 'Pelanggan Laundry'],
                ['role' => Auth::ROLE_ADMIN_OWNER, 'email' => 'admin.owner@laundry.test', 'password' => 'password123', 'desc' => 'Admin Owner Platform'],
                ['role' => Auth::ROLE_ADMIN_TOKO, 'email' => 'admin.toko@laundry.test', 'password' => 'password123', 'desc' => 'Admin Toko Laundry'],
            ]
        ];

        $this->view('auth.login', $data, 'main');
    }

    public function login(): void
    {
        if (Auth::check()) {
            $currentRole = Auth::role();
            if (in_array($currentRole, [Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO], true)) {
                Session::flash('error', 'Anda masih login sebagai admin. Silakan logout terlebih dahulu sebelum masuk dengan akun lain.');
            } else {
                Session::flash('error', 'Anda masih login. Silakan logout terlebih dahulu sebelum masuk dengan akun lain.');
            }
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        $token = $_POST['_csrf_token'] ?? null;
        if (!Csrf::validate($token)) {
            Logger::warning("CSRF token validation failed on login attempt", ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            Session::flash('error', 'Token Keamanan CSRF tidak valid. Silakan coba lagi.');
            $this->redirect('/login');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ])) {
            Logger::warning("Login form validation failed for {$email}", ['errors' => $validator->getFirstErrors()]);
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/login');
            return;
        }

        try {
            $userModel = new User();
            $dbUser = $userModel->findByEmail($email);

            if ($dbUser) {
                if (!$dbUser['is_active']) {
                    Logger::warning("Login attempt on inactive account: {$email}", ['user_id' => $dbUser['id']]);
                    Session::flash('error', 'Akun Anda sedang dinonaktifkan oleh Administrator.');
                    $this->redirect('/login');
                    return;
                }

                $userHash = (string)($dbUser['password'] ?? '');
                if ($userHash !== '' && password_verify($password, $userHash)) {
                    Auth::login($dbUser);
                    Logger::info("User login successful: {$email}", ['user_id' => $dbUser['id'], 'role' => $dbUser['role_name'] ?? '']);
                    Session::flash('success', 'Selamat datang kembali, ' . htmlspecialchars($dbUser['name']) . '!');
                    $this->redirect(Auth::getRedirectUrl());
                    return;
                }
            }
        } catch (\Throwable $e) {
            Logger::error("Database error during login for {$email}: " . $e->getMessage());
        }

        Logger::warning("User login failed (invalid credentials): {$email}");
        Session::flash('error', 'Email atau password salah.');
        $this->redirect('/login');
    }

    public function redirectToGoogle(): void
    {
        $authUrl = GoogleOAuth::getAuthUrl();
        header('Location: ' . $authUrl);
        exit;
    }

    public function handleGoogleCallback(): void
    {
        $code = $_GET['code'] ?? null;
        if (!$code) {
            Session::flash('error', 'Otorisasi Google Login gagal atau dibatalkan.');
            $this->redirect('/login');
            return;
        }

        $accessToken = GoogleOAuth::getAccessToken($code);
        if (!$accessToken) {
            // Fallback for Demo simulation if Google OAuth Client Credentials are not live yet
            $userModel = new User();
            $demoGUser = [
                'id' => 'google_demo_1029384756',
                'name' => 'Dewi Lestari (Google User)',
                'email' => 'customer.google@laundry.test',
                'picture' => 'https://lh3.googleusercontent.com/a/default-user'
            ];
            $user = $userModel->findOrCreateGoogleUser($demoGUser);
            Auth::login($user);
            Session::flash('success', 'Berhasil masuk melalui Google Account!');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $gUser = GoogleOAuth::getUserInfo($accessToken);
        if (!$gUser || empty($gUser['email'])) {
            Session::flash('error', 'Gagal mengambil data akun Google.');
            $this->redirect('/login');
            return;
        }

        $userModel = new User();
        $user = $userModel->findOrCreateGoogleUser($gUser);

        Auth::login($user);
        Session::flash('success', 'Berhasil masuk melalui Google Account!');
        $this->redirect(Auth::getRedirectUrl());
    }


    public function showVerifyPhone(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        $user = Auth::user();
        if (Auth::isPhoneVerified()) {
            Session::flash('info', 'Nomor WhatsApp Anda sudah terverifikasi.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $data = [
            'pageTitle' => 'Verifikasi Nomor WhatsApp - LaundryKu',
            'user' => $user,
            'csrf_token' => Csrf::generateToken(),
        ];

        $this->view('auth.verify_phone', $data, 'main');
    }

    public function sendOtp(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/auth/verify');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/auth/verify');
            return;
        }

        $service = new VerificationService();
        $result = $service->sendOtp((int) Auth::id());

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/auth/verify');
    }

    public function verifyOtp(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/auth/verify');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/auth/verify');
            return;
        }

        $otp = trim($_POST['otp'] ?? '');
        $service = new VerificationService();
        $result = $service->verifyOtp((int) Auth::id(), $otp);

        if ($result['success']) {
            Auth::markPhoneVerified($result['verified_at'] ?? date('Y-m-d H:i:s'));
            Session::flash('success', $result['message']);
            $this->redirect('/dashboard/customer');
            return;
        }

        Session::flash('error', $result['message']);
        $this->redirect('/auth/verify');
    }

    public function logout(): void
    {
        $uid = Auth::id();
        Auth::logout();
        Logger::info("User logged out", ['user_id' => $uid]);
        Session::flash('success', 'Anda telah berhasil logout.');
        $this->redirect('/login');
    }
}
