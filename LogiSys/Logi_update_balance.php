<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/logi_db.php';
require_once __DIR__ . '/Logi_security.php';
logi_require_admin_csrf($conn);

try {
    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
        http_response_code(401);
        throw new RuntimeException('Please sign in before updating inventory.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Invalid request data.');
    }

    $itemId = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);
    $rawBalance = $data['new_balance'] ?? null;
    if (!$itemId || filter_var($rawBalance, FILTER_VALIDATE_INT) === false) {
        throw new InvalidArgumentException('Item and a whole-number balance are required.');
    }

    $newBalance = (int)$rawBalance;
    if ($newBalance < 0) {
        throw new InvalidArgumentException('Balance cannot be negative.');
    }

    $reason = trim((string)($data['reason'] ?? 'Manual balance update'));
    if ($reason === '') {
        $reason = 'Manual balance update';
    }
    $actor = trim((string)$_SESSION['username']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id, item_no, item_name, current_balance FROM inventory_items WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        if (!$item) {
            throw new RuntimeException('Inventory item not found.');
        }

        $previousBalance = (int)$item['current_balance'];
        if ($previousBalance === $newBalance) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'The balance is already ' . $newBalance . '.',
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'changed' => false,
            ]);
            exit;
        }

        $quantity = abs($newBalance - $previousBalance);
        $type = 'ADJUSTMENT';
        $reference = 'BAL-' . $itemId . '-' . date('YmdHis');

        $stmt = $conn->prepare(
            'INSERT INTO inventory_transactions
             (item_no, item_name, transaction_type, quantity, previous_balance, new_balance, reason, requestor, reference_no, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())'
        );
        $itemNo = (string)$item['item_no'];
        $itemName = (string)$item['item_name'];
        $stmt->bind_param(
            'sssiiissss',
            $itemNo,
            $itemName,
            $type,
            $quantity,
            $previousBalance,
            $newBalance,
            $reason,
            $actor,
            $reference,
            $actor
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to record the balance adjustment.');
        }
        $transactionId = $conn->insert_id;

        $stmt = $conn->prepare('UPDATE inventory_items SET current_balance = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('ii', $newBalance, $itemId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            throw new RuntimeException('Failed to update the inventory balance.');
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Balance updated and posted to the stock card.',
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'transaction_id' => $transactionId,
            'changed' => true,
        ]);
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
} catch (Throwable $exception) {
    if (http_response_code() < 400) {
        http_response_code(422);
    }
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
} finally {
    $conn->close();
}
