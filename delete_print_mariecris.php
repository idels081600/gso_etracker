<?php
include 'db.php'; // Include your database connection file

if (isset($_POST['rfq_ids']) && !empty($_POST['rfq_ids'])) {
    $rfqIds = $_POST['rfq_ids'];
    $ids = implode(',', array_map('intval', $rfqIds)); // Ensure all values are integers

    $query = "DELETE FROM Maam_mariecris_print WHERE id IN ($ids)";
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
