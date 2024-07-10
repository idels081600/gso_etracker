<?php
require_once 'db.php'; // Include your database connection

// Check if supplier_name is set and not empty
if (isset($_POST['supplier_name']) && !empty($_POST['supplier_name'])) {
    $supplierName = $_POST['supplier_name'];

    // Check if the supplier name already exists
    $checkQuery = "SELECT COUNT(*) AS count FROM Supplier WHERE name = ?";
    $checkStmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "s", $supplierName);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_bind_result($checkStmt, $count);
    mysqli_stmt_fetch($checkStmt);
    mysqli_stmt_close($checkStmt);

    if ($count > 0) {
        echo "exists"; // Supplier already exists
    } else {
        // Insert into the Supplier table
        $insertQuery = "INSERT INTO Supplier (name) VALUES (?)";
        $insertStmt = mysqli_prepare($conn, $insertQuery);
        mysqli_stmt_bind_param($insertStmt, "s", $supplierName);

        if (mysqli_stmt_execute($insertStmt)) {
            echo "saved"; // Supplier saved successfully
        } else {
            echo "error"; // Error saving supplier
        }

        mysqli_stmt_close($insertStmt);
    }

    mysqli_close($conn);
} else {
    echo "empty"; // Supplier name is empty
}
