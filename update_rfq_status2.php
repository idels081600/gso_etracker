<?php
include 'db_asset.php'; // Include your database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rfq_ids = $_POST['rfq_ids'];

    // Ensure rfq_ids is an array
    if (is_array($rfq_ids)) {
        $placeholders = implode(',', array_fill(0, count($rfq_ids), '?'));
        $types = str_repeat('i', count($rfq_ids));

        $query = "UPDATE RFQ SET Status = 'Office Clerk' WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($query);

        // Use array_merge with references to pass dynamic parameters
        $stmt->bind_param($types, ...$rfq_ids);

        if ($stmt->execute()) {
            echo 'Success';
        } else {
            echo 'Error';
        }

        $stmt->close();
    } else {
        echo 'Invalid input.';
    }

    $conn->close();
}
