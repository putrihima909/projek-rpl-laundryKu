<?php

namespace App\Services\Ai;

use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Env;
use App\Helpers\Logger;
use PDO;
use Throwable;

class AiSettings
{
    private ?PDO $db;

    // Platform default values
    public const DEFAULTS = [
        'ai_enabled'             => true,
        'ai_provider'            => 'gemini',
        'ai_model'               => 'gemini-3.5-flash-lite',
        'ai_temperature'         => 0.7,
        'ai_rate_limit_per_hour' => 20,
        'ai_data_max_rows'       => 15,
    ];

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
     * Helper untuk mengenkripsi teks rahasia (seperti API key) dengan AES-256-GCM.
     */
    public function encryptSecret(string $plain): array
    {
        if (preg_match('/\s/', $plain) || strlen($plain) < 10 || strlen($plain) > 255) {
            throw new \InvalidArgumentException("Format API key tidak valid. Panjang harus antara 10 hingga 255 karakter tanpa spasi.");
        }

        $appKey = (string) Env::get('APP_KEY', '');
        if (trim($appKey) === '' || strlen(trim($appKey)) < 32) {
            $fallbackSecret = (string) Env::get('PAYMENT_MOCK_SECRET', 'smartlaundry_pro_secret_key_32bytes_min_2026');
            $appKey = hash('sha256', $fallbackSecret . '_ai_encryption_salt');
        }

        $encryptionKey = hash('sha256', trim($appKey), true);
        $iv = random_bytes(12);
        $tag = "";
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag, "", 16);

        if ($ciphertext === false) {
            throw new \RuntimeException("Gagal mengenkripsi rahasia.");
        }

        return [
            'enc'   => base64_encode($iv . $tag . $ciphertext),
            'last4' => substr($plain, -4),
        ];
    }

    /**
     * Helper untuk mendekripsi teks rahasia AES-256-GCM.
     */
    public function decryptSecret(?string $enc): ?string
    {
        if ($enc === null || trim($enc) === '') {
            return null;
        }

        $appKey = (string) Env::get('APP_KEY', '');
        if (trim($appKey) === '' || strlen(trim($appKey)) < 32) {
            $fallbackSecret = (string) Env::get('PAYMENT_MOCK_SECRET', 'smartlaundry_pro_secret_key_32bytes_min_2026');
            $appKey = hash('sha256', $fallbackSecret . '_ai_encryption_salt');
        }

        $raw = base64_decode($enc, true);
        if ($raw !== false && strlen($raw) >= 28) {
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $ciphertext = substr($raw, 28);
            $encryptionKey = hash('sha256', trim($appKey), true);
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
            if ($decrypted !== false && $decrypted !== '') {
                return $decrypted;
            }
        }

        return null;
    }

    /**
     * Memeriksa status keberadaan API key dengan prioritas: Database (terenkripsi) > .env > Tidak ada.
     * Mengembalikan ['ada' => bool, 'sumber' => 'database'|'env'|'tidak ada', 'last4' => string]
     */
    public function apiKeyStatus(): array
    {
        try {
            // 1. Cek database (terenkripsi)
            $dbEnc = $this->getFromDb('gemini_api_key_enc');
            $dbLast4 = $this->getFromDb('gemini_api_key_last4');
            if ($dbEnc !== null && trim($dbEnc) !== '') {
                $decryptedKey = $this->getDecryptedApiKey();
                if ($decryptedKey !== null && trim($decryptedKey) !== '') {
                    return [
                        'ada'    => true,
                        'sumber' => 'database',
                        'last4'  => (string) ($dbLast4 ?? substr($decryptedKey, -4)),
                    ];
                }
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Error saat memeriksa status API key database: " . $e->getMessage());
        }

        // 2. Cek file .env / config
        try {
            $envKey = (string) \config('ai.gemini.api_key', '');
            $envKeyTrim = trim($envKey);
            if ($envKeyTrim !== '') {
                return [
                    'ada'    => true,
                    'sumber' => 'env',
                    'last4'  => substr($envKeyTrim, -4),
                ];
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Error saat membaca API key dari .env/config: " . $e->getMessage());
        }

        return [
            'ada'    => false,
            'sumber' => 'tidak ada',
            'last4'  => '',
        ];
    }

    /**
     * Memeriksa keberadaan API key aktif (apakah dari database atau .env).
     */
    public function hasApiKey(): bool
    {
        try {
            $status = $this->apiKeyStatus();
            return (bool) $status['ada'];
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Exception pada hasApiKey(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mendekripsi dan mengembalikan API key untuk pembuatan GeminiAiClient.
     * Prioritas: Database terenkripsi > .env.
     * Nilai ini HANYA digunakan internal saat instansiasi client, TIDAK BOLEH dicatat ke log/JSON/HTML.
     */
    public function getDecryptedApiKey(): ?string
    {
        try {
            // 1. Coba dekripsi dari database
            $dbEnc = $this->getFromDb('gemini_api_key_enc');
            if ($dbEnc !== null && trim($dbEnc) !== '') {
                $appKey = (string) Env::get('APP_KEY', '');
                if (trim($appKey) === '' || strlen(trim($appKey)) < 32) {
                    Logger::warning("AiSettings: APP_KEY di .env kosong atau kurang dari 32 karakter. Gagal mendekripsi API key dari database. Fallback ke .env/config.");
                } else {
                    $raw = base64_decode($dbEnc, true);
                    if ($raw !== false && strlen($raw) >= 28) {
                        $iv = substr($raw, 0, 12);
                        $tag = substr($raw, 12, 16);
                        $ciphertext = substr($raw, 28);
                        $encryptionKey = hash('sha256', trim($appKey), true);
                        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
                        if ($decrypted !== false && $decrypted !== '') {
                            return $decrypted;
                        }
                    }
                    Logger::warning("AiSettings: Dekripsi API key dari database gagal (ciphertext rusak atau tag tidak cocok). Fallback ke .env/config.");
                }
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Terjadi exception saat membaca/mendekripsi API key dari database: " . $e->getMessage());
        }

        // 2. Fallback ke .env / config
        try {
            $envKey = (string) \config('ai.gemini.api_key', '');
            if (trim($envKey) !== '') {
                return trim($envKey);
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Terjadi exception saat membaca API key dari .env/config: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Menyimpan API key terenkripsi ke database (AES-256-GCM).
     * Validasi: panjang 20 sampai 200 karakter, tanpa spasi.
     * APP_KEY di .env wajib diisi minimal 32 karakter.
     *
     * @throws \InvalidArgumentException jika validasi key atau APP_KEY gagal
     * @throws \RuntimeException jika enkripsi gagal
     */
    public function setApiKey(string $key, ?int $userId = null): bool
    {
        // Validasi panjang 20 sampai 200 tanpa spasi
        if (preg_match('/\s/', $key) || strlen($key) < 20 || strlen($key) > 200) {
            throw new \InvalidArgumentException("Format API key tidak valid. Panjang harus antara 20 hingga 200 karakter dan tidak boleh mengandung spasi.");
        }

        // Cek APP_KEY
        $appKey = (string) Env::get('APP_KEY', '');
        if (trim($appKey) === '' || strlen(trim($appKey)) < 32) {
            $fallbackSecret = (string) Env::get('PAYMENT_MOCK_SECRET', 'smartlaundry_pro_secret_key_32bytes_min_2026');
            $appKey = hash('sha256', $fallbackSecret . '_ai_encryption_salt');
        }

        if (!$this->db) {
            throw new \RuntimeException("Koneksi database tidak tersedia.");
        }

        // Enkripsi AES-256-GCM
        $encryptionKey = hash('sha256', trim($appKey), true);
        $iv = random_bytes(12);
        $tag = "";
        $ciphertext = openssl_encrypt($key, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag, "", 16);

        if ($ciphertext === false) {
            throw new \RuntimeException("Gagal mengenkripsi API key.");
        }

        $combinedBase64 = base64_encode($iv . $tag . $ciphertext);
        $last4 = substr($key, -4);

        $prevLast4 = $this->getFromDb('gemini_api_key_last4');
        $effectiveUserId = $userId ?? (Auth::check() ? Auth::id() : null);
        $role = Auth::check() ? (Auth::role() ?? 'system') : 'system';

        // Simpan nilai terenkripsi & last4
        $this->saveRawDbSetting('gemini_api_key_enc', $combinedBase64, $effectiveUserId);
        $this->saveRawDbSetting('gemini_api_key_last4', $last4, $effectiveUserId);

        // Catat audit (rahasia tidak pernah dicatat)
        $oldAudit = $prevLast4 ? "tersimpan (****{$prevLast4})" : "-";
        $newAudit = "diperbarui (****{$last4})";
        $this->recordAudit('gemini_api_key', $oldAudit, $newAudit, $effectiveUserId, $role, null);

        return true;
    }

    /**
     * Menghapus API key dari database.
     */
    public function clearApiKey(?int $userId = null): bool
    {
        if (!$this->db) {
            return false;
        }

        $prevLast4 = $this->getFromDb('gemini_api_key_last4');
        $effectiveUserId = $userId ?? (Auth::check() ? Auth::id() : null);
        $role = Auth::check() ? (Auth::role() ?? 'system') : 'system';

        $this->deleteRawDbSetting('gemini_api_key_enc');
        $this->deleteRawDbSetting('gemini_api_key_last4');

        $oldAudit = $prevLast4 ? "dihapus (****{$prevLast4})" : "-";
        $this->recordAudit('gemini_api_key', $oldAudit, "-", $effectiveUserId, $role, null);

        return true;
    }

    /**
     * Mencatat riwayat audit pengaturan ke tabel ai_settings_audit dan app.log.
     * Nilai rahasia TIDAK PERNAH disimpan mentah di sini.
     */
    public function recordAudit(
        string $key,
        ?string $oldValue,
        ?string $newValue,
        ?int $userId = null,
        ?string $role = null,
        ?int $storeId = null
    ): void {
        $effectiveUserId = $userId ?? (Auth::check() ? Auth::id() : null);
        $effectiveRole = $role ?? (Auth::check() ? (Auth::role() ?? 'system') : 'system');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // 1. Tulis ke app.log
        Logger::info("AiSettings Audit: Perubahan pengaturan [{$key}].", [
            'user_id'   => $effectiveUserId,
            'role'      => $effectiveRole,
            'store_id'  => $storeId,
            'key'       => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip'        => $ip,
        ]);

        // 2. Tulis ke database ai_settings_audit
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("INSERT INTO ai_settings_audit 
                    (user_id, role, store_id, setting_key, old_value, new_value, ip, created_at) 
                    VALUES (:uid, :role, :sid, :key, :old_val, :new_val, :ip, NOW())");
                $stmt->execute([
                    'uid'     => $effectiveUserId,
                    'role'    => $effectiveRole,
                    'sid'     => $storeId,
                    'key'     => $key,
                    'old_val' => $oldValue,
                    'new_val' => $newValue,
                    'ip'      => $ip,
                ]);
            } catch (Throwable $e) {
                Logger::error("AiSettings Audit: Gagal mencatat baris audit ke database.", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Mengambil nilai platform setting dengan prioritas: Database > .env/config > Bawaan
     */
    public function get(string $key): mixed
    {
        try {
            $dbVal = $this->getFromDb($key);
            if ($dbVal !== null) {
                return $this->castValue($key, $dbVal);
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Gagal membaca setting [{$key}] dari database: " . $e->getMessage());
        }

        try {
            $configVal = $this->getFromConfig($key);
            if ($configVal !== null) {
                return $this->castValue($key, $configVal);
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Gagal membaca setting [{$key}] dari config: " . $e->getMessage());
        }

        return self::DEFAULTS[$key] ?? null;
    }

    /**
     * Mengambil semua pengaturan platform efektif beserta metadata.
     */
    public function getAll(): array
    {
        $result = [];
        try {
            foreach (array_keys(self::DEFAULTS) as $k) {
                $result[$k] = $this->get($k);
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Error saat membaca pengaturan default: " . $e->getMessage());
            $result = self::DEFAULTS;
        }

        try {
            // Hitung provider efektif: jika provider gemini tapi API key kosong, fallback ke mock
            $effectiveProvider = $result['ai_provider'] ?? 'gemini';
            if (is_string($effectiveProvider)) {
                $effectiveProvider = strtolower(trim($effectiveProvider));
                if (str_starts_with($effectiveProvider, 'gemini')) {
                    $effectiveProvider = 'gemini';
                } elseif (str_starts_with($effectiveProvider, 'groq')) {
                    $effectiveProvider = 'groqcloud';
                } elseif (str_starts_with($effectiveProvider, 'openai_compatible')) {
                    $effectiveProvider = 'openai_compatible';
                } elseif (str_starts_with($effectiveProvider, 'openai')) {
                    $effectiveProvider = 'openai';
                } elseif (str_starts_with($effectiveProvider, 'mock')) {
                    $effectiveProvider = 'mock';
                }
            }
            $hasKey = $this->hasApiKey();
            $fallbackToMock = false;

            if ($effectiveProvider === 'gemini' && !$hasKey) {
                $effectiveProvider = 'mock';
                $fallbackToMock = true;
                Logger::warning("AiSettings: AI Provider is set to 'gemini' but GEMINI_API_KEY is empty. Effective provider fallback to 'mock'.");
            }

            $result['effective_provider'] = $effectiveProvider;
            $result['fallback_to_mock']   = $fallbackToMock;
            $result['has_api_key']        = $hasKey;
            $result['api_key_status']     = $this->apiKeyStatus();
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Error saat menghitung provider efektif: " . $e->getMessage());
            $result['effective_provider'] = 'mock';
            $result['fallback_to_mock']   = true;
            $result['has_api_key']        = false;
            $result['api_key_status']     = ['ada' => false, 'sumber' => 'tidak ada', 'last4' => ''];
        }

        return $result;
    }

    /**
     * Menyimpan pengaturan platform dengan validasi ketat, logging app.log, dan ai_settings_audit.
     *
     * @throws \InvalidArgumentException jika nilai di luar aturan
     */
    public function set(string $key, mixed $value, ?int $userId = null): bool
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException("Kunci pengaturan platform '{$key}' tidak dikenali.");
        }

        $validatedValue = $this->validatePlatformValue($key, $value);
        $oldValue = $this->get($key);

        if (!$this->db) {
            throw new \RuntimeException("Koneksi database tidak tersedia untuk menyimpan pengaturan.");
        }

        $sql = "INSERT INTO ai_settings (setting_key, setting_value, updated_by, updated_at) 
                VALUES (:key, :val, :uid, NOW()) 
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value), 
                    updated_by = VALUES(updated_by), 
                    updated_at = NOW()";

        $stmt = $this->db->prepare($sql);
        $storeVal = is_bool($validatedValue) ? ($validatedValue ? '1' : '0') : (string)$validatedValue;

        $effectiveUserId = $userId ?? (Auth::check() ? Auth::id() : null);
        $role = Auth::check() ? (Auth::role() ?? 'system') : 'system';

        $success = $stmt->execute([
            'key' => $key,
            'val' => $storeVal,
            'uid' => $effectiveUserId,
        ]);

        if ($success) {
            $oldAuditVal = is_bool($oldValue) ? ($oldValue ? '1' : '0') : ($oldValue !== null ? (string)$oldValue : null);
            $this->recordAudit($key, $oldAuditVal, $storeVal, $effectiveUserId, $role, null);
        }

        return $success;
    }

    /**
     * Mengambil konfigurasi API Chatbot khusus toko dari tabel store_ai_settings.
     */
    public function getStoreAiApiConfig(int $storeId): array
    {
        $default = [
            'store_id'       => $storeId,
            'ai_enabled'     => true,
            'provider'       => 'gemini',
            'model'          => 'gemini-2.5-flash',
            'has_api_key'    => false,
            'api_key_last4'  => '',
            'base_url'       => '',
            'status'         => 'active',
            'updated_by'     => null,
            'updated_at'     => null,
        ];

        if (!$this->db || $storeId <= 0) {
            return $default;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM store_ai_settings WHERE store_id = :sid LIMIT 1");
            $stmt->execute(['sid' => $storeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $hasCustomKey = !empty($row['api_key_enc']);
                $provider = !empty($row['provider']) ? (string)$row['provider'] : 'gemini';
                $model = !empty($row['model']) ? (string)$row['model'] : 'gemini-2.5-flash';
                $baseUrl = !empty($row['base_url']) ? (string)$row['base_url'] : '';
                $aiEnabled = isset($row['ai_enabled']) ? (bool)$row['ai_enabled'] : true;
                $last4 = (string)($row['api_key_last4'] ?? '');

                return [
                    'store_id'       => $storeId,
                    'ai_enabled'     => $aiEnabled,
                    'provider'       => $provider,
                    'model'          => $model,
                    'has_api_key'    => $hasCustomKey,
                    'api_key_last4'  => $last4,
                    'base_url'       => $baseUrl,
                    'status'         => ($aiEnabled && $hasCustomKey) ? 'active' : ($aiEnabled ? 'ready' : 'inactive'),
                    'updated_by'     => $row['updated_by'] ? (int)$row['updated_by'] : null,
                    'updated_at'     => $row['updated_at'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Error getStoreAiApiConfig: " . $e->getMessage());
        }

        return $default;
    }

    /**
     * Menyimpan konfigurasi API Chatbot khusus toko ke tabel store_ai_settings.
     */
    public function saveStoreAiApiConfig(int $storeId, array $data, ?int $userId = null): bool
    {
        if (!$this->db || $storeId <= 0) {
            return false;
        }

        $aiEnabled = isset($data['ai_enabled']) ? (bool)$data['ai_enabled'] : true;
        $provider = strtolower(trim((string)($data['provider'] ?? 'gemini')));
        if (!in_array($provider, ['gemini', 'groqcloud', 'openai', 'openai_compatible', 'mock'], true)) {
            $provider = 'gemini';
        }

        $model = trim((string)($data['model'] ?? 'gemini-2.5-flash'));
        if ($model === '') {
            $model = match($provider) {
                'groqcloud' => 'llama-3.3-70b-versatile',
                'openai'    => 'gpt-4o-mini',
                default     => 'gemini-2.5-flash',
            };
        }

        $baseUrl = trim((string)($data['base_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = match($provider) {
                'groqcloud' => 'https://api.groq.com/openai/v1',
                'openai'    => 'https://api.openai.com/v1',
                default     => '',
            };
        }

        $effectiveUserId = $userId ?? (Auth::check() ? Auth::id() : null);
        $role = Auth::check() ? (Auth::role() ?? 'system') : 'system';

        $apiKey = trim((string)($data['api_key'] ?? ''));
        $clearApiKey = !empty($data['clear_api_key']);

        // Persiapkan enkripsi API key jika ada input baru
        $apiKeyEnc = null;
        $apiKeyLast4 = null;
        $updateKeySql = "";
        $params = [
            'sid'       => $storeId,
            'enabled'   => $aiEnabled ? 1 : 0,
            'provider'  => $provider,
            'model'     => $model,
            'base_url'  => $baseUrl !== '' ? $baseUrl : null,
            'uid'       => $effectiveUserId,
        ];

        $columns = ["store_id", "ai_enabled", "provider", "model", "base_url", "updated_by"];
        $values = [":sid", ":enabled", ":provider", ":model", ":base_url", ":uid"];
        $updates = [
            "ai_enabled = VALUES(ai_enabled)",
            "provider = VALUES(provider)",
            "model = VALUES(model)",
            "base_url = VALUES(base_url)",
            "updated_by = VALUES(updated_by)",
            "updated_at = NOW()"
        ];

        if ($clearApiKey) {
            $updates[] = "api_key_enc = NULL";
            $updates[] = "api_key_last4 = NULL";
        } elseif ($apiKey !== '') {
            $encResult = $this->encryptSecret($apiKey);
            $apiKeyEnc = $encResult['enc'];
            $apiKeyLast4 = $encResult['last4'];
            $columns[] = "api_key_enc";
            $columns[] = "api_key_last4";
            $values[] = ":k_enc";
            $values[] = ":k_last4";
            $updates[] = "api_key_enc = VALUES(api_key_enc)";
            $updates[] = "api_key_last4 = VALUES(api_key_last4)";
            $params['k_enc'] = $apiKeyEnc;
            $params['k_last4'] = $apiKeyLast4;
        }

        $sql = "INSERT INTO store_ai_settings (" . implode(', ', $columns) . ", updated_at) 
                VALUES (" . implode(', ', $values) . ", NOW()) 
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            $auditDesc = "Provider: {$provider}, Model: {$model}, Status: " . ($aiEnabled ? 'Aktif' : 'Nonaktif');
            if ($apiKeyLast4) {
                $auditDesc .= ", Key: ****{$apiKeyLast4}";
            } elseif ($clearApiKey) {
                $auditDesc .= ", Key: dihapus";
            }
            $this->recordAudit('store_ai_api_settings', null, $auditDesc, $effectiveUserId, $role, $storeId);
        }

        return $success;
    }

    /**
     * Mendapatkan decrypted API key untuk toko tertentu.
     */
    public function getStoreDecryptedApiKey(int $storeId): ?string
    {
        if (!$this->db || $storeId <= 0) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("SELECT api_key_enc FROM store_ai_settings WHERE store_id = :sid LIMIT 1");
            $stmt->execute(['sid' => $storeId]);
            $enc = $stmt->fetchColumn();

            if ($enc) {
                $decrypted = $this->decryptSecret((string)$enc);
                if ($decrypted !== null && trim($decrypted) !== '') {
                    return $decrypted;
                }
            }
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Gagal mendekripsi API key toko {$storeId}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Mengambil konfigurasi AI lengkap efektif untuk toko (termasuk fallback).
     */
    public function getEffectiveStoreAiConfig(int $storeId): array
    {
        $storeCfg = $this->getStoreAiApiConfig($storeId);
        $storeApiKey = $this->getStoreDecryptedApiKey($storeId);

        $platformKey = $this->getDecryptedApiKey();
        $finalKey = $storeApiKey ?? $platformKey;

        $provider = $storeCfg['provider'] ?? 'gemini';
        $model = $storeCfg['model'] ?? 'gemini-2.5-flash';
        $baseUrl = $storeCfg['base_url'] ?? null;
        $aiEnabled = (bool)($storeCfg['ai_enabled'] ?? true);

        $hasKey = ($finalKey !== null && trim($finalKey) !== '');
        $effectiveProvider = $provider;

        if ($provider !== 'mock' && !$hasKey) {
            $effectiveProvider = 'mock';
        }

        return [
            'store_id'           => $storeId,
            'ai_enabled'         => $aiEnabled,
            'provider'           => $provider,
            'effective_provider' => $effectiveProvider,
            'model'              => $model,
            'base_url'           => $baseUrl,
            'api_key'            => $finalKey,
            'has_api_key'        => $hasKey,
            'has_store_key'      => ($storeApiKey !== null),
            'api_key_last4'      => $storeCfg['api_key_last4'] ?? '',
        ];
    }

    /**
     * Mengambil pengaturan AI khusus toko dari tabel store_ai_settings.
     */
    public function getStoreSettings(int $storeId): array
    {
        $default = [
            'store_id'             => $storeId,
            'ai_enabled'           => true,
            'info_jam_operasional' => '',
            'info_antar_jemput'    => '',
            'info_kebijakan'       => '',
            'info_faq'             => '',
            'updated_by'           => null,
            'updated_at'           => null,
        ];

        if (!$this->db || $storeId <= 0) {
            return $default;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM store_ai_settings WHERE store_id = :sid LIMIT 1");
            $stmt->execute(['sid' => $storeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'store_id'             => (int) $row['store_id'],
                    'ai_enabled'           => (bool) $row['ai_enabled'],
                    'info_jam_operasional' => (string) ($row['info_jam_operasional'] ?? ''),
                    'info_antar_jemput'    => (string) ($row['info_antar_jemput'] ?? ''),
                    'info_kebijakan'       => (string) ($row['info_kebijakan'] ?? ''),
                    'info_faq'             => (string) ($row['info_faq'] ?? ''),
                    'updated_by'           => $row['updated_by'] ? (int) $row['updated_by'] : null,
                    'updated_at'           => $row['updated_at'] ?? null,
                ];
            }
        } catch (Throwable $e) {}

        return $default;
    }

    /**
     * Menyimpan pengaturan AI toko dengan batas 1000 karakter per kolom informasi dan strip_tags.
     * Mencatat perubahan ke ai_settings_audit dan app.log.
     */
    public function saveStoreSettings(int $storeId, array $data, ?int $userId = null): bool
    {
        if (!$this->db || $storeId <= 0) {
            return false;
        }

        $aiEnabled = isset($data['ai_enabled']) ? (bool)$data['ai_enabled'] : true;

        $cleanText = function(mixed $val): string {
            if ($val === null) return '';
            $stripped = trim(strip_tags((string)$val));
            return mb_substr($stripped, 0, 1000);
        };

        $jam        = $cleanText($data['info_jam_operasional'] ?? '');
        $antar      = $cleanText($data['info_antar_jemput'] ?? '');
        $kebijakan  = $cleanText($data['info_kebijakan'] ?? '');
        $faq        = $cleanText($data['info_faq'] ?? '');

        $effectiveUserId = $userId ?? (Auth::check() ? Auth::id() : null);
        $role = Auth::check() ? (Auth::role() ?? 'system') : 'system';

        $sql = "INSERT INTO store_ai_settings 
                (store_id, ai_enabled, info_jam_operasional, info_antar_jemput, info_kebijakan, info_faq, updated_by, updated_at) 
                VALUES (:sid, :enabled, :jam, :antar, :kebijakan, :faq, :uid, NOW()) 
                ON DUPLICATE KEY UPDATE 
                    ai_enabled = VALUES(ai_enabled), 
                    info_jam_operasional = VALUES(info_jam_operasional), 
                    info_antar_jemput = VALUES(info_antar_jemput), 
                    info_kebijakan = VALUES(info_kebijakan), 
                    info_faq = VALUES(info_faq), 
                    updated_by = VALUES(updated_by), 
                    updated_at = NOW()";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'sid'       => $storeId,
            'enabled'   => $aiEnabled ? 1 : 0,
            'jam'       => $jam !== '' ? $jam : null,
            'antar'     => $antar !== '' ? $antar : null,
            'kebijakan' => $kebijakan !== '' ? $kebijakan : null,
            'faq'       => $faq !== '' ? $faq : null,
            'uid'       => $effectiveUserId,
        ]);

        if ($success) {
            $summaryNew = json_encode([
                'ai_enabled'   => $aiEnabled,
                'len_jam'      => mb_strlen($jam),
                'len_antar'    => mb_strlen($antar),
                'len_kebijakan'=> mb_strlen($kebijakan),
                'len_faq'      => mb_strlen($faq),
            ]);
            $this->recordAudit(
                'store_ai_settings',
                'konfigurasi_lama',
                $summaryNew,
                $effectiveUserId,
                $role,
                $storeId
            );
        }

        return $success;
    }

    /**
     * Validasi nilai pengaturan platform.
     */
    private function validatePlatformValue(string $key, mixed $value): mixed
    {
        switch ($key) {
            case 'ai_enabled':
                if (is_bool($value)) return $value;
                if ($value === 1 || $value === '1' || $value === 'true') return true;
                if ($value === 0 || $value === '0' || $value === 'false') return false;
                throw new \InvalidArgumentException("Nilai 'ai_enabled' harus berupa boolean (true/false).");

            case 'ai_provider':
                $v = strtolower(trim((string)$value));
                if (str_starts_with($v, 'gemini')) {
                    $v = 'gemini';
                } elseif (str_starts_with($v, 'groq')) {
                    $v = 'groqcloud';
                } elseif (str_starts_with($v, 'openai_compatible')) {
                    $v = 'openai_compatible';
                } elseif (str_starts_with($v, 'openai')) {
                    $v = 'openai';
                } elseif (str_starts_with($v, 'mock')) {
                    $v = 'mock';
                }
                if (!in_array($v, ['gemini', 'groqcloud', 'openai', 'openai_compatible', 'mock'], true)) {
                    throw new \InvalidArgumentException("Nilai 'ai_provider' harus salah satu dari: gemini, groqcloud, openai, openai_compatible, mock. Diterima: '{$value}'");
                }
                return $v;

            case 'ai_model':
                $v = trim((string)$value);
                if (!preg_match('/^[a-z0-9.\-]{3,60}$/', $v)) {
                    throw new \InvalidArgumentException("Nilai 'ai_model' tidak valid. Harus berupa huruf kecil, angka, titik, atau strip (3-60 karakter). Diterima: '{$value}'");
                }
                return $v;

            case 'ai_temperature':
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException("Nilai 'ai_temperature' harus berupa angka antara 0 dan 1.");
                }
                $v = (float)$value;
                if ($v < 0.0 || $v > 1.0) {
                    throw new \InvalidArgumentException("Nilai 'ai_temperature' harus bernilai antara 0.0 hingga 1.0. Diterima: {$v}");
                }
                return $v;

            case 'ai_rate_limit_per_hour':
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException("Nilai 'ai_rate_limit_per_hour' harus berupa bilangan bulat antara 1 dan 200.");
                }
                $v = (int)$value;
                if ($v < 1 || $v > 200) {
                    throw new \InvalidArgumentException("Nilai 'ai_rate_limit_per_hour' harus berada dalam rentang 1 hingga 200. Diterima: {$v}");
                }
                return $v;

            case 'ai_data_max_rows':
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException("Nilai 'ai_data_max_rows' harus berupa bilangan bulat antara 5 dan 50.");
                }
                $v = (int)$value;
                if ($v < 5 || $v > 50) {
                    throw new \InvalidArgumentException("Nilai 'ai_data_max_rows' harus berada dalam rentang 5 hingga 50. Diterima: {$v}");
                }
                return $v;

            default:
                return $value;
        }
    }

    private function getFromDb(string $key): ?string
    {
        if (!$this->db) return null;
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = :k LIMIT 1");
            $stmt->execute(['k' => $key]);
            $res = $stmt->fetchColumn();
            return $res !== false ? (string)$res : null;
        } catch (Throwable $e) {
            Logger::warning("AiSettings: Gagal query database untuk kunci [{$key}]: " . $e->getMessage());
            return null;
        }
    }

    private function saveRawDbSetting(string $key, ?string $value, ?int $userId = null): bool
    {
        if (!$this->db) return false;
        try {
            $sql = "INSERT INTO ai_settings (setting_key, setting_value, updated_by, updated_at) 
                    VALUES (:key, :val, :uid, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value), 
                        updated_by = VALUES(updated_by), 
                        updated_at = NOW()";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'key' => $key,
                'val' => $value,
                'uid' => $userId,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function deleteRawDbSetting(string $key): bool
    {
        if (!$this->db) return false;
        try {
            $stmt = $this->db->prepare("DELETE FROM ai_settings WHERE setting_key = :key");
            return $stmt->execute(['key' => $key]);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function getFromConfig(string $key): mixed
    {
        return match ($key) {
            'ai_enabled'             => \config('ai.enabled'),
            'ai_provider'            => \config('ai.provider'),
            'ai_model'               => \config('ai.gemini.model'),
            'ai_temperature'         => \config('ai.temperature'),
            'ai_rate_limit_per_hour' => \config('ai.rate_limit_per_hour'),
            'ai_data_max_rows'       => \config('ai.data_max_rows'),
            default                  => null,
        };
    }

    private function castValue(string $key, mixed $val): mixed
    {
        if ($val === null) return null;
        return match ($key) {
            'ai_enabled'             => (bool)($val === '1' || $val === 1 || $val === true || $val === 'true'),
            'ai_provider'            => (function ($v) {
                $s = strtolower(trim((string)$v));
                if (str_starts_with($s, 'gemini')) return 'gemini';
                if (str_starts_with($s, 'groq')) return 'groqcloud';
                if (str_starts_with($s, 'openai_compatible')) return 'openai_compatible';
                if (str_starts_with($s, 'openai')) return 'openai';
                if (str_starts_with($s, 'mock')) return 'mock';
                return $s;
            })($val),
            'ai_model'               => (string)$val,
            'ai_temperature'         => (float)$val,
            'ai_rate_limit_per_hour' => (int)$val,
            'ai_data_max_rows'       => (int)$val,
            default                  => $val,
        };
    }

    /**
     * Memastikan tabel ai_model_pool tersedia di database.
     */
    public function ensureModelPoolTable(): void
    {
        if (!$this->db) return;
        $sql = "CREATE TABLE IF NOT EXISTS ai_model_pool (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NULL,
            provider VARCHAR(50) NOT NULL,
            model_name VARCHAR(100) NOT NULL,
            label VARCHAR(100) NULL,
            api_key_enc TEXT NULL,
            api_key_last4 VARCHAR(10) NULL,
            base_url VARCHAR(255) NULL,
            is_primary TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_store (store_id),
            INDEX idx_primary (is_primary)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->exec($sql);
        } catch (\Throwable $e) {}
    }

    /**
     * Mengambil daftar model AI dalam pool (baik untuk platform maupun cabang toko).
     */
    public function getModelPool(?int $storeId = null): array
    {
        if (!$this->db) return [];
        $this->ensureModelPoolTable();

        try {
            if ($storeId !== null && $storeId > 0) {
                $stmt = $this->db->prepare("SELECT * FROM ai_model_pool WHERE store_id = :sid ORDER BY is_primary DESC, id DESC");
                $stmt->execute(['sid' => $storeId]);
            } else {
                $stmt = $this->db->prepare("SELECT * FROM ai_model_pool WHERE store_id IS NULL ORDER BY is_primary DESC, id DESC");
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Menambahkan model AI baru ke failover pool.
     */
    public function addModelToPool(array $data, ?int $storeId = null, ?int $userId = null): int
    {
        if (!$this->db) return 0;
        $this->ensureModelPoolTable();

        $provider = strtolower(trim((string)($data['provider'] ?? 'gemini')));
        $modelName = trim((string)($data['model_name'] ?? ''));
        if ($modelName === '') {
            $modelName = match($provider) {
                'groqcloud' => 'llama-3.3-70b-versatile',
                'openai'    => 'gpt-4o-mini',
                default     => 'gemini-2.5-flash',
            };
        }
        $label = trim((string)($data['label'] ?? ''));
        if ($label === '') {
            $label = ucfirst($provider) . ' - ' . $modelName;
        }
        $rawKey = trim((string)($data['api_key'] ?? ''));
        $baseUrl = trim((string)($data['base_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = match($provider) {
                'groqcloud' => 'https://api.groq.com/openai/v1',
                'openai'    => 'https://api.openai.com/v1',
                default     => '',
            };
        }
        $isPrimary = !empty($data['is_primary']) ? 1 : 0;

        $apiKeyEnc = null;
        $apiKeyLast4 = null;
        if ($rawKey !== '') {
            $encRes = $this->encryptSecret($rawKey);
            $apiKeyEnc = $encRes['enc'];
            $apiKeyLast4 = $encRes['last4'];
        }

        // Jika diset sebagai model utama, nonaktifkan flag is_primary model lain
        if ($isPrimary) {
            if ($storeId !== null && $storeId > 0) {
                $upd = $this->db->prepare("UPDATE ai_model_pool SET is_primary = 0 WHERE store_id = :sid");
                $upd->execute(['sid' => $storeId]);
            } else {
                $upd = $this->db->prepare("UPDATE ai_model_pool SET is_primary = 0 WHERE store_id IS NULL");
                $upd->execute();
            }
        }

        $stmt = $this->db->prepare("INSERT INTO ai_model_pool 
            (store_id, provider, model_name, label, api_key_enc, api_key_last4, base_url, is_primary, is_active, created_by, created_at, updated_at)
            VALUES (:sid, :prov, :model, :lbl, :k_enc, :k_last4, :burl, :is_prim, 1, :uid, NOW(), NOW())");
        $stmt->execute([
            'sid'     => $storeId,
            'prov'    => $provider,
            'model'   => $modelName,
            'lbl'     => $label,
            'k_enc'   => $apiKeyEnc,
            'k_last4' => $apiKeyLast4,
            'burl'    => $baseUrl !== '' ? $baseUrl : null,
            'is_prim' => $isPrimary,
            'uid'     => $userId,
        ]);
        $newId = (int) $this->db->lastInsertId();

        // Jika diset sebagai model utama, sinkronkan ke konfigurasi live
        if ($isPrimary) {
            $this->applyModelToLiveConfig($provider, $modelName, $baseUrl, $rawKey, $storeId, $userId);
        }

        $this->recordAudit(
            'tambah_model_pool',
            null,
            "Provider: {$provider}, Model: {$modelName}, Label: {$label}, Primary: " . ($isPrimary ? 'Ya' : 'Tidak'),
            $userId,
            null,
            $storeId
        );

        return $newId;
    }

    /**
     * Mengaktifkan model dari pool sebagai model utama (Primary Live Model).
     */
    public function setPrimaryModelFromPool(int $modelId, ?int $storeId = null, ?int $userId = null): bool
    {
        if (!$this->db || $modelId <= 0) return false;
        $this->ensureModelPoolTable();

        // Ambil data model
        if ($storeId !== null && $storeId > 0) {
            $stmt = $this->db->prepare("SELECT * FROM ai_model_pool WHERE id = :mid AND store_id = :sid LIMIT 1");
            $stmt->execute(['mid' => $modelId, 'sid' => $storeId]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM ai_model_pool WHERE id = :mid AND store_id IS NULL LIMIT 1");
            $stmt->execute(['mid' => $modelId]);
        }
        $target = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$target) return false;

        // Reset is_primary yang lain
        if ($storeId !== null && $storeId > 0) {
            $this->db->prepare("UPDATE ai_model_pool SET is_primary = 0 WHERE store_id = :sid")->execute(['sid' => $storeId]);
            $this->db->prepare("UPDATE ai_model_pool SET is_primary = 1 WHERE id = :mid")->execute(['mid' => $modelId]);
        } else {
            $this->db->prepare("UPDATE ai_model_pool SET is_primary = 0 WHERE store_id IS NULL")->execute();
            $this->db->prepare("UPDATE ai_model_pool SET is_primary = 1 WHERE id = :mid")->execute(['mid' => $modelId]);
        }

        // Dekripsi key jika ada untuk disinkronkan ke live config
        $decryptedKey = null;
        if (!empty($target['api_key_enc'])) {
            $decryptedKey = $this->decryptSecret((string)$target['api_key_enc']);
        }

        $this->applyModelToLiveConfig(
            (string)$target['provider'],
            (string)$target['model_name'],
            (string)($target['base_url'] ?? ''),
            $decryptedKey,
            $storeId,
            $userId
        );

        $this->recordAudit(
            'aktivasi_model_pool',
            null,
            "Diaktifkan sebagai model utama: " . $target['provider'] . " - " . $target['model_name'],
            $userId,
            null,
            $storeId
        );

        return true;
    }

    /**
     * Menghapus model dari pool.
     */
    public function deleteModelFromPool(int $modelId, ?int $storeId = null): bool
    {
        if (!$this->db || $modelId <= 0) return false;
        $this->ensureModelPoolTable();

        if ($storeId !== null && $storeId > 0) {
            $stmt = $this->db->prepare("DELETE FROM ai_model_pool WHERE id = :mid AND store_id = :sid");
            return $stmt->execute(['mid' => $modelId, 'sid' => $storeId]);
        } else {
            $stmt = $this->db->prepare("DELETE FROM ai_model_pool WHERE id = :mid AND store_id IS NULL");
            return $stmt->execute(['mid' => $modelId]);
        }
    }

    /**
     * Mengambil satu data model dari pool (termasuk dekripsi API key jika ada).
     */
    public function getModelFromPool(int $modelId, ?int $storeId = null): ?array
    {
        if (!$this->db || $modelId <= 0) return null;
        $this->ensureModelPoolTable();

        if ($storeId !== null && $storeId > 0) {
            $stmt = $this->db->prepare("SELECT * FROM ai_model_pool WHERE id = :mid AND store_id = :sid LIMIT 1");
            $stmt->execute(['mid' => $modelId, 'sid' => $storeId]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM ai_model_pool WHERE id = :mid AND store_id IS NULL LIMIT 1");
            $stmt->execute(['mid' => $modelId]);
        }
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$res) return null;

        if (!empty($res['api_key_enc'])) {
            $res['decrypted_key'] = $this->decryptSecret((string)$res['api_key_enc']);
        } else {
            $res['decrypted_key'] = null;
        }

        return $res;
    }

    /**
     * Sinkronisasi model yang dipilih ke konfigurasi live (store_ai_settings atau platform settings).
     */
    private function applyModelToLiveConfig(string $provider, string $model, ?string $baseUrl, ?string $apiKey, ?int $storeId = null, ?int $userId = null): void
    {
        if ($storeId !== null && $storeId > 0) {
            $this->saveStoreAiApiConfig($storeId, [
                'provider'   => $provider,
                'model'      => $model,
                'base_url'   => $baseUrl ?? '',
                'ai_enabled' => true,
                'api_key'    => $apiKey ?? '',
            ], $userId);
        } else {
            $this->set('ai_provider', $provider);
            $this->set('ai_model', $model);
            if (!empty($apiKey)) {
                $this->setApiKey($apiKey, $userId);
            }
        }
    }
}
