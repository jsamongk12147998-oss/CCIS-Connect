<?php

declare(strict_types=1);

function parsePhpAmount(string $amount): int
{
    if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/', trim($amount), $matches)) {
        throw new InvalidArgumentException('Enter a valid amount with up to two decimal places.');
    }

    $minorUnits = ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    if ($minorUnits <= 0) {
        throw new InvalidArgumentException('The amount must be greater than zero.');
    }

    return $minorUnits;
}

function formatPhpAmount(string|int $amount): string
{
    $value = (string) $amount;
    if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/', $value, $matches)) {
        throw new InvalidArgumentException('Cannot display an invalid monetary amount.');
    }

    return 'PHP ' . number_format((int) $matches[1], 0, '.', ',')
        . '.' . str_pad($matches[2] ?? '', 2, '0');
}

function amountFromMinorUnits(int $minorUnits): string
{
    if ($minorUnits < 0) {
        throw new InvalidArgumentException('A monetary amount cannot be negative.');
    }

    return intdiv($minorUnits, 100) . '.' . str_pad((string) ($minorUnits % 100), 2, '0', STR_PAD_LEFT);
}

function formatPhpCents(int $minorUnits): string
{
    return formatPhpAmount(amountFromMinorUnits($minorUnits));
}

function billStatus(array $bill): string
{
    if ($bill['status'] === 'cancelled') {
        return 'cancelled';
    }

    $amountDue = parsePhpAmount((string) $bill['amount_due']);
    $amountPaid = parseNonNegativePhpAmount((string) ($bill['amount_paid'] ?? '0.00'));

    if ($amountPaid >= $amountDue) {
        return 'paid';
    }
    if ($bill['due_date'] < date('Y-m-d')) {
        return 'overdue';
    }
    if ($amountPaid > 0) {
        return 'partially_paid';
    }

    return 'unpaid';
}

function parseNonNegativePhpAmount(string $amount): int
{
    if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/', $amount, $matches)) {
        throw new InvalidArgumentException('The stored monetary amount is invalid.');
    }

    return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
}

function billBalancesQuery(): string
{
    return 'SELECT wb.id, wb.bill_number, wb.faculty_id, wb.billing_period, wb.description,
                   wb.amount_due, wb.due_date, wb.status, wb.created_at,
                   u.employee_id, u.first_name, u.last_name, u.email,
                   COALESCE(p.amount_paid, 0.00) AS amount_paid
            FROM water_bills wb
            JOIN users u ON u.id = wb.faculty_id
            LEFT JOIN (
                SELECT bill_id, SUM(amount) AS amount_paid
                FROM payments WHERE status = "paid" GROUP BY bill_id
            ) p ON p.bill_id = wb.id';
}

function createBillNotification(PDO $pdo, int $userId, string $billNumber, string $period): void
{
    $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, notification_type)
         VALUES (:user_id, :title, :message, :type)'
    )->execute([
        'user_id' => $userId,
        'title' => 'New water bill',
        'message' => "Water bill {$billNumber} for {$period} is available.",
        'type' => 'payment',
    ]);
}

function createBillingAudit(PDO $pdo, int $userId, string $action, string $description): void
{
    $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, description, ip_address)
         VALUES (:user_id, :action, :description, :ip_address)'
    )->execute([
        'user_id' => $userId,
        'action' => $action,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function recordManualBillPayment(
    PDO $pdo,
    int $billId,
    int $actorUserId,
    int $paymentMethodId,
    int $amountCents,
    string $notes
): int {
    if ($amountCents <= 0 || mb_strlen($notes) > 500) {
        throw new InvalidArgumentException('Enter a valid payment amount and a note no longer than 500 characters.');
    }

    $billStatement = $pdo->prepare(
        'SELECT wb.id, wb.bill_number, wb.billing_period, wb.faculty_id,
                wb.amount_due, wb.due_date, wb.status,
                u.first_name, u.last_name
         FROM water_bills wb
         JOIN users u ON u.id = wb.faculty_id
         WHERE wb.id = :id
         LIMIT 1 FOR UPDATE'
    );
    $billStatement->execute(['id' => $billId]);
    $bill = $billStatement->fetch();
    if (!$bill || $bill['status'] === 'cancelled') {
        throw new RuntimeException('The bill does not exist or has been cancelled.');
    }

    $pending = $pdo->prepare(
        'SELECT COUNT(*) FROM payments
         WHERE bill_id = :bill_id AND status IN ("pending", "processing")'
    );
    $pending->execute(['bill_id' => $billId]);
    if ((int) $pending->fetchColumn() > 0) {
        throw new RuntimeException('A payment-provider checkout is still pending. Reconcile it before recording a manual payment.');
    }

    $paidStatement = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0.00)
         FROM payments WHERE bill_id = :bill_id AND status = "paid"'
    );
    $paidStatement->execute(['bill_id' => $billId]);
    $amountDueCents = parseNonNegativePhpAmount((string) $bill['amount_due']);
    $amountPaidCents = min($amountDueCents, parseNonNegativePhpAmount((string) $paidStatement->fetchColumn()));
    $balanceCents = max(0, $amountDueCents - $amountPaidCents);
    if ($amountCents > $balanceCents) {
        throw new RuntimeException('The payment exceeds the outstanding bill balance.');
    }

    $methodStatement = $pdo->prepare(
        'SELECT method_name FROM payment_methods
         WHERE id = :id AND is_active = 1 LIMIT 1'
    );
    $methodStatement->execute(['id' => $paymentMethodId]);
    $methodName = $methodStatement->fetchColumn();
    if (!is_string($methodName) || !in_array($methodName, ['Cash', 'Bank Transfer', 'E-Wallet'], true)) {
        throw new RuntimeException('Select an enabled manual payment method.');
    }

    $amount = amountFromMinorUnits($amountCents);
    $reference = 'CCIS-MANUAL-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
    $pdo->prepare(
        'INSERT INTO payments
            (bill_id, user_id, payment_method_id, amount_paid, transaction_reference,
             payment_status, remarks, verified_by, payment_date, verified_at,
             payment_reference, faculty_id, amount, currency, gateway, payment_method,
             status, verification_status, gateway_status, paid_at)
         VALUES
            (:bill_id, :user_id, :method_id, :amount_paid, :reference,
             "verified", :remarks, :verified_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP,
             :payment_reference, :faculty_id, :amount, "PHP", "manual", :payment_method,
             "paid", "verified", "manually_verified", CURRENT_TIMESTAMP)'
    )->execute([
        'bill_id' => $billId,
        'user_id' => $bill['faculty_id'],
        'method_id' => $paymentMethodId,
        'amount_paid' => $amount,
        'reference' => $reference,
        'remarks' => $notes !== '' ? $notes : 'Payment received and verified by administrator.',
        'verified_by' => $actorUserId,
        'payment_reference' => $reference,
        'faculty_id' => $bill['faculty_id'],
        'amount' => $amount,
        'payment_method' => $methodName,
    ]);

    $paymentId = (int) $pdo->lastInsertId();
    $receiptNumber = 'CCIS-R-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
    $newPaidCents = $amountPaidCents + $amountCents;
    $newBalanceCents = max(0, $amountDueCents - $newPaidCents);
    $newStatus = $newBalanceCents === 0
        ? 'paid'
        : ($bill['due_date'] < date('Y-m-d') ? 'overdue' : 'partially_paid');

    $pdo->prepare(
        'UPDATE water_bills
         SET amount_paid = :amount_paid, balance_due = :balance_due, status = :status
         WHERE id = :id'
    )->execute([
        'amount_paid' => amountFromMinorUnits($newPaidCents),
        'balance_due' => amountFromMinorUnits($newBalanceCents),
        'status' => $newStatus,
        'id' => $billId,
    ]);

    $pdo->prepare(
        'INSERT INTO receipts
            (payment_id, receipt_number, faculty_id, bill_id, amount, gateway, gateway_transaction_id)
         VALUES
            (:payment_id, :receipt_number, :faculty_id, :bill_id, :amount, :gateway, :transaction_id)'
    )->execute([
        'payment_id' => $paymentId,
        'receipt_number' => $receiptNumber,
        'faculty_id' => $bill['faculty_id'],
        'bill_id' => $billId,
        'amount' => $amount,
        'gateway' => 'manual',
        'transaction_id' => $reference,
    ]);

    $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, notification_type)
         VALUES (:user_id, :title, :message, :type)'
    )->execute([
        'user_id' => $bill['faculty_id'],
        'title' => 'Water bill payment verified',
        'message' => "Payment {$reference} for bill {$bill['bill_number']} was verified. Receipt: {$receiptNumber}.",
        'type' => 'payment',
    ]);

    createBillingAudit(
        $pdo,
        $actorUserId,
        'MANUAL_PAYMENT_VERIFIED',
        "Verified {$methodName} payment {$reference} for {$bill['bill_number']} amount {$amount} PHP."
    );

    return $paymentId;
}
