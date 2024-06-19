<?php
// Include db.php for database connection
require_once 'db_asset.php';

// Fetch data based on ID
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Prepare and execute SQL query
    $stmt = $conn->prepare("SELECT no_of_tents FROM tent WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if row exists
    if ($result->num_rows > 0) {
        // Fetch row
        $row = $result->fetch_assoc();
        // Output data as JSON
        echo json_encode($row);
    } else {
        // No data found with the given ID
        echo json_encode(array("error" => "No data found for the given ID"));
    }

    // Close statement
    $stmt->close();
} else {
    // ID parameter not provided
    echo json_encode(array("error" => "ID parameter not provided"));
}

// Close connection (if using procedural style)
$conn->close();
