<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/logi_db.php';
require_once __DIR__ . '/Logi_ib_auth.php';

function ib_response(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function ib_fail(string $message, int $status = 422): void { ib_response($status, ['success' => false, 'message' => $message]); }
function ib_actor(): string {
    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) ib_fail('Your session has expired. Please sign in again.', 401);
    global $conn;
    $actor = logi_ib_admin_actor($conn);
    if ($actor === null) ib_fail('This account does not have permission to manage IB records. Sign in with the Version 1 ADMIN_SAP account.', 403);
    return $actor;
}
function ib_payload(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) ib_fail('The submitted request is not valid JSON.', 400);
    return $data;
}
function ib_date(string $value): string {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) ib_fail('Enter a valid delivery date.');
    return $value;
}
function ib_is_admin_gso_office(string $officeName): bool {
    $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($officeName)));
    return in_array($normalized, ['ADMIN', 'ADMINGSO'], true);
}
function ib_price($value): string {
    $text = trim((string)$value);
    if (!preg_match('/^(?:0|[1-9]\d{0,12})(?:\.\d{1,2})?$/', $text)) ib_fail('Unit prices must be valid non-negative amounts with at most two decimal places.');
    return number_format((float)$text, 2, '.', '');
}
function ib_activity(mysqli $conn, int $ibId, string $action, string $actor, array $details = []): void {
    $json = $details ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $stmt = $conn->prepare('INSERT INTO ib_activity_log (ib_id, action, actor, details_json) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $ibId, $action, $actor, $json);
    $stmt->execute();
    $stmt->close();
}
function ib_reference_reuse_authorization(mysqli $conn, string $ibNo, ?int $ibId = null): ?array {
    $stmt = $conn->prepare('SELECT id, reason, legacy_transaction_count, used_by_ib_id FROM ib_reference_reuse_authorizations WHERE ib_no = ? FOR UPDATE');
    $stmt->bind_param('s', $ibNo);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    $usedBy = $row['used_by_ib_id'] === null ? null : (int)$row['used_by_ib_id'];
    if ($usedBy !== null && $usedBy !== $ibId) return null;
    return $row;
}
function ib_consume_reference_reuse(mysqli $conn, array $authorization, int $ibId): void {
    $authorizationId = (int)$authorization['id'];
    $stmt = $conn->prepare('UPDATE ib_reference_reuse_authorizations SET used_by_ib_id = ?, used_at = COALESCE(used_at, NOW()) WHERE id = ? AND (used_by_ib_id IS NULL OR used_by_ib_id = ?)');
    $stmt->bind_param('iii', $ibId, $authorizationId, $ibId);
    $stmt->execute();
    if ($stmt->affected_rows < 1) {
        $stmt->close();
        throw new DomainException('The IB reference reuse authorization has already been consumed.');
    }
    $stmt->close();
}
function ib_lock_header(mysqli $conn, int $id): array {
    $stmt = $conn->prepare('SELECT * FROM ib_headers WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) throw new DomainException('IB record was not found.');
    return $row;
}
function ib_save_structure(mysqli $conn, int $ibId, array $groups): void {
    if (!$groups || count($groups) > 50) throw new DomainException('Add between 1 and 50 office groups.');
    $numberRow = $conn->query("SELECT GREATEST(COALESCE((SELECT MAX(CAST(item_no AS UNSIGNED)) FROM inventory_items WHERE item_no REGEXP '^[0-9]+$'),0),COALESCE((SELECT MAX(CAST(item_no AS UNSIGNED)) FROM ib_item_lines WHERE item_id IS NULL AND add_to_inventory=1 AND item_no REGEXP '^[0-9]+$'),0)) AS last_item_no")->fetch_assoc();
    $nextAutomaticItemNo = (int)($numberRow['last_item_no'] ?? 0) + 1;
    $officeStmt = $conn->prepare('SELECT id, office_name FROM office_balances WHERE id = ?');
    $itemStmt = $conn->prepare("SELECT id, item_no, item_name, unit, status, type FROM inventory_items WHERE id = ? AND LOWER(COALESCE(status,'')) NOT IN ('inactive','deleted') AND (type IS NULL OR type = '' OR LOWER(type) IN ('common use','consumable'))");
    $itemNoStmt = $conn->prepare('SELECT id FROM inventory_items WHERE item_no = ? LIMIT 1');
    $groupStmt = $conn->prepare('INSERT INTO ib_office_groups (ib_id, office_id, office_name, description, sort_order) VALUES (?, ?, ?, ?, ?)');
    $lineStmt = $conn->prepare('INSERT INTO ib_item_lines (group_id, item_id, item_no, item_name, unit, planned_quantity, unit_price, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $manualLineStmt = $conn->prepare('INSERT INTO ib_item_lines (group_id, item_id, item_no, item_name, unit, add_to_inventory, planned_quantity, unit_price, sort_order) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)');
    $totalLines = 0;
    foreach ($groups as $groupIndex => $group) {
        if (!is_array($group)) throw new DomainException('Office group ' . ($groupIndex + 1) . ' is invalid.');
        $officeId = filter_var($group['office_id'] ?? null, FILTER_VALIDATE_INT);
        $description = trim((string)($group['description'] ?? ''));
        $items = $group['items'] ?? [];
        if (!$officeId) throw new DomainException('Select an office for group ' . ($groupIndex + 1) . '.');
        if ($description === '' || mb_strlen($description) > 500) throw new DomainException('Enter a description of up to 500 characters for group ' . ($groupIndex + 1) . '.');
        if (!is_array($items) || !$items) throw new DomainException('Add at least one item to group ' . ($groupIndex + 1) . '.');
        $officeStmt->bind_param('i', $officeId); $officeStmt->execute();
        $office = $officeStmt->get_result()->fetch_assoc();
        if (!$office) throw new DomainException('The selected office for group ' . ($groupIndex + 1) . ' no longer exists.');
        $groupOrder = $groupIndex + 1;
        $groupStmt->bind_param('iissi', $ibId, $officeId, $office['office_name'], $description, $groupOrder); $groupStmt->execute();
        $groupId = (int)$conn->insert_id; $seen = [];
        foreach ($items as $itemIndex => $line) {
            $totalLines++; if ($totalLines > 300) throw new DomainException('An IB can contain at most 300 item lines.');
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT); $price = ib_price($line['unit_price'] ?? '');
            if (!$quantity || $quantity < 1) throw new DomainException('Planned quantities must be positive whole numbers.');
            $lineOrder = $itemIndex + 1; $isNew = !empty($line['is_new_item']);
            if ($isNew) {
                $addToInventory = !empty($line['add_to_inventory']) ? 1 : 0;
                $itemNo = $addToInventory ? (string)$nextAutomaticItemNo++ : trim((string)($line['item_no'] ?? '')); $itemName = trim((string)($line['item_name'] ?? '')); $unit = trim((string)($line['unit'] ?? ''));
                if (mb_strlen($itemNo) > 500) throw new DomainException('Stock numbers must not exceed 500 characters.');
                if ($itemName === '' || mb_strlen($itemName) > 500) throw new DomainException('Enter an item name of up to 500 characters for every new item.');
                if ($unit === '' || mb_strlen($unit) > 50) throw new DomainException('Enter a unit of up to 50 characters for every new item.');
                $key = $itemNo !== '' ? 'new:' . mb_strtolower($itemNo) : 'new-name:' . mb_strtolower($itemName . '|' . $unit); if (isset($seen[$key])) throw new DomainException('The same new item cannot appear twice in one office group.'); $seen[$key] = true;
                if ($itemNo !== '') { $itemNoStmt->bind_param('s', $itemNo); $itemNoStmt->execute(); if ($itemNoStmt->get_result()->fetch_row()) throw new DomainException('Stock number ' . $itemNo . ' already exists. Turn off “Not in inventory yet” and select it from the catalog.'); }
                $manualLineStmt->bind_param('isssiisi', $groupId, $itemNo, $itemName, $unit, $addToInventory, $quantity, $price, $lineOrder); $manualLineStmt->execute();
            } else {
                $itemId = filter_var($line['item_id'] ?? null, FILTER_VALIDATE_INT); if (!$itemId) throw new DomainException('Select a valid inventory item in group ' . ($groupIndex + 1) . '.');
                $key = 'catalog:' . $itemId; if (isset($seen[$key])) throw new DomainException('The same item cannot appear twice in one office group.'); $seen[$key] = true;
                $itemStmt->bind_param('i', $itemId); $itemStmt->execute(); $item = $itemStmt->get_result()->fetch_assoc();
                if (!$item) throw new DomainException('A selected item is unavailable or is not classified as a consumable.');
                $unit = (string)($item['unit'] ?? ''); $lineStmt->bind_param('iisssisi', $groupId, $itemId, $item['item_no'], $item['item_name'], $unit, $quantity, $price, $lineOrder); $lineStmt->execute();
            }
        }
    }
    $officeStmt->close(); $itemStmt->close(); $itemNoStmt->close(); $groupStmt->close(); $lineStmt->close(); $manualLineStmt->close();
}

function ib_append_active_items(mysqli $conn, int $groupId, array $items): int {
    if (!$items || count($items) > 100) throw new DomainException('Add between 1 and 100 items.');
    $numberRow = $conn->query("SELECT GREATEST(COALESCE((SELECT MAX(CAST(item_no AS UNSIGNED)) FROM inventory_items WHERE item_no REGEXP '^[0-9]+$'),0),COALESCE((SELECT MAX(CAST(item_no AS UNSIGNED)) FROM ib_item_lines WHERE item_id IS NULL AND add_to_inventory=1 AND item_no REGEXP '^[0-9]+$'),0)) AS last_item_no")->fetch_assoc();
    $nextAutomaticItemNo = (int)($numberRow['last_item_no'] ?? 0) + 1;
    $orderStmt = $conn->prepare('SELECT COALESCE(MAX(sort_order),0) last_order FROM ib_item_lines WHERE group_id=?');
    $orderStmt->bind_param('i', $groupId); $orderStmt->execute(); $nextOrder = (int)$orderStmt->get_result()->fetch_assoc()['last_order'] + 1; $orderStmt->close();
    $existingStmt = $conn->prepare('SELECT item_id,item_no,item_name,unit FROM ib_item_lines WHERE group_id=?');
    $existingStmt->bind_param('i', $groupId); $existingStmt->execute(); $existingRows = $existingStmt->get_result()->fetch_all(MYSQLI_ASSOC); $existingStmt->close();
    $seen = [];
    foreach ($existingRows as $existing) {
        $key = $existing['item_id'] !== null ? 'catalog:' . (int)$existing['item_id'] : ((string)$existing['item_no'] !== '' ? 'new:' . mb_strtolower((string)$existing['item_no']) : 'new-name:' . mb_strtolower((string)$existing['item_name'] . '|' . (string)$existing['unit']));
        $seen[$key] = true;
    }
    $itemStmt = $conn->prepare("SELECT id,item_no,item_name,unit FROM inventory_items WHERE id=? AND LOWER(COALESCE(status,'')) NOT IN ('inactive','deleted') AND (type IS NULL OR type='' OR LOWER(type) IN ('common use','consumable'))");
    $itemNoStmt = $conn->prepare('SELECT id FROM inventory_items WHERE item_no=? LIMIT 1');
    $lineStmt = $conn->prepare('INSERT INTO ib_item_lines (group_id,item_id,item_no,item_name,unit,planned_quantity,unit_price,sort_order) VALUES (?,?,?,?,?,?,?,?)');
    $manualStmt = $conn->prepare('INSERT INTO ib_item_lines (group_id,item_id,item_no,item_name,unit,add_to_inventory,planned_quantity,unit_price,sort_order) VALUES (?,NULL,?,?,?,?,?,?,?)');
    foreach ($items as $index => $line) {
        if (!is_array($line)) throw new DomainException('An added item is invalid.');
        $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT); $price = ib_price($line['unit_price'] ?? '');
        if (!$quantity || $quantity < 1) throw new DomainException('Planned quantities must be positive whole numbers.');
        $isNew = !empty($line['is_new_item']);
        if ($isNew) {
            $addToInventory = !empty($line['add_to_inventory']) ? 1 : 0;
            $itemNo = $addToInventory ? (string)$nextAutomaticItemNo++ : trim((string)($line['item_no'] ?? ''));
            $itemName = trim((string)($line['item_name'] ?? '')); $unit = trim((string)($line['unit'] ?? ''));
            if (mb_strlen($itemNo) > 500) throw new DomainException('Stock numbers must not exceed 500 characters.');
            if ($itemName === '' || mb_strlen($itemName) > 500) throw new DomainException('Enter an item name of up to 500 characters for every new item.');
            if ($unit === '' || mb_strlen($unit) > 50) throw new DomainException('Enter a unit of up to 50 characters for every new item.');
            $key = $itemNo !== '' ? 'new:' . mb_strtolower($itemNo) : 'new-name:' . mb_strtolower($itemName . '|' . $unit);
            if (isset($seen[$key])) throw new DomainException('That item already exists in the selected office group.'); $seen[$key] = true;
            if ($itemNo !== '') { $itemNoStmt->bind_param('s', $itemNo); $itemNoStmt->execute(); if ($itemNoStmt->get_result()->fetch_row()) throw new DomainException('Stock number ' . $itemNo . ' already exists. Select it from the catalog instead.'); }
            $sortOrder = $nextOrder++; $manualStmt->bind_param('isssiisi', $groupId, $itemNo, $itemName, $unit, $addToInventory, $quantity, $price, $sortOrder); $manualStmt->execute();
        } else {
            $itemId = filter_var($line['item_id'] ?? null, FILTER_VALIDATE_INT); if (!$itemId) throw new DomainException('Select a valid inventory item.');
            $key = 'catalog:' . $itemId; if (isset($seen[$key])) throw new DomainException('That inventory item already exists in the selected office group.'); $seen[$key] = true;
            $itemStmt->bind_param('i', $itemId); $itemStmt->execute(); $item = $itemStmt->get_result()->fetch_assoc();
            if (!$item) throw new DomainException('A selected item is unavailable or is not classified as a consumable.');
            $sortOrder = $nextOrder++; $unit = (string)($item['unit'] ?? ''); $lineStmt->bind_param('iisssisi', $groupId, $itemId, $item['item_no'], $item['item_name'], $unit, $quantity, $price, $sortOrder); $lineStmt->execute();
        }
    }
    $itemStmt->close(); $itemNoStmt->close(); $lineStmt->close(); $manualStmt->close();
    return count($items);
}

$actor = ib_actor();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ib_fail('Only POST requests are accepted.', 405);
$data = ib_payload();
if (empty($_SESSION['ib_csrf']) || !hash_equals($_SESSION['ib_csrf'], (string)($data['csrf_token'] ?? ''))) ib_fail('The security token is invalid. Refresh the page and try again.', 403);
$action = (string)($data['action'] ?? '');
$itemNumberLock = false;

try {
    $conn->begin_transaction();
    if ($action === 'create' || $action === 'update_draft') {
        $lockResult = $conn->query("SELECT GET_LOCK('logisys_inventory_item_no', 10) acquired")->fetch_assoc();
        if ((int)($lockResult['acquired'] ?? 0) !== 1) throw new DomainException('Item numbering is busy. Please save the draft again.');
        $itemNumberLock = true;
        $ibNo = trim((string)($data['ib_no'] ?? ''));
        if ($ibNo === '' || mb_strlen($ibNo) > 100) throw new DomainException('Enter an IB number of up to 100 characters.');
        $groups = $data['groups'] ?? [];
        if ($action === 'create') {
            $reuseAuthorization = null;
            $check = $conn->prepare('SELECT id FROM ib_headers WHERE ib_no = ? LIMIT 1');
            $check->bind_param('s', $ibNo); $check->execute();
            $exists = (bool)$check->get_result()->fetch_row(); $check->close();
            if (!$exists) {
                $check = $conn->prepare('SELECT id FROM inventory_transactions WHERE PO_no_IB_no = ? OR reference_no = ? LIMIT 1');
                $check->bind_param('ss', $ibNo, $ibNo); $check->execute();
                $exists = (bool)$check->get_result()->fetch_row(); $check->close();
                if ($exists) {
                    $reuseAuthorization = ib_reference_reuse_authorization($conn, $ibNo);
                    $exists = $reuseAuthorization === null;
                }
            }
            if ($exists) throw new DomainException('That IB number already exists in IB Monitoring or Transaction History.');
            $stmt = $conn->prepare("INSERT INTO ib_headers (ib_no, status, created_by) VALUES (?, 'DRAFT', ?)");
            $stmt->bind_param('ss', $ibNo, $actor); $stmt->execute(); $stmt->close();
            $ibId = (int)$conn->insert_id;
            ib_save_structure($conn, $ibId, $groups);
            if ($reuseAuthorization) ib_consume_reference_reuse($conn, $reuseAuthorization, $ibId);
            ib_activity($conn, $ibId, 'CREATED', $actor, [
                'ib_no' => $ibNo,
                'reference_reuse_authorization_id' => $reuseAuthorization ? (int)$reuseAuthorization['id'] : null,
                'legacy_transaction_count' => $reuseAuthorization ? (int)$reuseAuthorization['legacy_transaction_count'] : 0,
            ]);
            $message = 'IB ' . $ibNo . ' was saved as Draft.';
        } else {
            $ibId = (int)($data['ib_id'] ?? 0);
            $header = ib_lock_header($conn, $ibId);
            $reuseAuthorization = null;
            if ($header['status'] !== 'DRAFT') throw new DomainException('Only Draft IB records can be edited.');
            if (strcasecmp($ibNo, $header['ib_no']) !== 0) {
                $check = $conn->prepare('SELECT id FROM ib_headers WHERE ib_no = ? AND id <> ? LIMIT 1');
                $check->bind_param('si', $ibNo, $ibId); $check->execute();
                $exists = (bool)$check->get_result()->fetch_row(); $check->close();
                if (!$exists) {
                    $check = $conn->prepare('SELECT id FROM inventory_transactions WHERE PO_no_IB_no = ? OR reference_no = ? LIMIT 1');
                    $check->bind_param('ss', $ibNo, $ibNo); $check->execute();
                    $exists = (bool)$check->get_result()->fetch_row(); $check->close();
                    if ($exists) {
                        $reuseAuthorization = ib_reference_reuse_authorization($conn, $ibNo, $ibId);
                        $exists = $reuseAuthorization === null;
                    }
                }
                if ($exists) throw new DomainException('That IB number is already in use.');
            }
            $delete = $conn->prepare('DELETE FROM ib_office_groups WHERE ib_id = ?'); $delete->bind_param('i', $ibId); $delete->execute(); $delete->close();
            $update = $conn->prepare('UPDATE ib_headers SET ib_no = ?, version = version + 1 WHERE id = ?'); $update->bind_param('si', $ibNo, $ibId); $update->execute(); $update->close();
            ib_save_structure($conn, $ibId, $groups);
            if ($reuseAuthorization) ib_consume_reference_reuse($conn, $reuseAuthorization, $ibId);
            ib_activity($conn, $ibId, 'DRAFT_UPDATED', $actor, [
                'ib_no' => $ibNo,
                'reference_reuse_authorization_id' => $reuseAuthorization ? (int)$reuseAuthorization['id'] : null,
                'legacy_transaction_count' => $reuseAuthorization ? (int)$reuseAuthorization['legacy_transaction_count'] : 0,
            ]);
            $message = 'Draft IB ' . $ibNo . ' was updated.';
        }
    } elseif ($action === 'add_active_items') {
        $lockResult = $conn->query("SELECT GET_LOCK('logisys_inventory_item_no', 10) acquired")->fetch_assoc();
        if ((int)($lockResult['acquired'] ?? 0) !== 1) throw new DomainException('Item numbering is busy. Please try again.');
        $itemNumberLock = true;
        $ibId = (int)($data['ib_id'] ?? 0); $groupId = (int)($data['group_id'] ?? 0); $header = ib_lock_header($conn, $ibId);
        if ($header['status'] !== 'ACTIVE') throw new DomainException('Items can only be appended to an Active IB.');
        $groupStmt = $conn->prepare('SELECT id,office_name,description FROM ib_office_groups WHERE id=? AND ib_id=? FOR UPDATE');
        $groupStmt->bind_param('ii', $groupId, $ibId); $groupStmt->execute(); $group = $groupStmt->get_result()->fetch_assoc(); $groupStmt->close();
        if (!$group) throw new DomainException('Select a valid office group from this IB.');
        $items = $data['items'] ?? [];
        $currentCount = $conn->query('SELECT COUNT(*) c FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE g.ib_id=' . $ibId)->fetch_assoc();
        if ((int)$currentCount['c'] + (is_array($items) ? count($items) : 0) > 300) throw new DomainException('An IB can contain at most 300 item lines.');
        $addedCount = ib_append_active_items($conn, $groupId, is_array($items) ? $items : []);
        $update = $conn->prepare('UPDATE ib_headers SET version=version+1 WHERE id=?'); $update->bind_param('i', $ibId); $update->execute(); $update->close();
        ib_activity($conn, $ibId, 'ACTIVE_ITEMS_ADDED', $actor, ['group_id'=>$groupId,'office'=>$group['office_name'],'count'=>$addedCount]);
        $message = $addedCount . ' item' . ($addedCount === 1 ? '' : 's') . ' added to ' . $group['office_name'] . ' in IB ' . $header['ib_no'] . '.';
    } elseif ($action === 'update_item_quantity') {
        $ibId = (int)($data['ib_id'] ?? 0);
        $lineId = (int)($data['line_id'] ?? 0);
        $quantity = filter_var($data['planned_quantity'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim((string)($data['reason'] ?? ''));
        $header = ib_lock_header($conn, $ibId);
        if (!in_array($header['status'], ['ACTIVE', 'COMPLETED'], true)) throw new DomainException('Item quantities can only be changed in an Active or Completed IB.');
        if ($lineId < 1) throw new DomainException('Select a valid IB item line.');
        if ($quantity === false || $quantity < 1) throw new DomainException('The planned quantity must be a positive whole number.');
        if ($quantity > 1000000000) throw new DomainException('The planned quantity is too large.');
        if ($reason === '' || mb_strlen($reason) > 500) throw new DomainException('Enter a reason of up to 500 characters.');
        $lineStmt = $conn->prepare('SELECT l.id,l.item_name,l.planned_quantity,l.delivered_quantity,g.office_name FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE l.id=? AND g.ib_id=? FOR UPDATE');
        $lineStmt->bind_param('ii', $lineId, $ibId); $lineStmt->execute(); $line = $lineStmt->get_result()->fetch_assoc(); $lineStmt->close();
        if (!$line) throw new DomainException('The selected IB item line was not found.');
        $oldQuantity = (int)$line['planned_quantity'];
        if ($oldQuantity === $quantity) throw new DomainException('Enter a planned quantity different from the current quantity.');
        $updateLine = $conn->prepare('UPDATE ib_item_lines SET planned_quantity=? WHERE id=?');
        $updateLine->bind_param('ii', $quantity, $lineId); $updateLine->execute(); $updateLine->close();
        $remainingRow = $conn->query('SELECT COUNT(*) remaining_lines FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE g.ib_id=' . $ibId . ' AND l.delivered_quantity < l.planned_quantity')->fetch_assoc();
        $newStatus = (int)$remainingRow['remaining_lines'] === 0 ? 'COMPLETED' : 'ACTIVE';
        $updateHeader = $conn->prepare("UPDATE ib_headers SET status=?, completed_at=IF(?='COMPLETED',NOW(),NULL), version=version+1 WHERE id=?");
        $updateHeader->bind_param('ssi', $newStatus, $newStatus, $ibId); $updateHeader->execute(); $updateHeader->close();
        ib_activity($conn, $ibId, 'ITEM_QUANTITY_UPDATED', $actor, [
            'line_id'=>$lineId,
            'office'=>$line['office_name'],
            'item'=>$line['item_name'],
            'old_planned_quantity'=>$oldQuantity,
            'new_planned_quantity'=>$quantity,
            'delivered_quantity'=>(int)$line['delivered_quantity'],
            'reason'=>$reason,
        ]);
        $message = 'Planned quantity for ' . $line['item_name'] . ' was updated from ' . $oldQuantity . ' to ' . $quantity . '.';
    } elseif ($action === 'activate') {
        $ibId = (int)($data['ib_id'] ?? 0); $header = ib_lock_header($conn, $ibId);
        if ($header['status'] !== 'DRAFT') throw new DomainException('Only a Draft IB can be activated.');
        $count = $conn->query('SELECT COUNT(*) c FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE g.ib_id=' . $ibId)->fetch_assoc();
        if ((int)$count['c'] < 1) throw new DomainException('Add at least one planned item before activation.');
        $stmt = $conn->prepare("UPDATE ib_headers SET status='ACTIVE', activated_by=?, activated_at=NOW(), version=version+1 WHERE id=?");
        $stmt->bind_param('si', $actor, $ibId); $stmt->execute(); $stmt->close();
        ib_activity($conn, $ibId, 'ACTIVATED', $actor); $message = 'IB ' . $header['ib_no'] . ' is now Active.';
    } elseif ($action === 'cancel') {
        $ibId = (int)($data['ib_id'] ?? 0); $reason = trim((string)($data['reason'] ?? '')); $header = ib_lock_header($conn, $ibId);
        if ($header['status'] !== 'DRAFT' && $header['status'] !== 'ACTIVE') throw new DomainException('This IB cannot be cancelled.');
        if ($reason === '' || mb_strlen($reason) > 500) throw new DomainException('Enter a cancellation reason of up to 500 characters.');
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM ib_deliveries WHERE ib_id=?'); $stmt->bind_param('i', $ibId); $stmt->execute(); $has = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();
        if ($has > 0) throw new DomainException('An IB with delivery history cannot be cancelled.');
        $stmt = $conn->prepare("UPDATE ib_headers SET status='CANCELLED', cancelled_by=?, cancelled_at=NOW(), cancel_reason=?, version=version+1 WHERE id=?");
        $stmt->bind_param('ssi', $actor, $reason, $ibId); $stmt->execute(); $stmt->close();
        ib_activity($conn, $ibId, 'CANCELLED', $actor, ['reason' => $reason]); $message = 'IB ' . $header['ib_no'] . ' was cancelled.';
    } elseif ($action === 'record_delivery') {
        $ibId = (int)($data['ib_id'] ?? 0); $header = ib_lock_header($conn, $ibId);
        if ($header['status'] !== 'ACTIVE') throw new DomainException('Deliveries can only be recorded against an Active IB.');
        $deliveryDate = ib_date(trim((string)($data['delivery_date'] ?? '')));
        $notes = trim((string)($data['notes'] ?? '')); if (mb_strlen($notes) > 500) throw new DomainException('Delivery notes must not exceed 500 characters.');
        $token = trim((string)($data['idempotency_token'] ?? '')); if (!preg_match('/^[a-f0-9-]{36}$/i', $token)) throw new DomainException('The delivery token is invalid. Refresh and try again.');
        $lines = $data['lines'] ?? []; if (!is_array($lines) || !$lines) throw new DomainException('Enter at least one delivered quantity.');
        $deliveryStmt = $conn->prepare("INSERT INTO ib_deliveries (ib_id, delivery_date, notes, idempotency_token, posted_by) VALUES (?, ?, NULLIF(?,''), ?, ?)");
        $deliveryStmt->bind_param('issss', $ibId, $deliveryDate, $notes, $token, $actor); $deliveryStmt->execute(); $deliveryStmt->close();
        $deliveryId = (int)$conn->insert_id; $seen = [];
        $lineStmt = $conn->prepare('SELECT l.*, g.office_name, g.description FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE l.id=? AND g.ib_id=? FOR UPDATE');
        $itemStmt = $conn->prepare('SELECT id, current_balance FROM inventory_items WHERE id=? FOR UPDATE');
        $manualFind = $conn->prepare('SELECT id, current_balance FROM inventory_items WHERE item_no=? LIMIT 1 FOR UPDATE');
        $manualInsert = $conn->prepare("INSERT INTO inventory_items (item_no,item_name,rack_no,current_balance,description,status,unit,type) VALUES (?,?,'',0,?,'Available',?,'consumable')");
        $manualLink = $conn->prepare('UPDATE ib_item_lines SET item_id=? WHERE id=? AND item_id IS NULL');
        $txStmt = $conn->prepare("INSERT INTO inventory_transactions (item_no,item_name,transaction_type,quantity,previous_balance,new_balance,reason,user_id,reference_no,created_at,PO_no_IB_no,requestor) VALUES (?,?,'ADDITION',?,?,?,?,?,?,?,?,?)");
        $invUpdate = $conn->prepare('UPDATE inventory_items SET current_balance=? WHERE id=?');
        $lineUpdate = $conn->prepare('UPDATE ib_item_lines SET delivered_quantity=delivered_quantity+? WHERE id=?');
        $deliveryLine = $conn->prepare('INSERT INTO ib_delivery_lines (delivery_id,ib_item_line_id,quantity,unit_price,amount,inventory_transaction_id) VALUES (?,?,?,?,?,?)');
        foreach ($lines as $entry) {
            $lineId = filter_var($entry['line_id'] ?? null, FILTER_VALIDATE_INT); $qty = filter_var($entry['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (!$lineId || !$qty || $qty < 1) throw new DomainException('Every delivered quantity must be a positive whole number.');
            if (isset($seen[$lineId])) throw new DomainException('A delivery line was submitted more than once.'); $seen[$lineId] = true;
            $lineStmt->bind_param('ii', $lineId, $ibId); $lineStmt->execute(); $line = $lineStmt->get_result()->fetch_assoc();
            if (!$line) throw new DomainException('A selected IB item line was not found.');
            $itemId = (int)$line['item_id']; $transactionId = null;
            $postsToInventory = ib_is_admin_gso_office((string)$line['office_name'])
                && ($itemId > 0 || (int)$line['add_to_inventory'] === 1);
            if ($postsToInventory) {
                if ($itemId < 1) {
                    $manualFind->bind_param('s', $line['item_no']); $manualFind->execute(); $inventory = $manualFind->get_result()->fetch_assoc();
                    if ($inventory) {
                        throw new DomainException('Reserved stock number ' . $line['item_no'] . ' is already in inventory. The delivery was not posted; resolve the stock-number conflict before retrying.');
                    } else {
                        $catalogDescription = 'Created automatically from IB ' . $header['ib_no'];
                        $manualInsert->bind_param('ssss', $line['item_no'], $line['item_name'], $catalogDescription, $line['unit']); $manualInsert->execute();
                        $itemId = (int)$conn->insert_id; $inventory = ['id' => $itemId, 'current_balance' => 0];
                    }
                    $manualLink->bind_param('ii', $itemId, $lineId); $manualLink->execute();
                } else {
                    $itemStmt->bind_param('i', $itemId); $itemStmt->execute(); $inventory = $itemStmt->get_result()->fetch_assoc();
                }
                if (!$inventory) throw new DomainException($line['item_name'] . ' is no longer available in inventory.');
                $before = (int)$inventory['current_balance']; $after = $before + $qty; $reason = 'IB delivery - ' . $line['description'];
                $txStmt->bind_param('ssiiissssss', $line['item_no'], $line['item_name'], $qty, $before, $after, $reason, $actor, $header['ib_no'], $deliveryDate, $header['ib_no'], $line['office_name']);
                $txStmt->execute(); $transactionId = (int)$conn->insert_id;
                $invUpdate->bind_param('ii', $after, $itemId); $invUpdate->execute();
            }
            $lineUpdate->bind_param('ii', $qty, $lineId); $lineUpdate->execute();
            $amount = number_format($qty * (float)$line['unit_price'], 2, '.', ''); $unitPrice = (string)$line['unit_price'];
            $deliveryLine->bind_param('iiissi', $deliveryId, $lineId, $qty, $unitPrice, $amount, $transactionId); $deliveryLine->execute();
        }
        $lineStmt->close(); $itemStmt->close(); $manualFind->close(); $manualInsert->close(); $manualLink->close(); $txStmt->close(); $invUpdate->close(); $lineUpdate->close(); $deliveryLine->close();
        $remainingRow = $conn->query('SELECT COUNT(*) remaining_lines FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE g.ib_id=' . $ibId . ' AND l.delivered_quantity < l.planned_quantity')->fetch_assoc();
        if ((int)$remainingRow['remaining_lines'] === 0) $conn->query("UPDATE ib_headers SET status='COMPLETED', completed_at=NOW(), version=version+1 WHERE id=" . $ibId);
        ib_activity($conn, $ibId, 'DELIVERY_POSTED', $actor, ['delivery_id' => $deliveryId, 'date' => $deliveryDate]);
        $message = 'Delivery posted to IB ' . $header['ib_no'] . '.';
    } elseif ($action === 'reverse_delivery') {
        $deliveryId = (int)($data['delivery_id'] ?? 0); $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 500) throw new DomainException('Enter a reversal reason of up to 500 characters.');
        $stmt = $conn->prepare('SELECT d.*, h.ib_no, h.status header_status FROM ib_deliveries d JOIN ib_headers h ON h.id=d.ib_id WHERE d.id=? FOR UPDATE');
        $stmt->bind_param('i', $deliveryId); $stmt->execute(); $delivery = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$delivery) throw new DomainException('Delivery was not found.'); if ($delivery['status'] !== 'POSTED') throw new DomainException('This delivery has already been reversed.');
        $ibId = (int)$delivery['ib_id']; ib_lock_header($conn, $ibId);
        $rowsStmt = $conn->prepare('SELECT dl.*, l.item_id,l.item_no,l.item_name,g.office_name FROM ib_delivery_lines dl JOIN ib_item_lines l ON l.id=dl.ib_item_line_id JOIN ib_office_groups g ON g.id=l.group_id WHERE dl.delivery_id=? ORDER BY l.item_id FOR UPDATE');
        $rowsStmt->bind_param('i', $deliveryId); $rowsStmt->execute(); $rows = $rowsStmt->get_result()->fetch_all(MYSQLI_ASSOC); $rowsStmt->close();
        $itemStmt = $conn->prepare('SELECT current_balance FROM inventory_items WHERE id=? FOR UPDATE');
        $txStmt = $conn->prepare("INSERT INTO inventory_transactions (item_no,item_name,transaction_type,quantity,previous_balance,new_balance,reason,user_id,reference_no,created_at,PO_no_IB_no,requestor) VALUES (?,?,'DEDUCTION',?,?,?,?,?,?,?,?,?)");
        $invUpdate = $conn->prepare('UPDATE inventory_items SET current_balance=? WHERE id=?');
        $lineUpdate = $conn->prepare('UPDATE ib_item_lines SET delivered_quantity=delivered_quantity-? WHERE id=?');
        $dlUpdate = $conn->prepare('UPDATE ib_delivery_lines SET reversal_transaction_id=? WHERE id=?');
        foreach ($rows as $row) {
            $qty=(int)$row['quantity']; $lineId=(int)$row['ib_item_line_id']; $dlId=(int)$row['id'];
            if ($row['inventory_transaction_id'] !== null) {
                $itemId=(int)$row['item_id']; $itemStmt->bind_param('i',$itemId); $itemStmt->execute(); $inv=$itemStmt->get_result()->fetch_assoc();
                if (!$inv || (int)$inv['current_balance'] < $qty) throw new DomainException('Cannot reverse because ' . $row['item_name'] . ' has insufficient current stock.');
                $before=(int)$inv['current_balance']; $after=$before-$qty; $txReason='IB delivery reversal: '.$reason; $date=date('Y-m-d');
                $txStmt->bind_param('ssiiissssss',$row['item_no'],$row['item_name'],$qty,$before,$after,$txReason,$actor,$delivery['ib_no'],$date,$delivery['ib_no'],$row['office_name']); $txStmt->execute(); $txId=(int)$conn->insert_id;
                $invUpdate->bind_param('ii',$after,$itemId); $invUpdate->execute(); $dlUpdate->bind_param('ii',$txId,$dlId); $dlUpdate->execute();
            }
            $lineUpdate->bind_param('ii',$qty,$lineId); $lineUpdate->execute();
        }
        $itemStmt->close(); $txStmt->close(); $invUpdate->close(); $lineUpdate->close(); $dlUpdate->close();
        $stmt=$conn->prepare("UPDATE ib_deliveries SET status='REVERSED',reversed_by=?,reversed_at=NOW(),reversal_reason=? WHERE id=?"); $stmt->bind_param('ssi',$actor,$reason,$deliveryId); $stmt->execute(); $stmt->close();
        $conn->query("UPDATE ib_headers SET status='ACTIVE',completed_at=NULL,version=version+1 WHERE id=".$ibId." AND status='COMPLETED'");
        ib_activity($conn,$ibId,'DELIVERY_REVERSED',$actor,['delivery_id'=>$deliveryId,'reason'=>$reason]); $message='Delivery reversed for IB '.$delivery['ib_no'].'.';
    } else throw new DomainException('Unknown IB action.');
    $conn->commit();
    if ($itemNumberLock) { $conn->query("SELECT RELEASE_LOCK('logisys_inventory_item_no')"); $itemNumberLock = false; }
    ib_response(200, ['success'=>true,'message'=>$message,'ib_id'=>$ibId ?? null]);
} catch (Throwable $e) {
    $conn->rollback();
    if ($itemNumberLock) { $conn->query("SELECT RELEASE_LOCK('logisys_inventory_item_no')"); $itemNumberLock = false; }
    if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062 && str_contains($e->getMessage(), 'uq_ib_delivery_token')) ib_fail('This delivery was already submitted.', 409);
    if ($e instanceof DomainException) ib_fail($e->getMessage(), 409);
    error_log('IB action failed: '.$e->getMessage()); ib_fail('The IB action could not be completed. No changes were saved.', 500);
}
