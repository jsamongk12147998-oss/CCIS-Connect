<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_name('CCIS_CONNECT_SESSION');
    session_start();
}

$scriptPath = trim((string) parse_url($_SERVER['SCRIPT_NAME'] ?? '/', PHP_URL_PATH), '/');
$pathParts = $scriptPath === '' ? [] : explode('/', $scriptPath);
$directoryDepth = max(0, count($pathParts) - 1);
$rootPrefix = str_repeat('../', $directoryDepth);
$isAdminPage = in_array('admin', $pathParts, true);
$adminPathIndex = array_search('admin', $pathParts, true);
$adminDirectoryDepth = $adminPathIndex === false ? 0 : $adminPathIndex + 1;
$adminBase = $isAdminPage
    ? str_repeat('../', max(0, $directoryDepth - $adminDirectoryDepth))
    : $rootPrefix . 'admin/';
$homePage = isAdministratorSession() ? $adminBase . 'admin_dashboard.php' : $rootPrefix . 'dashboard.php';
$logoutPage = isAdministratorSession() ? $adminBase . 'admin_logout.php' : $rootPrefix . 'logout.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'CCIS Connect') ?></title>
    <link rel="stylesheet" href="<?= $rootPrefix ?>assets/style.css">
</head>
<body>

<header class="main-header">
    <div class="container nav-container">
        <a href="<?= $homePage ?>" class="brand">CCIS Connect</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="topbar-actions">
                <?php if (isAdministratorSession()): ?>
                    <a href="<?= $adminBase ?>bills.php">Water Bills</a>
                    <a href="<?= $adminBase ?>payments.php">Payments</a>
                    <a href="<?= $adminBase ?>reports/billing.php">Reports</a>
                <?php else: ?>
                    <a href="<?= $rootPrefix ?>faculty/water-bill.php">My Water Bill</a>
                    <a href="<?= $rootPrefix ?>faculty/payments.php">Payments</a>
                    <a href="<?= $rootPrefix ?>faculty/payment-history.php">Payment History</a>
                <?php endif; ?>
                <?php if (isAdministratorSession() && ($pageTitle ?? '') !== 'Admin Dashboard'): ?>
                    <a href="<?= $adminBase ?>admin_dashboard.php">Admin Dashboard</a>
                <?php elseif (!isAdministratorSession() && ($pageTitle ?? '') !== 'Dashboard'): ?>
                    <a href="<?= $rootPrefix ?>dashboard.php">Dashboard</a>
                <?php endif; ?>
                <details class="profile-menu">
                    <summary class="profile-button" aria-label="Open profile menu">
                        <img src="<?= $rootPrefix ?>assets/user-regular.png" alt="Profile">
                    </summary>
                    <div class="profile-popup">
                        <a href="<?= $rootPrefix ?>profile.php" class="profile-action">Profile</a>
                        <a href="<?= $rootPrefix ?>change-password.php" class="profile-action">Change password</a>
                        <form method="POST" action="<?= e($logoutPage) ?>">
                            <?= csrfField() ?>
                            <button type="submit" class="profile-action">Logout</button>
                        </form>
                    </div>
                </details>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="container">
