<?php
session_start();
require_once 'db_fuel.php';

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['q']) || strlen(trim($_GET['q'])) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit();
}

$query = trim($_GET['q']);
$voucher_query = isset($_GET['voucher']) ? trim($_GET['voucher']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search_string = '%' . mysqli_real_escape_string($conn, $query) . '%';
$safe_voucher = mysqli_real_escape_string($conn, ltrim($voucher_query, '0'));
$voucher_filter = '';
$voucher_join = '';
$voucher_select = 'NULL AS voucher_number, NULL AS claim_date';

if ($voucher_query !== '') {
    $voucher_join = 'INNER JOIN voucher_claims vc ON vc.tricycle_id = tr.id';
    $voucher_select = 'vc.voucher_number, vc.claim_date';
    $voucher_filter = " AND vc.voucher_number = " . (int)$safe_voucher;
}

// Get total count first
$count_sql = "SELECT COUNT(*) as total
              FROM tricycle_records tr
              $voucher_join
              WHERE (tr.tricycle_no LIKE '$search_string' OR tr.driver_name LIKE '$search_string')
              $voucher_filter";
$count_result = mysqli_query($conn, $count_sql);
$total = mysqli_fetch_assoc($count_result)['total'];

// Search for tricycle numbers or driver names
$sql = "SELECT 
            tr.tricycle_no, 
            tr.driver_name, 
            tr.total_vouchers,
            tr.claimed_vouchers,
            $voucher_select
        FROM tricycle_records tr
        $voucher_join
        WHERE 
            (tr.tricycle_no LIKE '$search_string' 
            OR tr.driver_name LIKE '$search_string')
            $voucher_filter
        ORDER BY tr.tricycle_no, voucher_number
        LIMIT $per_page OFFSET $offset";

$result = mysqli_query($conn, $sql);

$results = [];
while ($row = mysqli_fetch_assoc($result)) {
    $remaining = intval($row['total_vouchers']) - intval($row['claimed_vouchers']);
    $results[] = [
        'tricycle_no' => $row['tricycle_no'],
        'driver_name' => $row['driver_name'],
        'remaining' => $remaining,
        'voucher_number' => isset($row['voucher_number']) ? $row['voucher_number'] : null,
        'claim_date' => isset($row['claim_date']) ? $row['claim_date'] : null
    ];
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'page' => $page,
    'per_page' => $per_page,
    'total' => intval($total),
    'has_more' => ($page * $per_page) < $total
]);
?>
