<?php
require_once 'logi_db.php'; // Include database connection

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get office ID from POST data
    $office_id = intval($_POST['office_id'] ?? 0);
    
    // Validate office ID
    if ($office_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid office ID']);
        exit;
    }
    
    // Check if office exists
    $check_query = "SELECT id, office_name FROM office_balances WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    if (!$check_stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($check_stmt, "i", $office_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) === 0) {
        mysqli_stmt_close($check_stmt);
        echo json_encode(['success' => false, 'message' => 'Office not found']);
        exit;
    }
    
    $office_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    // Check if office has any items assigned (optional - you might want to prevent deletion if items exist)
    $items_check_query = "SELECT COUNT(*) as item_count FROM office_balance_items WHERE office_balance_id = ?";
    $items_check_stmt = mysqli_prepare($conn, $items_check_query);
    if ($items_check_stmt) {
        mysqli_stmt_bind_param($items_check_stmt, "i", $office_id);
        mysqli_stmt_execute($items_check_stmt);
        $items_result = mysqli_stmt_get_result($items_check_stmt);
        $items_data = mysqli_fetch_assoc($items_result);
        mysqli_stmt_close($items_check_stmt);
        
        // Uncomment the following lines if you want to prevent deletion when items exist
        /*
        if ($items_data['item_count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete office with assigned items. Please remove all items first.']);
            exit;
        }
        */
    }
    
    // Start transaction
    mysqli_autocommit($conn, false);
    
    try {
        // Delete related items first (if any)
        $delete_items_query = "DELETE FROM office_balance_items WHERE office_balance_id = ?";
        $delete_items_stmt = mysqli_prepare($conn, $delete_items_query);
        if ($delete_items_stmt) {
            mysqli_stmt_bind_param($delete_items_stmt, "i", $office_id);
            mysqli_stmt_execute($delete_items_stmt);
            mysqli_stmt_close($delete_items_stmt);
        }
        
        // Delete the office
        $delete_query = "DELETE FROM office_balances WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        if (!$delete_stmt) {
            throw new Exception('Database prepare error: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($delete_stmt, "i", $office_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            mysqli_stmt_close($delete_stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Office "' . $office_data['office_name'] . '" deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete office: ' . mysqli_stmt_error($delete_stmt));
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in Logi_delete_office.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.',
        'debug' => $e->getMessage() // Remove this in production
    ]);
} finally {
    // Restore autocommit
    mysqli_autocommit($conn, true);
    
    // Close connection
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>