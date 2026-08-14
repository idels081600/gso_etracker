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
mysqli_report(MYSQLI_REPORT_OFF);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$barangay = trim($input['barangay'] ?? '');
$household_name = trim($input['household_name'] ?? '');

if ($barangay === '' || $household_name === '') {
    echo json_encode(['success' => false, 'message' => 'Barangay and household name are required.']);
    exit();
}

try {
    $conn->begin_transaction();

    $duplicate_stmt = $conn->prepare(
        "SELECT id, household_code
         FROM rice_households
         WHERE address = ?
           AND household_name = ?
         LIMIT 1"
    );
    $duplicate_stmt->bind_param('ss', $barangay, $household_name);
    $duplicate_stmt->execute();
    $duplicate_result = $duplicate_stmt->get_result();
    $duplicate = $duplicate_result ? $duplicate_result->fetch_assoc() : null;

    if ($duplicate) {
        throw new Exception('This household name already exists in the selected barangay.');
    }

    $last_stmt = $conn->prepare(
        "SELECT household_code, household_code_prefix, household_code_number
         FROM rice_households
         WHERE address = ?
         ORDER BY household_code_number DESC,
                  household_code ASC
         LIMIT 1
         FOR UPDATE"
    );
    $last_stmt->bind_param('s', $barangay);
    $last_stmt->execute();
    $last_result = $last_stmt->get_result();
    $last_row = $last_result ? $last_result->fetch_assoc() : null;

    $prefix = riceDefaultPrefix($barangay);
    $last_number = 0;
    if ($last_row && !empty($last_row['household_code'])) {
        $parsed_code = riceParseHouseholdCode($last_row['household_code'], $barangay);
        $prefix = !empty($last_row['household_code_prefix']) ? $last_row['household_code_prefix'] : $parsed_code['prefix'];
        $last_number = isset($last_row['household_code_number'])
            ? (int)$last_row['household_code_number']
            : $parsed_code['number'];
    }

    $next_number = $last_number + 1;
    $household_code = $prefix . ' - ' . $next_number;
    $sort_code = riceParseHouseholdCode($household_code, $barangay);

    $insert_stmt = $conn->prepare(
        "INSERT INTO rice_households (
            household_code,
            household_code_prefix,
            household_code_number,
            household_name,
            address,
            status,
            is_claimed
         ) VALUES (?, ?, ?, ?, ?, 'Active', 0)"
    );
    $insert_stmt->bind_param(
        'ssiss',
        $household_code,
        $sort_code['prefix'],
        $sort_code['number'],
        $household_name,
        $barangay
    );
    if (!$insert_stmt->execute()) {
        throw new Exception('Unable to add the household record.');
    }

    $household_id = (int)$conn->insert_id;
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Household added successfully.',
        'household_id' => $household_id,
        'household_code' => $household_code,
        'household_name' => $household_name,
        'barangay' => $barangay
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();
?>
