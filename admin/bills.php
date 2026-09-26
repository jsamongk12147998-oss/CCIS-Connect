<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireAdministrator();

$pageTitle = 'Water Bills';
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$allowedStatuses = ['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = max(1, $page ?: 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$where = [];
$parameters = [];
if ($search !== '') {
    $where[] = '(wb.bill_number LIKE :search_number OR wb.billing_period LIKE :search_period OR u.employee_id LIKE :search_employee OR u.first_name LIKE :search_first OR u.last_name LIKE :search_last)';
    $like = '%' . $search . '%';
    $parameters += [
        'search_number' => $like,
        'search_period' => $like,
        'search_employee' => $like,
        'search_first' => $like,
        'search_last' => $like,
    ];
}
if ($statusFilter !== '') {
    if ($statusFilter === 'overdue') {
        $where[] = 'wb.status <> "cancelled" AND COALESCE(p.amount_paid, 0.00) < wb.amount_due AND wb.due_date < CURRENT_DATE';
    } elseif ($statusFilter === 'partially_paid') {
        $where[] = 'wb.status <> "cancelled" AND COALESCE(p.amount_paid, 0.00) > 0 AND COALESCE(p.amount_paid, 0.00) < wb.amount_due AND wb.due_date >= CURRENT_DATE';
    } elseif ($statusFilter === 'paid') {
        $where[] = 'wb.status = "paid"';
    } elseif ($statusFilter === 'cancelled') {
        $where[] = 'wb.status = "cancelled"';
    } else {
        $where[] = 'wb.status <> "cancelled" AND COALESCE(p.amount_paid, 0.00) = 0 AND wb.due_date >= CURRENT_DATE';
    }
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$paidSubquery = ' LEFT JOIN (
    SELECT bill_id, SUM(amount) AS amount_paid
    FROM payments WHERE status = "paid" GROUP BY bill_id
) p ON p.bill_id = wb.id ';
$countQuery = $pdo->prepare(
    'SELECT COUNT(*) FROM water_bills wb
     JOIN users u ON u.id = wb.faculty_id' . $paidSubquery . $whereSql
);
$countQuery->execute($parameters);
$totalBills = (int) $countQuery->fetchColumn();
$billQuery = $pdo->prepare(
    'SELECT wb.id, wb.bill_number, wb.billing_period, wb.description, wb.amount_due,
            wb.due_date, wb.status, u.employee_id, u.first_name, u.last_name,
            COALESCE(p.amount_paid, 0.00) AS amount_paid
     FROM water_bills wb
     JOIN users u ON u.id = wb.faculty_id
     ' . $paidSubquery . '
     ' . $whereSql . '
     ORDER BY wb.due_date DESC, wb.id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $billQuery->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$billQuery->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$billQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
$billQuery->execute();
$bills = $billQuery->fetchAll();
$pages = max(1, (int) ceil($totalBills / $pageSize));

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Internal office drinking-water contributions</p>
            <h1>Water Bills</h1>
        </div>
        <a class="button small" href="bill-create.php">Create Bill</a>
    </div>

    <form method="GET" action="bills.php" class="filter-form">
        <label for="q">Search bills</label>
        <input id="q" name="q" value="<?= e($search) ?>" placeholder="Bill number, faculty, or billing period">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                    <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button small" type="submit">Filter</button>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Bill</th><th>Faculty</th><th>Period</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Due</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($bills as $bill): ?>
                <?php
                $status = billStatus($bill);
                $balance = parseNonNegativePhpAmount((string) $bill['amount_due']) - parseNonNegativePhpAmount((string) $bill['amount_paid']);
                ?>
                <tr>
                    <td><?= e($bill['bill_number']) ?></td>
                    <td><?= e($bill['first_name'] . ' ' . $bill['last_name']) ?><small><?= e($bill['employee_id']) ?></small></td>
                    <td><?= e($bill['billing_period']) ?></td>
                    <td><?= e(formatPhpAmount((string) $bill['amount_due'])) ?></td>
                    <td><?= e(formatPhpAmount((string) $bill['amount_paid'])) ?></td>
                    <td><?= e(formatPhpCents($balance)) ?></td>
                    <td><?= e($bill['due_date']) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', $status))) ?></td>
                    <td><a href="bill-view.php?id=<?= (int) $bill['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($bills === []): ?>
                <tr><td colspan="9">No water bills match these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Bill pages">
            <?php for ($pageNumber = 1; $pageNumber <= $pages; $pageNumber++): ?>
                <?php $query = http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $pageNumber]); ?>
                <a href="bills.php?<?= e($query) ?>" <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
