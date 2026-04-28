<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 20;
$offset = ($page - 1) * $per_page;

// Filter parameters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$vendor_serial = isset($_GET['vendor_serial']) ? trim($_GET['vendor_serial']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_clauses = [];

if ($status && in_array($status, ['pending', 'completed', 'cancelled'])) {
    $escaped_status = mysqli_real_escape_string($conn, $status);
    $where_clauses[] = "b.status = '$escaped_status'";
}

if ($vendor_serial) {
    $escaped_serial = mysqli_real_escape_string($conn, $vendor_serial);
    $where_clauses[] = "v.vendor_serial = '$escaped_serial'";
}

if ($date_from) {
    $escaped_from = mysqli_real_escape_string($conn, $date_from);
    $where_clauses[] = "DATE(b.created_at) >= '$escaped_from'";
}

if ($date_to) {
    $escaped_to = mysqli_real_escape_string($conn, $date_to);
    $where_clauses[] = "DATE(b.created_at) <= '$escaped_to'";
}

if ($search) {
    $escaped_search = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(b.batch_number LIKE '%$escaped_search%' OR v.vendor_name LIKE '%$escaped_search%' OR v.vendor_serial LIKE '%$escaped_search%')";
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM food_redemption_batches b 
              LEFT JOIN food_vendors v ON b.vendor_id = v.id 
              $where_sql";
$count_result = mysqli_query($conn, $count_sql);
$total_row = mysqli_fetch_assoc($count_result);
$total = (int)$total_row['total'];
$total_pages = ceil($total / $per_page);

// Get batches
$sql = "SELECT 
            b.id,
            b.batch_number,
            b.total_vouchers,
            b.total_amount,
            b.status,
            b.created_by,
            b.created_at,
            b.redeemed_at,
            b.remarks,
            v.vendor_serial,
            v.vendor_name,
            v.area
        FROM food_redemption_batches b
        LEFT JOIN food_vendors v ON b.vendor_id = v.id
        $where_sql
        ORDER BY b.created_at DESC
        LIMIT $offset, $per_page";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
    exit();
}

$batches = [];

while ($row = mysqli_fetch_assoc($result)) {
    $batches[] = [
        'id' => (int)$row['id'],
        'batch_number' => $row['batch_number'],
        'total_vouchers' => (int)$row['total_vouchers'],
        'total_amount' => (float)$row['total_amount'],
        'status' => $row['status'],
        'created_by' => $row['created_by'],
        'created_at' => $row['created_at'],
        'redeemed_at' => $row['redeemed_at'],
        'remarks' => $row['remarks'],
        'vendor' => [
            'vendor_serial' => $row['vendor_serial'],
            'vendor_name' => $row['vendor_name'],
            'area' => $row['area']
        ]
    ];
}

echo json_encode([
    'success' => true,
    'batches' => $batches,
    'pagination' => [
        'page' => $page,
        'per_page' => $per_page,
        'total' => $total,
        'total_pages' => $total_pages
    ]
]);

exit();
?>