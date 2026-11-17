<?php

/**
 * Script untuk menghasilkan APP_KEY acak sepanjang 64 karakter.
 * Salin dan tempelkan nilai ini ke variabel APP_KEY di file .env Anda.
 */

$appKey = bin2hex(random_bytes(32)); // 32 bytes = 64 hex characters
echo $appKey . PHP_EOL;

