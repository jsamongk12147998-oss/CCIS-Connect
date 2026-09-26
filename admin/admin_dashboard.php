<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdministrator();
require_once __DIR__ . '/../includes/billing.php';

$pageTitle = 'Admin Dashboard';

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeFaculty = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'faculty' AND status = 'active'"
)->fetchColumn();
$pendingUsers = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE status = 'pending'"
)->fetchColumn();
$inactiveUsers = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE status = 'inactive'"
)->fetchColumn();

$billingSummary = $pdo->query(
    'SELECT COUNT(*) AS bill_count,
            COALESCE(SUM(CASE WHEN wb.status <> "cancelled" THEN wb.amount_due ELSE 0 END), 0.00) AS total_billed,
            COALESCE(SUM(CASE WHEN wb.status <> "cancelled" THEN LEAST(wb.amount_due, COALESCE(p.amount_paid, 0.00)) ELSE 0 END), 0.00) AS total_collected,
            COALESCE(SUM(CASE WHEN wb.status <> "cancelled" THEN GREATEST(wb.amount_due - COALESCE(p.amount_paid, 0.00), 0.00) ELSE 0 END), 0.00) AS total_outstanding,
            SUM(wb.status <> "cancelled" AND COALESCE(p.amount_paid, 0.00) = 0 AND wb.due_date >= CURRENT_DATE) AS unpaid_bills,
            SUM(wb.status <> "cancelled" AND COALESCE(p.amount_paid, 0.00) > 0 AND COALESCE(p.amount_paid, 0.00) < wb.amount_due AND wb.due_date >= CURRENT_DATE) AS partially_paid_bills,
            SUM(wb.status <> "cancelled" AND COALESCE(p.amount_paid, 0.00) >= wb.amount_due) AS paid_bills,
            SUM(wb.status <> "cancelled" AND COALESCE(p.amount_paid, 0.00) < wb.amount_due AND wb.due_date < CURRENT_DATE) AS overdue_bills
     FROM water_bills wb
     LEFT JOIN (
        SELECT bill_id, SUM(amount) AS amount_paid
        FROM payments WHERE status = "paid" GROUP BY bill_id
     ) p ON p.bill_id = wb.id'
)->fetch();
$paymentSummary = $pdo->query(
    'SELECT
        SUM(status = "pending") AS pending_payments,
        SUM(status = "processing") AS processing_payments,
        SUM(status = "paid") AS paid_payments,
        SUM(status IN ("failed", "rejected")) AS failed_payments,
        SUM(status = "rejected") AS rejected_payments
     FROM payments'
)->fetch();

$userStatement = $pdo->query(
    'SELECT employee_id, first_name, last_name, email, position, role, status, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 8'
);
$users = $userStatement->fetchAll();

$auditStatement = $pdo->query(
    'SELECT audit_logs.action, audit_logs.created_at,
            CONCAT(users.first_name, " ", users.last_name) AS actor
     FROM audit_logs
     LEFT JOIN users ON users.id = audit_logs.user_id
     ORDER BY audit_logs.created_at DESC
     LIMIT 8'
);
$auditLogs = $auditStatement->fetchAll();

$recentPayments = $pdo->query(
    'SELECT p.id, p.payment_reference, p.amount, p.gateway, p.status, p.created_at,
            u.first_name, u.last_name, u.employee_id
     FROM payments p JOIN users u ON u.id = p.faculty_id
     ORDER BY p.created_at DESC LIMIT 5'
)->fetchAll();

$recentAnnouncements = $pdo->query(
    'SELECT id, title, status, created_at FROM announcements
     ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

$upcomingEvents = $pdo->query(
    'SELECT id, title, event_date, location FROM events
     WHERE event_date >= CURRENT_TIMESTAMP AND status = "upcoming"
     ORDER BY event_date LIMIT 5'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-hero">
    <div>
        <p class="eyebrow">Operations center</p>
        <h1>Administrator Dashboard</h1>
        <p>Monitor accounts, manage faculty access, and review system activity.</p>
    </div>
    <a href="register.php" class="button admin-hero-button">Register Faculty</a>
</section>

<?php if (isset($_GET['registered'])): ?>
    <div class="alert success">Faculty account created successfully.</div>
<?php endif; ?>

<section class="admin-stats" aria-label="Account summary">
    <div class="card admin-stat">
        <span class="admin-stat-label">Total accounts</span>
        <strong><?= $totalUsers ?></strong>
        <small>All registered users</small>
    </div>
    <div class="card admin-stat">
        <span class="admin-stat-label">Active faculty</span>
        <strong><?= $activeFaculty ?></strong>
        <small>Can access the platform</small>
    </div>
    <div class="card admin-stat">
        <span class="admin-stat-label">Pending accounts</span>
        <strong><?= $pendingUsers ?></strong>
        <small>Awaiting review</small>
    </div>
    <div class="card admin-stat">
        <span class="admin-stat-label">Inactive accounts</span>
        <strong><?= $inactiveUsers ?></strong>
        <small>Access currently disabled</small>
    </div>
</section>

<section class="admin-grid">
    <div class="card admin-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Registration and access</p>
                <h2>Admin Tasks</h2>
            </div>
        </div>
        <div class="admin-task-list">
            <a href="register.php" class="admin-task">
                <span class="admin-task-icon">+</span>
                <span><strong>Register faculty</strong><small>Create an active faculty account.</small></span>
            </a>
            <a href="#user-monitoring" class="admin-task">
                <span class="admin-task-icon">U</span>
                <span><strong>Monitor accounts</strong><small>Review roles and account status.</small></span>
            </a>
            <a href="#audit-activity" class="admin-task">
                <span class="admin-task-icon">A</span>
                <span><strong>Review audit activity</strong><small>See recent sign-ins and changes.</small></span>
            </a>
            <a href="bills.php" class="admin-task">
                <span class="admin-task-icon">W</span>
                <span><strong>Water bills</strong><small>Create bills and review outstanding balances.</small></span>
            </a>
            <a href="payments.php" class="admin-task">
                <span class="admin-task-icon">P</span>
                <span><strong>Payments</strong><small>Review provider transactions and receipts.</small></span>
            </a>
            <a href="reports/billing.php" class="admin-task">
                <span class="admin-task-icon">R</span>
                <span><strong>Billing reports</strong><small>Review collections and balances.</small></span>
            </a>
        </div>
    </div>

    <div class="card admin-panel" id="audit-activity">
        <div class="section-heading">
            <div>
                <p class="eyebrow">System monitoring</p>
                <h2>Recent Activity</h2>
            </div>
        </div>
        <?php if (empty($auditLogs)): ?>
            <p class="admin-empty">No audit activity recorded yet.</p>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($auditLogs as $log): ?>
                    <div class="activity-item">
                        <strong><?= e($log['action']) ?></strong>
                        <small><?= e($log['actor'] ?? 'System') ?> · <?= e($log['created_at']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="admin-stats" aria-label="Water bill and payment summary">
    <?php foreach ([
        'Total water bills' => (string) ($billingSummary['bill_count'] ?? 0),
        'Unpaid bills' => (string) ($billingSummary['unpaid_bills'] ?? 0),
        'Partially paid bills' => (string) ($billingSummary['partially_paid_bills'] ?? 0),
        'Paid bills' => (string) ($billingSummary['paid_bills'] ?? 0),
        'Overdue bills' => (string) ($billingSummary['overdue_bills'] ?? 0),
        'Pending payments' => (string) ($paymentSummary['pending_payments'] ?? 0),
        'Processing payments' => (string) ($paymentSummary['processing_payments'] ?? 0),
        'Paid payments' => (string) ($paymentSummary['paid_payments'] ?? 0),
        'Failed payments' => (string) ($paymentSummary['failed_payments'] ?? 0),
        'Rejected payments' => (string) ($paymentSummary['rejected_payments'] ?? 0),
        'Total billed' => formatPhpAmount((string) ($billingSummary['total_billed'] ?? '0.00')),
        'Total collected' => formatPhpAmount((string) ($billingSummary['total_collected'] ?? '0.00')),
        'Total outstanding' => formatPhpAmount((string) ($billingSummary['total_outstanding'] ?? '0.00')),
    ] as $label => $value): ?>
        <div class="card admin-stat">
            <span class="admin-stat-label"><?= e($label) ?></span>
            <strong><?= e($value) ?></strong>
        </div>
    <?php endforeach; ?>
</section>

<section class="admin-grid">
    <div class="card admin-panel">
        <div class="section-heading"><div><p class="eyebrow">Collection activity</p><h2>Recent Transactions</h2></div><a href="payments.php">All payments</a></div>
        <div class="activity-list">
            <?php foreach ($recentPayments as $payment): ?>
                <div class="activity-item">
                    <strong><a href="payment-view.php?id=<?= (int) $payment['id'] ?>"><?= e($payment['payment_reference']) ?></a> · <?= e(formatPhpAmount((string) $payment['amount'])) ?></strong>
                    <small><?= e($payment['first_name'] . ' ' . $payment['last_name'] . ' (' . $payment['employee_id'] . ')') ?> · <?= e(ucfirst($payment['status'])) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if ($recentPayments === []): ?><p class="admin-empty">No transactions recorded.</p><?php endif; ?>
        </div>
    </div>
    <div class="card admin-panel">
        <div class="section-heading"><div><p class="eyebrow">Community</p><h2>Recent Announcements</h2></div><a href="announcements.php">Manage</a></div>
        <div class="activity-list">
            <?php foreach ($recentAnnouncements as $announcement): ?>
                <div class="activity-item"><strong><?= e($announcement['title']) ?></strong><small><?= e(ucfirst($announcement['status'])) ?> · <?= e($announcement['created_at']) ?></small></div>
            <?php endforeach; ?>
            <?php if ($recentAnnouncements === []): ?><p class="admin-empty">No announcements.</p><?php endif; ?>
        </div>
    </div>
    <div class="card admin-panel">
        <div class="section-heading"><div><p class="eyebrow">Calendar</p><h2>Upcoming Events</h2></div></div>
        <div class="activity-list">
            <?php foreach ($upcomingEvents as $event): ?>
                <div class="activity-item"><strong><?= e($event['title']) ?></strong><small><?= e($event['event_date']) ?> · <?= e($event['location'] ?? 'Location not set') ?></small></div>
            <?php endforeach; ?>
            <?php if ($upcomingEvents === []): ?><p class="admin-empty">No upcoming events.</p><?php endif; ?>
        </div>
    </div>
</section>

<section class="card admin-panel" id="user-monitoring">
    <div class="section-heading">
        <div>
            <p class="eyebrow">User monitoring</p>
            <h2>Registered Accounts</h2>
        </div>
        <a href="register.php" class="button small">Register Faculty</a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['employee_id']) ?></td>
                        <td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td><?= e(ucfirst($user['role'])) ?></td>
                        <td><span class="status <?= e($user['status']) ?>"><?= e(ucfirst($user['status'])) ?></span></td>
                        <td><?= e($user['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
