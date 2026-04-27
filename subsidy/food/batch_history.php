<?php
session_start();
require_once 'db_fuel.php';

// Check authentication
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../../login_v2.php");
//     exit;
// }

$pageTitle = "Batch History";
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
        .status-badge {
            font-size: 0.75rem;
        }
        .batch-card {
            transition: transform 0.2s;
        }
        .batch-card:hover {
            transform: translateY(-2px);
        }
    </style>
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
                            <a class="nav-link" href="redeem_batch.php">Batch Redemption</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="batch_history.php">Batch History</a>
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
                    <i class="bi bi-archive me-2"></i>
                    Batch History
                </h2>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel me-2"></i>
                    Filters
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Batch #, vendor...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" id="dateFrom" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" id="dateTo" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button id="applyFilters" class="btn btn-primary me-2">
                            <i class="bi bi-funnel-fill me-1"></i> Apply
                        </button>
                        <button id="clearFilters" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i> Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batches Table Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-ul me-2"></i>
                    Batches
                </h5>
                <span class="badge bg-secondary" id="totalBatches">0 total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Batch Number</th>
                                <th>Vendor</th>
                                <th>Vouchers</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>By</th>
                                <th width="180">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="batchesTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Loading batches...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <nav aria-label="Batch pagination">
                    <ul class="pagination justify-content-center mb-0" id="pagination">
                        <!-- Pagination will be loaded here -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Batch Details Modal -->
    <div class="modal fade" id="batchDetailsModal" tabindex="-1" aria-labelledby="batchDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="batchDetailsModalLabel">Batch Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="batchDetailsContent">
                    <!-- Content loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="exportPdfBtn" href="#" class="btn btn-danger" target="_blank">
                        <i class="bi bi-file-pdf me-1"></i> PDF
                    </a>
                    <a id="exportExcelBtn" href="#" class="btn btn-success" target="_blank">
                        <i class="bi bi-file-excel me-1"></i> Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this batch?</p>
                    <p class="text-danger mb-0">This will reverse all voucher redemptions in this batch.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="button" id="confirmCancelBtn" class="btn btn-warning">
                        <i class="bi bi-x-circle me-1"></i> Yes, Cancel Batch
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/batch_history.js"></script>
</body>
</html>