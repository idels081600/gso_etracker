<?php
session_start();
header('Content-Type: application/json');

require_once 'logi_db.php';

function has_column(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) AS count
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return ((int)(mysqli_fetch_assoc($result)['count'] ?? 0)) > 0;
}

function inventory_status(array $row, bool $hasThreshold): array
{
    $balance = (int)($row['current_balance'] ?? 0);
    $threshold = $hasThreshold ? (int)($row['low_stock_threshold'] ?? 10) : 10;
    if ($threshold <= 0) {
        $threshold = 10;
    }

    if ($balance <= 0) {
        return ['label' => 'Out of Stock', 'class' => 'bg-danger'];
    }
    if ($balance <= $threshold) {
        return ['label' => 'Low Stock', 'class' => 'bg-warning'];
    }
    return ['label' => 'Available', 'class' => 'bg-success'];
}

function expiry_status(?string $date): array
{
    if (!$date || $date === '0000-00-00') {
        return ['label' => 'No Expiry', 'class' => 'bg-secondary'];
    }

    $today = new DateTime('today');
    $expiry = DateTime::createFromFormat('Y-m-d', $date);
    if (!$expiry) {
        return ['label' => 'No Expiry', 'class' => 'bg-secondary'];
    }

    $days = (int)$today->diff($expiry)->format('%r%a');
    if ($days < 0) {
        return ['label' => 'Expired', 'class' => 'bg-danger'];
    }
    if ($days <= 7) {
        return ['label' => 'Expiring Soon', 'class' => 'bg-danger'];
    }
    if ($days <= 30) {
        return ['label' => 'Near Expiry', 'class' => 'bg-warning'];
    }
    if ($days <= 90) {
        return ['label' => 'Good', 'class' => 'bg-info'];
    }
    return ['label' => 'Valid', 'class' => 'bg-success'];
}

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(10, min((int)($_GET['per_page'] ?? 25), 100));
    $offset = ($page - 1) * $perPage;
    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $hasThreshold = has_column($conn, 'inventory_items', 'low_stock_threshold');

    $where = [];
    $types = '';
    $params = [];

    if ($search !== '') {
        $where[] = "(item_no LIKE ? OR rack_no LIKE ? OR item_name LIKE ? OR unit LIKE ? OR description LIKE ?)";
        $like = '%' . $search . '%';
        for ($i = 0; $i < 5; $i++) {
            $types .= 's';
            $params[] = $like;
        }
    }

    if ($status === 'out') {
        $where[] = "current_balance <= 0";
    } elseif ($status === 'low') {
        $thresholdSql = $hasThreshold ? 'COALESCE(NULLIF(low_stock_threshold, 0), 10)' : '10';
        $where[] = "current_balance > 0 AND current_balance <= $thresholdSql";
    } elseif ($status === 'available') {
        $thresholdSql = $hasThreshold ? 'COALESCE(NULLIF(low_stock_threshold, 0), 10)' : '10';
        $where[] = "current_balance > $thresholdSql";
    } elseif ($status === 'expiring') {
        $where[] = "expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date >= CURDATE() AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 31 DAY)";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) AS total FROM inventory_items $whereSql";
    $countStmt = mysqli_prepare($conn, $countSql);
    if (!$countStmt) {
        throw new Exception('Failed to prepare inventory count query: ' . mysqli_error($conn));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);

    $thresholdSelect = $hasThreshold ? ', low_stock_threshold' : '';
    $dataSql = "SELECT id, item_no, rack_no, item_name, unit, current_balance, expiry_date, expiry_status, status, description $thresholdSelect
                FROM inventory_items
                $whereSql
                ORDER BY id DESC
                LIMIT ? OFFSET ?";
    $dataStmt = mysqli_prepare($conn, $dataSql);
    if (!$dataStmt) {
        throw new Exception('Failed to prepare inventory data query: ' . mysqli_error($conn));
    }
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$perPage, $offset]);
    mysqli_stmt_bind_param($dataStmt, $dataTypes, ...$dataParams);
    mysqli_stmt_execute($dataStmt);
    $result = mysqli_stmt_get_result($dataStmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $itemStatus = inventory_status($row, $hasThreshold);
        $expiry = expiry_status($row['expiry_date'] ?? null);
        $rows[] = [
            'id' => (int)$row['id'],
            'item_no' => $row['item_no'] ?? '',
            'rack_no' => $row['rack_no'] ?? '',
            'item_name' => $row['item_name'] ?? '',
            'unit' => $row['unit'] ?? '',
            'current_balance' => (int)($row['current_balance'] ?? 0),
            'expiry_date' => $row['expiry_date'] ?? '',
            'expiry_label' => $expiry['label'],
            'expiry_class' => $expiry['class'],
            'status_label' => $itemStatus['label'],
            'status_class' => $itemStatus['class'],
            'low_stock_threshold' => $hasThreshold ? (int)($row['low_stock_threshold'] ?? 10) : 10,
        ];
    }

    $summarySql = "SELECT
                    COUNT(*) AS total_items,
                    SUM(CASE WHEN current_balance <= 0 THEN 1 ELSE 0 END) AS out_items,
                    SUM(CASE WHEN current_balance > 0 AND current_balance <= " . ($hasThreshold ? "COALESCE(NULLIF(low_stock_threshold, 0), 10)" : "10") . " THEN 1 ELSE 0 END) AS low_items,
                    SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date >= CURDATE() AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 31 DAY) THEN 1 ELSE 0 END) AS expiring_items
                   FROM inventory_items";
    $summaryResult = mysqli_query($conn, $summarySql);
    $summary = mysqli_fetch_assoc($summaryResult) ?: [];

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
        'summary' => [
            'total_items' => (int)($summary['total_items'] ?? 0),
            'low_items' => (int)($summary['low_items'] ?? 0),
            'out_items' => (int)($summary['out_items'] ?? 0),
            'expiring_items' => (int)($summary['expiring_items'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
