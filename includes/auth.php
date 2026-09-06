<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $isAdminRequest = strpos($scriptPath, '/admin/') !== false;

    session_name($isAdminRequest ? 'CCIS_ADMIN_SESSION' : 'CCIS_USER_SESSION');
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdministrator(): void
{
    if (!isLoggedIn()) {
        header('Location: admin_login.php');
        exit;
    }

    if (!isAdministratorSession()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function isAdministratorSession(): bool
{
    return isLoggedIn()
        && ($_SESSION['role'] ?? '') === 'administrator'
        && !empty($_SESSION['admin_authenticated']);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
