<?php

use App\Helpers\Env;

return [
    'default' => Env::get('PAYMENT_GATEWAY', 'mock_gateway'),
    'expiry_hours' => 1, // 1 hour expiration as required
    'mock' => [
        'secret_key' => Env::get('PAYMENT_MOCK_SECRET', 'mock_secret_key_laundry_2026_xyz'),
        'merchant_name' => 'LaundryKu',
        'merchant_city' => 'Jakarta Selatan',
        'merchant_id' => 'ID1020304050607',
    ],
    'midtrans' => [
        'server_key' => Env::get('MIDTRANS_SERVER_KEY', ''),
        'client_key' => Env::get('MIDTRANS_CLIENT_KEY', ''),
        'is_production' => filter_var(Env::get('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'xendit' => [
        'secret_key' => Env::get('XENDIT_SECRET_KEY', ''),
        'callback_token' => Env::get('XENDIT_CALLBACK_TOKEN', ''),
    ],
];
