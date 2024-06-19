<?php
header('Content-Type: application/json');

// Include the database connection file
include 'db_asset.php';

// Build the query based on the input
$query = "SELECT * FROM RFQ WHERE Status = 'SAP'";
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
