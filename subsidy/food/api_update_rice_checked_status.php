<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$household_id = isset($input['household_id']) ? (int)$input['household_id'] : 0;
$is_checked = isset($input['is_checked']) ? (int)(bool)$input['is_checked'] : 0;

if ($household_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Household id is required.']);
    exit();
}

$stmt = $conn->prepare(
    "UPDATE rice_households
     SET is_checked = ?,
         modified = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Unable to prepare checked status update.']);
    exit();
}

$stmt->bind_param('iii', $is_checked, $is_checked, $household_id);
$success = $stmt->execute();

if (!$success) {
    echo json_encode(['success' => false, 'message' => 'Unable to update checked status.']);
    exit();
}

$check_stmt = $conn->prepare(
    "SELECT id, is_checked, modified
     FROM rice_households
     WHERE id = ?
     LIMIT 1"
);
$check_stmt->bind_param('i', $household_id);
$check_stmt->execute();
$exists = $check_stmt->get_result();
$row = $exists ? $exists->fetch_assoc() : null;

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Household not found.']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => ((int)$row['is_checked'] === 1) ? 'Checked status updated.' : 'Checked status removed.',
    'is_checked' => (int)$row['is_checked'],
    'modified' => $row['modified']
]);
exit();
?>
