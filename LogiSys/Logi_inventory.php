<?php
require_once 'logi_display_data.php'; // Include database connection file
$logi_all_data = display_inventory_items(); // Fetch inventory items from the database 
function getStatusBadge($status)
{
    switch ($status) {
        case 'Available':
            return 'bg-success';
        case 'Low Stock':
            return 'bg-warning';
        case 'Out of Stock':
            return 'bg-danger';
        case 'Discontinued':
            return 'bg-dark';
        default:
            return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory management</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="logi_inventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
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
                        <h4>Inventory</h4>
                        <!-- Action buttons row -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <!-- Action Buttons - on the left -->
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-success btn-sm" id="addItemBtn" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                            <i class="fas fa-plus"></i> Add Item
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" id="updateItemBtn" disabled data-bs-toggle="modal" data-bs-target="#updateItemModal">
                                            <i class="fas fa-edit"></i> Update Item
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" id="updateBalanceBtn" data-bs-toggle="modal" data-bs-target="#updateBalanceModal">
                                            <i class="fas fa-balance-scale"></i> Update Balance
                                        </button>
                                        <button type="button" class="btn btn-info btn-sm" id="printBtn">
                                            <i class="fas fa-print"></i> Print
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" id="deleteItemBtn" disabled>
                                            <i class="fas fa-trash"></i> Delete Item
                                        </button>
                                    </div>

                                    <!-- Search Bar - justified to the right -->
                                    <div class="input-group" style="width: 300px;">
                                        <input type="text" class="form-control" id="searchInput" placeholder="Search items...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory Table -->
                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Item No.</th>
                                            <th>Rack NO.</th>
                                            <th>Name</th>
                                            <th>Unit</th>
                                            <th>Balance</th>
                                            <th>Expiry Date</th>
                                            <th>Expiry Status</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Check if there are any results
                                        if (mysqli_num_rows($logi_all_data) > 0) {
                                            // Loop through each row
                                            while ($row = mysqli_fetch_assoc($logi_all_data)) {
                                                $badgeClass = getStatusBadge($row['status']);
                                        ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="form-check-input row-checkbox" value="<?= htmlspecialchars($row['item_no']) ?>">
                                                    </td>
                                                    <td><?= htmlspecialchars($row['item_no']) ?></td>
                                                    <td>#<?= htmlspecialchars($row['rack_no']) ?></td>
                                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['unit']) ?></td>
                                                    <td><?= htmlspecialchars($row['current_balance']) ?></td>
                                                    <td><?= htmlspecialchars($row['expiry_date']) ?></td>
                                                    <td>
                                                        <?php
                                                        $expiryStatus = $row['expiry_status'];
                                                        $expiryDate = $row['expiry_date'];
                                                        // More granular expiry status determination
                                                        if (empty($expiryDate) || $expiryDate === '0000-00-00') {
                                                            $expiryBadgeClass = "bg-secondary";
                                                            $displayExpiryStatus = "No Expiry";
                                                        } else {
                                                            $today = new DateTime();
                                                            $expiry = new DateTime($expiryDate);
                                                            $interval = $today->diff($expiry);
                                                            $daysDiff = $interval->days;
                                                            if ($expiry < $today) {
                                                                $expiryBadgeClass = "bg-danger";
                                                                $displayExpiryStatus = "Expired";
                                                            } else if ($daysDiff <= 7) {
                                                                $expiryBadgeClass = "bg-danger";
                                                                $displayExpiryStatus = "Expiring Soon";
                                                            } else if ($daysDiff <= 30) {
                                                                $expiryBadgeClass = "bg-warning";
                                                                $displayExpiryStatus = "Near Expiry";
                                                            } else if ($daysDiff <= 90) {
                                                                $expiryBadgeClass = "bg-info";
                                                                $displayExpiryStatus = "Good";
                                                            } else {
                                                                $expiryBadgeClass = "bg-success";
                                                                $displayExpiryStatus = "Valid";
                                                            }
                                                        }
                                                        ?>
                                                        <span class="badge <?= $expiryBadgeClass ?>"><?= htmlspecialchars($displayExpiryStatus) ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $dbStatus = $row['status'];
                                                        $balance = (int)$row['current_balance'];
                                                        // More granular status determination
                                                        $balance = (int)$row['current_balance'];
                                                        if ($balance == 0) {
                                                            $badgeClass = "bg-danger";
                                                            $displayStatus = "Out of Stock";
                                                        } else if ($balance <= 10) {
                                                            $badgeClass = "bg-warning";
                                                            $displayStatus = "Low Stock";
                                                        } else {
                                                            $badgeClass = "bg-success";
                                                            $displayStatus = "Available";
                                                        }
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($displayStatus) ?></span>
                                                    </td>
                                                </tr>
                                            <?php
                                            }
                                        } else {
                                            // No data found
                                            ?>
                                            <tr>
                                                <td colspan="9" class="text-center">
                                                    <div class="py-4">
                                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                        <h5 class="text-muted">No inventory items found</h5>
                                                        <p class="text-muted">Start by adding some items to your inventory.</p>
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


    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addItemForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="itemNo" class="form-label">Item No.</label>
                            <input type="text" class="form-control" id="itemNo" name="itemNo" placeholder="Auto-generated" required readonly>
                            <div class="form-text">Item number will be automatically generated</div>
                        </div>

                        <div class="mb-3">
                            <label for="itemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="itemName" name="itemName" placeholder="e.g., Highlighter Pen" required>
                        </div>

                        <div class="mb-3">
                            <label for="balance" class="form-label">Current Balance</label>
                            <input type="number" class="form-control" id="balance" name="balance" min="0" placeholder="0" required>
                            <div class="form-text">Current available quantity</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rackNo" class="form-label">Rack No.</label>
                                    <select class="form-select" id="rackNo" name="rackNo" required>
                                        <option value="">Select Rack</option>
                                        <option value="1">Rack 1</option>
                                        <option value="2">Rack 2</option>
                                        <option value="3">Rack 3</option>
                                        <option value="4">Rack 4</option>
                                        <option value="5">Rack 5</option>
                                        <option value="6">Rack 6</option>
                                        <option value="7">Rack 7</option>
                                        <option value="8">Rack 8</option>
                                        <option value="9">Rack 9</option>
                                        <option value="10">Rack 10</option>
                                        <option value="11">Rack 11</option>
                                        <option value="12">Rack 12</option>
                                        <option value="13">Rack 13</option>
                                        <option value="14">Rack 14</option>
                                        <option value="15">Rack 15</option>
                                        <option value="16">Rack 16</option>
                                        <option value="17">Rack 17</option>
                                        <option value="18">Rack 18</option>
                                        <option value="19">Rack 19</option>
                                        <option value="20">Rack 20</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="unit" class="form-label">Unit of Measurement</label>
                                    <select class="form-select" id="unit" name="unit" required>
                                        <option value="">Select Unit</option>
                                        <option value="pcs">Pieces</option>
                                        <option value="boxes">Boxes</option>
                                        <option value="reams">Reams</option>
                                        <option value="cartridges">Cartridges</option>
                                        <option value="sets">Sets</option>
                                        <option value="packs">Packs</option>
                                        <option value="rolls">Rolls</option>
                                        <option value="bottles">Bottles</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Expiry Date Field -->
                        <div class="mb-3">
                            <label for="expiryDate" class="form-label">
                                Expiry Date
                                <span class="text-muted">(Optional)</span>
                            </label>
                            <input type="date" class="form-control" id="expiryDate" name="expiryDate">
                            <div class="form-text">
                                <i class="fas fa-info-circle text-info"></i>
                                Leave blank if item doesn't expire or has no expiry date
                            </div>
                        </div>

                        <!-- Expiry Alert Threshold -->
                        <div class="mb-3" id="expiryAlertSection" style="display: none;">
                            <label for="expiryAlertDays" class="form-label">
                                Alert Before Expiry
                                <span class="text-muted">(Days)</span>
                            </label>
                            <select class="form-select" id="expiryAlertDays" name="expiryAlertDays">
                                <option value="30">30 days before</option>
                                <option value="60">60 days before</option>
                                <option value="90">90 days before</option>
                                <option value="180">6 months before</option>
                                <option value="365">1 year before</option>
                            </select>
                            <div class="form-text">
                                System will alert when item is approaching expiry
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Additional details about the item"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Update Item Modal -->
    <div class="modal fade" id="updateItemModal" tabindex="-1" aria-labelledby="updateItemModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateItemModalLabel">Update Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateItemForm">
                    <div class="modal-body">
                        <!-- Hidden field to store the original item ID -->
                        <input type="hidden" id="updateItemId" name="itemId">

                        <div class="mb-3">
                            <label for="updateItemNo" class="form-label">Item No.</label>
                            <input type="text" class="form-control" id="updateItemNo" name="itemNo">
                        </div>

                        <div class="mb-3">
                            <label for="updateItemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="updateItemName" name="itemName" required>
                        </div>

                        <div class="mb-3">
                            <label for="updateRackNo" class="form-label">Rack No.</label>
                            <select class="form-select" id="updateRackNo" name="rackNo" required>
                                <option value="">Select Rack</option>
                                <option value="1">Rack 1</option>
                                <option value="2">Rack 2</option>
                                <option value="3">Rack 3</option>
                                <option value="4">Rack 4</option>
                                <option value="5">Rack 5</option>
                                <option value="6">Rack 6</option>
                                <option value="7">Rack 7</option>
                                <option value="8">Rack 8</option>
                                <option value="9">Rack 9</option>
                                <option value="10">Rack 10</option>
                                <option value="11">Rack 11</option>
                                <option value="12">Rack 12</option>
                                <option value="13">Rack 13</option>
                                <option value="14">Rack 14</option>
                                <option value="15">Rack 15</option>
                                <option value="16">Rack 16</option>
                                <option value="17">Rack 17</option>
                                <option value="18">Rack 18</option>
                                <option value="19">Rack 19</option>
                                <option value="20">Rack 20</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="updateUnit" class="form-label">Unit of Measurement</label>
                            <select class="form-select" id="updateUnit" name="unit" required>
                                <option value="">Select Unit</option>
                                <option value="pcs">Pieces</option>
                                <option value="boxes">Boxes</option>
                                <option value="reams">Reams</option>
                                <option value="cartridges">Cartridges</option>
                                <option value="sets">Sets</option>
                                <option value="packs">Packs</option>
                                <option value="rolls">Rolls</option>
                                <option value="bottles">Bottles</option>
                                <option value="sacks">Sacks</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="updateBalance" class="form-label">Current Balance</label>
                            <input type="number" class="form-control" id="updateBalance" name="balance" min="0" required>
                            <div class="form-text">Current available quantity</div>
                        </div>

                        <div class="mb-3">
                            <label for="updateStatus" class="form-label">Status</label>
                            <select class="form-select" id="updateStatus" name="status" required>
                                <option value="Available">Available</option>
                                <option value="Low Stock">Low Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                                <option value="Discontinued">Discontinued</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="updateDescription" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="updateDescription" name="description" rows="2" placeholder="Additional details about the item"></textarea>
                        </div>

                        <!-- Display last updated info -->
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                <span id="lastUpdatedInfo">Last updated information will appear here</span>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Update Balance Modal -->
    <div class="modal fade" id="updateBalanceModal" tabindex="-1" aria-labelledby="updateBalanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateBalanceModalLabel">Update Item Balance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Left Side - Item Selection -->
                        <div class="col-md-6">
                            <div class="border-end pe-3">
                                <h6 class="mb-3">Select Item to Update</h6>

                                <!-- Search Bar -->
                                <div class="mb-3">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="balanceSearchInput" placeholder="Search items...">
                                        <button class="btn btn-outline-secondary" type="button" id="balanceSearchBtn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Items Table -->
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-hover table-sm" id="balanceItemsTable">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Item No.</th>
                                                <th>Name</th>
                                                <th>Current Balance</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="balanceItemsTableBody">
                                            <?php
                                            // Reset the result pointer to reuse the data
                                            mysqli_data_seek($logi_all_data, 0);

                                            if (mysqli_num_rows($logi_all_data) > 0) {
                                                while ($row = mysqli_fetch_assoc($logi_all_data)) {
                                                    $balance = (int)$row['current_balance'];

                                                    if ($balance == 0) {
                                                        $badgeClass = "bg-danger";
                                                        $displayStatus = "Out of Stock";
                                                    } else if ($balance <= 10) {
                                                        $badgeClass = "bg-warning";
                                                        $displayStatus = "Low Stock";
                                                    } else {
                                                        $badgeClass = "bg-success";
                                                        $displayStatus = "Available";
                                                    }
                                            ?>
                                                    <tr class="balance-item-row" data-item-id="<?= htmlspecialchars($row['item_no']) ?>"
                                                        data-item-name="<?= htmlspecialchars($row['item_name']) ?>"
                                                        data-current-balance="<?= htmlspecialchars($row['current_balance']) ?>">
                                                        <td><?= htmlspecialchars($row['item_no']) ?></td>
                                                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                                                        <td><?= htmlspecialchars($row['current_balance']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $badgeClass ?> badge-sm"><?= htmlspecialchars($displayStatus) ?></span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary ms-2 update-btn" data-item-id="<?= htmlspecialchars($row['item_no']) ?>">
                                                                Update
                                                            </button>
                                                        </td>
                                                    </tr>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Items to be Updated Table -->
                        <div class="col-md-6">
                            <div class="mt-3">
                                <h6 class="mb-2">Items to be Updated</h6>
                                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                                    <table class="table table-sm table-bordered" id="updateListTable">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th style="width: 15%;">Item No.</th>
                                                <th style="width: 25%;">Name</th>
                                                <th style="width: 15%;">Current</th>
                                                <th style="width: 15%;">Action</th>
                                                <th style="width: 15%;">Amount</th>
                                                <th style="width: 15%;">New Balance</th>
                                                <th style="width: 10%;">Remove</th>
                                            </tr>
                                        </thead>
                                        <tbody id="updateListTableBody">
                                            <tr id="emptyUpdateListRow">
                                                <td colspan="7" class="text-center text-muted py-3">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                    No items added for update yet
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary -->
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Total items to update: <span id="updateListCount">0</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editBalanceModal" tabindex="-1" aria-labelledby="editBalanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item Balance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editBalanceForm">
                        <input type="hidden" id="editItemId">
                        <div class="mb-3">
                            <label for="editItemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="editItemName" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="editNewBalance" class="form-label">New Balance</label>
                            <input type="number" class="form-control" id="editNewBalance" required min="0">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editBalanceForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Print Options Modal -->
    <div class="modal fade" id="printOptionsModal" tabindex="-1" aria-labelledby="printOptionsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printOptionsModalLabel">Print Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="printOptionsForm">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="printAvailable" name="printAvailable" checked>
                            <label class="form-check-label" for="printAvailable">Available</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="printLowStock" name="printLowStock" checked>
                            <label class="form-check-label" for="printLowStock">Low Stock</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="printOutOfStock" name="printOutOfStock" checked>
                            <label class="form-check-label" for="printOutOfStock">Out of Stock</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="printDiscontinued" name="printDiscontinued" checked>
                            <label class="form-check-label" for="printDiscontinued">Discontinued</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="printSelectedBtn">Print</button>
                </div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
   <script src="/LogiSys/Logi_Sys_inventory.js"></script>
</body>

</html>