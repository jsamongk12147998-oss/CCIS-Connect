<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireFaculty();

$pageTitle = 'Payments';
$userId = (int) $_SESSION['user_id'];
$statement = $pdo->prepare(
    'SELECT p.id, p.payment_reference, p.amount, p.payment_method, p.gateway, p.status,
            p.created_at, p.paid_at, wb.id AS bill_id, wb.bill_number, wb.billing_period, r.receipt_number
     FROM payments p
     JOIN water_bills wb ON wb.id = p.bill_id
     LEFT JOIN receipts r ON r.payment_id = p.id
     WHERE p.faculty_id = :faculty_id
     ORDER BY p.created_at DESC
     LIMIT 100'
);
$statement->execute(['faculty_id' => $userId]);
$payments = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading">
        <div><p class="eyebrow">Your transactions</p><h1>Payments</h1></div>
        <a class="button small secondary-button" href="water-bill.php">My Water Bills</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Bill</th><th>Billing Period</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['paid_at'] ?? $payment['created_at']) ?></td>
                    <td><a href="water-bill.php?id=<?= (int) $payment['bill_id'] ?>"><?= e($payment['bill_number']) ?></a></td>
                    <td><?= e($payment['billing_period']) ?></td>
                    <td><?= e(formatPhpAmount((string) $payment['amount'])) ?></td>
                    <td><?= e($payment['payment_method'] ?? 'Pending') ?></td>
                    <td><a href="payment-view.php?id=<?= (int) $payment['id'] ?>"><?= e($payment['payment_reference']) ?></a></td>
                    <td><?= e(ucfirst($payment['status'])) ?></td>
                    <td><?php if ($payment['receipt_number']): ?><a href="receipt.php?id=<?= (int) $payment['id'] ?>">View Receipt</a><?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($payments === []): ?><tr><td colspan="8">No payment transactions yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
