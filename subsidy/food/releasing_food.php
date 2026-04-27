<?php
session_start();
require_once 'db_fuel.php';

// Security check - redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

// Role check - only FOOD_VERIFIER allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'FOOD_VERIFIER') {
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
    <title>Food Subsidy - Releasing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
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
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Fuel Subsidy Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="dashboard_fuel.php">Home</a>
                        </li> -->
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="releasing_food.php">Releasing</a>
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
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-10 col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body py-5">
                            <div class="row align-items-center">
                                <div class="col-md-8 mb-4 mb-md-0">
                                    <h2 class="fw-bold mb-2">Food Subsidy Releasing</h2>
                                    <p class="text-muted mb-4">Search for a beneficiary and confirm voucher release details.</p>
                                    <div class="input-group input-group-lg position-relative">
                                        <input id="mainSearch" type="text" class="form-control" placeholder="Search tricycle number or driver name" aria-label="Search tricycle" autocomplete="off">
                                        <button class="btn btn-primary" type="button" id="searchBtn"><i class="bi bi-search me-2"></i>Search</button>
                                        <!-- Autocomplete Dropdown -->
                                        <div id="searchDropdown" class="dropdown-menu w-100 shadow" style="display: none; top: 100%; left: 0; border-radius: 0 0 8px 8px; z-index: 1050; max-height: 320px; overflow-y: auto;">
                                            <div class="text-muted text-center py-2 small" id="searchLoading">Searching...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-4 bg-light p-4 h-100 d-flex flex-column justify-content-center align-items-center text-center">
                                        <p class="text-uppercase text-secondary mb-2">Current Tricycle</p>
                                        <h1 id="currentTricycle" class="display-4 fw-bold mb-1">----</h1>
                                        <p id="currentStatus" class="mb-0 text-muted">Search to load</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card shadow-sm border-0 mt-4">
                        <div class="card-body">
                            <h5 class="fw-semibold mb-3">Voucher Release Tracker</h5>
                            <p class="text-muted mb-4">Tap a voucher to mark it claimed. Claimed vouchers are shown in green.</p>
                            <div id="voucherButtons" class="row g-3"></div>
                            <div class="mt-4 d-flex gap-2">
                                <button type="button" class="btn btn-success" id="submitBtn" data-bs-toggle="modal" data-bs-target="#submitModal">
                                    <i class="bi bi-check-lg me-1"></i>Submit
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearBtn">
                                    <i class="bi bi-x-lg me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Proof of Claim Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="proofModalLabel">Proof of Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <h6 class="mb-3">Voucher: <span id="proofVoucherNo" class="text-success fw-bold"></span></h6>
                    <p class="mb-2"><strong>Claimant:</strong> <span id="proofClaimant" class="text-muted">N/A</span></p>
                    <p class="mb-3"><strong>Claim Date:</strong> <span id="proofDate" class="text-muted">N/A</span></p>
                    <div class="border rounded p-2 bg-light">
                        <img id="proofSignature" src="" alt="E-Signature" class="img-fluid" style="max-height: 200px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>



    <!-- Submit Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1" aria-labelledby="submitModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="submitModalLabel">Confirm Voucher Release</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name of Claimant (Optional)</label>
                        <div class="mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="claimantOption" id="claimantDriver" value="driver" checked>
                                <label class="form-check-label" for="claimantDriver">Use Driver Name</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="claimantOption" id="claimantManual" value="manual">
                                <label class="form-check-label" for="claimantManual">Enter Manually</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <select class="form-select" id="claimantNameDriver">
                                <option value="">Select driver...</option>
                            </select>
                        </div>
                        <input type="text" class="form-control" id="claimantNameManual" placeholder="Enter claimant name manually" disabled>
                        <input type="hidden" id="claimantName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-Signature</label>
                        <div class="border rounded p-2 bg-light">
                            <canvas id="signatureCanvas" width="100%" height="150" style="width: 100%; height: 150px; border: 1px dashed #ccc; background: #fff; cursor: crosshair;"></canvas>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
                                <i class="bi bi-eraser me-1"></i>Clear Signature
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmSubmit">
                        <i class="bi bi-check-lg me-1"></i>Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="releasing.js"></script>
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin {
            animation: spin 1s linear infinite;
        }
        
        .voucher-card {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 2px solid #198754;
            min-width: 100px;
        }
        .voucher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }
        .voucher-card.claimed {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            border-color: #157347;
            cursor: default;
        }
        .voucher-card.claimed:hover {
            transform: none;
            box-shadow: none;
        }
        .voucher-number {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .voucher-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</body>

</html>