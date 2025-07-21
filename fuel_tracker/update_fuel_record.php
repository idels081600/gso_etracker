<?php
header('Content-Type: application/json');

require_once '../db_asset.php'; // Use this for DB connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}
$recordId = intval($_GET['id']);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

// Prepare and sanitize input
$fuel_date = $input['fuel_date'] ?? null;
$office = $input['office'] ?? null;
$vehicle = $input['vehicle'] ?? null;
$plate_no = $input['plate_no'] ?? null;
$driver = $input['driver'] ?? null;
$purpose = $input['purpose'] ?? null;
$fuel_type = $input['fuel_type'] ?? null;
$liters_issued = $input['liters_issued'] ?? null;
$remarks = $input['remarks'] ?? null;

if (!$fuel_date || !$office || !$vehicle || !$plate_no || !$driver || !$purpose || !$fuel_type || $liters_issued === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Update the record in the 'fuel' table
$sql = "UPDATE fuel SET
            date = ?,
            office = ?,
            vehicle = ?,
            plate_no = ?,
            driver = ?,
            purpose = ?,
            fuel_type = ?,
            liters_issued = ?,
            remarks = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    "sssssssssi",
    $fuel_date,
    $office,
    $vehicle,
    $plate_no,
    $driver,
    $purpose,
    $fuel_type,
    $liters_issued,
    $remarks,
    $recordId
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update record: ' . $stmt->error]);
}

$stmt->close();
$conn->close(); 