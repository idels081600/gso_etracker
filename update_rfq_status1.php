<?php
// Include database connection
include 'db_asset.php';

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the newValue and rowId parameters are set
    if (isset($_POST["newValue"]) && isset($_POST["rowId"])) {
        // Sanitize and validate input
        $newValue = mysqli_real_escape_string($conn, $_POST['newValue']);
        $rowId = mysqli_real_escape_string($conn, $_POST['rowId']);

        // Update the Status column in the database
        $sql = "UPDATE RFQ SET Status = '$newValue' WHERE id = '$rowId'";

        if (mysqli_query($conn, $sql)) {
            echo "Status updated successfully";
        } else {
            echo "Error updating status: " . mysqli_error($conn);
        }
    } else {
        echo "Error: Missing parameters";
    }
} else {
    echo "Error: Invalid request method";
}
