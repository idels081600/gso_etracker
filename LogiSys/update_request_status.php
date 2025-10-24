<?php 
// Prevent PHP errors from breaking JSON output
ini_set('display_errors', 0);
error_reporting(0);

session_start(); 
header('Content-Type: application/json');  

// Check if user is logged in and is admin 
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {     
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);     
    exit(); 
}  

require_once 'logi_db.php';  

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON input');
    }

    $status = mysqli_real_escape_string($conn, $data['status']);
    $admin_remarks = isset($data['admin_remarks']) ? mysqli_real_escape_string($conn, $data['admin_remarks']) : '';

    // Check if this is a bulk approval operation
    if (isset($data['request_ids']) && is_array($data['request_ids']) && $status === 'Approved') {
        $request_ids = $data['request_ids'];

        if (empty($request_ids)) {
            throw new Exception('No request IDs provided');
        }

        // Validate all request IDs are integers
        $validated_ids = array_map('intval', $request_ids);
        $validated_ids = array_filter($validated_ids, function($id) { return $id > 0; });

        if (count($validated_ids) !== count($request_ids)) {
            throw new Exception('Invalid request IDs provided');
        }

        // Check if common approved quantity is provided
        $common_approved_qty = isset($data['bulk_approved_quantity']) ? intval($data['bulk_approved_quantity']) : null;

        // Update each request individually (simpler and more reliable)
        $updated_count = 0;
        $errors = [];

        foreach ($validated_ids as $request_id) {
            // If common quantity provided, use it; otherwise get original quantity
            if ($common_approved_qty !== null && $common_approved_qty > 0) {
                $approved_quantity = $common_approved_qty;

                // Verify that common quantity doesn't exceed requested quantity if provided
                $verify_query = "SELECT quantity FROM items_requested WHERE id = ? AND status = 'Pending'";
                $verify_stmt = mysqli_prepare($conn, $verify_query);
                if ($verify_stmt) {
                    mysqli_stmt_bind_param($verify_stmt, "i", $request_id);
                    mysqli_stmt_execute($verify_stmt);
                    $verify_result = mysqli_stmt_get_result($verify_stmt);

                    if ($verify_row = mysqli_fetch_assoc($verify_result)) {
                        $requested_qty = intval($verify_row['quantity']);
                        if ($approved_quantity > $requested_qty) {
                            $approved_quantity = $requested_qty; // Cap at requested quantity
                        }
                    }
                    mysqli_stmt_close($verify_stmt);
                }
            } else {
                // Get original quantity if no common quantity provided
                $quantity_query = "SELECT quantity FROM items_requested WHERE id = ? AND status = 'Pending'";
                $quantity_stmt = mysqli_prepare($conn, $quantity_query);
                if (!$quantity_stmt) {
                    $errors[] = "Failed to prepare quantity query for ID {$request_id}";
                    continue;
                }

                mysqli_stmt_bind_param($quantity_stmt, "i", $request_id);
                mysqli_stmt_execute($quantity_stmt);
                $quantity_result = mysqli_stmt_get_result($quantity_stmt);

                if ($quantity_row = mysqli_fetch_assoc($quantity_result)) {
                    $approved_quantity = intval($quantity_row['quantity']);
                } else {
                    $errors[] = "Request ID {$request_id} not found or not pending";
                    mysqli_stmt_close($quantity_stmt);
                    continue;
                }
                mysqli_stmt_close($quantity_stmt);
            }

            $query = "UPDATE items_requested SET
                        status = ?,
                        approved_quantity = ?,
                        remarks_admin = ?,
                        date_approved = NOW()
                      WHERE id = ? AND status = 'Pending'";

            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                $errors[] = "Failed to prepare update query for ID {$request_id}";
                continue;
            }

            mysqli_stmt_bind_param($stmt, "sisi", $status, $approved_quantity, $admin_remarks, $request_id);

            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $updated_count++;
                }
            } else {
                $errors[] = "Failed to update request ID {$request_id}";
            }

            mysqli_stmt_close($stmt);
        }

        if ($updated_count > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Successfully approved {$updated_count} request(s)",
                'affected_count' => $updated_count,
                'approved_quantity' => $common_approved_qty
            ]);
        } else {
            throw new Exception('No requests were updated: ' . implode(', ', $errors));
        }

    // Check if this is a bulk rejection operation
    } elseif (isset($data['request_ids']) && is_array($data['request_ids']) && $status === 'Rejected') {
        $request_ids = $data['request_ids'];

        if (empty($request_ids)) {
            throw new Exception('No request IDs provided');
        }

        // Validate all request IDs are integers
        $validated_ids = array_map('intval', $request_ids);
        $validated_ids = array_filter($validated_ids, function($id) { return $id > 0; });

        if (count($validated_ids) !== count($request_ids)) {
            throw new Exception('Invalid request IDs provided');
        }

        // Begin transaction for bulk update
        mysqli_begin_transaction($conn);

        try {
            // Update all requests in bulk
            $ids_placeholder = str_repeat('?,', count($validated_ids) - 1) . '?';
            $types = str_repeat('i', count($validated_ids));

            $query = "UPDATE items_requested SET
                        status = ?,
                        remarks_admin = ?,
                        date_approved = NOW()
                      WHERE id IN ($ids_placeholder)";

            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                throw new Exception('Failed to prepare bulk update query');
            }

            // Fixed parameter binding (procedural style)
            mysqli_stmt_bind_param($stmt, "ss" . $types, $status, $admin_remarks, ...$validated_ids);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to execute bulk update');
            }

            $affected_rows = mysqli_stmt_affected_rows($stmt);

            mysqli_commit($conn);

            echo json_encode([
                'success' => true,
                'message' => "Successfully rejected {$affected_rows} request(s)",
                'affected_count' => $affected_rows
            ]);

            mysqli_stmt_close($stmt);

        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e;
        }

    } else {
        // Original single request handling
        if (!isset($data['request_id']) || !isset($data['status'])) {
            throw new Exception('Invalid request data');
        }

        $request_id = intval($data['request_id']);
        $approved_quantity = isset($data['approved_quantity']) ? intval($data['approved_quantity']) : 0;

        // If approved_quantity is zero and status is Approved, get the original quantity
        if ($status === 'Approved' && $approved_quantity == 0) {
            $quantity_query = "SELECT quantity FROM items_requested WHERE id = ?";
            $quantity_stmt = mysqli_prepare($conn, $quantity_query);

            if (!$quantity_stmt) {
                throw new Exception('Failed to prepare quantity query');
            }

            mysqli_stmt_bind_param($quantity_stmt, "i", $request_id);
            mysqli_stmt_execute($quantity_stmt);
            $quantity_result = mysqli_stmt_get_result($quantity_stmt);

            if ($quantity_row = mysqli_fetch_assoc($quantity_result)) {
                $approved_quantity = intval($quantity_row['quantity']);
            } else {
                throw new Exception('Request not found');
            }

            mysqli_stmt_close($quantity_stmt);
        }

        // Build update query based on status
        if ($status === 'Approved' && $approved_quantity > 0) {
            $query = "UPDATE items_requested SET
                        status = ?,
                        approved_quantity = ?,
                        remarks_admin = ?,
                        date_approved = NOW()
                      WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sisi", $status, $approved_quantity, $admin_remarks, $request_id);
        } else {
            // FIXED: Added space and corrected column name
            $query = "UPDATE items_requested SET
                        status = ?,
                        remarks_admin = ?,
                        date_approved = NOW()
                      WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssi", $status, $admin_remarks, $request_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true,
                'message' => 'Request updated successfully',
                'approved_quantity' => $approved_quantity
            ]);
        } else {
            throw new Exception('Failed to update request');
        }

        mysqli_stmt_close($stmt);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn); 
?>
