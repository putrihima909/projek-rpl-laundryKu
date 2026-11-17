<?php

namespace App\Services\Ai;

use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Logger;
use PDO;
use Throwable;

class AiHealthCheck
{
    private ?PDO $db;
    private AiSettings $settings;

    public function __construct(?PDO $db = null, ?AiSettings $settings = null)
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
        $this->settings = $settings ?? new AiSettings($this->db);
    }

    /**
     * Menjalankan audit kesehatan ekosistem AI lengkap.
     *
     * @param bool $pingLlm Apakah akan melakukan ping langsung ke endpoint AI provider
     * @return array Hasil pengujian terstruktur tanpa membocorkan API key
     */
    public function run(bool $pingLlm = false): array
    {
        // 1. Status Koneksi Database (SELECT 1 dan waktu respons)
        $dbStatus = [
            'connected'    => false,
            'latency_ms'   => 0.0,
            'message'      => 'Koneksi database tidak tersedia.'
        ];

        if ($this->db) {
            $startDb = microtime(true);
            try {
                $stmt = $this->db->query("SELECT 1");
                if ($stmt && $stmt->fetchColumn() == 1) {
                    $elapsed = (microtime(true) - $startDb) * 1000;
                    $dbStatus = [
                        'connected'    => true,
                        'latency_ms'   => round($elapsed, 2),
                        'message'      => 'Koneksi database OK.'
                    ];
                }
            } catch (Throwable $e) {
                $dbStatus['message'] = $this->sanitizeErrorMessage('Database error: ' . $e->getMessage());
            }
        }

        // 2. Provider dan Model Efektif
        $allSettings = $this->settings->getAll();
        $aiEnabled   = (bool)$allSettings['ai_enabled'];
        $provider    = (string)$allSettings['effective_provider'];
        $configuredProvider = (string)$allSettings['ai_provider'];
        $model       = (string)$allSettings['ai_model'];
        $temperature = (float)$allSettings['ai_temperature'];
        $rateLimit   = (int)$allSettings['ai_rate_limit_per_hour'];
        $hasApiKey   = (bool)$allSettings['has_api_key'];
        $fallback    = (bool)$allSettings['fallback_to_mock'];

        // 3. Jumlah Pesan Chat 24 Jam Terakhir
        $chatCount24h = 0;
        if ($this->db) {
            try {
                $stmtChat = $this->db->query("SELECT COUNT(*) FROM ai_chat_messages WHERE created_at >= NOW() - INTERVAL 24 HOUR");
                $chatCount24h = (int) $stmtChat->fetchColumn();
            } catch (Throwable $e) {
                $chatCount24h = 0;
            }
        }

        // 4. Error AI Terakhir dari app.log (disanitasi tanpa API key)
        $lastAiError = $this->getLastAiErrorFromLog();

        // 5. Ping LLM jika diminta ($pingLlm = true)
        $llmPing = null;
        if ($pingLlm) {
            $providerTestRes = $this->testProvider();
            $llmPing = [
                'ping_executed' => true,
                'success'       => $providerTestRes['ok'],
                'latency_ms'    => $providerTestRes['ms'],
                'message'       => $providerTestRes['pesan'],
            ];
        }

        return [
            'timestamp'               => date('Y-m-d H:i:s'),
            'ai_enabled'              => $aiEnabled,
            'database'                => $dbStatus,
            'provider'                => $provider,
            'provider_effective'      => $provider,
            'provider_configured'     => $configuredProvider,
            'fallback_to_mock'        => $fallback,
            'model'                   => $model,
            'temperature'             => $temperature,
            'has_api_key'             => $hasApiKey,
            'rate_limit_per_hour'     => $rateLimit,
            'chat_messages_24h'       => $chatCount24h,
            'chat_messages_last_24h'  => $chatCount24h,
            'last_error'              => $lastAiError,
            'last_ai_error'           => $lastAiError,
            'llm_ping'                => $llmPing,
        ];
    }

    /**
     * Menguji koneksi database secara terisolasi.
     * Mengembalikan ['ok', 'ms', 'provider', 'model', 'pesan'] dengan pesan error yang disanitasi.
     */
    public function testDatabase(): array
    {
        $all = $this->settings->getAll();
        $provider = (string)($all['effective_provider'] ?? 'mock');
        $model = (string)($all['ai_model'] ?? 'gemini-3.5-flash-lite');

        if (!$this->db) {
            return [
                'ok'       => false,
                'ms'       => 0.0,
                'provider' => $provider,
                'model'    => $model,
                'pesan'    => 'Koneksi database tidak tersedia.'
            ];
        }

        $start = microtime(true);
        try {
            $stmt = $this->db->query("SELECT 1");
            $ok = ($stmt && $stmt->fetchColumn() == 1);
            $elapsed = round((microtime(true) - $start) * 1000, 2);

            return [
                'ok'       => $ok,
                'ms'       => $elapsed,
                'provider' => $provider,
                'model'    => $model,
                'pesan'    => $ok ? "Koneksi database OK ({$elapsed} ms)." : "Query database gagal diverifikasi."
            ];
        } catch (Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            return [
                'ok'       => false,
                'ms'       => $elapsed,
                'provider' => $provider,
                'model'    => $model,
                'pesan'    => $this->sanitizeErrorMessage('Database error: ' . $e->getMessage())
            ];
        }
    }

    /**
     * Menguji koneksi provider AI dengan batasan maksimal 10 kali per jam per user.
     * Batasan dihitung dari tabel ai_settings_audit (setting_key = 'tes_koneksi').
     * Mengembalikan ['ok', 'ms', 'provider', 'model', 'pesan'] dengan pesan error yang disanitasi.
     */
    public function testProvider(
        ?int $userId = null,
        ?string $customProvider = null,
        ?string $customModel = null,
        ?string $customKey = null,
        ?string $customBaseUrl = null
    ): array
    {
        $all = $this->settings->getAll();
        $provider = $customProvider !== null ? strtolower(trim($customProvider)) : (string)($all['effective_provider'] ?? 'mock');
        $model = $customModel !== null && trim($customModel) !== '' ? trim($customModel) : (string)($all['ai_model'] ?? 'gemini-2.5-flash');
        $effectiveUserId = $userId ?? (Auth::check() ? Auth::id() : null);

        // 1. Cek batas kuota tes koneksi: maksimal 10 kali per jam per pengguna
        $testCount1h = 0;
        if ($this->db && $effectiveUserId !== null) {
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM ai_settings_audit 
                    WHERE setting_key = 'tes_koneksi' 
                      AND user_id = :uid 
                      AND created_at >= NOW() - INTERVAL 1 HOUR");
                $stmt->execute(['uid' => $effectiveUserId]);
                $testCount1h = (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                $testCount1h = 0;
            }
        }

        if ($testCount1h >= 10) {
            return [
                'ok'       => false,
                'ms'       => 0.0,
                'provider' => $provider,
                'model'    => $model,
                'pesan'    => 'Batas uji coba koneksi tercapai (maksimal 10 kali per jam per pengguna).'
            ];
        }

        // 2. Jalankan tes koneksi ke provider
        $start = microtime(true);
        $ok = false;
        $pesan = '';

        if ($provider === 'mock') {
            $mockClient = new MockAiClient();
            $res = $mockClient->generateResponse("Sistem", [], "ping");
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $ok = !empty($res['success']);
            $pesan = $ok 
                ? "Koneksi Mock Provider OK ({$elapsed} ms)." 
                : "Pengujian Mock Provider gagal.";
        } elseif ($provider === 'gemini') {
            // Provider Gemini
            $apiKey = $customKey !== null && trim($customKey) !== '' ? trim($customKey) : $this->settings->getDecryptedApiKey();
            if (empty($apiKey)) {
                $elapsed = round((microtime(true) - $start) * 1000, 2);
                $ok = false;
                $pesan = "API key untuk Google Gemini belum dikonfigurasi.";
            } else {
                try {
                    $client = new GeminiAiClient($apiKey, $model, !empty($customBaseUrl) ? $customBaseUrl : null, 10);
                    $res = $client->generateResponse("Jawab satu kata: AKTIF", [], "Ping kesehatan");
                    $elapsed = round((microtime(true) - $start) * 1000, 2);
                    $ok = !empty($res['success']);
                    $pesan = $ok 
                        ? "Handshake Google Gemini ({$model}) berhasil dalam {$elapsed} ms." 
                        : "Handshake Gemini gagal: " . $this->sanitizeErrorMessage((string)($res['error'] ?? 'Unknown error'));
                } catch (Throwable $e) {
                    $elapsed = round((microtime(true) - $start) * 1000, 2);
                    $ok = false;
                    $pesan = "Koneksi Gemini gagal: " . $this->sanitizeErrorMessage($e->getMessage());
                }
            }
        } elseif (in_array($provider, ['groqcloud', 'openai', 'openai_compatible'], true)) {
            $apiKey = $customKey !== null && trim($customKey) !== '' ? trim($customKey) : $this->settings->getDecryptedApiKey();
            $providerLabel = match($provider) {
                'groqcloud'         => 'GroqCloud',
                'openai'            => 'OpenAI',
                'openai_compatible' => 'OpenAI Compatible API',
                default             => ucfirst($provider)
            };
            if (empty($apiKey)) {
                $elapsed = round((microtime(true) - $start) * 1000, 2);
                $ok = false;
                $pesan = "API key untuk {$providerLabel} belum dikonfigurasi.";
            } else {
                try {
                    $baseUrl = !empty($customBaseUrl) ? $customBaseUrl : match($provider) {
                        'groqcloud' => 'https://api.groq.com/openai/v1',
                        'openai'    => 'https://api.openai.com/v1',
                        default     => 'https://api.groq.com/openai/v1',
                    };
                    $client = new OpenAiCompatibleClient($apiKey, $model, $baseUrl, 10);
                    $res = $client->generateResponse("Jawab satu kata: AKTIF", [], "Ping test");
                    $elapsed = round((microtime(true) - $start) * 1000, 2);
                    $ok = !empty($res['success']);
                    $pesan = $ok 
                        ? "Handshake {$providerLabel} ({$model}) berhasil dalam {$elapsed} ms." 
                        : "Handshake {$providerLabel} gagal: " . $this->sanitizeErrorMessage((string)($res['error'] ?? 'Unknown error'));
                } catch (Throwable $e) {
                    $elapsed = round((microtime(true) - $start) * 1000, 2);
                    $ok = false;
                    $pesan = "Koneksi {$providerLabel} gagal: " . $this->sanitizeErrorMessage($e->getMessage());
                }
            }
        } else {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $ok = false;
            $pesan = "Provider '{$provider}' tidak didukung.";
        }

        // 3. Catat riwayat tes koneksi ke ai_settings_audit (dan app.log)
        $role = Auth::check() ? (Auth::role() ?? 'system') : 'system';
        $this->settings->recordAudit(
            'tes_koneksi',
            null,
            $ok ? 'sukses' : 'gagal',
            $effectiveUserId,
            $role,
            null
        );

        return [
            'ok'       => $ok,
            'ms'       => $elapsed,
            'provider' => $provider,
            'model'    => $model,
            'pesan'    => $pesan
        ];
    }

    /**
     * Sanitasi pesan error agar TIDAK PERNAH memuat raw API key, token, atau URL berkunci.
     */
    public function sanitizeErrorMessage(string $msg): string
    {
        $sanitized = preg_replace('/(key|api_key|token|password|secret)=([^&"\'\s]+)/i', '$1=[DISEMBUNYIKAN]', $msg);
        $sanitized = preg_replace('/AIza[0-9A-Za-z-_]{35}/', '[DISEMBUNYIKAN]', $sanitized);
        return trim((string)$sanitized);
    }

    /**
     * Membaca dan mensanitasi error AI terakhir dari file app.log.
     */
    private function getLastAiErrorFromLog(): ?string
    {
        $logFile = Logger::getLogPath();
        if (!file_exists($logFile) || !is_readable($logFile)) {
            return null;
        }

        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines || empty($lines)) {
            return null;
        }

        // Cari baris dari yang paling akhir
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            if (stripos($line, 'AI') !== false && (stripos($line, 'ERROR') !== false || stripos($line, 'WARNING') !== false)) {
                return $this->sanitizeErrorMessage($line);
            }
        }

        return null;
    }
}
