<?php
session_start();

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

function riceDefaultPrefix($barangay)
{
    $prefix = strtoupper(trim((string)$barangay));
    $prefix = preg_replace('/\s+/', ' ', $prefix);
    return $prefix;
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
        "SELECT household_code
         FROM rice_households
         WHERE address = ?
         ORDER BY CAST(TRIM(SUBSTRING_INDEX(household_code, '-', -1)) AS UNSIGNED) DESC,
                  household_code DESC
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
        $parts = explode('-', $last_row['household_code']);
        if (count($parts) >= 2) {
            $prefix = trim(implode('-', array_slice($parts, 0, -1)));
            $last_number = (int)trim($parts[count($parts) - 1]);
        }
    }

    $next_number = $last_number + 1;
    $household_code = $prefix . ' - ' . $next_number;

    $insert_stmt = $conn->prepare(
        "INSERT INTO rice_households (household_code, household_name, address, status, is_claimed)
         VALUES (?, ?, ?, 'Active', 0)"
    );
    $insert_stmt->bind_param('sss', $household_code, $household_name, $barangay);
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
