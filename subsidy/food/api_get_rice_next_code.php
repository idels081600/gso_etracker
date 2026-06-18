<?php
session_start();
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

function riceDefaultPrefix($barangay)
{
    $prefix = strtoupper(trim((string)$barangay));
    $prefix = preg_replace('/\s+/', ' ', $prefix);
    return $prefix;
}

$stmt = $conn->prepare(
    "SELECT household_code
     FROM rice_households
     WHERE address = ?
     ORDER BY CAST(TRIM(SUBSTRING_INDEX(household_code, '-', -1)) AS UNSIGNED) DESC,
              household_code DESC
     LIMIT 1"
);
$stmt->bind_param('s', $barangay);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;

$prefix = riceDefaultPrefix($barangay);
$last_number = 0;

if ($row && !empty($row['household_code'])) {
    $parts = explode('-', $row['household_code']);
    if (count($parts) >= 2) {
        $prefix = trim(implode('-', array_slice($parts, 0, -1)));
        $last_number = (int)trim($parts[count($parts) - 1]);
    }
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
