<?php
require_once 'logi_display_data.php'; // Include database connection
require_once __DIR__ . '/Logi_security.php';
logi_require_admin_csrf($conn);

header('Content-Type: application/json');

try {
    $item_name = trim((string)($_POST['itemName'] ?? $_POST['item_name'] ?? ''));
    $item_no = trim((string)($_POST['itemNo'] ?? $_POST['item_no'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $requestor = trim((string)($_POST['requestor_name'] ?? $_POST['requestor'] ?? ''));
    $transaction_type = 'DEDUCTION';

    if ($item_no === '' || $item_name === '' || $reason === '' || $requestor === '') {
        throw new Exception('Item, quantity, reason, and Requestor/Department are required');
    }

    if ($quantity <= 0) {
        throw new Exception('Quantity must be greater than 0');
    }

    $conn->begin_transaction();

    try {
        $select_sql = 'SELECT item_name, current_balance FROM inventory_items WHERE item_no = ? FOR UPDATE';
        $stmt = $conn->prepare($select_sql);
        $stmt->bind_param('s', $item_no);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();

        if (!$item) {
            throw new Exception('Inventory item not found');
        }

        $item_name = $item['item_name'];
        $previous_balance = (int)$item['current_balance'];
        $new_balance = $previous_balance - $quantity;

        if ($new_balance < 0) {
            throw new Exception('Insufficient stock for this transaction');
        }

        $insert_sql = 'INSERT INTO inventory_transactions (
            item_name,
            item_no,
            quantity,
            previous_balance,
            new_balance,
            reason,
            transaction_type,
            requestor,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())';

        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param(
            'ssiiisss',
            $item_name,
            $item_no,
            $quantity,
            $previous_balance,
            $new_balance,
            $reason,
            $transaction_type,
            $requestor
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert transaction');
        }

        $update_sql = 'UPDATE inventory_items SET current_balance = ?, updated_at = NOW() WHERE item_no = ?';
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('is', $new_balance, $item_no);

        if (!$stmt->execute() || $stmt->affected_rows < 1) {
            throw new Exception('Failed to update inventory');
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Stock out transaction completed successfully',
        'previous_balance' => $previous_balance,
        'new_balance' => $new_balance
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
