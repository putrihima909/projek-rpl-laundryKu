<?php

namespace App\Models;

use App\Core\Database;
use App\Services\Ai\AiSettings;
use PDO;
use Throwable;

class AiSetting
{
    private ?PDO $db;
    private AiSettings $aiSettings;

    public const ALLOWED_MODELS = [
        'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash Lite (Cepat & Ringan - Default)',
        'gemini-2.5-flash'      => 'Gemini 2.5 Flash (Standar Seimbang)',
        'gemini-2.5-pro'        => 'Gemini 2.5 Pro (Kemampuan Penalaran Lanjutan)',
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
        $this->aiSettings = new AiSettings($this->db);
    }

    /**
     * Ambil pengaturan AI saat ini via AiSettings.
     */
    public function getSettings(): array
    {
        $all = $this->aiSettings->getAll();

        return [
            'is_enabled'              => $all['ai_enabled'] ? 1 : 0,
            'provider'                => $all['effective_provider'] ?? 'mock',
            'model'                   => $all['ai_model'] ?? 'gemini-3.5-flash-lite',
            'rate_limit_per_hour'     => (int) ($all['ai_rate_limit_per_hour'] ?? 20),
            'temperature'             => (float) ($all['ai_temperature'] ?? 0.7),
            'data_max_rows'           => (int) ($all['ai_data_max_rows'] ?? 15),
            'additional_instructions' => '',
            'updated_at'              => null,
        ];
    }

    /**
     * Simpan pembaruan pengaturan AI via AiSettings.
     */
    public function updateSettings(array $data): bool
    {
        try {
            if (isset($data['is_enabled'])) {
                $this->aiSettings->set('ai_enabled', (bool)$data['is_enabled']);
            }
            if (isset($data['model'])) {
                $this->aiSettings->set('ai_model', (string)$data['model']);
            }
            if (isset($data['provider'])) {
                $this->aiSettings->set('ai_provider', (string)$data['provider']);
            }
            if (isset($data['rate_limit_per_hour'])) {
                $this->aiSettings->set('ai_rate_limit_per_hour', (int)$data['rate_limit_per_hour']);
            }
            if (isset($data['temperature'])) {
                $this->aiSettings->set('ai_temperature', (float)$data['temperature']);
            }
            if (isset($data['data_max_rows'])) {
                $this->aiSettings->set('ai_data_max_rows', (int)$data['data_max_rows']);
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
