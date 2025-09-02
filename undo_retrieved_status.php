<?php
require_once 'db_asset.php';

header('Content-Type: application/json');

if (!isset($_POST['tent_Id'])) {
    echo json_encode(['success' => false, 'message' => 'Tent ID is required']);
    exit;
}

$tentId = mysqli_real_escape_string($conn, $_POST['tent_Id']);

try {
    // Start transaction
    mysqli_begin_transaction($conn);

    // Get the tent_no value from the tent table using the ID
    $query = "SELECT tent_no FROM tent WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $tentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $tent_numbers = $row['tent_no'];
        
        // Split tent numbers if they contain commas
        $tent_nos = explode(',', $tent_numbers);
        
        // Update each tent number in tent_status table
        foreach ($tent_nos as $tent_no) {
            $tent_no = trim($tent_no); // Remove any whitespace
            if (!empty($tent_no)) {
                $updateStatusQuery = "UPDATE tent_status SET Status = 'For Retrieval' WHERE id = ?";
                $stmt = mysqli_prepare($conn, $updateStatusQuery);
                mysqli_stmt_bind_param($stmt, "s", $tent_no);
                mysqli_stmt_execute($stmt);
            }
        }
        
        // Update the main tent record
        $updateTentQuery = "UPDATE tent SET status = 'For Retrieval' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateTentQuery);
        mysqli_stmt_bind_param($stmt, "i", $tentId);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        throw new Exception('Tent record not found');
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
}

// Close the connection
mysqli_close($conn);
?>
