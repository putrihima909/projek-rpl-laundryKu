<?php

namespace App\Services\Auth;

use App\Core\Database;
use App\Helpers\Config;
use App\Helpers\PhoneHelper;
use App\Services\WhatsApp\WhatsAppNotificationService;
use PDO;
use Throwable;

class VerificationService
{
    protected ?PDO $db = null;
    protected int $otpValiditySeconds = 600; // 10 menit
    protected int $maxAttempts = 5;          // Maksimal 5x coba
    protected int $resendCooldownSeconds = 60; // 60 detik cooldown request ulang

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
     * Generate & kirim OTP via WhatsApp ke nomor user.
     * Mengembalikan array [ 'success' => bool, 'message' => string, 'cooldown' => int ].
     */
    public function sendOtp(int $userId): array
    {
        if (!$this->db || $userId <= 0) {
            return ['success' => false, 'message' => 'Database tidak tersedia atau User ID tidak valid.'];
        }

        $stmt = $this->db->prepare("SELECT id, name, phone, phone_verified_at, otp_sent_at, otp_attempts FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Akun pengguna tidak ditemukan.'];
        }

        if (!empty($user['phone_verified_at'])) {
            return ['success' => false, 'message' => 'Nomor WhatsApp akun ini sudah terverifikasi sebelumnya.'];
        }

        $phone = trim($user['phone'] ?? '');
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Nomor WhatsApp belum diisi pada profil akun Anda. Silakan lengkapi profil terlebih dahulu.'];
        }

        // Cek Rate-Limit / Cooldown Pengiriman Ulang
        if (!empty($user['otp_sent_at'])) {
            $lastSentTime = strtotime($user['otp_sent_at']);
            $elapsed = time() - $lastSentTime;
            if ($elapsed < $this->resendCooldownSeconds) {
                $wait = $this->resendCooldownSeconds - $elapsed;
                return [
                    'success' => false,
                    'message' => "Mohon tunggu {$wait} detik sebelum meminta kode OTP kembali.",
                    'cooldown' => $wait
                ];
            }
        }

        // Generate 6 digit angka aman
        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->otpValiditySeconds);
        $sentAt = date('Y-m-d H:i:s');

        // Simpan ke DB (hanya hash yang disimpan demi keamanan, bukan plaintext)
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET verification_otp = :otp_hash,
                otp_expires_at = :expires_at,
                otp_attempts = 0,
                otp_sent_at = :sent_at
            WHERE id = :id
        ");
        $updateStmt->execute([
            'otp_hash'   => $otpHash,
            'expires_at' => $expiresAt,
            'sent_at'    => $sentAt,
            'id'         => $userId
        ]);

        // Kirim via WhatsApp Notification Service
        $waMessage = "Halo *" . htmlspecialchars($user['name']) . "*,\n\n"
            . "Kode verifikasi (OTP) akun LaundryKu Anda adalah: *{$otp}*\n\n"
            . "Kode ini berlaku selama 10 menit. JANGAN bagikan kode ini kepada siapa pun termasuk pihak LaundryKu demi keamanan akun Anda.\n\n"
            . "Terima kasih telah menggunakan LaundryKu!";

        $sendSuccess = false;
        try {
            $waService = new WhatsAppNotificationService();
            // Kirim pesan langsung ke client WhatsApp yang aktif
            $normalizedPhone = PhoneHelper::normalize($phone);
            $client = $waService->getClient();
            $result = $client->sendMessage($normalizedPhone, $waMessage);
            if (is_array($result)) {
                $sendSuccess = !empty($result['success']);
            } elseif (is_object($result) && method_exists($result, 'isSuccess')) {
                $sendSuccess = $result->isSuccess();
            }
        } catch (Throwable $e) {
            error_log("[VerificationService::sendOtp] Error sending WA OTP: " . $e->getMessage());
            $sendSuccess = false;
        }

        return [
            'success' => true,
            'message' => 'Kode OTP verifikasi berhasil dikirim ke nomor WhatsApp Anda.',
            'phone' => $phone,
            'delivery_success' => $sendSuccess
        ];
    }

    /**
     * Verifikasi kode OTP yang diinput oleh user.
     * Mengembalikan array [ 'success' => bool, 'message' => string ].
     */
    public function verifyOtp(int $userId, string $inputOtp): array
    {
        if (!$this->db || $userId <= 0) {
            return ['success' => false, 'message' => 'Database tidak tersedia.'];
        }

        $inputOtp = trim($inputOtp);
        if (strlen($inputOtp) !== 6 || !ctype_digit($inputOtp)) {
            return ['success' => false, 'message' => 'Format kode OTP tidak valid. Harus berupa 6 digit angka.'];
        }

        $stmt = $this->db->prepare("SELECT id, verification_otp, otp_expires_at, otp_attempts, phone_verified_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Pengguna tidak ditemukan.'];
        }

        if (!empty($user['phone_verified_at'])) {
            return ['success' => true, 'message' => 'Akun sudah terverifikasi sebelumnya.'];
        }

        if (empty($user['verification_otp'])) {
            return ['success' => false, 'message' => 'Belum ada kode OTP yang diminta. Silakan minta kode OTP baru.'];
        }

        // Cek batas percobaan
        if ((int)$user['otp_attempts'] >= $this->maxAttempts) {
            return ['success' => false, 'message' => 'Batas percobaan verifikasi telah terlampaui (maksimal 5 kali). Silakan minta kode OTP baru.'];
        }

        // Cek masa berlaku (expired)
        if (empty($user['otp_expires_at']) || strtotime($user['otp_expires_at']) < time()) {
            return ['success' => false, 'message' => 'Kode OTP telah kadaluarsa. Silakan minta kode OTP baru.'];
        }

        // Validasi kode hash
        if (!password_verify($inputOtp, $user['verification_otp'])) {
            // Naikkan jumlah percobaan salah
            $newAttempts = (int)$user['otp_attempts'] + 1;
            $remaining = $this->maxAttempts - $newAttempts;
            $this->db->prepare("UPDATE users SET otp_attempts = :attempts WHERE id = :id")
                ->execute(['attempts' => $newAttempts, 'id' => $userId]);

            if ($remaining <= 0) {
                return ['success' => false, 'message' => 'Kode OTP salah. Anda telah mencapai batas maksimal percobaan. Silakan minta kode baru.'];
            }
            return ['success' => false, 'message' => "Kode OTP salah. Sisa kesempatan mencoba: {$remaining} kali."];
        }

        // Berhasil diverifikasi:
        // 1. Set phone_verified_at = now
        // 2. Kosongkan verification_otp agar tidak bisa dipakai dua kali (one-time use)
        // 3. Reset otp_attempts
        $verifiedAt = date('Y-m-d H:i:s');
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET phone_verified_at = :verified_at,
                verification_otp = NULL,
                otp_expires_at = NULL,
                otp_attempts = 0
            WHERE id = :id
        ");
        $updateStmt->execute([
            'verified_at' => $verifiedAt,
            'id'          => $userId
        ]);

        return [
            'success' => true,
            'message' => 'Nomor WhatsApp berhasil diverifikasi! Akun Anda kini aktif penuh.',
            'verified_at' => $verifiedAt
        ];
    }
}

