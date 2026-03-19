<?php
// Get the FCM token from the request body
$token = $_POST['token'];

// Replace these database connection details with your own
$servername = "localhost";
$username = "bryanmysql";
$password = "gsotagbilaran";
$dbname = "my_data";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Check if the token already exists in the database
$checkSql = "SELECT * FROM tokens WHERE token = '$token'";
$result = $conn->query($checkSql);

if ($result->num_rows > 0) {
    // Token already exists, handle accordingly
    $response = ['status' => 'error', 'message' => 'Token already exists in the database'];
} else {
    // Insert the token into the database
    $insertSql = "INSERT INTO tokens (token) VALUES ('$token')";

    if ($conn->query($insertSql) === TRUE) {
        $response = ['status' => 'success', 'message' => 'Token stored successfully'];
    } else {
        $response = ['status' => 'error', 'message' => 'Error storing token: ' . $conn->error];
    }
}

// Close the database connection
$conn->close();

// Send a JSON response back to the client
header('Content-Type: application/json');
echo json_encode($response);
?>
