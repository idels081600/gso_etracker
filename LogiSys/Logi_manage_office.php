<?php
require_once 'logi_db.php';

// Fetch all offices
$offices_query = "SELECT * FROM office_balances ORDER BY office_name";
$offices_result = mysqli_query($conn, $offices_query);

// Fetch all inventory items for dropdown
$items_query = "SELECT item_no, item_name, unit FROM inventory_items ORDER BY item_name";
$items_result = mysqli_query($conn, $items_query);

function getStatusBadge($status)
{
    switch ($status) {
        case 'Active':
            return 'bg-success';
        case 'Returned':
            return 'bg-info';
        case 'Damaged':
            return 'bg-warning';
        case 'Lost':
            return 'bg-danger';
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
    <title>Office Balance Management</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="Logi_manage_office.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <!-- Sidebar (same as inventory page) -->
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Lou March Cordovan</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="./Logi_Sys_Dashboard.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li><a href="Logi_inventory.php"><i class="fas fa-campground icon-size"></i> Inventory</a></li>
            <li><a href="Logi_office_balance.php"><i class="fas fa-users icon-size"></i> Office Balances</a></li>
            <li><a href="transpo.php"><i class="fas fa-truck icon-size"></i> Request</a></li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="main_content">
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="container" id="secondContainer">
                        <!-- Header and Action buttons -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4>Office Balance Management</h4>
                                    <div class="d-flex align-items-center gap-3">
                                        <!-- Action Buttons -->
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-success btn-sm" style="color: white;" data-bs-toggle="modal" data-bs-target="#addOfficeModal">
                                                <i class="fas fa-plus"></i> Add Office
                                            </button>
                                            <button type="button" class="btn btn-primary btn-sm" style="color: white;" data-bs-toggle="modal" data-bs-target="#assignSuppliesModal">
                                                <i class="fas fa-box"></i> Assign Supplies
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm" style="color: white;" data-bs-toggle="modal" data-bs-target="#manageSuppliesModal">
                                                <i class="fas fa-cogs"></i> Manage Supplies
                                            </button>
                                            <button type="button" class="btn btn-info btn-sm" style="color: black;" id="printBtn">
                                                <i class="fas fa-print"></i> Print Report
                                            </button>
                                        </div>


                                        <!-- Search Bar -->
                                        <div class="input-group" style="width: 300px;">
                                            <input type="text" class="form-control" id="searchInput" placeholder="Search offices...">
                                            <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Offices Cards/Table -->
                        <div class="row" id="officesContainer">
                            <?php if (mysqli_num_rows($offices_result) > 0): ?>
                                <?php while ($office = mysqli_fetch_assoc($offices_result)): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?= htmlspecialchars($office['office_name']) ?></h6>
                                                <div class="btn-group dropend">
                                                    <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" onclick="assignSupplies(<?= $office['id'] ?>)">Assign Supplies</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="viewOfficeDetails(<?= $office['id'] ?>)">View Details</a></li>
                                                    </ul>
                                                </div>

                                            </div>
                                            <div class="card-body">
                                                <p class="card-text">
                                                    <small class="text-muted">Department:</small><br>
                                                    <?= htmlspecialchars($office['department']) ?><br>
                                                    <small class="text-muted">Contact:</small><br>
                                                    <?= htmlspecialchars($office['contact_person']) ?>

                                                </p>

                                                <?php
                                                $count_query = "SELECT COUNT(*) as item_count FROM office_balance_items WHERE office_balance_id = {$office['id']} AND status = 'Active'";
                                                $count_result = mysqli_query($conn, $count_query);
                                                $count = mysqli_fetch_assoc($count_result)['item_count'];
                                                ?>

                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="badge bg-primary"><?= $count ?> Items Assigned</span>
                                                    <div>
                                                        <button class="btn btn-sm btn-outline-primary" style="margin-right: -1px; border-top-right-radius: 0; border-bottom-right-radius: 0;" onclick="viewOfficeDetails(<?= $office['id'] ?>)">
                                                            View Items
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" onclick="deleteOffice(<?= $office['id'] ?>)">
                                                            Delete Office
                                                        </button>
                                                    </div>
                                                </div>


                                            </div>

                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No offices found</h5>
                                        <p class="text-muted">Start by adding offices to manage their supply balances.</p>
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addOfficeModal">
                                            <i class="fas fa-plus"></i> Add First Office
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="officeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="officeDetailsTitle">Office Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Office Information</h6>
                                </div>
                                <div class="card-body" id="officeInfo">
                                    <!-- Office details will be loaded here -->
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Assigned Supplies</h6>
                                    <button class="btn btn-sm btn-primary" onclick="assignSupplies(currentOfficeId)">
                                        <i class="fas fa-plus"></i> Add More
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Quantity</th>
                                                    <th>PO Number</th>
                                                    <th>Date Assigned</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="assignedSuppliesTable">
                                                <!-- Assigned supplies will be loaded here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Office Modal -->
    <div class="modal fade" id="addOfficeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Office</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addOfficeForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="officeName" class="form-label">Office Name *</label>
                            <input type="text" class="form-control" id="officeName" name="officeName" required>
                        </div>
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department">
                        </div>
                        <div class="mb-3">
                            <label for="contactPerson" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contactPerson" name="contactPerson">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contactEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="contactEmail" name="contactEmail">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contactPhone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="contactPhone" name="contactPhone">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Office</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Supplies Modal -->
    <div class="modal fade" id="assignSuppliesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Supplies to Office</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignSuppliesForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="selectOffice" class="form-label">Select Office *</label>
                            <select class="form-select" id="selectOffice" name="officeId" required>
                                <option value="">Choose Office...</option>
                                <?php
                                mysqli_data_seek($offices_result, 0);
                                while ($office = mysqli_fetch_assoc($offices_result)):
                                ?>
                                    <option value="<?= $office['id'] ?>"><?= htmlspecialchars($office['office_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplies to Assign</label>
                            <div id="suppliesContainer">
                                <div class="supply-item border rounded p-3 mb-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Item *</label>
                                            <select class="form-select item-select" name="items[]" required>
                                                <option value="">Select Item...</option>
                                                <?php
                                                mysqli_data_seek($items_result, 0);
                                                while ($item = mysqli_fetch_assoc($items_result)):
                                                ?>
                                                    <option value="<?= $item['item_no'] ?>" data-unit="<?= $item['unit'] ?>">
                                                        <?= htmlspecialchars($item['item_name']) ?> (<?= $item['unit'] ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Quantity *</label>
                                            <input type="number" class="form-control" name="quantities[]" min="1" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">PO Number <span class="text-muted">(Optional)</span></label>
                                            <input type="text" class="form-control" name="po_numbers[]" placeholder="PO-2024-001 (optional)">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Date Assigned</label>
                                            <input type="date" class="form-control" name="assigned_dates[]" value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end">
                                            <button type="button" class="btn btn-danger btn-sm remove-supply" disabled>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <label class="form-label">Notes (Optional)</label>
                                            <textarea class="form-control" name="notes[]" rows="2" placeholder="Additional notes..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm" id="addSupplyItem">
                                <i class="fas fa-plus"></i> Add Another Item
                            </button>
                        </div>

                        <div class="mb-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> PO numbers are optional. Make sure the quantities don't exceed available inventory.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Assign Supplies
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Supplies Modal -->
    <div class="modal fade" id="manageSuppliesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Common Use Supplies</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Search Items Section -->
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Search & Select Items</h6>
                                    <button class="btn btn-sm btn-success" id="addSelectedItems" disabled>
                                        <i class="fas fa-arrow-right"></i> Add Selected Items
                                    </button>
                                </div>
                                <div class="card-body">
                                    <!-- Search Bar -->
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" id="itemSearchInput" placeholder="Search items by name or item number...">
                                        <button class="btn btn-outline-secondary" type="button" id="searchItemsBtn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" type="button" id="clearSearchBtn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>

                                    <!-- Search Results Table -->
                                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th width="30">
                                                        <input type="checkbox" id="selectAllSearchItems">
                                                    </th>
                                                    <th>Item No</th>
                                                    <th>Item Name</th>
                                                    <th>Unit</th>
                                                    <th>Available Stock</th>
                                                </tr>
                                            </thead>
                                            <tbody id="searchItemsTable">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        Use the search bar above to find items
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <!-- Common Use Items Section -->
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Common Use Items</h6>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-primary" id="updateCommonItems" disabled>
                                            <i class="fas fa-edit"></i> Update Selected
                                        </button>
                                        <button class="btn btn-danger" id="removeCommonItems" disabled>
                                            <i class="fas fa-trash"></i> Remove Selected
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th width="30">
                                                        <input type="checkbox" id="selectAllCommonItems">
                                                    </th>
                                                    <th>Item No</th>
                                                    <th>Item Name</th>
                                                    <th>Quantity</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="commonItemsTable">
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">
                                                        No common use items added yet
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="d-flex justify-content-between w-100">
                        <div>
                            <span class="text-muted">
                                <span id="selectedItemsCount">0</span> items selected for common use
                            </span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="saveCommonUseChanges">
                                <i class="fas fa-save"></i> Save All Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="Logi_manage_office.js"></script>
</body>

</html>