<?php
session_start();
require_once 'db_fuel.php';

header('Content-Type: application/json');

// Security check - return error if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data (JSON)
$input = json_decode(file_get_contents('php://input'), true);

$station_id = isset($input['station_id']) ? (int)$input['station_id'] : 0;
$username = mysqli_real_escape_string($conn, $_SESSION['username']);

// Validation
if ($station_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid station selected']);
    exit();
}

// Verify station exists and is active in food_markets
$check_station = "SELECT id, market_name FROM food_markets WHERE id = $station_id AND is_active = 1";
$station_result = mysqli_query($conn, $check_station);

if (mysqli_num_rows($station_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Station not found or inactive']);
    exit();
}

$station = mysqli_fetch_assoc($station_result);

// Check if user already has a station assignment
$check_user = "SELECT id FROM user_foods WHERE username = '$username'";
$user_result = mysqli_query($conn, $check_user);

if (mysqli_num_rows($user_result) > 0) {
    // Update existing assignment
    $update_sql = "UPDATE user_foods SET station_id = $station_id, market_name = '{$station['market_name']}' WHERE username = '$username'";
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['station_id'] = $station_id;
        $_SESSION['station_name'] = $station['market_name'];
        echo json_encode(['success' => true, 'message' => 'Station updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating station: ' . mysqli_error($conn)]);
    }
} else {
    // Insert new assignment
    $insert_sql = "INSERT INTO user_foods (username, station_id, market_name) VALUES ('$username', $station_id, '{$station['market_name']}')";
    if (mysqli_query($conn, $insert_sql)) {
        $_SESSION['station_id'] = $station_id;
        $_SESSION['station_name'] = $station['market_name'];
        echo json_encode(['success' => true, 'message' => 'Station assigned successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error assigning station: ' . mysqli_error($conn)]);
    }
}
?>