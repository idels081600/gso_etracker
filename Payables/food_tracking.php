<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';

function food_tracking_ensure_tables(): void
{
    global $conn;

    $trackersSql = "CREATE TABLE IF NOT EXISTS food_trackers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bac_monitoring_id INT NOT NULL,
        initial_cakes INT NOT NULL DEFAULT 0,
        initial_food_packs INT NOT NULL DEFAULT 0,
        created_by VARCHAR(150) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_food_trackers_bac_monitoring_id (bac_monitoring_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $entriesSql = "CREATE TABLE IF NOT EXISTS food_tracking_entries (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($trackersSql)) {
        payables_log_error('Food tracker table creation failed: ' . $conn->error);
    }
    if (!$conn->query($entriesSql)) {
        payables_log_error('Food entry table creation failed: ' . $conn->error);
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

function food_tracking_get_balance(int $trackerId): ?array
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            ft.id,
            ft.initial_cakes,
            ft.initial_food_packs,
            COALESCE(SUM(e.cakes_deducted), 0) AS used_cakes,
            COALESCE(SUM(e.food_packs_deducted), 0) AS used_food_packs
        FROM food_trackers ft
        LEFT JOIN food_tracking_entries e ON e.food_tracker_id = ft.id
        WHERE ft.id = ?
        GROUP BY ft.id, ft.initial_cakes, ft.initial_food_packs
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $trackerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['remaining_cakes'] = max(0, (int)$row['initial_cakes'] - (int)$row['used_cakes']);
    $row['remaining_food_packs'] = max(0, (int)$row['initial_food_packs'] - (int)$row['used_food_packs']);
    return $row;
}

function food_tracking_redirect(array $query): void
{
    header('Location: food_tracking.php?' . http_build_query($query));
    exit;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_tracker', 'edit_tracker', 'add_deduction', 'delete_tracker'], true)) {
    if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
        $formErrors[] = 'Security token expired. Please refresh and try again.';
    }

    if (!$formErrors && $action === 'add_tracker') {
        $bacId = filter_var($_POST['bac_monitoring_id'] ?? null, FILTER_VALIDATE_INT);
        $initialCakes = trim($_POST['initial_cakes'] ?? '');
        $initialFoodPacks = trim($_POST['initial_food_packs'] ?? '');

        if (!$bacId || $bacId < 1) {
            $formErrors[] = 'Select a valid IB number.';
        }
        if (!food_tracking_valid_int($initialCakes) || !food_tracking_valid_int($initialFoodPacks)) {
            $formErrors[] = 'Starting quantities must be whole numbers.';
        }
        if ((int)$initialCakes === 0 && (int)$initialFoodPacks === 0) {
            $formErrors[] = 'Enter at least one starting quantity greater than zero.';
        }

        if (!$formErrors) {
            $foodSql = food_tracking_is_food_sql('bm');
            $existsStmt = $conn->prepare("SELECT id FROM bac_monitoring bm WHERE bm.id = ? AND {$foodSql} LIMIT 1");
            $existsStmt->bind_param('i', $bacId);
            $existsStmt->execute();
            $exists = $existsStmt->get_result();
            $isFoodRecord = $exists && $exists->num_rows > 0;
            $existsStmt->close();

            if (!$isFoodRecord) {
                $formErrors[] = 'Selected IB is not a food tracking record.';
            }
        }

        if (!$formErrors) {
            $activeStmt = $conn->prepare("
                SELECT ft.id, ft.initial_cakes, ft.initial_food_packs,
                    COALESCE(SUM(e.cakes_deducted), 0) AS used_cakes,
                    COALESCE(SUM(e.food_packs_deducted), 0) AS used_food_packs
                FROM food_trackers ft
                LEFT JOIN food_tracking_entries e ON e.food_tracker_id = ft.id
                WHERE ft.bac_monitoring_id = ?
                GROUP BY ft.id, ft.initial_cakes, ft.initial_food_packs
            ");
            $activeStmt->bind_param('i', $bacId);
            $activeStmt->execute();
            $activeResult = $activeStmt->get_result();
            while ($row = $activeResult ? $activeResult->fetch_assoc() : null) {
                $remainingCakes = (int)$row['initial_cakes'] - (int)$row['used_cakes'];
                $remainingFoodPacks = (int)$row['initial_food_packs'] - (int)$row['used_food_packs'];
                if ($remainingCakes > 0 || $remainingFoodPacks > 0) {
                    $formErrors[] = 'This IB already has an active food tracker.';
                    break;
                }
            }
            $activeStmt->close();
        }

        if (!$formErrors) {
            $stmt = $conn->prepare("INSERT INTO food_trackers (bac_monitoring_id, initial_cakes, initial_food_packs, created_by) VALUES (?, ?, ?, ?)");
            $cakes = (int)$initialCakes;
            $foodPacks = (int)$initialFoodPacks;
            $stmt->bind_param('iiis', $bacId, $cakes, $foodPacks, $full_name);
            if ($stmt->execute()) {
                $stmt->close();
                food_tracking_redirect(['added' => 1]);
            }
            $formErrors[] = 'Unable to add food tracker right now.';
            $stmt->close();
        }
    }

    if (!$formErrors && $action === 'edit_tracker') {
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);
        $initialCakes = trim($_POST['initial_cakes'] ?? '');
        $initialFoodPacks = trim($_POST['initial_food_packs'] ?? '');

        if (!$trackerId || $trackerId < 1) {
            $formErrors[] = 'Invalid food tracker.';
        }
        if (!food_tracking_valid_int($initialCakes) || !food_tracking_valid_int($initialFoodPacks)) {
            $formErrors[] = 'Starting quantities must be whole numbers.';
        }
        if ((int)$initialCakes === 0 && (int)$initialFoodPacks === 0) {
            $formErrors[] = 'Enter at least one starting quantity greater than zero.';
        }

        $balance = $trackerId ? food_tracking_get_balance($trackerId) : null;
        if (!$balance) {
            $formErrors[] = 'Food tracker was not found.';
        } elseif ((int)$initialCakes < (int)$balance['used_cakes'] || (int)$initialFoodPacks < (int)$balance['used_food_packs']) {
            $formErrors[] = 'Starting quantities cannot be lower than the quantities already deducted.';
        }

        if (!$formErrors) {
            $stmt = $conn->prepare("UPDATE food_trackers SET initial_cakes = ?, initial_food_packs = ? WHERE id = ? LIMIT 1");
            $cakes = (int)$initialCakes;
            $foodPacks = (int)$initialFoodPacks;
            $stmt->bind_param('iii', $cakes, $foodPacks, $trackerId);
            if ($stmt->execute()) {
                $stmt->close();
                food_tracking_redirect(['updated' => 1]);
            }
            $formErrors[] = 'Unable to update food tracker right now.';
            $stmt->close();
        }
    }

    if (!$formErrors && $action === 'add_deduction') {
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);
        $deductionDate = trim($_POST['deduction_date'] ?? '');
        $cakesDeducted = trim($_POST['cakes_deducted'] ?? '0');
        $foodPacksDeducted = trim($_POST['food_packs_deducted'] ?? '0');
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$trackerId || $trackerId < 1) {
            $formErrors[] = 'Invalid food tracker.';
        }
        if (!payables_valid_date_or_empty($deductionDate) || $deductionDate === '') {
            $formErrors[] = 'Deduction date is required.';
        }
        if (!food_tracking_valid_int($cakesDeducted) || !food_tracking_valid_int($foodPacksDeducted)) {
            $formErrors[] = 'Deduction quantities must be whole numbers.';
        }
        if ((int)$cakesDeducted === 0 && (int)$foodPacksDeducted === 0) {
            $formErrors[] = 'Deduct at least one cake or food pack.';
        }

        $balance = $trackerId ? food_tracking_get_balance($trackerId) : null;
        if (!$balance) {
            $formErrors[] = 'Food tracker was not found.';
        } elseif ((int)$cakesDeducted > (int)$balance['remaining_cakes'] || (int)$foodPacksDeducted > (int)$balance['remaining_food_packs']) {
            $formErrors[] = 'Deduction cannot exceed the remaining balance.';
        }

        if (!$formErrors) {
            $stmt = $conn->prepare("INSERT INTO food_tracking_entries (food_tracker_id, deduction_date, cakes_deducted, food_packs_deducted, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $cakes = (int)$cakesDeducted;
            $foodPacks = (int)$foodPacksDeducted;
            $stmt->bind_param('isiiss', $trackerId, $deductionDate, $cakes, $foodPacks, $remarks, $full_name);
            if ($stmt->execute()) {
                $stmt->close();
                food_tracking_redirect(['deducted' => 1]);
            }
            $formErrors[] = 'Unable to save deduction right now.';
            $stmt->close();
        }
    }

    if (!$formErrors && $action === 'delete_tracker') {
        $trackerId = filter_var($_POST['tracker_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$trackerId || $trackerId < 1) {
            $formErrors[] = 'Invalid food tracker.';
        }

        if (!$formErrors) {
            $conn->begin_transaction();
            try {
                $entriesStmt = $conn->prepare("DELETE FROM food_tracking_entries WHERE food_tracker_id = ?");
                if (!$entriesStmt) {
                    throw new RuntimeException('Unable to prepare deduction delete.');
                }
                $entriesStmt->bind_param('i', $trackerId);
                $entriesStmt->execute();
                $entriesStmt->close();

                $trackerStmt = $conn->prepare("DELETE FROM food_trackers WHERE id = ? LIMIT 1");
                if (!$trackerStmt) {
                    throw new RuntimeException('Unable to prepare tracker delete.');
                }
                $trackerStmt->bind_param('i', $trackerId);
                $trackerStmt->execute();
                $affectedRows = $trackerStmt->affected_rows;
                $trackerStmt->close();

                if ($affectedRows < 1) {
                    throw new RuntimeException('Food tracker was not found.');
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
$ibResult = $conn->query("
    SELECT bm.id, bm.ib_no, bm.project_name
    FROM bac_monitoring bm
    WHERE {$foodFilterSql}
    ORDER BY bm.ib_no ASC, bm.id DESC
");
while ($row = $ibResult ? $ibResult->fetch_assoc() : null) {
    $availableIbRows[] = $row;
}

$where = [];
$types = '';
$params = [];
if ($searchTerm !== '') {
    $like = '%' . $searchTerm . '%';
    $where[] = "(bm.ib_no LIKE ? OR bm.project_name LIKE ?)";
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$trackerRows = [];
$sql = "
    SELECT
        ft.id,
        ft.bac_monitoring_id,
        ft.initial_cakes,
        ft.initial_food_packs,
        bm.ib_no,
        bm.project_name,
        COALESCE(SUM(e.cakes_deducted), 0) AS used_cakes,
        COALESCE(SUM(e.food_packs_deducted), 0) AS used_food_packs,
        MAX(e.deduction_date) AS last_deduction_date
    FROM food_trackers ft
    INNER JOIN bac_monitoring bm ON bm.id = ft.bac_monitoring_id
    LEFT JOIN food_tracking_entries e ON e.food_tracker_id = ft.id
    {$whereSql}
    GROUP BY ft.id, ft.bac_monitoring_id, ft.initial_cakes, ft.initial_food_packs, bm.ib_no, bm.project_name
    ORDER BY ft.updated_at DESC, ft.id DESC
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result ? $result->fetch_assoc() : null) {
        $row['remaining_cakes'] = max(0, (int)$row['initial_cakes'] - (int)$row['used_cakes']);
        $row['remaining_food_packs'] = max(0, (int)$row['initial_food_packs'] - (int)$row['used_food_packs']);
        $row['is_completed'] = $row['remaining_cakes'] === 0 && $row['remaining_food_packs'] === 0;
        if ($statusFilter === 'active' && $row['is_completed']) {
            continue;
        }
        if ($statusFilter === 'completed' && !$row['is_completed']) {
            continue;
        }
        $trackerRows[] = $row;
    }
    $stmt->close();
}

$historyByTracker = [];
if ($trackerRows) {
    $trackerIds = array_map(static fn($row) => (int)$row['id'], $trackerRows);
    $placeholders = implode(',', array_fill(0, count($trackerIds), '?'));
    $historyTypes = str_repeat('i', count($trackerIds));
    $historyStmt = $conn->prepare("
        SELECT food_tracker_id, deduction_date, cakes_deducted, food_packs_deducted, remarks, created_by, created_at
        FROM food_tracking_entries
        WHERE food_tracker_id IN ({$placeholders})
        ORDER BY deduction_date DESC, id DESC
    ");
    if ($historyStmt) {
        $historyStmt->bind_param($historyTypes, ...$trackerIds);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();
        while ($entry = $historyResult ? $historyResult->fetch_assoc() : null) {
            $historyByTracker[(int)$entry['food_tracker_id']][] = $entry;
        }
        $historyStmt->close();
    }
}

$totalCakesRemaining = array_sum(array_map(static fn($row) => (int)$row['remaining_cakes'], $trackerRows));
$totalFoodPacksRemaining = array_sum(array_map(static fn($row) => (int)$row['remaining_food_packs'], $trackerRows));
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
                <div>
                    <span class="food-tracking-eyebrow">Food Tracking</span>
                    <h1>Cakes and Food Packs</h1>
                </div>
                <div class="food-tracking-actions">
                    <button type="button" class="food-add-button" data-bs-toggle="modal" data-bs-target="#addTrackerModal">
                        <i class="fas fa-plus"></i>
                        <span>Add Tracker</span>
                    </button>
                    <form class="food-tracking-search" method="get" role="search">
                        <select name="status" class="food-filter-select" aria-label="Filter food tracking records" onchange="this.form.submit()">
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                        <input type="search" name="search" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search IB or project" aria-label="Search food tracking records">
                        <button type="submit" aria-label="Search">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($formErrors): ?>
                <div class="food-alert is-error" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['added'])): ?>
                <div class="food-alert is-success" role="status"><i class="fas fa-check"></i> Food tracker added successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="food-alert is-success" role="status"><i class="fas fa-check"></i> Food tracker updated successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['deducted'])): ?>
                <div class="food-alert is-success" role="status"><i class="fas fa-check"></i> Deduction saved successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="food-alert is-success" role="status"><i class="fas fa-check"></i> Food tracker deleted successfully.</div>
            <?php endif; ?>

            <div class="food-summary-strip">
                <div><span><?php echo number_format(count($trackerRows)); ?></span><strong>Trackers</strong></div>
                <div><span><?php echo number_format($totalCakesRemaining); ?></span><strong>Cakes Left</strong></div>
                <div><span><?php echo number_format($totalFoodPacksRemaining); ?></span><strong>Food Packs Left</strong></div>
            </div>

            <div class="food-tracking-table" role="table" aria-label="Food tracking table">
                <div class="food-tracking-row food-tracking-row-head" role="row">
                    <div>IB No.</div>
                    <div>Project</div>
                    <div>Cakes Balance</div>
                    <div>Food Packs Balance</div>
                    <div>Last Deduction Date</div>
                    <div>Status</div>
                    <div>Actions</div>
                </div>
                <?php if ($trackerRows): ?>
                    <?php foreach ($trackerRows as $row): ?>
                        <?php
                        $trackerId = (int)$row['id'];
                        $history = $historyByTracker[$trackerId] ?? [];
                        $statusLabel = $row['is_completed'] ? 'Completed' : 'Active';
                        $lastDate = $row['last_deduction_date'] ? date('M d, Y', strtotime((string)$row['last_deduction_date'])) : '-';
                        ?>
                        <div class="food-tracking-row" role="row">
                            <div class="food-ref"><?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="food-project"><?php echo htmlspecialchars($row['project_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="food-balance <?php echo (int)$row['remaining_cakes'] === 0 ? 'is-zero' : ''; ?>">
                                <strong><?php echo number_format((int)$row['remaining_cakes']); ?></strong>
                                <span>of <?php echo number_format((int)$row['initial_cakes']); ?></span>
                            </div>
                            <div class="food-balance <?php echo (int)$row['remaining_food_packs'] === 0 ? 'is-zero' : ''; ?>">
                                <strong><?php echo number_format((int)$row['remaining_food_packs']); ?></strong>
                                <span>of <?php echo number_format((int)$row['initial_food_packs']); ?></span>
                            </div>
                            <div class="food-date"><?php echo htmlspecialchars($lastDate, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>
                                <span class="food-status-badge <?php echo $row['is_completed'] ? 'is-completed' : 'is-active'; ?>">
                                    <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div class="food-row-actions">
                                <button type="button" class="food-icon-button food-deduct-row" title="Add Deduction" aria-label="Add deduction for <?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-bs-toggle="modal" data-bs-target="#deductionModal" data-tracker-id="<?php echo $trackerId; ?>" data-reference="<?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-project="<?php echo htmlspecialchars($row['project_name'], ENT_QUOTES, 'UTF-8'); ?>" data-remaining-cakes="<?php echo (int)$row['remaining_cakes']; ?>" data-remaining-food-packs="<?php echo (int)$row['remaining_food_packs']; ?>">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button type="button" class="food-icon-button food-history-row" title="View History" aria-label="View history for <?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-bs-toggle="modal" data-bs-target="#historyModal" data-reference="<?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-project="<?php echo htmlspecialchars($row['project_name'], ENT_QUOTES, 'UTF-8'); ?>" data-history="<?php echo htmlspecialchars(json_encode($history), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button type="button" class="food-icon-button food-edit-row" title="Edit Tracker" aria-label="Edit tracker for <?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-bs-toggle="modal" data-bs-target="#editTrackerModal" data-tracker-id="<?php echo $trackerId; ?>" data-reference="<?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-project="<?php echo htmlspecialchars($row['project_name'], ENT_QUOTES, 'UTF-8'); ?>" data-initial-cakes="<?php echo (int)$row['initial_cakes']; ?>" data-initial-food-packs="<?php echo (int)$row['initial_food_packs']; ?>" data-used-cakes="<?php echo (int)$row['used_cakes']; ?>" data-used-food-packs="<?php echo (int)$row['used_food_packs']; ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="food-icon-button is-danger food-delete-row" title="Delete Tracker" aria-label="Delete tracker for <?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-bs-toggle="modal" data-bs-target="#deleteTrackerModal" data-tracker-id="<?php echo $trackerId; ?>" data-reference="<?php echo htmlspecialchars($row['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" data-project="<?php echo htmlspecialchars($row['project_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="food-tracking-empty">
                        <?php echo $searchTerm !== '' ? 'No matching food tracking records found.' : 'No food trackers yet.'; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div class="modal fade" id="addTrackerModal" tabindex="-1" aria-labelledby="addTrackerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered food-dialog">
            <form class="modal-content food-modal" method="post" action="food_tracking.php">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="addTrackerModalLabel">Add Food Tracker</h5>
                        <div class="food-modal-subtitle">Select a food-related IB and enter starting balances.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo payables_csrf_input(); ?>
                    <input type="hidden" name="action" value="add_tracker">
                    <div class="food-form-grid">
                        <label class="is-wide">
                            <span>IB No.</span>
                            <select name="bac_monitoring_id" required>
                                <option value="">Select IB number</option>
                                <?php foreach ($availableIbRows as $ibRow): ?>
                                    <option value="<?php echo (int)$ibRow['id']; ?>">
                                        <?php echo htmlspecialchars(($ibRow['ib_no'] ?? '') . ' - ' . ($ibRow['project_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Initial Cakes</span>
                            <input type="number" name="initial_cakes" min="0" step="1" value="0" required>
                        </label>
                        <label>
                            <span>Initial Food Packs</span>
                            <input type="number" name="initial_food_packs" min="0" step="1" value="0" required>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success food-save-button">Save Tracker</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deductionModal" tabindex="-1" aria-labelledby="deductionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered food-dialog">
            <form class="modal-content food-modal" method="post" action="food_tracking.php">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="deductionModalLabel">Add Deduction</h5>
                        <div class="food-modal-subtitle" id="deductionSubtitle">Record dated usage.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo payables_csrf_input(); ?>
                    <input type="hidden" name="action" value="add_deduction">
                    <input type="hidden" name="tracker_id" id="deductionTrackerId">
                    <div class="food-form-grid">
                        <label class="is-wide">
                            <span>Date</span>
                            <input type="date" name="deduction_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </label>
                        <label>
                            <span>Cakes Deducted</span>
                            <input type="number" name="cakes_deducted" id="deductionCakes" min="0" step="1" value="0" required>
                        </label>
                        <label>
                            <span>Food Packs Deducted</span>
                            <input type="number" name="food_packs_deducted" id="deductionFoodPacks" min="0" step="1" value="0" required>
                        </label>
                        <label class="is-wide">
                            <span>Remarks</span>
                            <input type="text" name="remarks" placeholder="Optional remarks">
                        </label>
                    </div>
                    <div class="food-balance-note" id="deductionBalanceNote"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success food-save-button">Save Deduction</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editTrackerModal" tabindex="-1" aria-labelledby="editTrackerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered food-dialog">
            <form class="modal-content food-modal" method="post" action="food_tracking.php">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="editTrackerModalLabel">Edit Tracker</h5>
                        <div class="food-modal-subtitle" id="editSubtitle">Update starting balances.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo payables_csrf_input(); ?>
                    <input type="hidden" name="action" value="edit_tracker">
                    <input type="hidden" name="tracker_id" id="editTrackerId">
                    <div class="food-form-grid">
                        <label>
                            <span>Initial Cakes</span>
                            <input type="number" name="initial_cakes" id="editInitialCakes" min="0" step="1" required>
                        </label>
                        <label>
                            <span>Initial Food Packs</span>
                            <input type="number" name="initial_food_packs" id="editInitialFoodPacks" min="0" step="1" required>
                        </label>
                    </div>
                    <div class="food-balance-note" id="editBalanceNote"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success food-save-button">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered food-history-dialog">
            <div class="modal-content food-modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="historyModalLabel">Deduction History</h5>
                        <div class="food-modal-subtitle" id="historySubtitle">Dated usage history.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="food-history-list" id="historyList"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteTrackerModal" tabindex="-1" aria-labelledby="deleteTrackerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered food-dialog">
            <form class="modal-content food-modal" method="post" action="food_tracking.php">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="deleteTrackerModalLabel">Delete Food Tracker</h5>
                        <div class="food-modal-subtitle" id="deleteSubtitle">This will also remove deduction history.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo payables_csrf_input(); ?>
                    <input type="hidden" name="action" value="delete_tracker">
                    <input type="hidden" name="tracker_id" id="deleteTrackerId">
                    <div class="food-delete-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Delete this tracker and all of its dated deductions?</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Tracker</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll(".food-deduct-row").forEach(function (button) {
            button.addEventListener("click", function () {
                document.getElementById("deductionTrackerId").value = button.dataset.trackerId || "";
                document.getElementById("deductionSubtitle").textContent = (button.dataset.reference || "IB") + " - " + (button.dataset.project || "");
                document.getElementById("deductionCakes").max = button.dataset.remainingCakes || "0";
                document.getElementById("deductionFoodPacks").max = button.dataset.remainingFoodPacks || "0";
                document.getElementById("deductionBalanceNote").textContent = "Remaining: " + (button.dataset.remainingCakes || "0") + " cakes, " + (button.dataset.remainingFoodPacks || "0") + " food packs.";
            });
        });

        document.querySelectorAll(".food-edit-row").forEach(function (button) {
            button.addEventListener("click", function () {
                document.getElementById("editTrackerId").value = button.dataset.trackerId || "";
                document.getElementById("editSubtitle").textContent = (button.dataset.reference || "IB") + " - " + (button.dataset.project || "");
                document.getElementById("editInitialCakes").value = button.dataset.initialCakes || "0";
                document.getElementById("editInitialFoodPacks").value = button.dataset.initialFoodPacks || "0";
                document.getElementById("editInitialCakes").min = button.dataset.usedCakes || "0";
                document.getElementById("editInitialFoodPacks").min = button.dataset.usedFoodPacks || "0";
                document.getElementById("editBalanceNote").textContent = "Already deducted: " + (button.dataset.usedCakes || "0") + " cakes, " + (button.dataset.usedFoodPacks || "0") + " food packs.";
            });
        });

        document.querySelectorAll(".food-history-row").forEach(function (button) {
            button.addEventListener("click", function () {
                document.getElementById("historySubtitle").textContent = (button.dataset.reference || "IB") + " - " + (button.dataset.project || "");
                const list = document.getElementById("historyList");
                let entries = [];
                try {
                    entries = JSON.parse(button.dataset.history || "[]");
                } catch (error) {
                    entries = [];
                }

                if (!entries.length) {
                    list.innerHTML = '<div class="food-history-empty">No deductions recorded yet.</div>';
                    return;
                }

                list.innerHTML = entries.map(function (entry) {
                    const remarks = entry.remarks ? '<span>' + escapeHtml(entry.remarks) + '</span>' : '<span>No remarks</span>';
                    return '<div class="food-history-item">' +
                        '<strong>' + escapeHtml(entry.deduction_date || '-') + '</strong>' +
                        '<span>Cakes: ' + escapeHtml(entry.cakes_deducted || '0') + '</span>' +
                        '<span>Food packs: ' + escapeHtml(entry.food_packs_deducted || '0') + '</span>' +
                        remarks +
                    '</div>';
                }).join("");
            });
        });

        document.querySelectorAll(".food-delete-row").forEach(function (button) {
            button.addEventListener("click", function () {
                document.getElementById("deleteTrackerId").value = button.dataset.trackerId || "";
                document.getElementById("deleteSubtitle").textContent = (button.dataset.reference || "IB") + " - " + (button.dataset.project || "");
            });
        });

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (character) {
                return {"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"}[character];
            });
        }
    </script>
</body>
</html>
