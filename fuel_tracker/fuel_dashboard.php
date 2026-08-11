<?php
require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fuel_budget_data.php';

$budgetSummary = [
    'total_budget' => 0,
    'used_budget' => 0,
    'remaining_budget' => 0,
];
$latestWeeklyFuelPrice = null;
$currentFuelPriceTuesday = date('Y-m-d');

try {
    $budgetSummary = fuelBudgetSummary($conn);
    $latestWeeklyFuelPrice = fuelBudgetLatestWeeklyFuelPrice($conn);
    $currentFuelPriceTuesday = fuelBudgetTuesdayForDate(date('Y-m-d'));
} catch (Throwable $e) {
    error_log('Dashboard budget summary error: ' . $e->getMessage());
}
?>
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
            <a class="navbar-brand d-flex align-items-center" href="fuel_dashboard.php">
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
                        <a class="nav-link active" aria-current="page" href="fuel_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addFuelRecordModal">
                            <i class="fas fa-plus-circle me-1"></i>Add Fuel Record
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link" href="gas_issuance.php">
                            <i class="fas fa-receipt me-1"></i>Gas Issuance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="private_gas_issuance.php">
                            <i class="fas fa-car-side me-1"></i>Private Issuance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sub_admin.php">
                            <i class="fas fa-print me-1"></i>Print Desk
                        </a>
                    </li>
                </ul>

                <!-- Right side items -->
                <div class="navbar-user-actions d-flex align-items-center">

                    <!-- User dropdown -->
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="../logo.png" alt="User" class="rounded-circle me-2" width="32" height="32">
                            <span class="text-dark"><?php echo htmlspecialchars((string) ($_SESSION['pay_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></span>
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
    <div class="container-fluid dashboard-shell mt-4">
        <div class="row">
            <div class="col-12">
                <div class="dashboard-page-header">
                    <div>
                        <h1 class="mb-1">
                            <i class="fas fa-gas-pump text-primary me-2"></i>Fuel Tracker
                        </h1>
                        <p class="text-muted mb-0">Monitor fuel records, totals, budgets, and statistics.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operations Overview -->
        <div class="row g-3 mb-3 fuel-summary-grid">
            <!-- Unleaded Card -->
            <div class="col-md-6 col-xl-2">
                <div class="card border-0 shadow-sm h-100 summary-card summary-card-unleaded">
                    <div class="card-body">
                        <div class="summary-card-head">
                            <div class="summary-icon summary-icon-unleaded">
                                <i class="fas fa-gas-pump"></i>
                            </div>
                            <div>
                                <h5 class="card-title text-muted mb-1">UNLEADED</h5>
                                <h2 class="card-text fw-bold text-success mb-0" id="unleadedCount">0</h2>
                                <small class="text-muted">Records Issued</small>
                            </div>
                        </div>
                        <div class="summary-card-foot">
                            <small class="text-muted d-block">Total Liters</small>
                            <span class="fw-bold text-success" id="unleadedLiters">0.00 L</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Diesel Card -->
            <div class="col-md-6 col-xl-2">
                <div class="card border-0 shadow-sm h-100 summary-card summary-card-diesel">
                    <div class="card-body">
                        <div class="summary-card-head">
                            <div class="summary-icon summary-icon-diesel">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div>
                                <h5 class="card-title text-muted mb-1">DIESEL</h5>
                                <h2 class="card-text fw-bold text-warning mb-0" id="dieselCount">0</h2>
                                <small class="text-muted">Records Issued</small>
                            </div>
                        </div>
                        <div class="summary-card-foot">
                            <small class="text-muted d-block">Total Liters</small>
                            <span class="fw-bold text-warning" id="dieselLiters">0.00 L</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fuel Budget Card -->
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 budget-card budget-card-current">
                    <div class="card-body">
                        <?php
                        $dieselRemaining = (float) ($budgetSummary['remaining_diesel_budget'] ?? 0);
                        $dieselTotal = (float) ($budgetSummary['total_diesel_budget'] ?? 0);
                        $dieselPercent = $dieselTotal > 0 ? max(0, min(100, ($dieselRemaining / $dieselTotal) * 100)) : 0;
                        $unleadedRemaining = (float) ($budgetSummary['remaining_unleaded_budget'] ?? 0);
                        $unleadedTotal = (float) ($budgetSummary['total_unleaded_budget'] ?? 0);
                        $unleadedPercent = $unleadedTotal > 0 ? max(0, min(100, ($unleadedRemaining / $unleadedTotal) * 100)) : 0;
                        $actualUsedTotal = (float) ($budgetSummary['used_budget'] ?? 0);
                        $actualDieselUsed = (float) ($budgetSummary['used_diesel_budget'] ?? 0);
                        $actualUnleadedUsed = (float) ($budgetSummary['used_unleaded_budget'] ?? 0);
                        $actualDieselLiters = (float) ($budgetSummary['actual_diesel_liters'] ?? 0);
                        $actualUnleadedLiters = (float) ($budgetSummary['actual_unleaded_liters'] ?? 0);
                        $actualMissingPrices = (int) ($budgetSummary['actual_missing_price_count'] ?? 0);
                        ?>
                        <div class="summary-card-head budget-card-head">
                            <div class="summary-icon summary-icon-budget">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title text-muted mb-1">FUEL BUDGET LEFT</h5>
                                <div class="budget-progress-stack mt-2">
                                    <div class="budget-progress-item">
                                        <div class="budget-progress-head">
                                            <span class="budget-metric-label">Diesel</span>
                                            <span class="budget-metric-value text-warning" id="budgetDieselRemaining">&#8369;<?php echo htmlspecialchars(number_format($dieselRemaining, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="budget-progress-track" role="progressbar" aria-label="Diesel budget remaining" aria-valuenow="<?php echo htmlspecialchars(number_format($dieselPercent, 0), ENT_QUOTES, 'UTF-8'); ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span class="budget-progress-fill budget-progress-diesel" id="budgetDieselBar" style="width: <?php echo htmlspecialchars(number_format($dieselPercent, 2), ENT_QUOTES, 'UTF-8'); ?>%;"></span>
                                        </div>
                                        <small class="budget-progress-meta">
                                            <span id="budgetDieselPercent"><?php echo htmlspecialchars(number_format($dieselPercent, 0), ENT_QUOTES, 'UTF-8'); ?>% left</span>
                                            <span id="budgetDieselTotal">used &#8369;<?php echo htmlspecialchars(number_format((float) ($budgetSummary['used_diesel_budget'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> of &#8369;<?php echo htmlspecialchars(number_format($dieselTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </small>
                                    </div>
                                    <div class="budget-progress-item">
                                        <div class="budget-progress-head">
                                            <span class="budget-metric-label">Unleaded</span>
                                            <span class="budget-metric-value text-success" id="budgetUnleadedRemaining">&#8369;<?php echo htmlspecialchars(number_format($unleadedRemaining, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="budget-progress-track" role="progressbar" aria-label="Unleaded budget remaining" aria-valuenow="<?php echo htmlspecialchars(number_format($unleadedPercent, 0), ENT_QUOTES, 'UTF-8'); ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span class="budget-progress-fill budget-progress-unleaded" id="budgetUnleadedBar" style="width: <?php echo htmlspecialchars(number_format($unleadedPercent, 2), ENT_QUOTES, 'UTF-8'); ?>%;"></span>
                                        </div>
                                        <small class="budget-progress-meta">
                                            <span id="budgetUnleadedPercent"><?php echo htmlspecialchars(number_format($unleadedPercent, 0), ENT_QUOTES, 'UTF-8'); ?>% left</span>
                                            <span id="budgetUnleadedTotal">used &#8369;<?php echo htmlspecialchars(number_format((float) ($budgetSummary['used_unleaded_budget'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> of &#8369;<?php echo htmlspecialchars(number_format($unleadedTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="budget-context-grid mt-3">
                            <div class="budget-context-item">
                                <small>Total Budget</small>
                                <span id="budgetTotal">&#8369;<?php echo htmlspecialchars(number_format((float) $budgetSummary['total_budget'], 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="budget-context-item">
                                <small>Actual Used</small>
                                <span class="text-danger" id="budgetUsed">&#8369;<?php echo htmlspecialchars(number_format((float) $budgetSummary['used_budget'], 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                        <small class="budget-draft-note d-block mt-2" id="actualBudgetPriceNote">Actual used amount uses used gas issuance dates matched to saved weekly pump prices.</small>
                        <div class="budget-card-actions mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="openAddBudgetBtn" data-bs-toggle="modal" data-bs-target="#addBudgetModal">
                                <i class="fas fa-plus-circle me-1"></i>Add IB
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#manageBudgetModal">
                                <i class="fas fa-list-check me-1"></i>Manage IBs
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estimated Budget Card -->
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 budget-card budget-card-compact">
                    <div class="card-body">
                        <div class="budget-compact-header">
                            <h5 class="card-title text-muted mb-0">
                                <i class="fas fa-calculator text-primary me-2"></i>Estimated Budget
                            </h5>
                            <small class="text-muted">After approved scheduled issuances.</small>
                        </div>

                        <div class="budget-progress-stack budget-estimate-grid">
                            <div class="budget-progress-item">
                                <div class="budget-progress-head">
                                    <span class="budget-metric-label">Diesel</span>
                                    <span class="budget-metric-value text-warning" id="estimatedBudgetDieselRemaining">&#8369;<?php echo htmlspecialchars(number_format($dieselRemaining, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="budget-progress-track" role="progressbar" aria-label="Estimated diesel budget remaining" aria-valuenow="<?php echo htmlspecialchars(number_format($dieselPercent, 0), ENT_QUOTES, 'UTF-8'); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <span class="budget-progress-fill budget-progress-diesel" id="estimatedBudgetDieselBar" style="width: <?php echo htmlspecialchars(number_format($dieselPercent, 2), ENT_QUOTES, 'UTF-8'); ?>%;"></span>
                                </div>
                                <small class="budget-progress-meta">
                                    <span id="estimatedBudgetDieselPercent"><?php echo htmlspecialchars(number_format($dieselPercent, 0), ENT_QUOTES, 'UTF-8'); ?>% left</span>
                                    <span id="estimatedBudgetDieselTotal">of &#8369;<?php echo htmlspecialchars(number_format($dieselTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                </small>
                            </div>
                            <div class="budget-progress-item">
                                <div class="budget-progress-head">
                                    <span class="budget-metric-label">Unleaded</span>
                                    <span class="budget-metric-value text-success" id="estimatedBudgetUnleadedRemaining">&#8369;<?php echo htmlspecialchars(number_format($unleadedRemaining, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="budget-progress-track" role="progressbar" aria-label="Estimated unleaded budget remaining" aria-valuenow="<?php echo htmlspecialchars(number_format($unleadedPercent, 0), ENT_QUOTES, 'UTF-8'); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <span class="budget-progress-fill budget-progress-unleaded" id="estimatedBudgetUnleadedBar" style="width: <?php echo htmlspecialchars(number_format($unleadedPercent, 2), ENT_QUOTES, 'UTF-8'); ?>%;"></span>
                                </div>
                                <small class="budget-progress-meta">
                                    <span id="estimatedBudgetUnleadedPercent"><?php echo htmlspecialchars(number_format($unleadedPercent, 0), ENT_QUOTES, 'UTF-8'); ?>% left</span>
                                    <span id="estimatedBudgetUnleadedTotal">of &#8369;<?php echo htmlspecialchars(number_format($unleadedTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                </small>
                            </div>
                        </div>

                        <div class="budget-estimate-totals">
                            <div class="budget-context-item">
                                <small>Estimated Cost</small>
                                <span class="text-danger" id="estimatedBudgetCost">&#8369;0.00</span>
                            </div>
                            <div class="budget-context-item">
                                <small>Estimated Left</small>
                                <span class="text-primary" id="estimatedBudgetTotalLeft">&#8369;<?php echo htmlspecialchars(number_format((float) ($budgetSummary['remaining_budget'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>

                        <small class="budget-draft-note" id="budgetDraftNote">Reserved estimate uses approved/valid scheduled issuances.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm actual-weekly-card">
                    <div class="card-body">
                        <div class="actual-weekly-layout">
                            <div class="actual-weekly-summary">
                                <div class="budget-compact-header">
                                    <h5 class="card-title text-muted mb-0">
                                        <i class="fas fa-chart-column text-primary me-2"></i>Actual Weekly Deductions
                                    </h5>
                                    <small class="text-muted">Used gas issuances priced by the weekly pump price saved for each used date.</small>
                                </div>
                                <div class="actual-usage-panel">
                                    <div class="actual-usage-panel-head">
                                        <span>Total Actual Used</span>
                                        <strong id="actualUsedTotalAmount">&#8369;<?php echo htmlspecialchars(number_format($actualUsedTotal, 2), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="actual-usage-grid">
                                        <div class="actual-usage-item actual-usage-diesel">
                                            <small>Diesel</small>
                                            <span id="actualUsedDieselAmount">&#8369;<?php echo htmlspecialchars(number_format($actualDieselUsed, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <em id="actualUsedDieselLiters"><?php echo htmlspecialchars(number_format($actualDieselLiters, 2), ENT_QUOTES, 'UTF-8'); ?> L</em>
                                        </div>
                                        <div class="actual-usage-item actual-usage-unleaded">
                                            <small>Unleaded</small>
                                            <span id="actualUsedUnleadedAmount">&#8369;<?php echo htmlspecialchars(number_format($actualUnleadedUsed, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <em id="actualUsedUnleadedLiters"><?php echo htmlspecialchars(number_format($actualUnleadedLiters, 2), ENT_QUOTES, 'UTF-8'); ?> L</em>
                                        </div>
                                    </div>
                                    <small class="actual-usage-warning <?php echo $actualMissingPrices > 0 ? '' : 'd-none'; ?>" id="actualUsedMissingPrices">
                                        <?php echo htmlspecialchars($actualMissingPrices . ' used record(s) need weekly pump prices before they can be fully costed.', ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="actual-weekly-chart-panel">
                                <div class="actual-weekly-chart-head">
                                    <div class="budget-section-mini-title">Deductions Per Week</div>
                                    <small id="weeklyDeductionChartSummary">Loading weekly deductions...</small>
                                </div>
                                <div class="actual-weekly-chart-wrap">
                                    <canvas id="weeklyBudgetDeductionChart" height="170" aria-label="Weekly budget deductions by fuel type"></canvas>
                                    <div class="chart-empty-state actual-weekly-chart-empty d-none" id="weeklyBudgetDeductionEmpty">
                                        <i class="fas fa-chart-column"></i>
                                        <span>No weekly used gas issuance deductions yet.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm weekly-price-card">
                    <div class="card-body">
                        <div class="weekly-price-layout">
                            <div class="weekly-price-form">
                                <div class="budget-compact-header">
                                    <h5 class="card-title text-muted mb-0">
                                        <i class="fas fa-chart-line text-primary me-2"></i>Weekly Pump Prices
                                    </h5>
                                    <small class="text-muted">Update Tuesday pump prices used by the estimated budget.</small>
                                </div>
                                <div class="budget-price-grid">
                                    <div class="budget-context-item budget-week-item">
                                        <label for="dashboardFuelPriceWeek" class="form-label mb-1">Tuesday Week</label>
                                        <input type="date" class="form-control form-control-sm" id="dashboardFuelPriceWeek" value="<?php echo htmlspecialchars((string) ($latestWeeklyFuelPrice['week_start'] ?? $currentFuelPriceTuesday), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="budget-context-item">
                                        <label for="dashboardDieselPumpPrice" class="form-label mb-1">Diesel Price</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">&#8369;</span>
                                            <input type="number" class="form-control" id="dashboardDieselPumpPrice" min="0" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars(number_format((float) ($latestWeeklyFuelPrice['diesel_price'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    </div>
                                    <div class="budget-context-item">
                                        <label for="dashboardUnleadedPumpPrice" class="form-label mb-1">Unleaded Price</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">&#8369;</span>
                                            <input type="number" class="form-control" id="dashboardUnleadedPumpPrice" min="0" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars(number_format((float) ($latestWeeklyFuelPrice['unleaded_price'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    </div>
                                    <div class="budget-context-item budget-price-action">
                                        <label for="dashboardFuelPriceSource" class="form-label mb-1">Source Note</label>
                                        <input type="text" class="form-control form-control-sm" id="dashboardFuelPriceSource" placeholder="DOE / pump price" value="<?php echo htmlspecialchars((string) ($latestWeeklyFuelPrice['source_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                                <div class="weekly-price-actions">
                                    <button type="button" class="btn btn-primary btn-sm weekly-price-save" id="saveWeeklyFuelPriceBtn">
                                        <i class="fas fa-save me-1"></i>Save Weekly Prices
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm weekly-price-history-btn" data-bs-toggle="modal" data-bs-target="#weeklyFuelPriceHistoryModal">
                                        <i class="fas fa-clock-rotate-left me-1"></i>View Price History
                                    </button>
                                </div>
                            </div>
                            <div class="fuel-price-trend-panel">
                                <div class="budget-section-mini-title">Weekly Price Trend</div>
                                <div class="fuel-price-chart-wrap">
                                    <canvas id="fuelPriceTrendChart" height="120" aria-label="Weekly diesel and unleaded price trend"></canvas>
                                    <div class="chart-empty-state fuel-price-chart-empty d-none" id="fuelPriceTrendEmpty">
                                        <i class="fas fa-chart-line"></i>
                                        <span>No weekly fuel prices saved yet.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="weeklyFuelPriceHistoryModal" tabindex="-1" aria-labelledby="weeklyFuelPriceHistoryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="weeklyFuelPriceHistoryModalLabel">
                            <i class="fas fa-clock-rotate-left me-2"></i>Weekly Pump Price History
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="weekly-price-history-summary" id="weeklyFuelPriceHistorySummary">
                            Showing saved weekly pump prices.
                        </div>
                        <div class="table-responsive weekly-price-history-table-wrap">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Tuesday Week</th>
                                        <th scope="col" class="text-end">Diesel</th>
                                        <th scope="col" class="text-end">Unleaded</th>
                                        <th scope="col">Source Note</th>
                                        <th scope="col">Updated</th>
                                    </tr>
                                </thead>
                                <tbody id="weeklyFuelPriceHistoryBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No weekly fuel prices saved yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="addBudgetModal" tabindex="-1" aria-labelledby="addBudgetModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addBudgetModalLabel">
                            <i class="fas fa-wallet me-2"></i>Add IB Budget
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addBudgetForm">
                            <div class="mb-3">
                                <label for="budgetIbNo" class="form-label">IB Number</label>
                                <input type="text" class="form-control text-uppercase" id="budgetIbNo" required>
                                <div class="form-text" id="budgetIbHelp">Enter a new IB number or edit an existing IB from Manage IBs.</div>
                            </div>
                            <div class="mb-3">
                                <label for="budgetDescription" class="form-label">Description</label>
                                <input type="text" class="form-control" id="budgetDescription" placeholder="Fuel budget">
                            </div>
                            <div class="mb-3">
                                <label for="budgetFuelCoverage" class="form-label">Fuel Coverage</label>
                                <select class="form-select" id="budgetFuelCoverage" required>
                                    <option value="diesel">Diesel Only</option>
                                    <option value="unleaded">Unleaded Only</option>
                                    <option value="both" selected>Diesel and Unleaded</option>
                                </select>
                                <div class="form-text">Only the selected fuel allocation will be changed. Other existing allocations are preserved.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6" id="budgetDieselAllocationGroup">
                                    <label for="budgetDieselAllocation" class="form-label">Diesel Allocation</label>
                                    <div class="input-group">
                                        <span class="input-group-text">&#8369;</span>
                                        <input type="number" class="form-control" id="budgetDieselAllocation" min="0.01" step="0.01" value="0">
                                    </div>
                                    <small class="text-muted" id="budgetDieselUsedHelp"></small>
                                </div>
                                <div class="col-md-6" id="budgetUnleadedAllocationGroup">
                                    <label for="budgetUnleadedAllocation" class="form-label">Unleaded Allocation</label>
                                    <div class="input-group">
                                        <span class="input-group-text">&#8369;</span>
                                        <input type="number" class="form-control" id="budgetUnleadedAllocation" min="0.01" step="0.01" value="0">
                                    </div>
                                    <small class="text-muted" id="budgetUnleadedUsedHelp"></small>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">Allocations cannot be reduced below their already deducted amounts.</small>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="budgetAddAnother">
                                <label class="form-check-label" for="budgetAddAnother">Add another IB after saving</label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveBudgetBtn">
                            <i class="fas fa-save me-1"></i>Save Budget
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="manageBudgetModal" tabindex="-1" aria-labelledby="manageBudgetModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="manageBudgetModalLabel">
                            <i class="fas fa-list-check me-2"></i>Manage IB Budgets
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Each IB can fund diesel, unleaded, or both. Automatic deductions use the oldest matching active IB first.</p>
                        <div class="table-responsive budget-management-table-wrap">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>IB No.</th>
                                        <th>Description</th>
                                        <th>Fuel Coverage</th>
                                        <th class="text-end">Diesel Allocation</th>
                                        <th class="text-end">Diesel Remaining</th>
                                        <th class="text-end">Unleaded Allocation</th>
                                        <th class="text-end">Unleaded Remaining</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="budgetManagementBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No IB budgets saved yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="manageAddBudgetBtn">
                            <i class="fas fa-plus-circle me-1"></i>Add New IB
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-building me-2 text-primary"></i>Office Consumption Ranking
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-panel">
                            <canvas id="officeConsumptionChart" height="260" aria-label="Office fuel consumption ranking"></canvas>
                        </div>
                        <div class="chart-empty-state d-none" id="officeChartEmpty">
                            <i class="fas fa-chart-bar"></i>
                            <span>No used gas issuance data for office ranking yet.</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-car-side me-2 text-success"></i>Vehicle Consumption Ranking
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-panel">
                            <canvas id="vehicleConsumptionChart" height="260" aria-label="Vehicle fuel consumption ranking"></canvas>
                        </div>
                        <div class="chart-empty-state d-none" id="vehicleChartEmpty">
                            <i class="fas fa-chart-bar"></i>
                            <span>No used gas issuance data for vehicle ranking yet.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Dashboard Content -->
        <div class="row">
            <div class="col-12">
                <!-- Budget Deduction Transactions Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="row g-3 align-items-start align-items-xl-center">
                            <div class="col-12 col-xl">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-receipt me-2"></i>Budget Deduction Transactions
                                </h5>
                                <p class="text-muted small mb-0 mt-1">Saved deductions from fuel summaries, grouped by IB allocation.</p>
                            </div>
                            <div class="col-12 col-xl-auto">
                                <div class="dashboard-toolbar d-flex flex-wrap gap-2 align-items-center justify-content-start justify-content-xl-end">
                                    <!-- Add a search bar to the top of the page -->
                                    <div class="input-group input-group-sm toolbar-search">
                                        <input type="search" id="searchBar" class="form-control" placeholder="Search IB, office, user, ref...">
                                        <button class="btn btn-outline-secondary" id="searchBtn" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <input type="date" class="form-control form-control-sm toolbar-date" id="dateFilterStart" placeholder="Start Date">
                                    <span class="date-range-separator">to</span>
                                    <input type="date" class="form-control form-control-sm toolbar-date" id="dateFilterEnd" placeholder="End Date">
                                    <button class="btn btn-outline-secondary btn-sm" id="dateFilterBtn">
                                        <i class="fas fa-calendar-alt me-1"></i>Date
                                    </button>

                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                            id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-filter me-1"></i>Filter
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                                            <li><a class="dropdown-item" href="#" data-filter="all">All Transactions</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="today">Today</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="week">This Week</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="month">This Month</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <!-- <li><a class="dropdown-item" href="#" data-filter="unleaded">Unleaded Only</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="diesel">Diesel Only</a></li> -->
                                        </ul>
                                    </div>

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
                                        <th scope="col" class="border-0">Saved</th>
                                        <th scope="col" class="border-0">IB No.</th>
                                        <th scope="col" class="border-0">Office / Period</th>
                                        <th scope="col" class="border-0 text-end">Diesel</th>
                                        <th scope="col" class="border-0 text-end">Unleaded</th>
                                        <th scope="col" class="border-0 text-end">Total</th>
                                        <th scope="col" class="border-0">Saved By</th>
                                        <th scope="col" class="border-0">Ref</th>
                                    </tr>
                                </thead>
                                <tbody id="fuelRecordsBody">
                                    <!-- Data will be loaded here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-sm">
                                <small class="text-muted">
                                    Total transactions: <span id="totalRecords">0</span>
                                </small>
                            </div>
                            <div class="col-12 col-sm-auto">
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <!-- jQuery (if needed for your custom functionality) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="fuel_dashboard.js"></script>
</body>

</html>
