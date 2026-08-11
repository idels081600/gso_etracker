<?php

function payables_log_error(string $message): void
{
    $logFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'payables_error.log';
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $logFile);
}

function payables_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function payables_get_csrf_token(): string
{
    if (empty($_SESSION['_payables_csrf_token'])) {
        $_SESSION['_payables_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_payables_csrf_token'];
}

function payables_csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(payables_get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function payables_verify_csrf_token(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['_payables_csrf_token'] ?? '';

    if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
        payables_json_response(['success' => false, 'error' => 'Security token expired. Please refresh and try again.'], 403);
    }
}

function payables_sanitize_amount($amount): string
{
    $amount = preg_replace('/[^\d,.]/', '', (string)$amount);

    if ($amount === '') {
        return '0.00';
    }

    $commaCount = substr_count($amount, ',');
    $periodCount = substr_count($amount, '.');

    if ($commaCount === 0 && $periodCount === 0) {
        return number_format((float)$amount, 2, '.', '');
    }

    if ($commaCount === 0 && $periodCount === 1) {
        $parts = explode('.', $amount);
        if (strlen($parts[1]) <= 2) {
            return number_format((float)$amount, 2, '.', '');
        }

        return number_format((float)str_replace('.', '', $amount), 2, '.', '');
    }

    if ($commaCount === 1 && $periodCount === 0) {
        $parts = explode(',', $amount);
        if (strlen($parts[1]) <= 2) {
            return number_format((float)str_replace(',', '.', $amount), 2, '.', '');
        }

        return number_format((float)str_replace(',', '', $amount), 2, '.', '');
    }

    $lastComma = strrpos($amount, ',');
    $lastPeriod = strrpos($amount, '.');

    if ($lastPeriod > $lastComma) {
        $decimal = substr($amount, $lastPeriod + 1);
        $integer = str_replace([',', '.'], '', substr($amount, 0, $lastPeriod));
        if (strlen($decimal) <= 2) {
            return number_format((float)($integer . '.' . $decimal), 2, '.', '');
        }
    } elseif ($lastComma > $lastPeriod) {
        $decimal = substr($amount, $lastComma + 1);
        $integer = str_replace([',', '.'], '', substr($amount, 0, $lastComma));
        if (strlen($decimal) <= 2) {
            return number_format((float)($integer . '.' . $decimal), 2, '.', '');
        }
    }

    return number_format((float)str_replace([',', '.'], '', $amount), 2, '.', '');
}

function payables_valid_date_or_empty(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function payables_normalize_date_or_empty(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }

    if (strlen($date) > 10) {
        $date = substr($date, 0, 10);
    }

    return payables_valid_date_or_empty($date) ? $date : '';
}

function payables_current_datetime(): string
{
    if (!date_default_timezone_get() || date_default_timezone_get() !== 'Asia/Manila') {
        date_default_timezone_set('Asia/Manila');
    }

    return date('Y-m-d H:i:s');
}

function payables_post_date_or_now(string $field): string
{
    $date = payables_normalize_date_or_empty((string)($_POST[$field] ?? ''));
    return $date !== '' ? $date . ' 00:00:00' : payables_current_datetime();
}

function payables_post_nullable_date(string $field): ?string
{
    $date = payables_normalize_date_or_empty((string)($_POST[$field] ?? ''));
    return $date !== '' ? $date : null;
}

function payables_ensure_date_column(mysqli $conn, string $table, string $column): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        payables_log_error('Invalid table or column requested for date column check.');
        return;
    }

    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        payables_log_error('Unable to inspect column ' . $table . '.' . $column . ': ' . $conn->error);
        return;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result();
    $hasColumn = $exists && $exists->num_rows > 0;
    $stmt->close();

    if (!$hasColumn && !$conn->query("ALTER TABLE {$table} ADD COLUMN {$column} DATE NULL")) {
        payables_log_error('Unable to add column ' . $table . '.' . $column . ': ' . $conn->error);
    }
}

function payables_ensure_text_column(mysqli $conn, string $table, string $column): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        payables_log_error('Invalid table or column requested for text column check.');
        return;
    }

    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        payables_log_error('Unable to inspect column ' . $table . '.' . $column . ': ' . $conn->error);
        return;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result();
    $hasColumn = $exists && $exists->num_rows > 0;
    $stmt->close();

    if (!$hasColumn && !$conn->query("ALTER TABLE {$table} ADD COLUMN {$column} TEXT NULL")) {
        payables_log_error('Unable to add column ' . $table . '.' . $column . ': ' . $conn->error);
    }
}

function payables_split_comma_values(?string $value): array
{
    $parts = preg_split('/\s*,\s*/', trim((string)$value));
    if (!$parts) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $parts), static fn ($part) => $part !== ''));
}

function payables_calculate_deadline(string $noticeProceed, string $calendarDays): array
{
    $noticeProceed = payables_normalize_date_or_empty($noticeProceed);
    $calendarDays = trim($calendarDays);

    if ($calendarDays === '') {
        return ['', ''];
    }

    if (ctype_digit($calendarDays)) {
        $days = max(1, (int)$calendarDays);
        if ($noticeProceed === '') {
            return [$calendarDays, ''];
        }

        return [$calendarDays, date('Y-m-d', strtotime($noticeProceed . ' + ' . ($days - 1) . ' days'))];
    }

    $deadlineDate = payables_normalize_date_or_empty($calendarDays);
    return [$calendarDays, $deadlineDate];
}

function payables_require_post_fields(array $fields): array
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($_POST[$field])) {
            $missing[] = $field;
        }
    }

    return $missing;
}
