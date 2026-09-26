<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Profile';
$success = '';
$errors = [];

$userId = (int) $_SESSION['user_id'];

$current = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$current->execute(['id' => $userId]);
$user = $current->fetch();

if (!$user) {
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $duplicate->execute(['email' => $email, 'id' => $userId]);
        if ($duplicate->fetch()) {
            $errors[] = 'Another faculty member already uses that email.';
        }
    }

    if (empty($errors)) {
        $statement = $pdo->prepare(
            'UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, position = :position, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'position' => $position !== '' ? $position : null,
            'id' => $userId,
        ]);

        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $success = 'Profile updated successfully.';
        $user['first_name'] = $firstName;
        $user['last_name'] = $lastName;
        $user['email'] = $email;
        $user['position'] = $position;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>My Profile</h1>
    <p>View and update your profile details.</p>

    <?php if ($success !== ''): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="profile.php">
        <?= csrfField() ?>
        <label for="employee_id">Employee ID</label>
        <input type="text" id="employee_id" value="<?= e($user['employee_id']) ?>" disabled>

        <label for="first_name">First Name</label>
        <input type="text" id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required>

        <label for="last_name">Last Name</label>
        <input type="text" id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>

        <label for="position">Position</label>
        <input type="text" id="position" name="position" value="<?= e($user['position'] ?? '') ?>">

        <label for="status">Account Status</label>
        <input type="text" id="status" value="<?= e($user['status']) ?>" disabled>

        <button type="submit" class="button">Save Changes</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
