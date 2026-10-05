<?php
session_start();
require_once __DIR__ . '/rice_release_batches.php';
$conn = require(__DIR__ . '/config/database.php');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    header("Location: ../../login_v2.php");
    exit();
}

$station_name = 'Rice Assistance Verification';

$first_wave_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(is_claimed = 1) AS claimed,
            SUM(is_claimed = 0 AND status = 'Active') AS not_claimed
     FROM rice_households"
);
$next_wave_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(is_claimed = 1) AS claimed,
            SUM(is_claimed = 0 AND status = 'Active') AS not_claimed
     FROM rice_claimed_households"
);
$third_wave_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(is_claimed = 1) AS claimed,
            SUM(is_claimed = 0 AND status = 'Active') AS not_claimed
     FROM rice_third_wave_households"
);
$fourth_wave_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(is_claimed = 1) AS claimed,
            SUM(is_claimed = 0 AND status = 'Active') AS not_claimed
     FROM rice_fourth_wave_households"
);
$barangay_result = mysqli_query($conn, "SELECT DISTINCT address FROM rice_households WHERE address IS NOT NULL AND TRIM(address) <> '' ORDER BY address ASC");
$claimed_barangay_result = mysqli_query($conn, "SELECT address FROM rice_claimed_households WHERE status = 'Active' AND address IS NOT NULL AND TRIM(address) <> '' UNION SELECT address FROM rice_third_wave_households WHERE status = 'Active' AND address IS NOT NULL AND TRIM(address) <> '' UNION SELECT address FROM rice_fourth_wave_households WHERE status = 'Active' AND address IS NOT NULL AND TRIM(address) <> '' ORDER BY address ASC");

$first_wave_metrics = $first_wave_result ? mysqli_fetch_assoc($first_wave_result) : [];
$next_wave_metrics = $next_wave_result ? mysqli_fetch_assoc($next_wave_result) : [];
$third_wave_metrics = $third_wave_result ? mysqli_fetch_assoc($third_wave_result) : [];
$fourth_wave_metrics = $fourth_wave_result ? mysqli_fetch_assoc($fourth_wave_result) : [];
$dashboard_metrics = [
    'first_wave' => [
        'total' => (int)($first_wave_metrics['total'] ?? 0),
        'claimed' => (int)($first_wave_metrics['claimed'] ?? 0),
        'not_claimed' => (int)($first_wave_metrics['not_claimed'] ?? 0),
    ],
    'next_wave' => [
        'total' => (int)($next_wave_metrics['total'] ?? 0),
        'claimed' => (int)($next_wave_metrics['claimed'] ?? 0),
        'not_claimed' => (int)($next_wave_metrics['not_claimed'] ?? 0),
    ],
    'third_wave' => [
        'total' => (int)($third_wave_metrics['total'] ?? 0),
        'claimed' => (int)($third_wave_metrics['claimed'] ?? 0),
        'not_claimed' => (int)($third_wave_metrics['not_claimed'] ?? 0),
    ],
    'fourth_wave' => [
        'total' => (int)($fourth_wave_metrics['total'] ?? 0),
        'claimed' => (int)($fourth_wave_metrics['claimed'] ?? 0),
        'not_claimed' => (int)($fourth_wave_metrics['not_claimed'] ?? 0),
        'active' => riceReleaseBatchIsActive($conn, 'fourth_wave'),
    ],
];
$total_households = $dashboard_metrics['first_wave']['total'];
$claimed_total = $dashboard_metrics['first_wave']['claimed'];
$not_claimed_total = $dashboard_metrics['first_wave']['not_claimed'];
$barangays = [];
if ($barangay_result) {
    while ($row = mysqli_fetch_assoc($barangay_result)) {
        $barangays[] = $row['address'];
    }
}

$claimed_barangays = [];
if ($claimed_barangay_result) {
    while ($row = mysqli_fetch_assoc($claimed_barangay_result)) {
        $claimed_barangays[] = $row['address'];
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
        .wave-toggle {
            width: 100%;
            max-width: 520px;
        }
        .wave-toggle .btn {
            flex: 1 1 0;
            min-width: 0;
            border-color: var(--rice-teal);
            color: var(--rice-teal);
            touch-action: manipulation;
            white-space: nowrap;
        }
        .wave-toggle .btn.active,
        .wave-toggle .btn[aria-pressed="true"] {
            background-color: var(--rice-teal);
            border-color: var(--rice-teal);
            color: #fff;
        }
        @media (min-width: 576px) {
            .wave-toggle {
                width: auto;
            }
            .wave-toggle .btn {
                min-width: 112px;
            }
        }
        @media (max-width: 575.98px) {
            .navbar-brand {
                max-width: calc(100% - 72px);
                margin-right: 0;
                white-space: normal;
                font-size: 1rem;
            }
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
                        <li class="nav-item"><a class="nav-link" href="releasing_rice.php">2nd Batch Releasing</a></li>
                        <li class="nav-item"><a class="nav-link" href="releasing_rice_third_wave.php">3rd Batch Releasing</a></li>
                        <li class="nav-item"><a class="nav-link" href="releasing_rice_fourth_wave.php">4th Batch Releasing</a></li>
                        <li class="nav-item"><a class="nav-link" href="releasing_rice_first_wave.php">First-Wave Releasing</a></li>
                        <li class="nav-item"><a class="nav-link" href="cross_check_rice.php">Cross Check</a></li>
                        <li class="nav-item"><a class="nav-link" href="consolidate_rice_claims.php">Claim Consolidation</a></li>
                        <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <section class="container-fluid mt-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
                <h5 class="text-rice-teal mb-0"><i class="bi bi-basket2 me-2"></i>RICE ASSISTANCE STATISTICS</h5>
                <div class="btn-group wave-toggle" role="group" aria-label="Select dashboard wave">
                    <button type="button" class="btn btn-outline-success active" data-dashboard-wave="first_wave" aria-pressed="true">1st Batch</button>
                    <button type="button" class="btn btn-outline-success" data-dashboard-wave="next_wave" aria-pressed="false">2nd Batch</button>
                    <button type="button" class="btn btn-outline-success" data-dashboard-wave="third_wave" aria-pressed="false">3rd Batch</button>
                    <button type="button" class="btn btn-outline-success" data-dashboard-wave="fourth_wave" aria-pressed="false">4th Batch</button>
                </div>
                <span class="badge text-bg-success" id="batchActivationStatus">Active</span>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-rice-teal border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Total Households</h6>
                            <p class="display-6 mb-0 text-rice-teal" id="metricTotalHouseholds"><?php echo number_format($total_households); ?></p>
                            <small class="text-muted" id="metricTotalDescription">Households in the first-wave list</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-rice-teal border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Claimed</h6>
                            <p class="display-6 mb-0 text-rice-teal" id="metricClaimed"><?php echo number_format($claimed_total); ?></p>
                            <small class="text-muted" id="metricClaimedDescription">First-wave households already claimed</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm border-rice-teal border-2">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase text-secondary mb-3">Not Claimed</h6>
                            <p class="display-6 mb-0 text-rice-teal" id="metricNotClaimed"><?php echo number_format($not_claimed_total); ?></p>
                            <small class="text-muted" id="metricNotClaimedDescription">Active first-wave households not yet claimed</small>
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
                            <h5 class="mb-0">Rice Assistance Records - All Batches</h5>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="releasing_rice.php" class="btn btn-warning btn-sm">
                                    <i class="bi bi-person-check me-1"></i>2nd Batch Releasing
                                </a>
                                <a href="releasing_rice_third_wave.php" class="btn btn-rice-teal btn-sm">
                                    <i class="bi bi-person-check me-1"></i>3rd Batch Releasing
                                </a>
                                <a href="releasing_rice_fourth_wave.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-lock me-1"></i>4th Batch Releasing
                                </a>
                                <a href="releasing_rice_first_wave.php" class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-clock-history me-1"></i>First-Wave Releasing
                                </a>
                                <button type="button" class="btn btn-rice-teal btn-sm" data-bs-toggle="modal" data-bs-target="#addHouseholdModal">
                                    <i class="bi bi-plus-circle me-1"></i>Add 1st-Batch Household
                                </button>
                                <input type="text" class="form-control form-control-sm" placeholder="Search records..." id="tableSearch" style="width: 200px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#printBarangayModal">
                                    <i class="bi bi-printer me-1"></i>Print Vouchers
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#printAttendanceModal">
                                    <i class="bi bi-card-checklist me-1"></i>Attendance Sheet
                                </button>
                                <a href="api_export_rice_beneficiaries_pdf.php" class="btn btn-danger btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>1st-Batch PDF
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#claimedPdfModal">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>Claimed PDF
                                </button>
                                <a href="api_export_rice_master_list_pdf.php" class="btn btn-outline-danger btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>1st-Batch Master List
                                </a>
                                <a href="api_export_rice_recipient_form_pdf.php" class="btn btn-outline-success btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>Recipient Form PDF
                                </a>
                                <a data-rice-wave-export href="api_export_rice_daily_csv.php" class="btn btn-rice-teal btn-sm" target="_blank">
                                    <i class="bi bi-download me-1"></i>Daily Report CSV
                                </a>
                                <a data-rice-wave-export href="api_export_rice_daily_csv.php?all=1" class="btn btn-dark btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-excel me-1"></i>All Report CSV
                                </a>
                                <a data-rice-wave-export href="api_export_rice_raw_csv.php" class="btn btn-info btn-sm" target="_blank">
                                    <i class="bi bi-table me-1"></i>Raw Daily CSV
                                </a>
                                <a data-rice-wave-export href="api_export_rice_raw_csv.php?all=1" class="btn btn-secondary btn-sm" target="_blank">
                                    <i class="bi bi-database me-1"></i>Raw All CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Household Code</th>
                                    <th>Household Name</th>
                                    <th>Status</th>
                                    <th>First Wave Claim Status</th>
                                    <th>2nd Batch</th>
                                    <th>2nd-Batch Claimed At</th>
                                    <th>3rd Batch</th>
                                    <th>3rd-Batch Claimed At</th>
                                    <th>4th Batch</th>
                                    <th>4th-Batch Claimed At</th>
                                </tr>
                            </thead>
                            <tbody id="recordsTable"></tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-3">
                        <small class="text-muted" id="tablePaginationInfo">Showing 0 to 0 of 0 records</small>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="prevPageBtn">
                                <i class="bi bi-chevron-left"></i> Prev
                            </button>
                            <span class="small fw-semibold text-nowrap" id="pageIndicator">Page 1 of 1</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="nextPageBtn">
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="addHouseholdModal" tabindex="-1" aria-labelledby="addHouseholdModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-rice-teal" id="addHouseholdModalLabel">Add First-Batch Household</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="addHouseholdBarangay" class="form-label">Barangay</label>
                        <select class="form-select" id="addHouseholdBarangay">
                            <option value="">Select barangay...</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?php echo htmlspecialchars($barangay); ?>"><?php echo htmlspecialchars($barangay); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="addHouseholdLastNumber" class="form-label">Last Number</label>
                            <input type="text" class="form-control fw-semibold" id="addHouseholdLastNumber" value="Select barangay" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="addHouseholdCodePreview" class="form-label">Next Household Code</label>
                            <input type="text" class="form-control fw-semibold" id="addHouseholdCodePreview" value="Select barangay" readonly>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label for="addHouseholdName" class="form-label">Household Name</label>
                        <input type="text" class="form-control" id="addHouseholdName" placeholder="Enter household name">
                        <div id="addHouseholdCodeHint" class="form-text mt-2">The next available code will continue from the latest number in the selected barangay.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-rice-teal" id="saveHouseholdBtn">
                        <i class="bi bi-save me-1"></i>Add 1st-Batch Household
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="printBarangayModal" tabindex="-1" aria-labelledby="printBarangayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printBarangayModalLabel">Print Vouchers by Barangay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Open the complete arranged third- or fourth-distribution set, one barangay, or one exact household code.</p>

                    <form method="get" action="rice_voucher_print.php" target="_blank" class="border border-warning rounded-3 p-3 mb-3 bg-warning-subtle">
                        <input type="hidden" name="wave" value="third_wave">
                        <input type="hidden" name="arranged" value="1">
                        <input type="hidden" name="distribution" value="3">
                        <input type="hidden" name="filter" value="all">
                        <input type="hidden" name="sort" value="name">
                        <h6 class="mb-2">3rd Distribution - Arranged Coupon Set</h6>
                        <p class="small text-muted mb-3">Orange names, codes, and 3RD indicators, grouped by barangay, then PWD, HONEST DRIVERS, PORTER, URBAN POOR, and LOW INCOME.</p>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning text-dark fw-semibold">
                                <i class="bi bi-ticket-perforated me-1"></i>Open 3rd Coupons
                            </button>
                        </div>
                    </form>

                    <form method="get" action="rice_voucher_print.php" target="_blank" class="border border-primary rounded-3 p-3 mb-3 bg-primary-subtle">
                        <input type="hidden" name="wave" value="fourth_wave">
                        <input type="hidden" name="arranged" value="1">
                        <input type="hidden" name="filter" value="all">
                        <input type="hidden" name="sort" value="name">
                        <h6 class="mb-2">4th Distribution - Arranged Coupon Set</h6>
                        <p class="small text-muted mb-3">Blue names, codes, and 4TH indicators, grouped by barangay, then PWD, HONEST DRIVERS, PORTER, URBAN POOR, and LOW INCOME.</p>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-semibold">
                                <i class="bi bi-ticket-perforated me-1"></i>Open 4th Coupons
                            </button>
                        </div>
                    </form>

                    <form method="get" action="rice_voucher_print.php" target="_blank" class="border rounded-3 p-3 mb-3 bg-light-subtle">
                        <div class="mb-3">
                            <label for="voucherBarangayBatch" class="form-label">Distribution Batch</label>
                            <select class="form-select" id="voucherBarangayBatch" name="wave" required>
                                <option value="next_wave" selected>2nd Batch</option>
                                <option value="third_wave">3rd Batch</option>
                                <option value="fourth_wave">4th Batch</option>
                            </select>
                        </div>
                        <h6 class="mb-3">Print by Barangay</h6>
                        <div class="mb-3">
                            <label for="barangaySelect" class="form-label">Barangay</label>
                            <select class="form-select" id="barangaySelect" name="barangay" required>
                                <option value="" selected disabled>Select barangay...</option>
                                <?php foreach ($claimed_barangays as $barangay): ?>
                                    <option value="<?php echo htmlspecialchars($barangay); ?>"><?php echo htmlspecialchars($barangay); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="filter" value="all">
                        <input type="hidden" name="sort" value="name">
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-rice-teal">
                                <i class="bi bi-printer me-1"></i>Open Barangay Print
                            </button>
                        </div>
                    </form>

                    <form method="get" action="rice_voucher_print.php" target="_blank" class="border rounded-3 p-3">
                        <div class="mb-3">
                            <label for="voucherCodeBatch" class="form-label">Distribution Batch</label>
                            <select class="form-select" id="voucherCodeBatch" name="wave" required>
                                <option value="next_wave" selected>2nd Batch</option>
                                <option value="third_wave">3rd Batch</option>
                                <option value="fourth_wave">4th Batch</option>
                            </select>
                        </div>
                        <h6 class="mb-3">Print by Household Code</h6>
                        <div class="mb-3">
                            <label for="householdCodePrint" class="form-label">Household Code</label>
                            <input type="text" class="form-control text-uppercase" id="householdCodePrint" name="household_code" placeholder="Enter exact household code, e.g. R1832 or BOOL - 12" required>
                            <div class="form-text">This opens a print sheet filtered to one exact household code.</div>
                        </div>
                        <input type="hidden" name="filter" value="all">
                        <input type="hidden" name="sort" value="code">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-rice-teal">
                                <i class="bi bi-ticket-perforated me-1"></i>Open Single Coupon
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="claimedPdfModal" tabindex="-1" aria-labelledby="claimedPdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="get" action="api_export_rice_claimed_pdf.php" target="_blank" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="claimedPdfModalLabel">Print Claimed PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="claimedPdfBatch" class="form-label">Distribution Batch</label>
                    <select class="form-select" id="claimedPdfBatch" name="wave" required>
                        <option value="first_wave">1st Batch</option>
                        <option value="next_wave">2nd Batch</option>
                        <option value="third_wave">3rd Batch</option>
                        <option value="fourth_wave">4th Batch</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-rice-teal"><i class="bi bi-file-earmark-pdf me-1"></i>Open PDF</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="printAttendanceModal" tabindex="-1" aria-labelledby="printAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printAttendanceModalLabel">Print Attendance Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Open a registration sheet by barangay or by sector.</p>

                    <form method="get" action="rice_attendance_print.php" target="_blank" class="border rounded-3 p-3 mb-3 bg-light-subtle">
                        <div class="mb-3">
                            <label for="attendanceBarangayBatch" class="form-label">Distribution Batch</label>
                            <select class="form-select" id="attendanceBarangayBatch" name="wave" required>
                                <option value="next_wave" selected>2nd Batch</option>
                                <option value="third_wave">3rd Batch</option>
                                <option value="fourth_wave">4th Batch</option>
                            </select>
                        </div>
                        <h6 class="mb-3">Print by Barangay</h6>
                        <div class="mb-3">
                            <label for="attendanceBarangaySelect" class="form-label">Barangay</label>
                            <select class="form-select" id="attendanceBarangaySelect" name="barangay" required>
                                <option value="" selected disabled>Select barangay...</option>
                                <?php foreach ($claimed_barangays as $barangay): ?>
                                    <option value="<?php echo htmlspecialchars($barangay); ?>"><?php echo htmlspecialchars($barangay); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-rice-teal">
                                <i class="bi bi-printer me-1"></i>Open Barangay Attendance
                            </button>
                        </div>
                    </form>
                    <form method="get" action="rice_attendance_print.php" target="_blank" class="border rounded-3 p-3 bg-light-subtle">
                        <div class="mb-3">
                            <label for="attendanceSectorBatch" class="form-label">Distribution Batch</label>
                            <select class="form-select" id="attendanceSectorBatch" name="wave" required>
                                <option value="next_wave" selected>2nd Batch</option>
                                <option value="third_wave">3rd Batch</option>
                                <option value="fourth_wave">4th Batch</option>
                            </select>
                        </div>
                        <h6 class="mb-3">Print by Sector</h6>
                        <div class="mb-3">
                            <label for="attendanceSectorSelect" class="form-label">Sector</label>
                            <select class="form-select" id="attendanceSectorSelect" name="sector" required>
                                <option value="" selected disabled>Select sector...</option>
                                <option value="pwd">PWD</option>
                                <option value="honest_drivers">HONEST DRIVERS</option>
                                <option value="porter">PORTER</option>
                                <option value="urban_poor">URBAN POOR</option>
                                <option value="low_income">LOW INCOME</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-rice-teal">
                                <i class="bi bi-printer me-1"></i>Open Sector Attendance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.RICE_DASHBOARD_METRICS = <?php echo json_encode($dashboard_metrics, JSON_UNESCAPED_SLASHES); ?>;</script>
    <script src="rice_dashboard.js?v=<?php echo rawurlencode((string)filemtime(__DIR__ . '/rice_dashboard.js')); ?>"></script>
</body>
</html>
