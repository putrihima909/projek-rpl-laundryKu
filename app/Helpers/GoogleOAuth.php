<?php

namespace App\Helpers;

class GoogleOAuth
{
    public static function getAuthUrl(): string
    {
        $clientId = config('google.client_id', '');
        $redirectUri = config('google.redirect_uri', '');

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public static function getAccessToken(string $code): ?string
    {
        $clientId = config('google.client_id', '');
        $clientSecret = config('google.client_secret', '');
        $redirectUri = config('google.redirect_uri', '');

        $url = 'https://oauth2.googleapis.com/token';
        $params = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    public static function getUserInfo(string $accessToken): ?array
    {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        return json_decode($response, true);
    }
}
