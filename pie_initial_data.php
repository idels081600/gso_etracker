<?php
header('Content-Type: application/json');

// Include the database connection file
include 'db_asset.php';

// Initialize the counters for each event type
$event_counts = [
    'Wake' => 0,
    'Fiesta' => 0,
    'Birthday' => 0,
    'Wedding' => 0,
    'Baptism' => 0,
    'Personal' => 0,
    'Private' => 0,
    'Church' => 0,
    'School' => 0,
    'LGU' => 0,
    'Province' => 0
];

// Build the query to count all data with status 'On Stock'
$query = "SELECT * FROM tent WHERE status IN ('On Stock', 'Installed')";

$result = $conn->query($query);

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;

    // Increment the counter for the event type
    $purpose = $row['purpose']; // Assuming 'purpose' is the column name in your table
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
