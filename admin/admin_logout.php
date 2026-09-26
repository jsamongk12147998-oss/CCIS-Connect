<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin_login.php');
}

requireValidCsrfToken();
destroySession();

redirect('admin_login.php');
