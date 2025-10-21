<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("location: ../login_v2.php");
    exit;
}
require_once 'display_stat.php';
$start_date_param = !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date_param = !empty($_GET['end_date']) ? $_GET['end_date'] : null;

$total_amount_BQ = display_data_stat_BQ($start_date_param, $end_date_param);
$total_amount_NODAL = display_data_stat_NODAL($start_date_param, $end_date_param);
$total_amount_JETS_MARKETING = display_data_stat_JETS_MARKETING($start_date_param, $end_date_param);
$total_amount_JJS_SEAFOODS = display_data_stat_JJS_SEAFOODS($start_date_param, $end_date_param);
$total_amount_CITY_TYRE = display_data_stat_CITY_TYRE($start_date_param, $end_date_param);
$total_amount_BQ_BUILDERWARE = display_data_stat_BQ_BUILDERWARE($start_date_param, $end_date_param);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            width: 20rem;
            height: 13rem;
        }

        .card-text {
            margin-top: 3rem;
            font-size: 1.6rem;
            color: green;
            font-weight: bold;
        }

        .table-container {
            overflow: auto;
            margin: 0 auto;
            margin-top: 2rem;
            max-width: 90vw;
            max-height: 70vh;
        }

        .table-container::-webkit-scrollbar {
            display: none;
        }

        .table {
            padding: 2rem;
            min-width: 800px;
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            position: sticky;
            top: 0;
            background-color: #00772eff;
            color: white;
            z-index: 10;
        }

        .cards-row {
            display: flex;
            flex-direction: row;
            overflow-x: auto;
            gap: 1rem;
            padding: 0.5rem;
        }

        .table-container-modal {
            overflow: auto;
            max-height: 60vh;
        }

        .table-container-modal table {
            min-width: 1000px;
        }
    </style>
</head>

<body>
    <?php require_once 'advance_po_db.php'; ?>
    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="modal" data-bs-target="#exampleModal">Add data</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="modal" data-bs-target="#editModal">Edit data</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="modal" data-bs-target="#printModal">Report</a>
                    </li>
                      <li class="nav-item">
                        <a class="nav-link active" href="Po_monitoring.php">Po Monitoring</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="../logout.php">Logout</a>
                    </li>
                    <!-- <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Dropdown
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Action</a></li>
                            <li><a class="dropdown-item" href="#">Another action</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#">Something else here</a></li>
                        </ul>
                    </li> -->
                    <!-- <li class="nav-item">
                        <a class="nav-link disabled" aria-disabled="true">Disabled</a>
                    </li> -->
                </ul>
                <!-- <form class="d-flex" role="search">
                    <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search" />
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form> -->
            </div>
        </div>
    </nav>
    <div class="container-fluid">
        <!-- Notification container for toasts -->
        <div id="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;" aria-live="polite" aria-atomic="true"></div>

        <!-- Content here -->
        <h1>Dashboard</h1>
        <div class="my-container">
            <div class="cards-row">
                <div class="col-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">BQ</h5>
                            <h6 class="card-subtitle mb-2 text-body-secondary">Total Expenses</h6>
                            <p class="card-text"><?php echo $total_amount_BQ; ?></p>
                        </div>
                    </div>
                </div>
                    <div class="col-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">BQ BUILDERWARE</h5>
                            <h6 class="card-subtitle mb-2 text-body-secondary">Total Expenses</h6>
                            <p class="card-text"><?php echo $total_amount_BQ_BUILDERWARE; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">NODAL</h5>
                            <h6 class="card-subtitle mb-2 text-body-secondary">Total Expenses</h6>
                        <p class="card-text"><?php echo $total_amount_NODAL; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">JETS MARKETING</h5>
                            <h6 class="card-subtitle mb-2 text-body-secondary">Total Expenses</h6>
                            <p class="card-text"><?php echo $total_amount_JETS_MARKETING; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">JJS SEAFOODS</h5>
                            <h6 class="card-subtitle mb-2 text-body-secondary">Total Expenses</h6>
                            <p class="card-text"><?php echo $total_amount_JJS_SEAFOODS; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">CITY TYRE</h5>
                            <h6 class="card-subtitle mb-2 text-body-secondary">Total Expenses</h6>
                            <p class="card-text"><?php echo $total_amount_CITY_TYRE; ?></p>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    <div class="filter-container" style="margin: 0 auto; margin-top: 2rem; margin-bottom: 1rem; max-width: 90vw;">
        <form method="GET" action="" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="text" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>" placeholder="Select start date">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="text" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>" placeholder="Select end date">
                </div>
                <div class="col-md-2">
                    <label for="filter_store" class="form-label">Store</label>
                    <select class="form-select" id="filter_store" name="filter_store">
                        <option value="">All Stores</option>
                        <option value="BQ" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'BQ') ? 'selected' : ''; ?>>BQ</option>
                        <option value="NODAL" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'NODAL') ? 'selected' : ''; ?>>NODAL</option>
                        <option value="JETS MARKETING" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'JETS MARKETING') ? 'selected' : ''; ?>>JETS MARKETING</option>
                        <option value="JJS SEAFOODS" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'JJS SEAFOODS') ? 'selected' : ''; ?>>JJS SEAFOODS</option>
                        <option value="CITY TYRE" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'CITY TYRE') ? 'selected' : ''; ?>>CITY TYRE</option>
                        <option value="BQ BUILDERWARE" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'BQ BUILDERWARE') ? 'selected' : ''; ?>>BQ BUILDERWARE</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search_term" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search_term" name="search_term" value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>" placeholder="Search all columns">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary ms-1">Clear Filters</a>
                </div>
            </div>
        </form>
    </div>
    <div class="table-container">

        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Store</th>
                    <th scope="col">Date</th>
                    <th scope="col">Invoice Number</th>
                    <th scope="col">Description</th>
                    <th scope="col">Pcs</th>
                    <th scope="col">Unit Price</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody class="table-group-divider">
                <?php
                $query = "SELECT store, date, invoice_number, description, pcs, unit_price, amount, status FROM advancePo WHERE delete_status = 0";

                $whereConditions = [];

                if (!empty($_GET['start_date'])) {
                    $whereConditions[] = "date >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
                }

                if (!empty($_GET['end_date'])) {
                    $whereConditions[] = "date <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
                }

                if (!empty($_GET['filter_store'])) {
                    $whereConditions[] = "store = '" . mysqli_real_escape_string($conn, $_GET['filter_store']) . "'";
                }

                if (!empty($_GET['search_term'])) {
                    $search = mysqli_real_escape_string($conn, $_GET['search_term']);
                    $whereConditions[] = "(store LIKE '%" . $search . "%' OR date LIKE '%" . $search . "%' OR invoice_number LIKE '%" . $search . "%' OR description LIKE '%" . $search . "%' OR pcs LIKE '%" . $search . "%' OR unit_price LIKE '%" . $search . "%' OR amount LIKE '%" . $search . "%' OR status LIKE '%" . $search . "%')";
                }

                if (!empty($whereConditions)) {
                    $query .= " AND " . implode(" AND ", $whereConditions);
                }

                $query .= " ORDER BY id DESC";

                $result = mysqli_query($conn, $query);

                if ($result && mysqli_num_rows($result) > 0) {
                    $count = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                        <td>{$row['store']}</td>
                        <td>{$row['date']}</td>
                        <td>{$row['invoice_number']}</td>
                        <td>{$row['description']}</td>
                        <td>{$row['pcs']}</td>
                        <td>₱" . number_format($row['unit_price'], 2) . "</td>
                        <td>₱" . number_format($row['amount'], 2) . "</td>
                        <td>{$row['status']}</td>
                      </tr>";
                    }
                } else {
                    echo "<tr><td colspan='9' class='text-center'>No records found</td></tr>";
                }

                // Close the database connection
                mysqli_close($conn);
                ?>
            </tbody>
        </table>
    </div>
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalLabel">Add items</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDataForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Store</label>
                                <select class="form-select Store" name="Store">
                                    <option value="">Select Store</option>
                                    <option value="BQ">BQ</option>
                                    <option value="NODAL">NODAL</option>
                                    <option value="JETS MARKETING">JETS MARKETING</option>
                                    <option value="JJS SEAFOODS">JJS SEAFOODS</option>
                                    <option value="CITY TYRE">CITY TYRE</option>
                                    <option value="BQ BUILDERWARE">BQ BUILDERWARE</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="date" class="form-label">Date</label>
                                <input type="text" class="form-control" id="date" name="date" placeholder="Select a date">
                            </div>
                            <div class="col-md-6">
                                <label for="invoice_number" class="form-label">Invoice Number</label>
                                <input type="text" class="form-control" id="invoice_number" name="invoice_number">
                            </div>
                            <div class="col-md-6">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="description" name="description">
                            </div>
                            <div class="col-md-6">
                                <label for="pcs" class="form-label">Pieces</label>
                                <input type="number" class="form-control" id="pcs" name="pcs" min="0" step="1">
                            </div>
                            <div class="col-md-6">
                                <label for="unit_price" class="form-label">Unit Price</label>
                                <input type="number" class="form-control" id="unit_price" name="unit_price" min="0" step="0.01">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-info" id="addItemBtn">
                        <i class="fas fa-plus"></i> Add Multiple Items
                    </button>
                    <button type="button" class="btn btn-warning" id="saveSingleItemBtn">
                        <i class="fas fa-save"></i> Save changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Multiple Items Modal -->
    <div class="modal fade" id="multipleItemsModal" tabindex="-1" aria-labelledby="multipleItemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="multipleItemsModalLabel">Add Multiple Items</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Shared Fields -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="sharedDate" class="form-label fw-bold">Shared Date</label>
                            <input type="text" class="form-control" id="sharedDate" name="shared_date" placeholder="Select a date">
                        </div>
                        <div class="col-md-4">
                            <label for="sharedStore" class="form-label fw-bold">Shared Store</label>
                            <select class="form-select" id="sharedStore" name="shared_store">
                                <option value="">Select Store</option>
                                <option value="BQ">BQ</option>
                                <option value="NODAL">NODAL</option>
                                <option value="JETS MARKETING">JETS MARKETING</option>
                                <option value="JJS SEAFOODS">JJS SEAFOODS</option>
                                <option value="CITY TYRE">CITY TYRE</option>
                                <option value="BQ BUILDERWARE">BQ BUILDERWARE</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="sharedInvoiceNumber" class="form-label fw-bold">Shared Invoice Number</label>
                            <input type="text" class="form-control" id="sharedInvoiceNumber" name="shared_invoice_number" placeholder="Enter invoice number for all items">
                        </div>
                    </div>

                    <!-- Dynamic Item Rows -->
                    <div id="multipleItemsContainer">
                        <!-- Dynamic item rows will be added here -->
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addAnotherItemBtn">
                        <i class="fas fa-plus"></i> Add Another Item
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveMultipleItemsBtn">
                        <i class="fas fa-save"></i> Save Items
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and jQuery UI for datepicker -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="dashboard.js"></script>

    <!-- Edit Data Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="editSearchInput" placeholder="Search records in edit modal...">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-success btn-sm" id="refreshDataBtn">
                                <i class="fas fa-sync"></i> Refresh Data
                            </button>
                        </div>
                    </div>
                    <div class="table-container-modal">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Store</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Invoice Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Pcs</th>
                                    <th scope="col">Unit Price</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="editDataTable">
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Row Modal -->
    <div class="modal fade" id="editRowModal" tabindex="-1" aria-labelledby="editRowModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRowModalLabel">Edit Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editRowForm">
                        <input type="hidden" id="edit_row_id" name="edit_row_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Store</label>
                                <select class="form-select" id="edit_store" name="edit_store">
                                    <option value="BQ">BQ</option>
                                    <option value="NODAL">NODAL</option>
                                    <option value="JETS MARKETING">JETS MARKETING</option>
                                    <option value="JJS SEAFOODS">JJS SEAFOODS</option>
                                    <option value="CITY TYRE">CITY TYRE</option>
                                    <option value="BQ BUILDERWARE">BQ BUILDERWARE</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_date" class="form-label">Date</label>
                                <input type="text" class="form-control" id="edit_date" name="edit_date">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_invoice_number" class="form-label">Invoice Number</label>
                                <input type="text" class="form-control" id="edit_invoice_number" name="edit_invoice_number">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="edit_description" name="edit_description">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_pcs" class="form-label">Pieces</label>
                                <input type="number" class="form-control" id="edit_pcs" name="edit_pcs" min="0" step="1">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_unit_price" class="form-label">Unit Price</label>
                                <input type="number" class="form-control" id="edit_unit_price" name="edit_unit_price" min="0" step="0.01">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="edit_status">
                                    <option value="Pending">Pending</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="Declined">Declined</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>


</body>

</html>
