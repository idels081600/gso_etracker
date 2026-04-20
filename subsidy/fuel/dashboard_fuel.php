<?php
session_start();
require_once 'db_fuel.php';

// Security check - redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

// Check if user has station assigned
if (!isset($_SESSION['station_id']) || empty($_SESSION['station_id'])) {
    // Check if user has station in database
    $username = mysqli_real_escape_string($conn, $_SESSION['username']);
    $check_sql = "SELECT us.station_id, gs.station_name 
                  FROM user_stations us 
                  JOIN gas_stations gs ON us.station_id = gs.id 
                  WHERE us.username = '$username'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Set session
        $station_data = mysqli_fetch_assoc($check_result);
        $_SESSION['station_id'] = $station_data['station_id'];
        $_SESSION['station_name'] = $station_data['station_name'];
    } else {
        // Redirect to station selection
        header("Location: select_station.php");
        exit();
    }
}

$station_name = isset($_SESSION['station_name']) ? $_SESSION['station_name'] : 'Unknown Station';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Subsidy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
</head>

<body>
    <nav class="navbar bg-body-tertiary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Fuel Subsidy - <?php echo htmlspecialchars($station_name); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Fuel Subsidy Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="dashboard_fuel.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="releasing_fuel.php">Releasing</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                More Options
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Reports</a></li>
                                <li><a class="dropdown-item" href="#">Settings</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="select_station.php"><i class="bi bi-arrow-repeat me-2"></i>Change Gas Station</a></li>
                                <li><a class="dropdown-item" href="#">Help</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                        </li>
                    </ul>
                    <form class="d-flex mt-3" role="search">
                        <input class="form-control me-2" type="search" placeholder="Search subsidy records..." aria-label="Search" />
                        <button class="btn btn-outline-success" type="submit">Search</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <section class="container-fluid mt-4">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Tricycles</h6>
                            <p class="display-6 mb-0" id="totalTricycles">0</p>
                            <small class="text-muted">Updated just now</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Active</h6>
                            <p class="display-6 mb-0" id="activeCount">0</p>
                            <small class="text-muted">Active tricycles</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Not Active</h6>
                            <p class="display-6 mb-0" id="inactiveCount">0</p>
                            <small class="text-muted">Needs action</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Vouchers Claimed</h6>
                            <p class="display-6 mb-0" id="totalClaimed">0</p>
                            <small class="text-muted">Monthly total</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Today's Fuel Ups</h6>
                            <p class="display-6 mb-0" id="todayTricycles">0</p>
                            <small class="text-muted">Tricycles fueled today</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Today's Liters</h6>
                            <p class="display-6 mb-0" id="todayLiters">0</p>
                            <small class="text-muted">Liters dispensed today</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Liters</h6>
                            <p class="display-6 mb-0" id="totalLiters">0</p>
                            <small class="text-muted">All time total liters</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Set Pump Price</h6>
                            <div class="input-group mb-2">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="dashboardPumpPrice" placeholder="Current pump price per liter">
                                <button class="btn btn-primary" type="button" id="updateLitersBtn">Update Liters</button>
                            </div>
                            <small class="text-muted">Enter today's pump price to calculate actual liters dispensed</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="container-fluid mt-4 mb-5">
            <div class="card shadow-sm">
                <div class="card-header py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">Fuel Subsidy Records</h5>
                        </div>
                        <div class="col-auto">
                        <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" placeholder="Search records..." id="tableSearch" style="width: 200px;">
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#exportPdfModal">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                                </button>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Bulk Import
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDataModal">
                                    <i class="bi bi-plus-circle me-1"></i>Add Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Tricycle No.</th>
                                    <th scope="col">Driver</th>
                                    <th scope="col">Balance</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Last Claim</th>
                                </tr>
                            </thead>
                            <tbody id="recordsTable">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Bulk Import Modal -->
        <div class="modal fade" id="bulkImportModal" tabindex="-1" aria-labelledby="bulkImportModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkImportModalLabel">Bulk Import Tricycle Records</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Instructions:</strong> Download the CSV template, fill in your data, then upload the file.
                        </div>
                        <div class="mb-3">
                            <a href="template_fuel_subsidy.csv" class="btn btn-outline-primary btn-sm" download>
                                <i class="bi bi-download me-1"></i>Download CSV Template
                            </a>
                        </div>
                        <hr>
                        <form id="bulkImportForm">
                            <div class="mb-3">
                                <label for="csvFile" class="form-label">Upload CSV File</label>
                                <input type="file" class="form-control" id="csvFile" accept=".csv" required>
                            </div>
                            <div class="form-text">
                                <strong>CSV Format:</strong><br>
                                tricycle_no, driver_name, address, contact_number, total_vouchers
                            </div>
                        </form>
                        <div id="importProgress" class="mt-3" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted mt-1" id="importStatus">Processing...</small>
                        </div>
                        <div id="importResult" class="mt-3" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="processImportBtn">
                            <i class="bi bi-upload me-1"></i>Import Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export PDF Modal -->
        <div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exportPdfModalLabel">Export Claimed Vouchers PDF</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="exportStationSelect" class="form-label">Select Gas Station</label>
                            <select class="form-select" id="exportStationSelect" required>
                                <option value="">-- Select Station --</option>
                                <?php
                                // Fetch all active gas stations
                                $station_sql = "SELECT id, station_name FROM gas_stations WHERE is_active = 1 ORDER BY station_name";
                                $station_result = mysqli_query($conn, $station_sql);
                                while ($station_row = mysqli_fetch_assoc($station_result)) {
                                    echo '<option value="' . $station_row['id'] . '">' . htmlspecialchars($station_row['station_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="pumpPrice" class="form-label">Pump Price (Per Liter)</label>
                            <input type="number" step="0.01" class="form-control" id="pumpPrice" placeholder="Enter current pump price" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate">
                            </div>
                            <div class="col-md-6">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate">
                            </div>
                        </div>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Select date range to filter exported records. Leave blank to export all records.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="exportPdfBtn">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Data Modal -->
        <div class="modal fade" id="addDataModal" tabindex="-1" aria-labelledby="addDataModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addDataModalLabel">Add New Tricycle Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addDataForm">
                            <div class="mb-3">
                                <label for="tricycleNo" class="form-label">Tricycle Number</label>
                                <input type="text" class="form-control" id="tricycleNo" placeholder="e.g., 0004" required>
                            </div>
                            <div class="mb-3">
                                <label for="driverName" class="form-label">Driver Name</label>
                                <input type="text" class="form-control" id="driverName" placeholder="Enter driver name" required>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" placeholder="Enter address" required>
                            </div>
                            <div class="mb-3">
                                <label for="contactNumber" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="contactNumber" placeholder="Enter contact number" required>
                            </div>
                            <div class="mb-3">
                                <label for="voucherCount" class="form-label">Number of Vouchers</label>
                                <input type="number" class="form-control" id="voucherCount" placeholder="Enter number of vouchers" min="0" max="10" value="10" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveDataBtn">Save Record</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script src="subsidy.js"></script>
</body>

</html>