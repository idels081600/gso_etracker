<?php
session_start();
require_once __DIR__ . '/rice_household_code.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = require(__DIR__ . '/config/database.php');
header('Content-Type: application/json');

$sql = "SELECT
            first_wave.id,
            first_wave.household_code,
            first_wave.household_name,
            first_wave.address,
            first_wave.status,
            COALESCE(next_wave.is_claimed, 0) AS is_claimed,
            next_wave.claimed_at,
            1 AS previous_wave_exists,
            first_wave.is_claimed AS previous_wave_is_claimed,
            first_wave.claimed_at AS previous_wave_claimed_at,
            CASE WHEN next_wave.id IS NULL THEN 0 ELSE 1 END AS next_wave_exists
        FROM rice_households first_wave
        LEFT JOIN rice_claimed_households next_wave
            ON next_wave.household_code = first_wave.household_code
        ORDER BY first_wave.household_code_prefix ASC,
                 first_wave.household_code_number ASC,
                 first_wave.household_code ASC";

$result = mysqli_query($conn, $sql);
$records = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row['is_claimed'] = (int)$row['is_claimed'];
    $row['previous_wave_exists'] = (int)$row['previous_wave_exists'];
    $row['previous_wave_is_claimed'] = (int)$row['previous_wave_is_claimed'];
    $row['next_wave_exists'] = (int)$row['next_wave_exists'];
    $records[] = $row;
}

usort($records, function (array $left, array $right): int {
    return riceCompareHouseholdCodes(
        (string)$left['household_code'],
        (string)$right['household_code'],
        isset($left['address']) ? (string)$left['address'] : null,
        isset($right['address']) ? (string)$right['address'] : null
    );
});

echo json_encode([
    'success' => true,
    'data' => $records
]);
exit();
?>