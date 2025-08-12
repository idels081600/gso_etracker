<?php
session_start();
header('Content-Type: application/json');

require_once 'logi_db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $office = isset($_POST['office']) ? trim($_POST['office']) : '';
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';

    // Validate date if provided (expecting YYYY-MM-DD)
    if ($date !== '' && DateTime::createFromFormat('Y-m-d', $date) === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.']);
        exit;
    }

    // Build query dynamically based on filters (only un-uploaded approved requests)
    $baseSql = "SELECT id, item_id, office_name, item_name, approved_quantity, DATE(date_requested) AS date, status, COALESCE(uploaded, 0) AS uploaded
                FROM items_requested
                WHERE status = 'Approved' AND (uploaded IS NULL OR uploaded = 0)";

    $conditions = [];
    $types = '';
    $params = [];

    if ($office !== '') {
        $conditions[] = 'office_name = ?';
        $types .= 's';
        $params[] = $office;
    }
    if ($date !== '') {
        $conditions[] = 'DATE(date_requested) = ?';
        $types .= 's';
        $params[] = $date;
    }

    if (!empty($conditions)) {
        $baseSql .= ' AND ' . implode(' AND ', $conditions);
    }

    $baseSql .= ' ORDER BY id DESC';

    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $baseSql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to execute query: ' . mysqli_stmt_error($stmt));
        }
        $result = mysqli_stmt_get_result($stmt);
    } else {
        // No params -> simple query
        $result = mysqli_query($conn, $baseSql);
        if (!$result) {
            throw new Exception('Query failed: ' . mysqli_error($conn));
        }
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'uploaded' => (int)$row['uploaded'],
            'id' => (int)$row['id'],
            'item_id' => $row['item_id'],
            'office_name' => $row['office_name'],
            'item_name' => $row['item_name'],
            'approved_quantity' => (int)$row['approved_quantity'],
            'date' => $row['date'],
            'status' => $row['status'],
        ];
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}


