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
$normalized_serial = strtolower(str_replace(['-', ' '], '', $vendor_serial));
$prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $vendor_serial) . '%';

$sql = "SELECT vendor_serial, vendor_name, stall_no, section
        FROM food_vendors
        WHERE vendor_serial = ?
           OR vendor_serial LIKE ? ESCAPE '\\\\'
           OR vendor_name LIKE ? ESCAPE '\\\\'
           OR REPLACE(REPLACE(LOWER(vendor_serial), '-', ''), ' ', '') = ?
        ORDER BY
            CASE
                WHEN vendor_serial = ? THEN 0
                WHEN vendor_serial LIKE ? ESCAPE '\\\\' THEN 1
                ELSE 2
            END,
            vendor_serial ASC
        LIMIT 10";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database query prepare failed']);
    exit();
}

$stmt->bind_param(
    'ssssss',
    $vendor_serial,
    $prefix,
    $prefix,
    $normalized_serial,
    $vendor_serial,
    $prefix
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database query failed']);
    exit();
}

$result = $stmt->get_result();
$vendors = [];

while ($row = $result->fetch_assoc()) {
    $vendors[] = [
        'vendor_serial' => $row['vendor_serial'],
        'vendor_name' => $row['vendor_name'],
        'stall_no' => $row['stall_no'],
        'section' => $row['section']
    ];
}

if (count($vendors) === 0) {
    echo json_encode(['success' => false, 'message' => 'No vendor found']);
    exit();
}

$exact_match = null;
foreach ($vendors as $vendor) {
    if (strcasecmp($vendor['vendor_serial'], $vendor_serial) === 0) {
        $exact_match = $vendor;
        break;
    }
}

if ($exact_match) {
    echo json_encode([
        'success' => true,
        'vendor' => $exact_match,
        'match_type' => 'exact'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'vendors' => $vendors,
        'match_type' => 'partial',
        'message' => 'Multiple vendors found, please select one'
    ]);
}

exit();
?>
