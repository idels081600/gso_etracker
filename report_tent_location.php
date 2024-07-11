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

// Initialize the counters for each location
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

    // Increment the counter for the location by the number of tent numbers
    $location = $row['location']; // Assuming 'location' is the column name in your table
    $tent_no = $row['tent_no']; // Assuming 'tent_no' is the column name in your table
    if (isset($location_counts[$location])) {
        // Count tent numbers (assuming tent_no is comma-separated)
        $tent_numbers = explode(',', $tent_no);
        $location_counts[$location] += count($tent_numbers);
    }
}

// Prepare the final result
$response = [
    'data' => $data,
    'location_counts' => $location_counts
];

echo json_encode($response);

$conn->close();
