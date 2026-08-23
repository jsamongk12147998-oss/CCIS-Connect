<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Dashboard';
$userId = (int) $_SESSION['user_id'];

$userStatement = $pdo->prepare(
    'SELECT employee_id, first_name, last_name, email,
            position, role
     FROM users
     WHERE id = :id
     LIMIT 1'
);

$userStatement->execute(['id' => $userId]);
$user = $userStatement->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$announcementStatement = $pdo->query(
    'SELECT announcements.title,
            announcements.content,
            announcements.created_at,
            CONCAT(users.first_name, " ", users.last_name) AS author
     FROM announcements
     INNER JOIN users ON users.id = announcements.created_by
     ORDER BY announcements.created_at DESC
     LIMIT 5'
);

$announcements = $announcementStatement->fetchAll();

$eventStatement = $pdo->query(
    'SELECT title, description, event_date, location
     FROM events
     WHERE event_date >= NOW()
     ORDER BY event_date ASC
     LIMIT 5'
);

$events = $eventStatement->fetchAll();

$billStatement = $pdo->prepare(
    'SELECT wb.id, wb.billing_period, wb.total_amount AS amount, wb.due_date, wb.status
     FROM water_bills wb
     INNER JOIN water_accounts wa ON wb.water_account_id = wa.id
     WHERE wa.user_id = :user_id
     ORDER BY wb.due_date DESC
     LIMIT 1'
);

$billStatement->execute(['user_id' => $userId]);
$currentBill = $billStatement->fetch();

$notificationStatement = $pdo->prepare(
    'SELECT COUNT(*) AS unread_count
     FROM notifications
     WHERE user_id = :user_id
     AND is_read = FALSE'
);

$notificationStatement->execute(['user_id' => $userId]);
$notificationData = $notificationStatement->fetch();
$unreadNotifications = (int) ($notificationData['unread_count'] ?? 0);

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-header">
    <div>
        <h1>Welcome, <?= e($user['first_name']) ?>!</h1>
        <p>
            <?= e($user['position'] ?? 'Faculty Member') ?>
        </p>
    </div>

    <div class="notification-badge">
        Notifications: <?= $unreadNotifications ?>
    </div>
</div>

<section class="dashboard-grid">
    <div class="card">
        <h2>Faculty Profile</h2>

        <p>
            <strong>Name:</strong>
            <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
        </p>

        <p>
            <strong>Employee ID:</strong>
            <?= e($user['employee_id']) ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= e($user['email']) ?>
        </p>

        <p>
            <strong>Position:</strong>
            <?= e($user['position'] ?? 'Not specified') ?>
        </p>
    </div>

    <div class="card">
        <h2>Water Bill Summary</h2>

        <?php if ($currentBill): ?>
            <p>
                <strong>Billing Period:</strong>
                <?= e($currentBill['billing_period']) ?>
            </p>

            <p>
                <strong>Amount Due:</strong>
                ₱<?= number_format((float) $currentBill['amount'], 2) ?>
            </p>

            <p>
                <strong>Due Date:</strong>
                <?= e($currentBill['due_date']) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <span class="status <?= e($currentBill['status']) ?>">
                    <?= e(ucwords(str_replace('_', ' ', $currentBill['status']))) ?>
                </span>
            </p>

            <?php if ($currentBill['status'] !== 'paid'): ?>
                <a
                    href="#"
                    class="button small"
                    onclick="alert('Water bill payment page will be added next.'); return false;"
                >
                    View Payment
                </a>
            <?php endif; ?>
        <?php else: ?>
            <p>No water bill is currently available.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Quick Actions</h2>

        <a href="#" class="action-link">View Faculty Groups</a>
        <a href="#" class="action-link">View Discussions</a>
        <a href="#" class="action-link">View Events</a>
        <a href="#" class="action-link">Open Messages</a>
    </div>
</section>

<section class="content-section">
    <h2>Latest Announcements</h2>

    <?php if (empty($announcements)): ?>
        <div class="card">
            <p>No announcements available.</p>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
            <article class="card announcement">
                <h3><?= e($announcement['title']) ?></h3>

                <p><?= nl2br(e($announcement['content'])) ?></p>

                <small>
                    Posted by <?= e($announcement['author']) ?>
                    on <?= e($announcement['created_at']) ?>
                </small>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="content-section">
    <h2>Upcoming Events</h2>

    <?php if (empty($events)): ?>
        <div class="card">
            <p>No upcoming events available.</p>
        </div>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <article class="card event">
                <h3><?= e($event['title']) ?></h3>

                <p><?= nl2br(e($event['description'])) ?></p>

                <p>
                    <strong>Date:</strong>
                    <?= e($event['event_date']) ?>
                </p>

                <p>
                    <strong>Location:</strong>
                    <?= e($event['location']) ?>
                </p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
