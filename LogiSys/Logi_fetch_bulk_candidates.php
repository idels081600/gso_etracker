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

    // Build query dynamically with inventory_items JOIN to get current balance
    $baseSql = "SELECT 
                    ir.id, 
                    ir.item_id, 
                    ir.office_name, 
                    ir.item_name, 
                    ir.approved_quantity, 
                    DATE(ir.date_requested) AS date, 
                    ir.status, 
                    COALESCE(ir.uploaded, 0) AS uploaded,
                    COALESCE(inv.current_balance, 0) AS current_balance
                FROM items_requested ir
                LEFT JOIN inventory_items inv ON ir.item_id = inv.item_no
                WHERE ir.status = 'Approved' AND (ir.uploaded IS NULL OR ir.uploaded = 0)";

    $conditions = [];
    $types = '';
    $params = [];

    if ($office !== '') {
        $conditions[] = 'ir.office_name = ?';
        $types .= 's';
        $params[] = $office;
    }
    if ($date !== '') {
        $conditions[] = 'DATE(ir.date_requested) = ?';
        $types .= 's';
        $params[] = $date;
    }

    if (!empty($conditions)) {
        $baseSql .= ' AND ' . implode(' AND ', $conditions);
    }

    $baseSql .= ' ORDER BY ir.id DESC';

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
            'current_balance' => (int)$row['current_balance'],
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
?>