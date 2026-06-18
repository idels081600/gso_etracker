<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = require(__DIR__ . '/config/database.php');
header('Content-Type: application/json');

$sql = "SELECT
            rh.id,
            rh.household_code,
            rh.household_name,
            rh.status,
            rh.is_claimed,
            rh.claimed_at
        FROM rice_households rh
        ORDER BY rh.household_code ASC";

$result = mysqli_query($conn, $sql);
$records = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row['is_claimed'] = (int)$row['is_claimed'];
    $records[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $records
]);
exit();
?>
