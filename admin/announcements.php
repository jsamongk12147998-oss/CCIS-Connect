<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdministrator();

$pageTitle = 'Announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' && $title !== '' && $content !== '') {
        $statement = $pdo->prepare(
            'INSERT INTO announcements (title, content, created_by, status) VALUES (:title, :content, :created_by, :status)'
        );
        $statement->execute([
            'title' => $title,
            'content' => $content,
            'created_by' => (int) $_SESSION['user_id'],
            'status' => 'published',
        ]);
    }
}

$announcements = $pdo->query(
    'SELECT a.id, a.title, a.content, a.status, a.created_at, CONCAT(u.first_name, " ", u.last_name) AS author
     FROM announcements a
     JOIN users u ON u.id = a.created_by
     ORDER BY a.created_at DESC'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-card">
    <h1>Announcements</h1>

    <form method="POST" action="announcements.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" required>

        <label for="content">Content</label>
        <textarea id="content" name="content" rows="6" required></textarea>

        <button type="submit" class="button">Publish</button>
    </form>

    <table class="admin-table" style="margin-top: 2rem;">
        <thead>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($announcements as $item): ?>
                <tr>
                    <td><?= e($item['title']) ?></td>
                    <td><?= e($item['author']) ?></td>
                    <td><?= e($item['status']) ?></td>
                    <td><?= e($item['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
