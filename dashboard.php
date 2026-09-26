<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/billing.php';

requireLogin();

$pageTitle = 'Dashboard';
$userId = (int) $_SESSION['user_id'];
$postError = $_SESSION['post_error'] ?? '';
unset($_SESSION['post_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);

    if ($action === 'create_post') {
        $postContent = trim((string) ($_POST['post_content'] ?? ''));
        $attachment = $_FILES['attachment'] ?? null;
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        if ($postContent === '' && (!$attachment || $attachment['error'] === UPLOAD_ERR_NO_FILE)) {
            $_SESSION['post_error'] = 'Add text or attach a file before publishing.';
            header('Location: dashboard.php#community-feed');
            exit;
        }

        if ($attachment && $attachment['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = [
                'image' => [
                    'max_size' => 5 * 1024 * 1024,
                    'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                    'extension' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
                ],
                'video' => [
                    'max_size' => 50 * 1024 * 1024,
                    'mimes' => ['video/mp4', 'video/webm', 'video/quicktime'],
                    'extension' => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
                ],
                'file' => [
                    'max_size' => 10 * 1024 * 1024,
                    'mimes' => [
                        'application/pdf',
                        'text/plain',
                        'application/zip',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/msword',
                        'application/vnd.ms-excel',
                    ],
                    'extension' => [
                        'application/pdf' => 'pdf',
                        'text/plain' => 'txt',
                        'application/zip' => 'zip',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                        'application/msword' => 'doc',
                        'application/vnd.ms-excel' => 'xls',
                    ],
                ],
            ];

            $uploadError = (int) $attachment['error'];
            $fileSize = (int) $attachment['size'];
            $attachmentCategory = null;

            if ($uploadError === UPLOAD_ERR_OK) {
                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $fileInfo->file($attachment['tmp_name']);

                foreach ($allowedTypes as $category => $rules) {
                    if (in_array($mimeType, $rules['mimes'], true)) {
                        $attachmentCategory = $category;
                        break;
                    }
                }
            }

            if ($uploadError !== UPLOAD_ERR_OK) {
                $postError = 'The attachment could not be uploaded.';
            } elseif ($attachmentCategory === null) {
                $postError = 'Only supported images, videos, PDF, text, Word, Excel, and ZIP files may be attached.';
            } elseif ($fileSize > $allowedTypes[$attachmentCategory]['max_size']) {
                $postError = $attachmentCategory === 'image'
                    ? 'Images must be 5 MB or smaller.'
                    : ($attachmentCategory === 'video' ? 'Videos must be 50 MB or smaller.' : 'Files must be 10 MB or smaller.');
            } elseif (!is_uploaded_file($attachment['tmp_name'])) {
                $postError = 'The uploaded attachment is invalid.';
            } else {
                $uploadDirectory = __DIR__ . '/uploads/posts';
                $extension = $allowedTypes[$attachmentCategory]['extension'][$mimeType];
                $storedFilename = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');

                if (!move_uploaded_file($attachment['tmp_name'], $uploadDirectory . '/' . $storedFilename)) {
                    $postError = 'The attachment could not be saved.';
                } else {
                    $attachmentPath = 'uploads/posts/' . $storedFilename;
                    $attachmentName = basename($attachment['name']);
                    $attachmentType = $attachmentCategory;
                    $attachmentSize = $fileSize;
                }
            }
        }

        if ($postError === '') {
            $postStatement = $pdo->prepare(
                'INSERT INTO posts
                    (user_id, content, attachment_path, attachment_name, attachment_type, attachment_size, status)
                 VALUES
                    (:user_id, :content, :attachment_path, :attachment_name, :attachment_type, :attachment_size, "published")'
            );
            $postStatement->execute([
                'user_id' => $userId,
                'content' => $postContent,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_type' => $attachmentType,
                'attachment_size' => $attachmentSize,
            ]);
        } elseif ($attachmentPath !== null) {
            @unlink(__DIR__ . '/' . $attachmentPath);
        }

        if ($postError !== '') {
            $_SESSION['post_error'] = $postError;
        }
        header('Location: dashboard.php#community-feed');
        exit;
    }

    if ($action === 'edit_post' && $postId > 0) {
        $editedContent = trim((string) ($_POST['post_content'] ?? ''));
        $ownershipStatement = $pdo->prepare(
            'SELECT attachment_path
             FROM posts
             WHERE id = :post_id
               AND user_id = :user_id
               AND status <> "deleted"
             LIMIT 1'
        );
        $ownershipStatement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        $ownedPost = $ownershipStatement->fetch();

        if (!$ownedPost) {
            $_SESSION['post_error'] = 'You can only edit your own posts.';
        } elseif ($editedContent === '' && empty($ownedPost['attachment_path'])) {
            $_SESSION['post_error'] = 'A post cannot be empty.';
        } else {
            $editStatement = $pdo->prepare(
                'UPDATE posts
                 SET content = :content, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :post_id
                   AND user_id = :user_id
                   AND status <> "deleted"'
            );
            $editStatement->execute([
                'content' => $editedContent,
                'post_id' => $postId,
                'user_id' => $userId,
            ]);
        }

        header('Location: dashboard.php#community-feed');
        exit;
    }

    if ($action === 'delete_post' && $postId > 0) {
        $deleteLookup = $pdo->prepare(
            'SELECT attachment_path
             FROM posts
             WHERE id = :post_id
               AND user_id = :user_id
               AND status <> "deleted"
             LIMIT 1'
        );
        $deleteLookup->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        $ownedPost = $deleteLookup->fetch();

        if ($ownedPost) {
            $deleteStatement = $pdo->prepare(
                'UPDATE posts
                 SET status = "deleted",
                     attachment_path = NULL,
                     attachment_name = NULL,
                     attachment_type = NULL,
                     attachment_size = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :post_id
                   AND user_id = :user_id'
            );
            $deleteStatement->execute([
                'post_id' => $postId,
                'user_id' => $userId,
            ]);

            if (!empty($ownedPost['attachment_path'])) {
                @unlink(__DIR__ . '/' . $ownedPost['attachment_path']);
            }
        } else {
            $_SESSION['post_error'] = 'You can only delete your own posts.';
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
            posts.user_id,
            posts.content,
            posts.attachment_path,
            posts.attachment_name,
            posts.attachment_type,
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
    'SELECT wb.id, wb.bill_number, wb.billing_period, wb.amount_due, wb.due_date, wb.status,
            COALESCE(p.amount_paid, 0.00) AS amount_paid
     FROM water_bills wb
     LEFT JOIN (
         SELECT bill_id, SUM(amount) AS amount_paid
         FROM payments WHERE status = "paid" GROUP BY bill_id
     ) p ON p.bill_id = wb.id
     WHERE wb.faculty_id = :user_id
     ORDER BY wb.due_date DESC, wb.id DESC
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
                    <?php $currentBillStatus = billStatus($currentBill); ?>
                    <span class="status <?= e($currentBillStatus) ?>">
                        <?= e(ucwords(str_replace('_', ' ', $currentBillStatus))) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($currentBill): ?>
                <?php
                $billDueCents = parseNonNegativePhpAmount((string) $currentBill['amount_due']);
                $billPaidCents = min($billDueCents, parseNonNegativePhpAmount((string) $currentBill['amount_paid']));
                $billBalanceCents = max(0, $billDueCents - $billPaidCents);
                ?>
                <div class="bill-amount">
                    <span>Amount due</span>
                    <strong><?= e(formatPhpCents($billBalanceCents)) ?></strong>
                </div>

                <div class="bill-meta">
                    <p><strong>Billing period</strong><?= e($currentBill['billing_period']) ?></p>
                    <p><strong>Due date</strong><?= e($currentBill['due_date']) ?></p>
                    <p><strong>Bill amount</strong><?= e(formatPhpAmount((string) $currentBill['amount_due'])) ?></p>
                    <p><strong>Amount paid</strong><?= e(formatPhpCents($billPaidCents)) ?></p>
                </div>

                <a class="button small" href="faculty/water-bill.php?id=<?= (int) $currentBill['id'] ?>">View Water Bill</a>
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
            <?php if ($postError !== ''): ?>
                <div class="alert error"><?= e($postError) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_post">
                <textarea name="post_content" rows="3" maxlength="2000" placeholder="What would you like to share?"></textarea>
                <input type="file" id="attachment" name="attachment" class="attachment-input" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,application/pdf,text/plain,.doc,.docx,.xls,.xlsx,.zip">
                <div id="attachment-preview" class="attachment-preview" hidden aria-live="polite"></div>
                <small id="attachment-help" class="attachment-help" hidden>Images up to 5 MB, videos up to 50 MB, and other files up to 10 MB.</small>
                <div class="composer-actions">
                    <div class="attachment-actions">
                        <label for="attachment" class="attachment-button">
                            <span aria-hidden="true">+</span>
                            Add Attachments
                        </label>
                        <button type="button" id="remove-attachment" class="attachment-remove" hidden>Remove Attachment</button>
                    </div>
                    <div class="composer-publish-actions">
                        <button type="submit" class="button post-submit">Publish Post</button>
                        <details class="composer-menu">
                            <summary class="composer-more-button" aria-label="More create options" title="More create options">
                                <span aria-hidden="true">&#8942;</span>
                            </summary>
                        </details>
                    </div>
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
            <a href="faculty/water-bill.php" class="action-link">My Water Bills</a>
            <a href="faculty/payments.php" class="action-link">Payment Transactions</a>
            <a href="#community-feed" class="action-link">View Faculty Feed</a>
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
                        <?php if (!empty($post['attachment_path'])): ?>
                            <div class="post-attachment post-attachment-<?= e($post['attachment_type']) ?>">
                                <?php if ($post['attachment_type'] === 'image'): ?>
                                    <div class="post-attachment-frame">
                                        <img src="<?= e($post['attachment_path']) ?>" alt="<?= e($post['attachment_name']) ?>">
                                    </div>
                                <?php elseif ($post['attachment_type'] === 'video'): ?>
                                    <div class="post-attachment-frame">
                                        <video controls preload="metadata">
                                            <source src="<?= e($post['attachment_path']) ?>">
                                        </video>
                                    </div>
                                <?php else: ?>
                                    <a href="<?= e($post['attachment_path']) ?>" download><?= e($post['attachment_name']) ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="post-summary">
                            <span><?= (int) $post['reaction_count'] ?> likes</span>
                            <span><?= (int) $post['comment_count'] ?> comments</span>
                        </div>
                        <div class="post-actions">
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="react">
                                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                <button type="submit" class="post-action <?= $post['user_reacted'] ? 'is-active' : '' ?>">
                                    <?= $post['user_reacted'] ? 'Liked' : 'Like' ?>
                                </button>
                            </form>
                            <button type="button" class="post-action comment-toggle" aria-expanded="false">Comment</button>
                            <?php if ((int) $post['user_id'] === $userId): ?>
                                <button
                                    type="button"
                                    class="post-action edit-toggle"
                                    data-edit-target="edit-form-<?= (int) $post['id'] ?>"
                                    aria-expanded="false"
                                    aria-controls="edit-form-<?= (int) $post['id'] ?>"
                                >Edit</button>
                            <?php endif; ?>
                        </div>
                        <?php if ((int) $post['user_id'] === $userId): ?>
                            <form method="post" id="edit-form-<?= (int) $post['id'] ?>" class="post-edit-form" hidden>
                                <?= csrfField() ?>
                                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                <textarea name="post_content" rows="3" maxlength="2000"><?= e($post['content']) ?></textarea>
                                <div class="post-edit-actions">
                                    <button type="button" class="button small edit-cancel">Cancel</button>
                                    <button type="submit" name="action" value="edit_post" class="button small">Save Changes</button>
                                    <button type="submit" name="action" value="delete_post" class="button small delete-button" onclick="return confirm('Delete this post? This cannot be undone.');">Delete Post</button>
                                </div>
                            </form>
                        <?php endif; ?>
                        <div class="post-comments">
                            <?php foreach ($post['comments'] as $comment): ?>
                                <div class="comment-item">
                                    <strong><?= e($comment['first_name'] . ' ' . $comment['last_name']) ?></strong>
                                    <p><?= e($comment['comment_content']) ?></p>
                                </div>
                            <?php endforeach; ?>
                            <form method="post" class="comment-form">
                                <?= csrfField() ?>
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
                <h2>My Upcoming Events</h2>
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

    document.querySelectorAll('.edit-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const editForm = document.getElementById(button.dataset.editTarget);

            if (!editForm) {
                return;
            }

            const isOpening = editForm.hasAttribute('hidden');

            if (isOpening) {
                editForm.removeAttribute('hidden');
                editForm.classList.add('is-open');
            } else {
                editForm.setAttribute('hidden', 'hidden');
                editForm.classList.remove('is-open');
            }

            button.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
        });
    });

    document.querySelectorAll('.edit-cancel').forEach((button) => {
        button.addEventListener('click', () => {
            const editForm = button.closest('.post-edit-form');
            editForm.setAttribute('hidden', 'hidden');
            editForm.classList.remove('is-open');
            document.querySelector(`[data-edit-target="${editForm.id}"]`).setAttribute('aria-expanded', 'false');
        });
    });

    const attachmentInput = document.getElementById('attachment');
    const removeAttachmentButton = document.getElementById('remove-attachment');
    const attachmentPreview = document.getElementById('attachment-preview');
    const attachmentHelp = document.getElementById('attachment-help');
    let attachmentPreviewUrl = '';

    const clearAttachment = () => {
        if (attachmentPreviewUrl !== '') {
            URL.revokeObjectURL(attachmentPreviewUrl);
            attachmentPreviewUrl = '';
        }

        attachmentInput.value = '';
        attachmentPreview.replaceChildren();
        attachmentPreview.hidden = true;
        removeAttachmentButton.hidden = true;
        attachmentHelp.hidden = true;
    };

    attachmentInput.addEventListener('change', () => {
        const [file] = attachmentInput.files;

        if (!file) {
            clearAttachment();
            return;
        }

        if (attachmentPreviewUrl !== '') {
            URL.revokeObjectURL(attachmentPreviewUrl);
        }

        attachmentPreviewUrl = URL.createObjectURL(file);
        attachmentPreview.replaceChildren();
        attachmentPreview.hidden = false;
        removeAttachmentButton.hidden = false;
        attachmentHelp.hidden = false;

        if (file.type.startsWith('image/')) {
            const image = document.createElement('img');
            image.src = attachmentPreviewUrl;
            image.alt = file.name;
            attachmentPreview.appendChild(image);
        } else if (file.type.startsWith('video/')) {
            const video = document.createElement('video');
            video.src = attachmentPreviewUrl;
            video.controls = true;
            video.preload = 'metadata';
            attachmentPreview.appendChild(video);
        } else {
            const fileLabel = document.createElement('span');
            fileLabel.textContent = file.name;
            attachmentPreview.appendChild(fileLabel);
        }
    });

    removeAttachmentButton.addEventListener('click', clearAttachment);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
