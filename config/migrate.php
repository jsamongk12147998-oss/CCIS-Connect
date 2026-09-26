<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/database.php';

function migrationHasColumn(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $statement->execute(['table' => $table, 'column' => $column]);
    return (int) $statement->fetchColumn() > 0;
}

function migrationHasIndex(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index'
    );
    $statement->execute(['table' => $table, 'index' => $index]);
    return (int) $statement->fetchColumn() > 0;
}

function migrationHasForeignKey(PDO $pdo, string $table, string $constraint): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND CONSTRAINT_NAME = :constraint
           AND CONSTRAINT_TYPE = :type'
    );
    $statement->execute([
        'table' => $table,
        'constraint' => $constraint,
        'type' => 'FOREIGN KEY',
    ]);
    return (int) $statement->fetchColumn() > 0;
}

function migrationAddColumns(PDO $pdo, string $table, array $columns): void
{
    foreach ($columns as $column => $definition) {
        if (!migrationHasColumn($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

function migrationAddIndex(PDO $pdo, string $table, string $index, string $definition): void
{
    if (!migrationHasIndex($pdo, $table, $index)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD {$definition}");
    }
}

function migrationAddForeignKey(
    PDO $pdo,
    string $table,
    string $constraint,
    string $definition
): void {
    if (!migrationHasForeignKey($pdo, $table, $constraint)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` {$definition}");
    }
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(100) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$migrationName = '20260926_billing_payments';
$checkMigration = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = :migration');
$checkMigration->execute(['migration' => $migrationName]);

if ($checkMigration->fetchColumn()) {
    fwrite(STDOUT, "Billing migration already applied.\n");
    exit(0);
}

if ((int) $pdo->query("SELECT COUNT(*) FROM water_bills")->fetchColumn() > 0
    && (int) $pdo->query(
        'SELECT COUNT(*)
         FROM water_bills wb
         LEFT JOIN water_accounts wa ON wa.id = wb.water_account_id
         WHERE wa.id IS NULL'
    )->fetchColumn() > 0
) {
    throw new RuntimeException('Cannot migrate water bills with a missing water account.');
}

$pdo->exec(
    "ALTER TABLE water_bills
     MODIFY status ENUM(
         'unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled',
         'pending_verification', 'rejected'
     ) NOT NULL DEFAULT 'unpaid'"
);

migrationAddColumns($pdo, 'water_bills', [
    'bill_number' => 'VARCHAR(50) NULL',
    'faculty_id' => 'INT UNSIGNED NULL',
    'description' => 'VARCHAR(255) NULL',
    'amount_due' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'amount_paid' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'balance_due' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
]);

$pdo->exec(
    "UPDATE water_bills wb
     INNER JOIN water_accounts wa ON wa.id = wb.water_account_id
     SET wb.faculty_id = wa.user_id,
         wb.amount_due = wb.total_amount,
         wb.amount_paid = LEAST(
             wb.total_amount,
             COALESCE((
                 SELECT SUM(p.amount_paid)
                 FROM payments p
                 WHERE p.bill_id = wb.id AND p.payment_status = 'verified'
             ), 0.00)
         ),
         wb.bill_number = COALESCE(
             wb.bill_number,
             CONCAT('WB-', YEAR(wb.created_at), '-', LPAD(wb.id, 6, '0'))
         )"
);
$pdo->exec(
    "UPDATE water_bills
     SET balance_due = GREATEST(amount_due - amount_paid, 0.00),
         status = CASE
             WHEN status = 'cancelled' THEN 'cancelled'
             WHEN amount_paid >= amount_due AND amount_due > 0 THEN 'paid'
             WHEN amount_paid > 0 THEN 'partially_paid'
             WHEN due_date < CURRENT_DATE THEN 'overdue'
             ELSE 'unpaid'
         END"
);

migrationAddIndex($pdo, 'water_bills', 'uq_water_bills_bill_number', 'UNIQUE INDEX `uq_water_bills_bill_number` (`bill_number`)');
migrationAddIndex($pdo, 'water_bills', 'uq_water_bills_faculty_period', 'UNIQUE INDEX `uq_water_bills_faculty_period` (`faculty_id`, `billing_period`)');
migrationAddIndex($pdo, 'water_bills', 'idx_water_bills_faculty_due_date', 'INDEX `idx_water_bills_faculty_due_date` (`faculty_id`, `due_date`)');
migrationAddForeignKey(
    $pdo,
    'water_bills',
    'fk_water_bill_faculty',
    'FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT'
);

$pdo->exec(
    "ALTER TABLE payments
     MODIFY payment_status ENUM('pending', 'verified', 'rejected', 'processing', 'paid', 'failed', 'cancelled', 'refunded')
     NOT NULL DEFAULT 'pending'"
);

migrationAddColumns($pdo, 'payments', [
    'payment_reference' => 'VARCHAR(64) NULL',
    'faculty_id' => 'INT UNSIGNED NULL',
    'amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'currency' => "CHAR(3) NOT NULL DEFAULT 'PHP'",
    'gateway' => "VARCHAR(30) NOT NULL DEFAULT 'manual'",
    'gateway_checkout_id' => 'VARCHAR(100) NULL',
    'gateway_checkout_url' => 'TEXT NULL',
    'gateway_payment_id' => 'VARCHAR(100) NULL',
    'gateway_transaction_id' => 'VARCHAR(100) NULL',
    'payment_method' => 'VARCHAR(100) NULL',
    'status' => "ENUM('pending', 'processing', 'paid', 'failed', 'rejected', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending'",
    'gateway_status' => 'VARCHAR(50) NULL',
    'verification_status' => "ENUM('pending', 'verified', 'failed', 'manual_review') NOT NULL DEFAULT 'pending'",
    'paid_at' => 'DATETIME NULL',
    'created_at' => 'DATETIME NULL',
    'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
]);

$pdo->exec(
    "UPDATE payments p
     LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
     SET p.faculty_id = p.user_id,
         p.amount = p.amount_paid,
         p.payment_reference = COALESCE(p.payment_reference, p.transaction_reference, CONCAT('LEGACY-', p.id)),
         p.payment_method = COALESCE(p.payment_method, pm.method_name, 'Manual'),
         p.status = CASE
             WHEN p.payment_status = 'verified' THEN 'paid'
             WHEN p.payment_status = 'rejected' THEN 'rejected'
             ELSE 'pending'
         END,
         p.verification_status = CASE
             WHEN p.payment_status = 'verified' THEN 'verified'
             WHEN p.payment_status = 'rejected' THEN 'failed'
             ELSE 'pending'
         END,
         p.created_at = COALESCE(p.created_at, p.payment_date),
         p.paid_at = CASE
             WHEN p.payment_status = 'verified' THEN p.verified_at
             ELSE NULL
         END"
);
$pdo->exec('ALTER TABLE payments MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

migrationAddIndex($pdo, 'payments', 'uq_payments_payment_reference', 'UNIQUE INDEX `uq_payments_payment_reference` (`payment_reference`)');
migrationAddIndex($pdo, 'payments', 'uq_payments_gateway_checkout', 'UNIQUE INDEX `uq_payments_gateway_checkout` (`gateway_checkout_id`)');
migrationAddIndex($pdo, 'payments', 'uq_payments_gateway_payment', 'UNIQUE INDEX `uq_payments_gateway_payment` (`gateway_payment_id`)');
migrationAddIndex($pdo, 'payments', 'uq_payments_gateway_transaction', 'UNIQUE INDEX `uq_payments_gateway_transaction` (`gateway_transaction_id`)');
migrationAddIndex($pdo, 'payments', 'idx_payments_bill_status', 'INDEX `idx_payments_bill_status` (`bill_id`, `status`)');
migrationAddIndex($pdo, 'payments', 'idx_payments_faculty_created', 'INDEX `idx_payments_faculty_created` (`faculty_id`, `created_at`)');
migrationAddForeignKey(
    $pdo,
    'payments',
    'fk_payment_faculty',
    'FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT'
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS payment_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        gateway_event_id VARCHAR(150) NOT NULL UNIQUE,
        event_type VARCHAR(100) NOT NULL,
        payment_id INT UNSIGNED DEFAULT NULL,
        gateway VARCHAR(30) NOT NULL,
        processing_status ENUM('received', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'received',
        payload LONGTEXT NOT NULL,
        received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        INDEX idx_payment_events_payment (payment_id, received_at),
        CONSTRAINT fk_payment_event_payment
            FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
    ) ENGINE=InnoDB"
);

migrationAddColumns($pdo, 'receipts', [
    'faculty_id' => 'INT UNSIGNED NULL',
    'bill_id' => 'INT UNSIGNED NULL',
    'amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'gateway' => 'VARCHAR(30) NULL',
    'gateway_transaction_id' => 'VARCHAR(100) NULL',
]);
$pdo->exec(
    "UPDATE receipts r
     INNER JOIN payments p ON p.id = r.payment_id
     SET r.faculty_id = p.faculty_id,
         r.bill_id = p.bill_id,
         r.amount = p.amount,
         r.gateway = p.gateway,
         r.gateway_transaction_id = p.gateway_transaction_id"
);
migrationAddIndex($pdo, 'receipts', 'idx_receipts_faculty', 'INDEX `idx_receipts_faculty` (`faculty_id`)');

migrationAddIndex($pdo, 'notifications', 'idx_notifications_user_created', 'INDEX `idx_notifications_user_created` (`user_id`, `created_at`)');

$pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)')
    ->execute(['migration' => $migrationName]);

fwrite(STDOUT, "Billing schema migration applied successfully.\n");
