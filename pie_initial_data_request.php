<?php
header('Content-Type: application/json');

// Include the database connection file
include 'db_asset.php';

// Initialize the counters for each event type
$event_counts = [
    'Burial Services' => 0,
    'Office Services' => 0,
    'Cargo Services' => 0,
    'Other Services' => 0,
    'Travel Services' => 0,
];

// Build the query to count all data with status 'On Stock'
$query = "SELECT * FROM Transportation WHERE Status1 = 'Arrived'";

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

// Prepare the final result
$response = [
    'data' => $data,
    'event_counts' => $event_counts
];

echo json_encode($response);

$conn->close();
