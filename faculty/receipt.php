<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireFaculty();

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$paymentId || $paymentId < 1) {
    http_response_code(404);
    exit('Receipt not found.');
}

$statement = $pdo->prepare(
    'SELECT r.receipt_number, r.issued_at, r.amount AS receipt_amount,
            p.payment_reference, p.amount, p.currency, p.payment_method, p.gateway,
            p.gateway_transaction_id, p.status, p.paid_at,
            u.employee_id, u.first_name, u.last_name,
            wb.bill_number, wb.billing_period
     FROM receipts r
     JOIN payments p ON p.id = r.payment_id
     JOIN users u ON u.id = r.faculty_id
     JOIN water_bills wb ON wb.id = r.bill_id
     WHERE r.payment_id = :payment_id
       AND r.faculty_id = :faculty_id
       AND p.status = "paid"
     LIMIT 1'
);
$statement->execute(['payment_id' => $paymentId, 'faculty_id' => (int) $_SESSION['user_id']]);
$receipt = $statement->fetch();
if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found.');
}

$pageTitle = 'Payment Receipt';
require_once __DIR__ . '/../includes/header.php';
?>

<article class="card receipt-card">
    <p class="eyebrow">CCIS Connect</p>
    <h1>OFFICE DRINKING-WATER CONTRIBUTION</h1>
    <p><strong>Status: PAID</strong></p>
    <dl class="detail-grid">
        <div><dt>Receipt Number</dt><dd><?= e($receipt['receipt_number']) ?></dd></div>
        <div><dt>Payment Reference</dt><dd><?= e($receipt['payment_reference']) ?></dd></div>
        <div><dt>Faculty</dt><dd><?= e($receipt['first_name'] . ' ' . $receipt['last_name']) ?></dd></div>
        <div><dt>Faculty ID</dt><dd><?= e($receipt['employee_id']) ?></dd></div>
        <div><dt>Bill Number</dt><dd><?= e($receipt['bill_number']) ?></dd></div>
        <div><dt>Billing Period</dt><dd><?= e($receipt['billing_period']) ?></dd></div>
        <div><dt>Amount</dt><dd><?= e(formatPhpAmount((string) $receipt['amount'])) ?></dd></div>
        <div><dt>Payment Date</dt><dd><?= e($receipt['paid_at'] ?? $receipt['issued_at']) ?></dd></div>
        <div><dt>Payment Method</dt><dd><?= e($receipt['payment_method'] ?? 'Not available') ?></dd></div>
        <div><dt>Gateway</dt><dd><?= e(ucfirst($receipt['gateway'] ?? 'Manual')) ?></dd></div>
        <div><dt>Gateway Transaction ID</dt><dd><?= e($receipt['gateway_transaction_id'] ?? '—') ?></dd></div>
    </dl>
    <div class="button-row no-print">
        <button class="button small" type="button" onclick="window.print()">Print Receipt</button>
        <a class="button small secondary-button" href="payment-history.php">Back to Payment History</a>
    </div>
</article>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
