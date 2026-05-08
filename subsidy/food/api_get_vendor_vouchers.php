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

// Pagination params
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, (int)$_GET['limit']) : 50;
$offset = ($page - 1) * $limit;

// Search param (optional) - filters by beneficiary code, name, claimant name, or voucher number
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$escaped_search = $search !== '' ? mysqli_real_escape_string($conn, $search) : '';

// Helper function to parse full voucher code (e.g., "si-1271-001")
function parseFullVoucherCode($code) {
    $lastDashPos = strrpos($code, '-');
    if ($lastDashPos === false) {
        return null;
    }
    $codePart = substr($code, 0, $lastDashPos);
    $numPart = substr($code, $lastDashPos + 1);
    $voucherNum = (int)$numPart;
    return $voucherNum > 0 ? ['code' => $codePart, 'number' => $voucherNum] : null;
}

// First, get vendor info
$vendor_sql = "SELECT vendor_serial, vendor_name FROM food_vendors WHERE vendor_serial = '$escaped_serial' LIMIT 1";
$vendor_result = mysqli_query($conn, $vendor_sql);

if (!$vendor_result || mysqli_num_rows($vendor_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    exit();
}

$vendor = mysqli_fetch_assoc($vendor_result);

// Base WHERE conditions - show only verified and unredeemed vouchers
// Also exclude vouchers that exist in voided redemption items
$where_conditions = [
    "vc.is_verified = 1", 
    "vc.is_redeemed = 0",
    "NOT EXISTS (SELECT 1 FROM food_redemption_items ri WHERE ri.voucher_id = vc.id AND ri.status = 'void')"
];

// Add search filter if provided
if ($escaped_search !== '') {
    $searchConditions = [];
    
    // Standard search fields
    $searchConditions[] = "(fb.beneficiary_code LIKE '%$escaped_search%' OR fb.full_name LIKE '%$escaped_search%' OR vc.claimant_name LIKE '%$escaped_search%')";
    
    // Try to parse as full voucher code (e.g., "si-1271-001")
    $parsed = parseFullVoucherCode($escaped_search);
    if ($parsed !== null) {
        $codePart = mysqli_real_escape_string($conn, $parsed['code']);
        $numPart = (int)$parsed['number'];
        $searchConditions[] = "(fb.beneficiary_code = '$codePart' AND vc.voucher_number = $numPart)";
    } else {
        // If only a number is provided (e.g., "001"), also try matching voucher_number
        if (is_numeric($escaped_search)) {
            $voucherNum = (int)$escaped_search;
            $searchConditions[] = "vc.voucher_number = $voucherNum";
        }
    }
    
    $where_conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
}

$where_sql = implode(' AND ', $where_conditions);

// Count total matching records
$count_sql = "SELECT COUNT(*) as total 
              FROM food_voucher_claims vc
              LEFT JOIN food_beneficiaries fb ON vc.beneficiary_id = fb.id
              WHERE $where_sql";
$count_result = mysqli_query($conn, $count_sql);
$total = 0;
if ($count_result) {
    $total = (int)mysqli_fetch_assoc($count_result)['total'];
}

$total_pages = $total > 0 ? (int)ceil($total / $limit) : 1;

// Fetch paginated vouchers
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
        WHERE $where_sql
        ORDER BY vc.claim_date DESC
        LIMIT $limit OFFSET $offset";

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
    'count' => count($vouchers),
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => $total_pages,
    'search' => $search
]);

exit();
?>