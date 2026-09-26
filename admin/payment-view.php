<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireAdministrator();

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$paymentId || $paymentId < 1) {
    http_response_code(404);
    exit('Payment not found.');
}

$statement = $pdo->prepare(
    'SELECT p.*, u.employee_id, u.first_name, u.last_name, u.email,
            wb.bill_number, wb.billing_period, r.receipt_number
     FROM payments p
     JOIN users u ON u.id = p.faculty_id
     JOIN water_bills wb ON wb.id = p.bill_id
     LEFT JOIN receipts r ON r.payment_id = p.id
     WHERE p.id = :id LIMIT 1'
);
$statement->execute(['id' => $paymentId]);
$payment = $statement->fetch();
if (!$payment) {
    http_response_code(404);
    exit('Payment not found.');
}

$eventsStatement = $pdo->prepare(
    'SELECT gateway_event_id, event_type, processing_status, received_at, processed_at, error_message
     FROM payment_events WHERE payment_id = :payment_id ORDER BY received_at DESC'
);
$eventsStatement->execute(['payment_id' => $paymentId]);
$events = $eventsStatement->fetchAll();

$pageTitle = 'Payment Details';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading"><div><p class="eyebrow">Transaction details</p><h1><?= e($payment['payment_reference']) ?></h1></div></div>
    <dl class="detail-grid">
        <div><dt>Faculty</dt><dd><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?> (<?= e($payment['employee_id']) ?>)</dd></div>
        <div><dt>Bill Number</dt><dd><?= e($payment['bill_number']) ?></dd></div>
        <div><dt>Billing Period</dt><dd><?= e($payment['billing_period']) ?></dd></div>
        <div><dt>Amount</dt><dd><?= e(formatPhpAmount((string) $payment['amount'])) ?></dd></div>
        <div><dt>Payment Method</dt><dd><?= e($payment['payment_method'] ?? 'Not available') ?></dd></div>
        <div><dt>Gateway</dt><dd><?= e(ucfirst($payment['gateway'])) ?></dd></div>
        <div><dt>Checkout ID</dt><dd><?= e($payment['gateway_checkout_id'] ?? '—') ?></dd></div>
        <div><dt>Gateway Payment ID</dt><dd><?= e($payment['gateway_payment_id'] ?? '—') ?></dd></div>
        <div><dt>Gateway Transaction ID</dt><dd><?= e($payment['gateway_transaction_id'] ?? '—') ?></dd></div>
        <div><dt>Gateway Status</dt><dd><?= e($payment['gateway_status'] ?? '—') ?></dd></div>
        <div><dt>Status</dt><dd><?= e(ucfirst($payment['status'])) ?></dd></div>
        <div><dt>Verification</dt><dd><?= e(ucwords(str_replace('_', ' ', $payment['verification_status']))) ?></dd></div>
        <div><dt>Created</dt><dd><?= e($payment['created_at']) ?></dd></div>
        <div><dt>Paid</dt><dd><?= e($payment['paid_at'] ?? 'Not confirmed') ?></dd></div>
    </dl>
    <?php if ($payment['receipt_number']): ?><p><a class="button small" href="payment-receipt.php?id=<?= (int) $paymentId ?>">View Receipt</a></p><?php endif; ?>
</section>

<section class="card admin-panel">
    <h2>Provider Events</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Event</th><th>Type</th><th>Processing</th><th>Received</th><th>Processed</th><th>Error</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?><tr><td><?= e($event['gateway_event_id']) ?></td><td><?= e($event['event_type']) ?></td><td><?= e(ucfirst($event['processing_status'])) ?></td><td><?= e($event['received_at']) ?></td><td><?= e($event['processed_at'] ?? '—') ?></td><td><?= e($event['error_message'] ?? '—') ?></td></tr><?php endforeach; ?>
            <?php if ($events === []): ?><tr><td colspan="6">No provider events recorded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
