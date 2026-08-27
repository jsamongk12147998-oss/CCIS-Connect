<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Dashboard';
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);

    if ($action === 'create_post') {
        $postContent = trim((string) ($_POST['post_content'] ?? ''));

        if ($postContent !== '') {
            $postStatement = $pdo->prepare(
                'INSERT INTO posts (user_id, content, status)
                 VALUES (:user_id, :content, "published")'
            );
            $postStatement->execute([
                'user_id' => $userId,
                'content' => $postContent,
            ]);
        }

        header('Location: dashboard.php#community-feed');
        exit;
    }

    if ($action === 'react' && $postId > 0) {
        $reactionLookup = $pdo->prepare(
            'SELECT id FROM reactions WHERE post_id = :post_id AND user_id = :user_id'
        );
        $reactionLookup->execute(['post_id' => $postId, 'user_id' => $userId]);
        $reactionId = $reactionLookup->fetchColumn();

        if ($reactionId) {
            $reactionStatement = $pdo->prepare('DELETE FROM reactions WHERE id = :id');
            $reactionStatement->execute(['id' => $reactionId]);
        } else {
            $reactionStatement = $pdo->prepare(
                'INSERT INTO reactions (post_id, user_id, reaction_type)
                 VALUES (:post_id, :user_id, :reaction_type)'
            );
            $reactionStatement->execute([
                'post_id' => $postId,
                'user_id' => $userId,
                'reaction_type' => 'like',
            ]);
        }
    }

    if ($action === 'comment' && $postId > 0) {
        $commentContent = trim((string) ($_POST['comment_content'] ?? ''));

        if ($commentContent !== '') {
            $commentStatement = $pdo->prepare(
                'INSERT INTO comments (post_id, user_id, comment_content)
                 VALUES (:post_id, :user_id, :comment_content)'
            );
            $commentStatement->execute([
                'post_id' => $postId,
                'user_id' => $userId,
                'comment_content' => $commentContent,
            ]);
        }
    }

    header('Location: dashboard.php#community-feed');
    exit;
}

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

$postStatement = $pdo->prepare(
    'SELECT posts.id,
            posts.content,
            posts.created_at,
            users.first_name,
            users.last_name,
            users.position,
            (SELECT COUNT(*) FROM reactions WHERE reactions.post_id = posts.id) AS reaction_count,
            (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS comment_count,
            EXISTS (
                SELECT 1 FROM reactions
                WHERE reactions.post_id = posts.id
                AND reactions.user_id = :user_id
            ) AS user_reacted
     FROM posts
     INNER JOIN users ON users.id = posts.user_id
     WHERE posts.status = "published"
     ORDER BY posts.created_at DESC
     LIMIT 10'
);
$postStatement->execute(['user_id' => $userId]);
$posts = $postStatement->fetchAll();

$commentStatement = $pdo->prepare(
    'SELECT comments.post_id,
            comments.comment_content,
            comments.created_at,
            users.first_name,
            users.last_name
     FROM comments
     INNER JOIN users ON users.id = comments.user_id
     WHERE comments.post_id = :post_id
     ORDER BY comments.created_at ASC
     LIMIT 3'
);

foreach ($posts as &$post) {
    $commentStatement->execute(['post_id' => $post['id']]);
    $post['comments'] = $commentStatement->fetchAll();
}
unset($post);

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

require_once __DIR__ . '/includes/header.php';
?>

<section class="dashboard-top">
    <div class="dashboard-main">
        <div class="dashboard-intro">
        <div>
            <p class="eyebrow">Faculty workspace</p>
            <h1>Welcome, <?= e($user['first_name']) ?>!</h1>
            <p><?= e($user['position'] ?? 'Faculty Member') ?></p>
        </div>

        <div class="intro-bill">
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
        </div>

        <div class="card post-composer">
            <div class="composer-heading">
                <span class="avatar" aria-hidden="true">
                    <?= e(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?>
                </span>
                <div>
                    <strong>Create a post</strong>
                    <small>Share an update with your faculty community</small>
                </div>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="create_post">
                <textarea name="post_content" rows="3" maxlength="2000" placeholder="What would you like to share?" required></textarea>
                <div class="composer-actions">
                    <button type="button" class="attachment-button">
                        <span aria-hidden="true">+</span>
                        Add Attachments
                    </button>
                    <button type="submit" class="button post-submit">Publish Post</button>
                </div>
            </form>
        </div>
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

<section class="dashboard-feeds" id="community-feed">
    <div class="content-section community-feed">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Community activity</p>
                <h2>Faculty Feed</h2>
            </div>
        </div>

        <?php if (empty($posts)): ?>
            <div class="card empty-state">
                <p>No community posts yet.</p>
            </div>
        <?php else: ?>
            <div class="feed-list">
                <?php foreach ($posts as $post): ?>
                    <article class="card social-post">
                        <header class="post-author">
                            <span class="avatar" aria-hidden="true">
                                <?= e(strtoupper(substr($post['first_name'], 0, 1) . substr($post['last_name'], 0, 1))) ?>
                            </span>
                            <div>
                                <strong><?= e($post['first_name'] . ' ' . $post['last_name']) ?></strong>
                                <small><?= e($post['position'] ?? 'Faculty Member') ?> · <?= e($post['created_at']) ?></small>
                            </div>
                        </header>
                        <p class="post-content"><?= nl2br(e($post['content'])) ?></p>
                        <div class="post-summary">
                            <span><?= (int) $post['reaction_count'] ?> likes</span>
                            <span><?= (int) $post['comment_count'] ?> comments</span>
                        </div>
                        <div class="post-actions">
                            <form method="post">
                                <input type="hidden" name="action" value="react">
                                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                <button type="submit" class="post-action <?= $post['user_reacted'] ? 'is-active' : '' ?>">
                                    <?= $post['user_reacted'] ? 'Liked' : 'Like' ?>
                                </button>
                            </form>
                            <button type="button" class="post-action comment-toggle" aria-expanded="false">Comment</button>
                        </div>
                        <div class="post-comments">
                            <?php foreach ($post['comments'] as $comment): ?>
                                <div class="comment-item">
                                    <strong><?= e($comment['first_name'] . ' ' . $comment['last_name']) ?></strong>
                                    <p><?= e($comment['comment_content']) ?></p>
                                </div>
                            <?php endforeach; ?>
                            <form method="post" class="comment-form">
                                <input type="hidden" name="action" value="comment">
                                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                <input type="text" name="comment_content" placeholder="Write a comment..." maxlength="1000" required>
                                <button type="submit" class="button small">Post</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-section feed-updates">
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

<script>
    document.querySelectorAll('.comment-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const comments = button.closest('.social-post').querySelector('.post-comments');
            const isOpen = comments.classList.toggle('is-open');
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
