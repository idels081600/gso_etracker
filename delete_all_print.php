<?php
include 'db.php'; // Include your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    // SQL query to delete all rows from sir_bayong_print
    $query = "DELETE FROM sir_bayong_print";

    if ($conn->query($query) === TRUE) {
        echo 'success';
    } else {
        echo 'error';
    }

    $conn->close();
} else {
    echo 'error';
}
?>
