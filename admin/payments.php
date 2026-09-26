<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireAdministrator();

$pageTitle = 'Payments';
$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$allowedStatuses = ['pending', 'processing', 'paid', 'failed', 'rejected', 'cancelled', 'refunded'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.payment_reference LIKE :reference OR u.employee_id LIKE :employee OR u.first_name LIKE :first_name OR u.last_name LIKE :last_name OR wb.bill_number LIKE :bill)';
    $like = '%' . $search . '%';
    $params += ['reference' => $like, 'employee' => $like, 'first_name' => $like, 'last_name' => $like, 'bill' => $like];
}
if ($status !== '') {
    $where[] = 'p.status = :status';
    $params['status'] = $status;
}

$query = $pdo->prepare(
    'SELECT p.id, p.payment_reference, p.amount, p.payment_method, p.gateway,
            p.gateway_transaction_id, p.status, p.verification_status, p.created_at, p.paid_at,
            u.employee_id, u.first_name, u.last_name,
            wb.id AS bill_id, wb.bill_number, wb.billing_period, r.receipt_number
     FROM payments p
     JOIN users u ON u.id = p.faculty_id
     JOIN water_bills wb ON wb.id = p.bill_id
     LEFT JOIN receipts r ON r.payment_id = p.id
     ' . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . '
     ORDER BY p.created_at DESC LIMIT 200'
);
$query->execute($params);
$payments = $query->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading">
        <div><p class="eyebrow">Payment activity</p><h1>Payments</h1></div>
    </div>
    <form method="GET" action="payments.php" class="filter-form">
        <label for="q">Search</label>
        <input id="q" name="q" value="<?= e($search) ?>" placeholder="Reference, bill, or faculty">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="button small">Filter</button>
    </form>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Faculty</th><th>Bill</th><th>Amount</th><th>Method</th><th>Reference</th><th>Gateway transaction</th><th>Status</th><th>Verification</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['paid_at'] ?? $payment['created_at']) ?></td>
                    <td><?= e($payment['first_name'] . ' ' . $payment['last_name'] . ' (' . $payment['employee_id'] . ')') ?></td>
                    <td><a href="bill-view.php?id=<?= (int) $payment['bill_id'] ?>"><?= e($payment['bill_number']) ?></a></td>
                    <td><?= e(formatPhpAmount((string) $payment['amount'])) ?></td>
                    <td><?= e($payment['payment_method'] ?? 'Pending') ?></td>
                    <td><a href="payment-view.php?id=<?= (int) $payment['id'] ?>"><?= e($payment['payment_reference']) ?></a></td>
                    <td><?= e($payment['gateway_transaction_id'] ?? '—') ?></td>
                    <td><?= e(ucfirst($payment['status'])) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', $payment['verification_status']))) ?></td>
                    <td><?php if ($payment['receipt_number']): ?><a href="payment-receipt.php?id=<?= (int) $payment['id'] ?>">View</a><?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($payments === []): ?><tr><td colspan="10">No payment transactions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
