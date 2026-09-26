<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/billing.php';

requireAdministrator();

$pageTitle = 'Payment Report';
$status = (string) ($_GET['status'] ?? '');
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$allowedStatuses = ['pending', 'processing', 'paid', 'failed', 'rejected', 'cancelled', 'refunded'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}
$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'p.status = :status';
    $params['status'] = $status;
}
foreach (['date_from' => $dateFrom, 'date_to' => $dateTo] as $key => $date) {
    if ($date === '') {
        continue;
    }
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        http_response_code(400);
        exit('Invalid report date filter.');
    }
    $where[] = $key === 'date_from' ? 'p.created_at >= :' . $key : 'p.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
    $params[$key] = $date;
}
$query = $pdo->prepare(
    'SELECT p.id, p.payment_reference, p.amount, p.gateway, p.payment_method,
            p.gateway_transaction_id, p.created_at, p.paid_at, p.status,
            u.employee_id, u.first_name, u.last_name, wb.bill_number, wb.billing_period
     FROM payments p
     JOIN users u ON u.id = p.faculty_id
     JOIN water_bills wb ON wb.id = p.bill_id
     ' . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . '
     ORDER BY p.created_at DESC LIMIT 500'
);
$query->execute($params);
$payments = $query->fetchAll();

$totals = ['count' => count($payments), 'paid_cents' => 0];
foreach ($payments as $payment) {
    if ($payment['status'] === 'paid') {
        $totals['paid_cents'] += parseNonNegativePhpAmount((string) $payment['amount']);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading"><div><p class="eyebrow">Financial activity</p><h1>Payment Report</h1></div><a class="button small secondary-button" href="billing.php">Billing Report</a></div>
    <form class="filter-form" method="GET" action="payments.php">
        <label for="status">Status</label>
        <select id="status" name="status"><option value="">All statuses</option><?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select>
        <label for="date_from">From</label><input id="date_from" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        <label for="date_to">To</label><input id="date_to" type="date" name="date_to" value="<?= e($dateTo) ?>">
        <button class="button small" type="submit">Apply Filters</button>
    </form>
    <p><?= $totals['count'] ?> transactions · Confirmed collected total: <strong><?= e(formatPhpCents($totals['paid_cents'])) ?></strong></p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Reference</th><th>Faculty</th><th>Bill</th><th>Period</th><th>Amount</th><th>Gateway</th><th>Method</th><th>Gateway Transaction</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr><td><?= e($payment['paid_at'] ?? $payment['created_at']) ?></td><td><a href="../payment-view.php?id=<?= (int) $payment['id'] ?>"><?= e($payment['payment_reference']) ?></a></td><td><?= e($payment['first_name'] . ' ' . $payment['last_name'] . ' (' . $payment['employee_id'] . ')') ?></td><td><?= e($payment['bill_number']) ?></td><td><?= e($payment['billing_period']) ?></td><td><?= e(formatPhpAmount((string) $payment['amount'])) ?></td><td><?= e(ucfirst($payment['gateway'])) ?></td><td><?= e($payment['payment_method'] ?? '—') ?></td><td><?= e($payment['gateway_transaction_id'] ?? '—') ?></td><td><?= e(ucfirst($payment['status'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($payments === []): ?><tr><td colspan="10">No transactions match these filters.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
