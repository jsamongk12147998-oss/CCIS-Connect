<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/PaymentGatewayInterface.php';

final class PayMongoGateway implements PaymentGatewayInterface
{
    public function createCheckout(array $payload): array
    {
        return createGatewayPayment($payload);
    }
}
