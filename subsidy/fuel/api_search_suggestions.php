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

$query = mysqli_real_escape_string($conn, trim($_GET['q']));

// Search for tricycle numbers or driver names
$sql = "SELECT 
            tr.tricycle_no, 
            tr.driver_name, 
            tr.total_vouchers,
            tr.claimed_vouchers
        FROM tricycle_records tr
        WHERE 
            tr.tricycle_no LIKE '%$query%' 
            OR tr.driver_name LIKE '%$query%'
        LIMIT 10";

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
    'results' => $results
]);
?>