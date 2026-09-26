<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireFaculty();

$reference = trim((string) ($_GET['reference'] ?? ''));
if ($reference === '') {
    http_response_code(400);
    exit('Payment reference is required.');
}

$statement = $pdo->prepare(
    'SELECT p.id, p.payment_reference, p.amount, p.currency, p.status, p.created_at, p.paid_at,
            wb.bill_number, wb.billing_period
     FROM payments p
     JOIN water_bills wb ON wb.id = p.bill_id
     WHERE p.payment_reference = :reference AND p.faculty_id = :faculty_id
     LIMIT 1'
);
$statement->execute(['reference' => $reference, 'faculty_id' => (int) $_SESSION['user_id']]);
$payment = $statement->fetch();
if (!$payment) {
    http_response_code(404);
    exit('Payment transaction not found.');
}

$pageTitle = 'Payment Submitted';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card auth-card">
    <p class="eyebrow">Payment submitted</p>
    <h1>We are confirming your transaction</h1>
    <p>Returning from the payment provider is not proof of payment. We will update the status after trusted server-side confirmation.</p>
    <dl class="detail-grid">
        <div><dt>Reference</dt><dd><?= e($payment['payment_reference']) ?></dd></div>
        <div><dt>Bill</dt><dd><?= e($payment['bill_number']) ?> · <?= e($payment['billing_period']) ?></dd></div>
        <div><dt>Amount</dt><dd><?= e(formatPhpAmount((string) $payment['amount'])) ?></dd></div>
        <div><dt>Status</dt><dd><?= e(strtoupper($payment['status'])) ?></dd></div>
    </dl>
    <p><a class="button small" href="payment-view.php?id=<?= (int) $payment['id'] ?>">View Transaction</a></p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
