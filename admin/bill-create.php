<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../config/mail.php';

requireAdministrator();

$pageTitle = 'Create Water Bill';
$errors = [];
$faculty = $pdo->query(
    "SELECT id, employee_id, first_name, last_name, email
     FROM users WHERE role = 'faculty' AND status = 'active'
     ORDER BY last_name, first_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $facultyId = filter_input(INPUT_POST, 'faculty_id', FILTER_VALIDATE_INT) ?: 0;
    $period = trim((string) ($_POST['billing_period'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $dueDate = trim((string) ($_POST['due_date'] ?? ''));
    $amountString = trim((string) ($_POST['amount_due'] ?? ''));
    $amountCents = null;

    try {
        $amountCents = parsePhpAmount($amountString);
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }
    if ($facultyId <= 0) {
        $errors[] = 'Select an active faculty member.';
    }
    if ($period === '' || mb_strlen($period) > 50) {
        $errors[] = 'Billing period is required and must not exceed 50 characters.';
    }
    if ($description !== '' && mb_strlen($description) > 255) {
        $errors[] = 'Description must not exceed 255 characters.';
    }
    $dueDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
    if (!$dueDateObject || $dueDateObject->format('Y-m-d') !== $dueDate) {
        $errors[] = 'Enter a valid due date.';
    }

    if ($errors === []) {
        $facultyStatement = $pdo->prepare(
            "SELECT id, employee_id, email, first_name
             FROM users WHERE id = :id AND role = 'faculty' AND status = 'active'
             LIMIT 1"
        );
        $facultyStatement->execute(['id' => $facultyId]);
        $member = $facultyStatement->fetch();
        if (!$member) {
            $errors[] = 'The selected faculty account is not active.';
        }
    }

    if ($errors === []) {
        $billNumber = 'WB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $amount = amountFromMinorUnits($amountCents);

        try {
            $pdo->beginTransaction();
            $accountStatement = $pdo->prepare('SELECT id FROM water_accounts WHERE user_id = :user_id FOR UPDATE');
            $accountStatement->execute(['user_id' => $facultyId]);
            $accountId = $accountStatement->fetchColumn();
            if (!$accountId) {
                $accountNumber = substr('WTR-' . $member['employee_id'], 0, 50);
                $pdo->prepare(
                    'INSERT INTO water_accounts (user_id, account_number, account_status)
                     VALUES (:user_id, :account_number, :status)'
                )->execute([
                    'user_id' => $facultyId,
                    'account_number' => $accountNumber,
                    'status' => 'active',
                ]);
                $accountId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare(
                'INSERT INTO water_bills
                    (water_account_id, bill_number, faculty_id, billing_period, description,
                     total_amount, amount_due, amount_paid, balance_due, due_date, status, created_by)
                 VALUES
                    (:account_id, :bill_number, :faculty_id, :period, :description,
                     :total_amount, :amount_due, 0.00, :balance_due, :due_date, :status, :created_by)'
            )->execute([
                'account_id' => $accountId,
                'bill_number' => $billNumber,
                'faculty_id' => $facultyId,
                'period' => $period,
                'description' => $description !== '' ? $description : null,
                'total_amount' => $amount,
                'amount_due' => $amount,
                'balance_due' => $amount,
                'due_date' => $dueDate,
                'status' => 'unpaid',
                'created_by' => (int) $_SESSION['user_id'],
            ]);
            $billId = (int) $pdo->lastInsertId();
            createBillNotification($pdo, $facultyId, $billNumber, $period);
            createBillingAudit($pdo, (int) $_SESSION['user_id'], 'WATER_BILL_CREATED', "Created water bill {$billNumber} for faculty {$member['employee_id']}.");
            $pdo->commit();

            if (isMailConfigured()) {
                try {
                    sendMail(
                        $member['email'],
                        'New CCIS Connect water bill',
                        "Hello {$member['first_name']},\n\nA water contribution bill is available for {$period}.\nBill: {$billNumber}\nAmount due: " . formatPhpAmount($amount) . "\nDue date: {$dueDate}\n\nSign in to CCIS Connect to view bill details."
                    );
                } catch (RuntimeException $exception) {
                    error_log('Water bill email notification failed: ' . $exception->getMessage());
                }
            }

            redirect('bill-view.php?id=' . $billId . '&created=1');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                $errors[] = 'A bill already exists for this faculty member and billing period.';
            } else {
                throw $exception;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-card">
    <h1>Create Water Bill</h1>
    <p>Bill internal office drinking-water contributions. Online checkout charges the full outstanding balance for the bill.</p>
    <?php if ($errors !== []): ?>
        <div class="alert error" role="alert"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="POST" action="bill-create.php">
        <?= csrfField() ?>
        <label for="faculty_id">Faculty</label>
        <select id="faculty_id" name="faculty_id" required>
            <option value="">Select faculty</option>
            <?php foreach ($faculty as $member): ?>
                <option value="<?= (int) $member['id'] ?>" <?= (string) ($_POST['faculty_id'] ?? '') === (string) $member['id'] ? 'selected' : '' ?>>
                    <?= e($member['last_name'] . ', ' . $member['first_name'] . ' (' . $member['employee_id'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="billing_period">Billing Period</label>
        <input id="billing_period" name="billing_period" maxlength="50" value="<?= e($_POST['billing_period'] ?? '') ?>" placeholder="September 2026" required>

        <label for="description">Description</label>
        <textarea id="description" name="description" maxlength="255" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>

        <label for="amount_due">Amount Due (PHP)</label>
        <input id="amount_due" name="amount_due" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" value="<?= e($_POST['amount_due'] ?? '') ?>" placeholder="300.00" required>

        <label for="due_date">Due Date</label>
        <input id="due_date" type="date" name="due_date" value="<?= e($_POST['due_date'] ?? '') ?>" required>
        <button class="button" type="submit">Create Bill</button>
    </form>
    <p class="form-link"><a href="bills.php">Back to water bills</a></p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
