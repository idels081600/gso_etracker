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
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search_string = '%' . mysqli_real_escape_string($conn, $query) . '%';

// Get total count first
$count_sql = "SELECT COUNT(*) as total FROM tricycle_records tr WHERE tr.tricycle_no LIKE '$search_string' OR tr.driver_name LIKE '$search_string'";
$count_result = mysqli_query($conn, $count_sql);
$total = mysqli_fetch_assoc($count_result)['total'];

// Search for tricycle numbers or driver names
$sql = "SELECT 
            tr.tricycle_no, 
            tr.driver_name, 
            tr.total_vouchers,
            tr.claimed_vouchers
        FROM tricycle_records tr
        WHERE 
            tr.tricycle_no LIKE '$search_string' 
            OR tr.driver_name LIKE '$search_string'
        LIMIT $per_page OFFSET $offset";

$result = mysqli_query($conn, $sql);

$results = [];
while ($row = mysqli_fetch_assoc($result)) {
    $remaining = intval($row['total_vouchers']) - intval($row['claimed_vouchers']);
    $results[] = [
        'tricycle_no' => $row['tricycle_no'],
        'driver_name' => $row['driver_name'],
        'remaining' => $remaining
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