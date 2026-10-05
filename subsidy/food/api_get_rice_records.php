<?php
session_start();
require_once __DIR__ . '/rice_household_code.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'RICE_VERIFIER') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = require(__DIR__ . '/config/database.php');
header('Content-Type: application/json');

$searchTerm = trim((string)($_GET['q'] ?? ''));
$codeSourceSql = "
            SELECT household_code FROM rice_households
            UNION SELECT household_code FROM rice_claimed_households
            UNION SELECT household_code FROM rice_third_wave_households
            UNION SELECT household_code FROM rice_fourth_wave_households";
if ($searchTerm !== '') {
    $escapedSearch = mysqli_real_escape_string($conn, $searchTerm);
    $likeSearch = "'%" . $escapedSearch . "%'";
    $codeSourceSql = "
            SELECT household_code FROM rice_households
             WHERE household_code LIKE {$likeSearch} OR household_name LIKE {$likeSearch} OR address LIKE {$likeSearch}
            UNION SELECT household_code FROM rice_claimed_households
             WHERE household_code LIKE {$likeSearch} OR household_name LIKE {$likeSearch} OR address LIKE {$likeSearch}
            UNION SELECT household_code FROM rice_third_wave_households
             WHERE household_code LIKE {$likeSearch} OR household_name LIKE {$likeSearch} OR address LIKE {$likeSearch}
            UNION SELECT household_code FROM rice_fourth_wave_households
             WHERE household_code LIKE {$likeSearch} OR household_name LIKE {$likeSearch} OR address LIKE {$likeSearch}";
}

$sql = "SELECT
            COALESCE(first_wave.id, next_wave.id, third_wave.id, fourth_wave.id) AS id,
            codes.household_code,
            COALESCE(first_wave.household_name, next_wave.household_name, third_wave.household_name, fourth_wave.household_name) AS household_name,
            COALESCE(first_wave.address, next_wave.address, third_wave.address, fourth_wave.address) AS address,
            COALESCE(first_wave.status, next_wave.status, third_wave.status, fourth_wave.status) AS status,
            COALESCE(next_wave.is_claimed, 0) AS is_claimed,
            next_wave.claimed_at,
            CASE WHEN first_wave.id IS NULL THEN 0 ELSE 1 END AS previous_wave_exists,
            COALESCE(first_wave.is_claimed, 0) AS previous_wave_is_claimed,
            first_wave.claimed_at AS previous_wave_claimed_at,
            CASE WHEN next_wave.id IS NULL THEN 0 ELSE 1 END AS next_wave_exists,
            CASE WHEN third_wave.id IS NULL THEN 0 ELSE 1 END AS third_wave_exists,
            COALESCE(third_wave.is_claimed, 0) AS third_wave_is_claimed,
            third_wave.claimed_at AS third_wave_claimed_at,
            CASE WHEN fourth_wave.id IS NULL THEN 0 ELSE 1 END AS fourth_wave_exists,
            COALESCE(fourth_wave.is_claimed, 0) AS fourth_wave_is_claimed,
            fourth_wave.claimed_at AS fourth_wave_claimed_at
        FROM ({$codeSourceSql}
        ) codes
        LEFT JOIN rice_households first_wave ON first_wave.household_code = codes.household_code
        LEFT JOIN rice_claimed_households next_wave ON next_wave.household_code = codes.household_code
        LEFT JOIN rice_third_wave_households third_wave ON third_wave.household_code = codes.household_code
        LEFT JOIN rice_fourth_wave_households fourth_wave ON fourth_wave.household_code = codes.household_code
        ORDER BY COALESCE(first_wave.household_code_prefix, next_wave.household_code_prefix, third_wave.household_code_prefix, fourth_wave.household_code_prefix),
                 COALESCE(first_wave.household_code_number, next_wave.household_code_number, third_wave.household_code_number, fourth_wave.household_code_number),
                 codes.household_code";

$result = mysqli_query($conn, $sql);
$records = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row['is_claimed'] = (int)$row['is_claimed'];
    $row['previous_wave_exists'] = (int)$row['previous_wave_exists'];
    $row['previous_wave_is_claimed'] = (int)$row['previous_wave_is_claimed'];
    $row['next_wave_exists'] = (int)$row['next_wave_exists'];
    $row['third_wave_exists'] = (int)$row['third_wave_exists'];
    $row['third_wave_is_claimed'] = (int)$row['third_wave_is_claimed'];
    $row['fourth_wave_exists'] = (int)$row['fourth_wave_exists'];
    $row['fourth_wave_is_claimed'] = (int)$row['fourth_wave_is_claimed'];
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
