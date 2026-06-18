<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    header("Location: ../../login_v2.php");
    exit();
}

$station_name = 'Rice Assistance Verification';

$total_households_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM rice_households");
$claimed_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM rice_households WHERE is_claimed = 1");
$not_claimed_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM rice_households WHERE is_claimed = 0 AND status = 'Active'");
$barangay_result = mysqli_query($conn, "SELECT DISTINCT address FROM rice_households WHERE address IS NOT NULL AND TRIM(address) <> '' ORDER BY address ASC");

$total_households = $total_households_result ? (int)mysqli_fetch_assoc($total_households_result)['total'] : 0;
$claimed_total = $claimed_result ? (int)mysqli_fetch_assoc($claimed_result)['total'] : 0;
$not_claimed_total = $not_claimed_result ? (int)mysqli_fetch_assoc($not_claimed_result)['total'] : 0;
$barangays = [];
if ($barangay_result) {
    while ($row = mysqli_fetch_assoc($barangay_result)) {
        $barangays[] = $row['address'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rice Assistance Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <style>
        :root {
            --rice-teal: #0f766e;
            --rice-teal-dark: #115e59;
            --rice-teal-soft: #ccfbf1;
            --rice-teal-border: #14b8a6;
        }
        .text-rice-teal { color: var(--rice-teal) !important; }
        .border-rice-teal { border-color: var(--rice-teal-border) !important; }
        .btn-rice-teal {
            background-color: var(--rice-teal);
            border-color: var(--rice-teal);
            color: #fff;
        }
        .btn-rice-teal:hover,
        .btn-rice-teal:focus {
            background-color: var(--rice-teal-dark);
            border-color: var(--rice-teal-dark);
            color: #fff;
        }
    </style>
    <script src="./js/session_heartbeat.js"></script>
    <script>
        SessionHeartbeat.init({ apiUrl: './api_heartbeat.php' });
    </script>
</head>
<body class="bg-light">
    <nav class="navbar bg-body-tertiary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Rice Assistance - <?php echo htmlspecialchars($station_name); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Rice Assistance Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard_rice.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="releasing_rice.php">Releasing</a></li>
                        <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <section class="container-fluid mt-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-rice-teal mb-3"><i class="bi bi-basket2 me-2"></i>RICE ASSISTANCE STATISTICS</h5>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-rice-teal border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Households</h6>
                            <p class="display-6 mb-0 text-rice-teal"><?php echo number_format($total_households); ?></p>
                            <small class="text-muted">Registered rice households</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-rice-teal border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Claimed</h6>
                            <p class="display-6 mb-0 text-rice-teal"><?php echo number_format($claimed_total); ?></p>
                            <small class="text-muted">Households already claimed</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-rice-teal border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Not Claimed</h6>
                            <p class="display-6 mb-0 text-rice-teal"><?php echo number_format($not_claimed_total); ?></p>
                            <small class="text-muted">Active households not yet claimed</small>
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
                            <h5 class="mb-0">Rice Assistance Records</h5>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex gap-2">
                                <a href="releasing_rice.php" class="btn btn-warning btn-sm">
                                    <i class="bi bi-person-check me-1"></i>Open Releasing
                                </a>
                                <input type="text" class="form-control form-control-sm" placeholder="Search records..." id="tableSearch" style="width: 200px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#printBarangayModal">
                                    <i class="bi bi-printer me-1"></i>Print Vouchers
                                </button>
                                <a href="api_export_rice_daily_csv.php" class="btn btn-rice-teal btn-sm" target="_blank">
                                    <i class="bi bi-download me-1"></i>Daily Report CSV
                                </a>
                                <a href="api_export_rice_daily_csv.php?all=1" class="btn btn-dark btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-excel me-1"></i>All Report CSV
                                </a>
                                <a href="api_export_rice_raw_csv.php" class="btn btn-info btn-sm" target="_blank">
                                    <i class="bi bi-table me-1"></i>Raw Daily CSV
                                </a>
                                <a href="api_export_rice_raw_csv.php?all=1" class="btn btn-secondary btn-sm" target="_blank">
                                    <i class="bi bi-database me-1"></i>Raw All CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Household Code</th>
                                    <th>Household Name</th>
                                    <th>Status</th>
                                    <th>Claim State</th>
                                    <th>Claimed At</th>
                                </tr>
                            </thead>
                            <tbody id="recordsTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="printBarangayModal" tabindex="-1" aria-labelledby="printBarangayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printBarangayModalLabel">Print Vouchers by Barangay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="get" action="rice_voucher_print.php" target="_blank">
                    <div class="modal-body">
                        <p class="text-muted mb-3">Choose one barangay. The voucher sheet will be filtered to that barangay and arranged by household code in ascending order.</p>
                        <div class="mb-3">
                            <label for="barangaySelect" class="form-label">Barangay</label>
                            <select class="form-select" id="barangaySelect" name="barangay" required>
                                <option value="" selected disabled>Select barangay...</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo htmlspecialchars($barangay); ?>"><?php echo htmlspecialchars($barangay); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="filter" value="unclaimed">
                        <input type="hidden" name="sort" value="code">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-rice-teal">
                            <i class="bi bi-printer me-1"></i>Open Print Sheet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="rice_dashboard.js"></script>
</body>
</html>
