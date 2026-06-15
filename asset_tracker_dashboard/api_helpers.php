<?php

function api_response(bool $success, string $message, array $data = [], int $status = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);

    echo json_encode([
        'success' => $success,
        'status' => $success ? 'success' : 'error',
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_database_error(Throwable $error): never
{
    error_log($error->getMessage());
    api_response(false, 'The request could not be completed. Please try again.', [], 500);
}

function db_execute(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('Database statement preparation failed: ' . mysqli_error($conn));
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $message = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Database statement execution failed: ' . $message);
    }

    return $stmt;
}

function db_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = db_execute($conn, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

