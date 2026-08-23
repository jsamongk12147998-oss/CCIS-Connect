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
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="container">
