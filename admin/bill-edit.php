<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireAdministrator();

$billId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$billId || $billId < 1) {
    http_response_code(404);
    exit('Water bill not found.');
}

$statement = $pdo->prepare(
    'SELECT wb.*, wb.faculty_id, u.employee_id, u.first_name, u.last_name
     FROM water_bills wb JOIN users u ON u.id = wb.faculty_id
     WHERE wb.id = :id LIMIT 1'
);
$statement->execute(['id' => $billId]);
$bill = $statement->fetch();
if (!$bill) {
    http_response_code(404);
    exit('Water bill not found.');
}

$errors = [];
$paymentCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM payments WHERE bill_id = :bill_id'
);
$paymentCheck->execute(['bill_id' => $billId]);
$hasPayments = (int) $paymentCheck->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $period = trim((string) ($_POST['billing_period'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $dueDate = trim((string) ($_POST['due_date'] ?? ''));
    $amountString = trim((string) ($_POST['amount_due'] ?? ''));

    try {
        $amountCents = parsePhpAmount($amountString);
    } catch (InvalidArgumentException $exception) {
        $amountCents = null;
        $errors[] = $exception->getMessage();
    }
    if ($period === '' || mb_strlen($period) > 50) {
        $errors[] = 'Billing period is required and must not exceed 50 characters.';
    }
    if (mb_strlen($description) > 255) {
        $errors[] = 'Description must not exceed 255 characters.';
    }
    $dueDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
    if (!$dueDateObject || $dueDateObject->format('Y-m-d') !== $dueDate) {
        $errors[] = 'Enter a valid due date.';
    }
    if ($hasPayments) {
        $errors[] = 'A bill with any payment attempt cannot be edited. Cancel it and create a corrected bill instead.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT status FROM water_bills WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $billId]);
            $locked = $lock->fetch();
            if (!$locked || $locked['status'] === 'cancelled') {
                throw new RuntimeException('This bill is no longer editable.');
            }
            $lockedPaymentCheck = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE bill_id = :bill_id');
            $lockedPaymentCheck->execute(['bill_id' => $billId]);
            if ((int) $lockedPaymentCheck->fetchColumn() > 0) {
                throw new RuntimeException('A bill with a payment attempt cannot be edited.');
            }
            $amount = amountFromMinorUnits($amountCents);
            $pdo->prepare(
                'UPDATE water_bills
                 SET billing_period = :period, description = :description,
                     total_amount = :total_amount, amount_due = :amount_due,
                     balance_due = :balance_due, due_date = :due_date
                 WHERE id = :id'
            )->execute([
                'period' => $period,
                'description' => $description !== '' ? $description : null,
                'total_amount' => $amount,
                'amount_due' => $amount,
                'balance_due' => $amount,
                'due_date' => $dueDate,
                'id' => $billId,
            ]);
            createBillingAudit(
                $pdo,
                (int) $_SESSION['user_id'],
                'WATER_BILL_UPDATED',
                'Updated water bill ' . $bill['bill_number'] . '.'
            );
            $pdo->commit();
            redirect('bill-view.php?id=' . $billId . '&updated=1');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                $errors[] = 'A bill already exists for this faculty member and billing period.';
            } else {
                throw $exception;
            }
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        }
    }
}

$pageTitle = 'Edit Water Bill';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-card">
    <h1>Edit <?= e($bill['bill_number']) ?></h1>
    <p><?= e($bill['first_name'] . ' ' . $bill['last_name'] . ' (' . $bill['employee_id'] . ')') ?></p>
    <?php if ($errors !== []): ?><div class="alert error" role="alert"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if (!$hasPayments): ?>
        <form method="POST" action="bill-edit.php?id=<?= (int) $billId ?>">
            <?= csrfField() ?>
            <label for="billing_period">Billing Period</label>
            <input id="billing_period" name="billing_period" maxlength="50" value="<?= e($_POST['billing_period'] ?? $bill['billing_period']) ?>" required>
            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="255" rows="3"><?= e($_POST['description'] ?? $bill['description'] ?? '') ?></textarea>
            <label for="amount_due">Amount Due (PHP)</label>
            <input id="amount_due" name="amount_due" inputmode="decimal" value="<?= e($_POST['amount_due'] ?? $bill['amount_due']) ?>" required>
            <label for="due_date">Due Date</label>
            <input id="due_date" type="date" name="due_date" value="<?= e($_POST['due_date'] ?? $bill['due_date']) ?>" required>
            <button class="button" type="submit">Save Changes</button>
        </form>
    <?php endif; ?>
    <p class="form-link"><a href="bill-view.php?id=<?= (int) $billId ?>">Back to bill</a></p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
