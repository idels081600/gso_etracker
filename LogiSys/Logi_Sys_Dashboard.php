<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: Logi_login.php");
    exit();
}

// Include database connection
require_once 'logi_db.php';

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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiSys - Admin Dashboard</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="Logi_Sys.css">
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
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="./Logi_Sys_Dashboard.php" class="active"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li><a href="Logi_inventory.php"><i class="fas fa-box icon-size"></i> Inventory</a></li>
            <li><a href="Logi_app_req.php"><i class="fas fa-users icon-size"></i> Approve Request</a></li>
            <li><a href="Logi_manage_office.php"><i class="fas fa-truck icon-size"></i> Request</a></li>
            <li><a href="Logi_transactions.php"><i class="fas fa-exchange-alt icon-size"></i> Transactions</a></li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

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
                <!-- Requests per Office -->
                <div class="col-md-4">
                    <div class="card border-light shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-building text-secondary"></i> Requests per Office
                            </h5>
                        </div>
                        <div class="card-body bg-white">
                            <div class="scrollable-container">
                                <?php if (!empty($office_stats)): ?>
                                    <?php foreach ($office_stats as $office): ?>
                                        <div class="office-card p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0 text-dark"><?= htmlspecialchars($office['office_name']) ?></h6>
                                                <span class="badge bg-primary"><?= $office['total_requests'] ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <small class="text-warning">
                                                    <i class="fas fa-clock"></i> Pending: <?= $office['pending_requests'] ?>
                                                </small>
                                                <small class="text-success">
                                                    <i class="fas fa-check"></i> Approved: <?= $office['approved_requests'] ?>
                                                </small>
                                                <small class="text-danger">
                                                    <i class="fas fa-times"></i> Rejected: <?= $office['rejected_requests'] ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No office requests found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Requests -->
                <div class="col-md-8">
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
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-dark btn-sm me-2">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="Logi_Sys_Dashboard.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
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
                                            <th class="text-dark">Actions</th>
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
                                                    <td>
                                                        <?php if ($request['status'] === 'Pending'): ?>
                                                            <button class="btn btn-success btn-sm me-1" onclick="updateRequestStatus(<?= $request['id'] ?>, 'Approved')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-danger btn-sm" onclick="updateRequestStatus(<?= $request['id'] ?>, 'Rejected')">
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
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date');
            const statusSelect = document.getElementById('status');
            const officeSelect = document.getElementById('office');

            dateInput.addEventListener('change', function() {
                this.form.submit();
            });

            statusSelect.addEventListener('change', function() {
                this.form.submit();
            });

            officeSelect.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Function to update request status
        function updateRequestStatus(requestId, status) {
            if (confirm(`Are you sure you want to ${status.toLowerCase()} this request?`)) {
                fetch('update_request_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            request_id: requestId,
                            status: status
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating request status');
                    });
            }
        }
    </script>
</body>

</html>