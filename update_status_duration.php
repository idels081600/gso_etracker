<?php
// Enhanced error handling and logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

header('Content-Type: application/json');
include 'db_asset.php';

// Function to log debug information
function logDebug($message, $data = null)
{
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $logMessage .= ' - Data: ' . json_encode($data);
    }
    error_log($logMessage);
}

// Function to send JSON response
function sendResponse($success, $message, $data = null)
{
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

// Function to convert comma-separated string to integer array
function sanitizeId($value)
{
    // If it's already an integer, return it as a single-item array
    if (is_int($value)) {
        return [$value];
    }

    // If it's already an array, process each value
    if (is_array($value)) {
        return array_map(function ($item) {
            return intval(trim(strval($item)));
        }, $value);
    }

    // Convert to string and trim whitespace
    $value = trim(strval($value));

    // If it contains commas, split and process all values
    if (strpos($value, ',') !== false) {
        $parts = explode(',', $value);
        return array_map(function ($item) {
            return intval(trim($item));
        }, $parts);
    }

    // Single value, return as array
    return [intval($value)];
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
        logDebug('Red date auto-update is disabled; ignoring payload');
    }

    // Process orange dates (due today items)
    if (isset($_POST['orangeDates'])) {
        $orangeDates = json_decode($_POST['orangeDates'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in orangeDates: ' . json_last_error_msg());
        }

        logDebug('Processing orange dates', $orangeDates);

        // Track processed tent numbers to avoid duplicates
        $processedTents = [];

        foreach ($orangeDates as $item) {
            if (!isset($item['tent_no']) || !isset($item['id'])) {
                $errors[] = 'Missing tent_no or id in orange date item';
                continue;
            }

            // Sanitize and convert values
            $tent_nos = sanitizeId($item['tent_no']);
            $request_id = intval($item['id']); // Request ID from the item

            logDebug("Processing request", [
                'raw_tent_no' => $item['tent_no'],
                'sanitized_tent_nos' => $tent_nos,
                'request_id' => $request_id,
                'count' => count($tent_nos)
            ]);

            // Validate the request ID
            if ($request_id <= 0) {
                $errors[] = "Invalid request id ({$request_id})";
                continue;
            }

            // Process each tent for this request
            foreach ($tent_nos as $index => $tent_no) {

                // Skip if already processed to avoid duplicates
                if (isset($processedTents[$tent_no])) {
                    logDebug("Tent {$tent_no} already processed, skipping duplicate");
                    continue;
                }

                // Mark as processed
                $processedTents[$tent_no] = true;

                logDebug("Processing tent", [
                    'tent_no' => $tent_no,
                    'request_id' => $request_id,
                    'iteration' => $index + 1,
                    'total_tents' => count($tent_nos)
                ]);

                // Additional validation
                if ($tent_no <= 0) {
                    $errors[] = "Invalid tent_no ({$tent_no}) for request ({$request_id})";
                    continue;
                }

                // Check current status of the tent before updating
                $checkQuery = "SELECT id, Status FROM tent_status WHERE id = ?";
                $checkStmt = mysqli_prepare($conn, $checkQuery);

                if (!$checkStmt) {
                    $errors[] = "Failed to prepare status check query: " . mysqli_error($conn);
                    continue;
                }

                mysqli_stmt_bind_param($checkStmt, "i", $tent_no);
                mysqli_stmt_execute($checkStmt);
                $result = mysqli_stmt_get_result($checkStmt);
                $currentRecord = mysqli_fetch_assoc($result);
                mysqli_stmt_close($checkStmt);

                logDebug("Current tent status check", [
                    'tent_no' => $tent_no,
                    'found_record' => $currentRecord,
                    'current_status' => $currentRecord ? $currentRecord['Status'] : 'NOT_FOUND'
                ]);

                if (!$currentRecord) {
                    $errors[] = "Tent {$tent_no} not found in tent_status table";
                    continue;
                }

                if ($currentRecord['Status'] === 'For Retrieval') {
                    logDebug("Tent {$tent_no} already in 'For Retrieval' status, skipping");
                    continue;
                }

                if ($currentRecord['Status'] !== 'Installed') {
                    $errors[] = "Tent {$tent_no} has status '{$currentRecord['Status']}', must be 'Installed' to update to 'For Retrieval'";
                    continue;
                }

                // Update tent_status table first
                $statusQuery = "UPDATE tent_status SET Status = 'For Retrieval' WHERE id = ? AND Status = 'Installed'";
                $statusStmt = mysqli_prepare($conn, $statusQuery);

                if (!$statusStmt) {
                    $errors[] = "Failed to prepare tent_status update: " . mysqli_error($conn);
                    continue;
                }

                mysqli_stmt_bind_param($statusStmt, "i", $tent_no);

                if (!mysqli_stmt_execute($statusStmt)) {
                    $errors[] = "Failed to update tent_status for tent {$tent_no}: " . mysqli_stmt_error($statusStmt);
                    mysqli_stmt_close($statusStmt);
                    continue;
                }

                $statusAffectedRows = mysqli_stmt_affected_rows($statusStmt);
                mysqli_stmt_close($statusStmt);

                if ($statusAffectedRows === 0) {
                    logDebug("No rows updated in tent_status for tent {$tent_no}");
                    continue;
                }

                // Update tent table
                $tentQuery = "UPDATE tent SET status = 'For Retrieval' WHERE id = ? AND status = 'Installed'";
                $tentStmt = mysqli_prepare($conn, $tentQuery);

                if (!$tentStmt) {
                    $errors[] = "Failed to prepare tent update: " . mysqli_error($conn);
                    continue;
                }

                mysqli_stmt_bind_param($tentStmt, "i", $request_id);

                if (!mysqli_stmt_execute($tentStmt)) {
                    $errors[] = "Failed to update tent table for tent {$tent_no}: " . mysqli_stmt_error($tentStmt);
                    mysqli_stmt_close($tentStmt);
                    continue;
                }

                $tentAffectedRows = mysqli_stmt_affected_rows($tentStmt);
                mysqli_stmt_close($tentStmt);

                // Record successful update
                if ($statusAffectedRows > 0) {
                    $updatedItems[] = [
                        'tent_no' => $tent_no,
                        'request_id' => $request_id,
                        'new_status' => 'For Retrieval',
                        'type' => 'due_today',
                        'previous_status' => $currentRecord['Status'],
                        'tent_table_updated' => $tentAffectedRows > 0
                    ];

                    logDebug("Successfully updated tent {$tent_no} to For Retrieval", [
                        'tent_no' => $tent_no,
                        'previous_status' => $currentRecord['Status'],
                        'tent_status_rows' => $statusAffectedRows,
                        'tent_table_rows' => $tentAffectedRows
                    ]);
                }
            }
        }
    }

    // Commit transaction if we have updates or no critical errors
    if (count($updatedItems) > 0) {
        mysqli_commit($conn);

        $message = "Successfully updated " . count($updatedItems) . " tent(s)";
        if (!empty($errors)) {
            $message .= " (with " . count($errors) . " warnings)";
        }

        sendResponse(true, $message, [
            'updated_items' => $updatedItems,
            'warnings' => $errors,
            'total_updated' => count($updatedItems)
        ]);
    } else {
        // Rollback if no updates and we have errors
        mysqli_rollback($conn);

        $message = empty($errors) ? "No updates needed" : "Update failed: " . implode(', ', $errors);
        sendResponse(false, $message, [
            'errors' => $errors
        ]);
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
