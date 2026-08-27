<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Faculty Registration';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = trim($_POST['employee_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($employeeId === '') {
        $errors[] = 'Employee ID is required.';
    }

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {
        $errors[] = 'Password must include lowercase and uppercase letters, a number, and a special character.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $checkStatement = $pdo->prepare(
            'SELECT id FROM users WHERE employee_id = :employee_id OR email = :email'
        );

        $checkStatement->execute([
            'employee_id' => $employeeId,
            'email' => $email
        ]);

        if ($checkStatement->fetch()) {
            $errors[] = 'Employee ID or email is already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStatement = $pdo->prepare(
                'INSERT INTO users
                (employee_id, first_name, last_name, email, password, position)
                VALUES
                (:employee_id, :first_name, :last_name, :email, :password, :position)'
            );

            $insertStatement->execute([
                'employee_id' => $employeeId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => $hashedPassword,
                'position' => $position !== '' ? $position : null
            ]);

            header('Location: login.php?registered=1');
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>Faculty Registration</h1>
    <p>Create your CCIS Connect faculty account.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <label for="employee_id">Employee ID</label>
        <input
            type="text"
            id="employee_id"
            name="employee_id"
            value="<?= e($_POST['employee_id'] ?? '') ?>"
            required
        >

        <label for="first_name">First Name</label>
        <input
            type="text"
            id="first_name"
            name="first_name"
            value="<?= e($_POST['first_name'] ?? '') ?>"
            required
        >

        <label for="last_name">Last Name</label>
        <input
            type="text"
            id="last_name"
            name="last_name"
            value="<?= e($_POST['last_name'] ?? '') ?>"
            required
        >

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= e($_POST['email'] ?? '') ?>"
            required
        >

        <label for="position">Position</label>
        <input
            type="text"
            id="position"
            name="position"
            value="<?= e($_POST['position'] ?? '') ?>"
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).{8,}"
            title="Use at least 8 characters with lowercase and uppercase letters, a number, and a special character."
            required
        >

        <label for="confirm_password">Confirm Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            required
        >

        <button type="submit" class="button">Register</button>
    </form>

    <p class="form-link">
        Already registered?
        <a href="login.php">Log in here</a>.
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
