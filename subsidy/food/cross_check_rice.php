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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rice Assistance - Cross Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <style>
        :root {
            --rice-teal: #0f766e;
            --rice-teal-dark: #115e59;
            --rice-teal-soft: #ccfbf1;
            --rice-teal-border: #14b8a6;
        }
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
        .text-rice-teal { color: var(--rice-teal) !important; }
        .bg-rice-teal-soft { background-color: var(--rice-teal-soft) !important; }
        .border-rice-teal { border-color: var(--rice-teal-border) !important; }
        .bg-rice-danger-soft { background-color: #fee2e2 !important; }
        .border-rice-danger { border-color: #ef4444 !important; }
        .checked-badge {
            min-width: 120px;
        }
        .result-detail-label {
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.35rem;
        }
        .result-detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            word-break: break-word;
        }
    </style>
    <script src="./js/session_heartbeat.js"></script>
    <script>
        SessionHeartbeat.init({ apiUrl: './api_heartbeat.php' });
    </script>
</head>
<body>
    <nav class="navbar bg-body-tertiary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_rice.php">Rice Assistance - <?php echo htmlspecialchars($station_name); ?></a>
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
                        <li class="nav-item"><a class="nav-link" href="dashboard_rice.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="releasing_rice.php">Next-Wave Releasing</a></li>
                        <li class="nav-item"><a class="nav-link" href="releasing_rice_first_wave.php">First-Wave Releasing</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="cross_check_rice.php">Cross Check</a></li>
                        <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-xl-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body py-5">
                            <div class="row align-items-center g-4">
                                <div class="col-lg-7">
                                    <h2 class="fw-bold mb-2">Rice Cross Check</h2>
                                    <p class="text-muted mb-4">Search by household name or household code to confirm whether the household exists and whether it has already claimed.</p>
                                    <div class="input-group input-group-lg position-relative">
                                        <input id="crossCheckSearch" type="text" class="form-control" placeholder="Search household name or code" aria-label="Search household" autocomplete="off">
                                        <button class="btn btn-rice-teal" type="button" id="crossCheckSearchBtn"><i class="bi bi-search me-2"></i>Search</button>
                                        <div id="crossCheckDropdown" class="dropdown-menu w-100 shadow" style="display: none; top: 100%; left: 0; border-radius: 0 0 8px 8px; z-index: 1050; max-height: 320px; overflow-y: auto;">
                                            <div class="text-muted text-center py-2 small">Searching...</div>
                                        </div>
                                    </div>
                                    <div class="form-text mt-2">Type at least 2 characters to find matching households.</div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="border rounded-4 bg-light p-4 h-100 d-flex flex-column justify-content-center align-items-center text-center">
                                        <p class="text-uppercase text-secondary mb-2">Selected Household</p>
                                        <h1 id="selectedHouseholdCode" class="display-5 fw-bold mb-1">----</h1>
                                        <p id="selectedHouseholdName" class="mb-1 text-muted">No household selected</p>
                                        <p id="selectedHouseholdMeta" class="mb-0 text-muted">Search to load</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mt-4">
                        <div class="card-body p-4 p-lg-5">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                <div>
                                    <h5 class="fw-semibold mb-1">Cross-Check Result</h5>
                                    <p class="text-muted mb-0">Review the current household status without opening the releasing workflow.</p>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span id="checkedStatusBadge" class="badge text-bg-secondary checked-badge">Not Checked</span>
                                    <button type="button" class="btn btn-outline-success" id="toggleCheckedBtn" disabled>
                                        <i class="bi bi-check2-square me-1"></i>Mark Checked
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="crossCheckClearBtn">
                                        <i class="bi bi-x-lg me-1"></i>Clear
                                    </button>
                                </div>
                            </div>

                            <div id="crossCheckStateCard" class="card border-2 border-secondary-subtle bg-light mb-4">
                                <div class="card-body text-center py-5">
                                    <p class="text-uppercase text-muted small mb-2">Claim State</p>
                                    <h3 id="crossCheckStateText" class="fw-bold mb-2">No household loaded</h3>
                                    <p id="crossCheckStateHint" class="text-muted mb-0">Search by household name or household code.</p>
                                </div>
                            </div>

                            <div class="row g-3" id="crossCheckDetailsRow">
                                <div class="col-md-3">
                                    <div class="border rounded-3 p-3 h-100 bg-white">
                                        <div class="result-detail-label">Household Code</div>
                                        <div class="result-detail-value" id="detailHouseholdCode">--</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded-3 p-3 h-100 bg-white">
                                        <div class="result-detail-label">Household Name</div>
                                        <div class="result-detail-value" id="detailHouseholdName">--</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded-3 p-3 h-100 bg-white">
                                        <div class="result-detail-label">Barangay</div>
                                        <div class="result-detail-value" id="detailBarangay">--</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded-3 p-3 h-100 bg-white">
                                        <div class="result-detail-label">Checked Time</div>
                                        <div class="result-detail-value" id="detailCheckedTime">--</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="cross_check_rice.js?v=<?php echo rawurlencode((string)filemtime(__DIR__ . '/cross_check_rice.js')); ?>"></script>
</body>
</html>
