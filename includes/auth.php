<?php

declare(strict_types=1);

function ensureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('CCIS_CONNECT_SESSION');
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

ensureSession();

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdministratorSession(): bool
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'administrator';
}

function isFacultySession(): bool
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'faculty';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('/login.php');
    }

    global $pdo;
    $statement = $pdo->prepare('SELECT role, status FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        redirect('/login.php?inactive=1');
    }

    $_SESSION['role'] = $user['role'];
}

function requireAdministrator(): void
{
    requireLogin();

    if (!isAdministratorSession()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function requireFaculty(): void
{
    requireLogin();

    if (!isFacultySession()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function requireValidCsrfToken(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken)
        || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(419);
        exit('Your session expired or the form was invalid. Please go back, refresh the page, and try again.');
    }
}

function passwordPolicyError(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }

    if (!preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {
        return 'Password must include lowercase and uppercase letters, a number, and a special character.';
    }

    return null;
}

function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}
