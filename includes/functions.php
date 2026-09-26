<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function currentUser(): ?array
{
    global $pdo;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT u.*, r.name AS role_name
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => (int) $_SESSION['user_id']]);
    return $statement->fetch() ?: null;
}

function formatCurrency(float $amount): string
{
    return '₱' . number_format($amount, 2, '.', ',');
}

function logAudit(int $userId, string $action, string $description = '', ?string $ipAddress = null): void
{
    global $pdo;

    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, description, ip_address)
         VALUES (:user_id, :action, :description, :ip_address)'
    );
    $statement->execute([
        'user_id' => $userId,
        'action' => $action,
        'description' => $description,
        'ip_address' => $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null),
    ]);
}

function createNotification(int $userId, string $title, string $message, string $type = 'system'): void
{
    global $pdo;

    $statement = $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, notification_type)
         VALUES (:user_id, :title, :message, :notification_type)'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'notification_type' => $type,
    ]);
}

function redirectTo(string $path): void
{
    header('Location: ' . $path);
    exit;
}
