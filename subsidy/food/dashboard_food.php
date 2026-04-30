<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

// Security check - redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

// Check if user has station assigned
if (!isset($_SESSION['station_id']) || empty($_SESSION['station_id'])) {
    $username = mysqli_real_escape_string($conn, $_SESSION['username']);
    $check_sql = "SELECT uf.station_id, fm.market_name 
                  FROM user_foods uf 
                  JOIN food_markets fm ON uf.station_id = fm.id 
                  WHERE uf.username = '$username'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $station_data = mysqli_fetch_assoc($check_result);
        $_SESSION['station_id'] = $station_data['station_id'];
        $_SESSION['station_name'] = $station_data['market_name'];
    }
}

// Get proper market name with default fallback
$station_name = isset($_SESSION['station_name']) ? trim($_SESSION['station_name']) : 'Unknown Market';

// Statistics queries
$verified_sql = "SELECT COUNT(*) as total FROM food_voucher_claims WHERE is_verified = 1";
$verified_result = mysqli_query($conn, $verified_sql);
$total_verified = $verified_result ? mysqli_fetch_assoc($verified_result)['total'] : 0;

$verified_today_sql = "SELECT COUNT(DISTINCT beneficiary_id) as total FROM food_voucher_claims WHERE is_verified = 1 AND DATE(claim_date) = CURDATE()";
$verified_today_result = mysqli_query($conn, $verified_today_sql);
$total_verified_today = $verified_today_result ? mysqli_fetch_assoc($verified_today_result)['total'] : 0;

$redeemed_sql = "SELECT COUNT(*) as total FROM food_voucher_claims WHERE is_redeemed = 1";
$redeemed_result = mysqli_query($conn, $redeemed_sql);
$total_redeemed = $redeemed_result ? mysqli_fetch_assoc($redeemed_result)['total'] : 0;

// Get per market verified voucher counts
$market_stats = [];
$market_sql = "SELECT 
                    fm.id, 
                    fm.market_name, 
                    COUNT(fvc.id) as verified_count
               FROM food_markets fm
               LEFT JOIN food_voucher_claims fvc ON fm.id = fvc.station_id AND fvc.is_verified = 1
               GROUP BY fm.id, fm.market_name
               ORDER BY fm.market_name";
$market_result = mysqli_query($conn, $market_sql);
if ($market_result) {
    while ($row = mysqli_fetch_assoc($market_result)) {
        $market_stats[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Subsidy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <!-- Session Heartbeat -->
    <script src="./js/session_heartbeat.js"></script>
    <script>
        SessionHeartbeat.init({
            interval: 5 * 60 * 1000,
            apiUrl: './api_heartbeat.php'
        });
    </script>
</head>

<body>
    <nav class="navbar bg-body-tertiary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Food Subsidy - <?php echo htmlspecialchars($station_name); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Food Voucher Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard_food.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="redeem_batch.php">Batch Redemption</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="batch_history.php">Batch History</a>
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
                                <li><a class="dropdown-item" href="select_station.php"><i class="bi bi-arrow-repeat me-2"></i>Change Station</a></li>
                                <li><a class="dropdown-item" href="#">Help</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <section class="container-fluid mt-4">

            <!-- FOOD VOUCHER STATISTICS -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary mb-3"><i class="bi bi-ticket-perforated me-2"></i>FOOD VOUCHER STATISTICS</h5>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-primary border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Verified Vouchers</h6>
                            <p class="display-6 mb-0 text-primary" id="totalVerifiedVouchers"><?php echo number_format($total_verified); ?></p>
                            <small class="text-muted">All verified vouchers</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-success border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Verified Beneficiaries Today</h6>
                            <p class="display-6 mb-0 text-success" id="totalVerifiedToday"><?php echo number_format($total_verified_today); ?></p>
                            <small class="text-muted">Unique beneficiaries verified today</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-info border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Redeemed Vouchers</h6>
                            <p class="display-6 mb-0 text-info" id="totalRedeemedVouchers"><?php echo number_format($total_redeemed); ?></p>
                            <small class="text-muted">All redeemed vouchers</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MARKET STATISTICS -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="text-primary mb-3"><i class="bi bi-building me-2"></i>MARKET VERIFIED VOUCHERS</h5>
            </div>
        </div>
        <div class="row g-3 mb-4">
            <?php foreach ($market_stats as $idx => $market): ?>
                <?php
                // Cycle through border colors
                $colors = ['border-warning', 'border-danger', 'border-dark', 'border-primary', 'border-success'];
                $color_class = $colors[$idx % count($colors)];
                ?>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm <?php echo $color_class; ?> border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3"><?php echo htmlspecialchars($market['market_name']); ?></h6>
                            <p class="display-6 mb-0"><?php echo number_format($market['verified_count']); ?></p>
                            <small class="text-muted">Verified vouchers</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="container-fluid mt-4 mb-5">
            <div class="card shadow-sm">
                <div class="card-header py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">Food Subsidy Records</h5>
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
                                <a href="api_export_daily_csv.php" class="btn btn-warning btn-sm" target="_blank">
                                    <i class="bi bi-download me-1"></i>Daily Report CSV
                                </a>
                                <a href="api_export_daily_csv.php?all=1" class="btn btn-dark btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-excel me-1"></i>All Report CSV
                                </a>
                                <a href="api_export_raw_csv.php" class="btn btn-info btn-sm" target="_blank">
                                    <i class="bi bi-table me-1"></i>Raw Daily CSV
                                </a>
                                <a href="api_export_raw_csv.php?all=1" class="btn btn-secondary btn-sm" target="_blank">
                                    <i class="bi bi-database me-1"></i>Raw All CSV
                                </a>
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
                                    <th scope="col">Beneficiary Code</th>
                                    <th scope="col">Full Name</th>
                                    <th scope="col">Area</th>
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
                        <h5 class="modal-title" id="bulkImportModalLabel">Bulk Import Beneficiary Records</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Instructions:</strong> Download the CSV template, fill in your data, then upload the file.
                        </div>
                        <div class="mb-3">
                            <a href="template_food_subsidy.csv" class="btn btn-outline-primary btn-sm" download>
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
                                beneficiary_code, full_name, address, contact_number, total_vouchers
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
                            <label for="exportStationSelect" class="form-label">Select Market</label>
                            <select class="form-select" id="exportStationSelect" required>
                                <option value="">-- Select Market --</option>
                                <?php
                                $station_sql = "SELECT id, market_name FROM food_markets WHERE is_active = 1 ORDER BY market_name";
                                $station_result = mysqli_query($conn, $station_sql);
                                while ($station_row = mysqli_fetch_assoc($station_result)) {
                                    echo '<option value="' . $station_row['id'] . '">' . htmlspecialchars($station_row['market_name']) . '</option>';
                                }
                                ?>
                            </select>
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
                        <h5 class="modal-title" id="addDataModalLabel">Add New Beneficiary Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addDataForm">
                            <div class="mb-3">
                                <label for="beneficiaryCode" class="form-label">Beneficiary Code</label>
                                <input type="text" class="form-control" id="beneficiaryCode" placeholder="e.g., FB0001" required>
                            </div>
                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="fullName" placeholder="Enter full name" required>
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
                                <input type="number" class="form-control" id="voucherCount" placeholder="Enter number of vouchers" min="0" max="50" value="12" required>
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