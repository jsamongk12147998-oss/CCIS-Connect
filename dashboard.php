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

<section class="dashboard-intro">
    <div>
        <p class="eyebrow">Faculty workspace</p>
        <h1>Welcome, <?= e($user['first_name']) ?>!</h1>
        <p><?= e($user['position'] ?? 'Faculty Member') ?></p>
    </div>

    <div class="notification-badge">
        <strong><?= $unreadNotifications ?></strong>
        <span>Unread notifications</span>
    </div>
</section>

<section class="dashboard-overview">
    <div class="card profile-card">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Account overview</p>
                <h2>Faculty Profile</h2>
            </div>
            <span class="card-mark">ID</span>
        </div>

        <dl class="profile-details">
            <div>
                <dt>Name</dt>
                <dd><?= e($user['first_name'] . ' ' . $user['last_name']) ?></dd>
            </div>
            <div>
                <dt>Employee ID</dt>
                <dd><?= e($user['employee_id']) ?></dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd><?= e($user['email']) ?></dd>
            </div>
            <div>
                <dt>Position</dt>
                <dd><?= e($user['position'] ?? 'Not specified') ?></dd>
            </div>
        </dl>
    </div>

    <div class="card bill-card">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Utilities</p>
                <h2>Water Bill Summary</h2>
            </div>
            <?php if ($currentBill): ?>
                <span class="status <?= e($currentBill['status']) ?>">
                    <?= e(ucwords(str_replace('_', ' ', $currentBill['status']))) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($currentBill): ?>
            <div class="bill-amount">
                <span>Amount due</span>
                <strong>₱<?= number_format((float) $currentBill['amount'], 2) ?></strong>
            </div>

            <div class="bill-meta">
                <p><strong>Billing period</strong><?= e($currentBill['billing_period']) ?></p>
                <p><strong>Due date</strong><?= e($currentBill['due_date']) ?></p>
            </div>

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

    <div class="card quick-actions-card">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Shortcuts</p>
                <h2>Quick Actions</h2>
            </div>
        </div>

        <div class="quick-actions">
            <a href="#" class="action-link">View Faculty Groups</a>
            <a href="#" class="action-link">View Discussions</a>
            <a href="#" class="action-link">View Events</a>
            <a href="#" class="action-link">Open Messages</a>
        </div>
    </div>
</section>

<section class="dashboard-feeds">
    <div class="content-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Stay informed</p>
                <h2>Latest Announcements</h2>
            </div>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="card empty-state">
                <p>No announcements available.</p>
            </div>
        <?php else: ?>
            <div class="feed-list">
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
            </div>
        <?php endif; ?>
    </div>

    <div class="content-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Plan ahead</p>
                <h2>Upcoming Events</h2>
            </div>
        </div>

        <?php if (empty($events)): ?>
            <div class="card empty-state">
                <p>No upcoming events available.</p>
            </div>
        <?php else: ?>
            <div class="feed-list">
                <?php foreach ($events as $event): ?>
                    <article class="card event">
                        <h3><?= e($event['title']) ?></h3>
                        <p><?= nl2br(e($event['description'])) ?></p>
                        <p class="event-meta">
                            <strong>Date</strong><?= e($event['event_date']) ?>
                        </p>
                        <p class="event-meta">
                            <strong>Location</strong><?= e($event['location']) ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
