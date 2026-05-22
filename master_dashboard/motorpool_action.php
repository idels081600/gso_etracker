<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();
master_verify_csrf();
require_once dirname(__DIR__) . '/db_asset.php';

header('Content-Type: application/json');

function motorpool_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    motorpool_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$action = $_POST['action'] ?? '';

if ($action === 'status') {
    $repairId = (int)($_POST['repair_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $validStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    if ($repairId < 1 || !in_array($status, $validStatuses, true)) {
        motorpool_response(['success' => false, 'message' => 'Invalid repair status request.'], 422);
    }

    $stmt = $conn->prepare('UPDATE motorpool_repair SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $repairId);
    motorpool_response(['success' => $stmt->execute(), 'message' => $stmt->error ?: 'Repair status updated.']);
}

if ($action === 'delete') {
    $repairId = (int)($_POST['repair_id'] ?? 0);
    if ($repairId < 1) {
        motorpool_response(['success' => false, 'message' => 'Invalid repair ID.'], 422);
    }

    $stmt = $conn->prepare('DELETE FROM motorpool_repair WHERE id = ?');
    $stmt->bind_param('i', $repairId);
    motorpool_response(['success' => $stmt->execute(), 'message' => $stmt->error ?: 'Repair deleted.']);
}

if ($action === 'update') {
    $repairId = (int)($_POST['edit_repair_id'] ?? 0);
    $plateNo = trim($_POST['edit_vehicle_id'] ?? '');
    $repairDate = trim($_POST['edit_repair_date'] ?? '');
    $repairTypes = $_POST['edit_repair_type'] ?? [];
    $repairType = is_array($repairTypes) ? implode(', ', array_map('trim', $repairTypes)) : trim((string)$repairTypes);
    $mileage = (int)($_POST['edit_mileage'] ?? 0);
    $parts = trim($_POST['edit_parts_replaced'] ?? '');
    $cost = (float)($_POST['edit_cost'] ?? 0);
    $office = trim($_POST['edit_office'] ?? '');
    $remarks = trim($_POST['edit_notes'] ?? '');
    $status = trim($_POST['edit_status'] ?? 'Pending');
    $validStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    if ($repairId < 1 || $plateNo === '' || !in_array($status, $validStatuses, true)) {
        motorpool_response(['success' => false, 'message' => 'Invalid repair update request.'], 422);
    }

    $carModel = '';
    $vehicleStmt = $conn->prepare('SELECT car_model FROM vehicle_records WHERE plate_no = ? LIMIT 1');
    $vehicleStmt->bind_param('s', $plateNo);
    $vehicleStmt->execute();
    $vehicleResult = $vehicleStmt->get_result();
    if ($vehicleResult && $vehicleRow = $vehicleResult->fetch_assoc()) {
        $carModel = $vehicleRow['car_model'] ?? '';
    }
    $vehicleStmt->close();

    $stmt = $conn->prepare('UPDATE motorpool_repair SET plate_no = ?, car_model = ?, repair_date = ?, repair_type = ?, mileage = ?, parts_replaced = ?, cost = ?, office = ?, remarks = ?, status = ? WHERE id = ?');
    $stmt->bind_param('ssssisdsssi', $plateNo, $carModel, $repairDate, $repairType, $mileage, $parts, $cost, $office, $remarks, $status, $repairId);
    motorpool_response(['success' => $stmt->execute(), 'message' => $stmt->error ?: 'Repair updated.']);
}

motorpool_response(['success' => false, 'message' => 'Unknown action.'], 422);

