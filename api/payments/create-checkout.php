<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/billing.php';
require_once __DIR__ . '/../../services/PayMongoGateway.php';

requireFaculty();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use POST to start a payment.');
}

requireValidCsrfToken();

if (!paymentGatewayConfigured()) {
    http_response_code(503);
    exit('Online payments are not available because PayMongo is not configured.');
}

$billId = filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);
if (!$billId || $billId < 1) {
    http_response_code(400);
    exit('A valid water bill is required.');
}

$userId = (int) $_SESSION['user_id'];
$pdo->beginTransaction();

$billStatement = $pdo->prepare(
    'SELECT id, bill_number, billing_period, description, amount_due, due_date, status
     FROM water_bills
     WHERE id = :bill_id AND faculty_id = :faculty_id
     LIMIT 1 FOR UPDATE'
);
$billStatement->execute(['bill_id' => $billId, 'faculty_id' => $userId]);
$bill = $billStatement->fetch();
if (!$bill) {
    $pdo->rollBack();
    http_response_code(404);
    exit('Water bill not found.');
}
if ($bill['status'] === 'cancelled') {
    $pdo->rollBack();
    http_response_code(409);
    exit('This water bill has been cancelled.');
}

$paidStatement = $pdo->prepare(
    'SELECT COALESCE(SUM(amount), 0.00)
     FROM payments WHERE bill_id = :bill_id AND status = :status'
);
$paidStatement->execute(['bill_id' => $billId, 'status' => 'paid']);
$paidCents = parseNonNegativePhpAmount((string) $paidStatement->fetchColumn());
$dueCents = parseNonNegativePhpAmount((string) $bill['amount_due']);
$balanceCents = max(0, $dueCents - $paidCents);
if ($balanceCents === 0) {
    $pdo->rollBack();
    http_response_code(409);
    exit('This water bill has no outstanding balance.');
}

$pendingStatement = $pdo->prepare(
    'SELECT payment_reference
     FROM payments
     WHERE bill_id = :bill_id AND faculty_id = :faculty_id
       AND status IN ("pending", "processing")
     ORDER BY created_at DESC
     LIMIT 1'
);
$pendingStatement->execute(['bill_id' => $billId, 'faculty_id' => $userId]);
if ($pendingStatement->fetch()) {
    $pdo->rollBack();
    redirect('../../faculty/water-bill.php?id=' . $billId . '&payment_pending=1');
}

$methodStatement = $pdo->prepare(
    'SELECT id FROM payment_methods WHERE method_name = :method AND is_active = 1 LIMIT 1'
);
$methodStatement->execute(['method' => 'PayMongo']);
$paymentMethodId = $methodStatement->fetchColumn();
if (!$paymentMethodId) {
    $pdo->prepare(
        'INSERT INTO payment_methods (method_name, description, is_active)
         VALUES (:method, :description, 1)'
    )->execute([
        'method' => 'PayMongo',
        'description' => 'Online checkout via PayMongo.',
    ]);
    $paymentMethodId = (int) $pdo->lastInsertId();
}

$reference = 'CCIS-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
$amount = amountFromMinorUnits($balanceCents);
$pdo->prepare(
    'INSERT INTO payments
        (bill_id, user_id, payment_method_id, amount_paid, transaction_reference,
         payment_status, payment_date, payment_reference, faculty_id, amount,
         currency, gateway, payment_method, status, verification_status)
     VALUES
        (:bill_id, :user_id, :method_id, :legacy_amount, :reference,
         "pending", CURRENT_TIMESTAMP, :payment_reference, :faculty_id, :amount,
         "PHP", "paymongo", "PayMongo", "pending", "pending")'
)->execute([
    'bill_id' => $billId,
    'user_id' => $userId,
    'method_id' => $paymentMethodId,
    'legacy_amount' => $amount,
    'amount' => $amount,
    'reference' => $reference,
    'payment_reference' => $reference,
    'faculty_id' => $userId,
]);
$paymentId = (int) $pdo->lastInsertId();
$pdo->commit();

$baseUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
$parsedBaseUrl = filter_var($baseUrl, FILTER_VALIDATE_URL) ? parse_url($baseUrl) : false;
if ($parsedBaseUrl === false
    || !in_array($parsedBaseUrl['scheme'] ?? '', ['http', 'https'], true)
    || empty($parsedBaseUrl['host'])
    || isset($parsedBaseUrl['query'])
    || isset($parsedBaseUrl['fragment'])
) {
    $pdo->prepare(
        'UPDATE payments SET status = "failed", payment_status = "failed",
             verification_status = "failed", gateway_status = "invalid_app_url"
         WHERE id = :id AND status = "pending"'
    )->execute(['id' => $paymentId]);
    throw new RuntimeException('APP_URL must be a valid base HTTP or HTTPS URL.');
}

$successUrl = $baseUrl . '/faculty/payment-return.php?reference=' . rawurlencode($reference);
$cancelUrl = $baseUrl . '/faculty/payment-return.php?reference=' . rawurlencode($reference) . '&result=cancelled';
$gateway = new PayMongoGateway();

try {
    $checkout = $gateway->createCheckout([
        'amount' => $amount,
        'reference' => $reference,
        'description' => 'Water contribution ' . $bill['billing_period'],
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => [
            'payment_reference' => $reference,
            'bill_id' => (string) $billId,
        ],
    ]);

    $pdo->prepare(
        'UPDATE payments SET gateway_checkout_id = :checkout_id,
             gateway_checkout_url = :checkout_url, gateway_status = :gateway_status
         WHERE id = :id AND status = "pending"'
    )->execute([
        'checkout_id' => $checkout['transaction_id'],
        'checkout_url' => $checkout['redirect_url'],
        'gateway_status' => 'checkout_created',
        'id' => $paymentId,
    ]);
    redirect($checkout['redirect_url']);
} catch (RuntimeException $exception) {
    $uncertain = str_starts_with($exception->getMessage(), 'PayMongo checkout request failed:');
    $newStatus = $uncertain ? 'processing' : 'failed';
    $verificationStatus = $uncertain ? 'manual_review' : 'failed';
    $gatewayStatus = $uncertain ? 'checkout_request_uncertain' : 'checkout_creation_failed';

    $pdo->prepare(
        'UPDATE payments SET status = :status, payment_status = :legacy_status,
             verification_status = :verification_status, gateway_status = :gateway_status
         WHERE id = :id AND status = "pending"'
    )->execute([
        'status' => $newStatus,
        'legacy_status' => $uncertain ? 'processing' : 'failed',
        'verification_status' => $verificationStatus,
        'gateway_status' => $gatewayStatus,
        'id' => $paymentId,
    ]);
    error_log('PayMongo checkout creation failed for ' . $reference . ': ' . $exception->getMessage());

    if ($uncertain) {
        http_response_code(503);
        exit('The payment provider response was uncertain. This transaction is held for reconciliation; do not submit another payment. Contact an administrator.');
    }

    redirect('../../faculty/water-bill.php?id=' . $billId . '&payment_error=checkout_unavailable');
}
