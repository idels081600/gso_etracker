<?php
session_start();


// Include database connection
require_once 'logi_db.php';
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$office_filter = isset($_GET['office']) ? $_GET['office'] : 'all';

// Get overall statistics
$stats_query = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
                FROM items_requested";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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
$office_query = "SELECT DISTINCT office_name FROM items_requested ORDER BY office_name ASC";
$office_result = $conn->query($office_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiSys - Admin Dashboard</title>
    <link rel="stylesheet" href="Logi_Sys.css">
    <link rel="stylesheet" href="Logi_req.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        .office-card {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .office-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .scrollable-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .filter-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }

        .main_content {
            display: flex;
            justify-content: center;
            /* Center horizontally */
            align-items: center;
            /* Center vertically */
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <a class="navbar-brand" href="Logi_req.php">
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
                <li class="nav-item">
                    <a class="nav-link" href="create_report.php">
                        <i class="fas fa-chart-line icon-size"></i> Report
                    </a>
                </li>
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
            <h2 class="mb-4"><i class="fas fa-tachometer-alt"></i> Dashboard</h2>

            <!-- Overall Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card bg-white border">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0 text-primary">Total Requests</h5>
                                    <h2 class="mb-0 text-dark"><?= $stats['total_requests'] ?? 0 ?></h2>
                                </div>
                                <i class="fas fa-clipboard-list stats-icon text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-white border">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0 text-warning">Pending</h5>
                                    <h2 class="mb-0 text-dark"><?= $stats['pending_requests'] ?? 0 ?></h2>
                                </div>
                                <i class="fas fa-clock stats-icon text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-white border">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0 text-success">Approved</h5>
                                    <h2 class="mb-0 text-dark"><?= $stats['approved_requests'] ?? 0 ?></h2>
                                </div>
                                <i class="fas fa-check-circle stats-icon text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-white border">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0 text-danger">Rejected</h5>
                                    <h2 class="mb-0 text-dark"><?= $stats['rejected_requests'] ?? 0 ?></h2>
                                </div>
                                <i class="fas fa-times-circle stats-icon text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Detailed Requests -->
                <div class="col-12">
                    <!-- Filters -->
                    <div class="filter-card">
                        <form method="GET" action="Logi_Sys_Dashboard.php">
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
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-dark btn-sm">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="Logi_Sys_Dashboard.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#printModal">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Requests Table -->
                    <div class="card border-light shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-list text-secondary"></i> Recent Requests
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
                                            <th class="text-dark">Date</th>
                                            <th class="text-dark">Office</th>
                                            <th class="text-dark">Item</th>
                                            <th class="text-dark">Qty</th>
                                            <th class="text-dark">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($detailed_requests)): ?>
                                            <?php foreach ($detailed_requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y', strtotime($request['date_requested'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small><?= htmlspecialchars($request['office_name']) ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($request['item_name']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($request['item_id']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= $request['quantity'] ?></span>
                                                        <?php if ($request['approved_quantity'] > 0): ?>
                                                            <br><span class="badge bg-success mt-1"><?= $request['approved_quantity'] ?></span>
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
                                                            <?= htmlspecialchars($request['status']) ?>
                                                        </span>
                                                    </td>

                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">
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
    </div><!-- Print Modal -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printModalLabel"><i class="fas fa-print"></i> Print Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p>Select how you want to print the filtered records.</p>

                    <!-- Date Selection -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Date:</label>
                        <input type="date" class="form-control" id="printDate" name="print_date" value="<?= date('Y-m-d') ?>">
                        <div class="form-text">Select the date for which you want to print records.</div>
                    </div>

                    <?php if ($office_result->num_rows > 0): ?>
                        <form id="printOfficesForm">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Office(s):</label>

                                <!-- Select All toggle -->
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="selectAllOffices">
                                    <label class="form-check-label" for="selectAllOffices">Select All</label>
                                </div>

                                <?php while ($row = $office_result->fetch_assoc()):
                                    $office = htmlspecialchars($row['office_name']);
                                    $id = 'office_' . md5($office);
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input office-checkbox" type="checkbox" name="offices[]" value="<?= $office ?>" id="<?= $id ?>">
                                        <label class="form-check-label" for="<?= $id ?>"><?= $office ?></label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">No office names found.</p>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mt-3">
                        <button type="button" class="btn btn-outline-dark" onclick="handlePrint('summary')">Print Request</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printModalLabel"><i class="fas fa-print"></i> Print Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p>Select how you want to print the filtered records.</p>

                    <!-- Date Selection -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Date:</label>
                        <input type="date" class="form-control" id="printDate" name="print_date" value="<?= date('Y-m-d') ?>">
                        <div class="form-text">Select the date for which you want to print records.</div>
                    </div>

                    <?php if ($office_result->num_rows > 0): ?>
                        <form id="printOfficesForm">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Office(s):</label>

                                <!-- Select All toggle -->
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="selectAllOffices">
                                    <label class="form-check-label" for="selectAllOffices">Select All</label>
                                </div>

                                <?php while ($row = $office_result->fetch_assoc()):
                                    $office = htmlspecialchars($row['office_name']);
                                    $id = 'office_' . md5($office);
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input office-checkbox" type="checkbox" name="offices[]" value="<?= $office ?>" id="<?= $id ?>">
                                        <label class="form-check-label" for="<?= $id ?>"><?= $office ?></label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">No office names found.</p>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mt-3">
                        <button type="button" class="btn btn-outline-dark" onclick="handlePrint('summary')">Print Request</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Logi_Sys_Dashboard.js"></script>

</body>

</html>