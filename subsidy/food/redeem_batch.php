<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

// Check authentication
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit;
}

// Role check - only FOOD_REDEEMER allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'FOOD_REDEEMER') {
    header("Location: ../../login_v2.php");
    exit;
}

$pageTitle = "Batch Voucher Redemption";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <style>
        .voucher-row.selected {
            background-color: #d1e7dd !important;
        }

        .status-badge {
            font-size: 0.75rem;
        }

        #draftIndicator {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #ffc107;
        }

        #draftIndicator .btn-close {
            padding: 0.5rem;
        }
    </style>
    <!-- Session Heartbeat -->
    <script src="./js/session_heartbeat.js"></script>
    <script>
       
        SessionHeartbeat.init({
            interval: 5 * 60 * 1000, // 5 minutes
            apiUrl: './api_heartbeat.php',
            warningThreshold: 5 * 60 * 1000
        });

        // Pass user info to JavaScript
        window.USER_INFO = {
            username: '<?= addslashes($_SESSION['username']) ?>',
            role: '<?= addslashes($_SESSION['role']) ?>'
        };
    </script>
</head>

<body class="bg-light">

    <!-- Navigation -->
    <nav class="navbar bg-body-tertiary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_food.php">Food Voucher System </a>
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

    <div class="container mt-1">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-box-seam me-2"></i>
                    Batch Voucher Redemption
                </h2>
            </div>
        </div>

        <!-- Vendor Search Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-shop me-2"></i>
                    Search Vendor
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group position-relative">
                            <span class="input-group-text">Vendor Serial</span>
                            <input type="text" id="vendorSerial" class="form-control" placeholder="Enter Vendor Serial or Vendor Name" maxlength="50" autocomplete="off" style="font-size: 1.5rem;">
                            <button class="btn btn-primary" id="searchVendorBtn">
                                <i class="bi bi-search me-1"></i> Search
                            </button>
                        </div>
                        <!-- Vendor Autocomplete Dropdown -->
                        <div id="vendorSuggestions" class="list-group position-absolute w-100 mt-1 z-3 d-none" style="max-height: 250px; overflow-y: auto;"></div>
                    </div>
                </div>

                <!-- Vendor Information -->
                <div id="vendorInfo" class="mt-4 d-none">
                    <div class="alert alert-success">
                        <h6 class="alert-heading">
                            <i class="bi bi-check-circle me-2"></i>
                            Vendor Found
                        </h6>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <strong>Vendor ID:</strong> <span id="vendorId">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Vendor Name:</strong> <span id="vendorName">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Stall No:</strong> <span id="vendorStallNo">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Section:</strong> <span id="vendorSection">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dual Table Card -->
        <div id="voucherSection" class="card shadow-sm d-none">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-ticket-perforated me-2"></i>
                    Voucher Selection
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- LEFT TABLE: Available Vouchers -->
                    <div class="col-lg-6">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="bi bi-list-ul me-2"></i>
                                    Available Vouchers
                                </h6>
                            </div>
                            <div class="card-body p-2 bg-light">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="voucherSearch" class="form-control" placeholder="Search voucher..." style="font-size: 1.3rem;">
                                    <button class="btn btn-outline-secondary" id="clearVoucherSearch" type="button">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="60">Code</th>
                                            <th>Beneficiary</th>
                                            <th width="50">Verified</th>
                                            <th width="50">Redeemed</th>
                                            <th width="70">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="availableVoucherBody">
                                        <!-- Available vouchers loaded here -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light text-center text-muted">
                                <small id="availableCount">0 available</small>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT TABLE: Selected Vouchers -->
                    <div class="col-lg-6">
                        <div class="card border border-success">
                            <div class="card-header bg-success text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="bi bi-check-circle me-2"></i>
                                        Selected Vouchers
                                    </h6>
                                    <span class="badge bg-light text-dark" id="selectedCount">0 selected</span>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="40">No.</th>
                                            <th width="60">Code</th>
                                            <th>Beneficiary</th>
                                            <th width="70">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="selectedVoucherBody">
                                        <!-- Selected vouchers loaded here -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="row text-center">
                                    <div class="col-6 border-end">
                                        <small class="text-muted">Total Selected</small>
                                        <div class="fw-bold" id="totalAmount">₱0.00</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Items</small>
                                        <div class="fw-bold" id="totalItems">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <div>
                    <button id="refreshBtn" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                    <button id="saveDraftBtn" class="btn btn-outline-info btn-sm ms-2" disabled>
                        <i class="bi bi-save me-1"></i> Save Draft
                    </button>
                </div>
                <div>
                    <button id="redeemBtn" class="btn btn-success" disabled>
                        <i class="bi bi-check-circle me-1"></i>
                        Create Batch & Export PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- No Vendor Selected Message -->
        <div id="noVendorMessage" class="alert alert-info text-center py-5">
            <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
            <h5>Search for a vendor to begin redemption</h5>
            <p class="mb-0">Enter the vendor serial number above to view eligible vouchers</p>
        </div>

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

    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Redemption</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to redeem <strong id="modalCount">0</strong> vouchers for:</p>
                    <div class="alert alert-info">
                        <strong id="modalVendorName">-</strong>
                    </div>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmRedeemBtn" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Confirm Redemption
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Redemption Complete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="bi bi-check-circle text-success fs-1"></i>
                    <h5 class="mt-3">Redemption Successful</h5>
                    <p id="successMessage" class="mb-0"></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/redeem_batch.js"></script>

</body>

</html>