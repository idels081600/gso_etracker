<?php
require_once 'leave_db.php'; // Include your database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['title'])) {
    $id = intval($_POST['id']); // Sanitize input
    $title = trim($_POST['title']); // Get the title and trim whitespace

    try {
        // Start transaction
        mysqli_begin_transaction($conn);

        if ($title === 'CTO') {
            // Delete from cto table
            $stmt = mysqli_prepare($conn, "DELETE FROM cto WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
        } elseif ($title === 'SPL' || $title === 'FL') {
            // Delete from leave_reg table
            $stmt = mysqli_prepare($conn, "DELETE FROM leave_reg WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
        } else {
            // If the title doesn't match, throw an exception
            throw new Exception('Invalid title specified');
        }

        // Commit transaction
        mysqli_commit($conn);
        echo 'success';
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo 'error: ' . $e->getMessage();
    }
} else {
    echo 'invalid request';
}
