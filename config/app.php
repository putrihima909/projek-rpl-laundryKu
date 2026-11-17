<?php

use App\Helpers\Env;

return [
    'name' => Env::get('APP_NAME', 'LaundryKu'),
    'env' => Env::get('APP_ENV', 'development'),
    'debug' => filter_var(Env::get('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'url' => Env::get('APP_URL', 'http://localhost/rpl/public'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
];
