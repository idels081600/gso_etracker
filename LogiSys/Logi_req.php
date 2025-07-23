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

// Get all active items from common_items
$assigned_items = [];
$items_query = "SELECT 
                    item_no,
                    item_name,
                    quantity,
                    unit,
                    status
                FROM common_items
                WHERE status = 'Active'
                ORDER BY item_name ASC";

$items_stmt = mysqli_prepare($conn, $items_query);

if ($items_stmt) {
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
            grid-template-columns: 1fr 1fr;
            /* Two columns */
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

        /* Make the items table scrollable */
        .scrollable-table-container {
            max-height: 70vh;
            overflow-y: auto;
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
                        <li><a class="dropdown-item" href="Logi_my_req.php"><i class="fas fa-user"></i> My Request</a></li>
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
        <div class="row mt-4">
            <!-- First Container - Items Table -->
            <div class="col-md-8">
                <div class="card border-light shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-dark"><i class="fas fa-boxes text-secondary"></i> Available Items</h5>
                    </div>
                    <div class="card-body bg-white">
                        <!-- Search bar for items table -->
                        <div class="mb-3">
                            <input type="text" id="itemSearchInput" class="form-control" placeholder="Search by item name or number...">
                        </div>
                        <div class="scrollable-table-container">
                            <table class="table table-hover" id="itemsTableMain">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-dark">Item Name</th>
                                        <th class="text-dark">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTable">
                                    <?php if (!empty($assigned_items)): ?>
                                        <?php foreach ($assigned_items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>

                                                <td>
                                                    <?php if ($item['quantity'] > 0): ?>
                                                        <button class="btn btn-sm btn-outline-dark"
                                                            type="button"
                                                            onclick="openAddToRequestModal('<?= htmlspecialchars($item['item_no']) ?>', '<?= htmlspecialchars($item['item_name']) ?>', '<?= htmlspecialchars($item['unit']) ?>')">
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
                                                    <p>No items available</p>
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
    <!-- Add this toast container to your HTML body -->
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const userId = <?php echo json_encode($_SESSION['user_id']); ?>;
        const username = <?php echo json_encode($_SESSION['username']); ?>;
        const officeId = <?php echo json_encode($_SESSION['user_id']); ?>; // Using user_id as office_id
        const officeName = <?php echo json_encode($_SESSION['username'] . "'s Office"); ?>; // Or set a 
    </script>
    <script src="Logi_req.js"></script>
    <script>
        // Client-side search for items table
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('itemSearchInput');
            const table = document.getElementById('itemsTable');
            searchInput.addEventListener('input', function() {
                const filter = searchInput.value.toLowerCase();
                const rows = table.getElementsByTagName('tr');
                for (let i = 0; i < rows.length; i++) {
                    const cells = rows[i].getElementsByTagName('td');
                    if (cells.length > 0) {
                        const itemName = cells[0].textContent.toLowerCase();
                        if (itemName.indexOf(filter) > -1) {
                            rows[i].style.display = '';
                        } else {
                            rows[i].style.display = 'none';
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>