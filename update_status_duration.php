<?php
// Enhanced error handling and logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

header('Content-Type: application/json');

// Include your database connection file
include 'db_asset.php';

// Function to log debug information
function logDebug($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $logMessage .= ' - Data: ' . json_encode($data);
    }
    error_log($logMessage);
}

// Function to send JSON response
function sendResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Function to convert comma-separated string to integer or return single integer
function sanitizeId($value) {
    // If it's already an integer, return it
    if (is_int($value)) {
        return $value;
    }
    
    // Convert to string and trim whitespace
    $value = trim(strval($value));
    
    // If it contains commas, take only the first value
    if (strpos($value, ',') !== false) {
        $parts = explode(',', $value);
        $value = trim($parts[0]);
    }
    
    // Convert to integer
    return intval($value);
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method');
    }

    // Start transaction for data consistency
    mysqli_autocommit($conn, false);
    
    $updatedItems = [];
    $errors = [];

    // Process red dates (overdue items) - disabled per requirements
    if (isset($_POST['redDates'])) {
        // Intentionally ignore red date auto-updates
        // Keep a minimal log for observability without changing data
        logDebug('Red date auto-update is disabled; ignoring payload');
        // Do not decode or process to avoid side-effects
    }
    
    // Process orange dates (due today items)
    if (isset($_POST['orangeDates'])) {
        $orangeDates = json_decode($_POST['orangeDates'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in orangeDates: ' . json_last_error_msg());
        }
        
        logDebug('Processing orange dates', $orangeDates);
        
        foreach ($orangeDates as $item) {
            if (!isset($item['tent_no']) || !isset($item['id'])) {
                $errors[] = 'Missing tent_no or id in orange date item';
                continue;
            }
            
            // Sanitize and convert values
            $tent_no = sanitizeId($item['tent_no']);
            $id = sanitizeId($item['id']);
            
            // Additional validation
            if ($tent_no <= 0 || $id <= 0) {
                $errors[] = "Invalid tent_no ({$tent_no}) or id ({$id}) in orange date item";
                continue;
            }
            
            logDebug("Processing orange date item", ['tent_no' => $tent_no, 'id' => $id]);
            
            // Update tent table
            $tentQuery = "UPDATE tent SET status = 'For Retrieval' WHERE id = ? AND status IN ('Installed', 'Pending')";
            $tentStmt = mysqli_prepare($conn, $tentQuery);
            
            if (!$tentStmt) {
                $errors[] = "Prepare failed for tent update: " . mysqli_error($conn);
                continue;
            }
            
            mysqli_stmt_bind_param($tentStmt, "i", $id);
            
            if (!mysqli_stmt_execute($tentStmt)) {
                $errors[] = "Failed to update tent {$tent_no}: " . mysqli_stmt_error($tentStmt);
                continue;
            }
            
            $affectedRows = mysqli_stmt_affected_rows($tentStmt);
            mysqli_stmt_close($tentStmt);
            
            if ($affectedRows > 0) {
                // Update tent_status table
                $statusQuery = "UPDATE tent_status SET Status = 'For Retrieval' WHERE id = ?";
                $statusStmt = mysqli_prepare($conn, $statusQuery);
                
                if ($statusStmt) {
                    mysqli_stmt_bind_param($statusStmt, "i", $tent_no);
                    mysqli_stmt_execute($statusStmt);
                    mysqli_stmt_close($statusStmt);
                }
                
                $updatedItems[] = [
                    'tent_no' => $tent_no,
                    'id' => $id,
                    'new_status' => 'For Retrieval',
                    'type' => 'due_today'
                ];
                
                logDebug("Successfully updated tent {$tent_no} to For Retrieval");
            } else {
                logDebug("No rows affected for tent {$tent_no} - may already be updated or not found");
            }
        }
    }
    
    // Commit transaction if no critical errors
    if (empty($errors) || count($updatedItems) > 0) {
        mysqli_commit($conn);
        
        $message = count($updatedItems) > 0 
            ? "Successfully updated " . count($updatedItems) . " tent(s)"
            : "No updates needed";
            
        if (!empty($errors)) {
            $message .= " (with " . count($errors) . " warnings)";
        }
        
        sendResponse(true, $message, [
            'updated_items' => $updatedItems,
            'warnings' => $errors,
            'total_updated' => count($updatedItems)
        ]);
    } else {
        // Rollback on errors
        mysqli_rollback($conn);
        sendResponse(false, "Update failed: " . implode(', ', $errors));
    }
} catch (Exception $e) {
    // Rollback transaction on exception
    mysqli_rollback($conn);
    
    logDebug('Exception in update_status_duration.php', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    sendResponse(false, "System error: " . $e->getMessage());
} finally {
    // Restore autocommit
    mysqli_autocommit($conn, true);
    
    // Close connection
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>