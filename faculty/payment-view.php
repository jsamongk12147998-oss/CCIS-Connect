<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireFaculty();

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$paymentId || $paymentId < 1) {
    http_response_code(404);
    exit('Payment not found.');
}

$statement = $pdo->prepare(
    'SELECT p.*, wb.bill_number, wb.billing_period, r.receipt_number
     FROM payments p
     JOIN water_bills wb ON wb.id = p.bill_id
     LEFT JOIN receipts r ON r.payment_id = p.id
     WHERE p.id = :id AND p.faculty_id = :faculty_id
     LIMIT 1'
);
$statement->execute(['id' => $paymentId, 'faculty_id' => (int) $_SESSION['user_id']]);
$payment = $statement->fetch();
if (!$payment) {
    http_response_code(404);
    exit('Payment not found.');
}

$pageTitle = 'Payment Details';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card admin-panel">
    <p class="eyebrow">Transaction details</p>
    <h1><?= e($payment['payment_reference']) ?></h1>
    <dl class="detail-grid">
        <div><dt>Bill Number</dt><dd><?= e($payment['bill_number']) ?></dd></div>
        <div><dt>Billing Period</dt><dd><?= e($payment['billing_period']) ?></dd></div>
        <div><dt>Amount</dt><dd><?= e(formatPhpAmount((string) $payment['amount'])) ?></dd></div>
        <div><dt>Method</dt><dd><?= e($payment['payment_method'] ?? 'Pending') ?></dd></div>
        <div><dt>Gateway</dt><dd><?= e(ucfirst($payment['gateway'])) ?></dd></div>
        <div><dt>Status</dt><dd><?= e(ucfirst($payment['status'])) ?></dd></div>
        <div><dt>Created</dt><dd><?= e($payment['created_at']) ?></dd></div>
        <div><dt>Paid</dt><dd><?= e($payment['paid_at'] ?? 'Awaiting confirmation') ?></dd></div>
    </dl>
    <p>Payment status is updated only after server-side provider confirmation.</p>
    <?php if ($payment['receipt_number']): ?><a class="button small" href="receipt.php?id=<?= (int) $paymentId ?>">View Receipt</a><?php endif; ?>
    <a class="button small secondary-button" href="payment-history.php">Back to Payment History</a>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
