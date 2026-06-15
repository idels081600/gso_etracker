<?php
require_once dirname(__DIR__) . '/db_asset.php';
require_once dirname(__DIR__) . '/api_helpers.php';

const EQUIPMENT_DEPLOYMENT_STATUSES = ['Pending', 'Deployed', 'For Retrieval', 'Retrieved', 'Long Term'];

function equipment_status_reserves_inventory(string $status): bool
{
    return $status !== 'Retrieved';
}

function get_equipment_types(?string $category = null): array
{
    global $conn;
    if ($category !== null && !in_array($category, ['Chair', 'Table'], true)) {
        throw new InvalidArgumentException('Invalid equipment category.');
    }

    $sql = 'SELECT *, (total_qty - available_qty) AS reserved_qty FROM equipment_types';
    $params = [];
    $types = '';
    if ($category !== null) {
        $sql .= ' WHERE category = ?';
        $params[] = $category;
        $types = 's';
    }
    $sql .= ' ORDER BY category, subtype_name';

    $stmt = db_execute($conn, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function update_inventory_totals(array $updates): int
{
    global $conn;
    if ($updates === []) {
        throw new InvalidArgumentException('No inventory quantities were provided.');
    }

    $normalized = [];
    foreach ($updates as $update) {
        $id = (int) ($update['id'] ?? 0);
        $total = filter_var($update['total_qty'] ?? null, FILTER_VALIDATE_INT);
        if ($id <= 0 || $total === false || $total < 0 || isset($normalized[$id])) {
            throw new InvalidArgumentException('Inventory quantities must be unique, valid whole numbers.');
        }
        $normalized[$id] = $total;
    }
    ksort($normalized);

    mysqli_begin_transaction($conn);
    try {
        foreach ($normalized as $id => $total) {
            $type = db_fetch_one(
                $conn,
                'SELECT id, display_name, total_qty, available_qty FROM equipment_types WHERE id = ? FOR UPDATE',
                'i',
                [$id]
            );
            if (!$type) {
                throw new InvalidArgumentException('One of the equipment subtypes no longer exists.');
            }

            $reserved = (int) $type['total_qty'] - (int) $type['available_qty'];
            if ($total < $reserved) {
                throw new InvalidArgumentException(
                    sprintf('%s has %d reserved and cannot be reduced below that quantity.', $type['display_name'], $reserved)
                );
            }

            $stmt = db_execute(
                $conn,
                'UPDATE equipment_types SET total_qty = ?, available_qty = ? WHERE id = ?',
                'iii',
                [$total, $total - $reserved, $id]
            );
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conn);
        return count($normalized);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function validate_equipment_type_fields(string $category, string $subtypeName, string $displayName, int $availableBalance): void
{
    if (!in_array($category, ['Chair', 'Table'], true)) {
        throw new InvalidArgumentException('Category must be Chair or Table.');
    }
    if ($subtypeName === '' || mb_strlen($subtypeName) > 50) {
        throw new InvalidArgumentException('Subtype name is required and must not exceed 50 characters.');
    }
    if ($displayName === '' || mb_strlen($displayName) > 100) {
        throw new InvalidArgumentException('Display name is required and must not exceed 100 characters.');
    }
    if ($availableBalance < 0 || $availableBalance > 1000000) {
        throw new InvalidArgumentException('Available balance must be between 0 and 1,000,000.');
    }
}

function create_equipment_type(string $category, string $subtypeName, string $displayName, int $availableBalance): int
{
    global $conn;
    validate_equipment_type_fields($category, $subtypeName, $displayName, $availableBalance);
    mysqli_begin_transaction($conn);
    try {
        $duplicate = db_fetch_one(
            $conn,
            'SELECT id FROM equipment_types WHERE category = ? AND (LOWER(subtype_name) = LOWER(?) OR LOWER(display_name) = LOWER(?)) LIMIT 1 FOR UPDATE',
            'sss',
            [$category, $subtypeName, $displayName]
        );
        if ($duplicate) {
            throw new InvalidArgumentException('An equipment type with that subtype or display name already exists in this category.');
        }
        $stmt = db_execute(
            $conn,
            'INSERT INTO equipment_types (category, subtype_name, display_name, total_qty, available_qty) VALUES (?, ?, ?, ?, ?)',
            'sssii',
            [$category, $subtypeName, $displayName, $availableBalance, $availableBalance]
        );
        $id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        return $id;
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function update_equipment_type(int $id, string $category, string $subtypeName, string $displayName, int $availableBalance): void
{
    global $conn;
    validate_equipment_type_fields($category, $subtypeName, $displayName, $availableBalance);
    mysqli_begin_transaction($conn);
    try {
        $type = db_fetch_one(
            $conn,
            'SELECT id, total_qty, available_qty FROM equipment_types WHERE id = ? FOR UPDATE',
            'i',
            [$id]
        );
        if (!$type) {
            throw new InvalidArgumentException('Equipment type not found.');
        }
        $duplicate = db_fetch_one(
            $conn,
            'SELECT id FROM equipment_types WHERE id <> ? AND category = ? AND (LOWER(subtype_name) = LOWER(?) OR LOWER(display_name) = LOWER(?)) LIMIT 1 FOR UPDATE',
            'isss',
            [$id, $category, $subtypeName, $displayName]
        );
        if ($duplicate) {
            throw new InvalidArgumentException('Another equipment type already uses that subtype or display name in this category.');
        }

        $reserved = (int) $type['total_qty'] - (int) $type['available_qty'];
        $newTotal = $reserved + $availableBalance;
        $stmt = db_execute(
            $conn,
            'UPDATE equipment_types SET category = ?, subtype_name = ?, display_name = ?, total_qty = ?, available_qty = ? WHERE id = ?',
            'sssiii',
            [$category, $subtypeName, $displayName, $newTotal, $availableBalance, $id]
        );
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function delete_equipment_type(int $id): void
{
    global $conn;
    mysqli_begin_transaction($conn);
    try {
        $type = db_fetch_one($conn, 'SELECT display_name FROM equipment_types WHERE id = ? FOR UPDATE', 'i', [$id]);
        if (!$type) {
            throw new InvalidArgumentException('Equipment type not found.');
        }
        $usage = db_fetch_one($conn, 'SELECT COUNT(*) AS count FROM deployment_items WHERE equipment_type_id = ?', 'i', [$id]);
        if ((int) ($usage['count'] ?? 0) > 0) {
            throw new InvalidArgumentException($type['display_name'] . ' cannot be deleted because it is used in deployment history.');
        }
        $stmt = db_execute($conn, 'DELETE FROM equipment_types WHERE id = ?', 'i', [$id]);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function get_deployment_metrics(): array
{
    global $conn;
    $row = db_fetch_one($conn, "
        SELECT COUNT(*) AS total,
            SUM(status = 'Deployed') AS deployed,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'For Retrieval' AND retrieval_date = CURDATE()) AS due_today,
            SUM(status = 'For Retrieval' AND retrieval_date < CURDATE()) AS overdue
        FROM deployments
    ") ?? [];

    $deployed = db_fetch_one($conn, "
        SELECT
            COALESCE(SUM(CASE WHEN et.category = 'Chair' THEN di.quantity ELSE 0 END), 0) AS chairs_deployed,
            COALESCE(SUM(CASE WHEN et.category = 'Table' THEN di.quantity ELSE 0 END), 0) AS tables_deployed
        FROM deployment_items di
        JOIN deployments d ON d.id = di.deployment_id
        JOIN equipment_types et ON et.id = di.equipment_type_id
        WHERE d.status <> 'Retrieved'
    ") ?? [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'deployed' => (int) ($row['deployed'] ?? 0),
        'pending' => (int) ($row['pending'] ?? 0),
        'due_today' => (int) ($row['due_today'] ?? 0),
        'overdue' => (int) ($row['overdue'] ?? 0),
        'chairs_deployed' => (int) ($deployed['chairs_deployed'] ?? 0),
        'tables_deployed' => (int) ($deployed['tables_deployed'] ?? 0),
    ];
}

function display_deployments(): mysqli_result
{
    global $conn;
    $result = mysqli_query($conn, 'SELECT * FROM deployments ORDER BY id DESC');
    if (!$result) {
        throw new RuntimeException('Unable to load deployments.');
    }
    return $result;
}

function get_deployment_items(int $deploymentId): array
{
    global $conn;
    $stmt = db_execute($conn, "
        SELECT di.*, et.subtype_name, et.display_name, et.category, et.total_qty, et.available_qty
        FROM deployment_items di
        JOIN equipment_types et ON et.id = di.equipment_type_id
        WHERE di.deployment_id = ?
        ORDER BY et.category, et.subtype_name
    ", 'i', [$deploymentId]);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $items;
}

function get_deployments_with_items(?string $status = null): array
{
    global $conn;
    $sql = 'SELECT * FROM deployments';
    $types = '';
    $params = [];
    if ($status !== null) {
        if (!in_array($status, EQUIPMENT_DEPLOYMENT_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid deployment status.');
        }
        $sql .= ' WHERE status = ?';
        $types = 's';
        $params[] = $status;
    }
    $sql .= ' ORDER BY id DESC';
    $stmt = db_execute($conn, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['items'] = get_deployment_items((int) $row['id']);
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function normalize_deployment_items(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        $typeId = (int) ($item['equipment_type_id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        if ($typeId <= 0 || $quantity <= 0) {
            throw new InvalidArgumentException('Every equipment row needs a subtype and a quantity greater than zero.');
        }
        if (isset($normalized[$typeId])) {
            throw new InvalidArgumentException('The same equipment subtype cannot be selected more than once.');
        }
        $normalized[$typeId] = ['equipment_type_id' => $typeId, 'quantity' => $quantity];
    }
    if ($normalized === []) {
        throw new InvalidArgumentException('Add at least one chair or table subtype.');
    }
    ksort($normalized);
    return array_values($normalized);
}

function lock_equipment_types(array $items): array
{
    global $conn;
    $locked = [];
    foreach ($items as $item) {
        $typeId = (int) $item['equipment_type_id'];
        $stmt = db_execute(
            $conn,
            'SELECT id, display_name, total_qty, available_qty FROM equipment_types WHERE id = ? FOR UPDATE',
            'i',
            [$typeId]
        );
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) {
            throw new InvalidArgumentException('One of the selected equipment subtypes no longer exists.');
        }
        $locked[$typeId] = $row;
    }
    return $locked;
}

function adjust_inventory(array $items, bool $reserve): void
{
    global $conn;
    usort($items, fn (array $left, array $right): int => (int) $left['equipment_type_id'] <=> (int) $right['equipment_type_id']);
    $locked = lock_equipment_types($items);
    foreach ($items as $item) {
        $typeId = (int) $item['equipment_type_id'];
        $quantity = (int) $item['quantity'];
        $type = $locked[$typeId];
        if ($reserve && (int) $type['available_qty'] < $quantity) {
            throw new InvalidArgumentException(
                sprintf('%s only has %d available.', $type['display_name'], (int) $type['available_qty'])
            );
        }

        if ($reserve) {
            $stmt = db_execute(
                $conn,
                'UPDATE equipment_types SET available_qty = available_qty - ? WHERE id = ? AND available_qty >= ?',
                'iii',
                [$quantity, $typeId, $quantity]
            );
        } else {
            $stmt = db_execute(
                $conn,
                'UPDATE equipment_types SET available_qty = LEAST(total_qty, available_qty + ?) WHERE id = ?',
                'ii',
                [$quantity, $typeId]
            );
        }
        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('Inventory changed while the request was being processed. Please try again.');
        }
        mysqli_stmt_close($stmt);
    }
}

function insert_deployment(string $name, string $contact, string $purpose, string $location, string $address, string $date, string $retrievalDate, array $items): int
{
    global $conn;
    $items = normalize_deployment_items($items);
    mysqli_begin_transaction($conn);
    try {
        adjust_inventory($items, true);
        $stmt = db_execute(
            $conn,
            "INSERT INTO deployments (name, contact_no, purpose, location, address, status, date, retrieval_date)
             VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)",
            'sssssss',
            [$name, $contact, $purpose, $location, $address, $date, $retrievalDate]
        );
        $deploymentId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        insert_deployment_items($deploymentId, $items);
        mysqli_commit($conn);
        return $deploymentId;
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function insert_deployment_items(int $deploymentId, array $items): void
{
    global $conn;
    foreach ($items as $item) {
        $stmt = db_execute(
            $conn,
            'INSERT INTO deployment_items (deployment_id, equipment_type_id, quantity) VALUES (?, ?, ?)',
            'iii',
            [$deploymentId, (int) $item['equipment_type_id'], (int) $item['quantity']]
        );
        mysqli_stmt_close($stmt);
    }
}

function update_deployment(int $id, string $name, string $contact, string $purpose, string $location, string $address, string $date, string $retrievalDate, string $status, array $items): void
{
    global $conn;
    $items = normalize_deployment_items($items);
    mysqli_begin_transaction($conn);
    try {
        $current = db_fetch_one($conn, 'SELECT status FROM deployments WHERE id = ? FOR UPDATE', 'i', [$id]);
        if (!$current) {
            throw new InvalidArgumentException('Deployment not found.');
        }
        $oldItems = get_deployment_items($id);
        if (equipment_status_reserves_inventory($current['status'])) {
            adjust_inventory($oldItems, false);
        }
        if (equipment_status_reserves_inventory($status)) {
            adjust_inventory($items, true);
        }

        $stmt = db_execute(
            $conn,
            'UPDATE deployments SET name = ?, contact_no = ?, purpose = ?, location = ?, address = ?, status = ?, date = ?, retrieval_date = ? WHERE id = ?',
            'ssssssssi',
            [$name, $contact, $purpose, $location, $address, $status, $date, $retrievalDate, $id]
        );
        mysqli_stmt_close($stmt);
        $stmt = db_execute($conn, 'DELETE FROM deployment_items WHERE deployment_id = ?', 'i', [$id]);
        mysqli_stmt_close($stmt);
        insert_deployment_items($id, $items);
        mysqli_commit($conn);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function update_deployment_status(int $id, string $newStatus): void
{
    global $conn;
    mysqli_begin_transaction($conn);
    try {
        update_deployment_status_in_transaction($id, $newStatus);
        mysqli_commit($conn);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function update_deployment_status_in_transaction(int $id, string $newStatus): void
{
    global $conn;
    if (!in_array($newStatus, EQUIPMENT_DEPLOYMENT_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid deployment status.');
    }
    $deployment = db_fetch_one($conn, 'SELECT status FROM deployments WHERE id = ? FOR UPDATE', 'i', [$id]);
    if (!$deployment) {
        throw new InvalidArgumentException('Deployment not found.');
    }
    $oldStatus = $deployment['status'];
    if ($oldStatus === $newStatus) {
        return;
    }
    $items = get_deployment_items($id);
    if (equipment_status_reserves_inventory($oldStatus) && !equipment_status_reserves_inventory($newStatus)) {
        adjust_inventory($items, false);
    } elseif (!equipment_status_reserves_inventory($oldStatus) && equipment_status_reserves_inventory($newStatus)) {
        adjust_inventory($items, true);
    }
    $stmt = db_execute($conn, 'UPDATE deployments SET status = ? WHERE id = ?', 'si', [$newStatus, $id]);
    mysqli_stmt_close($stmt);
}

function bulk_update_deployment_statuses(array $updates): int
{
    global $conn;
    if ($updates === []) {
        throw new InvalidArgumentException('Select at least one deployment to update.');
    }
    mysqli_begin_transaction($conn);
    try {
        usort($updates, fn (array $left, array $right): int => (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        $seen = [];
        foreach ($updates as $update) {
            $id = (int) ($update['id'] ?? 0);
            $status = (string) ($update['status'] ?? '');
            if ($id <= 0 || isset($seen[$id])) {
                throw new InvalidArgumentException('Invalid or duplicate deployment selection.');
            }
            $seen[$id] = true;
            update_deployment_status_in_transaction($id, $status);
        }
        mysqli_commit($conn);
        return count($seen);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function delete_deployment(int $id): void
{
    global $conn;
    mysqli_begin_transaction($conn);
    try {
        $deployment = db_fetch_one($conn, 'SELECT status FROM deployments WHERE id = ? FOR UPDATE', 'i', [$id]);
        if (!$deployment) {
            throw new InvalidArgumentException('Deployment not found.');
        }
        if (equipment_status_reserves_inventory($deployment['status'])) {
            adjust_inventory(get_deployment_items($id), false);
        }
        $stmt = db_execute($conn, 'DELETE FROM deployments WHERE id = ?', 'i', [$id]);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}
