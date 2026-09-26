<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdministrator();

$pageTitle = 'Faculty Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($userId > 0 && in_array($status, ['active', 'inactive', 'pending'], true)) {
        $statement = $pdo->prepare('UPDATE users SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $userId]);
        $pdo->prepare('INSERT INTO audit_logs (user_id, action, description, ip_address) VALUES (:user_id, :action, :description, :ip_address)')
            ->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => 'FACULTY_STATUS',
                'description' => 'Updated faculty status to ' . $status,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
    }
}

$faculty = $pdo->query(
    'SELECT id, employee_id, first_name, last_name, email, position, status, role, created_at
     FROM users
     WHERE role = "faculty"
     ORDER BY created_at DESC'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-card">
    <h1>Faculty Management</h1>
    <p>Review registrations and update faculty access.</p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Position</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($faculty as $member): ?>
                <tr>
                    <td><?= e($member['first_name'] . ' ' . $member['last_name']) ?></td>
                    <td><?= e($member['email']) ?></td>
                    <td><?= e($member['position'] ?? 'N/A') ?></td>
                    <td><?= e($member['status']) ?></td>
                    <td>
                        <form method="POST" action="faculty.php" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                            <select name="status">
                                <option value="pending" <?= $member['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <button type="submit" class="button small">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
