<?php

use App\Helpers\Env;

return [
    'client_id' => Env::get('GOOGLE_CLIENT_ID', ''),
    'client_secret' => Env::get('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri' => Env::get('GOOGLE_REDIRECT_URI', 'http://localhost/rpl/public/auth/google/callback'),
];
