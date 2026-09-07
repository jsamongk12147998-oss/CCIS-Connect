<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdminPage = basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin';
$rootPrefix = $isAdminPage ? '../' : '';
$adminPrefix = $isAdminPage ? '' : 'admin/';
$homePage = isAdministratorSession() ? $adminPrefix . 'admin_dashboard.php' : $rootPrefix . 'dashboard.php';
$logoutPage = $isAdminPage ? 'admin_logout.php' : $rootPrefix . 'logout.php';
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
                <?php if (isAdministratorSession() && ($pageTitle ?? '') !== 'Admin Dashboard'): ?>
                    <a href="<?= $adminPrefix ?>admin_dashboard.php">Admin Dashboard</a>
                <?php elseif (!isAdministratorSession() && ($pageTitle ?? '') !== 'Dashboard'): ?>
                    <a href="<?= $rootPrefix ?>dashboard.php">Dashboard</a>
                <?php endif; ?>
                <details class="profile-menu">
                    <summary class="profile-button" aria-label="Open profile menu">
                        <img src="<?= $rootPrefix ?>assets/user-regular.png" alt="Profile">
                    </summary>
                    <div class="profile-popup">
                        <button type="button" class="profile-action">Edit Profile</button>
                        <a href="<?= $logoutPage ?>" class="profile-action">Logout</a>
                    </div>
                </details>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="container">
