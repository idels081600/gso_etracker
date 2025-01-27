<?php
require_once 'leave_db.php';

if (isset($_POST['title']) && isset($_POST['name']) && isset($_POST['dates'])) {
    $leaveTitle = $_POST['title'];
    $leaveName = $_POST['name'];
    $leaveDates = $_POST['dates'];

    // Check for existing leave entry
    $checkDuplicate = "SELECT id FROM leave_reg WHERE title = ? AND name = ? AND dates = ?";
    $checkStmt = mysqli_prepare($conn, $checkDuplicate);
    mysqli_stmt_bind_param($checkStmt, "sss", $leaveTitle, $leaveName, $leaveDates);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);

    if (mysqli_stmt_num_rows($checkStmt) > 0) {
        echo "This leave record already exists";
    } else {
        $sql = "INSERT INTO leave_reg (title, name, dates) VALUES (?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sss", $leaveTitle, $leaveName, $leaveDates);

            if (mysqli_stmt_execute($stmt)) {
                echo "success";
            } else {
                echo "Error saving leave: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_stmt_close($checkStmt);
} else {
    echo "Missing required fields";
}

mysqli_close($conn);
