<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();
master_verify_csrf();
require_once dirname(__DIR__) . '/db_asset.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = trim($_POST['status'] ?? '');
$validStatuses = ['Stand By', 'Departed', 'Arrived'];

if ($id <= 0 || !in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid transportation status request.']);
    exit();
}

$stmt = $conn->prepare('SELECT Plate_no FROM Transportation WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Transportation record not found.']);
    exit();
}

$plateNo = $row['Plate_no'];
$vehicleStatus = $status === 'Departed' ? 'Departed' : 'Stand By';

$update = $conn->prepare('UPDATE Transportation SET Status = ?, Status1 = ? WHERE id = ?');
$update->bind_param('ssi', $status, $status, $id);
$transportOk = $update->execute();
$update->close();

$vehicleUpdate = $conn->prepare('UPDATE Vehicle SET Status = ? WHERE Plate_no = ?');
$vehicleUpdate->bind_param('ss', $vehicleStatus, $plateNo);
$vehicleOk = $vehicleUpdate->execute();
$vehicleUpdate->close();

echo json_encode([
    'success' => $transportOk && $vehicleOk,
    'message' => $transportOk && $vehicleOk ? 'Transportation status updated.' : 'Unable to update status.',
    'status' => $status,
]);
