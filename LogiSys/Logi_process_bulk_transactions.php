<?php
session_start();
header('Content-Type: application/json');

require_once 'logi_db.php';

function log_error_message(string $message): void {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/error.log');
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    if (!isset($_POST['selected'])) {
        echo json_encode(['success' => false, 'message' => 'No selected items provided']);
        exit;
    }

    // Parse selected payload (expects JSON array). Accept array of objects or IDs.
    $selectedRaw = $_POST['selected'];
    if (is_string($selectedRaw)) {
        $selected = json_decode($selectedRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in selected payload');
        }
    } else if (is_array($selectedRaw)) {
        $selected = $selectedRaw;
    } else {
        throw new Exception('Unsupported selected payload format');
    }

    // Normalize to an array of integer request IDs from items_requested
    $selectedRequestIds = [];
    foreach ($selected as $entry) {
        if (is_array($entry) && isset($entry['id'])) {
            $selectedRequestIds[] = intval($entry['id']);
        } else if (is_numeric($entry)) {
            $selectedRequestIds[] = intval($entry);
        }
    }

    // Deduplicate and validate
    $selectedRequestIds = array_values(array_unique(array_filter($selectedRequestIds, fn($v) => $v > 0)));
    if (empty($selectedRequestIds)) {
        echo json_encode(['success' => false, 'message' => 'No valid request IDs provided']);
        exit;
    }

    // Step 1: Fetch item_id and approved_quantity (and supporting fields) from items_requested for the selected IDs
    // Build IN clause safely using validated integers
    $inClause = implode(',', $selectedRequestIds);
    $selectSql = "SELECT id, item_id, item_name, approved_quantity, office_name, DATE(date_requested) AS date_requested, status
                  FROM items_requested
                  WHERE id IN ($inClause)";

    $requestsResult = mysqli_query($conn, $selectSql);
    if (!$requestsResult) {
        throw new Exception('Failed to fetch selected requests: ' . mysqli_error($conn));
    }

    $requests = [];
    while ($row = mysqli_fetch_assoc($requestsResult)) {
        // Only process approved and with a positive approved_quantity
        if (strtolower((string)$row['status']) !== 'approved') {
            throw new Exception('Request ID ' . $row['id'] . ' is not in Approved status');
        }
        $approvedQty = intval($row['approved_quantity']);
        if ($approvedQty <= 0) {
            throw new Exception('Request ID ' . $row['id'] . ' has no approved quantity');
        }
        $requests[] = [
            'id' => intval($row['id']),
            'item_id' => (string)$row['item_id'],
            'item_name' => (string)$row['item_name'],
            'approved_quantity' => $approvedQty,
            'office_name' => (string)$row['office_name'],
            'date' => (string)$row['date_requested'],
        ];
    }

    if (empty($requests)) {
        echo json_encode(['success' => false, 'message' => 'No matching approved requests found']);
        exit;
    }

    // Step 2: For each request, deduct approved_quantity from inventory_items.current_balance (matching by item_no = item_id)
    mysqli_autocommit($conn, false);

    $now = date('Y-m-d H:i:s');
    $processed = [];

    // Prepare statements reused in the loop
    $selectBalanceSql = 'SELECT current_balance FROM inventory_items WHERE item_no = ? FOR UPDATE';
    $selectBalanceStmt = mysqli_prepare($conn, $selectBalanceSql);
    if (!$selectBalanceStmt) {
        throw new Exception('Prepare select balance failed: ' . mysqli_error($conn));
    }

    $updateBalanceSql = 'UPDATE inventory_items SET current_balance = ? WHERE item_no = ?';
    $updateBalanceStmt = mysqli_prepare($conn, $updateBalanceSql);
    if (!$updateBalanceStmt) {
        throw new Exception('Prepare update balance failed: ' . mysqli_error($conn));
    }

    $insertTxnSql = "INSERT INTO inventory_transactions
        (item_no, item_name, quantity, requestor, created_at, transaction_type, previous_balance, reason, new_balance)
        VALUES (?, ?, ?, ?, ?, 'DEDUCTION', ?, 'COMMON USE SUPPLY', ?)";
    $insertTxnStmt = mysqli_prepare($conn, $insertTxnSql);
    if (!$insertTxnStmt) {
        throw new Exception('Prepare transaction insert failed: ' . mysqli_error($conn));
    }

    // Statement to mark the request as uploaded
    $markUploadedSql = 'UPDATE items_requested SET uploaded = 1 WHERE id = ?';
    $markUploadedStmt = mysqli_prepare($conn, $markUploadedSql);
    if (!$markUploadedStmt) {
        throw new Exception('Prepare mark uploaded failed: ' . mysqli_error($conn));
    }

    foreach ($requests as $req) {
        $itemNo = $req['item_id'];
        $approvedQty = $req['approved_quantity'];

        // Read current balance with row lock
        mysqli_stmt_bind_param($selectBalanceStmt, 's', $itemNo);
        if (!mysqli_stmt_execute($selectBalanceStmt)) {
            throw new Exception('Execute select balance failed for item ' . $itemNo . ': ' . mysqli_stmt_error($selectBalanceStmt));
        }
        $balanceResult = mysqli_stmt_get_result($selectBalanceStmt);
        $balanceRow = $balanceResult ? mysqli_fetch_assoc($balanceResult) : null;
        if (!$balanceRow) {
            throw new Exception('Item not found in inventory: ' . $itemNo);
        }

        $previousBalance = intval($balanceRow['current_balance']);
        $newBalance = $previousBalance - $approvedQty;
        if ($newBalance < 0) {
            throw new Exception('Insufficient stock for item ' . $req['item_name'] . ' (' . $itemNo . '). Available: ' . $previousBalance . ', Requested: ' . $approvedQty);
        }

        // Update inventory_items
        mysqli_stmt_bind_param($updateBalanceStmt, 'is', $newBalance, $itemNo);
        if (!mysqli_stmt_execute($updateBalanceStmt)) {
            throw new Exception('Execute update balance failed for item ' . $itemNo . ': ' . mysqli_stmt_error($updateBalanceStmt));
        }

        // Insert transaction log
        $quantity = $approvedQty;
        $requestor = $req['office_name'];
        mysqli_stmt_bind_param(
            $insertTxnStmt,
            'ssissii',
            $itemNo,
            $req['item_name'],
            $quantity,
            $requestor,
            $now,
            $previousBalance,
            $newBalance
        );
        if (!mysqli_stmt_execute($insertTxnStmt)) {
            throw new Exception('Execute insert transaction failed for item ' . $itemNo . ': ' . mysqli_stmt_error($insertTxnStmt));
        }

        // Mark the items_requested row as uploaded = 1
        $reqId = $req['id'];
        mysqli_stmt_bind_param($markUploadedStmt, 'i', $reqId);
        if (!mysqli_stmt_execute($markUploadedStmt)) {
            throw new Exception('Execute mark uploaded failed for request ' . $reqId . ': ' . mysqli_stmt_error($markUploadedStmt));
        }

        $processed[] = [
            'request_id' => $req['id'],
            'item_no' => $itemNo,
            'item_name' => $req['item_name'],
            'deducted' => $quantity,
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
        ];
    }

    mysqli_commit($conn);
    mysqli_autocommit($conn, true);

    echo json_encode([
        'success' => true,
        'message' => 'Bulk transactions processed successfully',
        'processed_count' => count($processed),
        'processed' => $processed,
    ]);
} catch (Throwable $e) {
    log_error_message('Bulk process failed: ' . $e->getMessage());
    if (isset($conn) && $conn) {
        @mysqli_rollback($conn);
        @mysqli_autocommit($conn, true);
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}


