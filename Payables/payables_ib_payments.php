<?php

function payables_ensure_ib_payments_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS payables_ib_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bac_monitoring_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_date DATE NOT NULL,
        dv_number VARCHAR(50) NULL,
        created_by VARCHAR(150) NULL,
        updated_by VARCHAR(150) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ib_payments_record_date (bac_monitoring_id, payment_date, id),
        CONSTRAINT fk_ib_payments_bac_monitoring
            FOREIGN KEY (bac_monitoring_id) REFERENCES bac_monitoring(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to initialize partial-payment storage.');
    }

    $hasDvColumn = false;
    $columnCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payables_ib_payments' AND COLUMN_NAME = 'dv_number' LIMIT 1");
    if ($columnCheck && $columnCheck->num_rows > 0) {
        $hasDvColumn = true;
    }
    $columnCheck->close();

    if (!$hasDvColumn && !$conn->query("ALTER TABLE payables_ib_payments ADD COLUMN dv_number VARCHAR(50) NULL")) {
        throw new RuntimeException('Unable to add dv_number column to payables_ib_payments.');
    }
}

function payables_amount_to_cents($amount): int
{
    $rawAmount = trim((string)$amount);
    $cents = (int)round((float)payables_sanitize_amount($rawAmount) * 100);
    return preg_match('/^-/', $rawAmount) ? -abs($cents) : $cents;
}

function payables_cents_to_decimal(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function payables_validate_payment_date(string $paymentDate): ?string
{
    $paymentDate = trim($paymentDate);
    $timezone = new DateTimeZone('Asia/Manila');
    $parsed = DateTime::createFromFormat('!Y-m-d', $paymentDate, $timezone);
    if (!$parsed || $parsed->format('Y-m-d') !== $paymentDate) {
        return 'Payment date must be a valid date.';
    }

    $today = new DateTime('today', $timezone);
    if ($parsed > $today) {
        return 'Payment date cannot be in the future.';
    }

    return null;
}

function payables_ib_total_paid_cents(mysqli $conn, int $recordId, int $excludePaymentId = 0): int
{
    $sql = 'SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payables_ib_payments WHERE bac_monitoring_id = ?';
    if ($excludePaymentId > 0) {
        $sql .= ' AND id <> ?';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to calculate the payment total.');
    }

    if ($excludePaymentId > 0) {
        $stmt->bind_param('ii', $recordId, $excludePaymentId);
    } else {
        $stmt->bind_param('i', $recordId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return payables_amount_to_cents($row['total_paid'] ?? 0);
}

function payables_lock_ib_payment_record(mysqli $conn, int $recordId): array
{
    $stmt = $conn->prepare('SELECT id, final_amount FROM bac_monitoring WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
        throw new RuntimeException('Unable to verify the IB record.');
    }

    $stmt->bind_param('i', $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$record) {
        throw new InvalidArgumentException('The selected IB record was not found.');
    }

    return $record;
}

function payables_validate_payment_amount(int $amountCents, int $finalAmountCents, int $otherPaidCents): void
{
    if ($finalAmountCents <= 0) {
        throw new InvalidArgumentException('Set a positive Final Amount before recording a payment.');
    }
    if ($amountCents <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }

    $availableCents = max(0, $finalAmountCents - $otherPaidCents);
    if ($amountCents > $availableCents) {
        throw new InvalidArgumentException(
            'Payment exceeds the remaining balance of PHP ' . number_format($availableCents / 100, 2) . '.'
        );
    }
}

function payables_add_ib_payment(mysqli $conn, int $recordId, string $amount, string $paymentDate, string $user, string $dvNumber = ''): void
{
    if ($dateError = payables_validate_payment_date($paymentDate)) {
        throw new InvalidArgumentException($dateError);
    }

    $amountCents = payables_amount_to_cents($amount);
    $conn->begin_transaction();
    try {
        $record = payables_lock_ib_payment_record($conn, $recordId);
        $finalAmountCents = payables_amount_to_cents($record['final_amount'] ?? 0);
        $totalPaidCents = payables_ib_total_paid_cents($conn, $recordId);
        payables_validate_payment_amount($amountCents, $finalAmountCents, $totalPaidCents);

        $decimalAmount = payables_cents_to_decimal($amountCents);
        $stmt = $conn->prepare('INSERT INTO payables_ib_payments (bac_monitoring_id, amount, payment_date, dv_number, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the partial payment.');
        }
        $stmt->bind_param('idssss', $recordId, $decimalAmount, $paymentDate, $dvNumber, $user, $user);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to save the partial payment.');
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function payables_edit_ib_payment(mysqli $conn, int $recordId, int $paymentId, string $amount, string $paymentDate, string $user, string $dvNumber = ''): void
{
    if ($dateError = payables_validate_payment_date($paymentDate)) {
        throw new InvalidArgumentException($dateError);
    }

    $amountCents = payables_amount_to_cents($amount);
    $conn->begin_transaction();
    try {
        $record = payables_lock_ib_payment_record($conn, $recordId);
        $paymentStmt = $conn->prepare('SELECT id FROM payables_ib_payments WHERE id = ? AND bac_monitoring_id = ? LIMIT 1 FOR UPDATE');
        if (!$paymentStmt) {
            throw new RuntimeException('Unable to verify the partial payment.');
        }
        $paymentStmt->bind_param('ii', $paymentId, $recordId);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $paymentExists = $paymentResult && $paymentResult->num_rows === 1;
        $paymentStmt->close();
        if (!$paymentExists) {
            throw new InvalidArgumentException('The selected partial payment was not found.');
        }

        $finalAmountCents = payables_amount_to_cents($record['final_amount'] ?? 0);
        $otherPaidCents = payables_ib_total_paid_cents($conn, $recordId, $paymentId);
        payables_validate_payment_amount($amountCents, $finalAmountCents, $otherPaidCents);

        $decimalAmount = payables_cents_to_decimal($amountCents);
        $stmt = $conn->prepare('UPDATE payables_ib_payments SET amount = ?, payment_date = ?, dv_number = ?, updated_by = ? WHERE id = ? AND bac_monitoring_id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the payment update.');
        }
        $stmt->bind_param('dsssii', $decimalAmount, $paymentDate, $dvNumber, $user, $paymentId, $recordId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update the partial payment.');
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function payables_delete_ib_payment(mysqli $conn, int $recordId, int $paymentId): void
{
    $conn->begin_transaction();
    try {
        payables_lock_ib_payment_record($conn, $recordId);
        $stmt = $conn->prepare('DELETE FROM payables_ib_payments WHERE id = ? AND bac_monitoring_id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the payment deletion.');
        }
        $stmt->bind_param('ii', $paymentId, $recordId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new InvalidArgumentException('The selected partial payment was not found.');
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function payables_get_ib_payment_map(mysqli $conn, array $recordIds): array
{
    $recordIds = array_values(array_unique(array_filter(array_map('intval', $recordIds))));
    if (!$recordIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
    $types = str_repeat('i', count($recordIds));
    $stmt = $conn->prepare("SELECT id, bac_monitoring_id, amount, payment_date, dv_number, created_by, updated_by, created_at, updated_at FROM payables_ib_payments WHERE bac_monitoring_id IN ({$placeholders}) ORDER BY payment_date DESC, id DESC");
    if (!$stmt) {
        throw new RuntimeException('Unable to load partial-payment history.');
    }

    $stmt->bind_param($types, ...$recordIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    while ($row = $result ? $result->fetch_assoc() : null) {
        $recordId = (int)$row['bac_monitoring_id'];
        $map[$recordId][] = $row;
    }
    $stmt->close();

    return $map;
}
