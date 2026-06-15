<?php
require_once __DIR__ . '/app_security.php';
asset_require_auth();
require_once __DIR__ . '/db_asset.php';

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
    location,
    address
FROM tent 
WHERE status = 'Retrieved' 
ORDER BY retrieval_date DESC";

$result = mysqli_query($conn, $query);

// Check for query execution errors
if (!$result) {
    error_log('fetch_retrieved_data query failed: ' . mysqli_error($conn));
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Unable to load retrieved data.']));
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

