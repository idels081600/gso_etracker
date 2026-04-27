<?php
session_start();
require_once 'db_fuel.php';

// Force JSON output always
header('Content-Type: application/json');

// Disable PHP warnings from breaking JSON
mysqli_report(MYSQLI_REPORT_OFF);

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
$beneficiary_code = isset($_GET['beneficiary_code']) 
    ? mysqli_real_escape_string($conn, $_GET['beneficiary_code']) 
    : '';

if (empty($beneficiary_code)) {
    echo json_encode(['success' => false, 'message' => 'Beneficiary number is required']);
    exit();
}

// Main query
$sql = "SELECT tr.id, tr.beneficiary_code, tr.full_name, tr.address, tr.contact_number,
        tr.total_vouchers, tr.claimed_vouchers, tr.status, tr.last_claim_date
        FROM food_beneficiaries tr 
        WHERE tr.beneficiary_code = '$beneficiary_code' 
        OR tr.full_name LIKE '%$beneficiary_code%' 
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database query failed']);
    exit();
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
    exit();
}

$beneficiary = mysqli_fetch_assoc($result);

// Claims query
$beneficiary_id = (int)$beneficiary['id'];

$claims_sql = "SELECT voucher_number, claimant_name, claim_date, e_signature 
               FROM food_voucher_claims 
               WHERE beneficiary_id = $beneficiary_id 
               ORDER BY voucher_number";

$claims_result = mysqli_query($conn, $claims_sql);

$claimed_vouchers = [];
$claims_data = [];

if ($claims_result) {
    while ($row = mysqli_fetch_assoc($claims_result)) {
        $claimed_vouchers[] = (int)$row['voucher_number'];
        $claims_data[] = [
            'voucher_number' => (int)$row['voucher_number'],
            'claimant_name' => $row['claimant_name'],
            'claim_date' => $row['claim_date'],
            'e_signature' => $row['e_signature']
        ];
    }
}

$beneficiary['claimed_vouchers_list'] = $claimed_vouchers;
$beneficiary['claims_data'] = $claims_data;

echo json_encode([
    'success' => true,
    'data' => $beneficiary
]);
exit();