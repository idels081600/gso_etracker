<?php
header('Content-Type: application/json');

// Include the database connection file
include 'db_asset.php';

// Initialize the counters for each event type
$location_counts = [
    'Bool' => 0,
    'Booy' => 0,
    'Cabawan' => 0,
    'Cogon' => 0,
    'Dao' => 0,
    'Dampas' => 0,
    'Manga' => 0,
    'Mansasa' => 0,
    'Poblacion I' => 0,
    'Poblacion II' => 0,
    'Poblacion III' => 0,
    'San Isidro' => 0,
    'Taloto' => 0,
    'Tiptip' => 0,
    'Ubujan' => 0,
    'Outside Tagbilaran' => 0
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
    $purpose = $row['Location']; // Assuming 'purpose' is the column name in your table
    if (isset($location_counts[$purpose])) {
        $location_counts[$purpose]++;
    }
}

// Prepare the final result
$response = [
    'data' => $data,
    'event_counts' => $location_counts
];

echo json_encode($response);

$conn->close();
