<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (isAdministratorSession()) {
    header('Location: admin_dashboard.php');
    exit;
}

$pageTitle = 'Administrator Sign In';
$error = '';
$lockoutDuration = 30;
$failedAttempts = (int) ($_SESSION['admin_login_failed_attempts'] ?? 0);
$lockoutUntil = (int) ($_SESSION['admin_login_lockout_until'] ?? 0);

if ($lockoutUntil > 0 && $lockoutUntil <= time()) {
    unset($_SESSION['admin_login_failed_attempts'], $_SESSION['admin_login_lockout_until']);
    $failedAttempts = 0;
    $lockoutUntil = 0;
}

$isLockedOut = $lockoutUntil > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLockedOut) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $error = 'Please enter your password.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, employee_id, first_name, last_name, password, role, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user && $user['role'] === 'administrator' && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'This administrator account is not active.';
            } else {
                unset($_SESSION['admin_login_failed_attempts'], $_SESSION['admin_login_lockout_until']);
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['admin_authenticated'] = true;

                $logStatement = $pdo->prepare(
                    'INSERT INTO audit_logs (user_id, action, ip_address)
                     VALUES (:user_id, :action, :ip_address)'
                );
                $logStatement->execute([
                    'user_id' => $user['id'],
                    'action' => 'Administrator logged in',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                header('Location: admin_dashboard.php');
                exit;
            }
        } else {
            $failedAttempts++;

            if ($failedAttempts >= 3) {
                $_SESSION['admin_login_failed_attempts'] = 0;
                $_SESSION['admin_login_lockout_until'] = time() + $lockoutDuration;
                $lockoutUntil = (int) $_SESSION['admin_login_lockout_until'];
                $isLockedOut = true;
                $error = 'Too many failed attempts. Please try again in 30 seconds.';
            } else {
                $_SESSION['admin_login_failed_attempts'] = $failedAttempts;
                $remainingAttempts = 3 - $failedAttempts;
                $error = "Invalid administrator credentials. {$remainingAttempts} attempt(s) remaining.";
            }
        }
    }
}

if ($isLockedOut && $error === '') {
    $remainingSeconds = max(1, $lockoutUntil - time());
    $error = "Too many failed attempts. Please try again in {$remainingSeconds} seconds.";
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-card">
    <p class="eyebrow">Restricted access</p>
    <h1>Administrator Sign In</h1>
    <p>Use your administrator credentials to manage CCIS Connect.</p>

    <?php if ($error !== ''): ?>
        <div class="alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="admin_login.php">
        <label for="email">Administrator Email</label>
        <input type="email" id="email" name="email" required <?= $isLockedOut ? 'disabled' : '' ?>>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required <?= $isLockedOut ? 'disabled' : '' ?>>

        <button type="submit" class="button" <?= $isLockedOut ? 'disabled' : '' ?>>Sign In</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
