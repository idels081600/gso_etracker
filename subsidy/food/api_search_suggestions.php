<?php
session_start();
require_once 'db_fuel.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate query
if (!isset($_GET['q']) || strlen(trim($_GET['q'])) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit();
}

$query = trim($_GET['q']);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search_string = '%' . mysqli_real_escape_string($conn, $query) . '%';

// Count query
$count_sql = "SELECT COUNT(*) as total 
              FROM food_beneficiaries 
              WHERE beneficiary_code LIKE '$search_string' 
              OR full_name LIKE '$search_string'";

$count_result = mysqli_query($conn, $count_sql);

if (!$count_result) {
    echo json_encode(['success' => false, 'message' => 'Count query failed']);
    exit();
}

$total = mysqli_fetch_assoc($count_result)['total'];

// Data query
$sql = "SELECT beneficiary_code, full_name, total_vouchers, claimed_vouchers
        FROM food_beneficiaries
        WHERE beneficiary_code LIKE '$search_string' 
        OR full_name LIKE '$search_string'
        LIMIT $per_page OFFSET $offset";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Search query failed']);
    exit();
}

$results = [];

while ($row = mysqli_fetch_assoc($result)) {
    $remaining = intval($row['total_vouchers']) - intval($row['claimed_vouchers']);

    $results[] = [
        'beneficiary_code' => $row['beneficiary_code'],
        'full_name' => $row['full_name'],
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
exit();