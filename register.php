<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = 'Faculty Registration';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($employeeId === '') $errors[] = 'Employee ID is required.';
    if ($firstName === '') $errors[] = 'First name is required.';
    if ($lastName === '') $errors[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid institutional email is required.';
    $passwordError = passwordPolicyError($password);
    if ($passwordError !== null) $errors[] = $passwordError;
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT id FROM users WHERE employee_id = :employee_id OR email = :email LIMIT 1');
        $check->execute(['employee_id' => $employeeId, 'email' => $email]);
        if ($check->fetch()) {
            $errors[] = 'An account with that employee ID or email already exists.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $pdo->prepare(
            'INSERT INTO users (employee_id, first_name, last_name, email, password, position, role, status)
             VALUES (:employee_id, :first_name, :last_name, :email, :password, :position, :role, :status)'
        );

        $statement->execute([
            'employee_id' => $employeeId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $hash,
            'position' => $position !== '' ? $position : null,
            'role' => 'faculty',
            'status' => 'pending',
        ]);

        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO audit_logs (user_id, action, description, ip_address) VALUES (:user_id, :action, :description, :ip_address)')
            ->execute([
                'user_id' => $userId,
                'action' => 'REGISTRATION',
                'description' => 'Faculty registration submitted',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

        redirect('login.php?registered=1');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>Faculty Registration</h1>
    <p>Submit your request for CCIS Connect access.</p>

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
        <?= csrfField() ?>
        <label for="employee_id">Employee ID</label>
        <input type="text" id="employee_id" name="employee_id" value="<?= e($_POST['employee_id'] ?? '') ?>" required>

        <label for="first_name">First Name</label>
        <input type="text" id="first_name" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" required>

        <label for="last_name">Last Name</label>
        <input type="text" id="last_name" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" required>

        <label for="email">Institutional Email</label>
        <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

        <label for="position">Position</label>
        <input type="text" id="position" name="position" value="<?= e($_POST['position'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit" class="button">Register</button>
    </form>

    <p class="form-link">
        <a href="login.php">Back to login</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
