<?php
header('Content-Type: application/json');

// Include the database connection file
include 'db_asset.php';

// Get parameters from the request
$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];

// Validate and sanitize input
$start_date = $conn->real_escape_string($start_date);
$end_date = $conn->real_escape_string($end_date);

// Build the query based on the input
$query = "SELECT * FROM RFQ WHERE date BETWEEN '$start_date' AND '$end_date' AND Status = 'SAP'";
$result = $conn->query($query);

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit();
}

$data = [];
$rowCount = 0;

while ($row = $result->fetch_assoc()) {
    $rowCount++;
    $data[] = $row;
}

$response = [
    'total_count' => $rowCount,
    'data' => $data
];

echo json_encode($response);

$conn->close();
