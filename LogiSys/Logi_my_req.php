<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: Logi_login.php");
    exit();
}

// Include database connection
require_once 'logi_db.php';

// Get user information from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];
$user_office = null;

// Get filter parameters
$date_filter = isset($_GET['date']) ? htmlspecialchars(trim($_GET['date'])) : date('Y-m-d');
$status_filter = isset($_GET['status']) ? htmlspecialchars(trim($_GET['status'])) : 'all';

// Build the query based on filters for detailed requests
$where_conditions = ["office_id = ?"];
$params = [$user_id];
$param_types = "i";

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

$where_clause = implode(" AND ", $where_conditions);

// Get user's requests
$requests_query = "SELECT 
                    id,
                    office_name,
                    item_id,
                    item_name,
                    quantity,
                    approved_quantity,
                    date_requested,
                    remarks,
                    remarks_admin,
                    status
                FROM items_requested
                WHERE $where_clause
                ORDER BY date_requested DESC, id DESC";

$requests_stmt = mysqli_prepare($conn, $requests_query);

if ($requests_stmt) {
    mysqli_stmt_bind_param($requests_stmt, $param_types, ...$params);
    mysqli_stmt_execute($requests_stmt);
    $requests_result = mysqli_stmt_get_result($requests_stmt);

    $user_requests = [];
    while ($row = mysqli_fetch_assoc($requests_result)) {
        $user_requests[] = $row;
    }
    mysqli_stmt_close($requests_stmt);
}

// Updated summary statistics query - filtered by date
$stats_where_conditions = ["office_id = ?"];
$stats_params = [$user_id];
$stats_param_types = "i";

// Add date filter to stats if date is selected
if ($date_filter) {
    $stats_where_conditions[] = "DATE(date_requested) = ?";
    $stats_params[] = $date_filter;
    $stats_param_types .= "s";
}

$stats_where_clause = implode(" AND ", $stats_where_conditions);

$stats_query = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
                FROM items_requested 
                WHERE $stats_where_clause";

$stats_stmt = mysqli_prepare($conn, $stats_query);
if ($stats_stmt) {
    mysqli_stmt_bind_param($stats_stmt, $stats_param_types, ...$stats_params);
    mysqli_stmt_execute($stats_stmt);
    $stats_result = mysqli_stmt_get_result($stats_stmt);
    $stats = mysqli_fetch_assoc($stats_result);
    mysqli_stmt_close($stats_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiSys - My Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="Logi_req.css" rel="stylesheet">
    <style>
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .scrollable-table-container {
            max-height: 60vh;
            overflow-y: auto;
        }

        .filter-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .card-title small {
            font-weight: normal;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <a class="navbar-brand" href="Logi_req.php">
            <img src="./tagbi_seal.png" alt="Logo" class="logo-img">
            <img src="../logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">LogiSys - Supply Officer Request System</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="Logi_req.php">
                        <i class="fas fa-plus-circle"></i> New Request
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                        <div class="dropdown-header">
                            <strong><?= htmlspecialchars($user_role) ?></strong><br>
                            <?php if ($user_office): ?>
                                <small class="text-muted"><?= htmlspecialchars($user_office['office_name']) ?></small><br>
                                <small class="text-muted"><?= htmlspecialchars($user_office['department']) ?></small>
                            <?php else: ?>
                                <small class="text-muted">Requestor</small>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <li><a class="dropdown-item" href="Logi_my_req.php"><i class="fas fa-list"></i> My Requests</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Statistics Cards -->
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

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" action="Logi_my_req.php">
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
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-dark btn-sm me-2">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="Logi_my_req.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-refresh"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <!-- Requests Table -->
        <div class="card border-light shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 text-dark">
                    <i class="fas fa-list text-secondary"></i> My Requests
                    <?php if ($date_filter): ?>
                        <small class="text-muted">- <?= date('F j, Y', strtotime($date_filter)) ?></small>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body bg-white">
                <div class="scrollable-table-container">
                    <table class="table table-hover">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-dark">Date Requested</th>
                                <th class="text-dark">Item Name</th>
                                <th class="text-dark">Quantity</th>
                                <th class="text-dark">Approved Qty</th>
                                <th class="text-dark">Additional Items</th>
                                <th class="text-dark">Remarks</th>
                                <th class="text-dark">Status</th>
                                <th class="text-dark">Actions</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($user_requests)): ?>
                                <?php foreach ($user_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('M j, Y', strtotime($request['date_requested'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($request['item_name']) ?></strong><br>
                                            <small class="text-muted">ID: <?= htmlspecialchars($request['item_id']) ?></small>
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
                                        <td>
                                            <?php if (!empty($request['remarks']) && $request['remarks'] !== ''): ?>
                                                <strong class="text-muted">
                                                    <?= htmlspecialchars($request['remarks']) ?>
                                                </strong>
                                            <?php endif; ?>
                                        <td>
                                            <?php if (!empty($request['remarks_admin'])): ?>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($request['remarks_admin']) ?>
                                                </small>
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
                                                <?= htmlspecialchars($request['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($request['status'] === 'Pending'): ?>
                                                <button class="btn btn-primary btn-sm me-1"
                                                    onclick="openEditModal(<?= $request['id'] ?>, '<?= addslashes(htmlspecialchars($request['item_name'])) ?>', <?= $request['quantity'] ?>, '<?= addslashes(htmlspecialchars($request['remarks'] ?? '')) ?>')"
                                                    title="Edit Request">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm"
                                                    onclick="confirmDelete(<?= $request['id'] ?>, '<?= addslashes(htmlspecialchars($request['item_name'])) ?>')"
                                                    title="Delete Request">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted">No actions available</small>
                                            <?php endif; ?>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <div class="py-4">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <p>No requests found for the selected filters</p>
                                            <a href="Logi_req.php" class="btn btn-dark btn-sm">
                                                <i class="fas fa-plus"></i> Create New Request
                                            </a>
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

    <!-- Add this toast container for notifications -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i id="toast-icon" class="fas fa-info-circle me-2"></i>
                <strong id="toast-title" class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div id="toast-body" class="toast-body">
                <!-- Message will be inserted here -->
            </div>
        </div>
    </div>
    <!-- Edit Request Modal -->
    <div class="modal fade" id="editRequestModal" tabindex="-1" aria-labelledby="editRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRequestModalLabel">
                        <i class="fas fa-edit text-primary"></i> Edit Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editRequestForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><strong>Item:</strong></label>
                            <p id="editItemName" class="text-muted mb-2">Loading...</p>
                        </div>
                        <div class="mb-3">
                            <label for="editQuantity" class="form-label"><strong>Quantity:</strong></label>
                            <input type="number" class="form-control" id="editQuantity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="editRemarks" class="form-label"><strong>Remarks (Optional):</strong></label>
                            <textarea class="form-control" id="editRemarks" rows="3" placeholder="Add any additional notes..."></textarea>
                        </div>
                        <input type="hidden" id="editRequestId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteRequestModal" tabindex="-1" aria-labelledby="deleteRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteRequestModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this request?</p>
                    <div class="alert alert-warning">
                        <strong>Item:</strong> <span id="deleteItemName">Loading...</span><br>
                        <small>This action cannot be undone.</small>
                    </div>
                    <input type="hidden" id="deleteRequestId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="executeDelete()">
                        <i class="fas fa-trash"></i> Delete Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-submit form when date or status changes
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date');
            const statusSelect = document.getElementById('status');

            dateInput.addEventListener('change', function() {
                this.form.submit();
            });

            statusSelect.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Bootstrap Toast function
        function showBootstrapToast(message, type = 'success', duration = 3000) {
            const toastElement = document.getElementById('liveToast');
            const toastIcon = document.getElementById('toast-icon');
            const toastTitle = document.getElementById('toast-title');
            const toastBody = document.getElementById('toast-body');

            // Configure toast based on type
            const config = {
                success: {
                    icon: 'fas fa-check-circle text-success',
                    title: 'Success',
                    class: 'text-success'
                },
                error: {
                    icon: 'fas fa-exclamation-circle text-danger',
                    title: 'Error',
                    class: 'text-danger'
                },
                warning: {
                    icon: 'fas fa-exclamation-triangle text-warning',
                    title: 'Warning',
                    class: 'text-warning'
                },
                info: {
                    icon: 'fas fa-info-circle text-info',
                    title: 'Info',
                    class: 'text-info'
                }
            };

            const currentConfig = config[type] || config.success;

            toastIcon.className = currentConfig.icon;
            toastTitle.textContent = currentConfig.title;
            toastTitle.className = `me-auto ${currentConfig.class}`;
            toastBody.textContent = message;

            // Create toast with custom duration
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: duration
            });

            toast.show();
        }

        // Function to open edit modal
        function openEditModal(requestId, itemName, quantity, remarks) {
            console.log('Opening edit modal for request:', requestId); // Debug log

            if (!requestId || requestId <= 0) {
                alert('Invalid request ID');
                return;
            }

            // Set form values
            document.getElementById('editRequestId').value = requestId;
            document.getElementById('editItemName').textContent = itemName;
            document.getElementById('editQuantity').value = quantity || 1;
            document.getElementById('editRemarks').value = remarks || '';

            // Show modal
            const modalElement = document.getElementById('editRequestModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Edit modal element not found');
                alert('Error: Modal not found. Please refresh the page.');
            }
        }

        // Function to open delete confirmation modal
        function confirmDelete(requestId, itemName) {
            console.log('Opening delete modal for request:', requestId); // Debug log

            if (!requestId || requestId <= 0) {
                alert('Invalid request ID');
                return;
            }

            // Set values
            document.getElementById('deleteRequestId').value = requestId;
            document.getElementById('deleteItemName').textContent = itemName;

            // Show modal
            const modalElement = document.getElementById('deleteRequestModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Delete modal element not found');
                alert('Error: Modal not found. Please refresh the page.');
            }
        }

        // Function to handle edit form submission
        document.addEventListener('DOMContentLoaded', function() {
            const editForm = document.getElementById('editRequestForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const requestId = document.getElementById('editRequestId').value;
                    const quantity = document.getElementById('editQuantity').value;
                    const remarks = document.getElementById('editRemarks').value;

                    if (!quantity || quantity < 1) {
                        alert('Please enter a valid quantity');
                        return;
                    }

                    // Disable submit button
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

                    fetch('update_my_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                request_id: requestId,
                                quantity: parseInt(quantity),
                                remarks: remarks
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                showBootstrapToast('Request updated successfully', 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            } else {
                                throw new Error(data.message || 'Unknown error occurred');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showBootstrapToast('Error updating request: ' + error.message, 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        });
                });
            }
        });

        // Function to execute delete
        function executeDelete() {
            const requestId = document.getElementById('deleteRequestId').value;

            if (!requestId) {
                alert('No request selected for deletion');
                return;
            }

            // Disable delete button
            const deleteBtn = document.querySelector('#deleteRequestModal .btn-danger');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

            fetch('delete_my_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        request_id: parseInt(requestId)
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showBootstrapToast('Request deleted successfully', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        throw new Error(data.message || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showBootstrapToast('Error deleting request: ' + error.message, 'error');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                });
        }

        // Clear modals when hidden
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = document.getElementById('editRequestModal');
            const deleteModal = document.getElementById('deleteRequestModal');

            if (editModal) {
                editModal.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('editRequestForm').reset();
                    document.getElementById('editRequestId').value = '';
                    document.getElementById('editItemName').textContent = 'Loading...';
                });
            }

            if (deleteModal) {
                deleteModal.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('deleteRequestId').value = '';
                    document.getElementById('deleteItemName').textContent = 'Loading...';
                });
            }
        });
    </script>
</body>

</html>