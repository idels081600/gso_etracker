<?php
require_once 'logi_db.php'; // Include your database connection

header('Content-Type: application/json');

try {
    // Get the connection from your existing file
    // Assuming you have a $conn variable in logi_display_data.php
    include_once 'logi_db.php';

    // Query to get the highest item number
    $query = "SELECT MAX(CAST(item_no AS UNSIGNED)) as max_item_no FROM inventory_items";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $maxItemNo = $row['max_item_no'];

        if ($maxItemNo !== null) {
            $nextItemNo = $maxItemNo + 1;
        } else {
            // If no records found, start with 1
            $nextItemNo = 1;
        }
    } else {
        // If no records found, start with 1
        $nextItemNo = 1;
    }

    echo json_encode([
        'success' => true,
        'nextItemNo' => (string)$nextItemNo
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
