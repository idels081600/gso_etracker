<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Dashboard</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="fuel_dashboard.css">
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <!-- Brand/Logo -->
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-gas-pump text-primary me-2"></i>
                <span class="fw-bold text-dark">Fuel Tracker</span>
            </a>

            <!-- Mobile toggle button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addFuelRecordModal">
                            <i class="fas fa-plus-circle me-1"></i>Add Fuel Record
                        </a>
                    </li>
                </ul>

                <!-- Right side items -->
                <div class="d-flex align-items-center">

                    <!-- User dropdown -->
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="../logo.png" alt="User" class="rounded-circle me-2" width="32" height="32">
                            <span class="text-dark"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Add Fuel Record Modal -->
    <div class="modal fade" id="addFuelRecordModal" tabindex="-1" aria-labelledby="addFuelRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addFuelRecordModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add Fuel Record
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addFuelRecordForm">
                        <div class="row">
                            <!-- Date -->
                            <div class="col-md-6 mb-3">
                                <label for="fuelDate" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>Date
                                </label>
                                <input type="date" class="form-control" id="fuelDate" name="fuel_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <!-- Office -->
                            <div class="col-md-6 mb-3">
                                <label for="office" class="form-label">
                                    <i class="fas fa-building me-1"></i>Office
                                </label>
                                <select class="form-select" id="office" name="office">
                                    <option value="">Select Office</option>
                                    <option value="ADMIN">ADMIN</option>
                                    <option value="ALERT">ALERT</option>
                                    <option value="BFP">BFP</option>
                                    <option value="BJMP">BJMP</option>
                                    <option value="BPLO">BPLO</option>
                                    <option value="CASSO">CASSO</option>
                                    <option value="CAVI">CAVI</option>
                                    <option value="CAVO">CAVO</option>
                                    <option value="CDRRMO">CDRRMO</option>
                                    <option value="CEE">CEE</option>
                                    <option value="CEO">CEO</option>
                                    <option value="CGSO">CGSO</option>
                                    <option value="CHO">CHO</option>
                                    <option value="CITY ADMIN">CITY ADMIN</option>
                                    <option value="CMO">CMO</option>
                                    <option value="CSWD">CSWD</option>
                                    <option value="CTMO">CTMO</option>
                                    <option value="CTO">CTO</option>
                                    <option value="CVMO">CVMO</option>
                                    <option value="DILG">DILG</option>
                                    <option value="HRMO">HRMO</option>
                                    <option value="OSCA">OSCA</option>
                                    <option value="PDAO">PDAO</option>
                                    <option value="PNP">PNP</option>
                                    <option value="SP">SP</option>
                                    <option value="TCWS">TCWS</option>
                                    <option value="SWMO">SWMO</option>
                                </select>
                            </div>

                            <!-- Vehicle -->
                            <div class="col-md-6 mb-3">
                                <label for="vehicle" class="form-label">
                                    <i class="fas fa-car me-1"></i>Vehicle
                                </label>
                                <input type="text" class="form-control" id="vehicle" name="vehicle" placeholder="e.g. Toyota Hiace, Isuzu Truck">
                            </div>


                            <!-- Plate Number -->
                            <div class="col-md-6 mb-3">
                                <label for="plateNo" class="form-label">
                                    <i class="fas fa-id-card me-1"></i>Plate Number
                                </label>
                                <input type="text" class="form-control" id="plateNo" name="plate_no" placeholder="e.g. ABC-1234">
                            </div>

                            <!-- Driver -->
                            <div class="col-md-6 mb-3">
                                <label for="driver" class="form-label">
                                    <i class="fas fa-user me-1"></i>Driver
                                </label>
                                <input type="text" class="form-control" id="driver" name="driver" placeholder="Driver's Name">
                            </div>

                            <!-- Purpose/Destination -->
                            <div class="col-md-6 mb-3">
                                <label for="purpose" class="form-label">
                                    <i class="fas fa-map-marker-alt me-1"></i>Purpose/Destination
                                </label>
                                <input type="text" class="form-control" id="purpose" name="purpose" placeholder="e.g. Official Business - City Hall">
                            </div>

                            <!-- Fuel Type -->
                            <div class="col-md-6 mb-3">
                                <label for="fuelType" class="form-label">
                                    <i class="fas fa-gas-pump me-1"></i>Fuel Type
                                </label>
                                <select class="form-select" id="fuelType" name="fuel_type">
                                    <option value="">Select Fuel Type</option>
                                    <option value="Unleaded">Unleaded</option>
                                    <option value="Diesel">Diesel</option>
                                </select>
                            </div>

                            <!-- Liters Issued -->
                            <div class="col-md-6 mb-3">
                                <label for="litersIssued" class="form-label">
                                    <i class="fas fa-tint me-1"></i>Liters Issued
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="litersIssued" name="liters_issued"
                                        placeholder="0.00" step="0.01" min="0">
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>

                            <!-- Remarks (Optional) -->
                            <div class="col-12 mb-3">
                                <label for="remarks" class="form-label">
                                    <i class="fas fa-comment me-1"></i>Remarks <small class="text-muted">(Optional)</small>
                                </label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"
                                    placeholder="Additional notes or comments..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="saveFuelRecord">
                        <i class="fas fa-save me-1"></i>Save Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Fuel Record Modal -->
    <div class="modal fade" id="editFuelRecordModal" tabindex="-1" aria-labelledby="editFuelRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editFuelRecordModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Fuel Record
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editFuelRecordForm">
                        <div class="row">
                            <!-- Date -->
                            <div class="col-md-6 mb-3">
                                <label for="editFuelDate" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>Date
                                </label>
                                <input type="date" class="form-control" id="editFuelDate" name="fuel_date">
                            </div>
                            <!-- Office -->
                            <div class="col-md-6 mb-3">
                                <label for="editOffice" class="form-label">
                                    <i class="fas fa-building me-1"></i>Office
                                </label>
                                <select class="form-select" id="editOffice" name="office">
                                    <option value="">Select Office</option>
                                    <option value="ADMIN">ADMIN</option>
                                    <option value="ALERT">ALERT</option>
                                    <option value="BFP">BFP</option>
                                    <option value="BJMP">BJMP</option>
                                    <option value="BPLO">BPLO</option>
                                    <option value="CASSO">CASSO</option>
                                    <option value="CAVI">CAVI</option>
                                    <option value="CAVO">CAVO</option>
                                    <option value="CDRRMO">CDRRMO</option>
                                    <option value="CEE">CEE</option>
                                    <option value="CEO">CEO</option>
                                    <option value="CGSO">CGSO</option>
                                    <option value="CHO">CHO</option>
                                    <option value="CITY ADMIN">CITY ADMIN</option>
                                    <option value="CMO">CMO</option>
                                    <option value="CSWD">CSWD</option>
                                    <option value="CTMO">CTMO</option>
                                    <option value="CTO">CTO</option>
                                    <option value="CVMO">CVMO</option>
                                    <option value="DILG">DILG</option>
                                    <option value="HRMO">HRMO</option>
                                    <option value="OSCA">OSCA</option>
                                    <option value="PDAO">PDAO</option>
                                    <option value="PNP">PNP</option>
                                    <option value="SP">SP</option>
                                    <option value="TCWS">TCWS</option>
                                    <option value="SWMO">SWMO</option>
                                </select>
                            </div>
                            <!-- Vehicle -->
                            <div class="col-md-6 mb-3">
                                <label for="editVehicle" class="form-label">
                                    <i class="fas fa-car me-1"></i>Vehicle
                                </label>
                                <input type="text" class="form-control" id="editVehicle" name="vehicle" placeholder="e.g. Toyota Hiace, Isuzu Truck">
                            </div>
                            <!-- Plate Number -->
                            <div class="col-md-6 mb-3">
                                <label for="editPlateNo" class="form-label">
                                    <i class="fas fa-id-card me-1"></i>Plate Number
                                </label>
                                <input type="text" class="form-control" id="editPlateNo" name="plate_no" placeholder="e.g. ABC-1234">
                            </div>
                            <!-- Driver -->
                            <div class="col-md-6 mb-3">
                                <label for="editDriver" class="form-label">
                                    <i class="fas fa-user me-1"></i>Driver
                                </label>
                                <input type="text" class="form-control" id="editDriver" name="driver" placeholder="Driver's Name">
                            </div>
                            <!-- Purpose/Destination -->
                            <div class="col-md-6 mb-3">
                                <label for="editPurpose" class="form-label">
                                    <i class="fas fa-map-marker-alt me-1"></i>Purpose/Destination
                                </label>
                                <input type="text" class="form-control" id="editPurpose" name="purpose" placeholder="e.g. Official Business - City Hall">
                            </div>
                            <!-- Fuel Type -->
                            <div class="col-md-6 mb-3">
                                <label for="editFuelType" class="form-label">
                                    <i class="fas fa-gas-pump me-1"></i>Fuel Type
                                </label>
                                <select class="form-select" id="editFuelType" name="fuel_type">
                                    <option value="">Select Fuel Type</option>
                                    <option value="Unleaded">Unleaded</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="Premium">Premium</option>
                                </select>
                            </div>
                            <!-- Liters Issued -->
                            <div class="col-md-6 mb-3">
                                <label for="editLitersIssued" class="form-label">
                                    <i class="fas fa-tint me-1"></i>Liters Issued
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="editLitersIssued" name="liters_issued" placeholder="0.00" step="0.01" min="0">
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                            <!-- Remarks (Optional) -->
                            <div class="col-12 mb-3">
                                <label for="editRemarks" class="form-label">
                                    <i class="fas fa-comment me-1"></i>Remarks <small class="text-muted">(Optional)</small>
                                </label>
                                <textarea class="form-control" id="editRemarks" name="remarks" rows="3" placeholder="Additional notes or comments..."></textarea>
                            </div>
                        </div>
                        <input type="hidden" id="editRecordId" name="id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-warning" id="updateFuelRecord">
                        <i class="fas fa-save me-1"></i>Update Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Fuel Record Modal -->
    <div class="modal fade" id="viewFuelRecordModal" tabindex="-1" aria-labelledby="viewFuelRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewFuelRecordModalLabel">
                        <i class="fas fa-eye me-2"></i>View Fuel Record
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8" id="viewFuelDate"></dd>
                        <dt class="col-sm-4">Office</dt>
                        <dd class="col-sm-8" id="viewOffice"></dd>
                        <dt class="col-sm-4">Vehicle</dt>
                        <dd class="col-sm-8" id="viewVehicle"></dd>
                        <dt class="col-sm-4">Plate No.</dt>
                        <dd class="col-sm-8" id="viewPlateNo"></dd>
                        <dt class="col-sm-4">Driver</dt>
                        <dd class="col-sm-8" id="viewDriver"></dd>
                        <dt class="col-sm-4">Purpose/Destination</dt>
                        <dd class="col-sm-8" id="viewPurpose"></dd>
                        <dt class="col-sm-4">Fuel Type</dt>
                        <dd class="col-sm-8" id="viewFuelType"></dd>
                        <dt class="col-sm-4">Liters Issued</dt>
                        <dd class="col-sm-8" id="viewLitersIssued"></dd>
                        <dt class="col-sm-4">Remarks</dt>
                        <dd class="col-sm-8" id="viewRemarks"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Gas Issuance</h1>
            </div>
        </div>

        <!-- Fuel Tally Cards -->
        <div class="row mb-4">
            <!-- Unleaded Card -->
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-gradient rounded-circle p-3">
                                    <i class="fas fa-gas-pump text-white fa-2x"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-muted mb-1">UNLEADED</h5>
                                        <h2 class="card-text fw-bold text-success mb-0" id="unleadedCount">0</h2>
                                        <small class="text-muted">Records Issued</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Liters</small>
                                    <span class="fw-bold text-success" id="unleadedLiters">0.00 L</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">This Month</small>
                                    <span class="fw-bold text-success" id="unleadedMonth">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Diesel Card -->
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning bg-gradient rounded-circle p-3">
                                    <i class="fas fa-truck text-white fa-2x"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-muted mb-1">DIESEL</h5>
                                        <h2 class="card-text fw-bold text-warning mb-0" id="dieselCount">0</h2>
                                        <small class="text-muted">Records Issued</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Liters</small>
                                    <span class="fw-bold text-warning" id="dieselLiters">0.00 L</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">This Month</small>
                                    <span class="fw-bold text-warning" id="dieselMonth">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Dashboard Content -->
        <div class="row">
            <div class="col-12">
                <!-- Fuel Records Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Recent Fuel Records
                                </h5>
                            </div>
                            <div class="col-auto">
                                <div class="d-flex gap-2">
                                    <!-- Filter Dropdown -->
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                            id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-filter me-1"></i>Filter
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                                            <li><a class="dropdown-item" href="#" data-filter="all">All Records</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="today">Today</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="week">This Week</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="month">This Month</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="#" data-filter="unleaded">Unleaded Only</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="diesel">Diesel Only</a></li>
                                        </ul>
                                    </div>
                                    <!-- Export Button -->
                                    <button class="btn btn-outline-primary btn-sm" id="exportBtn">
                                        <i class="fas fa-download me-1"></i>Export
                                    </button>
                                    <!-- Refresh Button -->
                                    <button class="btn btn-outline-success btn-sm" id="refreshBtn">
                                        <i class="fas fa-sync-alt me-1"></i>Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Scrollable Table Container -->
                        <div class="table-container-scrollable">
                            <table class="table table-hover mb-0 table-sticky-header" id="fuelRecordsTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th scope="col" class="border-0">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th scope="col" class="border-0">Date</th>
                                        <th scope="col" class="border-0">Office</th>
                                        <th scope="col" class="border-0">Vehicle</th>
                                        <th scope="col" class="border-0">Plate No.</th>
                                        <th scope="col" class="border-0">Driver</th>
                                        <th scope="col" class="border-0">Purpose/Destination</th>
                                        <th scope="col" class="border-0">Fuel Type</th>
                                        <th scope="col" class="border-0">Liters</th>
                                        <th scope="col" class="border-0">Remarks</th>
                                        <th scope="col" class="border-0">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="fuelRecordsBody">
                                    <!-- Data will be loaded here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="row align-items-center">
                            <div class="col">
                                <small class="text-muted">
                                    Total records: <span id="totalRecords">0</span>
                                </small>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted" id="lastUpdated">
                                    Last updated: <span id="lastUpdateTime">Never</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- jQuery (if needed for your custom functionality) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="fuel_dashboard.js"></script>
</body>

</html>