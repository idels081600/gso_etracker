<?php
session_start();
require_once __DIR__ . '/rice_household_code.php';
$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
if ($barangay === '') {
    echo json_encode(['success' => false, 'message' => 'Barangay is required']);
    exit();
}

$stmt = $conn->prepare(
    "SELECT household_code, household_code_prefix, household_code_number
     FROM rice_households
     WHERE address = ?
     ORDER BY household_code_number DESC,
              household_code ASC
     LIMIT 1"
);
$stmt->bind_param('s', $barangay);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;

$prefix = riceDefaultPrefix($barangay);
$last_number = 0;

if ($row && !empty($row['household_code'])) {
    $parsed_code = riceParseHouseholdCode($row['household_code'], $barangay);
    $prefix = !empty($row['household_code_prefix']) ? $row['household_code_prefix'] : $parsed_code['prefix'];
    $last_number = isset($row['household_code_number'])
        ? (int)$row['household_code_number']
        : $parsed_code['number'];
}

$next_number = $last_number + 1;
$next_code = $prefix . ' - ' . $next_number;

echo json_encode([
    'success' => true,
    'barangay' => $barangay,
    'prefix' => $prefix,
    'last_number' => $last_number,
    'next_number' => $next_number,
    'next_code' => $next_code
]);
exit();
?>
