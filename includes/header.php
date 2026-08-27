<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'CCIS Connect') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="main-header">
    <div class="container nav-container">
        <a href="dashboard.php" class="brand">CCIS Connect</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="topbar-actions">
                <?php if (($pageTitle ?? '') !== 'Dashboard'): ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a href="#" class="create-button">
                    <span class="plus-icon" aria-hidden="true">+</span>
                    Create
                </a>
                <details class="profile-menu">
                    <summary class="profile-button" aria-label="Open profile menu">
                        <img src="assets/user-regular.png" alt="Profile">
                    </summary>
                    <div class="profile-popup">
                        <button type="button" class="profile-action">Edit Profile</button>
                        <a href="logout.php" class="profile-action">Logout</a>
                    </div>
                </details>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="container">
