<?php
require_once 'logi_display_data.php'; // Include your database functions
$transactions_data = display_transactions(); // Fetch transactions from the database
$logi_all_data = display_inventory_items(); // Fetch inventory items from the database
function getTransactionTypeBadge($type)
{
    switch (strtolower($type)) {
        case 'stock in':
            return ['class' => 'bg-success', 'icon' => 'fas fa-plus'];
        case 'stock out':
            return ['class' => 'bg-danger', 'icon' => 'fas fa-minus'];
        case 'adjustment':
            return ['class' => 'bg-warning', 'icon' => 'fas fa-edit'];
        case 'transfer':
            return ['class' => 'bg-info', 'icon' => 'fas fa-exchange-alt'];
        default:
            return ['class' => 'bg-secondary', 'icon' => 'fas fa-question'];
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logi_Sys_Dashboard</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="Logi_transactions.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <style>
        .container { max-width: 75vw; }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Lou March Cordovan</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="./Logi_Sys_Dashboard.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li><a href="Logi_inventory.php"><i class="fas fa-box icon-size"></i> Inventory</a></li>
            <li><a href="Logi_mang.php"><i class="fas fa-users icon-size"></i> Office Balances</a></li>
            <li><a href="Logi_manage_office.php"><i class="fas fa-truck icon-size"></i> Request</a></li>
            <li><a href="Logi_transactions.php"><i class="fas fa-exchange-alt icon-size"></i> Transactions</a></li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="main_content">
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="container" id="secondContainer">
                        <h4>Transaction History</h4>

                        <!-- Filter and Search Section -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <!-- Filter Buttons -->
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm active" id="allTransactions">
                                            <i class="fas fa-list"></i> All
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" id="inTransactions" data-bs-toggle="modal" data-bs-target="#stockInModal">
                                            <i class="fas fa-plus"></i> Stock In
                                        </button>

                                        <button type="button" class="btn btn-outline-danger btn-sm" id="outTransactions" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                                            <i class="fas fa-minus"></i> Stock Out
                                        </button>

                                        <button type="button" class="btn btn-info btn-sm" id="exportBtn">
                                            <i class="fas fa-download"></i> Export
                                        </button>
                                    </div>

                                    <!-- Search Bar -->
                                    <div class="input-group" style="width: 300px;">
                                        <input type="text" class="form-control" id="transactionSearchInput" placeholder="Search transactions...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date Range Filter -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="dateFrom" class="form-label">From Date</label>
                                <input type="date" class="form-control form-control-sm" id="dateFrom">
                            </div>
                            <div class="col-md-3">
                                <label for="dateTo" class="form-label">To Date</label>
                                <input type="date" class="form-control form-control-sm" id="dateTo">
                            </div>
                            <div class="col-md-3">
                                <label for="transactionType" class="form-label">Transaction Type</label>
                                <select class="form-select form-select-sm" id="transactionType">
                                    <option value="">All Types</option>
                                    <option value="Addition">Addition</option>
                                    <option value="Deduction">Deduction</option>
                                </select>
                            </div>
                         
                        </div>

                        <!-- Transactions Table -->
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm small">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Item Name</th>
                                            <th>Quantity</th>
                                            <th>Previous Balance</th>
                                            <th>New Balance</th>
                                            <th>Reason</th>
                                            <th>Requestor</th>
                                            <th>Transaction Type</th>
                                            <th>Undo</th>
                                        </tr>
                                    </thead>

                                    <tbody id="transactionsTableBody">
                                        <?php
                                        // Check if there are any results
                                        if (mysqli_num_rows($transactions_data) > 0) {
                                            // Loop through each row
                                            while ($row = mysqli_fetch_assoc($transactions_data)) {
                                                $transactionBadge = getTransactionTypeBadge($row['transaction_type']);
                                        ?>
                                                <tr>
                                                    <td><?= date('Y-m-d H:i:s', strtotime($row['created_at'] ?? 'now')) ?></td>
                                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                                    <td>
                                                        <?php
                                                        $quantity = (int)$row['quantity'];
                                                        $previousBalance = (int)$row['previous_balance'];
                                                        $newBalance = (int)$row['new_balance'];

                                                        if ($newBalance > $previousBalance) {
                                                            echo '+' . abs($quantity);
                                                        } else {
                                                            echo '-' . abs($quantity);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['previous_balance']) ?></td>
                                                    <td><?= htmlspecialchars($row['new_balance']) ?></td>
                                                    <td><?= htmlspecialchars($row['reason']) ?></td>
                                                    <td><?= htmlspecialchars($row['requestor']) ?></td>
                                                    <td>
                                                        <?php
                                                        $previousBalance = (int)$row['previous_balance'];
                                                        $newBalance = (int)$row['new_balance'];

                                                        if ($newBalance > $previousBalance) {
                                                            $badgeClass = 'bg-success';
                                                            $icon = 'fas fa-plus';
                                                        } else {
                                                            $badgeClass = 'bg-danger';
                                                            $icon = 'fas fa-minus';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?>">
                                                            <i class="<?= $icon ?>"></i> <?= htmlspecialchars($row['transaction_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-warning btn-sm undo-transaction-btn" 
                                                                data-transaction-id="<?= htmlspecialchars($row['id']) ?>">
                                                            <i class="fas fa-undo"></i> Undo
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php
                                            }
                                        } else {
                                            // No data found
                                            ?>
                                            <tr id="noTransactionsRow">
                                                <td colspan="8" class="text-center">
                                                    <div class="py-4">
                                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                        <h5 class="text-muted">No transactions found</h5>
                                                        <p class="text-muted">No transactions have been recorded yet.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>

                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Stock In Modal -->
    <div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockInModalLabel">
                        <i class="fas fa-plus text-success"></i> Stock In Transaction
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="stockInForm" >
                    <div class="modal-body">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="stockInItemSearch" class="form-label">Select Item <span class="text-danger">*</span></label>

                                    <!-- Search Input with Dropdown -->
                                    <div class="position-relative">
                                        <input type="text"
                                            class="form-control"
                                            id="stockInItemSearch"
                                            name="item_search"
                                            placeholder="Type to search for an item..."
                                            autocomplete="off"
                                            required>

                                        <!-- Suggestions Dropdown -->
                                        <div class="position-absolute top-100 start-0 w-100 bg-white border rounded-bottom shadow-sm"
                                            id="stockInItemDropdown"
                                            style="display: none; max-height: 250px; overflow-y: auto; z-index: 1050;">
                                            <!-- Suggestions will be populated here -->
                                        </div>
                                    </div>

                                    <!-- Hidden inputs to store selected values -->
                                    <input type="hidden" id="selectedItemNameInput" name="item_name">
                                    <input type="hidden" id="selectedItemNo" name="item_no">
                                    <input type="hidden" id="selectedCurrentBalance" name="current_balance">

                                    <div class="form-text">Type to search and select an item from suggestions</div>

                                    <!-- Selected item indicator -->
                                    <div id="selectedItemIndicator" class="mt-2" style="display: none;">
                                        <div class="alert alert-success py-2 mb-0">
                                            <i class="fas fa-check-circle text-success"></i>
                                            <strong>Selected:</strong> <span id="displaySelectedItem"></span>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-2 float-end" id="clearItemSelection">
                                                <i class="fas fa-times"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                </div>


                                <div class="mb-3">
                                    <label for="stockInQuantity" class="form-label">Quantity to Add <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stockInQuantity" name="quantity" min="1" required>
                                    <div class="form-text">Enter the number of items to add</div>
                                </div>

                                <div class="mb-3">
                                    <label for="stockInReason" class="form-label">Reason <span class="text-danger">*</span></label>
                                    <select class="form-select" id="stockInReason" name="reason" required>
                                        <option value="">Select reason...</option>
                                        <option value="New Purchase">New Purchase</option>
                                        <option value="Supplier Delivery">Supplier Delivery</option>
                                        <option value="Return from Department">Return from Department</option>
                                        <option value="Stock Correction">Stock Correction</option>
                                        <option value="Transfer In">Transfer In</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="customReasonDiv" style="display: none;">
                                    <label for="customReason" class="form-label">Custom Reason</label>
                                    <input type="text" class="form-control" id="customReason" name="custom_reason" placeholder="Enter custom reason">
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Transaction Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong>Selected Item:</strong>
                                            <span id="selectedItemNameSummary" class="text-muted">None selected</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Current Balance:</strong>
                                            <span id="currentBalanceSummary" class="badge bg-info">0</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Quantity to Add:</strong>
                                            <span id="addQuantity" class="badge bg-success">0</span>
                                        </div>
                                        <hr>
                                        <div class="mb-2">
                                            <strong>New Balance:</strong>
                                            <span id="newBalance" class="badge bg-primary">0</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label for="stockInNotes" class="form-label">Additional Notes</label>
                                    <textarea class="form-control" id="stockInNotes" name="notes" rows="3" placeholder="Any additional information..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields for form processing -->
                        <input type="hidden" id="previousBalance" name="previous_balance">
                        <input type="hidden" id="calculatedNewBalance" name="new_balance">
                        <input type="hidden" name="transaction_type" value="Stock In">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success" id="submitStockIn">
                            <i class="fas fa-plus"></i> Add Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Stock Out Modal -->
    <div class="modal fade" id="stockOutModal" tabindex="-1" aria-labelledby="stockOutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockOutModalLabel">
                        <i class="fas fa-minus text-danger"></i> Stock Out Transaction
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="stockOutForm">
                    <div class="modal-body">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="stockOutItemSearch" class="form-label">Select Item <span class="text-danger">*</span></label>

                                    <!-- Search Input with Dropdown -->
                                    <div class="position-relative">
                                        <input type="text"
                                            class="form-control"
                                            id="stockOutItemSearch"
                                            name="item_search"
                                            placeholder="Type to search for an item..."
                                            autocomplete="off"
                                            required>

                                        <!-- Suggestions Dropdown -->
                                        <div class="position-absolute top-100 start-0 w-100 bg-white border rounded-bottom shadow-sm"
                                            id="stockOutItemDropdown"
                                            style="display: none; max-height: 250px; overflow-y: auto; z-index: 1050;">
                                            <!-- Suggestions will be populated here -->
                                        </div>
                                    </div>

                                    <!-- Hidden inputs to store selected values -->
                                    <input type="hidden" id="stockOutSelectedItemName" name="item_name" required>
                                    <input type="hidden" id="stockOutSelectedItemNo" name="item_no">
                                    <input type="hidden" id="stockOutSelectedCurrentBalance" name="current_balance">

                                    <div class="form-text">Type to search and select an item</div>

                                    <!-- Selected item indicator -->
                                    <div id="stockOutSelectedItemIndicator" class="mt-2" style="display: none;">
                                        <div class="alert alert-success py-2 mb-0">
                                            <i class="fas fa-check-circle text-success"></i>
                                            <strong>Selected:</strong> <span id="stockOutDisplaySelectedItem"></span>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-2 float-end" id="stockOutClearItemSelection">
                                                <i class="fas fa-times"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="stockOutQuantity" class="form-label">Quantity to Remove <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stockOutQuantity" name="quantity" min="1" required>
                                    <div class="form-text">Enter the number of items to remove</div>
                                    <div id="stockOutQuantityError" class="text-danger" style="display: none;">
                                        <small><i class="fas fa-exclamation-triangle"></i> Insufficient stock available</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="stockOutReason" class="form-label">Reason <span class="text-danger">*</span></label>
                                    <select class="form-select" id="stockOutReason" name="reason" required>
                                        <option value="">Select reason...</option>
                                        <option value="Department Request">Department Request</option>
                                        <option value="Office Supply">Office Supply</option>
                                        <option value="Damaged/Expired">Damaged/Expired</option>
                                        <option value="Transfer Out">Transfer Out</option>
                                        <option value="Lost/Missing">Lost/Missing</option>
                                        <option value="Stock Correction">Stock Correction</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="stockOutCustomReasonDiv" style="display: none;">
                                    <label for="stockOutCustomReason" class="form-label">Custom Reason</label>
                                    <input type="text" class="form-control" id="stockOutCustomReason" name="custom_reason" placeholder="Enter custom reason">
                                </div>

                                <div class="mb-3">
                                    <label for="stockOutRequestor" class="form-label">Requestor/Department</label>
                                    <input type="text" class="form-control" id="stockOutRequestor" name="requestor" placeholder="Who is requesting this item?">
                                    <div class="form-text">Optional: Name or department requesting the items</div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Transaction Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong>Selected Item:</strong>
                                            <span id="stockOutSelectedItemNameSummary" class="text-muted">None selected</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Current Balance:</strong>
                                            <span id="stockOutCurrentBalanceSummary" class="badge bg-info">0</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Quantity to Remove:</strong>
                                            <span id="stockOutRemoveQuantity" class="badge bg-danger">0</span>
                                        </div>
                                        <hr>
                                        <div class="mb-2">
                                            <strong>New Balance:</strong>
                                            <span id="stockOutNewBalance" class="badge bg-primary">0</span>
                                        </div>
                                        <div class="mt-2" id="stockOutWarning" style="display: none;">
                                            <div class="alert alert-warning py-2 mb-0">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <small><strong>Warning:</strong> This will result in low stock or out of stock!</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label for="stockOutNotes" class="form-label">Additional Notes</label>
                                    <textarea class="form-control" id="stockOutNotes" name="notes" rows="3" placeholder="Any additional information..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields for form processing -->
                        <input type="hidden" id="stockOutPreviousBalance" name="previous_balance">
                        <input type="hidden" id="stockOutCalculatedNewBalance" name="new_balance">
                        <input type="hidden" name="transaction_type" value="Stock Out">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-danger" id="submitStockOut">
                            <i class="fas fa-minus"></i> Remove Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="Logi_transactions.js"></script>
    <script>
        // Pass inventory data to JavaScript
        window.inventoryItems = [
            <?php
            // Reset the inventory data pointer to reuse
            if (isset($logi_all_data)) {
                mysqli_data_seek($logi_all_data, 0);
                $items = [];
                while ($item = mysqli_fetch_assoc($logi_all_data)) {
                    $items[] = '{
                item_no: "' . addslashes($item['item_no']) . '",
                item_name: "' . addslashes($item['item_name']) . '",
                current_balance: "' . addslashes($item['current_balance']) . '",
                unit: "' . addslashes($item['unit']) . '"
            }';
                }
                echo implode(',', $items);
            }
            ?>
        ];

        function updateNewBalance() {
            const quantity = parseInt(document.getElementById('stockInQuantity').value) || 0;
            const currentBalance = parseInt(document.getElementById('selectedCurrentBalance').value) || 0;
            const newBalance = currentBalance + quantity;
            document.getElementById('calculatedNewBalance').value = newBalance;
            // Optionally update the summary display too
            const newBalanceDisplay = document.getElementById('newBalance');
            if (newBalanceDisplay) newBalanceDisplay.textContent = newBalance;
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.undo-transaction-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const transactionId = this.getAttribute('data-transaction-id');
                    if (confirm('Are you sure you want to undo this transaction?')) {
                        fetch('Logi_undo_transaction.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'transaction_id=' + encodeURIComponent(transactionId)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Transaction undone successfully!');
                                location.reload();
                            } else {
                                alert('Failed to undo transaction: ' + (data.message || 'Unknown error'));
                            }
                        })
                        .catch(err => {
                            alert('Error: ' + err.message);
                        });
                    }
                });
            });
        });
    </script>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle"></i> Success
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">
                        <i class="fas fa-exclamation-circle"></i> Error
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>