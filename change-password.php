<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Change Password';
$errors = [];
$success = '';
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $statement = $pdo->prepare('SELECT password FROM users WHERE id = :id AND status = :status LIMIT 1');
    $statement->execute(['id' => $userId, 'status' => 'active']);
    $user = $statement->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        $errors[] = 'The current password is incorrect.';
    }

    $passwordError = passwordPolicyError($newPassword);
    if ($passwordError !== null) {
        $errors[] = $passwordError;
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'The new passwords do not match.';
    }
    if ($user && password_verify($newPassword, $user['password'])) {
        $errors[] = 'Choose a password different from your current password.';
    }

    if ($errors === []) {
        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE users
             SET password = :password, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = :status'
        )->execute([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
            'status' => 'active',
        ]);
        $pdo->prepare(
            'UPDATE password_reset_tokens
             SET used_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND used_at IS NULL'
        )->execute(['user_id' => $userId]);
        $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, description, ip_address)
             VALUES (:user_id, :action, :description, :ip_address)'
        )->execute([
            'user_id' => $userId,
            'action' => 'PASSWORD_CHANGED',
            'description' => 'Password changed by the account holder.',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $pdo->commit();
        $success = 'Your password has been changed.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>Change Password</h1>

    <?php if ($success !== ''): ?>
        <div class="alert success" role="status"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert error" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="change-password.php">
        <?= csrfField() ?>
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>

        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>

        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>

        <button type="submit" class="button">Change Password</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
