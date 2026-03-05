<?php
// Security: Start session and check authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page if not authenticated
    header("Location: ../login_v2.php");
    exit();
}

// Get user information from session
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Unknown';
$full_name = isset($_SESSION['pay_name']) ? htmlspecialchars($_SESSION['pay_name']) : 'Unknown';
$user_role = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Unknown';

// Security: Regenerate session ID to prevent session fixation
session_regenerate_id(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracking System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card .icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .incoming-card {
            border-left: 4px solid #0d6efd;
        }
        .outgoing-card {
            border-left: 4px solid #198754;
        }
        .pending-card {
            border-left: 4px solid #ffc107;
        }
        .processed-card {
            border-left: 4px solid #0dcaf0;
        }
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .tracking-timeline {
            position: relative;
            padding-left: 30px;
        }
        .tracking-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 15px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0d6efd;
            border: 2px solid #fff;
        }
        .timeline-item.completed::before {
            background-color: #198754;
        }
        .timeline-item.pending::before {
            background-color: #ffc107;
        }
        .barcode-scanner {
            background: linear-gradient(135deg, #36c000 0%, #12b300 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .barcode-scanner input {
            font-size: 1.5rem;
            padding: 15px 20px;
            text-align: center;
            letter-spacing: 3px;
        }
        .scan-btn {
            font-size: 1.2rem;
            padding: 15px 30px;
        }
        .type-btn {
            padding: 30px 50px;
            font-size: 1.3rem;
            border-radius: 15px;
            transition: all 0.3s;
        }
        .type-btn:hover {
            transform: scale(1.05);
        }
        .type-btn.incoming {
            background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
            border: none;
        }
        .type-btn.outgoing {
            background: linear-gradient(135deg, #198754 0%, #0f5132 100%);
            border: none;
        }
        .user-badge {
            background-color: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Navbar with White Theme -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-primary d-flex align-items-center" href="#">
                <img src="logo.png" alt="Logo" width="40" height="40" class="me-2">Document Tracking System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#trackModal">
                            <i class="bi bi-geo-alt me-1"></i>Track
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?php echo $full_name; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><span class="dropdown-item-text"><small class="text-muted">Role: <?php echo $user_role; ?></small></span></li>
                            <li><span class="dropdown-item-text"><small class="text-muted">User: <?php echo $username; ?></small></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Barcode Scanner Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="barcode-scanner text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-3"><i class="bi bi-upc-scan me-2"></i>Scan Document Barcode</h4>
                            <div class="input-group">
                                <input type="text" class="form-control" id="barcodeInput" placeholder="Scan or enter barcode number..." autofocus>
                                <button class="btn btn-light scan-btn" type="button" onclick="processBarcode()">
                                    <i class="bi bi-arrow-right-circle me-2"></i>Process
                                </button>
                            </div>
                            <small class="text-white-50 mt-2 d-block">Press Enter after scanning or click Process</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="bi bi-qr-code-scan" style="font-size: 5rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card shadow-sm incoming-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Incoming</h6>
                                <h3 class="mb-0" id="incomingCount">0</h3>
                            </div>
                            <div class="text-primary icon">
                                <i class="bi bi-box-arrow-in-down"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card shadow-sm outgoing-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Outgoing</h6>
                                <h3 class="mb-0" id="outgoingCount">0</h3>
                            </div>
                            <div class="text-success icon">
                                <i class="bi bi-box-arrow-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card shadow-sm pending-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Pending</h6>
                                <h3 class="mb-0" id="pendingCount">0</h3>
                            </div>
                            <div class="text-warning icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card shadow-sm processed-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Processed</h6>
                                <h3 class="mb-0" id="processedCount">0</h3>
                            </div>
                            <div class="text-info icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Tables -->
        <div class="row">
            <!-- Incoming Documents Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-box-arrow-in-down me-2"></i>Incoming Documents</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-container">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tracking #</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Date Received</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="incomingTableBody">
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outgoing Documents Table -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-box-arrow-up me-2"></i>Outgoing Documents</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-container">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tracking #</th>
                                        <th>Subject</th>
                                        <th>Destination</th>
                                        <th>Date Sent</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="outgoingTableBody">
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Select Document Type Modal -->
    <div class="modal fade" id="selectTypeModal" tabindex="-1" aria-labelledby="selectTypeModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="selectTypeModalLabel">
                        <i class="bi bi-upc-scan me-2"></i>Barcode: <span id="scannedBarcode" class="text-primary"></span>
                    </h5>
                </div>
                <div class="modal-body text-center py-5">
                    <h4 class="mb-4">Select Document Type</h4>
                    <p class="text-muted mb-4">Choose whether this document is incoming or outgoing</p>
                    <div class="d-flex justify-content-center gap-4 flex-wrap">
                        <button type="button" class="btn btn-primary type-btn incoming" onclick="selectDocumentType('incoming')">
                            <i class="bi bi-box-arrow-in-down d-block mb-2" style="font-size: 2rem;"></i>
                            INCOMING
                        </button>
                        <button type="button" class="btn btn-success type-btn outgoing" onclick="selectDocumentType('outgoing')">
                            <i class="bi bi-box-arrow-up d-block mb-2" style="font-size: 2rem;"></i>
                            OUTGOING
                        </button>
                        <button type="button" class="btn btn-warning type-btn" onclick="selectDocumentType('track')" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); border: none;">
                            <i class="bi bi-geo-alt d-block mb-2" style="font-size: 2rem;"></i>
                            TRACK
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="clearBarcodeInput()">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Incoming Document Modal -->
    <div class="modal fade" id="addIncomingModal" tabindex="-1" aria-labelledby="addIncomingModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addIncomingModalLabel"><i class="bi bi-box-arrow-in-down me-2"></i>Add Incoming Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="clearBarcodeInput()"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-upc-scan me-2"></i><strong>Barcode:</strong> <span id="incomingBarcode"></span>
                    </div>
                    <form id="addIncomingForm">
                        <input type="hidden" id="incomingBarcodeHidden">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="incomingSubject" class="form-label">Document Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="incomingSubject" required placeholder="Enter document subject">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="incomingType" class="form-label">Document Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="incomingType" required>
                                    <option value="">Select type...</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Memo">Memo</option>
                                    <option value="Report">Report</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Invoice">Invoice</option>
                                    <option value="Request">Request</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="incomingDate" class="form-label">Date Received <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="incomingDate" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="incomingStatus" class="form-label">Status</label>
                                <select class="form-select" id="incomingStatus">
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Processed">Processed</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="clearBarcodeInput()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveIncomingDocument()">
                        <i class="bi bi-save me-1"></i>Save Document
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Outgoing Document Modal -->
    <div class="modal fade" id="addOutgoingModal" tabindex="-1" aria-labelledby="addOutgoingModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addOutgoingModalLabel"><i class="bi bi-box-arrow-up me-2"></i>Add Outgoing Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="clearBarcodeInput()"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-upc-scan me-2"></i><strong>Barcode:</strong> <span id="outgoingBarcode"></span>
                    </div>
                    <form id="addOutgoingForm">
                        <input type="hidden" id="outgoingBarcodeHidden">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="outgoingSubject" class="form-label">Document Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="outgoingSubject" required placeholder="Enter document subject">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="outgoingType" class="form-label">Document Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="outgoingType" required>
                                    <option value="">Select type...</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Memo">Memo</option>
                                    <option value="Report">Report</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Invoice">Invoice</option>
                                    <option value="Request">Request</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="outgoingDestination" class="form-label">Destination/Office <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="outgoingDestination" required placeholder="Where is the document going?">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="outgoingStatus" class="form-label">Status</label>
                                <select class="form-select" id="outgoingStatus">
                                    <option value="Pending">Pending</option>
                                    <option value="In Transit">In Transit</option>
                                    <option value="Delivered">Delivered</option>
                                    <option value="Received">Received</option>
                                    <option value="Returned">Returned</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="outgoingReturnable" onchange="toggleReturnDays()">
                                    <label class="form-check-label" for="outgoingReturnable">
                                        <i class="bi bi-arrow-return-left me-1"></i>Document must return
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="returnDaysRow" style="display: none;">
                            <div class="col-md-6 mb-3">
                                <label for="outgoingReturnDays" class="form-label">Return After (Days)</label>
                                <input type="number" class="form-control" id="outgoingReturnDays" min="1" value="7" placeholder="Number of days">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expected Return Date</label>
                                <input type="text" class="form-control" id="expectedReturnDate" readonly placeholder="Auto-calculated">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="clearBarcodeInput()">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveOutgoingDocument()">
                        <i class="bi bi-save me-1"></i>Save Document
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Track Document Modal -->
    <div class="modal fade" id="trackModal" tabindex="-1" aria-labelledby="trackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="trackModalLabel"><i class="bi bi-geo-alt me-2"></i>Track Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="trackingNumber" class="form-label">Enter Tracking Number or Barcode</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="trackingNumber" placeholder="e.g., INC-2026-0001 or barcode number">
                            <button class="btn btn-primary" type="button" onclick="trackDocument()">
                                <i class="bi bi-search me-1"></i>Track
                            </button>
                        </div>
                    </div>
                    <div id="trackingResult" style="display: none;">
                        <hr>
                        <h6 class="mb-3">Document Tracking History</h6>
                        <div class="tracking-timeline" id="trackingTimeline">
                            <!-- Timeline will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Document Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel"><i class="bi bi-file-text me-2"></i>Document Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <!-- Document details will be shown here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Existing Barcode Options Modal -->
    <div class="modal fade" id="existingBarcodeModal" tabindex="-1" aria-labelledby="existingBarcodeModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="existingBarcodeModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Barcode Already Registered
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-upc-scan me-2"></i><strong>Barcode:</strong> <span id="existingBarcodeDisplay"></span>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <strong><i class="bi bi-file-text me-2"></i>Document Information</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Tracking #:</strong> <span class="badge bg-primary" id="existingTrackingNo"></span></p>
                                    <p class="mb-2"><strong>Description:</strong> <span id="existingDescription"></span></p>
                                    <p class="mb-2"><strong>Document Type:</strong> <span id="existingDocType"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Status:</strong> <span id="existingStatus"></span></p>
                                    <p class="mb-2"><strong>Direction:</strong> <span id="existingDirection"></span></p>
                                    <p class="mb-2"><strong>Date Received:</strong> <span id="existingDateReceived"></span></p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6" id="existingDestinationRow" style="display: none;">
                                    <p class="mb-2"><strong>Destination:</strong> <span id="existingDestination"></span></p>
                                </div>
                                <div class="col-md-6" id="existingDeadlineRow" style="display: none;">
                                    <p class="mb-2"><strong>Return Deadline:</strong> <span id="existingDeadline"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center py-3">
                        <h5 class="mb-3">What would you like to do?</h5>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <button type="button" class="btn btn-primary btn-lg" onclick="trackExistingDocument()">
                                <i class="bi bi-geo-alt me-2"></i>Track Document
                            </button>
                            <button type="button" class="btn btn-success btn-lg" onclick="showUpdateDocumentForm()">
                                <i class="bi bi-pencil-square me-2"></i>Update Document
                            </button>
                            <button type="button" class="btn btn-warning btn-lg" onclick="showOutgoingForm()">
                                <i class="bi bi-box-arrow-up me-2"></i>Mark as Outgoing
                            </button>
                            <button type="button" class="btn btn-danger btn-lg" id="markReturnedBtn" onclick="markDocumentAsReturned()" style="display: none;">
                                <i class="bi bi-arrow-return-left me-2"></i>Mark as Returned
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="clearBarcodeInput()">
                        <i class="bi bi-x-lg me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark as Outgoing Modal -->
    <div class="modal fade" id="markAsOutgoingModal" tabindex="-1" aria-labelledby="markAsOutgoingModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="markAsOutgoingModalLabel"><i class="bi bi-box-arrow-up me-2"></i>Mark Document as Outgoing</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <div class="row">
                            <div class="col-6">
                                <strong>Tracking #:</strong> <span id="markOutgoingTrackingNo"></span>
                            </div>
                            <div class="col-6">
                                <strong>Barcode:</strong> <span id="markOutgoingBarcode"></span>
                            </div>
                        </div>
                    </div>
                    <form id="markAsOutgoingForm">
                        <input type="hidden" id="markOutgoingDocId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="markOutgoingDescription" class="form-label">Description</label>
                                <input type="text" class="form-control" id="markOutgoingDescription" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="markOutgoingDocType" class="form-label">Document Type</label>
                                <input type="text" class="form-control" id="markOutgoingDocType" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="markOutgoingDestination" class="form-label">Destination/Office <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="markOutgoingDestination" required placeholder="Where is the document going?">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="markOutgoingStatus" class="form-label">Status</label>
                                <select class="form-select" id="markOutgoingStatus">
                                    <option value="In Transit">In Transit</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Delivered">Delivered</option>
                                    <option value="Received">Received</option>
                                    <option value="Returned">Returned</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="markOutgoingReturnable" onchange="toggleMarkOutgoingReturnDays()">
                                    <label class="form-check-label" for="markOutgoingReturnable">
                                        <i class="bi bi-arrow-return-left me-1"></i>Document must return
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="markOutgoingDeadlineRow" style="display: none;">
                            <div class="col-md-6 mb-3">
                                <label for="markOutgoingReturnDays" class="form-label">Return After (Days)</label>
                                <input type="number" class="form-control" id="markOutgoingReturnDays" min="1" value="7" placeholder="Number of days" oninput="updateMarkOutgoingDeadline()">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="markOutgoingDateDeadline" class="form-label">Return Deadline</label>
                                <input type="date" class="form-control" id="markOutgoingDateDeadline">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="markDocumentAsOutgoing()">
                        <i class="bi bi-check-circle me-1"></i>Mark as Outgoing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Document Modal -->
    <div class="modal fade" id="updateDocumentModal" tabindex="-1" aria-labelledby="updateDocumentModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="updateDocumentModalLabel"><i class="bi bi-pencil-square me-2"></i>Update Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateDocumentForm">
                        <input type="hidden" id="updateDocId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="updateDescription" class="form-label">Description <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="updateDescription" required placeholder="Enter document description">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="updateDocType" class="form-label">Document Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="updateDocType" required>
                                    <option value="">Select type...</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Memo">Memo</option>
                                    <option value="Report">Report</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Invoice">Invoice</option>
                                    <option value="Request">Request</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="updateDateReceived" class="form-label">Date Received <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="updateDateReceived" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="updateStatus" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="updateStatus" required>
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Processed">Processed</option>
                                    <option value="In Transit">In Transit</option>
                                    <option value="Delivered">Delivered</option>
                                    <option value="Received">Received</option>
                                    <option value="Returned">Returned</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="updateDateReleased" class="form-label">Date Released</label>
                                <input type="date" class="form-control" id="updateDateReleased" placeholder="Date document was released">
                            </div>
                            <div class="col-md-6 mb-3" id="updateDestinationRow" style="display: none;">
                                <label for="updateDestination" class="form-label">Destination</label>
                                <input type="text" class="form-control" id="updateDestination" placeholder="Document destination">
                            </div>
                            <div class="col-md-6 mb-3" id="updateDeadlineRow" style="display: none;">
                                <label for="updateDateDeadline" class="form-label">Return Deadline</label>
                                <input type="date" class="form-control" id="updateDateDeadline" placeholder="Expected return date">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="updateExistingDocument()">
                        <i class="bi bi-save me-1"></i>Update Document
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="dashboard.js"></script>
</body>
</html>