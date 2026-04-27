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

// Validate vendor_serial parameter
if (!isset($_GET['vendor_serial']) || empty(trim($_GET['vendor_serial']))) {
    echo json_encode(['success' => false, 'message' => 'Vendor serial is required']);
    exit();
}

$vendor_serial = trim($_GET['vendor_serial']);
$escaped_serial = mysqli_real_escape_string($conn, $vendor_serial);

// Search query for exact match or partial match
$sql = "SELECT vendor_serial, vendor_name, stall_no, section
        FROM food_vendors
        WHERE vendor_serial = '$escaped_serial' 
        OR vendor_serial LIKE '$escaped_serial%' 
        OR vendor_name LIKE '%$escaped_serial%' 
        LIMIT 10";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database query failed']);
    exit();
}

$vendors = [];

while ($row = mysqli_fetch_assoc($result)) {
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

// If exact match found, return single vendor; otherwise return list
$exact_match = null;
foreach ($vendors as $vendor) {
    if (strtoupper($vendor['vendor_serial']) === strtoupper($vendor_serial)) {
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