<?php

declare(strict_types=1);

function googleConfig(): array
{
    return [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: '',
    ];
}

function isGoogleConfigured(): bool
{
    $config = googleConfig();
    return $config['client_id'] !== ''
        && $config['client_secret'] !== ''
        && filter_var($config['redirect_uri'], FILTER_VALIDATE_URL) !== false;
}

function googleLoginUrl(): string
{
    if (!isGoogleConfigured()) {
        throw new RuntimeException('Google OAuth is not configured.');
    }

    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id' => googleConfig()['client_id'],
        'redirect_uri' => googleConfig()['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'state' => $state,
        'prompt' => 'select_account',
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function googleRequest(string $url, array $fields = [], ?string $accessToken = null): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the Google OAuth request.');
    }

    $headers = ['Accept: application/json'];
    if ($accessToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($fields !== []) {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
    }

    $responseBody = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($responseBody === false) {
        throw new RuntimeException('Google OAuth request failed: ' . $curlError);
    }

    $response = json_decode($responseBody, true);
    if (!is_array($response)) {
        throw new RuntimeException('Google OAuth returned an invalid response.');
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Google OAuth returned HTTP ' . $statusCode . '.');
    }

    return $response;
}
