<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/google.php';

$expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
$receivedState = (string) ($_GET['state'] ?? '');
unset($_SESSION['google_oauth_state']);

if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)) {
    redirect('login.php?google_error=invalid_response');
}

if (isset($_GET['error']) || !isset($_GET['code']) || !isGoogleConfigured()) {
    redirect('login.php?google_error=sign_in_failed');
}

try {
    $config = googleConfig();
    $tokens = googleRequest('https://oauth2.googleapis.com/token', [
        'code' => (string) $_GET['code'],
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);

    $accessToken = (string) ($tokens['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Google OAuth did not return an access token.');
    }

    $googleUser = googleRequest('https://openidconnect.googleapis.com/v1/userinfo', [], $accessToken);
    $email = strtolower(trim((string) ($googleUser['email'] ?? '')));
    $verified = ($googleUser['email_verified'] ?? false) === true;

    if (!$verified || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('login.php?google_error=unverified_email');
    }

    $statement = $pdo->prepare(
        'SELECT id, employee_id, first_name, last_name, role, status
         FROM users
         WHERE email = :email AND role = :role AND status = :status
         LIMIT 1'
    );
    $statement->execute([
        'email' => $email,
        'role' => 'faculty',
        'status' => 'active',
    ]);
    $user = $statement->fetch();

    if (!$user) {
        redirect('login.php?google_error=account_unavailable');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['role'] = $user['role'];
    unset($_SESSION['admin_authenticated']);

    $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, description, ip_address)
         VALUES (:user_id, :action, :description, :ip_address)'
    )->execute([
        'user_id' => $user['id'],
        'action' => 'GOOGLE_LOGIN',
        'description' => 'Faculty member signed in using Google OAuth.',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    redirect('dashboard.php');
} catch (RuntimeException $exception) {
    error_log('Google OAuth sign-in failed: ' . $exception->getMessage());
    redirect('login.php?google_error=sign_in_failed');
}
