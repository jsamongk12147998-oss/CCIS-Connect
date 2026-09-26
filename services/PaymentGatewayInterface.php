<?php

declare(strict_types=1);

interface PaymentGatewayInterface
{
    public function createCheckout(array $payload): array;
}
