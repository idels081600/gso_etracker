<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/logi_db.php';
require_once __DIR__ . '/Logi_security.php';
logi_require_admin_csrf($conn);

function hasInventoryColumn(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items' AND COLUMN_NAME = ?");
    $stmt->bind_param('s', $column);
    $stmt->execute();
    return ((int)($stmt->get_result()->fetch_assoc()['count'] ?? 0)) > 0;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Invalid request method. Only POST requests are allowed.');
    }
    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
        http_response_code(401);
        throw new RuntimeException('Please sign in before updating inventory.');
    }

    $itemNo = trim((string)($_POST['itemNo'] ?? ''));
    $itemName = trim((string)($_POST['itemName'] ?? ''));
    $rackNo = trim((string)($_POST['rackNo'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? ''));
    $rawBalance = $_POST['balance'] ?? null;
    $status = trim((string)($_POST['status'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $lowStockThreshold = max(1, (int)($_POST['lowStockThreshold'] ?? 10));
    $actor = trim((string)$_SESSION['username']);

    if ($itemNo === '' || $itemName === '' || $unit === '' || $status === '') {
        throw new InvalidArgumentException('All required fields must be filled.');
    }
    if (filter_var($rawBalance, FILTER_VALIDATE_INT) === false || (int)$rawBalance < 0) {
        throw new InvalidArgumentException('Balance must be a whole number of zero or greater.');
    }
    $currentBalance = (int)$rawBalance;

    if ($currentBalance === 0 && $status !== 'Discontinued') {
        $status = 'Out of Stock';
    } elseif ($currentBalance <= $lowStockThreshold && $status !== 'Discontinued') {
        $status = 'Low Stock';
    } elseif ($status !== 'Discontinued') {
        $status = 'Available';
    }

    $hasLowStockThreshold = hasInventoryColumn($conn, 'low_stock_threshold');
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id, item_no, item_name, current_balance FROM inventory_items WHERE item_no = ? FOR UPDATE');
        $stmt->bind_param('s', $itemNo);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if (!$existing) {
            throw new RuntimeException('Item not found in database.');
        }

        $dbId = (int)$existing['id'];
        $previousBalance = (int)$existing['current_balance'];

        if ($hasLowStockThreshold) {
            $stmt = $conn->prepare(
                'UPDATE inventory_items
                 SET item_name = ?, rack_no = ?, unit = ?, current_balance = ?, low_stock_threshold = ?, status = ?, description = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->bind_param('sssiissi', $itemName, $rackNo, $unit, $currentBalance, $lowStockThreshold, $status, $description, $dbId);
        } else {
            $stmt = $conn->prepare(
                'UPDATE inventory_items
                 SET item_name = ?, rack_no = ?, unit = ?, current_balance = ?, status = ?, description = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->bind_param('sssissi', $itemName, $rackNo, $unit, $currentBalance, $status, $description, $dbId);
        }
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update the item.');
        }

        $transactionId = null;
        if ($currentBalance !== $previousBalance) {
            $quantity = abs($currentBalance - $previousBalance);
            $type = 'ADJUSTMENT';
            $reason = 'Balance changed while updating item details';
            $reference = 'ITEM-EDIT-' . $dbId . '-' . date('YmdHis');

            $stmt = $conn->prepare(
                'INSERT INTO inventory_transactions
                 (item_no, item_name, transaction_type, quantity, previous_balance, new_balance, reason, requestor, reference_no, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())'
            );
            $stmt->bind_param(
                'sssiiissss',
                $itemNo,
                $itemName,
                $type,
                $quantity,
                $previousBalance,
                $currentBalance,
                $reason,
                $actor,
                $reference,
                $actor
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Failed to post the balance change to the stock card.');
            }
            $transactionId = $conn->insert_id;
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => $transactionId
                ? 'Item updated and balance posted to the stock card.'
                : 'Item updated successfully.',
            'item_no' => $itemNo,
            'transaction_id' => $transactionId,
            'updated_fields' => [
                'item_name' => $itemName,
                'rack_no' => $rackNo,
                'unit' => $unit,
                'current_balance' => $currentBalance,
                'low_stock_threshold' => $lowStockThreshold,
                'status' => $status,
                'description' => $description,
            ],
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
