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
    billBalancesQuery() . ' WHERE wb.id = :id LIMIT 1'
);
$statement->execute(['id' => $billId]);
$bill = $statement->fetch();
if (!$bill) {
    http_response_code(404);
    exit('Water bill not found.');
}

$status = billStatus($bill);
$dueCents = parseNonNegativePhpAmount((string) $bill['amount_due']);
$paidCents = min($dueCents, parseNonNegativePhpAmount((string) $bill['amount_paid']));
$balanceCents = max(0, $dueCents - $paidCents);
$paymentsStatement = $pdo->prepare(
    'SELECT id, payment_reference, amount, payment_method, gateway, status, created_at, paid_at
     FROM payments WHERE bill_id = :bill_id ORDER BY created_at DESC'
);
$paymentsStatement->execute(['bill_id' => $billId]);
$payments = $paymentsStatement->fetchAll();
$manualMethods = $pdo->query(
    "SELECT id, method_name FROM payment_methods
     WHERE is_active = 1 AND method_name IN ('Cash', 'Bank Transfer', 'E-Wallet')
     ORDER BY method_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = (string) ($_POST['action'] ?? '');
    if (!in_array($action, ['cancel', 'record_manual_payment'], true)) {
        http_response_code(400);
        exit('Invalid bill action.');
    }

    $pdo->beginTransaction();
    if ($action === 'record_manual_payment') {
        $methodId = filter_input(INPUT_POST, 'payment_method_id', FILTER_VALIDATE_INT) ?: 0;
        $notes = trim((string) ($_POST['notes'] ?? ''));
        try {
            $amountCents = parsePhpAmount(trim((string) ($_POST['amount'] ?? '')));
            if ($methodId < 1) {
                throw new InvalidArgumentException('Select a manual payment method.');
            }
            $paymentId = recordManualBillPayment(
                $pdo,
                (int) $billId,
                (int) $_SESSION['user_id'],
                $methodId,
                $amountCents,
                $notes
            );
            $pdo->commit();
            redirect('payment-view.php?id=' . $paymentId . '&recorded=1');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $manualPaymentError = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    if ($action === 'cancel') {
        $lock = $pdo->prepare('SELECT status, amount_due FROM water_bills WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $billId]);
        $lockedBill = $lock->fetch();
        $paid = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0.00) FROM payments
             WHERE bill_id = :bill_id AND status = "paid"'
        );
        $paid->execute(['bill_id' => $billId]);
        $lockedDueCents = $lockedBill
            ? parseNonNegativePhpAmount((string) $lockedBill['amount_due'])
            : 0;
        $lockedPaidCents = min(
            $lockedDueCents,
            parseNonNegativePhpAmount((string) $paid->fetchColumn())
        );
        $pending = $pdo->prepare(
            'SELECT COUNT(*) FROM payments
             WHERE bill_id = :bill_id AND status IN ("pending", "processing")'
        );
        $pending->execute(['bill_id' => $billId]);

        if (!$lockedBill || $lockedBill['status'] === 'cancelled'
            || $lockedPaidCents > 0
            || (int) $pending->fetchColumn() > 0
        ) {
            $pdo->rollBack();
            http_response_code(409);
            exit('This bill cannot be cancelled because it has payments, an outstanding checkout, or is already cancelled.');
        }

        $pdo->prepare('UPDATE water_bills SET status = "cancelled" WHERE id = :id')->execute(['id' => $billId]);
        createBillingAudit(
            $pdo,
            (int) $_SESSION['user_id'],
            'WATER_BILL_CANCELLED',
            'Cancelled water bill ' . $bill['bill_number'] . '.'
        );
        $pdo->commit();
        redirect('bill-view.php?id=' . $billId . '&cancelled=1');
    }
}

$pageTitle = 'Water Bill Details';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card admin-panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Water bill</p>
            <h1><?= e($bill['bill_number']) ?></h1>
        </div>
        <span class="status <?= e($status) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span>
    </div>
    <?php if (isset($_GET['created'])): ?><div class="alert success">Bill created and faculty notified in CCIS Connect.</div><?php endif; ?>
    <?php if (isset($_GET['cancelled'])): ?><div class="alert success">Bill cancelled.</div><?php endif; ?>
    <?php if (isset($manualPaymentError)): ?><div class="alert error" role="alert"><?= e($manualPaymentError) ?></div><?php endif; ?>

    <dl class="detail-grid">
        <div><dt>Faculty</dt><dd><?= e($bill['first_name'] . ' ' . $bill['last_name']) ?> (<?= e($bill['employee_id']) ?>)</dd></div>
        <div><dt>Email</dt><dd><?= e($bill['email']) ?></dd></div>
        <div><dt>Billing Period</dt><dd><?= e($bill['billing_period']) ?></dd></div>
        <div><dt>Description</dt><dd><?= e($bill['description'] ?? 'Office drinking-water contribution') ?></dd></div>
        <div><dt>Bill Amount</dt><dd><?= e(formatPhpAmount((string) $bill['amount_due'])) ?></dd></div>
        <div><dt>Amount Paid</dt><dd><?= e(formatPhpCents($paidCents)) ?></dd></div>
        <div><dt>Outstanding Balance</dt><dd><?= e(formatPhpCents($balanceCents)) ?></dd></div>
        <div><dt>Due Date</dt><dd><?= e($bill['due_date']) ?></dd></div>
    </dl>

    <div class="button-row">
        <?php if ($status !== 'cancelled' && $paidCents === 0 && $payments === []): ?>
            <a class="button small" href="bill-edit.php?id=<?= (int) $billId ?>">Edit Bill</a>
        <?php endif; ?>
        <?php if ($status !== 'cancelled' && $paidCents === 0 && $payments === []): ?>
            <form method="POST" action="bill-view.php?id=<?= (int) $billId ?>" onsubmit="return confirm('Cancel this bill?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <button class="button small danger-button" type="submit">Cancel Bill</button>
            </form>
        <?php endif; ?>
        <a class="button small secondary-button" href="bills.php">Back to Bills</a>
    </div>
    <?php if ($balanceCents > 0 && $status !== 'cancelled' && $manualMethods !== []): ?>
        <h2>Record Manual Payment</h2>
        <p>Only use this form after the cashier has received and confirmed the funds. Saving verifies the payment and generates a receipt.</p>
        <form method="POST" action="bill-view.php?id=<?= (int) $billId ?>" class="manual-payment-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="record_manual_payment">
            <label for="payment_method_id">Payment Method</label>
            <select id="payment_method_id" name="payment_method_id" required>
                <option value="">Select method</option>
                <?php foreach ($manualMethods as $method): ?><option value="<?= (int) $method['id'] ?>"><?= e($method['method_name']) ?></option><?php endforeach; ?>
            </select>
            <label for="amount">Amount Received (PHP)</label>
            <input id="amount" name="amount" inputmode="decimal" max="<?= e(amountFromMinorUnits($balanceCents)) ?>" value="<?= e(amountFromMinorUnits($balanceCents)) ?>" required>
            <label for="notes">Verification Note</label>
            <textarea id="notes" name="notes" maxlength="500" rows="2"></textarea>
            <button class="button small" type="submit">Verify Manual Payment</button>
        </form>
    <?php endif; ?>
</section>

<section class="card admin-panel">
    <h2>Payment Activity</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Gateway</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><a href="payment-view.php?id=<?= (int) $payment['id'] ?>"><?= e($payment['payment_reference']) ?></a></td>
                    <td><?= e(formatPhpAmount((string) $payment['amount'])) ?></td>
                    <td><?= e($payment['payment_method'] ?? 'Not available') ?></td>
                    <td><?= e(ucfirst($payment['gateway'])) ?></td>
                    <td><?= e(ucfirst($payment['status'])) ?></td>
                    <td><?= e($payment['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($payments === []): ?><tr><td colspan="6">No payment attempts for this bill.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
