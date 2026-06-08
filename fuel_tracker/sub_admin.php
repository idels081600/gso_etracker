<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('coa_admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';
require_once __DIR__ . '/fuel_budget_data.php';

$vehicles = fuelTrackerFetchVehicles($conn);
$vehicleLookup = fuelTrackerVehicleLookupByPlate($vehicles);
fuelTrackerSyncIssuanceOffices($conn);
$fuelBudgetPool = fuelBudgetSummary($conn);
$initialPrintableLimit = 200;
$printableIssuances = fuelTrackerFetchGasIssuances($conn, ['used'], 'government', $initialPrintableLimit);

$approvedVehicles = [];
$approvedOffices = [];
foreach ($printableIssuances as $issuance) {
    $plate = (string) ($issuance['plate_no'] ?? '');
    if ($plate !== '') {
        $approvedVehicles[$plate] = trim($plate . ' - ' . (string) ($issuance['vehicle_type'] ?? ''));
    }
    $office = trim((string) ($issuance['office'] ?? ''));
    if ($office !== '') {
        $approvedOffices[$office] = $office;
    }
}
ksort($approvedVehicles);
ksort($approvedOffices);

$todayPrintable = count(array_filter($printableIssuances, static fn(array $issuance): bool => ($issuance['issue_date'] ?? '') === date('Y-m-d')));
$totalLiters = array_reduce($printableIssuances, static fn(float $sum, array $issuance): float => $sum + (float) ($issuance['authorized_liters'] ?? 0), 0.0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub Admin Printing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="fuel_dashboard.css">
    <style>
        :root {
            --sub-green: #039a00;
            --sub-green-dark: #027100;
        }

        body {
            background: #f4f7f4;
        }

        .sub-admin-shell {
            max-width: 1480px;
        }

        .sub-admin-hero {
            background: linear-gradient(135deg, var(--sub-green), var(--sub-green-dark));
            border-radius: 8px;
            color: #fff;
            padding: 1.25rem;
        }

        .sub-admin-hero h1 {
            font-size: clamp(1.45rem, 2.4vw, 2rem);
            letter-spacing: 0;
        }

        .summary-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .summary-tile {
            background: #fff;
            border: 1px solid #dfe8df;
            border-radius: 8px;
            padding: 0.85rem 1rem;
        }

        .summary-label {
            color: #5d6b60;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary-value {
            color: #18351c;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .print-toolbar {
            align-items: end;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: minmax(210px, 1fr) minmax(190px, 240px) repeat(2, minmax(145px, 170px)) repeat(4, auto);
        }

        .sub-table-card {
            border: 0;
            border-radius: 8px;
            overflow: hidden;
        }

        .sub-table {
            min-width: 1180px;
            table-layout: fixed;
        }

        .sub-table thead th {
            background: #eff7ef;
            border-bottom: 1px solid #d7e2d8;
            color: #244327;
            font-size: 0.76rem;
            padding: 0.85rem 0.9rem;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .sub-table tbody tr {
            border-bottom: 1px solid #dfe7e1;
            min-height: 64px;
        }

        .sub-table tbody td {
            border-bottom: 0;
            min-width: 0;
            overflow: hidden;
            padding: 0.75rem 0.8rem;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        .sub-table th:nth-child(1),
        .sub-table td:nth-child(1) {
            width: 46px;
        }

        .sub-table th:nth-child(2),
        .sub-table td:nth-child(2) {
            width: 150px;
        }

        .sub-table th:nth-child(3),
        .sub-table td:nth-child(3) {
            width: 112px;
        }

        .sub-table th:nth-child(4),
        .sub-table td:nth-child(4) {
            width: 190px;
        }

        .sub-table th:nth-child(5),
        .sub-table td:nth-child(5) {
            width: 180px;
        }

        .sub-table th:nth-child(6),
        .sub-table td:nth-child(6),
        .sub-table th:nth-child(7),
        .sub-table td:nth-child(7) {
            width: 118px;
        }

        .sub-table th:nth-child(8),
        .sub-table td:nth-child(8) {
            width: 130px;
        }

        .sub-table th:nth-child(9),
        .sub-table td:nth-child(9) {
            width: 96px;
        }

        .sub-table th:nth-child(10),
        .sub-table td:nth-child(10) {
            width: 170px;
        }

        .serial-cell {
            color: #143a17;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .vehicle-stack {
            display: grid;
            gap: 0.12rem;
            min-width: 0;
            width: 100%;
        }

        .vehicle-stack .plate {
            font-weight: 800;
        }

        .vehicle-stack .plate,
        .vehicle-stack .vehicle,
        .driver-cell,
        .office-cell {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .vehicle-stack .vehicle {
            color: #6c757d;
            font-size: 0.82rem;
        }

        .print-actions {
            display: flex;
            gap: 0.35rem;
            justify-content: flex-end;
            min-width: 0;
            white-space: nowrap;
        }

        .print-actions .btn {
            align-items: center;
            display: inline-flex;
            gap: 0.3rem;
            justify-content: center;
            min-height: 34px;
            padding-left: 0.55rem;
            padding-right: 0.55rem;
            white-space: nowrap;
        }

        .status-approved {
            background: #dff3df;
            color: #075f05;
        }

        .status-valid {
            background: #e6f0ff;
            color: #084298;
        }

        .status-used {
            background: #fff3cd;
            color: #7a4f01;
        }

        .selection-cell {
            width: 42px;
        }

        .selected-summary {
            color: #4f5f52;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .table-pagination {
            align-items: center;
            background: #fff;
            border-top: 1px solid #dfe7e1;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
            padding: 0.85rem 1rem;
        }

        .table-pagination-status {
            color: #5d6b60;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .table-pagination-controls {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .table-page-size {
            width: 82px;
        }

        .coa-odometer-table th {
            background: #f8fafc;
            color: #475467;
            font-size: 0.76rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .coa-odometer-table td {
            vertical-align: middle;
        }

        .coa-odometer-table input[type="number"] {
            min-width: 140px;
        }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
        }

        .fuel-summary-modal .modal-dialog {
            max-width: 980px;
        }

        .fuel-summary-report {
            background: #cfe2f5;
            border: 2px solid #27425e;
            color: #0f2437;
            font-size: 0.86rem;
            overflow-x: auto;
        }

        .fuel-summary-title {
            border-bottom: 2px solid #27425e;
            padding: 0.75rem 0.85rem;
        }

        .fuel-summary-title .period {
            font-weight: 800;
            text-transform: uppercase;
        }

        .fuel-summary-table {
            border-collapse: collapse;
            margin: 0;
            min-width: 620px;
            width: 100%;
        }

        .fuel-summary-table th,
        .fuel-summary-table td {
            border: 1px solid #27425e;
            padding: 0.45rem 0.55rem;
        }

        .fuel-summary-table th {
            background: #c4d9ee;
            font-size: 0.76rem;
            text-align: center;
            text-transform: uppercase;
        }

        .fuel-summary-table .office-row td {
            background: #6f8191;
            color: #fff;
            font-weight: 800;
            text-transform: uppercase;
        }

        .fuel-summary-table .subtotal-row td,
        .fuel-summary-table .grand-row td {
            background: #d9e8f7;
            font-weight: 900;
        }

        .fuel-summary-table .grand-row td {
            font-size: 1rem;
        }

        .fuel-summary-empty {
            padding: 2.5rem 1rem;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .summary-grid,
            .print-toolbar {
                grid-template-columns: 1fr;
            }

            .print-toolbar .btn {
                width: 100%;
            }
        }

        @media (max-width: 767.98px) {
            .sub-admin-hero {
                padding: 1rem;
            }

            .print-actions {
                flex-direction: row;
                min-width: 0;
            }
        }
    </style>
</head>

<body>
    <main class="container-fluid sub-admin-shell py-4">
        <section class="sub-admin-hero shadow-sm mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="mb-1 text-white-50 fw-semibold">Sub Admin Print Desk</p>
                    <h1 class="mb-1 fw-bold">Trip Ticket and COA Printing</h1>
                    <p class="mb-0 text-white-50">Used gas issuances only. Print driver trip tickets and COA reports from one list.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-white text-success px-3 py-2">
                        <i class="fas fa-check-circle me-1"></i>Used Records
                    </span>
                    <a href="../logout.php" class="btn btn-light text-danger fw-semibold">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </section>

        <section class="summary-grid mb-4" aria-label="Used issuance summary">
            <div class="summary-tile shadow-sm">
                <div class="summary-label">Used Records</div>
                <div class="summary-value"><?php echo count($printableIssuances); ?></div>
            </div>
            <div class="summary-tile shadow-sm">
                <div class="summary-label">Used Today</div>
                <div class="summary-value"><?php echo $todayPrintable; ?></div>
            </div>
            <div class="summary-tile shadow-sm">
                <div class="summary-label">Total Fuel Liters</div>
                <div class="summary-value"><?php echo number_format($totalLiters, 2); ?> L</div>
            </div>
        </section>

        <section class="card sub-table-card shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1 fw-bold">
                            <i class="fas fa-print me-2 text-success"></i>Used Gas Issuances
                        </h2>
                        <p class="text-muted mb-0 small">Search the latest loaded used records, then print the required document.</p>
                    </div>
                </div>
                <div class="print-toolbar">
                    <div>
                        <label for="subAdminSearch" class="form-label small fw-bold text-muted">Search issuance, plate, driver, vehicle, or office</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-success"></i></span>
                            <input type="search" class="form-control" id="subAdminSearch" placeholder="Search used records...">
                        </div>
                    </div>
                    <div>
                        <label for="vehicleFilter" class="form-label small fw-bold text-muted">Vehicle</label>
                        <select class="form-select" id="vehicleFilter">
                            <option value="">All vehicles</option>
                            <?php foreach ($approvedVehicles as $plate => $label): ?>
                                <option value="<?php echo htmlspecialchars($plate, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="dateFromFilter" class="form-label small fw-bold text-muted">Date from</label>
                        <input type="date" class="form-control" id="dateFromFilter">
                    </div>
                    <div>
                        <label for="dateToFilter" class="form-label small fw-bold text-muted">Date to</label>
                        <input type="date" class="form-control" id="dateToFilter">
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="clearFiltersBtn">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#fuelSummaryModal">
                        <i class="fas fa-gas-pump me-1"></i>Fuel Summary
                    </button>
                    <button type="button" class="btn btn-success" id="printSelectedTripsBtn" disabled>
                        <i class="fas fa-route me-1"></i>Print Selected Trips
                    </button>
                    <button type="button" class="btn btn-primary" id="printMonthlyBtn" disabled>
                        <i class="fas fa-table-list me-1"></i>Print Monthly Form B
                    </button>
                    <button type="button" class="btn btn-dark" id="printSelectedCoaBtn" disabled>
                        <i class="fas fa-file-contract me-1"></i>Print Selected COA
                    </button>
                </div>
                <div class="selected-summary mt-2" id="selectedSummary">Select one or more gas issuances. Monthly Form B will be grouped per vehicle.</div>
                <div class="small text-muted mt-1">
                    Showing the latest <?php echo htmlspecialchars((string) $initialPrintableLimit, ENT_QUOTES, 'UTF-8'); ?> used records for faster loading.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table sub-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="selection-cell">
                                <input class="form-check-input" type="checkbox" id="selectVisibleRows" aria-label="Select visible issuances">
                            </th>
                            <th>Issuance No.</th>
                            <th>Date</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th class="text-end">Issued Liters</th>
                            <th class="text-end">Actual Liters</th>
                            <th>Office</th>
                            <th>Status</th>
                            <th class="text-end">Print</th>
                        </tr>
                    </thead>
                    <tbody id="subAdminTableBody">
                        <?php if (empty($printableIssuances)): ?>
                            <tr>
                                <td colspan="10" class="empty-state text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No used gas issuance records available.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($printableIssuances as $issuance): ?>
                                <?php
                                $searchText = strtolower(implode(' ', [
                                    $issuance['serial_no'] ?? '',
                                    $issuance['plate_no'] ?? '',
                                    $issuance['driver_name'] ?? '',
                                    $issuance['vehicle_type'] ?? '',
                                    $issuance['office'] ?? '',
                                ]));
                                $status = strtolower((string) ($issuance['status'] ?? 'valid'));
                                $statusClass = 'status-valid';
                                if ($status === 'approved') {
                                    $statusClass = 'status-approved';
                                } elseif ($status === 'used') {
                                    $statusClass = 'status-used';
                                }
                                ?>
                                <tr data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-plate="<?php echo htmlspecialchars((string) ($issuance['plate_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date="<?php echo htmlspecialchars((string) ($issuance['issue_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="selection-cell">
                                        <input class="form-check-input monthly-select"
                                            type="checkbox"
                                            value="<?php echo htmlspecialchars((string) $issuance['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="Select issuance <?php echo htmlspecialchars((string) $issuance['serial_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td class="serial-cell font-monospace"><?php echo htmlspecialchars((string) $issuance['serial_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string) $issuance['issue_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="vehicle-stack">
                                            <span class="plate"><?php echo htmlspecialchars((string) ($issuance['plate_no'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="vehicle"><?php echo htmlspecialchars((string) ($issuance['vehicle_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                    </td>
                                    <td class="fw-semibold driver-cell" title="<?php echo htmlspecialchars((string) ($issuance['driver_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($issuance['driver_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo htmlspecialchars(number_format((float) ($issuance['authorized_liters'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> L</td>
                                    <td class="text-end fw-bold"><?php echo htmlspecialchars(number_format((float) ($issuance['actual_liters_fueled'] ?? $issuance['authorized_liters'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> L</td>
                                    <td class="office-cell" title="<?php echo htmlspecialchars((string) ($issuance['office'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($issuance['office'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?> px-3 py-2"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <div class="print-actions">
                                            <button type="button"
                                                class="btn btn-success btn-sm action-print-trip"
                                                data-id="<?php echo htmlspecialchars((string) $issuance['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Print driver trip ticket">
                                                <i class="fas fa-route"></i><span>Trip</span>
                                            </button>
                                            <button type="button"
                                                class="btn btn-outline-dark btn-sm action-print-coa"
                                                data-id="<?php echo htmlspecialchars((string) $issuance['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Print COA report">
                                                <i class="fas fa-file-contract"></i><span>COA</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noFilterResults" class="d-none">
                                <td colspan="10" class="empty-state text-muted">
                                    <i class="fas fa-search fa-2x mb-2 d-block"></i>
                                    No used records match your filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-pagination">
                <div class="table-pagination-status" id="tablePaginationStatus">Showing 0 records</div>
                <div class="table-pagination-controls">
                    <label for="tablePageSize" class="small text-muted fw-semibold mb-0">Rows</label>
                    <select class="form-select form-select-sm table-page-size" id="tablePageSize">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="tablePrevPage">
                        <i class="fas fa-chevron-left me-1"></i>Previous
                    </button>
                    <span class="small fw-semibold text-muted" id="tablePageLabel">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="tableNextPage">
                        Next<i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade fuel-summary-modal" id="fuelSummaryModal" tabindex="-1" aria-labelledby="fuelSummaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="fuelSummaryModalLabel">
                        <i class="fas fa-gas-pump me-2"></i>Fuel Summary by Office and Vehicle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-4">
                            <label for="summaryOfficeFilter" class="form-label small fw-bold text-muted">Office</label>
                            <select class="form-select" id="summaryOfficeFilter">
                                <option value="">All offices</option>
                                <?php foreach ($approvedOffices as $office): ?>
                                    <option value="<?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="summaryDateFrom" class="form-label small fw-bold text-muted">Date from</label>
                            <input type="date" class="form-control" id="summaryDateFrom">
                        </div>
                        <div class="col-md-2">
                            <label for="summaryDateTo" class="form-label small fw-bold text-muted">Date to</label>
                            <input type="date" class="form-control" id="summaryDateTo">
                        </div>
                        <div class="col-md-2">
                            <label for="summaryDieselPrice" class="form-label small fw-bold text-muted">Diesel Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="summaryDieselPrice" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="summaryUnleadedPrice" class="form-label small fw-bold text-muted">Unleaded Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="summaryUnleadedPrice" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Budget Deduction</label>
                            <div class="form-control bg-light">
                                <i class="fas fa-shuffle me-1 text-success"></i>
                                Auto-allocate oldest active IB first
                            </div>
                            <small class="text-muted d-block mt-1" id="summaryBudgetHint">
                                Active remaining budget: &#8369;<?php echo htmlspecialchars(number_format((float) $fuelBudgetPool['remaining_budget'], 2), ENT_QUOTES, 'UTF-8'); ?>
                            </small>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="button" class="btn btn-outline-secondary" id="summaryClearBtn">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                    <div class="fuel-summary-report" id="fuelSummaryReport" aria-live="polite"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-success" id="saveFuelSummaryDeductionBtn">
                        <i class="fas fa-wallet me-1"></i>Save Deduction
                    </button>
                    <button type="button" class="btn btn-success" id="printFuelSummaryBtn">
                        <i class="fas fa-print me-1"></i>Print Summary
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="coaOdometerModal" tabindex="-1" aria-labelledby="coaOdometerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="coaOdometerModalLabel">
                        <i class="fas fa-gauge-high me-2"></i>Manual COA Odometer Readings
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        Enter the Beginning odometer for each selected vehicle. The Ending odometer is computed from fuel used and normal km/liter so COA excess becomes zero.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm coa-odometer-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Plate</th>
                                    <th class="text-end">Fuel Used</th>
                                    <th>Beginning Odometer</th>
                                    <th>Ending Odometer <span class="text-muted">(Auto)</span></th>
                                </tr>
                            </thead>
                            <tbody id="coaOdometerRows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-dark" id="confirmSelectedCoaBtn">
                        <i class="fas fa-print me-1"></i>Print COA
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
        <div id="subAdminToast" class="toast align-items-center border-0 text-bg-success" role="status" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="subAdminToastMessage">Opening document...</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var approvedIssuances = <?php echo json_encode(array_map(static function (array $issuance) use ($vehicleLookup): array {
            $plate = strtoupper((string) ($issuance['plate_no'] ?? ''));
            $vehicleMeta = $vehicleLookup[$plate] ?? [];
            $currentOdometer = (float) ($issuance['current_odometer'] ?? $vehicleMeta['current_odo'] ?? 0);
            $liters = (float) ($issuance['authorized_liters'] ?? 0);
            $pastOdometer = (float) ($issuance['past_odometer'] ?? max(0, $currentOdometer - round($liters * 20)));

            return [
                'id' => $issuance['id'],
                'serial_no' => $issuance['serial_no'],
                'date' => $issuance['issue_date'],
                'plate_no' => $issuance['plate_no'],
                'driver_name' => $issuance['driver_name'],
                'vehicle_type' => $issuance['vehicle_type'],
                'liters' => $liters,
                'issued_liters' => $issuance['authorized_liters'],
                'fuel_type' => $issuance['fuel_type'] ?? '',
                'unit' => $issuance['unit'] ?? 'Liters',
                'purpose' => $issuance['purpose'] ?? 'OFFICIAL TRAVEL',
                'past_odometer' => $pastOdometer,
                'current_odometer' => $currentOdometer,
                'cylinders' => $issuance['cylinders'] ?? $vehicleMeta['cylinders'] ?? 4,
                'normal_km_per_liter' => $issuance['normal_km_per_liter'] ?? 20,
                'office' => $issuance['office'] ?? 'Office',
                'status' => $issuance['status'] ?? 'valid'
            ];
        }, $printableIssuances), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        var fuelBudgetPool = <?php echo json_encode([
            'remaining_budget' => (float) ($fuelBudgetPool['remaining_budget'] ?? 0),
            'total_budget' => (float) ($fuelBudgetPool['total_budget'] ?? 0),
            'used_budget' => (float) ($fuelBudgetPool['used_budget'] ?? 0),
            'remaining_diesel_budget' => (float) ($fuelBudgetPool['remaining_diesel_budget'] ?? 0),
            'remaining_unleaded_budget' => (float) ($fuelBudgetPool['remaining_unleaded_budget'] ?? 0),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        var lastFuelSummaryAmount = 0;
        var lastFuelSummaryDieselAmount = 0;
        var lastFuelSummaryUnleadedAmount = 0;
        var pendingSelectedCoaItems = [];

        function getIssuance(id) {
            return approvedIssuances.find(function(item) {
                return String(item.id) === String(id);
            });
        }

        function showToast(message) {
            var toastEl = document.getElementById('subAdminToast');
            document.getElementById('subAdminToastMessage').textContent = message;
            bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2400 }).show();
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function(character) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        }

        function formatLiters(value) {
            return Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatSummaryPeriod(startDate, endDate) {
            if (!startDate && !endDate) {
                return 'ALL DATES';
            }

            var start = startDate || endDate;
            var end = endDate || startDate;
            var startObj = new Date(start + 'T00:00:00');
            var endObj = new Date(end + 'T00:00:00');

            if (Number.isNaN(startObj.getTime()) || Number.isNaN(endObj.getTime())) {
                return (start || '') + (end && end !== start ? ' - ' + end : '');
            }

            var month = startObj.toLocaleString('en-US', { month: 'long' }).toUpperCase();
            if (startObj.getFullYear() === endObj.getFullYear() && startObj.getMonth() === endObj.getMonth()) {
                return month + ' ' + startObj.getDate() + '-' + endObj.getDate() + ', ' + startObj.getFullYear();
            }

            return start + ' - ' + end;
        }

        function fuelBucket(fuelType) {
            return String(fuelType || '').toLowerCase().indexOf('diesel') !== -1 ? 'diesel' : 'unleaded';
        }

        function updateSummaryBudgetHint(totalAmount) {
            var hint = document.getElementById('summaryBudgetHint');
            var remaining = Number(fuelBudgetPool.remaining_budget || 0) || 0;
            var dieselRemaining = Number(fuelBudgetPool.remaining_diesel_budget || 0) || 0;
            var unleadedRemaining = Number(fuelBudgetPool.remaining_unleaded_budget || 0) || 0;
            var afterSummary = remaining - totalAmount;

            if (remaining <= 0) {
                hint.textContent = 'No active IB budget remaining. Add another IB before printing this summary.';
                hint.className = 'text-danger d-block mt-1 fw-semibold';
                return;
            }

            hint.textContent = 'Diesel left: PHP ' + formatMoney(dieselRemaining) + '. Unleaded left: PHP ' + formatMoney(unleadedRemaining) + '. After summary total: PHP ' + formatMoney(afterSummary) + '.';
            hint.className = (afterSummary < 0 || lastFuelSummaryDieselAmount > dieselRemaining || lastFuelSummaryUnleadedAmount > unleadedRemaining) ? 'text-danger d-block mt-1 fw-semibold' : 'text-muted d-block mt-1';
        }

        function buildFuelSummaryParams(includeDeduct) {
            var params = new URLSearchParams({
                office: document.getElementById('summaryOfficeFilter').value,
                start_date: document.getElementById('summaryDateFrom').value,
                end_date: document.getElementById('summaryDateTo').value,
                diesel_price: document.getElementById('summaryDieselPrice').value || '0',
                unleaded_price: document.getElementById('summaryUnleadedPrice').value || '0'
            });

            if (includeDeduct) {
                params.set('deduct', '1');
            }

            return params;
        }

        function canSaveFuelSummaryDeduction() {
            if (lastFuelSummaryAmount <= 0) {
                showToast('Create a fuel summary with a computed amount first.');
                return false;
            }
            if ((Number(fuelBudgetPool.remaining_budget || 0) || 0) <= 0) {
                showToast('Add an active IB budget before saving the deduction.');
                return false;
            }
            if (lastFuelSummaryAmount > (Number(fuelBudgetPool.remaining_budget || 0) || 0)) {
                showToast('The fuel summary is higher than the active IB budget pool.');
                return false;
            }
            if (lastFuelSummaryDieselAmount > (Number(fuelBudgetPool.remaining_diesel_budget || 0) || 0)) {
                showToast('The diesel amount is higher than the active diesel allocation pool.');
                return false;
            }
            if (lastFuelSummaryUnleadedAmount > (Number(fuelBudgetPool.remaining_unleaded_budget || 0) || 0)) {
                showToast('The unleaded amount is higher than the active unleaded allocation pool.');
                return false;
            }
            return true;
        }

        function renderFuelSummary() {
            var officeFilter = document.getElementById('summaryOfficeFilter').value;
            var dateFrom = document.getElementById('summaryDateFrom').value;
            var dateTo = document.getElementById('summaryDateTo').value;
            var dieselPrice = Number(document.getElementById('summaryDieselPrice').value || 0) || 0;
            var unleadedPrice = Number(document.getElementById('summaryUnleadedPrice').value || 0) || 0;
            var report = document.getElementById('fuelSummaryReport');
            var filtered = approvedIssuances.filter(function(item) {
                var itemOffice = item.office || 'Office';
                var itemDate = item.date || '';
                var itemStatus = String(item.status || '').toLowerCase();
                return itemStatus === 'used'
                    && (!officeFilter || itemOffice === officeFilter)
                    && (!dateFrom || (itemDate && itemDate >= dateFrom))
                    && (!dateTo || (itemDate && itemDate <= dateTo));
            });

            var groups = {};
            filtered.forEach(function(item) {
                var office = item.office || 'Office';
                var vehicle = [item.vehicle_type || 'Vehicle', item.plate_no || ''].filter(Boolean).join(' ');
                var bucket = fuelBucket(item.fuel_type);
                var liters = Number(item.issued_liters || 0) || 0;

                if (!groups[office]) {
                    groups[office] = {};
                }
                if (!groups[office][vehicle]) {
                    groups[office][vehicle] = { diesel: 0, unleaded: 0 };
                }
                groups[office][vehicle][bucket] += liters;
            });

            var offices = Object.keys(groups).sort();
            var grandDiesel = 0;
            var grandUnleaded = 0;
            var rows = '';

            offices.forEach(function(office) {
                var officeDiesel = 0;
                var officeUnleaded = 0;
                rows += '<tr class="office-row"><td colspan="3">' + escapeHtml(office) + '</td></tr>';

                Object.keys(groups[office]).sort().forEach(function(vehicle) {
                    var totals = groups[office][vehicle];
                    officeDiesel += totals.diesel;
                    officeUnleaded += totals.unleaded;
                    rows += '<tr>' +
                        '<td>' + escapeHtml(vehicle) + '</td>' +
                        '<td class="text-end">' + (totals.diesel ? formatLiters(totals.diesel) : '') + '</td>' +
                        '<td class="text-end">' + (totals.unleaded ? formatLiters(totals.unleaded) : '') + '</td>' +
                        '</tr>';
                });

                grandDiesel += officeDiesel;
                grandUnleaded += officeUnleaded;
                rows += '<tr class="subtotal-row">' +
                    '<td class="text-end">Office Total</td>' +
                    '<td class="text-end">' + formatLiters(officeDiesel) + '</td>' +
                    '<td class="text-end">' + formatLiters(officeUnleaded) + '</td>' +
                    '</tr>';
            });

            if (offices.length === 0) {
                report.innerHTML = '<div class="fuel-summary-empty text-muted"><i class="fas fa-search fa-2x mb-2 d-block"></i>No fuel records found for this office and date range.</div>';
                lastFuelSummaryAmount = 0;
                lastFuelSummaryDieselAmount = 0;
                lastFuelSummaryUnleadedAmount = 0;
                updateSummaryBudgetHint(0);
                return;
            }

            var dieselAmount = grandDiesel * dieselPrice;
            var unleadedAmount = grandUnleaded * unleadedPrice;
            var grandAmount = dieselAmount + unleadedAmount;
            lastFuelSummaryAmount = grandAmount;
            lastFuelSummaryDieselAmount = dieselAmount;
            lastFuelSummaryUnleadedAmount = unleadedAmount;
            rows += '<tr class="grand-row">' +
                '<td class="text-end">TOTAL</td>' +
                '<td class="text-end">' + formatLiters(grandDiesel) + '</td>' +
                '<td class="text-end">' + formatLiters(grandUnleaded) + '</td>' +
                '</tr>' +
                '<tr class="grand-row">' +
                '<td class="text-end">PRICE / LITER</td>' +
                '<td class="text-end">₱' + formatMoney(dieselPrice) + '</td>' +
                '<td class="text-end">₱' + formatMoney(unleadedPrice) + '</td>' +
                '</tr>' +
                '<tr class="grand-row">' +
                '<td class="text-end">AMOUNT</td>' +
                '<td class="text-end">₱' + formatMoney(grandDiesel * dieselPrice) + '</td>' +
                '<td class="text-end">₱' + formatMoney(grandUnleaded * unleadedPrice) + '</td>' +
                '</tr>' +
                '<tr class="grand-row">' +
                '<td class="text-end" colspan="2">GRAND TOTAL</td>' +
                '<td class="text-end">₱' + formatMoney(grandAmount) + '</td>' +
                '</tr>';

            report.innerHTML =
                '<div class="fuel-summary-title">' +
                    '<div class="small fw-bold">SUMMARY COMPUTATION: <span class="period">' + escapeHtml(formatSummaryPeriod(dateFrom, dateTo)) + '</span></div>' +
                    '<div class="small text-uppercase">' + escapeHtml(officeFilter || 'All Offices') + '</div>' +
                '</div>' +
                '<table class="fuel-summary-table">' +
                    '<thead><tr><th>Vehicle</th><th>Diesel</th><th>Unleaded</th></tr></thead>' +
                    '<tbody>' + rows + '</tbody>' +
                '</table>';
            updateSummaryBudgetHint(grandAmount);
        }

        function buildTripParams(item) {
            return new URLSearchParams({
                serial_no: item.serial_no || '',
                date: item.date || '',
                driver: item.driver_name || '',
                vehicle: item.vehicle_type || '',
                plate_no: item.plate_no || '',
                office: item.office || '',
                purpose: item.purpose || 'OFFICIAL TRAVEL',
                gas_stock_issued: item.issued_liters || item.liters || '0',
                gas_issued: item.issued_liters || item.liters || '0'
            });
        }

        function buildCoaParams(item) {
            return buildSelectedCoaParams([item]);
        }

        function buildSelectedCoaParams(items) {
            var dates = items.map(function(item) {
                return item.date || '';
            }).filter(Boolean).sort();
            var ids = items.map(function(item) {
                return item.id;
            }).filter(Boolean);

            return new URLSearchParams({
                start_date: dates[0] || '',
                end_date: dates[dates.length - 1] || '',
                issuance_ids: ids.join(',')
            });
        }

        function groupSelectedCoaItems(items) {
            var groups = {};

            items.forEach(function(item) {
                var key = item.plate_no || item.vehicle_type || String(item.id);
                var normalKmPerLiter = Number(item.normal_km_per_liter || 0);
                if (!Number.isFinite(normalKmPerLiter) || normalKmPerLiter <= 0) {
                    normalKmPerLiter = 20;
                }
                if (!groups[key]) {
                    groups[key] = {
                        vehicle_type: item.vehicle_type || '',
                        plate_number: item.plate_no || '',
                        cylinders: item.cylinders || 4,
                        fuel_used: 0,
                        normal_km_per_liter: normalKmPerLiter,
                        remarks: item.office || '',
                        items: []
                    };
                }

                groups[key].fuel_used += Number(item.issued_liters || item.liters || 0) || 0;
                groups[key].items.push(item);
            });

            return Object.keys(groups).map(function(key) {
                return groups[key];
            });
        }

        function calculateZeroExcessEnding(beginning, fuelUsed, normalKmPerLiter) {
            var start = Number(beginning);
            var liters = Number(fuelUsed);
            var normal = Number(normalKmPerLiter);
            if (!Number.isFinite(start) || !Number.isFinite(liters) || !Number.isFinite(normal) || start < 0 || liters < 0 || normal <= 0) {
                return '';
            }

            return String(Math.round(start + ((liters * normal) / 1.10)));
        }

        function updateCoaAutoEnding(row) {
            var beginningInput = row.querySelector('.coa-beginning');
            var endingInput = row.querySelector('.coa-ending');
            if (!beginningInput || !endingInput) {
                return;
            }

            endingInput.value = calculateZeroExcessEnding(
                beginningInput.value,
                row.dataset.fuelUsed,
                row.dataset.normalKmPerLiter
            );
        }

        function renderCoaOdometerRows(items) {
            var tbody = document.getElementById('coaOdometerRows');
            var groups = groupSelectedCoaItems(items);

            tbody.innerHTML = groups.map(function(group, index) {
                var suggestedBeginning = Math.max.apply(null, group.items.map(function(item) {
                    return Number(item.current_odometer || item.past_odometer || 0) || 0;
                }).filter(function(value) {
                    return value > 0;
                }));
                if (!Number.isFinite(suggestedBeginning)) suggestedBeginning = '';
                var suggestedEnding = suggestedBeginning === '' ? '' : calculateZeroExcessEnding(suggestedBeginning, group.fuel_used, group.normal_km_per_liter);

                return '<tr data-coa-row="' + index + '" data-fuel-used="' + escapeHtml(group.fuel_used) + '" data-normal-km-per-liter="' + escapeHtml(group.normal_km_per_liter) + '">' +
                    '<td>' +
                        '<div class="fw-bold">' + escapeHtml(group.vehicle_type || '-') + '</div>' +
                        '<div class="small text-muted">' + group.items.length + ' selected issuance' + (group.items.length === 1 ? '' : 's') + '</div>' +
                    '</td>' +
                    '<td class="fw-semibold">' + escapeHtml(group.plate_number || '-') + '</td>' +
                    '<td class="text-end fw-bold">' + group.fuel_used.toFixed(2) + ' L</td>' +
                    '<td><input type="number" class="form-control form-control-sm coa-beginning" min="0" step="1" value="' + escapeHtml(suggestedBeginning) + '" required></td>' +
                    '<td><input type="number" class="form-control form-control-sm coa-ending bg-light" min="0" step="1" value="' + escapeHtml(suggestedEnding) + '" readonly tabindex="-1"></td>' +
                    '</tr>';
            }).join('');

            tbody.querySelectorAll('.coa-beginning').forEach(function(input) {
                input.addEventListener('input', function() {
                    updateCoaAutoEnding(this.closest('tr'));
                });
            });
        }

        function buildManualSelectedCoaParams(items) {
            var dates = items.map(function(item) {
                return item.date || '';
            }).filter(Boolean).sort();
            var groups = groupSelectedCoaItems(items);
            var rows = Array.from(document.querySelectorAll('#coaOdometerRows tr[data-coa-row]')).map(function(row, index) {
                var group = groups[index];
                var beginning = Math.round(Number(row.querySelector('.coa-beginning').value));
                var ending = Math.round(Number(row.querySelector('.coa-ending').value));

                return {
                    type_of_vehicle: group.vehicle_type,
                    plate_number: group.plate_number,
                    cylinders: group.cylinders,
                    beginning_odometer: beginning,
                    ending_odometer: ending,
                    fuel_used: group.fuel_used,
                    normal_km_per_liter: group.normal_km_per_liter,
                    remarks: group.remarks
                };
            });

            return new URLSearchParams({
                start_date: dates[0] || '',
                end_date: dates[dates.length - 1] || '',
                manual_odometer: '1',
                records: JSON.stringify(rows)
            });
        }

        function validateManualCoaOdometers() {
            var rows = Array.from(document.querySelectorAll('#coaOdometerRows tr[data-coa-row]'));
            for (var index = 0; index < rows.length; index++) {
                var row = rows[index];
                var beginningInput = row.querySelector('.coa-beginning');
                var endingInput = row.querySelector('.coa-ending');
                var beginning = Number(beginningInput.value);
                updateCoaAutoEnding(row);
                var ending = Number(endingInput.value);

                beginningInput.classList.remove('is-invalid');
                endingInput.classList.remove('is-invalid');

                if (!Number.isFinite(beginning) || beginning < 0) {
                    beginningInput.classList.add('is-invalid');
                    showToast('Enter a valid beginning odometer reading.');
                    return false;
                }
                if (!Number.isFinite(ending) || ending < 0) {
                    endingInput.classList.add('is-invalid');
                    showToast('Ending odometer could not be computed. Check fuel used and normal km/liter.');
                    return false;
                }
                if (ending < beginning) {
                    endingInput.classList.add('is-invalid');
                    showToast('Ending odometer cannot be lower than beginning odometer.');
                    return false;
                }
            }

            return rows.length > 0;
        }

        function getSelectedIssuances() {
            return Array.from(document.querySelectorAll('.monthly-select:checked'))
                .map(function(checkbox) {
                    return getIssuance(checkbox.value);
                })
                .filter(Boolean);
        }

        function formatMonthlyLabel(items) {
            var dates = items.map(function(item) {
                return item.date || '';
            }).filter(Boolean).sort();

            if (dates.length === 0) {
                return '';
            }

            var first = new Date(dates[0] + 'T00:00:00');
            var last = new Date(dates[dates.length - 1] + 'T00:00:00');
            var monthName = first.toLocaleString('en-US', { month: 'long' }).toUpperCase();

            if (first.getFullYear() === last.getFullYear() && first.getMonth() === last.getMonth()) {
                return monthName + ' ' + first.getDate() + '-' + last.getDate() + ', ' + first.getFullYear();
            }

            return dates[0] + ' - ' + dates[dates.length - 1];
        }

        function buildMonthlyFormBParams(items) {
            var first = items[0];
            var ids = items.map(function(item) {
                return item.id;
            }).filter(Boolean);

            return new URLSearchParams({
                vehicle: first.vehicle_type || '',
                plate_no: first.plate_no || '',
                date: formatMonthlyLabel(items),
                driver: first.driver_name || '',
                issuance_ids: ids.join(',')
            });
        }

        var tableCurrentPage = 1;
        var tablePageSize = 25;

        function getFilteredTableRows() {
            return Array.from(document.querySelectorAll('#subAdminTableBody tr[data-search]')).filter(function(row) {
                return row.dataset.filterMatch !== 'false';
            });
        }

        function renderTablePage() {
            var rows = Array.from(document.querySelectorAll('#subAdminTableBody tr[data-search]'));
            var filteredRows = getFilteredTableRows();
            var totalPages = Math.max(1, Math.ceil(filteredRows.length / tablePageSize));
            tableCurrentPage = Math.min(Math.max(1, tableCurrentPage), totalPages);
            var startIndex = (tableCurrentPage - 1) * tablePageSize;
            var endIndex = Math.min(startIndex + tablePageSize, filteredRows.length);
            var pageRows = new Set(filteredRows.slice(startIndex, endIndex));

            rows.forEach(function(row) {
                row.classList.toggle('d-none', !pageRows.has(row));
            });

            var status = document.getElementById('tablePaginationStatus');
            var pageLabel = document.getElementById('tablePageLabel');
            var previous = document.getElementById('tablePrevPage');
            var next = document.getElementById('tableNextPage');
            var noResults = document.getElementById('noFilterResults');

            status.textContent = filteredRows.length === 0
                ? 'Showing 0 records'
                : 'Showing ' + (startIndex + 1) + '-' + endIndex + ' of ' + filteredRows.length + ' records';
            pageLabel.textContent = 'Page ' + tableCurrentPage + ' of ' + totalPages;
            previous.disabled = tableCurrentPage <= 1;
            next.disabled = tableCurrentPage >= totalPages;
            if (noResults) {
                noResults.classList.toggle('d-none', filteredRows.length !== 0);
            }

            updateMonthlySelectionState();
        }

        function updateMonthlySelectionState() {
            var selected = getSelectedIssuances();
            var printButton = document.getElementById('printMonthlyBtn');
            var selectedTripsButton = document.getElementById('printSelectedTripsBtn');
            var selectedCoaButton = document.getElementById('printSelectedCoaBtn');
            var summary = document.getElementById('selectedSummary');
            var selectVisible = document.getElementById('selectVisibleRows');
            var plates = Array.from(new Set(selected.map(function(item) {
                return item.plate_no || '';
            }).filter(Boolean)));
            var selectedLiters = selected.reduce(function(total, item) {
                return total + (Number(item.issued_liters || item.liters) || 0);
            }, 0);

            printButton.disabled = selected.length === 0;
            selectedTripsButton.disabled = selected.length === 0;
            selectedCoaButton.disabled = selected.length === 0;

            if (selected.length === 0) {
                summary.textContent = 'Select one or more gas issuances. Monthly Form B will be grouped per vehicle.';
            } else {
                summary.textContent = 'Selected ' + selected.length + ' records across ' + (plates.length || 1) + ' vehicle' + (plates.length === 1 ? '' : 's') + '. Issued fuel total: ' + selectedLiters.toFixed(2) + ' L.';
            }

            var visibleCheckboxes = Array.from(document.querySelectorAll('#subAdminTableBody tr[data-search]:not(.d-none) .monthly-select'));
            var checkedVisible = visibleCheckboxes.filter(function(checkbox) {
                return checkbox.checked;
            });
            selectVisible.checked = visibleCheckboxes.length > 0 && checkedVisible.length === visibleCheckboxes.length;
            selectVisible.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visibleCheckboxes.length;
        }

        function applyFilters() {
            var query = document.getElementById('subAdminSearch').value.trim().toLowerCase();
            var vehicle = document.getElementById('vehicleFilter').value;
            var dateFrom = document.getElementById('dateFromFilter').value;
            var dateTo = document.getElementById('dateToFilter').value;
            var rows = Array.from(document.querySelectorAll('#subAdminTableBody tr[data-search]'));

            rows.forEach(function(row) {
                var matchesQuery = !query || row.dataset.search.indexOf(query) !== -1;
                var matchesVehicle = !vehicle || row.dataset.plate === vehicle;
                var rowDate = row.dataset.date || '';
                var matchesFrom = !dateFrom || (rowDate && rowDate >= dateFrom);
                var matchesTo = !dateTo || (rowDate && rowDate <= dateTo);
                var show = matchesQuery && matchesVehicle && matchesFrom && matchesTo;
                row.dataset.filterMatch = show ? 'true' : 'false';
                if (!show) {
                    var checkbox = row.querySelector('.monthly-select');
                    if (checkbox) {
                        checkbox.checked = false;
                    }
                }
            });

            tableCurrentPage = 1;
            renderTablePage();
        }

        document.getElementById('subAdminSearch').addEventListener('input', applyFilters);
        document.getElementById('vehicleFilter').addEventListener('change', applyFilters);
        document.getElementById('dateFromFilter').addEventListener('change', applyFilters);
        document.getElementById('dateToFilter').addEventListener('change', applyFilters);
        document.getElementById('clearFiltersBtn').addEventListener('click', function() {
            document.getElementById('subAdminSearch').value = '';
            document.getElementById('vehicleFilter').value = '';
            document.getElementById('dateFromFilter').value = '';
            document.getElementById('dateToFilter').value = '';
            applyFilters();
        });
        document.getElementById('tablePageSize').addEventListener('change', function() {
            tablePageSize = Number(this.value) || 25;
            tableCurrentPage = 1;
            renderTablePage();
        });
        document.getElementById('tablePrevPage').addEventListener('click', function() {
            if (tableCurrentPage > 1) {
                tableCurrentPage--;
                renderTablePage();
            }
        });
        document.getElementById('tableNextPage').addEventListener('click', function() {
            var totalPages = Math.max(1, Math.ceil(getFilteredTableRows().length / tablePageSize));
            if (tableCurrentPage < totalPages) {
                tableCurrentPage++;
                renderTablePage();
            }
        });

        document.getElementById('summaryOfficeFilter').addEventListener('change', renderFuelSummary);
        document.getElementById('summaryDateFrom').addEventListener('change', renderFuelSummary);
        document.getElementById('summaryDateTo').addEventListener('change', renderFuelSummary);
        document.getElementById('summaryDieselPrice').addEventListener('input', renderFuelSummary);
        document.getElementById('summaryUnleadedPrice').addEventListener('input', renderFuelSummary);
        document.getElementById('summaryClearBtn').addEventListener('click', function() {
            document.getElementById('summaryOfficeFilter').value = '';
            document.getElementById('summaryDateFrom').value = '';
            document.getElementById('summaryDateTo').value = '';
            document.getElementById('summaryDieselPrice').value = '';
            document.getElementById('summaryUnleadedPrice').value = '';
            renderFuelSummary();
        });
        document.getElementById('fuelSummaryModal').addEventListener('shown.bs.modal', function() {
            var tableFrom = document.getElementById('dateFromFilter').value;
            var tableTo = document.getElementById('dateToFilter').value;
            if (tableFrom && !document.getElementById('summaryDateFrom').value) {
                document.getElementById('summaryDateFrom').value = tableFrom;
            }
            if (tableTo && !document.getElementById('summaryDateTo').value) {
                document.getElementById('summaryDateTo').value = tableTo;
            }
            renderFuelSummary();
        });
        document.getElementById('printFuelSummaryBtn').addEventListener('click', function() {
            var params = buildFuelSummaryParams(false);
            window.open('fuel_summary_pdf.php?' + params.toString(), '_blank', 'noopener');
            showToast('Opening fuel summary PDF...');
        });

        document.getElementById('saveFuelSummaryDeductionBtn').addEventListener('click', async function() {
            if (!canSaveFuelSummaryDeduction()) {
                return;
            }

            var button = this;
            var originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

            try {
                var params = buildFuelSummaryParams(false);
                var response = await fetch('fuel_summary_deduction_save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(Object.fromEntries(params.entries()))
                });
                var payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Unable to save fuel summary deduction.');
                }

                if (payload.budget_summary) {
                    fuelBudgetPool.remaining_budget = Number(payload.budget_summary.remaining_budget || 0) || 0;
                    fuelBudgetPool.remaining_diesel_budget = Number(payload.budget_summary.remaining_diesel_budget || 0) || 0;
                    fuelBudgetPool.remaining_unleaded_budget = Number(payload.budget_summary.remaining_unleaded_budget || 0) || 0;
                }
                renderFuelSummary();
                showToast(payload.message || 'Fuel summary deduction saved.');
            } catch (error) {
                showToast(error.message);
            } finally {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        });

        document.getElementById('selectVisibleRows').addEventListener('change', function() {
            var visibleCheckboxes = document.querySelectorAll('#subAdminTableBody tr[data-search]:not(.d-none) .monthly-select');
            visibleCheckboxes.forEach(function(checkbox) {
                checkbox.checked = document.getElementById('selectVisibleRows').checked;
            });
            updateMonthlySelectionState();
        });

        document.getElementById('subAdminTableBody').addEventListener('change', function(event) {
            if (event.target.classList.contains('monthly-select')) {
                updateMonthlySelectionState();
            }
        });

        document.getElementById('printMonthlyBtn').addEventListener('click', function() {
            var selected = getSelectedIssuances();

            if (selected.length === 0) {
                showToast('Select at least one issuance for the monthly report.');
                return;
            }

            var groups = {};
            selected.forEach(function(item) {
                var key = item.plate_no || item.vehicle_type || String(item.id);
                if (!groups[key]) {
                    groups[key] = [];
                }
                groups[key].push(item);
            });

            Object.keys(groups).forEach(function(key) {
                window.open('monthly_official_travels.php?' + buildMonthlyFormBParams(groups[key]).toString(), '_blank', 'noopener');
            });

            showToast('Opening ' + Object.keys(groups).length + ' monthly Form B report' + (Object.keys(groups).length === 1 ? '' : 's') + '...');
        });

        document.getElementById('printSelectedTripsBtn').addEventListener('click', function() {
            var selected = getSelectedIssuances();

            if (selected.length === 0) {
                showToast('Select at least one issuance for trip ticket printing.');
                return;
            }

            var ids = selected.map(function(item) {
                return item.id;
            }).filter(Boolean);
            var params = new URLSearchParams({
                issuance_ids: ids.join(',')
            });
            window.open('trip_ticket_batch.php?' + params.toString(), '_blank', 'noopener');

            showToast('Opening one PDF with ' + selected.length + ' trip ticket page' + (selected.length === 1 ? '' : 's') + '...');
        });

        document.getElementById('printSelectedCoaBtn').addEventListener('click', function() {
            var selected = getSelectedIssuances();

            if (selected.length === 0) {
                showToast('Select at least one issuance for the COA report.');
                return;
            }

            pendingSelectedCoaItems = selected;
            renderCoaOdometerRows(selected);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('coaOdometerModal')).show();
        });

        document.getElementById('confirmSelectedCoaBtn').addEventListener('click', function() {
            if (pendingSelectedCoaItems.length === 0) {
                showToast('Select at least one issuance for the COA report.');
                return;
            }
            if (!validateManualCoaOdometers()) {
                return;
            }

            window.open('coa_report.php?' + buildManualSelectedCoaParams(pendingSelectedCoaItems).toString(), '_blank', 'noopener');
            var odometerModal = bootstrap.Modal.getInstance(document.getElementById('coaOdometerModal'));
            if (odometerModal) {
                odometerModal.hide();
            }
            showToast('Opening selected COA report with manual odometer readings.');
        });

        document.getElementById('subAdminTableBody').addEventListener('click', function(event) {
            var tripButton = event.target.closest('.action-print-trip');
            var coaButton = event.target.closest('.action-print-coa');
            var button = tripButton || coaButton;

            if (!button) {
                return;
            }

            var item = getIssuance(button.dataset.id);
            if (!item) {
                return;
            }

            if (tripButton) {
                window.open('trip_ticket.php?' + buildTripParams(item).toString(), '_blank', 'noopener');
                showToast('Opening trip ticket for ' + item.serial_no + '...');
                return;
            }

            if (coaButton) {
                window.open('coa_report.php?' + buildCoaParams(item).toString(), '_blank', 'noopener');
                showToast('Opening COA report for ' + item.serial_no + '...');
                return;
            }
        });
        applyFilters();
    </script>
</body>

</html>
