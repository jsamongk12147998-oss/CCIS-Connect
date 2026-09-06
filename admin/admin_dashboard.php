<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdministrator();

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
