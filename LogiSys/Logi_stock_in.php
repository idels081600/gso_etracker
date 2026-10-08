<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/logi_db.php';
require_once __DIR__ . '/Logi_security.php';
logi_require_admin_csrf($conn);

function respond_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
    respond_json(401, ['success' => false, 'message' => 'Your session has expired. Please sign in again.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(405, ['success' => false, 'message' => 'Only POST requests are accepted.']);
}

$rawBody = file_get_contents('php://input');
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = [];

if (strpos($contentType, 'application/json') !== false) {
    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        respond_json(400, ['success' => false, 'message' => 'The submitted transaction is not valid JSON.']);
    }
    $payload = $decoded;
} else {
    // Keep older cached form submissions working during rollout.
    $payload = $_POST;
    if (!isset($payload['items']) && isset($payload['item_no'], $payload['quantity'])) {
        $payload['items'] = [[
            'item_no' => $payload['item_no'],
            'quantity' => $payload['quantity'],
        ]];
    }
    if (!isset($payload['ib_no'])) {
        $payload['ib_no'] = $payload['reference_no'] ?? $payload['PO_no_IB_no'] ?? '';
    }
}

$ibNo = trim((string)($payload['ib_no'] ?? ''));
$transactionDate = trim((string)($payload['transaction_date'] ?? ''));
$reason = trim((string)($payload['reason'] ?? ''));
$notes = trim((string)($payload['notes'] ?? ''));
$items = $payload['items'] ?? null;

if ($ibNo === '') {
    respond_json(422, ['success' => false, 'message' => 'IB No. is required.']);
}
if (strlen($ibNo) > 100) {
    respond_json(422, ['success' => false, 'message' => 'IB No. must not exceed 100 characters.']);
}
if ($transactionDate === '') {
    respond_json(422, ['success' => false, 'message' => 'Transaction date is required.']);
}
$dateValue = DateTime::createFromFormat('!Y-m-d', $transactionDate);
if (!$dateValue || $dateValue->format('Y-m-d') !== $transactionDate) {
    respond_json(422, ['success' => false, 'message' => 'Transaction date must be a valid date.']);
}
if ($reason === '') {
    respond_json(422, ['success' => false, 'message' => 'Reason is required.']);
}
if (strlen($reason) > 200) {
    respond_json(422, ['success' => false, 'message' => 'Reason must not exceed 200 characters.']);
}
if (strlen($notes) > 280) {
    respond_json(422, ['success' => false, 'message' => 'Additional notes must not exceed 280 characters.']);
}
if (!is_array($items) || count($items) < 1) {
    respond_json(422, ['success' => false, 'message' => 'Add at least one item to this transaction.']);
}
if (count($items) > 100) {
    respond_json(422, ['success' => false, 'message' => 'A Stock In transaction can contain at most 100 items.']);
}

$normalizedItems = [];
foreach ($items as $index => $line) {
    if (!is_array($line)) {
        respond_json(422, ['success' => false, 'message' => 'Item line ' . ($index + 1) . ' is invalid.']);
    }

    $itemNo = trim((string)($line['item_no'] ?? ''));
    $quantityRaw = $line['quantity'] ?? null;

    if ($itemNo === '') {
        respond_json(422, ['success' => false, 'message' => 'Item line ' . ($index + 1) . ' has no item number.']);
    }
    if (filter_var($quantityRaw, FILTER_VALIDATE_INT) === false || (int)$quantityRaw <= 0) {
        respond_json(422, ['success' => false, 'message' => 'Quantity for item ' . $itemNo . ' must be a positive whole number.']);
    }

    $quantity = (int)$quantityRaw;
    if (isset($normalizedItems[$itemNo])) {
        $normalizedItems[$itemNo] += $quantity;
    } else {
        $normalizedItems[$itemNo] = $quantity;
    }
}

ksort($normalizedItems, SORT_NATURAL);
$storedReason = $reason;
if ($notes !== '') {
    $storedReason .= ' | Notes: ' . $notes;
}
if (strlen($storedReason) > 500) {
    respond_json(422, ['success' => false, 'message' => 'Reason and notes are too long when combined.']);
}

$userId = (string)$_SESSION['username'];
$createdAt = $transactionDate . ' ' . date('H:i:s');

try {
    $conn->begin_transaction();

    $plannedIbStmt = $conn->prepare('SELECT id, status FROM ib_headers WHERE ib_no = ? LIMIT 1 FOR UPDATE');
    if ($plannedIbStmt) {
        $plannedIbStmt->bind_param('s', $ibNo);
        $plannedIbStmt->execute();
        $plannedIb = $plannedIbStmt->get_result()->fetch_assoc();
        $plannedIbStmt->close();
        if ($plannedIb) {
            throw new DomainException('IB No. ' . $ibNo . ' is managed in IB Monitoring. Record its delivery from that page.');
        }
    }

    $duplicateStmt = $conn->prepare(
        "SELECT id FROM inventory_transactions
         WHERE transaction_type = 'ADDITION'
           AND (reference_no = ? OR PO_no_IB_no = ?)
         LIMIT 1 FOR UPDATE"
    );
    if (!$duplicateStmt) {
        throw new RuntimeException('Unable to prepare duplicate IB validation.');
    }
    $duplicateStmt->bind_param('ss', $ibNo, $ibNo);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    if ($duplicateResult && $duplicateResult->fetch_assoc()) {
        throw new DomainException('IB No. ' . $ibNo . ' has already been posted. Use a different IB number.');
    }
    $duplicateStmt->close();

    $selectStmt = $conn->prepare(
        'SELECT id, item_no, item_name, current_balance, unit
         FROM inventory_items
         WHERE item_no = ?
         LIMIT 1 FOR UPDATE'
    );
    $insertStmt = $conn->prepare(
        "INSERT INTO inventory_transactions
            (item_no, item_name, transaction_type, quantity, previous_balance, new_balance,
             reason, reference_no, PO_no_IB_no, requestor, user_id, created_at)
         VALUES (?, ?, 'ADDITION', ?, ?, ?, ?, ?, ?, '', ?, ?)"
    );
    $updateStmt = $conn->prepare(
        'UPDATE inventory_items SET current_balance = ? WHERE id = ?'
    );
    if (!$selectStmt || !$insertStmt || !$updateStmt) {
        throw new RuntimeException('Unable to prepare the Stock In posting statements.');
    }

    $postedItems = [];
    foreach ($normalizedItems as $itemNo => $quantity) {
        $selectStmt->bind_param('s', $itemNo);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $item = $result ? $result->fetch_assoc() : null;
        if (!$item) {
            throw new DomainException('Item ' . $itemNo . ' was not found. No items were posted.');
        }

        $itemId = (int)$item['id'];
        $itemName = (string)$item['item_name'];
        $previousBalance = (int)$item['current_balance'];
        $newBalance = $previousBalance + $quantity;

        $insertStmt->bind_param(
            'ssiiisssss',
            $itemNo,
            $itemName,
            $quantity,
            $previousBalance,
            $newBalance,
            $storedReason,
            $ibNo,
            $ibNo,
            $userId,
            $createdAt
        );
        if (!$insertStmt->execute()) {
            throw new RuntimeException('Unable to record item ' . $itemNo . '.');
        }

        $updateStmt->bind_param('ii', $newBalance, $itemId);
        if (!$updateStmt->execute()) {
            throw new RuntimeException('Unable to update the balance for item ' . $itemNo . '.');
        }

        $postedItems[] = [
            'item_no' => $itemNo,
            'item_name' => $itemName,
            'quantity' => $quantity,
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'unit' => (string)($item['unit'] ?? ''),
        ];
    }

    $selectStmt->close();
    $insertStmt->close();
    $updateStmt->close();
    $conn->commit();

    respond_json(200, [
        'success' => true,
        'message' => 'IB No. ' . $ibNo . ' posted successfully with ' . count($postedItems) . ' item(s).',
        'ib_no' => $ibNo,
        'items' => $postedItems,
    ]);
} catch (Throwable $error) {
    $conn->rollback();

    $status = $error instanceof DomainException ? 409 : 500;
    $message = $error instanceof DomainException
        ? $error->getMessage()
        : 'The Stock In transaction could not be posted. No item balances were changed.';

    error_log('Batch Stock In failed: ' . $error->getMessage());
    respond_json($status, ['success' => false, 'message' => $message]);
}
