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

// Get assigned items for this user directly from office_balance_items
$assigned_items = [];
$items_query = "SELECT 
                    id,
                    item_no,
                    item_name,
                    quantity,
                    unit,
                    po_number,
                    assigned_date,
                    status,
                    notes
                FROM office_balance_items
                WHERE office_balance_id = ? AND status = 'Active'
                ORDER BY assigned_date DESC";

$items_stmt = mysqli_prepare($conn, $items_query);

if ($items_stmt) {
    mysqli_stmt_bind_param($items_stmt, "i", $user_id);
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);

    while ($row = mysqli_fetch_assoc($items_result)) {
        $assigned_items[] = $row;
    }
    mysqli_stmt_close($items_stmt);
}

// Get all available inventory items for the request form
$inventory_query = "SELECT item_no, item_name, current_balance, unit, status FROM inventory_items WHERE status != 'Discontinued' ORDER BY item_name";
$inventory_result = mysqli_query($conn, $inventory_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiSys - Inventory Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="Logi_req.css" rel="stylesheet">
    <style>
        .request-items-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns */
            gap: 12px;
        }

        .request-item {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px 12px;
            color: #212529;
            display: flex;
            align-items: center;
            min-height: 40px;
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
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mt-4">
            <!-- First Container - Items Table -->
            <div class="col-md-8">
                <div class="card border-light shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-dark"><i class="fas fa-boxes text-secondary"></i> Available Items</h5>
                    </div>
                    <div class="card-body bg-white">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-dark">Item No</th>
                                        <th class="text-dark">Item Name</th>
                                        <th class="text-dark">Current Balance</th>
                                        <th class="text-dark">Unit</th>
                                        <th class="text-dark">Status</th>
                                        <th class="text-dark">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTable">
                                    <?php if (!empty($assigned_items)): ?>
                                        <?php foreach ($assigned_items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_no']) ?></td>
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                                <td><?= htmlspecialchars($item['unit']) ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = 'text-success';
                                                    $status_text = 'Available';
                                                    if ($item['quantity'] <= 0) {
                                                        $status_class = 'text-danger';
                                                        $status_text = 'Out of Stock';
                                                    } elseif ($item['quantity'] <= 10) {
                                                        $status_class = 'text-warning';
                                                        $status_text = 'Low Stock';
                                                    }
                                                    ?>
                                                    <span class="badge badge-light border <?= $status_class ?>"><?= $status_text ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($item['quantity'] > 0): ?>
                                                        <button class="btn btn-sm btn-outline-dark"
                                                            type="button"
                                                            onclick="openAddToRequestModal('<?= htmlspecialchars($item['item_no']) ?>', '<?= htmlspecialchars($item['item_name']) ?>', <?= $item['quantity'] ?>, '<?= htmlspecialchars($item['unit']) ?>')">
                                                            <i class="fas fa-plus"></i> Add
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                                            <i class="fas fa-times"></i> Unavailable
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                <div class="py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                                    <p>No items assigned to your office</p>
                                                    <small>Contact the administrator to assign supplies to your office.</small>
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

            <!-- Second Container - My Request -->
            <div class="col-md-4">
                <div class="card border-light shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-dark"><i class="fas fa-shopping-cart text-secondary"></i> My Request</h5>
                    </div>
                    <div class="card-body bg-white">
                        <div id="requestItems" class="request-items-grid">
                            <!-- Example item structure -->
                            <div class="request-item">
                                <span>Item 1</span>
                            </div>
                            <div class="request-item">
                                <span>Item 2</span>
                            </div>
                            <!-- More items... -->
                        </div>

                        <div class="mt-3" id="requestActions" style="display: none;">
                            <hr class="border-light">
                            <div class="form-group">
                                <label for="requestReason" class="text-dark">Remarks <span class="text-muted">(Optional)</span>:</label>
                                <textarea class="form-control border-light" id="requestReason" rows="3" placeholder="Enter request (optional)..."></textarea>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-dark" onclick="submitRequest()">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                                <button class="btn btn-outline-secondary" onclick="clearRequest()">
                                    <i class="fas fa-trash"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add to Request Modal -->
    <div class="modal fade" id="addToRequestModal" tabindex="-1" role="dialog" aria-labelledby="addToRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addToRequestModalLabel">Add Item to Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addToRequestForm">
                        <div class="form-group">
                            <label>Item Name</label>
                            <input type="text" class="form-control" id="modalItemName" readonly>
                        </div>
                        <div class="form-group">
                            <label>Unit</label>
                            <input type="text" class="form-control" id="modalItemUnit" readonly>
                        </div>
                        <div class="form-group">
                            <label>Current Balance</label>
                            <input type="number" class="form-control" id="modalItemCurrentBalance" readonly>
                        </div>
                        <div class="form-group">
                            <label>Quantity to Request</label>
                            <input type="number" class="form-control" id="modalItemQty" min="1" required>
                        </div>
                        <input type="hidden" id="modalItemId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="confirmAddToRequest">Add to My Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Logi_req.js"></script>
</body>

</html>