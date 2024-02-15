<?php
// Assuming you have a database connection
require_once 'dbh.php';
session_start();
$username1 = $_SESSION['username'];
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_data";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getLatestImage($conn, $username1)
{
    $sql = "SELECT * FROM request WHERE `name` = ? ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("s", $username1);
    $stmt->execute();

    $result = $stmt->get_result();

    if (!$result) {
        die("Query execution failed: " . $stmt->error);
    }

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['ImageName'];
    } else {
        return false;
    }
}

// Check if it's an AJAX request
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Call the function to get the latest image URL
    $latestImage = getLatestImage($conn, $username1);

    if ($latestImage) {
        // Send a JSON response with the success status and image URL
        echo json_encode(['success' => true, 'image' => $latestImage]);
    } else {
        // Send a JSON response with an error message
        echo json_encode(['success' => false, 'message' => 'Failed to fetch the latest image.']);
    }
} else {
    // Handle non-AJAX requests (if any)
    echo 'Direct access not allowed.';
}
?>