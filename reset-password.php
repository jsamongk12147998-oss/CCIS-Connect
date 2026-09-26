<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Reset Password';
$errors = [];
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$tokenHash = hash('sha256', $token);
$success = false;
$tokenValid = false;

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $errors[] = 'This password reset link is invalid or has expired.';
} else {
    $check = $pdo->prepare(
        'SELECT prt.id
         FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = :token_hash
           AND prt.used_at IS NULL
           AND prt.expires_at > CURRENT_TIMESTAMP
           AND u.status = :status
         LIMIT 1'
    );
    $check->execute(['token_hash' => $tokenHash, 'status' => 'active']);
    if (!$check->fetch()) {
        $errors[] = 'This password reset link is invalid or has expired.';
    } else {
        $tokenValid = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    requireValidCsrfToken();
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $passwordError = passwordPolicyError($password);

    if ($passwordError !== null) {
        $errors[] = $passwordError;
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'The passwords do not match.';
    }

    if ($errors === []) {
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'SELECT prt.id, prt.user_id
             FROM password_reset_tokens prt
             JOIN users u ON u.id = prt.user_id
             WHERE prt.token_hash = :token_hash
               AND prt.used_at IS NULL
               AND prt.expires_at > CURRENT_TIMESTAMP
               AND u.status = :status
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['token_hash' => $tokenHash, 'status' => 'active']);
        $resetRecord = $statement->fetch();

        if (!$resetRecord) {
            $pdo->rollBack();
            $tokenValid = false;
            $errors[] = 'This password reset link is invalid or has expired.';
        } else {
            $pdo->prepare(
                'UPDATE users
                 SET password = :password, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :user_id'
            )->execute([
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'user_id' => $resetRecord['user_id'],
            ]);
            $pdo->prepare(
                'UPDATE password_reset_tokens
                 SET used_at = CURRENT_TIMESTAMP
                 WHERE user_id = :user_id AND used_at IS NULL'
            )->execute(['user_id' => $resetRecord['user_id']]);
            $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, description, ip_address)
                 VALUES (:user_id, :action, :description, :ip_address)'
            )->execute([
                'user_id' => $resetRecord['user_id'],
                'action' => 'PASSWORD_RESET_COMPLETED',
                'description' => 'Password reset completed.',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            $pdo->commit();
            $success = true;

            destroySession();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>Reset Password</h1>

    <?php if ($success): ?>
        <div class="alert success" role="status">Your password has been reset. You can now sign in with your new password.</div>
        <p class="form-link"><a href="login.php">Go to login</a></p>
    <?php elseif (!$tokenValid): ?>
        <div class="alert error" role="alert"><?= e($errors[0]) ?></div>
        <p class="form-link"><a href="forgot-password.php">Request a new reset link</a></p>
    <?php else: ?>
        <?php if ($errors !== []): ?>
            <div class="alert error" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="reset-password.php">
            <?= csrfField() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>

            <button type="submit" class="button">Reset Password</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
