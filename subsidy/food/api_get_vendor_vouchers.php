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

// Validate vendor_serial parameter
if (!isset($_GET['vendor_serial']) || empty(trim($_GET['vendor_serial']))) {
    echo json_encode(['success' => false, 'message' => 'Vendor serial is required']);
    exit();
}

$vendor_serial = trim($_GET['vendor_serial']);
$escaped_serial = mysqli_real_escape_string($conn, $vendor_serial);

// First, get vendor info
$vendor_sql = "SELECT vendor_serial, vendor_name FROM food_vendors WHERE vendor_serial = '$escaped_serial' LIMIT 1";
$vendor_result = mysqli_query($conn, $vendor_sql);

if (!$vendor_result || mysqli_num_rows($vendor_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    exit();
}

$vendor = mysqli_fetch_assoc($vendor_result);

// Get claimed vouchers from food_voucher_claims
// Show ALL verified vouchers regardless of redemption status
$where_sql = "WHERE vc.is_verified = 1";

$sql = "SELECT 
            vc.id,
            vc.beneficiary_id,
            vc.voucher_number,
            vc.claimant_name,
            vc.claim_date,
            vc.is_verified,
            vc.is_redeemed,
            fb.beneficiary_code,
            fb.full_name as beneficiary_name
        FROM food_voucher_claims vc
        LEFT JOIN food_beneficiaries fb ON vc.beneficiary_id = fb.id
        $where_sql
        ORDER BY vc.claim_date DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
    exit();
}

$vouchers = [];

while ($row = mysqli_fetch_assoc($result)) {
    $vouchers[] = [
        'id' => (int)$row['id'],
        'beneficiary_id' => (int)$row['beneficiary_id'],
        'beneficiary_code' => $row['beneficiary_code'],
        'beneficiary_name' => $row['beneficiary_name'],
        'voucher_number' => (int)$row['voucher_number'],
        'claimant_name' => $row['claimant_name'],
        'claim_date' => $row['claim_date'],
        'is_verified' => (int)$row['is_verified'],
        'is_redeemed' => (int)$row['is_redeemed']
    ];
}

echo json_encode([
    'success' => true,
    'vendor' => [
        'vendor_serial' => $vendor['vendor_serial'],
        'vendor_name' => $vendor['vendor_name']
    ],
    'vouchers' => $vouchers,
    'count' => count($vouchers)
]);

exit();
?>