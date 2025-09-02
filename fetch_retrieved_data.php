<?php
require_once 'db_asset.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
}

// Fetch all tents with 'Retrieved' status
$query = "SELECT 
    id,
    tent_no,
    retrieval_date,
    name,
    purpose,
    location
FROM tent 
WHERE status = 'Retrieved' 
ORDER BY retrieval_date DESC";

$result = mysqli_query($conn, $query);

// Check for query execution errors
if (!$result) {
    die(json_encode(['error' => 'Query failed: ' . mysqli_error($conn)]));
}

$data = array();
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Check if retrieval_date is null or invalid
        if ($row['retrieval_date'] !== null && $row['retrieval_date'] !== '') {
            $row['retrieval_date'] = date('Y-m-d', strtotime($row['retrieval_date']));
        } else {
            $row['retrieval_date'] = 'N/A';
        }
        $data[] = $row;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $data]);
