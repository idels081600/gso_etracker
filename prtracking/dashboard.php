<?php
// Start session at the very beginning
session_start();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    // User is not logged in, redirect to login page
    header("Location: ../login_v2.php");
    exit();
}

// Check if user has the correct role for this page
if ($_SESSION['role'] !== 'pr_admin') {
    // User doesn't have the right role, redirect to login
    header("Location: ../login_v2.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        body {
            min-height: 100vh;
        }

        .dropdown-toggle {
            outline: 0;
        }

        .nav-link {
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            color: #333;
        }

        .nav-link:hover {
            background-color: #f8f9fa;
            color: #007bff;
            text-decoration: none;
        }

        .nav-link.active {
            background-color: #e9ecef;
            color: #007bff;
            font-weight: 500;
        }

        .dropdown-menu {
            border: 1px solid #dee2e6;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        /* Sidebar styles */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease-in-out;
        }

        .content {
            margin-left: 280px;
            transition: margin-left 0.3s ease-in-out;
        }

        /* Dark mode styles */
        body.dark-mode {
            background-color: #121212;
            color: #ffffff;
        }

        body.dark-mode .sidebar {
            background-color: #1e1e1e;
            border-right: 1px solid #333;
        }

        body.dark-mode .sidebar .nav-link {
            color: #ffffff;
        }

        body.dark-mode .sidebar .nav-link:hover {
            background-color: #333;
            color: #007bff;
        }

        body.dark-mode .sidebar .nav-link.active {
            background-color: #333;
            color: #007bff;
        }

        body.dark-mode .sidebar .dropdown-menu {
            background-color: #1e1e1e;
            border: 1px solid #333;
            color: #ffffff;
        }

        body.dark-mode .sidebar .dropdown-menu .dropdown-item {
            color: #ffffff;
        }

        body.dark-mode .sidebar .dropdown-menu .dropdown-item:hover {
            background-color: #333;
        }

        body.dark-mode .main-header {
            background-color: #1e1e1e;
            color: #ffffff;
            border-bottom: 1px solid #333;
        }

        body.dark-mode .toggle-btn {
            color: #ffffff;
        }

        body.dark-mode .toggle-btn:hover {
            background-color: #333;
        }

        body.dark-mode .content {
            background-color: #121212;
            color: #ffffff;
        }

        body.dark-mode .sidebar-overlay {
            background-color: rgba(0, 0, 0, 0.5);
        }

        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                position: fixed;
                top: 60px;
                /* Below mobile header */
                height: calc(100vh - 60px);
                z-index: 1000;
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                padding: 10px;
                transition: margin-left 0.3s ease-in-out;
            }

            .content.shifted {
                margin-left: 280px;
            }

            .navbar-toggler {
                display: block;
            }

            .main-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px;
                background-color: #f8f9fa;
                color: #333;
                border-bottom: 1px solid #dee2e6;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 999;
                height: 60px;
            }

            body.dark-mode .main-header {
                background-color: #1e1e1e;
                color: #ffffff;
                border-bottom: 1px solid #333;
            }

            /* Toggle button styling */
            .toggle-btn {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0 15px;
                transition: background-color 0.3s ease;
            }

            .toggle-btn:hover {
                background-color: rgba(255, 255, 255, 0.1);
                border-radius: 4px;
            }

            .toggle-btn.active {
                background-color: rgba(255, 255, 255, 0.2);
            }

            /* Modal-style overlay for closing sidebar when clicking outside */
            .sidebar-overlay {
                position: fixed;
                top: 60px;
                /* Below mobile header */
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
                display: none;
                cursor: pointer;
                backdrop-filter: blur(2px);
            }

            .sidebar.show+.sidebar-overlay {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .navbar-toggler {
                display: none;
            }

            .desktop-header {
                display: flex !important;
            }

            body.dark-mode .desktop-header {
                background-color: #1e1e1e;
                color: #ffffff;
                border-bottom: 1px solid #333;
            }
        }
        .form-label{
            font-weight: 500;
        }
    </style>
</head>

<body>
    <!-- Mobile Header -->
    <div class="main-header d-none">
        <button class="toggle-btn" id="sidebarToggle" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
        <h4 class="mb-0">Admin Panel</h4>
        <div class="dark-mode-toggle">
            <button class="btn btn-outline-secondary btn-sm" id="darkModeToggle" type="button" aria-label="Toggle dark mode">
                <i class="fas fa-moon" id="darkModeIcon"></i>
            </button>
        </div>
    </div>
    <!-- Desktop Header -->

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column flex-shrink-0 p-3 bg-white border-end" id="sidebar">
        <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
            <img src="logo.png" alt="Logo" width="40" height="40" class="me-2">
            <span class="fs-4">Admin Panel</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="dashboard_asset_tracker.php" class="nav-link">
                    <i class="fas fa-home me-2"></i> Dashboard
                </a>
            </li>
        </ul>
        <hr>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="logo.png" alt="" width="32" height="32" class="rounded-circle me-2">
                <strong>CSGO</strong>
            </a>
            <ul class="dropdown-menu text-small shadow">
                <li><a class="dropdown-item" href="#">Settings</a></li>
                <li><a class="dropdown-item" href="#">Profile</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center" href="#" id="darkModeDropdownToggle">
                        <i class="fas fa-moon me-2" id="darkModeDropdownIcon"></i>
                        <span id="darkModeDropdownText">Enable Dark Mode</span>
                    </a>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
            </ul>
        </div>
    </div>

    <!-- Sidebar Overlay for closing when clicking outside -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="content" id="content">
        <!-- Your main content goes here -->
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1> CGSO Pr tracking </h1>
                </div>
            </div>

            <!-- Status Cards Row -->
            <div class="row mt-4">
                <!-- Delivered Section (based on Delivery Status) -->
                <div class="col-md-3 mb-4">
                    <div class="card h-100 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                Delivered
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success fs-4" id="deliveredCount">0</span>
                                <span class="text-muted">Total Requests</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ongoing Section (based on PR Status) -->
                <div class="col-md-3 mb-4">
                    <div class="card h-100 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-sync-alt me-2"></i>
                                Ongoing
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-warning fs-4" id="ongoingCount">0</span>
                                <span class="text-muted">Total Requests</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Declined Section (based on PR Status) -->
                <div class="col-md-3 mb-4">
                    <div class="card h-100 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-times-circle me-2"></i>
                                Declined
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-danger fs-4" id="declinedCount">0</span>
                                <span class="text-muted">Total Requests</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PR to PO Section (based on PR Status) -->
                <div class="col-md-3 mb-4">
                    <div class="card h-100 border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shopping-cart me-2"></i>
                                PR to PO
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-info fs-4" id="prToPoCount">0</span>
                                <span class="text-muted">Total Requests</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Additional Content Area -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-subtitle text-muted mb-0">Table Overview</h6>
                                <div class="d-flex gap-2">
                                    <div class="input-group" style="min-width: 250px;">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input type="text" class="form-control" id="tableSearch" placeholder="Search projects, dates, status...">
                                    </div>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDataModal">
                                        <i class="fas fa-plus me-1"></i>
                                        Add Data
                                    </button>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editDataModal">
                                        <i class="fas fa-edit me-1"></i>
                                        Edit Data
                                    </button>
                                    <button class="btn btn-danger btn-sm" id="deleteSelectedBtn">
                                        <i class="fas fa-trash me-1"></i>
                                        Delete Selected
                                    </button>
                                </div>
                            </div>
                            <table class="table" id="ppmpTable">
                                <thead>
                                    <tr>
                                        <th scope="col">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                                <label class="form-check-label" for="selectAll">
                                                </label>
                                            </div>
                                        </th>
                                        <th scope="col">PR no.</th>
                                        <th scope="col">PO no.</th>
                                        <th scope="col">Project</th>
                                        <th scope="col">Start of Procurement</th>
                                        <th scope="col">End Of Procurement</th>
                                        <th scope="col">Expected Delivery</th>
                                        <th scope="col">Amount</th>
                                        <th scope="col">Delivery Status</th>
                            <th scope="col">PR Status</th>
                            <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="ppmpTableBody">
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Data Modal -->
    <div class="modal fade" id="editDataModal" tabindex="-1" aria-labelledby="editDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDataModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Project Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editDataForm">
                        <input type="hidden" id="editRecordId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editPrNo" class="form-label">PR no.</label>
                                <input type="text" class="form-control" id="editPrNo" placeholder="Enter PR number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editPoNo" class="form-label">PO no.</label>
                                <input type="text" class="form-control" id="editPoNo" placeholder="Enter PO number" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editProjectName" class="form-label">Project</label>
                                <input type="text" class="form-control" id="editProjectName" placeholder="Enter project name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editStartProcurement" class="form-label">Start of Procurement</label>
                                <input type="date" class="form-control" id="editStartProcurement" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editEndProcurement" class="form-label">End of Procurement</label>
                                <input type="date" class="form-control" id="editEndProcurement" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editExpectedDelivery" class="form-label">Expected Delivery</label>
                                <input type="date" class="form-control" id="editExpectedDelivery" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editAmount" class="form-label">Amount</label>
                                <input type="number" step="0.01" class="form-control" id="editAmount" placeholder="Enter amount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editPrStatus" class="form-label">PR Status</label>
                                <select class="form-select" id="editPrStatus" required>
                                    <option value="">Select status</option>
                                    <option value="pending">Pending</option>
                                    <option value="PO">PO</option>
                                    <option value="declined">Declined</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PRE CGSO Status</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editSubmitted" value="Submitted">
                                    <label class="form-check-label" for="editSubmitted">Submitted</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editApproved" value="Approved">
                                    <label class="form-check-label" for="editApproved">Approved</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editDeclined" value="Declined">
                                    <label class="form-check-label" for="editDeclined">Declined</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editBacDeclined" value="BAC Declined">
                                    <label class="form-check-label" for="editBacDeclined">BAC Declined</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">BAC Status</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editBacSubmitted" value="Submitted">
                                    <label class="form-check-label" for="editBacSubmitted">Submitted</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editCategorized" value="Categorized">
                                    <label class="form-check-label" for="editCategorized">Categorized</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editPosted" value="Posted">
                                    <label class="form-check-label" for="editPosted">Posted</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editBidding" value="Bidding">
                                    <label class="form-check-label" for="editBidding">Bidding</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">POST GSO Status</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editAwarded" value="Awarded">
                                    <label class="form-check-label" for="editAwarded">Awarded</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editPoApproved" value="PO Approved">
                                    <label class="form-check-label" for="editPoApproved">PO Approved</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editDeliveryStatus" class="form-label ">Delivery Status</label>
                                <select class="form-select" id="editDeliveryStatus" required>
                                    <option value="">Select status</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_transit">In Transit</option>
                                    <option value="delivered">Delivered</option>
                                </select>
                            </div>

                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateDataBtn">
                        <i class="fas fa-save me-2"></i>Update Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Data Modal -->
    <div class="modal fade" id="addDataModal" tabindex="-1" aria-labelledby="addDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDataModalLabel">
                        <i class="fas fa-plus me-2"></i>Add New Project Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDataForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="projectName" class="form-label ">Project</label>
                                <input type="text" class="form-control" id="projectName" placeholder="Enter project name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="startProcurement" class="form-label">Start of Procurement</label>
                                <input type="date" class="form-control" id="startProcurement" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="endProcurement" class="form-label">End of Procurement</label>
                                <input type="date" class="form-control" id="endProcurement" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expectedDelivery" class="form-label">Expected Delivery</label>
                                <input type="date" class="form-control" id="expectedDelivery" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prStatus" class="form-label">PR Status</label>
                                <select class="form-select" id="prStatus" required>
                                    <option value="">Select status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="declined">Declined</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                        <!-- <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="deliveryStatus" class="form-label">Delivery Status</label>
                                <select class="form-select" id="deliveryStatus" required>
                                    <option value="">Select status</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_transit">In Transit</option>
                                    <option value="delivered">Delivered</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prProgress" class="form-label">PR Progress (%)</label>
                                <input type="range" class="form-range" id="prProgress" min="0" max="100" value="0" step="5">
                                <div class="d-flex justify-content-between text-muted">
                                    <small>0%</small>
                                    <small id="progressValue">0%</small>
                                    <small>100%</small>
                                </div>
                            </div>
                        </div> -->
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveDataBtn">
                        <i class="fas fa-save me-2"></i>Save Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="dashboard.js"></script>
</body>

</html>