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

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method');
    }

    // Start transaction for data consistency
    mysqli_autocommit($conn, false);
    
    $updatedItems = [];
    $errors = [];

    // Process red dates (overdue items)
    if (isset($_POST['redDates'])) {
        $redDates = json_decode($_POST['redDates'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in redDates: ' . json_last_error_msg());
        }
        
        logDebug('Processing red dates', $redDates);
        
        foreach ($redDates as $item) {
            if (!isset($item['tent_no']) || !isset($item['id'])) {
                $errors[] = 'Missing tent_no or id in red date item';
                continue;
            }
            
            $tent_no = mysqli_real_escape_string($conn, $item['tent_no']);
            $id = mysqli_real_escape_string($conn, $item['id']);
            
            // Update tent table
            $tentQuery = "UPDATE tent SET status = 'Retrieved' WHERE id = ? AND status IN ('Installed', 'Pending')";
            $tentStmt = mysqli_prepare($conn, $tentQuery);
            
            if (!$tentStmt) {
                $errors[] = "Prepare failed for tent update: " . mysqli_error($conn);
                continue;
            }
            
            mysqli_stmt_bind_param($tentStmt, "s", $id);
            
            if (!mysqli_stmt_execute($tentStmt)) {
                $errors[] = "Failed to update tent {$tent_no}: " . mysqli_stmt_error($tentStmt);
                continue;
            }
            
            $affectedRows = mysqli_stmt_affected_rows($tentStmt);
            mysqli_stmt_close($tentStmt);
            
            if ($affectedRows > 0) {
                // Update tent_status table
                $statusQuery = "UPDATE tent_status SET Status = 'On Stock' WHERE id = ?";
                $statusStmt = mysqli_prepare($conn, $statusQuery);
                
                if ($statusStmt) {
                    mysqli_stmt_bind_param($statusStmt, "s", $tent_no);
                    mysqli_stmt_execute($statusStmt);
                    mysqli_stmt_close($statusStmt);
                }
                
                $updatedItems[] = [
                    'tent_no' => $tent_no,
                    'id' => $id,
                    'new_status' => 'Retrieved',
                    'type' => 'overdue'
                ];
                
                logDebug("Successfully updated tent {$tent_no} to Retrieved");
            } else {
                logDebug("No rows affected for tent {$tent_no} - may already be updated or not found");
            }
        }
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
            
            $tent_no = mysqli_real_escape_string($conn, $item['tent_no']);
            $id = mysqli_real_escape_string($conn, $item['id']);
            
            // Update tent table
            $tentQuery = "UPDATE tent SET status = 'For Retrieval' WHERE id = ? AND status IN ('Installed', 'Pending')";
            $tentStmt = mysqli_prepare($conn, $tentQuery);
            
            if (!$tentStmt) {
                $errors[] = "Prepare failed for tent update: " . mysqli_error($conn);
                continue;
            }
            
            mysqli_stmt_bind_param($tentStmt, "s", $id);
            
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
                    mysqli_stmt_bind_param($statusStmt, "s", $tent_no);
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
