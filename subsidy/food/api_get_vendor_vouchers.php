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
$require_search = isset($_GET['require_search']) && $_GET['require_search'] === '1';

// Helper function to parse full voucher code (e.g., "si-1271-001")
// Also handles partial codes like "TIP-113-" (trailing dash) or "TIP-113" (no trailing part)
function parseFullVoucherCode($code) {
    $lastDashPos = strrpos($code, '-');
    if ($lastDashPos === false) {
        return null;
    }
    $codePart = substr($code, 0, $lastDashPos);
    $numPart = substr($code, $lastDashPos + 1);
    $voucherNum = (int)$numPart;
    
    // If there's a valid numeric part, return exact match
    if ($voucherNum > 0) {
        return ['code' => $codePart, 'number' => $voucherNum, 'exact' => true];
    }
    
    // If trailing dash (numPart is empty string) or numPart is 0
    // Return as prefix search for the code part
    if ($numPart === '' || $voucherNum === 0) {
        // If the codePart itself also contains a dash like "TIP-113", search by prefix
        return ['code_prefix' => $code, 'exact' => false];
    }
    
    return null;
}

// First, get vendor info
$vendor_sql = "SELECT vendor_serial, vendor_name FROM food_vendors WHERE vendor_serial = '$escaped_serial' LIMIT 1";
$vendor_result = mysqli_query($conn, $vendor_sql);

if (!$vendor_result || mysqli_num_rows($vendor_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    exit();
}

$vendor = mysqli_fetch_assoc($vendor_result);

if ($require_search && $escaped_search === '') {
    echo json_encode([
        'success' => true,
        'vendor' => [
            'vendor_serial' => $vendor['vendor_serial'],
            'vendor_name' => $vendor['vendor_name']
        ],
        'vouchers' => [],
        'count' => 0,
        'total' => 0,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => 1,
        'search' => $search,
        'message' => 'Enter search criteria to find vouchers.'
    ]);
    exit();
}

// Base WHERE conditions - show only verified and unredeemed vouchers
// Also exclude vouchers that exist in voided redemption items
// Determine if we should show all vouchers or only available ones
// When searching, show ALL (including redeemed) so users can see status
// When not searching, only show available (verified + unredeemed) for selection
$show_all = $escaped_search !== '';

$where_conditions = [];

if ($show_all) {
    // Show all vouchers - no filtering on is_verified or is_redeemed
    $where_conditions = [];
} else {
    // Default: only show available vouchers for selection
    $where_conditions = [
        "vc.is_verified = 1", 
        "vc.is_redeemed = 0"
    ];
}

// Add search filter if provided
if ($escaped_search !== '') {
    $searchConditions = [];
    
    // Prefix matching on beneficiary fields can use idx_code_name.
    // Claimant and voucher number stay flexible for existing redemption search behavior.
    $searchConditions[] = "(fb.beneficiary_code LIKE '$escaped_search%' OR fb.full_name LIKE '$escaped_search%' OR vc.claimant_name LIKE '%$escaped_search%' OR vc.voucher_number LIKE '%$escaped_search%')";
    
    // Try to parse as full voucher code (e.g., "si-1271-001")
    $parsed = parseFullVoucherCode($escaped_search);
    if ($parsed !== null) {
        if ($parsed['exact'] === true) {
            // Exact match - e.g. "TIP-113-001" -> code='TIP-113', number=1
            $codePart = mysqli_real_escape_string($conn, $parsed['code']);
            $numPart = (int)$parsed['number'];
            $searchConditions[] = "(fb.beneficiary_code = '$codePart' AND vc.voucher_number = $numPart)";
        } else {
            // Prefix match - e.g. "TIP-113-" -> find all vouchers starting with "TIP-113-"
            $escapedPrefixLike = mysqli_real_escape_string($conn, $parsed['code_prefix']);
            // Use CONCAT to match beneficiary_code + '-' + padded voucher_number
            $searchConditions[] = "CONCAT(fb.beneficiary_code, '-', LPAD(vc.voucher_number, 3, '0')) LIKE '$escapedPrefixLike%'";
        }
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
            vc.batch_id,
            fb.beneficiary_code,
            fb.full_name as beneficiary_name,
            rb.batch_number
        FROM food_voucher_claims vc
        LEFT JOIN food_beneficiaries fb ON vc.beneficiary_id = fb.id
        LEFT JOIN food_redemption_batches rb ON vc.batch_id = rb.id
        WHERE $where_sql
        ORDER BY vc.is_redeemed ASC, vc.claim_date DESC
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
        'is_redeemed' => (int)$row['is_redeemed'],
        'batch_id' => $row['batch_id'] ? (int)$row['batch_id'] : null,
        'batch_number' => $row['batch_number']
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
