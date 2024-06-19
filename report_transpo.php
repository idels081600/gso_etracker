<?php
header('Content-Type: application/json');

// Include the database connection file
include 'db_asset.php';

// Get parameters from the request
$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];
$tent_status = $_GET['tent_status'];

// Validate and sanitize input
$start_date = $conn->real_escape_string($start_date);
$end_date = $conn->real_escape_string($end_date);
$tent_status = $conn->real_escape_string($tent_status);

// Initialize the counters for each event type
$event_counts = [
    'Burial Services' => 0,
    'Office Services' => 0,
    'Cargo Services' => 0,
    'Other Services' => 0,
    'Travel Services' => 0,
];

// Build the query based on the input
$query = "SELECT * FROM Transportation WHERE Status1 = '$tent_status' AND date BETWEEN '$start_date' AND '$end_date'";

$result = $conn->query($query);

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;

    // Increment the counter for the event type
    $purpose = $row['Purpose']; // Assuming 'purpose' is the column name in your table
    if (isset($event_counts[$purpose])) {
        $event_counts[$purpose]++;
    }
}

$response = [
    'data' => $data,
    'event_counts' => $event_counts
];

echo json_encode($response);

$conn->close();
