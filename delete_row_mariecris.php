<?php
include 'db.php'; // Adjust the path to your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ids'])) {
        $ids = $_POST['ids'];

        // Sanitize and prepare IDs for SQL query
        $ids = array_map('intval', $ids);
        $idsStr = implode(',', $ids);

        // Prepare the SQL query to delete multiple rows
        $query = "DELETE FROM Maam_mariecris WHERE id IN ($idsStr)";

        if (mysqli_query($conn, $query)) {
            echo 'Rows deleted successfully.';
        } else {
            echo 'Failed to delete rows.';
        }

        $conn->close();
    } else {
        echo 'No IDs provided.';
    }
} else {
    echo 'Invalid request method.';
}
