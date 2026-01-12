<?php
header('Content-Type: application/json');

include 'sinulog_db.php';


if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number = $_POST['number'] ?? '';

    if (!empty($number)) {
        $number = $conn->real_escape_string($number);

        // Check if the member exists in the sinulog table
        $checkSql = "SELECT id FROM Sinulog WHERE number = '$number'";
        $result = $conn->query($checkSql);

        if ($result && $result->num_rows > 0) {
            // Update status to Present
            $updateSql = "UPDATE Sinulog SET status = 'Present' WHERE number = '$number'";

            if ($conn->query($updateSql) === TRUE) {
                echo json_encode(['success' => true, 'message' => 'Marked as Present']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No number provided']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
