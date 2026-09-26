<?php

declare(strict_types=1);

function payMongoSecretKey(): string
{
    return trim((string) (getenv('PAYMONGO_SECRET_KEY') ?: ''));
}

function paymentGatewayConfigured(): bool
{
    $secretKey = payMongoSecretKey();
    $environment = getenv('PAYMONGO_ENV') ?: 'test';

    return $secretKey !== ''
        && in_array($environment, ['test', 'production'], true)
        && str_starts_with($secretKey, $environment === 'test' ? 'sk_test_' : 'sk_live_');
}

function amountToCentavos(string $amount): int
{
    if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/', $amount, $matches)) {
        throw new InvalidArgumentException('Amount must be a positive decimal value with no more than two decimal places.');
    }

    $whole = (int) $matches[1];
    $fraction = str_pad($matches[2] ?? '', 2, '0');
    $centavos = ($whole * 100) + (int) $fraction;
    if ($centavos <= 0) {
        throw new InvalidArgumentException('Amount must be greater than zero.');
    }

    return $centavos;
}

function createGatewayPayment(array $payload): array
{
    if (!paymentGatewayConfigured()) {
        throw new RuntimeException('PayMongo is not configured with a matching test or production secret key.');
    }

    $amount = $payload['amount'] ?? null;
    $reference = trim((string) ($payload['reference'] ?? ''));
    $description = trim((string) ($payload['description'] ?? 'CCIS Connect water bill'));
    $successUrl = (string) ($payload['success_url'] ?? '');
    $cancelUrl = (string) ($payload['cancel_url'] ?? '');
    $paymentMethodTypes = $payload['payment_method_types'] ?? ['card', 'gcash'];

    if (!is_string($amount) || $reference === '' || $description === '') {
        throw new InvalidArgumentException('Payment amount, reference, and description are required.');
    }
    if (!is_array($paymentMethodTypes)
        || $paymentMethodTypes === []
        || array_filter($paymentMethodTypes, static fn ($method) => !is_string($method)) !== []
    ) {
        throw new InvalidArgumentException('At least one valid PayMongo payment method type is required.');
    }
    $environment = getenv('PAYMONGO_ENV') ?: 'test';
    foreach ([$successUrl, $cancelUrl] as $url) {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array($scheme, $environment === 'test' ? ['http', 'https'] : ['https'], true)
        ) {
            throw new InvalidArgumentException('PayMongo return URLs must use HTTPS in production and HTTP or HTTPS in test mode.');
        }
    }

    $attributes = [
        'line_items' => [[
            'currency' => 'PHP',
            'amount' => amountToCentavos($amount),
            'name' => mb_substr($description, 0, 100),
            'quantity' => 1,
        ]],
        'payment_method_types' => array_values(array_unique($paymentMethodTypes)),
        'reference_number' => mb_substr($reference, 0, 50),
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => array_merge(
            ['source' => 'CCIS Connect'],
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []
        ),
    ];

    $handle = curl_init('https://api.paymongo.com/v2/checkout_sessions');
    if ($handle === false) {
        throw new RuntimeException('Could not initialize a PayMongo checkout request.');
    }

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => payMongoSecretKey() . ':',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['data' => ['attributes' => $attributes]], JSON_THROW_ON_ERROR),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $responseBody = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($responseBody === false) {
        throw new RuntimeException('PayMongo checkout request failed: ' . $curlError);
    }

    $response = json_decode($responseBody, true);
    if (!is_array($response)) {
        throw new RuntimeException('PayMongo returned an invalid checkout response.');
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        $providerCode = $response['errors'][0]['code'] ?? 'provider_error';
        throw new RuntimeException('PayMongo checkout failed (HTTP ' . $statusCode . ', ' . $providerCode . ').');
    }

    $checkout = $response['data'] ?? [];
    $checkoutUrl = $checkout['attributes']['checkout_url'] ?? null;
    $checkoutId = $checkout['id'] ?? null;
    if (!is_string($checkoutUrl) || !is_string($checkoutId)) {
        throw new RuntimeException('PayMongo response did not include a checkout URL and session ID.');
    }

    return [
        'success' => true,
        'gateway' => 'paymongo',
        'transaction_id' => $checkoutId,
        'reference' => $reference,
        'redirect_url' => $checkoutUrl,
        'status' => 'pending',
    ];
}
