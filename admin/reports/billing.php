<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/billing.php';

requireAdministrator();

$pageTitle = 'Billing Report';
$facultyId = filter_input(INPUT_GET, 'faculty_id', FILTER_VALIDATE_INT);
$status = (string) ($_GET['status'] ?? '');
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$allowedStatuses = ['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}
$faculty = $pdo->query(
    "SELECT id, employee_id, first_name, last_name FROM users
     WHERE role = 'faculty' ORDER BY last_name, first_name"
)->fetchAll();

$where = [];
$params = [];
if ($facultyId && $facultyId > 0) {
    $where[] = 'wb.faculty_id = :faculty_id';
    $params['faculty_id'] = $facultyId;
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
    $where[] = $key === 'date_from' ? 'wb.due_date >= :' . $key : 'wb.due_date <= :' . $key;
    $params[$key] = $date;
}

$query = $pdo->prepare(
    'SELECT wb.id, wb.bill_number, wb.billing_period, wb.amount_due, wb.due_date,
            wb.status, u.employee_id, u.first_name, u.last_name,
            COALESCE(p.amount_paid, 0.00) AS amount_paid
     FROM water_bills wb
     JOIN users u ON u.id = wb.faculty_id
     LEFT JOIN (
         SELECT bill_id, SUM(amount) AS amount_paid FROM payments
         WHERE status = "paid" GROUP BY bill_id
     ) p ON p.bill_id = wb.id
     ' . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . '
     ORDER BY wb.due_date DESC, wb.id DESC LIMIT 500'
);
$query->execute($params);
$bills = $query->fetchAll();

$totals = [
    'total' => 0,
    'billed' => 0,
    'paid' => 0,
    'outstanding' => 0,
    'unpaid' => 0,
    'partially_paid' => 0,
    'paid_bills' => 0,
    'overdue' => 0,
    'pending' => 0,
    'failed' => 0,
];
foreach ($bills as $bill) {
    $billStatus = billStatus($bill);
    if ($status !== '' && $billStatus !== $status) {
        continue;
    }

    $amountDue = parseNonNegativePhpAmount((string) $bill['amount_due']);
    $amountPaid = min($amountDue, parseNonNegativePhpAmount((string) $bill['amount_paid']));
    $totals['total']++;
    $totals['billed'] += $amountDue;
    $totals['paid'] += $amountPaid;
    if ($billStatus !== 'cancelled') {
        $totals['outstanding'] += max(0, $amountDue - $amountPaid);
    }
    if (isset($totals[$billStatus])) {
        $totals[$billStatus]++;
    } elseif ($billStatus === 'paid') {
        $totals['paid_bills']++;
    }
}

$pageStatement = $pdo->query(
    'SELECT
        SUM(status IN ("pending", "processing")) AS pending_count,
        SUM(status IN ("failed", "rejected")) AS failed_count
     FROM payments'
);
$paymentCounts = $pageStatement->fetch() ?: [];
$totals['pending'] = (int) ($paymentCounts['pending_count'] ?? 0);
$totals['failed'] = (int) ($paymentCounts['failed_count'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading"><div><p class="eyebrow">Reconciliation</p><h1>Billing Report</h1></div><a class="button small secondary-button" href="payments.php">Payments Report</a></div>
    <form class="filter-form" method="GET" action="billing.php">
        <label for="faculty_id">Faculty</label>
        <select id="faculty_id" name="faculty_id"><option value="">All faculty</option><?php foreach ($faculty as $member): ?><option value="<?= (int) $member['id'] ?>" <?= $facultyId === (int) $member['id'] ? 'selected' : '' ?>><?= e($member['last_name'] . ', ' . $member['first_name'] . ' (' . $member['employee_id'] . ')') ?></option><?php endforeach; ?></select>
        <label for="status">Status</label>
        <select id="status" name="status"><option value="">All</option><?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?></select>
        <label for="date_from">Due from</label><input id="date_from" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        <label for="date_to">Due to</label><input id="date_to" type="date" name="date_to" value="<?= e($dateTo) ?>">
        <button class="button small" type="submit">Apply Filters</button>
    </form>
    <div class="admin-stats">
        <div class="card admin-stat"><span class="admin-stat-label">Bills</span><strong><?= $totals['total'] ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Total billed</span><strong><?= e(formatPhpCents($totals['billed'])) ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Total collected</span><strong><?= e(formatPhpCents($totals['paid'])) ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Outstanding</span><strong><?= e(formatPhpCents($totals['outstanding'])) ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Unpaid</span><strong><?= $totals['unpaid'] ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Partially paid</span><strong><?= $totals['partially_paid'] ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Paid</span><strong><?= $totals['paid_bills'] ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Overdue</span><strong><?= $totals['overdue'] ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Pending payments</span><strong><?= $totals['pending'] ?></strong></div>
        <div class="card admin-stat"><span class="admin-stat-label">Failed/rejected payments</span><strong><?= $totals['failed'] ?></strong></div>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Reference</th><th>Faculty</th><th>Bill</th><th>Period</th><th>Amount Due</th><th>Amount Paid</th><th>Balance</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($bills as $bill): ?>
                <?php $billStatus = billStatus($bill); $dueCents = parseNonNegativePhpAmount((string) $bill['amount_due']); $paidCents = min($dueCents, parseNonNegativePhpAmount((string) $bill['amount_paid'])); ?>
                <?php if ($status !== '' && $billStatus !== $status) { continue; } ?>
                <tr>
                    <td><?= e($bill['bill_number']) ?></td>
                    <td><?= e($bill['first_name'] . ' ' . $bill['last_name'] . ' (' . $bill['employee_id'] . ')') ?></td>
                    <td><a href="../bill-view.php?id=<?= (int) $bill['id'] ?>"><?= e($bill['bill_number']) ?></a></td>
                    <td><?= e($bill['billing_period']) ?></td>
                    <td><?= e(formatPhpAmount((string) $bill['amount_due'])) ?></td>
                    <td><?= e(formatPhpCents($paidCents)) ?></td>
                    <td><?= e(formatPhpCents($billStatus === 'cancelled' ? 0 : max(0, $dueCents - $paidCents))) ?></td>
                    <td><?= e($bill['due_date']) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', $billStatus))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
