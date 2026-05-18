<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['vendor_serial']) || empty(trim($_GET['vendor_serial']))) {
    echo json_encode(['success' => false, 'message' => 'Vendor serial is required']);
    exit();
}

$vendor_serial = trim($_GET['vendor_serial']);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 50;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$require_search = isset($_GET['require_search']) && $_GET['require_search'] === '1';

function bindParams(mysqli_stmt $stmt, string $types, array &$params): void {
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }

    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetchOne(mysqli $conn, string $sql, string $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    bindParams($stmt, $types, $params);
    if (!$stmt->execute()) {
        return false;
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : false;
}

function escapeLike(string $value): string {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

function parseFullVoucherCode(string $code): ?array {
    $lastDashPos = strrpos($code, '-');
    if ($lastDashPos === false) {
        return null;
    }

    $codePart = substr($code, 0, $lastDashPos);
    $numPart = substr($code, $lastDashPos + 1);

    if ($numPart !== '' && ctype_digit($numPart)) {
        return [
            'type' => 'exact_code',
            'beneficiary_code' => $codePart,
            'voucher_number' => (int)$numPart
        ];
    }

    return [
        'type' => 'beneficiary_prefix',
        'beneficiary_code' => rtrim($code, '-')
    ];
}

$vendor = fetchOne(
    $conn,
    'SELECT vendor_serial, vendor_name FROM food_vendors WHERE vendor_serial = ? LIMIT 1',
    's',
    [$vendor_serial]
);

if (!$vendor) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    exit();
}

if ($require_search && $search === '') {
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

$where = [];
$types = '';
$params = [];

if ($search === '') {
    $where[] = 'vc.is_verified = 1';
    $where[] = 'vc.is_redeemed = 0';
} else {
    $parsed = parseFullVoucherCode($search);

    if ($parsed && $parsed['type'] === 'exact_code') {
        $where[] = '((fb.beneficiary_code = ? AND vc.voucher_number = ?) OR fb.beneficiary_code LIKE ? ESCAPE \'\\\\\')';
        $types .= 'sis';
        $params[] = $parsed['beneficiary_code'];
        $params[] = $parsed['voucher_number'];
        $params[] = escapeLike($search) . '%';
    } elseif ($parsed && $parsed['type'] === 'beneficiary_prefix') {
        $where[] = 'fb.beneficiary_code LIKE ? ESCAPE \'\\\\\'';
        $types .= 's';
        $params[] = escapeLike($parsed['beneficiary_code']) . '%';
    } elseif (ctype_digit($search)) {
        $where[] = 'vc.voucher_number = ?';
        $types .= 'i';
        $params[] = (int)$search;
    } else {
        $like = escapeLike($search) . '%';
        $where[] = '(fb.beneficiary_code LIKE ? ESCAPE \'\\\\\' OR fb.full_name LIKE ? ESCAPE \'\\\\\' OR vc.claimant_name LIKE ? ESCAPE \'\\\\\')';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
}

$where_sql = implode(' AND ', $where);

$query_limit = $limit + 1;

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
            fb.full_name AS beneficiary_name,
            rb.batch_number
        FROM food_voucher_claims vc
        INNER JOIN food_beneficiaries fb ON vc.beneficiary_id = fb.id
        LEFT JOIN food_redemption_batches rb ON vc.batch_id = rb.id
        WHERE $where_sql
        ORDER BY vc.is_redeemed ASC, vc.claim_date DESC
        LIMIT ? OFFSET ?";

$query_types = $types . 'ii';
$query_params = $params;
$query_params[] = $query_limit;
$query_params[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database query prepare failed: ' . $conn->error]);
    exit();
}

bindParams($stmt, $query_types, $query_params);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . $stmt->error]);
    exit();
}

$result = $stmt->get_result();
$vouchers = [];

while ($row = $result->fetch_assoc()) {
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

$has_more = count($vouchers) > $limit;
if ($has_more) {
    array_pop($vouchers);
}

$total = $offset + count($vouchers) + ($has_more ? 1 : 0);
$total_pages = $has_more ? $page + 1 : max(1, $page);

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
