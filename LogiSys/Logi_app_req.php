<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: Logi_login.php");
    exit();
}

// Include database connection
require_once 'logi_db.php';
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];
// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$office_filter = isset($_GET['office']) ? $_GET['office'] : 'all';

// Get requests per office
$office_stats_query = "SELECT 
                        office_name,
                        COUNT(*) as total_requests,
                        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
                    FROM items_requested 
                    GROUP BY office_name, office_id
                    ORDER BY pending_requests DESC, total_requests DESC";

$office_stats_result = mysqli_query($conn, $office_stats_query);
$office_stats = [];
while ($row = mysqli_fetch_assoc($office_stats_result)) {
    $office_stats[] = $row;
}

// Get all offices for filter dropdown
$offices_query = "SELECT DISTINCT office_name FROM items_requested ORDER BY office_name";
$offices_result = mysqli_query($conn, $offices_query);
$offices = [];
while ($row = mysqli_fetch_assoc($offices_result)) {
    $offices[] = $row['office_name'];
}

// Build the query for detailed requests based on filters
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($date_filter) {
    $where_conditions[] = "DATE(date_requested) = ?";
    $params[] = $date_filter;
    $param_types .= "s";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($office_filter !== 'all') {
    $where_conditions[] = "office_name = ?";
    $params[] = $office_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get detailed requests
$requests_query = "SELECT 
                    id,
                    office_name,
                    item_id,
                    item_name,
                    quantity,
                    approved_quantity,
                    date_requested,
                    remarks,
                    status
                FROM items_requested
                WHERE $where_clause
                ORDER BY date_requested DESC, id DESC
                LIMIT 50";

if (!empty($params)) {
    $requests_stmt = mysqli_prepare($conn, $requests_query);
    mysqli_stmt_bind_param($requests_stmt, $param_types, ...$params);
    mysqli_stmt_execute($requests_stmt);
    $requests_result = mysqli_stmt_get_result($requests_stmt);
} else {
    $requests_result = mysqli_query($conn, $requests_query);
}

$detailed_requests = [];
while ($row = mysqli_fetch_assoc($requests_result)) {
    $detailed_requests[] = $row;
}

if (isset($requests_stmt)) {
    mysqli_stmt_close($requests_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiSys - Approve Requests</title>
    <link rel="stylesheet" href="Logi_Sys.css">
    <link rel="stylesheet" href="Logi_app_req.css">
    <link rel="stylesheet" href="Logi_req.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <a class="navbar-brand" href="Logi_Sys_Dashboard.php">
            <img src="tagbi_seal.png" alt="Logo" class="logo-img">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">LogiSys - Admin System</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Main Navigation Menu -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="./Logi_Sys_Dashboard.php">
                        <i class="fas fa-home icon-size"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="Logi_inventory.php">
                        <i class="fas fa-box icon-size"></i> Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="Logi_app_req.php">
                        <i class="fas fa-users icon-size"></i> Approve Request
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="Logi_transactions.php">
                        <i class="fas fa-exchange-alt icon-size"></i> Transactions
                    </a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="create_report.php">
                        <i class="fas fa-chart-line icon-size"></i> Report
                    </a>
                </li> -->
            </ul>

            <!-- User Profile Dropdown (Right Side) -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                        <div class="dropdown-header">
                            <strong><?= htmlspecialchars($user_role) ?></strong><br>
                        </div>

                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="main_content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="fas fa-clipboard-check"></i> Approve Requests</h2>

            <div class="row">
                <!-- Requests per Office -->
                <div class="col-md-4">
                    <div class="card border-light shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-history text-secondary"></i> Latest Past Office Requests
                                <?php if ($date_filter || $status_filter !== 'all' || $office_filter !== 'all'): ?>
                                    <small class="text-muted">
                                        - Before
                                        <?php if ($date_filter): ?>
                                            <?= date('M j, Y', strtotime($date_filter)) ?>
                                        <?php else: ?>
                                            Today
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body bg-white p-0">
                            <div class="scrollable-container" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="text-dark" style="font-size: 0.8rem;">Office</th>
                                            <th class="text-dark text-center" style="font-size: 0.8rem;">Last Item</th>
                                            <th class="text-dark text-center" style="font-size: 0.8rem;">Qty</th>
                                            <th class="text-dark text-center" style="font-size: 0.8rem;">Date</th>
                                            <th class="text-dark text-center" style="font-size: 0.8rem;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Build the query for latest request per office BEFORE the selected date
                                        $recent_where_conditions = ["1=1"];
                                        $recent_params = [];
                                        $recent_param_types = "";

                                        // Apply date filter - show requests BEFORE the selected date
                                        if ($date_filter) {
                                            $recent_where_conditions[] = "DATE(date_requested) < ?";
                                            $recent_params[] = $date_filter;
                                            $recent_param_types .= "s";
                                        } else {
                                            // Show requests before today if no date filter
                                            $recent_where_conditions[] = "DATE(date_requested) < CURDATE()";
                                        }

                                        // Apply status filter
                                        if ($status_filter !== 'all') {
                                            $recent_where_conditions[] = "status = ?";
                                            $recent_params[] = $status_filter;
                                            $recent_param_types .= "s";
                                        }

                                        // Apply office filter
                                        if ($office_filter !== 'all') {
                                            $recent_where_conditions[] = "office_name = ?";
                                            $recent_params[] = $office_filter;
                                            $recent_param_types .= "s";
                                        }

                                        $recent_where_clause = implode(" AND ", $recent_where_conditions);

                                        // Get latest request per office BEFORE the selected date
                                        $recent_items_query = "SELECT 
                        office_name,
                        item_name,
                        quantity,
                        remarks,
                        approved_quantity,
                        DATE(date_requested) as request_date,
                        status
                    FROM (
                        SELECT 
                            office_name,
                            item_name,
                            quantity,
                            approved_quantity,
                            date_requested,
                            remarks,
                            status,
                            DENSE_RANK() OVER (PARTITION BY office_name ORDER BY date_requested DESC) as date_rank
                        FROM items_requested 
                        WHERE $recent_where_clause
                    ) ranked
                    WHERE date_rank = 1
                    ORDER BY office_name, date_requested DESC, item_name
                    LIMIT 100";


                                        // Execute query with or without parameters
                                        if (!empty($recent_params)) {
                                            $recent_items_stmt = mysqli_prepare($conn, $recent_items_query);
                                            if ($recent_items_stmt) {
                                                mysqli_stmt_bind_param($recent_items_stmt, $recent_param_types, ...$recent_params);
                                                mysqli_stmt_execute($recent_items_stmt);
                                                $recent_items_result = mysqli_stmt_get_result($recent_items_stmt);
                                            } else {
                                                $recent_items_result = false;
                                            }
                                        } else {
                                            $recent_items_result = mysqli_query($conn, $recent_items_query);
                                        }

                                        $recent_items = [];
                                        if ($recent_items_result) {
                                            while ($row = mysqli_fetch_assoc($recent_items_result)) {
                                                $recent_items[] = $row;
                                            }
                                        }

                                        // Close prepared statement if used
                                        if (isset($recent_items_stmt)) {
                                            mysqli_stmt_close($recent_items_stmt);
                                        }
                                        ?>

                                        <?php if (!empty($recent_items)): ?>
                                            <?php foreach ($recent_items as $item): ?>
                                                <tr class="border-bottom">
                                                    <td style="padding: 0.5rem 0.75rem;">
                                                        <strong style="font-size: 0.85rem; color: #333;">
                                                            <?= htmlspecialchars($item['office_name'] ?? '') ?>
                                                        </strong>
                                                    </td>
                                                    <td style="padding: 0.5rem 0.75rem;">
                                                        <small style="font-size: 0.8rem;">
                                                            <?= htmlspecialchars($item['item_name'] ?? '') ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                        <div>
                                                            <span class="badge bg-info" style="font-size: 0.7rem;">
                                                                <?= $item['quantity'] ?>
                                                            </span>
                                                            <?php if ($item['approved_quantity'] > 0): ?>
                                                                <br>
                                                                <span class="badge bg-success mt-1" style="font-size: 0.65rem;">
                                                                    ✓ <?= $item['approved_quantity'] ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                        <small class="text-muted" style="font-size: 0.75rem;">
                                                            <?= date('M j', strtotime($item['request_date'])) ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                        <?php
                                                        $status_class = '';
                                                        $status_icon = '';
                                                        switch ($item['status']) {
                                                            case 'Pending':
                                                                $status_class = 'bg-warning';
                                                                $status_icon = 'fas fa-clock';
                                                                break;
                                                            case 'Approved':
                                                                $status_class = 'bg-success';
                                                                $status_icon = 'fas fa-check';
                                                                break;
                                                            case 'Rejected':
                                                                $status_class = 'bg-danger';
                                                                $status_icon = 'fas fa-times';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-secondary';
                                                                $status_icon = 'fas fa-question';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $status_class ?>" style="font-size: 0.65rem;" title="<?= htmlspecialchars($item['status'] ?? '') ?>">
                                                            <i class="<?= $status_icon ?>"></i>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 0.5rem 0.75rem;">
                                                        <small style="font-size: 0.75rem; color: #666;">
                                                            <?php if (!empty($item['remarks'])): ?>
                                                                <?= htmlspecialchars($item['remarks'] ?? '') ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                                    <p style="font-size: 0.9rem;">No past office requests found</p>
                                                    <?php if ($date_filter || $status_filter !== 'all' || $office_filter !== 'all'): ?>
                                                        <small class="text-muted">
                                                            No requests found before
                                                            <?php if ($date_filter): ?>
                                                                <?= date('M j, Y', strtotime($date_filter)) ?>
                                                            <?php else: ?>
                                                                today
                                                            <?php endif; ?>
                                                            <br>
                                                            <a href="Logi_app_req.php" class="text-decoration-none">reset filters</a>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light text-center py-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Latest past requests from <?= count($recent_items) ?> offices
                                <?php if ($date_filter): ?>
                                    before <?= date('M j, Y', strtotime($date_filter)) ?>
                                <?php else: ?>
                                    before today
                                <?php endif; ?>

                                <?php if ($date_filter || $status_filter !== 'all' || $office_filter !== 'all'): ?>
                                    <br>
                                    <a href="Logi_app_req.php" class="text-decoration-none small">
                                        <i class="fas fa-times"></i> Clear filters
                                    </a>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <!-- Detailed Requests -->
                <div class="col-md-8">
                    <!-- Filters -->
                    <div class="filter-card">
                        <form method="GET" action="Logi_app_req.php">
                            <div class="row align-items-end g-2">
                                <div class="col-md-3">
                                    <label for="date" class="form-label small"><i class="fas fa-calendar"></i> Date</label>
                                    <input type="date" class="form-control form-control-sm" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label small"><i class="fas fa-filter"></i> Status</label>
                                    <select class="form-select form-select-sm" id="status" name="status">
                                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="office" class="form-label small"><i class="fas fa-building"></i> Office</label>
                                    <select class="form-select form-select-sm" id="office" name="office">
                                        <option value="all" <?= $office_filter === 'all' ? 'selected' : '' ?>>All Offices</option>
                                        <?php foreach ($offices as $office): ?>
                                            <option value="<?= htmlspecialchars($office) ?>" <?= $office_filter === $office ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($office) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-dark btn-sm me-2">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <!-- <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location.href='Logi_app_req.php'">
                                        <i class="fas fa-refresh"></i>
                                    </button> -->
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkApproveModal">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#bulkRejectModal">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Requests Table -->
                    <div class="card border-light shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-list text-secondary"></i> Requests to Review
                                <?php if ($date_filter): ?>
                                    <small class="text-muted">- <?= date('F j, Y', strtotime($date_filter)) ?></small>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body bg-white">
                            <div class="scrollable-container">
                                <table class="table table-hover table-sm">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="text-dark"><input type="checkbox" id="selectAllCheckbox"></th>
                                            <th class="text-dark">Date</th>
                                            <th class="text-dark">Office</th>
                                            <th class="text-dark">Item</th>
                                            <th class="text-dark">Qty</th>
                                            <th class="text-dark">Appv. QTY</th>
                                            <th class="text-dark">Remarks</th>
                                            <th class="text-dark">Status</th>
                                            <th class="text-dark">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($detailed_requests)): ?>
                                            <?php foreach ($detailed_requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="request-checkbox" value="<?= $request['id'] ?>" <?= $request['status'] !== 'Pending' ? 'disabled' : '' ?>>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y', strtotime($request['date_requested'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small><?= htmlspecialchars($request['office_name'] ?? '') ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($request['item_name'] ?? '') ?></strong><br>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= $request['quantity'] ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($request['approved_quantity'] > 0): ?>
                                                            <span class="badge bg-success"><?= $request['approved_quantity'] ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding: 0.5rem 0.75rem;">
                                                        <?php if (!empty($request['remarks'])): ?>
                                                            <?php
                                                            // Split remarks by line breaks first, then by common delimiters
                                                            $remarks_text = str_replace(['\n', '\r\n'], "\n", $request['remarks']);
                                                            $remarks_items = preg_split('/\n|;|\|/', $remarks_text);

                                                            // Check if preg_split failed and handle error
                                                            if ($remarks_items === false) {
                                                                $remarks_items = array($request['remarks']); // fallback to original text
                                                            } else {
                                                                $remarks_items = array_filter($remarks_items, function ($item) {
                                                                    return !empty(trim($item));
                                                                });
                                                            }

                                                            // If only one item and no delimiters found, try to split by pattern matching
                                                            if (count($remarks_items) == 1) {
                                                                $text = trim($remarks_items[0]);
                                                                // Use a simpler approach: split after "pcs " or "pc " followed by lowercase letter
                                                                $text = preg_replace('/(\d+\s*pcs?)\s+([a-z])/i', '$1|$2', $text);
                                                                $split_items = explode('|', $text);

                                                                if (count($split_items) > 1) {
                                                                    $remarks_items = array_filter($split_items, function ($item) {
                                                                        return !empty(trim($item));
                                                                    });
                                                                }
                                                            }
                                                            ?>

                                                            <?php if (!empty($remarks_items)): ?>
                                                                <ul style="margin: 0; padding-left: 1.2rem; font-size: 0.85rem;">
                                                                    <?php foreach ($remarks_items as $item): ?>
                                                                        <li style="margin-bottom: 0.2rem;">
                                                                            <strong><?= htmlspecialchars(trim($item)) ?></strong>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        switch ($request['status']) {
                                                            case 'Pending':
                                                                $status_class = 'bg-warning';
                                                                break;
                                                            case 'Approved':
                                                                $status_class = 'bg-success';
                                                                break;
                                                            case 'Rejected':
                                                                $status_class = 'bg-danger';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge status-badge <?= $status_class ?>">
                                                            <?= htmlspecialchars($request['status'] ?? '') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($request['status'] === 'Pending'): ?>
                                                            <button class="btn btn-success btn-sm me-1" onclick="openApproveModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['item_name'] ?? '') ?>', <?= $request['quantity'] ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <!-- Replace the existing reject button with this -->
                                                            <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['item_name'] ?? '') ?>')">
                                                                <i class="fas fa-times"></i>
                                                            </button>

                                                        <?php else: ?>
                                                            <small class="text-muted">-</small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">
                                                    <div class="py-4">
                                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                                        <p>No requests found for the selected filters</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">
                        <i class="fas fa-check-circle text-success"></i> Approve Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Item:</strong></label>
                        <p id="modalItemName" class="text-muted mb-2">Loading...</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Requested Quantity:</strong></label>
                        <p id="modalRequestedQty" class="text-info mb-2">Loading...</p>
                    </div>
                    <div class="mb-3">
                        <label for="approvedQuantity" class="form-label"><strong>Approved Quantity:</strong></label>
                        <input type="number" class="form-control" id="approvedQuantity" min="0" required>
                        <div class="form-text">Enter the quantity to approve (can be less than or equal to requested quantity)</div>
                    </div>
                    <div class="mb-3">
                        <label for="adminRemarks" class="form-label"><strong>Admin Remarks (Optional):</strong></label>
                        <textarea class="form-control" id="adminRemarks" rows="3" placeholder="Add any remarks or notes..."></textarea>
                    </div>
                    <input type="hidden" id="modalRequestId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmApproval()">
                        <i class="fas fa-check"></i> Approve Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">Add New Items</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="itemSearchInput" class="form-control" placeholder="Search items...">
                    </div>

                    <div class="table-responsive scrollable-table">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="itemTable">
                                <?php
                                try {
                                    require_once 'logi_db.php';

                                    // Prepare and execute query
                                    $query = "SELECT item_no, item_name, unit FROM inventory_items ORDER BY item_name ASC";
                                    $result = mysqli_query($conn, $query);

                                    if (!$result) {
                                        throw new Exception("Database query failed: " . mysqli_error($conn));
                                    }

                                    // Check if there are any results
                                    if (mysqli_num_rows($result) > 0) {
                                        // Loop through results and display rows
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $itemNo = htmlspecialchars($row['item_no'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $itemName = htmlspecialchars($row['item_name'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $unit = htmlspecialchars($row['unit'] ?? '', ENT_QUOTES, 'UTF-8');

                                            echo "<tr>";
                                            echo "    <td>{$itemName}</td>";
                                            echo "    <td>{$unit}</td>";
                                            echo "    <td><input type='number' class='form-control item-quantity' data-item-no='{$itemNo}' data-item-name='{$itemName}' data-unit='{$unit}' min='1' placeholder='Enter quantity'></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        // No items found
                                        echo "<tr>";
                                        echo "    <td colspan='3' class='text-center text-muted'>";
                                        echo "        <i class='fas fa-info-circle'></i> No items found";
                                        echo "    </td>";
                                        echo "</tr>";
                                    }

                                    // Free result set
                                    mysqli_free_result($result);
                                } catch (Exception $e) {
                                    // Handle errors gracefully
                                    echo "<tr>";
                                    echo "    <td colspan='3' class='text-center text-danger'>";
                                    echo "        <i class='fas fa-exclamation-triangle'></i> ";
                                    echo "        Error loading items: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                                    echo "    </td>";
                                    echo "</tr>";

                                    // Log error for debugging (optional)
                                    error_log("Database error in common_items table: " . $e->getMessage());
                                } finally {
                                    // Close connection if it exists
                                    if (isset($conn) && $conn) {
                                        mysqli_close($conn);
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="addAllItemsWithQuantity()">Add All Items</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="fas fa-times-circle text-danger"></i> Reject Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Item:</strong></label>
                        <p id="rejectModalItemName" class="text-muted mb-2">Loading...</p>
                    </div>
                    <div class="mb-3">
                        <label for="rejectRemarks" class="form-label"><strong>Reason for Rejection (Required):</strong></label>
                        <textarea class="form-control" id="rejectRemarks" rows="4" placeholder="Please provide a reason for rejecting this request..." required></textarea>
                        <div class="form-text">This information will be sent to the requestor.</div>
                    </div>
                    <input type="hidden" id="rejectModalRequestId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmRejection()">
                        <i class="fas fa-times"></i> Reject Request
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="allItemsModal" tabindex="-1" aria-labelledby="allItemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form id="itemSelectionForm"> <!-- Added form element -->
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="allItemsModalLabel">
                            <i class="fas fa-list-check"></i> Process All Selected Items
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle"></i>
                            <strong>Instructions:</strong> Review and configure each item below. All fields marked with <span class="text-danger">*</span> are required.
                        </div>

                        <!-- Common fields for all items -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-building"></i> Office/Department for All Items <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="commonOfficeSelect" required>
                                    <option value="">Select Office/Department</option>
                                </select>
                                <div class="form-text">This office will be applied to all items.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i> Date Received for All Items <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="commonDateReceived" required>
                                <div class="form-text">This date will be applied to all items.</div>
                            </div>
                        </div>

                        <!-- Items Container - This will be populated dynamically -->
                        <div id="allItemsContainer">
                            <!-- Items will be dynamically inserted here by JavaScript -->
                        </div>

                        <!-- Progress indicator -->
                        <div id="processingProgress" class="mt-3" style="display: none;">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <span>Processing items...</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="me-auto">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i>
                                Items: <span id="itemCount">0</span>
                            </small>
                        </div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="confirmItemSelection()">
                            <i class="fas fa-check-double"></i> Process All Items
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="Logi_app_req.js"></script>
</body>

</html>
