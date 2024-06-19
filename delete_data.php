<?php
// Assuming you have a database connection established
include('db_asset.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id'])) {
        $id = $_POST['id'];
        // Perform delete operation based on the provided ID
        $sql = "DELETE FROM tent WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo 'Record deleted successfully.';
        } else {
            echo 'Error deleting record: ' . mysqli_error($conn);
        }
    } else {
        echo 'Invalid request. ID not provided.';
    }
} else {
    echo 'Invalid request method.';
}
?>
