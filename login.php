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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $error = 'Please enter your password.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, employee_id, first_name, last_name, email,
                    password, department, position, role, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account is not active.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];

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
            $error = 'Invalid email or password.';
        }
    }
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
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <button type="submit" class="button">Login</button>
    </form>

    <p class="form-link">
        Do not have an account?
        <a href="register.php">Register as faculty</a>.
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
