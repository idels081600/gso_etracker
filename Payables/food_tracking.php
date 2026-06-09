<?php
session_start();
require_once 'auth_payables.php';
$full_name = $_SESSION['pay_name'] ?? '';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';

function food_tracking_query(string $sql): void
{
    global $conn;
    if (!$conn->query($sql)) {
        payables_log_error('Food tracking schema error: ' . $conn->error);
    }
}

function food_tracking_ensure_tables(): void
{
    global $conn;

    food_tracking_query("CREATE TABLE IF NOT EXISTS food_trackers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bac_monitoring_id INT NOT NULL,
        initial_cakes INT NOT NULL DEFAULT 0,
        initial_food_packs INT NOT NULL DEFAULT 0,
        created_by VARCHAR(150) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_food_trackers_bac_monitoring_id (bac_monitoring_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    food_tracking_query("CREATE TABLE IF NOT EXISTS food_tracking_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        food_tracker_id INT NOT NULL,
        deduction_date DATE NOT NULL,
        cakes_deducted INT NOT NULL DEFAULT 0,
        food_packs_deducted INT NOT NULL DEFAULT 0,
        remarks TEXT NULL,
        created_by VARCHAR(150) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_food_entries_tracker_id (food_tracker_id),
        INDEX idx_food_entries_deduction_date (deduction_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    food_tracking_query("CREATE TABLE IF NOT EXISTS food_tracker_edit_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        food_tracker_id INT NOT NULL,
        old_initial_cakes INT NOT NULL DEFAULT 0,
        old_initial_food_packs INT NOT NULL DEFAULT 0,
        new_initial_cakes INT NOT NULL DEFAULT 0,
        new_initial_food_packs INT NOT NULL DEFAULT 0,
        edited_by VARCHAR(150) NULL,
        edited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_food_edit_history_tracker_id (food_tracker_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    food_tracking_query("CREATE TABLE IF NOT EXISTS food_tracker_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        food_tracker_id INT NOT NULL,
        item_type ENUM('cakes', 'food_packs') NOT NULL,
        category_name VARCHAR(120) NOT NULL,
        initial_quantity INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by VARCHAR(150) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_food_category_name (food_tracker_id, item_type, category_name),
        INDEX idx_food_categories_tracker (food_tracker_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    food_tracking_query("CREATE TABLE IF NOT EXISTS food_deduction_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        food_tracker_id INT NOT NULL,
        deduction_date DATE NOT NULL,
        remarks TEXT NULL,
        created_by VARCHAR(150) NULL,
        legacy_entry_id INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_food_transaction_legacy (legacy_entry_id),
        INDEX idx_food_transactions_tracker (food_tracker_id),
        INDEX idx_food_transactions_date (deduction_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    food_tracking_query("CREATE TABLE IF NOT EXISTS food_deduction_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        category_id INT NOT NULL,
        quantity_deducted INT NOT NULL,
        INDEX idx_food_lines_transaction (transaction_id),
        INDEX idx_food_lines_category (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    food_tracking_query("CREATE TABLE IF NOT EXISTS food_tracking_migrations (
        migration_key VARCHAR(100) PRIMARY KEY,
        completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $migrationResult = $conn->query("SELECT migration_key FROM food_tracking_migrations WHERE migration_key = 'category_model_v1' LIMIT 1");
    if ($migrationResult && $migrationResult->num_rows > 0) {
        return;
    }

    $conn->begin_transaction();
    try {
        food_tracking_query("INSERT IGNORE INTO food_tracker_categories (food_tracker_id, item_type, category_name, initial_quantity, created_by)
            SELECT id, 'cakes', 'Uncategorized Cakes', initial_cakes, created_by FROM food_trackers WHERE initial_cakes > 0");
        food_tracking_query("INSERT IGNORE INTO food_tracker_categories (food_tracker_id, item_type, category_name, initial_quantity, created_by)
            SELECT id, 'food_packs', 'Uncategorized Food Packs', initial_food_packs, created_by FROM food_trackers WHERE initial_food_packs > 0");
        food_tracking_query("INSERT IGNORE INTO food_deduction_transactions (food_tracker_id, deduction_date, remarks, created_by, legacy_entry_id, created_at)
            SELECT food_tracker_id, deduction_date, remarks, created_by, id, created_at FROM food_tracking_entries");
        food_tracking_query("INSERT INTO food_deduction_lines (transaction_id, category_id, quantity_deducted)
            SELECT t.id, c.id, e.cakes_deducted
            FROM food_tracking_entries e
            INNER JOIN food_deduction_transactions t ON t.legacy_entry_id = e.id
            INNER JOIN food_tracker_categories c ON c.food_tracker_id = e.food_tracker_id AND c.item_type = 'cakes' AND c.category_name = 'Uncategorized Cakes'
            WHERE e.cakes_deducted > 0 AND NOT EXISTS (
                SELECT 1 FROM food_deduction_lines lx
                INNER JOIN food_tracker_categories cx ON cx.id = lx.category_id
                WHERE lx.transaction_id = t.id AND cx.item_type = 'cakes'
            )");
        food_tracking_query("INSERT INTO food_deduction_lines (transaction_id, category_id, quantity_deducted)
            SELECT t.id, c.id, e.food_packs_deducted
            FROM food_tracking_entries e
            INNER JOIN food_deduction_transactions t ON t.legacy_entry_id = e.id
            INNER JOIN food_tracker_categories c ON c.food_tracker_id = e.food_tracker_id AND c.item_type = 'food_packs' AND c.category_name = 'Uncategorized Food Packs'
            WHERE e.food_packs_deducted > 0 AND NOT EXISTS (
                SELECT 1 FROM food_deduction_lines lx
                INNER JOIN food_tracker_categories cx ON cx.id = lx.category_id
                WHERE lx.transaction_id = t.id AND cx.item_type = 'food_packs'
            )");
        food_tracking_query("INSERT INTO food_tracking_migrations (migration_key) VALUES ('category_model_v1')");
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        payables_log_error('Food tracking migration failed: ' . $error->getMessage());
    }
}

function food_tracking_is_food_sql(string $alias = 'bm'): string
{
    return "(LOWER({$alias}.project_name) LIKE '%cake%' OR LOWER({$alias}.project_name) LIKE '%food%' OR LOWER({$alias}.project_name) LIKE '%meal%' OR LOWER({$alias}.project_name) LIKE '%snack%' OR LOWER({$alias}.project_name) LIKE '%grocery%')";
}

function food_tracking_valid_int($value): bool
{
    return preg_match('/^\d+$/', (string)$value) === 1;
}

function food_tracking_redirect(array $query): void
{
    header('Location: food_tracking.php?' . http_build_query($query));
    exit;
}

function food_tracking_category_rows(int $trackerId, bool $activeOnly = false): array
{
    global $conn;
    $activeSql = $activeOnly ? ' AND c.is_active = 1' : '';
    $stmt = $conn->prepare("SELECT c.id, c.food_tracker_id, c.item_type, c.category_name, c.initial_quantity, c.is_active,
        COALESCE(SUM(l.quantity_deducted), 0) AS used_quantity
        FROM food_tracker_categories c
        LEFT JOIN food_deduction_lines l ON l.category_id = c.id
        WHERE c.food_tracker_id = ? {$activeSql}
        GROUP BY c.id, c.food_tracker_id, c.item_type, c.category_name, c.initial_quantity, c.is_active
        ORDER BY c.item_type, c.is_active DESC, c.category_name");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $trackerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result ? $result->fetch_assoc() : null) {
        $row['remaining_quantity'] = max(0, (int)$row['initial_quantity'] - (int)$row['used_quantity']);
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function food_tracking_post_lines(): array
{
    $categoryIds = $_POST['category_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $lines = [];
    foreach ($categoryIds as $index => $categoryId) {
        $id = filter_var($categoryId, FILTER_VALIDATE_INT);
        $quantity = trim((string)($quantities[$index] ?? ''));
        if ($id && food_tracking_valid_int($quantity) && (int)$quantity > 0) {
            $lines[(int)$id] = ($lines[(int)$id] ?? 0) + (int)$quantity;
        }
    }
    return $lines;
}

function food_tracking_validate_lines(int $trackerId, array $lines, ?int $editingTransactionId, array &$errors): array
{
    global $conn;
    if (!$lines) {
        $errors[] = 'Add at least one category deduction greater than zero.';
        return [];
    }

    $categories = [];
    foreach (food_tracking_category_rows($trackerId, $editingTransactionId === null) as $category) {
        $categories[(int)$category['id']] = $category;
    }

    $oldByCategory = [];
    if ($editingTransactionId) {
        $stmt = $conn->prepare("SELECT category_id, quantity_deducted FROM food_deduction_lines WHERE transaction_id = ?");
        $stmt->bind_param('i', $editingTransactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result ? $result->fetch_assoc() : null) {
            $oldByCategory[(int)$row['category_id']] = (int)$row['quantity_deducted'];
        }
        $stmt->close();
    }

    foreach ($lines as $categoryId => $quantity) {
        if (!isset($categories[$categoryId])) {
            $errors[] = 'One selected category is unavailable or archived.';
            continue;
        }
        $available = (int)$categories[$categoryId]['remaining_quantity'] + ($oldByCategory[$categoryId] ?? 0);
        if ($quantity > $available) {
            $errors[] = $categories[$categoryId]['category_name'] . ' exceeds its available balance of ' . number_format($available) . '.';
        }
    }
    return $categories;
}

food_tracking_ensure_tables();

$formErrors = [];
$searchTerm = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'active';
if (!in_array($statusFilter, ['active', 'completed', 'all'], true)) {
    $statusFilter = 'active';
}
$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['_payables_csrf_token'] ?? '';
$action = $_POST['action'] ?? '';
$allowedActions = ['add_tracker', 'save_category', 'archive_category', 'delete_category', 'add_deduction', 'edit_deduction', 'delete_deduction', 'delete_tracker'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, $allowedActions, true)) {
    if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
        $formErrors[] = 'Security token expired. Please refresh and try again.';
    }

    if (!$formErrors && $action === 'add_tracker') {
        $bacId = filter_var($_POST['bac_monitoring_id'] ?? null, FILTER_VALIDATE_INT);
        $types = $_POST['new_category_type'] ?? [];
        $names = $_POST['new_category_name'] ?? [];
        $quantities = $_POST['new_category_quantity'] ?? [];
        $categories = [];
        $seen = [];
        if (!$bacId) {
            $formErrors[] = 'Select a valid IB number.';
        }
        foreach ($names as $index => $nameValue) {
            $type = $types[$index] ?? '';
            $name = trim((string)$nameValue);
            $quantity = trim((string)($quantities[$index] ?? ''));
            if ($name === '' && ($quantity === '' || $quantity === '0')) {
                continue;
            }
            if (!in_array($type, ['cakes', 'food_packs'], true) || $name === '' || !food_tracking_valid_int($quantity)) {
                $formErrors[] = 'Each category needs a type, name, and whole-number starting quantity.';
                break;
            }
            $key = $type . '|' . strtolower($name);
            if (isset($seen[$key])) {
                $formErrors[] = 'Category names must be unique within the same item type.';
                break;
            }
            $seen[$key] = true;
            $categories[] = [$type, $name, (int)$quantity];
        }
        if (!$categories) {
            $formErrors[] = 'Add at least one category.';
        }
        if (!$formErrors) {
            $foodSql = food_tracking_is_food_sql('bm');
            $stmt = $conn->prepare("SELECT bm.id FROM bac_monitoring bm WHERE bm.id = ? AND {$foodSql} LIMIT 1");
            $stmt->bind_param('i', $bacId);
            $stmt->execute();
            $exists = $stmt->get_result();
            if (!$exists || !$exists->num_rows) {
                $formErrors[] = 'Selected IB is not a food tracking record.';
            }
            $stmt->close();
        }
        if (!$formErrors) {
            $stmt = $conn->prepare("SELECT id FROM food_trackers WHERE bac_monitoring_id = ? LIMIT 1");
            $stmt->bind_param('i', $bacId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows) {
                $formErrors[] = 'This IB already has a food tracker.';
            }
            $stmt->close();
        }
        if (!$formErrors) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO food_trackers (bac_monitoring_id, created_by) VALUES (?, ?)");
                $stmt->bind_param('is', $bacId, $full_name);
                $stmt->execute();
                $trackerId = $stmt->insert_id;
                $stmt->close();
                $categoryStmt = $conn->prepare("INSERT INTO food_tracker_categories (food_tracker_id, item_type, category_name, initial_quantity, created_by) VALUES (?, ?, ?, ?, ?)");
                foreach ($categories as [$type, $name, $quantity]) {
                    $categoryStmt->bind_param('issis', $trackerId, $type, $name, $quantity, $full_name);
                    $categoryStmt->execute();
                }
                $categoryStmt->close();
                $conn->commit();
                food_tracking_redirect(['added' => 1]);
            } catch (Throwable $error) {
                $conn->rollback();
                payables_log_error('Food tracker add failed: ' . $error->getMessage());
                $formErrors[] = 'Unable to add food tracker right now.';
            }
        }
    }

    if (!$formErrors && $action === 'save_category') {
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);
        $categoryId = filter_var($_POST['category_record_id'] ?? null, FILTER_VALIDATE_INT);
        $type = $_POST['item_type'] ?? '';
        $name = trim($_POST['category_name'] ?? '');
        $quantityText = trim($_POST['initial_quantity'] ?? '');
        if (!$trackerId || !in_array($type, ['cakes', 'food_packs'], true) || $name === '' || !food_tracking_valid_int($quantityText)) {
            $formErrors[] = 'Enter a valid category type, name, and whole-number starting quantity.';
        }
        $quantity = (int)$quantityText;
        if (!$formErrors && $categoryId) {
            $stmt = $conn->prepare("SELECT c.id, c.item_type, c.initial_quantity, COALESCE(SUM(l.quantity_deducted), 0) used_quantity
                FROM food_tracker_categories c LEFT JOIN food_deduction_lines l ON l.category_id = c.id
                WHERE c.id = ? AND c.food_tracker_id = ? GROUP BY c.id, c.item_type, c.initial_quantity");
            $stmt->bind_param('ii', $categoryId, $trackerId);
            $stmt->execute();
            $category = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$category) {
                $formErrors[] = 'Category was not found.';
            } elseif ($quantity < (int)$category['used_quantity']) {
                $formErrors[] = 'Starting quantity cannot be below the amount already deducted.';
            } elseif ((int)$category['used_quantity'] > 0 && $type !== $category['item_type']) {
                $formErrors[] = 'Item type cannot be changed after deductions have been recorded.';
            }
        }
        if (!$formErrors) {
            try {
                if ($categoryId) {
                    $stmt = $conn->prepare("UPDATE food_tracker_categories SET item_type = ?, category_name = ?, initial_quantity = ? WHERE id = ? AND food_tracker_id = ?");
                    $stmt->bind_param('ssiii', $type, $name, $quantity, $categoryId, $trackerId);
                } else {
                    $stmt = $conn->prepare("INSERT INTO food_tracker_categories (food_tracker_id, item_type, category_name, initial_quantity, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param('issis', $trackerId, $type, $name, $quantity, $full_name);
                }
                $stmt->execute();
                $stmt->close();
                food_tracking_redirect(['category_saved' => 1]);
            } catch (Throwable $error) {
                payables_log_error('Food category save failed: ' . $error->getMessage());
                $formErrors[] = 'Category name must be unique within its item type.';
            }
        }
    }

    if (!$formErrors && $action === 'archive_category') {
        $categoryId = filter_var($_POST['category_record_id'] ?? null, FILTER_VALIDATE_INT);
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);
        $targetActive = (int)($_POST['target_active'] ?? 0);
        $categories = $trackerId ? food_tracking_category_rows($trackerId) : [];
        $category = null;
        foreach ($categories as $row) {
            if ((int)$row['id'] === (int)$categoryId) {
                $category = $row;
                break;
            }
        }
        if (!$category) {
            $formErrors[] = 'Category was not found.';
        } elseif (!$targetActive && (int)$category['remaining_quantity'] > 0) {
            $formErrors[] = 'A category can only be archived after its balance reaches zero.';
        }
        if (!$formErrors) {
            $stmt = $conn->prepare("UPDATE food_tracker_categories SET is_active = ? WHERE id = ? AND food_tracker_id = ?");
            $stmt->bind_param('iii', $targetActive, $categoryId, $trackerId);
            $stmt->execute();
            $stmt->close();
            food_tracking_redirect(['category_saved' => 1]);
        }
    }

    if (!$formErrors && $action === 'delete_category') {
        $categoryId = filter_var($_POST['category_record_id'] ?? null, FILTER_VALIDATE_INT);
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);
        $category = null;
        foreach ($trackerId ? food_tracking_category_rows($trackerId) : [] as $row) {
            if ((int)$row['id'] === (int)$categoryId) {
                $category = $row;
                break;
            }
        }
        if (!$category) {
            $formErrors[] = 'Category was not found.';
        }
        if (!$formErrors) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM food_deduction_lines WHERE category_id = ?");
                $stmt->bind_param('i', $categoryId);
                $stmt->execute();
                $stmt->close();

                $conn->query("DELETE t FROM food_deduction_transactions t
                    LEFT JOIN food_deduction_lines l ON l.transaction_id = t.id
                    WHERE t.food_tracker_id = " . (int)$trackerId . " AND l.id IS NULL");

                $stmt = $conn->prepare("DELETE FROM food_tracker_categories WHERE id = ? AND food_tracker_id = ? LIMIT 1");
                $stmt->bind_param('ii', $categoryId, $trackerId);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                if (!$affected) {
                    throw new RuntimeException('Category was not found.');
                }
                $conn->commit();
                food_tracking_redirect(['category_deleted' => 1]);
            } catch (Throwable $error) {
                $conn->rollback();
                payables_log_error('Food category delete failed: ' . $error->getMessage());
                $formErrors[] = 'Unable to delete category right now.';
            }
        }
    }

    if (!$formErrors && in_array($action, ['add_deduction', 'edit_deduction'], true)) {
        $transactionId = $action === 'edit_deduction' ? filter_var($_POST['transaction_id'] ?? null, FILTER_VALIDATE_INT) : null;
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);
        $date = trim($_POST['deduction_date'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $lines = food_tracking_post_lines();
        if (!$trackerId || ($action === 'edit_deduction' && !$transactionId)) {
            $formErrors[] = 'Invalid deduction transaction.';
        }
        if (!$formErrors && $transactionId) {
            $stmt = $conn->prepare("SELECT id FROM food_deduction_transactions WHERE id = ? AND food_tracker_id = ? LIMIT 1");
            $stmt->bind_param('ii', $transactionId, $trackerId);
            $stmt->execute();
            $belongsToTracker = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if (!$belongsToTracker) {
                $formErrors[] = 'Deduction transaction was not found for this tracker.';
            }
        }
        if (!payables_valid_date_or_empty($date) || $date === '') {
            $formErrors[] = 'Deduction date is required.';
        }
        if (!$formErrors || $lines) {
            food_tracking_validate_lines((int)$trackerId, $lines, $transactionId ? (int)$transactionId : null, $formErrors);
        }
        if (!$formErrors) {
            $conn->begin_transaction();
            try {
                if ($transactionId) {
                    $stmt = $conn->prepare("UPDATE food_deduction_transactions SET deduction_date = ?, remarks = ? WHERE id = ? AND food_tracker_id = ?");
                    $stmt->bind_param('ssii', $date, $remarks, $transactionId, $trackerId);
                    $stmt->execute();
                    $stmt->close();
                    $stmt = $conn->prepare("DELETE FROM food_deduction_lines WHERE transaction_id = ?");
                    $stmt->bind_param('i', $transactionId);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("INSERT INTO food_deduction_transactions (food_tracker_id, deduction_date, remarks, created_by) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('isss', $trackerId, $date, $remarks, $full_name);
                    $stmt->execute();
                    $transactionId = $stmt->insert_id;
                    $stmt->close();
                }
                $lineStmt = $conn->prepare("INSERT INTO food_deduction_lines (transaction_id, category_id, quantity_deducted) VALUES (?, ?, ?)");
                foreach ($lines as $categoryId => $quantity) {
                    $lineStmt->bind_param('iii', $transactionId, $categoryId, $quantity);
                    $lineStmt->execute();
                }
                $lineStmt->close();
                $conn->commit();
                food_tracking_redirect([$action === 'edit_deduction' ? 'entry_updated' : 'deducted' => 1]);
            } catch (Throwable $error) {
                $conn->rollback();
                payables_log_error('Food deduction save failed: ' . $error->getMessage());
                $formErrors[] = 'Unable to save deduction right now.';
            }
        }
    }

    if (!$formErrors && $action === 'delete_deduction') {
        $transactionId = filter_var($_POST['transaction_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$transactionId) {
            $formErrors[] = 'Invalid deduction transaction.';
        } else {
            $conn->begin_transaction();
            try {
                $legacyEntryId = null;
                $stmt = $conn->prepare("SELECT legacy_entry_id FROM food_deduction_transactions WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $transactionId);
                $stmt->execute();
                $transaction = $stmt->get_result()->fetch_assoc();
                $legacyEntryId = $transaction['legacy_entry_id'] ?? null;
                $stmt->close();
                $stmt = $conn->prepare("DELETE FROM food_deduction_lines WHERE transaction_id = ?");
                $stmt->bind_param('i', $transactionId);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("DELETE FROM food_deduction_transactions WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $transactionId);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                if (!$affected) {
                    throw new RuntimeException('Transaction not found.');
                }
                if ($legacyEntryId) {
                    $stmt = $conn->prepare("DELETE FROM food_tracking_entries WHERE id = ? LIMIT 1");
                    $stmt->bind_param('i', $legacyEntryId);
                    $stmt->execute();
                    $stmt->close();
                }
                $conn->commit();
                food_tracking_redirect(['entry_deleted' => 1]);
            } catch (Throwable $error) {
                $conn->rollback();
                $formErrors[] = 'Unable to delete deduction right now.';
            }
        }
    }

    if (!$formErrors && $action === 'delete_tracker') {
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$trackerId) {
            $formErrors[] = 'Invalid food tracker.';
        } else {
            $conn->begin_transaction();
            try {
                $transactionIds = [];
                $stmt = $conn->prepare("SELECT id FROM food_deduction_transactions WHERE food_tracker_id = ?");
                $stmt->bind_param('i', $trackerId);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $transactionIds[] = (int)$row['id'];
                }
                $stmt->close();
                if ($transactionIds) {
                    $ids = implode(',', $transactionIds);
                    $conn->query("DELETE FROM food_deduction_lines WHERE transaction_id IN ({$ids})");
                }
                foreach (['food_deduction_transactions', 'food_tracker_categories', 'food_tracking_entries', 'food_tracker_edit_history'] as $table) {
                    $stmt = $conn->prepare("DELETE FROM {$table} WHERE food_tracker_id = ?");
                    $stmt->bind_param('i', $trackerId);
                    $stmt->execute();
                    $stmt->close();
                }
                $stmt = $conn->prepare("DELETE FROM food_trackers WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $trackerId);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                if (!$affected) {
                    throw new RuntimeException('Tracker not found.');
                }
                $conn->commit();
                food_tracking_redirect(['deleted' => 1]);
            } catch (Throwable $error) {
                $conn->rollback();
                payables_log_error('Food tracker delete failed: ' . $error->getMessage());
                $formErrors[] = 'Unable to delete food tracker right now.';
            }
        }
    }
}

$foodFilterSql = food_tracking_is_food_sql('bm');
$availableIbRows = [];
$ibResult = $conn->query("SELECT bm.id, bm.ib_no, bm.project_name FROM bac_monitoring bm WHERE {$foodFilterSql} ORDER BY bm.ib_no ASC, bm.id DESC");
while ($row = $ibResult ? $ibResult->fetch_assoc() : null) {
    $availableIbRows[] = $row;
}

$whereSql = '';
$params = [];
$types = '';
if ($searchTerm !== '') {
    $whereSql = 'WHERE bm.ib_no LIKE ? OR bm.project_name LIKE ?';
    $params = ['%' . $searchTerm . '%', '%' . $searchTerm . '%'];
    $types = 'ss';
}

$trackerRows = [];
$stmt = $conn->prepare("SELECT ft.id, ft.bac_monitoring_id, bm.ib_no, bm.project_name,
    COALESCE(SUM(CASE WHEN c.item_type = 'cakes' THEN c.initial_quantity ELSE 0 END), 0) initial_cakes,
    COALESCE(SUM(CASE WHEN c.item_type = 'food_packs' THEN c.initial_quantity ELSE 0 END), 0) initial_food_packs,
    (SELECT COALESCE(SUM(l.quantity_deducted), 0) FROM food_deduction_lines l INNER JOIN food_tracker_categories cx ON cx.id = l.category_id WHERE cx.food_tracker_id = ft.id AND cx.item_type = 'cakes') used_cakes,
    (SELECT COALESCE(SUM(l.quantity_deducted), 0) FROM food_deduction_lines l INNER JOIN food_tracker_categories cx ON cx.id = l.category_id WHERE cx.food_tracker_id = ft.id AND cx.item_type = 'food_packs') used_food_packs,
    (SELECT MAX(t.deduction_date) FROM food_deduction_transactions t WHERE t.food_tracker_id = ft.id) last_deduction_date
    FROM food_trackers ft
    INNER JOIN bac_monitoring bm ON bm.id = ft.bac_monitoring_id
    LEFT JOIN food_tracker_categories c ON c.food_tracker_id = ft.id
    {$whereSql}
    GROUP BY ft.id, ft.bac_monitoring_id, bm.ib_no, bm.project_name
    ORDER BY ft.updated_at DESC, ft.id DESC");
if ($stmt) {
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result ? $result->fetch_assoc() : null) {
        $row['remaining_cakes'] = max(0, (int)$row['initial_cakes'] - (int)$row['used_cakes']);
        $row['remaining_food_packs'] = max(0, (int)$row['initial_food_packs'] - (int)$row['used_food_packs']);
        $row['is_completed'] = $row['remaining_cakes'] === 0 && $row['remaining_food_packs'] === 0;
        if (($statusFilter === 'active' && $row['is_completed']) || ($statusFilter === 'completed' && !$row['is_completed'])) {
            continue;
        }
        $trackerRows[] = $row;
    }
    $stmt->close();
}

$categoriesByTracker = [];
$historyByTracker = [];
foreach ($trackerRows as $row) {
    $trackerId = (int)$row['id'];
    $categoriesByTracker[$trackerId] = food_tracking_category_rows($trackerId);
    $stmt = $conn->prepare("SELECT t.id, t.food_tracker_id, t.deduction_date, t.remarks, t.created_by, t.created_at,
        l.category_id, l.quantity_deducted, c.item_type, c.category_name
        FROM food_deduction_transactions t
        INNER JOIN food_deduction_lines l ON l.transaction_id = t.id
        INNER JOIN food_tracker_categories c ON c.id = l.category_id
        WHERE t.food_tracker_id = ? ORDER BY t.deduction_date DESC, t.id DESC, c.item_type, c.category_name");
    $stmt->bind_param('i', $trackerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($entry = $result ? $result->fetch_assoc() : null) {
        $transactionId = (int)$entry['id'];
        if (!isset($historyByTracker[$trackerId][$transactionId])) {
            $historyByTracker[$trackerId][$transactionId] = [
                'id' => $transactionId,
                'food_tracker_id' => $trackerId,
                'deduction_date' => $entry['deduction_date'],
                'remarks' => $entry['remarks'],
                'created_by' => $entry['created_by'],
                'lines' => []
            ];
        }
        $historyByTracker[$trackerId][$transactionId]['lines'][] = [
            'category_id' => (int)$entry['category_id'],
            'quantity' => (int)$entry['quantity_deducted'],
            'item_type' => $entry['item_type'],
            'category_name' => $entry['category_name']
        ];
    }
    $stmt->close();
    $historyByTracker[$trackerId] = array_values($historyByTracker[$trackerId] ?? []);
}

$totalCakesRemaining = array_sum(array_column($trackerRows, 'remaining_cakes'));
$totalFoodPacksRemaining = array_sum(array_column($trackerRows, 'remaining_food_packs'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="food_tracking.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Food Tracking</title>
</head>
<body class="food-tracking-page">
<?php $payablesActivePage = 'food_tracking'; require 'payables_sidebar.php'; ?>
<main class="food-tracking-content">
    <section class="food-tracking-shell" aria-label="Food tracking list">
        <div class="food-tracking-header">
            <div><span class="food-tracking-eyebrow">Food Tracking</span><h1>Cakes and Food Packs</h1></div>
            <div class="food-tracking-actions">
                <button type="button" class="food-add-button" data-bs-toggle="modal" data-bs-target="#addTrackerModal"><i class="fas fa-plus"></i><span>Add Tracker</span></button>
                <form class="food-tracking-search" method="get" role="search">
                    <select name="status" class="food-filter-select" onchange="this.form.submit()">
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                    </select>
                    <input type="search" name="search" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES); ?>" placeholder="Search IB or project">
                    <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
        <?php if ($formErrors): ?><div class="food-alert is-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES); ?></div><?php endif; ?>
        <?php
        $successMessages = ['added' => 'Food tracker added.', 'category_saved' => 'Category saved.', 'category_deleted' => 'Category deleted.', 'deducted' => 'Deduction saved.', 'deleted' => 'Food tracker deleted.', 'entry_updated' => 'Deduction updated.', 'entry_deleted' => 'Deduction deleted.'];
        foreach ($successMessages as $key => $message):
            if (isset($_GET[$key])):
        ?><div class="food-alert is-success"><i class="fas fa-check"></i><?php echo $message; ?></div><?php endif; endforeach; ?>
        <div class="food-summary-strip">
            <div><span><?php echo number_format(count($trackerRows)); ?></span><strong>Trackers</strong></div>
            <div><span><?php echo number_format($totalCakesRemaining); ?></span><strong>Cakes Left</strong></div>
            <div><span><?php echo number_format($totalFoodPacksRemaining); ?></span><strong>Food Packs Left</strong></div>
        </div>
        <div class="food-tracking-table" role="table">
            <div class="food-tracking-row food-tracking-row-head" role="row"><div>IB No.</div><div>Project</div><div>Cakes Balance</div><div>Food Packs Balance</div><div>Last Deduction</div><div>Status</div><div>Actions</div></div>
            <?php if (!$trackerRows): ?><div class="food-tracking-empty"><?php echo $searchTerm ? 'No matching food tracking records found.' : 'No food trackers yet.'; ?></div><?php endif; ?>
            <?php foreach ($trackerRows as $row):
                $trackerId = (int)$row['id'];
                $categories = $categoriesByTracker[$trackerId] ?? [];
                $history = $historyByTracker[$trackerId] ?? [];
                $lastDate = $row['last_deduction_date'] ? date('M d, Y', strtotime($row['last_deduction_date'])) : '-';
                $commonData = ' data-tracker-id="' . $trackerId . '" data-reference="' . htmlspecialchars($row['ib_no'], ENT_QUOTES) . '" data-project="' . htmlspecialchars($row['project_name'], ENT_QUOTES) . '"';
            ?>
            <div class="food-tracking-row" role="row">
                <div class="food-ref"><?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES); ?></div>
                <div class="food-project"><?php echo htmlspecialchars($row['project_name'], ENT_QUOTES); ?></div>
                <div class="food-balance <?php echo !$row['remaining_cakes'] ? 'is-zero' : ''; ?>"><strong><?php echo number_format($row['remaining_cakes']); ?></strong><span>of <?php echo number_format($row['initial_cakes']); ?></span></div>
                <div class="food-balance <?php echo !$row['remaining_food_packs'] ? 'is-zero' : ''; ?>"><strong><?php echo number_format($row['remaining_food_packs']); ?></strong><span>of <?php echo number_format($row['initial_food_packs']); ?></span></div>
                <div class="food-date"><?php echo $lastDate; ?></div>
                <div><span class="food-status-badge <?php echo $row['is_completed'] ? 'is-completed' : 'is-active'; ?>"><?php echo $row['is_completed'] ? 'Completed' : 'Active'; ?></span></div>
                <div class="food-row-actions">
                    <button type="button" class="food-icon-button food-deduct-row" title="Add Deduction" data-bs-toggle="modal" data-bs-target="#deductionModal"<?php echo $commonData; ?> data-categories="<?php echo htmlspecialchars(json_encode($categories), ENT_QUOTES); ?>"><i class="fas fa-minus"></i></button>
                    <button type="button" class="food-icon-button food-category-row" title="Manage Categories" data-bs-toggle="modal" data-bs-target="#categoryModal"<?php echo $commonData; ?> data-categories="<?php echo htmlspecialchars(json_encode($categories), ENT_QUOTES); ?>"><i class="fas fa-layer-group"></i></button>
                    <button type="button" class="food-icon-button food-history-row" title="View History" data-bs-toggle="modal" data-bs-target="#historyModal"<?php echo $commonData; ?> data-history="<?php echo htmlspecialchars(json_encode($history), ENT_QUOTES); ?>"><i class="fas fa-history"></i></button>
                    <button type="button" class="food-icon-button is-danger food-delete-row" title="Delete Tracker" data-bs-toggle="modal" data-bs-target="#deleteTrackerModal"<?php echo $commonData; ?>><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<div class="modal fade" id="addTrackerModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered food-wide-dialog"><form class="modal-content food-modal" method="post">
    <div class="modal-header"><div><h5 class="modal-title">Add Food Tracker</h5><div class="food-modal-subtitle">Select an IB and define its starting categories.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="add_tracker">
        <div class="food-form-grid"><label class="is-wide"><span>IB No.</span><select name="bac_monitoring_id" required><option value="">Select IB number</option><?php foreach ($availableIbRows as $ib): ?><option value="<?php echo (int)$ib['id']; ?>"><?php echo htmlspecialchars($ib['ib_no'] . ' - ' . $ib['project_name'], ENT_QUOTES); ?></option><?php endforeach; ?></select></label></div>
        <div class="food-section-toolbar"><strong>Starting Categories</strong><button type="button" class="food-inline-button add-category-input"><i class="fas fa-plus"></i> Add Category</button></div>
        <div class="food-line-list" id="newCategoryList"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success food-save-button">Save Tracker</button></div>
</form></div></div>

<div class="modal fade" id="categoryModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered food-history-dialog"><div class="modal-content food-modal">
    <div class="modal-header"><div><h5 class="modal-title">Manage Categories</h5><div class="food-modal-subtitle" id="categorySubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="food-category-manage-list" id="categoryManageList"></div>
        <form method="post" class="food-category-editor" id="categoryEditor"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="tracker_id" id="categoryTrackerId"><input type="hidden" name="category_record_id" id="categoryRecordId">
            <label><span>Item Type</span><select name="item_type" id="categoryType" required><option value="cakes">Cakes</option><option value="food_packs">Food Packs</option></select></label>
            <label><span>Category Name</span><input name="category_name" id="categoryName" required placeholder="e.g. PWD"></label>
            <label><span>Starting Quantity</span><input type="number" min="0" step="1" name="initial_quantity" id="categoryInitial" required value="0"></label>
            <button class="food-add-button" id="categorySaveButton"><i class="fas fa-plus"></i><span>Add Category</span></button>
        </form>
        <form method="post" id="archiveCategoryForm" class="d-none"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="archive_category"><input type="hidden" name="tracker_id" id="archiveTrackerId"><input type="hidden" name="category_record_id" id="archiveCategoryId"><input type="hidden" name="target_active" id="archiveTargetActive"></form>
        <form method="post" id="deleteCategoryForm" class="d-none"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="tracker_id" id="deleteCategoryTrackerId"><input type="hidden" name="category_record_id" id="deleteCategoryId"></form>
    </div>
</div></div></div>

<div class="modal fade" id="deductionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered food-wide-dialog"><form class="modal-content food-modal" method="post">
    <div class="modal-header"><div><h5 class="modal-title">Add Deduction</h5><div class="food-modal-subtitle" id="deductionSubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="add_deduction"><input type="hidden" name="tracker_id" id="deductionTrackerId">
        <div class="food-form-grid"><label><span>Date</span><input type="date" name="deduction_date" value="<?php echo date('Y-m-d'); ?>" required></label><label><span>Remarks</span><input name="remarks" placeholder="Optional remarks"></label></div>
        <div class="food-section-toolbar"><strong>Category Deductions</strong><button type="button" class="food-inline-button add-deduction-line"><i class="fas fa-plus"></i> Add Line</button></div>
        <div class="food-line-list deduction-lines"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success food-save-button">Save Deduction</button></div>
</form></div></div>

<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered food-history-dialog"><div class="modal-content food-modal">
    <div class="modal-header"><div><h5 class="modal-title">Tracking History</h5><div class="food-modal-subtitle" id="historySubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="food-history-list" id="historyList"></div></div>
</div></div></div>

<div class="modal fade" id="editDeductionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered food-wide-dialog"><form class="modal-content food-modal" method="post">
    <div class="modal-header"><div><h5 class="modal-title">Edit Deduction</h5><div class="food-modal-subtitle">Update the entire dated transaction.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="edit_deduction"><input type="hidden" name="tracker_id" id="editTransactionTrackerId"><input type="hidden" name="transaction_id" id="editTransactionId">
        <div class="food-form-grid"><label><span>Date</span><input type="date" name="deduction_date" id="editTransactionDate" required></label><label><span>Remarks</span><input name="remarks" id="editTransactionRemarks"></label></div>
        <div class="food-section-toolbar"><strong>Category Deductions</strong><button type="button" class="food-inline-button add-edit-line"><i class="fas fa-plus"></i> Add Line</button></div>
        <div class="food-line-list edit-deduction-lines"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success food-save-button">Save Changes</button></div>
</form></div></div>

<div class="modal fade" id="deleteDeductionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered food-dialog"><form class="modal-content food-modal" method="post">
    <div class="modal-header"><div><h5 class="modal-title">Delete Deduction</h5><div class="food-modal-subtitle">All category lines in this transaction will be removed.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="delete_deduction"><input type="hidden" name="transaction_id" id="deleteTransactionId"><div class="food-delete-warning"><i class="fas fa-exclamation-triangle"></i><span id="deleteTransactionMessage"></span></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete Transaction</button></div>
</form></div></div>

<div class="modal fade" id="deleteTrackerModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered food-dialog"><form class="modal-content food-modal" method="post">
    <div class="modal-header"><div><h5 class="modal-title">Delete Food Tracker</h5><div class="food-modal-subtitle" id="deleteSubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="delete_tracker"><input type="hidden" name="tracker_id" id="deleteTrackerId"><div class="food-delete-warning"><i class="fas fa-exclamation-triangle"></i><span>Delete this tracker, its categories, and deduction history?</span></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete Tracker</button></div>
</form></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrfInput = <?php echo json_encode(payables_csrf_input()); ?>;
let activeCategories = [];
let activeHistory = [];

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[char]));
}
function parseData(button, key) {
    try { return JSON.parse(button.dataset[key] || "[]"); } catch (error) { return []; }
}
function labelType(type) { return type === "cakes" ? "Cakes" : "Food Packs"; }
function categoryOption(category, selectedId) {
    const selected = String(category.id) === String(selectedId) ? " selected" : "";
    const disabled = Number(category.is_active) === 0 && !selected ? " disabled" : "";
    return '<option value="' + category.id + '"' + selected + disabled + '>' + escapeHtml(labelType(category.item_type) + " - " + category.category_name + " (" + category.remaining_quantity + " left)") + '</option>';
}
function deductionLineHtml(categories, line = {}) {
    const options = categories.filter(category => Number(category.is_active) === 1 || String(category.id) === String(line.category_id)).map(category => categoryOption(category, line.category_id)).join("");
    return '<div class="food-input-line"><label><span>Category</span><select name="category_id[]" required><option value="">Select category</option>' + options + '</select></label><label><span>Quantity</span><input type="number" name="quantity[]" min="1" step="1" value="' + escapeHtml(line.quantity || 1) + '" required></label><button type="button" class="food-remove-line" title="Remove line"><i class="fas fa-times"></i></button></div>';
}
function categoryInputHtml() {
    return '<div class="food-input-line"><label><span>Item Type</span><select name="new_category_type[]" required><option value="cakes">Cakes</option><option value="food_packs">Food Packs</option></select></label><label><span>Category Name</span><input name="new_category_name[]" placeholder="e.g. Senior Citizens" required></label><label><span>Starting Quantity</span><input type="number" name="new_category_quantity[]" min="0" step="1" value="0" required></label><button type="button" class="food-remove-line" title="Remove category"><i class="fas fa-times"></i></button></div>';
}
function bindLineRemoval(container) {
    container.querySelectorAll(".food-remove-line").forEach(button => button.onclick = () => button.closest(".food-input-line").remove());
}
function renderLines(container, categories, lines = []) {
    container.innerHTML = (lines.length ? lines : [{}]).map(line => deductionLineHtml(categories, line)).join("");
    bindLineRemoval(container);
}
function switchModal(fromId, toId) {
    const from = bootstrap.Modal.getInstance(document.getElementById(fromId));
    const target = bootstrap.Modal.getOrCreateInstance(document.getElementById(toId));
    if (!from) return target.show();
    document.getElementById(fromId).addEventListener("hidden.bs.modal", () => target.show(), {once:true});
    from.hide();
}

document.querySelector(".add-category-input").onclick = function () {
    const list = document.getElementById("newCategoryList");
    list.insertAdjacentHTML("beforeend", categoryInputHtml());
    bindLineRemoval(list);
};
document.getElementById("addTrackerModal").addEventListener("show.bs.modal", function () {
    const list = document.getElementById("newCategoryList");
    if (!list.children.length) list.innerHTML = categoryInputHtml();
    bindLineRemoval(list);
});

document.querySelectorAll(".food-deduct-row").forEach(button => button.onclick = function () {
    activeCategories = parseData(button, "categories");
    document.getElementById("deductionTrackerId").value = button.dataset.trackerId;
    document.getElementById("deductionSubtitle").textContent = button.dataset.reference + " - " + button.dataset.project;
    renderLines(document.querySelector(".deduction-lines"), activeCategories);
});
document.querySelector(".add-deduction-line").onclick = function () {
    const list = document.querySelector(".deduction-lines");
    list.insertAdjacentHTML("beforeend", deductionLineHtml(activeCategories));
    bindLineRemoval(list);
};

function renderCategoryManager(categories) {
    const list = document.getElementById("categoryManageList");
    if (!categories.length) {
        list.innerHTML = '<div class="food-history-empty">No categories yet.</div>';
        return;
    }
    list.innerHTML = categories.map(category => '<div class="food-category-manage-row' + (Number(category.is_active) ? '' : ' is-archived') + '"><div><strong>' + escapeHtml(category.category_name) + '</strong><span>' + escapeHtml(labelType(category.item_type)) + '</span></div><div class="food-balance"><strong>' + escapeHtml(category.remaining_quantity) + '</strong><span>of ' + escapeHtml(category.initial_quantity) + '</span></div><div class="food-row-actions"><button type="button" class="food-history-action edit-category" title="Edit category" data-category="' + escapeHtml(JSON.stringify(category)) + '"><i class="fas fa-pen"></i></button><button type="button" class="food-history-action archive-category" title="' + (Number(category.is_active) ? 'Archive' : 'Restore') + ' category" data-category-id="' + category.id + '" data-target-active="' + (Number(category.is_active) ? 0 : 1) + '"' + (Number(category.is_active) && Number(category.remaining_quantity) > 0 ? ' disabled' : '') + '><i class="fas fa-' + (Number(category.is_active) ? 'archive' : 'undo') + '"></i></button><button type="button" class="food-history-action is-danger delete-category" title="Delete category" data-category-id="' + category.id + '" data-category-name="' + escapeHtml(category.category_name) + '" data-used-quantity="' + escapeHtml(category.used_quantity) + '"><i class="fas fa-trash"></i></button></div></div>').join("");
}
document.querySelectorAll(".food-category-row").forEach(button => button.onclick = function () {
    activeCategories = parseData(button, "categories");
    document.getElementById("categorySubtitle").textContent = button.dataset.reference + " - " + button.dataset.project;
    document.getElementById("categoryTrackerId").value = button.dataset.trackerId;
    document.getElementById("archiveTrackerId").value = button.dataset.trackerId;
    document.getElementById("deleteCategoryTrackerId").value = button.dataset.trackerId;
    document.getElementById("categoryRecordId").value = "";
    document.getElementById("categoryName").value = "";
    document.getElementById("categoryInitial").value = "0";
    document.getElementById("categorySaveButton").innerHTML = '<i class="fas fa-plus"></i><span>Add Category</span>';
    renderCategoryManager(activeCategories);
});
document.getElementById("categoryManageList").onclick = function (event) {
    const edit = event.target.closest(".edit-category");
    if (edit) {
        const category = JSON.parse(edit.dataset.category);
        document.getElementById("categoryRecordId").value = category.id;
        document.getElementById("categoryType").value = category.item_type;
        document.getElementById("categoryName").value = category.category_name;
        document.getElementById("categoryInitial").value = category.initial_quantity;
        document.getElementById("categoryInitial").min = category.used_quantity;
        document.getElementById("categorySaveButton").innerHTML = '<i class="fas fa-save"></i><span>Save Category</span>';
    }
    const archive = event.target.closest(".archive-category");
    if (archive && !archive.disabled) {
        document.getElementById("archiveCategoryId").value = archive.dataset.categoryId;
        document.getElementById("archiveTargetActive").value = archive.dataset.targetActive;
        document.getElementById("archiveCategoryForm").submit();
    }
    const remove = event.target.closest(".delete-category");
    if (remove) {
        const historyWarning = Number(remove.dataset.usedQuantity) > 0 ? " Its related deduction history will also be deleted." : "";
        if (!window.confirm('Delete category "' + remove.dataset.categoryName + '"?' + historyWarning)) return;
        document.getElementById("deleteCategoryId").value = remove.dataset.categoryId;
        document.getElementById("deleteCategoryForm").submit();
    }
};

function renderHistory(history) {
    const list = document.getElementById("historyList");
    if (!history.length) return list.innerHTML = '<div class="food-history-empty">No deductions recorded yet.</div>';
    list.innerHTML = history.map(entry => {
        const lines = entry.lines.map(line => '<span class="food-history-line"><strong>' + escapeHtml(labelType(line.item_type)) + ' - ' + escapeHtml(line.category_name) + '</strong><b>' + escapeHtml(line.quantity) + '</b></span>').join("");
        return '<div class="food-history-transaction"><div class="food-history-transaction-head"><strong>' + escapeHtml(entry.deduction_date) + '</strong><span>' + escapeHtml(entry.remarks || "No remarks") + '</span><div class="food-history-actions"><button type="button" class="food-history-action edit-history-entry" data-entry="' + escapeHtml(JSON.stringify(entry)) + '"><i class="fas fa-pen"></i></button><button type="button" class="food-history-action is-danger delete-history-entry" data-id="' + entry.id + '" data-date="' + escapeHtml(entry.deduction_date) + '"><i class="fas fa-trash"></i></button></div></div><div class="food-history-lines">' + lines + '</div></div>';
    }).join("");
}
document.querySelectorAll(".food-history-row").forEach(button => button.onclick = function () {
    activeCategories = document.querySelector('.food-category-row[data-tracker-id="' + button.dataset.trackerId + '"]') ? parseData(document.querySelector('.food-category-row[data-tracker-id="' + button.dataset.trackerId + '"]'), "categories") : [];
    activeHistory = parseData(button, "history");
    document.getElementById("historySubtitle").textContent = button.dataset.reference + " - " + button.dataset.project;
    renderHistory(activeHistory);
});
document.getElementById("historyList").onclick = function (event) {
    const edit = event.target.closest(".edit-history-entry");
    if (edit) {
        const entry = JSON.parse(edit.dataset.entry);
        document.getElementById("editTransactionTrackerId").value = entry.food_tracker_id;
        document.getElementById("editTransactionId").value = entry.id;
        document.getElementById("editTransactionDate").value = entry.deduction_date;
        document.getElementById("editTransactionRemarks").value = entry.remarks || "";
        renderLines(document.querySelector(".edit-deduction-lines"), activeCategories, entry.lines);
        switchModal("historyModal", "editDeductionModal");
    }
    const remove = event.target.closest(".delete-history-entry");
    if (remove) {
        document.getElementById("deleteTransactionId").value = remove.dataset.id;
        document.getElementById("deleteTransactionMessage").textContent = "Delete the complete transaction dated " + remove.dataset.date + "?";
        switchModal("historyModal", "deleteDeductionModal");
    }
};
document.querySelector(".add-edit-line").onclick = function () {
    const list = document.querySelector(".edit-deduction-lines");
    list.insertAdjacentHTML("beforeend", deductionLineHtml(activeCategories));
    bindLineRemoval(list);
};
document.querySelectorAll(".food-delete-row").forEach(button => button.onclick = function () {
    document.getElementById("deleteTrackerId").value = button.dataset.trackerId;
    document.getElementById("deleteSubtitle").textContent = button.dataset.reference + " - " + button.dataset.project;
});
</script>
</body>
</html>
