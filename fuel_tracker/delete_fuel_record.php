<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../db_asset.php';

// Set content type to JSON
header('Content-Type: application/json');

// Function to send JSON response
function sendResponse($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Function to log errors
function logError($message) {
    error_log("Delete Fuel Record API Error: " . $message);
}

// Function to delete fuel record
function deleteFuelRecord($id) {
    global $conn;
    
    try {
        // Check if database connection exists
        if (!isset($conn) || !$conn) {
            throw new Exception("Database connection not available");
        }
        
        // Validate ID
        if (!is_numeric($id) || $id <= 0) {
            throw new Exception("Invalid record ID provided");
        }
        
        // Check if record exists first
        $checkSQL = "SELECT id, vehicle, plate_no, date FROM fuel WHERE id = ?";
        $checkStmt = mysqli_prepare($conn, $checkSQL);
        
        if (!$checkStmt) {
            throw new Exception("Failed to prepare check statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($checkStmt, "i", $id);
        mysqli_stmt_execute($checkStmt);
        $result = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($result) == 0) {
            mysqli_stmt_close($checkStmt);
            throw new Exception("Fuel record with ID $id not found");
        }
        
        // Get record details for logging
        $recordDetails = mysqli_fetch_assoc($result);
        mysqli_stmt_close($checkStmt);
        
        // Delete the record
        $deleteSQL = "DELETE FROM fuel WHERE id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteSQL);
        
        if (!$deleteStmt) {
            throw new Exception("Failed to prepare delete statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($deleteStmt, "i", $id);
        
        if (mysqli_stmt_execute($deleteStmt)) {
            $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
            mysqli_stmt_close($deleteStmt);
            
            if ($affectedRows > 0) {
                logError("Successfully deleted fuel record - ID: $id, Vehicle: " . $recordDetails['vehicle'] . ", Date: " . $recordDetails['date']);
                
                return [
                    'success' => true,
                    'message' => 'Fuel record deleted successfully',
                    'data' => [
                        'deleted_id' => $id,
                        'deleted_record' => $recordDetails
                    ]
                ];
            } else {
                throw new Exception("No records were deleted. Record may have already been removed.");
            }
        } else {
            throw new Exception("Failed to execute delete statement: " . mysqli_stmt_error($deleteStmt));
        }
        
    } catch (Exception $e) {
        logError("Error in deleteFuelRecord: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error deleting fuel record: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

// Function to delete multiple fuel records
function deleteMultipleFuelRecords($ids) {
    global $conn;
    
    try {
        if (!is_array($ids) || empty($ids)) {
            throw new Exception("No record IDs provided for deletion");
        }
        
        // Validate all IDs
        foreach ($ids as $id) {
            if (!is_numeric($id) || $id <= 0) {
                throw new Exception("Invalid record ID provided: $id");
            }
        }
        
        $deletedCount = 0;
        $errors = [];
        $deletedRecords = [];
        
        // Delete each record
        foreach ($ids as $id) {
            $result = deleteFuelRecord($id);
            if ($result['success']) {
                $deletedCount++;
                $deletedRecords[] = $result['data']['deleted_record'];
            } else {
                $errors[] = "ID $id: " . $result['message'];
            }
        }
        
        if ($deletedCount > 0) {
            $message = "Successfully deleted $deletedCount record(s)";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }
            
            return [
                'success' => true,
                'message' => $message,
                'data' => [
                    'deleted_count' => $deletedCount,
                    'total_requested' => count($ids),
                    'deleted_records' => $deletedRecords,
                    'errors' => $errors
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No records were deleted. Errors: ' . implode(', ', $errors),
                'data' => null
            ];
        }
        
    } catch (Exception $e) {
        logError("Error in deleteMultipleFuelRecords: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error deleting multiple fuel records: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid JSON data: ' . json_last_error_msg(),
            'data' => null
        ]);
    }
    
    // Log the received data for debugging
    logError("Received delete request: " . print_r($data, true));
    
    // Check if single ID or multiple IDs
    if (isset($data['id'])) {
        // Single record deletion
        $response = deleteFuelRecord($data['id']);
    } elseif (isset($data['ids']) && is_array($data['ids'])) {
        // Multiple records deletion
        $response = deleteMultipleFuelRecords($data['ids']);
    } else {
        $response = [
            'success' => false,
            'message' => 'No record ID(s) provided for deletion',
            'data' => null
        ];
    }
    
    sendResponse($response);
    
} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Handle DELETE method (alternative approach)
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        $response = deleteFuelRecord($id);
        sendResponse($response);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'No valid record ID provided in DELETE request',
            'data' => null
        ]);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request for testing
    sendResponse([
        'success' => true,
        'message' => 'Delete Fuel Record API is working',
        'methods' => ['POST', 'DELETE'],
        'post_format' => [
            'single_delete' => ['id' => 123],
            'multiple_delete' => ['ids' => [123, 456, 789]]
        ],
        'delete_format' => 'DELETE /delete_fuel_record.php?id=123'
    ]);
    
} else {
    // Method not allowed
    http_response_code(405);
    sendResponse([
        'success' => false,
        'message' => 'Method not allowed. Only POST and DELETE requests are supported.',
        'allowed_methods' => ['POST', 'DELETE', 'GET']
    ]);
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
?>
