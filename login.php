<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Login';
$error = '';
$lockoutDuration = 30;
$failedAttempts = (int) ($_SESSION['login_failed_attempts'] ?? 0);
$lockoutUntil = (int) ($_SESSION['login_lockout_until'] ?? 0);

if ($lockoutUntil > 0 && $lockoutUntil <= time()) {
    unset($_SESSION['login_failed_attempts'], $_SESSION['login_lockout_until']);
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
            'SELECT id, employee_id, first_name, last_name, email,
                    password, position, role, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] === 'administrator') {
                $error = 'Administrator accounts must use the administrator sign-in.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Your account is not active.';
            } else {
                unset($_SESSION['login_failed_attempts'], $_SESSION['login_lockout_until']);
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                unset($_SESSION['admin_authenticated']);

                $logStatement = $pdo->prepare(
                    'INSERT INTO audit_logs (user_id, action, ip_address)
                     VALUES (:user_id, :action, :ip_address)'
                );

                $logStatement->execute([
                    'user_id' => $user['id'],
                    'action' => 'User logged in',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                header('Location: dashboard.php');
                exit;
            }
        } else {
            $failedAttempts++;

            if ($failedAttempts >= 3) {
                $_SESSION['login_failed_attempts'] = 0;
                $_SESSION['login_lockout_until'] = time() + $lockoutDuration;
                $lockoutUntil = (int) $_SESSION['login_lockout_until'];
                $isLockedOut = true;
                $error = 'Too many failed attempts. Please try again in 30 seconds.';
            } else {
                $_SESSION['login_failed_attempts'] = $failedAttempts;
                $remainingAttempts = 3 - $failedAttempts;
                $error = "Invalid email or password. {$remainingAttempts} attempt(s) remaining.";
            }
        }
    }
}

if ($isLockedOut && $error === '') {
    $remainingSeconds = max(1, $lockoutUntil - time());
    $error = "Too many failed attempts. Please try again in {$remainingSeconds} seconds.";
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>CCIS Connect</h1>
    <p>Faculty Community and Engagement Platform</p>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert success">
            Registration successful. You may now log in.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            required
            <?= $isLockedOut ? 'disabled' : '' ?>
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            <?= $isLockedOut ? 'disabled' : '' ?>
        >

        <button type="button" class="forgot-password-button" <?= $isLockedOut ? 'disabled' : '' ?>>Forgot password?</button>
        <button type="submit" class="button" <?= $isLockedOut ? 'disabled' : '' ?>>Login</button>
        <a href="#" class="button button-google">
            <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" style="margin-right: 10px;">
              <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.3l6.8-6.8C35.5 2.6 30.2 0 24 0 14.8 0 7 5.4 3 13.5l8.1 6.3C13.2 12.6 18 9.5 24 9.5z"/>
              <path fill="#4285F4" d="M46.5 24.5c0-1.5-.1-3-0.4-4.5H24v9h12.7c-0.6 3-2.3 5.5-4.8 7.2l8.1 6.3C43.5 37.5 46.5 31.5 46.5 24.5z"/>
              <path fill="#FBBC05" d="M11.1 28.5C10.5 27 10 25.5 10 24s0.5-3 1.1-4.5L3 13.2C1.1 17.1 0 21.5 0 26s1.1 8.9 3 12.8l8.1-6.3z"/>
              <path fill="#34A853" d="M24 48c6.5 0 12-2.2 16-5.8l-8.1-6.3c-2.3 1.5-5.2 2.4-8.2 2.4-6 0-11.1-4-12.9-9.5l-8.1 6.3C7 42.6 14.8 48 24 48z"/>
            </svg>
            Continue with Google
        </a>
    </form>

    <p class="form-link">
        Need an account?
        Contact an administrator for registration.
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>