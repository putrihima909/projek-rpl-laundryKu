<?php

use App\Helpers\Env;

return [
    'enabled' => filter_var(Env::get('WHATSAPP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'provider' => Env::get('WHATSAPP_PROVIDER', 'fonnte'),
    'max_retries' => (int) Env::get('WHATSAPP_MAX_RETRIES', 3),
    'timeout' => (int) Env::get('WHATSAPP_TIMEOUT', 30),

    'fonnte' => [
        'api_url' => Env::get('WHATSAPP_API_URL', 'https://api.fonnte.com/send'),
        'device_url' => 'https://api.fonnte.com/device',
        'token' => Env::get('WHATSAPP_API_TOKEN', ''),
        'timeout' => (int) Env::get('WHATSAPP_TIMEOUT', 30),
    ],

    'cloud_api' => [
        'api_url' => rtrim(Env::get('WHATSAPP_API_URL', 'https://graph.facebook.com/v19.0'), '/'),
        'phone_number_id' => Env::get('WHATSAPP_PHONE_NUMBER_ID', ''),
        'access_token' => Env::get('WHATSAPP_ACCESS_TOKEN', ''),
        'business_account_id' => Env::get('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
    ],

    'generic_gateway' => [
        'endpoint' => Env::get('WHATSAPP_GENERIC_ENDPOINT', ''),
        'api_key' => Env::get('WHATSAPP_GENERIC_API_KEY', ''),
    ],

    'mock' => [
        'log_messages' => true,
        'simulated_delay_ms' => 0,
    ],
];
