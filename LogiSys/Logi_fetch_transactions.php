<?php
session_start();
header('Content-Type: application/json');

require_once 'logi_db.php';

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(10, min((int)($_GET['per_page'] ?? 25), 100));
    $offset = ($page - 1) * $perPage;
    $search = trim((string)($_GET['search'] ?? ''));
    $type = strtoupper(trim((string)($_GET['type'] ?? '')));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $where = [];
    $types = '';
    $params = [];

    if ($search !== '') {
        $where[] = "(item_name LIKE ? OR item_no LIKE ? OR reason LIKE ? OR requestor LIKE ? OR transaction_type LIKE ? OR DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') LIKE ?)";
        $like = '%' . $search . '%';
        for ($i = 0; $i < 6; $i++) {
            $types .= 's';
            $params[] = $like;
        }
    }

    if ($type === 'ADDITION' || $type === 'DEDUCTION') {
        $where[] = "UPPER(transaction_type) = ?";
        $types .= 's';
        $params[] = $type;
    }

    if ($dateFrom !== '') {
        $from = DateTime::createFromFormat('Y-m-d', $dateFrom);
        if (!$from || $from->format('Y-m-d') !== $dateFrom) {
            throw new Exception('Invalid from date.');
        }
        $where[] = "created_at >= ?";
        $types .= 's';
        $params[] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo !== '') {
        $to = DateTime::createFromFormat('Y-m-d', $dateTo);
        if (!$to || $to->format('Y-m-d') !== $dateTo) {
            throw new Exception('Invalid to date.');
        }
        $where[] = "created_at < ?";
        $types .= 's';
        $params[] = $to->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) AS total FROM inventory_transactions $whereSql";
    $countStmt = mysqli_prepare($conn, $countSql);
    if (!$countStmt) {
        throw new Exception('Failed to prepare count query: ' . mysqli_error($conn));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $total = (int)(mysqli_fetch_assoc($countResult)['total'] ?? 0);

    $dataSql = "SELECT id, item_name, item_no, quantity, previous_balance, new_balance, reason, requestor, transaction_type, created_at
                FROM inventory_transactions
                $whereSql
                ORDER BY id DESC
                LIMIT ? OFFSET ?";
    $dataStmt = mysqli_prepare($conn, $dataSql);
    if (!$dataStmt) {
        throw new Exception('Failed to prepare data query: ' . mysqli_error($conn));
    }

    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$perPage, $offset]);
    mysqli_stmt_bind_param($dataStmt, $dataTypes, ...$dataParams);
    mysqli_stmt_execute($dataStmt);
    $result = mysqli_stmt_get_result($dataStmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'id' => (int)$row['id'],
            'item_name' => $row['item_name'] ?? '',
            'item_no' => $row['item_no'] ?? '',
            'quantity' => (int)($row['quantity'] ?? 0),
            'previous_balance' => (int)($row['previous_balance'] ?? 0),
            'new_balance' => (int)($row['new_balance'] ?? 0),
            'reason' => $row['reason'] ?? '',
            'requestor' => $row['requestor'] ?? '',
            'transaction_type' => $row['transaction_type'] ?? '',
            'created_at' => $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : '',
        ];
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
