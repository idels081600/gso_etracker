<?php
require_once __DIR__ . '/auth_guard.php';
requireFuelRole('gas_checker');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gas Checker</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="fuel_dashboard.css">
    <link rel="stylesheet" href="gas_checker.css">
</head>

<body>
    <!-- Main Content -->
    <div class="container-fluid checker-shell mt-4">
        <div class="checker-page-header mb-4">
            <div class="checker-title-wrap">
                <span class="checker-title-icon">
                    <i class="fas fa-clipboard-check"></i>
                </span>
                <div>
                    <h1 class="mb-1">Gas Checker</h1>
                    <p class="text-muted mb-0">Record actual fuel-up data and driver e-signatures for gas issuance records</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary checker-reset-btn" onclick="resetAll()" data-bs-toggle="tooltip" title="Reset all fields">
                    <i class="fas fa-undo me-1"></i>Reset
                </button>
                <a href="../logout.php" class="btn btn-outline-danger checker-reset-btn">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-item active" id="step1">
                <div class="step-number">1</div>
                <span class="step-label">Look Up Issuance</span>
            </div>
            <div class="step-connector"></div>
            <div class="step-item" id="step2">
                <div class="step-number">2</div>
                <span class="step-label">Select Vehicle</span>
            </div>
            <div class="step-connector"></div>
            <div class="step-item" id="step3">
                <div class="step-number">3</div>
                <span class="step-label">Input & Sign</span>
            </div>
        </div>

        <!-- Step 1: Search Section -->
        <div class="checker-search-section shadow">
            <div class="row align-items-end">
                <div class="col-12">
                    <label for="issuanceIdSearch" class="form-label">
                        <i class="fas fa-search me-1"></i>Enter Gas Issuance ID to Verify
                    </label>
                    <div class="checker-search-control">
                        <div class="input-group input-group-lg checker-id-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-receipt text-primary"></i>
                            </span>
                            <input type="text" class="form-control form-control-lg" id="issuanceIdSearch"
                                placeholder="e.g. FI-20260515-ABC123" aria-label="Gas Issuance ID">
                        </div>
                        <div class="checker-search-actions">
                            <button class="btn btn-primary btn-lg" id="searchIssuanceBtn" onclick="searchGasIssuance()">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                            <button class="btn btn-light btn-lg" type="button" id="scanQrBtn" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                                <i class="fas fa-qrcode me-1"></i>Scan QR
                            </button>
                        </div>
                    </div>
                    <small class="text-white-50 mt-2 d-block">
                        <i class="fas fa-info-circle me-1"></i>
                        Use the serial number shown in the Gas Issuance table, for example FI-20260515-ABC123.
                    </small>
                </div>
            </div>
        </div>

        <!-- Step 1 Result: Issuance Details -->
        <div class="vehicle-details-card" id="issuanceDetails">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-invoice text-primary me-2"></i>Issuance Record Found
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 issuance-detail-grid">
                        <div class="col-sm-6 col-xl-3">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-hashtag me-1"></i>Issuance ID
                                </small>
                                <span class="detail-value" id="detailIssuanceId">--</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-calendar me-1"></i>Date Issued
                                </small>
                                <span class="detail-value" id="detailDate">--</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-building me-1"></i>Office
                                </small>
                                <span class="detail-value" id="detailOffice">--</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-gas-pump me-1"></i>Fuel Type
                                </small>
                                <span id="detailFuelType" class="badge bg-secondary badge-fuel-type">--</span>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 issuance-detail-grid">
                        <div class="col-md-6 col-xl-4">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-car me-1"></i>Vehicle
                                </small>
                                <span class="detail-value" id="detailVehicle">--</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-user me-1"></i>Assigned Driver
                                </small>
                                <span class="detail-value" id="detailDriver">--</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-2">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-tint me-1"></i>Liters Issued
                                </small>
                                <span class="detail-value fw-bold text-primary" id="detailLitersIssued">--</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-2">
                            <div class="detail-item">
                                <small class="detail-label d-block">
                                    <i class="fas fa-check-circle me-1"></i>Status
                                </small>
                                <span class="badge bg-success fs-6 px-3 py-2" id="detailStatus">--</span>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 issuance-detail-grid">
                        <div class="col-12">
                            <div class="detail-item detail-item-purpose">
                                <small class="detail-label d-block">
                                    <i class="fas fa-map-marker-alt me-1"></i>Purpose
                                </small>
                                <span class="detail-value" id="detailPurpose">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Vehicle Selection -->
        <div class="d-none" id="vehicleListSection">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-car text-success me-2"></i>Vehicle to Check
                        </h5>
                        <span class="results-count" id="vehicleCount">
                            <i class="fas fa-chevron-right text-muted me-1"></i>Click vehicle to proceed
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="vehicleList">
                        <!-- Vehicle items dynamically added -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Checker Form (Input Actual Data & E-Signature) -->
        <div class="checker-form-section" id="checkerFormSection">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-signature me-2"></i>Input Actual Fuel-Up Data & E-Signature
                    </h5>
                </div>
                <div class="card-body">
                    <form id="checkerForm" novalidate>
                        <!-- Reference Info (Read Only) -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="issued-gas-info">
                                    <h6 class="fw-bold mb-3">
                                        <i class="fas fa-file-invoice me-2"></i>Issued Gas Reference
                                    </h6>
                                    <div class="gas-item">
                                        <span>Vehicle</span>
                                        <strong id="checkVehicle">--</strong>
                                    </div>
                                    <div class="gas-item">
                                        <span>Fuel Type Issued</span>
                                        <strong id="checkFuelType">--</strong>
                                    </div>
                                    <div class="gas-item">
                                        <span>Liters Issued</span>
                                        <strong id="checkLitersIssued">--</strong>
                                    </div>
                                    <div class="gas-item">
                                        <span>Issuance Reference #</span>
                                        <strong id="checkIssuanceRef">--</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Actual Input Fields -->
                        <h6 class="fw-bold mb-3 text-primary">
                            <i class="fas fa-pen me-2"></i>Enter Actual Fuel-Up Information
                        </h6>
                        <div class="row">
                            <!-- Latest Odometer -->
                            <div class="col-md-4 mb-3">
                                <label for="checkOdometer" class="form-label">
                                    <i class="fas fa-tachometer-alt me-1"></i>Latest Odometer Reading
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="checkOdometer" 
                                        name="odometer" placeholder="Enter odometer reading"
                                        min="0" step="1" required>
                                    <span class="input-group-text">km</span>
                                    <div class="invalid-feedback">
                                        Please enter the latest odometer reading.
                                    </div>
                                </div>
                            </div>

                            <!-- Driver Name -->
                            <div class="col-md-4 mb-3">
                                <label for="checkDriver" class="form-label">
                                    <i class="fas fa-user me-1"></i>Driver Name
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="checkDriver" 
                                    name="driver" placeholder="e.g. Juan Dela Cruz"
                                    required>
                                <div class="invalid-feedback">
                                    Please enter the driver's name.
                                </div>
                            </div>

                            <!-- Actual Fueled Up -->
                            <div class="col-md-4 mb-3">
                                <label for="checkActual" class="form-label">
                                    <i class="fas fa-tint me-1"></i>Actual Fueled Up
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="checkActual" 
                                        name="actual_fuel" placeholder="Enter actual liters"
                                        min="0" step="0.01" required>
                                    <span class="input-group-text">L</span>
                                    <div class="invalid-feedback">
                                        Please enter the actual fueled up amount.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- E-Signature -->
                        <div class="row mt-2">
                            <div class="col-12">
                                <label for="checkSignaturePad" class="form-label">
                                    <i class="fas fa-signature me-1"></i>Driver E-Signature
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="signature-pad-wrap">
                                    <canvas id="checkSignaturePad" class="signature-pad" width="900" height="220" aria-label="Driver e-signature pad"></canvas>
                                    <input type="hidden" id="checkSignature" name="signature" required>
                                    <div class="signature-placeholder" id="signaturePlaceholder">Sign here</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">Use mouse or touch to sign before submitting.</small>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSignatureBtn" onclick="clearSignaturePad()">
                                        <i class="fas fa-eraser me-1"></i>Clear Signature
                                    </button>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="signatureFeedback">
                                    Please provide the driver's e-signature.
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-lg px-5" id="submitCheckBtn" onclick="submitCheck()">
                                        <i class="fas fa-file-signature me-1"></i>Submit E-Signature
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg" onclick="resetAll()">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Check Result -->
                        <div id="checkResult"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="qrScannerModalLabel">
                        <i class="fas fa-qrcode me-2"></i>Scan Gas Issuance QR
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="qr-scanner-frame">
                        <video id="qrScannerVideo" class="qr-scanner-video" autoplay muted playsinline></video>
                        <div class="qr-scanner-target" aria-hidden="true"></div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0" id="qrScannerStatus">
                        Point the camera at the gas issuance QR code.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="restartQrScannerBtn">
                        <i class="fas fa-camera me-1"></i>Restart Camera
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="gas_checker.js"></script>
</body>

</html>
