<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/mail.php';

$pageTitle = 'Forgot Password';
$errors = [];
$submitted = false;
$mailConfigured = isMailConfigured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $submitted = true;
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
        $submitted = false;
    } else {
        $statement = $pdo->prepare(
            'SELECT id, first_name, email
             FROM users
             WHERE email = :email AND status = :status
             LIMIT 1'
        );
        $statement->execute(['email' => $email, 'status' => 'active']);
        $user = $statement->fetch();

        if ($user) {
            $rateLimit = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM password_reset_tokens
                 WHERE user_id = :user_id
                   AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)'
            );
            $rateLimit->execute(['user_id' => $user['id']]);

            if ((int) $rateLimit->fetchColumn() < 3) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);

                $pdo->beginTransaction();
                $pdo->prepare(
                    'UPDATE password_reset_tokens
                     SET used_at = CURRENT_TIMESTAMP
                     WHERE user_id = :user_id AND used_at IS NULL'
                )->execute(['user_id' => $user['id']]);

                $pdo->prepare(
                    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                     VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR))'
                )->execute([
                    'user_id' => $user['id'],
                    'token_hash' => $tokenHash,
                ]);

                $pdo->prepare(
                    'INSERT INTO audit_logs (user_id, action, description, ip_address)
                     VALUES (:user_id, :action, :description, :ip_address)'
                )->execute([
                    'user_id' => $user['id'],
                    'action' => 'PASSWORD_RESET_REQUESTED',
                    'description' => 'Password reset requested.',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                $pdo->commit();

                $appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://127.0.0.1:8000'), '/');
                $parsedAppUrl = filter_var($appUrl, FILTER_VALIDATE_URL) ? parse_url($appUrl) : false;
                if ($parsedAppUrl === false
                    || !in_array($parsedAppUrl['scheme'] ?? '', ['http', 'https'], true)
                    || empty($parsedAppUrl['host'])
                    || isset($parsedAppUrl['query'])
                    || isset($parsedAppUrl['fragment'])
                ) {
                    throw new RuntimeException('APP_URL must be a valid base HTTP or HTTPS URL.');
                }

                $resetUrl = $appUrl . '/reset-password.php?token=' . rawurlencode($token);
                $message = "Hello {$user['first_name']},\n\n"
                    . "Use the following link to reset your CCIS Connect password. "
                    . "This link expires in one hour and can only be used once.\n\n"
                    . $resetUrl
                    . "\n\nIf you did not request this change, you can ignore this email.";

                try {
                    sendMail($user['email'], 'CCIS Connect password reset', $message);
                } catch (RuntimeException $exception) {
                    error_log('Password reset email could not be sent: ' . $exception->getMessage());
                    $pdo->prepare(
                        'UPDATE password_reset_tokens
                         SET used_at = CURRENT_TIMESTAMP
                         WHERE token_hash = :token_hash AND used_at IS NULL'
                    )->execute(['token_hash' => $tokenHash]);
                    $pdo->prepare(
                        'INSERT INTO audit_logs (user_id, action, description, ip_address)
                         VALUES (:user_id, :action, :description, :ip_address)'
                    )->execute([
                        'user_id' => $user['id'],
                        'action' => 'PASSWORD_RESET_DELIVERY_FAILED',
                        'description' => 'Password reset email delivery failed; check server SMTP configuration.',
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]);
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>Forgot Password</h1>
    <p>Enter your institutional email address. If an active account matches, a one-time reset link will be sent when email delivery is configured.</p>

    <?php if (!$mailConfigured): ?>
        <div class="alert error" role="alert">
            Password-reset email is unavailable because SMTP is not fully configured. Contact an administrator to reset your password.
        </div>
    <?php endif; ?>

    <?php if ($submitted): ?>
        <div class="alert success" role="status">
            If an active account matches that email, a reset link will be sent. Check your inbox or contact an administrator if you do not receive one.
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert error" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="forgot-password.php">
        <?= csrfField() ?>
        <label for="email">Institutional Email</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
        <button type="submit" class="button">Send Reset Link</button>
    </form>

    <p class="form-link"><a href="login.php">Back to login</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
