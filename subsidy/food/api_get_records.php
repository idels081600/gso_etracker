<?php
session_start();

// Security check - return error if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_fuel.php';

header('Content-Type: application/json');

// Get all tricycle records - sort numerically by tricycle_no
$sql = "SELECT 
    id,
    beneficiary_code,
    full_name,
    claimed_vouchers,
    total_vouchers,
    CONCAT(claimed_vouchers, '/', total_vouchers) AS balance,
    status,
    last_claim_date,
    created_at
FROM food_beneficiaries
ORDER BY CAST(beneficiary_code AS UNSIGNED)";

$result = mysqli_query($conn, $sql);

$records = [];
while ($row = mysqli_fetch_assoc($result)) {
    $records[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $records
]);
?>