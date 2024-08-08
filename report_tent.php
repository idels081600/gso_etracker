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
    'Province' => 0,
    'City Government' => 0,
    'Municipalities' => 0
];

// Build the query based on the input
$query = "SELECT * FROM tent WHERE status IN ('On Stock', 'Installed') AND date BETWEEN '$start_date' AND '$end_date'";
$result = $conn->query($query);

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;

    // Increment the counter for the event type by the number of tent numbers
    $purpose = $row['purpose']; // Assuming 'purpose' is the column name in your table
    $tent_no = $row['tent_no']; // Assuming 'tent_no' is the column name in your table
    if (isset($event_counts[$purpose]) && !empty($tent_no)) {
        $tent_numbers = explode(',', $tent_no); // Split the tent_no string by commas
        $event_counts[$purpose] += count($tent_numbers); // Increment the counter by the number of tent numbers
    }
}

$response = [
    'data' => $data,
    'event_counts' => $event_counts
];

echo json_encode($response);

$conn->close();
