<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../config/payment.php';

requireFaculty();

$pageTitle = 'My Water Bill';
$userId = (int) $_SESSION['user_id'];
$billId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($billId && $billId > 0) {
    $statement = $pdo->prepare(
        billBalancesQuery() . ' WHERE wb.faculty_id = :faculty_id AND wb.id = :bill_id LIMIT 1'
    );
    $statement->execute(['faculty_id' => $userId, 'bill_id' => $billId]);
    $bill = $statement->fetch();
    if (!$bill) {
        http_response_code(404);
        exit('Water bill not found.');
    }

    $status = billStatus($bill);
    $dueCents = parseNonNegativePhpAmount((string) $bill['amount_due']);
    $paidCents = min($dueCents, parseNonNegativePhpAmount((string) $bill['amount_paid']));
    $balanceCents = max(0, $dueCents - $paidCents);
    $paymentStatement = $pdo->prepare(
        'SELECT id, payment_reference, status, created_at
         FROM payments
         WHERE bill_id = :bill_id AND faculty_id = :faculty_id
           AND status IN ("pending", "processing")
         ORDER BY created_at DESC LIMIT 1'
    );
    $paymentStatement->execute(['bill_id' => $billId, 'faculty_id' => $userId]);
    $pendingPayment = $paymentStatement->fetch();
} else {
    $statement = $pdo->prepare(
        billBalancesQuery() . ' WHERE wb.faculty_id = :faculty_id ORDER BY wb.due_date DESC, wb.id DESC'
    );
    $statement->execute(['faculty_id' => $userId]);
    $bills = $statement->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Internal office drinking-water contributions</p>
            <h1><?= isset($bill) ? e($bill['bill_number']) : 'My Water Bills' ?></h1>
        </div>
        <a class="button small secondary-button" href="payment-history.php">Payment History</a>
    </div>

    <?php if (isset($bill)): ?>
        <dl class="detail-grid">
            <div><dt>Billing Period</dt><dd><?= e($bill['billing_period']) ?></dd></div>
            <div><dt>Description</dt><dd><?= e($bill['description'] ?? 'Office drinking-water contribution') ?></dd></div>
            <div><dt>Bill Amount</dt><dd><?= e(formatPhpAmount((string) $bill['amount_due'])) ?></dd></div>
            <div><dt>Amount Paid</dt><dd><?= e(formatPhpCents($paidCents)) ?></dd></div>
            <div><dt>Outstanding Balance</dt><dd><?= e(formatPhpCents($balanceCents)) ?></dd></div>
            <div><dt>Due Date</dt><dd><?= e($bill['due_date']) ?></dd></div>
            <div><dt>Payment Status</dt><dd><?= e(ucwords(str_replace('_', ' ', $status))) ?></dd></div>
        </dl>
        <?php if ($pendingPayment): ?>
            <div class="alert success" role="status">
                A payment is <?= e($pendingPayment['status']) ?>. We will update this bill after the provider confirms the transaction.
                <a href="payment-view.php?id=<?= (int) $pendingPayment['id'] ?>">View transaction</a>
            </div>
        <?php elseif ($balanceCents > 0 && $status !== 'cancelled'): ?>
            <form method="POST" action="../api/payments/create-checkout.php">
                <?= csrfField() ?>
                <input type="hidden" name="bill_id" value="<?= (int) $billId ?>">
                <button class="button" type="submit" <?= paymentGatewayConfigured() ? '' : 'disabled' ?>>
                    <?= paymentGatewayConfigured() ? 'Pay Now · ' . e(formatPhpCents($balanceCents)) : 'Online Payment Unavailable' ?>
                </button>
            </form>
            <?php if (!paymentGatewayConfigured()): ?>
                <p class="admin-empty">Online payment is unavailable until PayMongo credentials are configured.</p>
            <?php endif; ?>
        <?php endif; ?>
        <p class="form-link"><a href="water-bill.php">Back to all bills</a></p>
    <?php elseif ($bills === []): ?>
        <p>No water bills have been issued to your account.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Bill</th><th>Billing Period</th><th>Amount Due</th><th>Amount Paid</th><th>Outstanding</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bills as $bill): ?>
                    <?php
                    $status = billStatus($bill);
                    $dueCents = parseNonNegativePhpAmount((string) $bill['amount_due']);
                    $paidCents = min($dueCents, parseNonNegativePhpAmount((string) $bill['amount_paid']));
                    $balanceCents = max(0, $dueCents - $paidCents);
                    ?>
                    <tr>
                        <td><?= e($bill['bill_number']) ?></td>
                        <td><?= e($bill['billing_period']) ?></td>
                        <td><?= e(formatPhpAmount((string) $bill['amount_due'])) ?></td>
                        <td><?= e(formatPhpCents($paidCents)) ?></td>
                        <td><?= e(formatPhpCents($balanceCents)) ?></td>
                        <td><?= e($bill['due_date']) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $status))) ?></td>
                        <td><a href="water-bill.php?id=<?= (int) $bill['id'] ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
