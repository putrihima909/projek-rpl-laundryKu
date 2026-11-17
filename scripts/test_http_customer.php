<?php
$baseUrl = 'http://localhost/rpl/public';

// 1. GET /login
$ch = curl_init("$baseUrl/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);

preg_match('/Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $res, $cookieMatch);
$sessionId = $cookieMatch[1] ?? '';

preg_match('/name="_csrf_token"\s+value="([^"]+)"/i', $res, $csrfMatch);
$csrfToken = $csrfMatch[1] ?? '';

// 2. POST /login
$loginData = [
    'email' => 'customer@laundry.test',
    'password' => 'password123',
    '_csrf_token' => $csrfToken
];

curl_setopt($ch, CURLOPT_URL, "$baseUrl/login");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: $sessionId"
]);
$loginRes = curl_exec($ch);

if (preg_match_all('/Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $loginRes, $allCookies)) {
    $sessionId = end($allCookies[1]);
}

// 3. POST /api/ai/chat
echo "--- POST /api/ai/chat as customer@laundry.test ---\n";
curl_setopt($ch, CURLOPT_URL, "$baseUrl/api/ai/chat");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'message' => 'siapa saya'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json',
    "Cookie: $sessionId"
]);
$chatRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($chatRes, 0, $headerSize);
$body = substr($chatRes, $headerSize);

echo "HTTP Status Code: $httpCode\n";
echo "Headers:\n$headers\n";
echo "Raw Response Body:\n$body\n";

curl_close($ch);

