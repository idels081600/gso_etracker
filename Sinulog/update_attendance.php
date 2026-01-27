<?php
header('Content-Type: application/json');
include 'sinulog_db.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';

    if (!empty($role)) {
        $searchTerm = '';
        // Match logic from fetch_members.php to ensure consistent targeting
        if (stripos($role, 'Dancer') !== false) {
            $searchTerm = '%dancer%';
        } elseif (stripos($role, 'Props') !== false) {
            $searchTerm = '%props%';
        } elseif (stripos($role, 'Instrument') !== false) {
            $searchTerm = '%instrument%';
        } else {
            $searchTerm = '%' . $conn->real_escape_string($role) . '%';
        }

        $sql = "UPDATE Sinulog SET status = 'Absent' WHERE role LIKE '$searchTerm'";

        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'message' => "All $role members marked as Absent"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Role is required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>