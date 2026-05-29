<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';
require_once __DIR__ . '/fuel_budget_data.php';

$today = date('Y-m-d');
$nextWeek = date('Y-m-d', strtotime('+7 days'));

$vehicles = fuelTrackerFetchVehicles($conn);
fuelTrackerSyncIssuanceOffices($conn);
fuelTrackerCreateUpcomingScheduledIssuances($conn, 14);
$allIssuances = fuelTrackerFetchGasIssuances($conn);
$recentIssuances = array_slice($allIssuances, 0, 5);
$vehicleLookup = fuelTrackerVehicleLookupByPlate($vehicles);
$serialNo = 'FI-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$defaultExpiryDate = date('Y-m-d', strtotime('+7 days'));
$budgetSummary = fuelBudgetSummary($conn);
$dieselBudgetRemaining = (float) ($budgetSummary['remaining_diesel_budget'] ?? 0);
$dieselBudgetTotal = (float) ($budgetSummary['total_diesel_budget'] ?? 0);
$dieselBudgetPercent = $dieselBudgetTotal > 0 ? max(0, min(100, ($dieselBudgetRemaining / $dieselBudgetTotal) * 100)) : 0;
$unleadedBudgetRemaining = (float) ($budgetSummary['remaining_unleaded_budget'] ?? 0);
$unleadedBudgetTotal = (float) ($budgetSummary['total_unleaded_budget'] ?? 0);
$unleadedBudgetPercent = $unleadedBudgetTotal > 0 ? max(0, min(100, ($unleadedBudgetRemaining / $unleadedBudgetTotal) * 100)) : 0;
$budgetUsed = (float) ($budgetSummary['used_budget'] ?? 0);
$budgetTotal = (float) ($budgetSummary['total_budget'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gas Issuance</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="fuel_dashboard.css">

    <style>
        .issuance-shell {
            max-width: 1600px;
        }

        .summary-value {
            font-size: 1.35rem;
            line-height: 1.2;
        }

        .issuance-preview dt {
            color: #6c757d;
            font-weight: 600;
        }

        .issuance-preview dd {
            font-weight: 600;
        }

        .issuance-page-header {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }
        .issuance-page-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        .issuance-sidebar {
            position: sticky;
            top: 1rem;
        }
        .active-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            min-height: 0;
        }
        .active-filter-chips .filter-chip {
            align-items: center;
            background: #e7f1ff;
            border: 1px solid #b6d4fe;
            border-radius: 999px;
            color: #084298;
            display: inline-flex;
            font-size: 0.78rem;
            font-weight: 600;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
        }
        .active-filter-chips .filter-chip button {
            background: none;
            border: 0;
            color: inherit;
            line-height: 1;
            padding: 0;
        }
        .schedule-dropdown-menu {
            max-height: 260px;
            overflow-y: auto;
            width: 100%;
        }
        .schedule-dropdown-menu .dropdown-item {
            align-items: center;
            border-radius: 6px;
            display: flex;
            font-size: 0.9rem;
            gap: 0.25rem;
            margin-bottom: 0.1rem;
        }

        /* ---------- Mini Calendar ---------- */
        .mini-calendar {
            font-size: 0.8rem;
            user-select: none;
        }
        .mini-calendar .mc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .mini-calendar .mc-header .mc-month {
            font-weight: 700;
            font-size: 0.9rem;
        }
        .mini-calendar .mc-header button {
            background: none;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 0.15rem 0.45rem;
            font-size: 0.75rem;
            cursor: pointer;
            color: #495057;
        }
        .mini-calendar .mc-header button:hover {
            background: #e9ecef;
        }
        .mini-calendar .mc-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
        }
        .mini-calendar .mc-grid .day-label {
            font-weight: 600;
            color: #6c757d;
            padding: 0.2rem 0;
            font-size: 0.7rem;
        }
        .mini-calendar .mc-grid .day-cell {
            padding: 0.3rem 0;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
        }
        .mini-calendar .mc-grid .day-cell:hover {
            background: #e9ecef;
        }
        .mini-calendar .mc-grid .day-cell.other-month {
            color: #ced4da;
        }
        .mini-calendar .mc-grid .day-cell.today {
            font-weight: 700;
            background: #e8f4fd;
            border-radius: 4px;
        }
        .mini-calendar .mc-grid .day-cell.selected {
            background: #0d6efd;
            color: #fff;
            border-radius: 50%;
            font-weight: 700;
        }
        .mini-calendar .mc-grid .day-cell.has-issuance::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #dc3545;
        }
        .mini-calendar .mc-grid .day-cell.selected.has-issuance::after {
            background: #fff;
        }
        .mini-calendar .mc-grid .day-cell .issuance-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #0d6efd;
            color: #fff;
            font-size: 0.55rem;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .calendar-schedule-list {
            border-top: 1px solid #e9ecef;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
        }
        .calendar-schedule-item {
            align-items: flex-start;
            background: #f8fbff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            display: flex;
            gap: 0.5rem;
            padding: 0.5rem 0.6rem;
        }
        .calendar-schedule-item + .calendar-schedule-item {
            margin-top: 0.45rem;
        }
        .calendar-schedule-item .schedule-dot {
            background: #0d6efd;
            border-radius: 50%;
            flex: 0 0 8px;
            height: 8px;
            margin-top: 0.35rem;
            width: 8px;
        }
        .calendar-schedule-item .schedule-title {
            color: #173b23;
            display: block;
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .calendar-schedule-item .schedule-meta {
            color: #667085;
            display: block;
            font-size: 0.75rem;
            line-height: 1.25;
        }

        /* ---------- Status badges ---------- */
        .summary-stats .stat-card {
            text-align: center;
            padding: 0.6rem 0.3rem;
            border-radius: 6px;
        }
        .summary-stats .stat-card .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
        }
        .summary-stats .stat-card .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .budget-status-card .budget-row {
            display: grid;
            gap: 0.35rem;
            margin-bottom: 0.9rem;
        }
        .budget-status-card .budget-head,
        .budget-status-card .budget-meta {
            align-items: baseline;
            display: flex;
            gap: 0.75rem;
            justify-content: space-between;
        }
        .budget-status-card .budget-label {
            color: #6c757d;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .budget-status-card .budget-amount {
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.15;
            overflow-wrap: anywhere;
            text-align: right;
        }
        .budget-status-card .budget-track {
            background: #eef2f6;
            border-radius: 999px;
            height: 9px;
            overflow: hidden;
        }
        .budget-status-card .budget-fill {
            border-radius: inherit;
            display: block;
            height: 100%;
            min-width: 2px;
        }
        .budget-status-card .budget-fill-diesel {
            background: #f5b301;
        }
        .budget-status-card .budget-fill-unleaded {
            background: #198754;
        }
        .budget-status-card .budget-meta {
            color: #6c757d;
            font-size: 0.72rem;
        }
        .budget-status-card .budget-total-row {
            border-top: 1px solid #edf1f5;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding-top: 0.9rem;
        }
        .budget-status-card .budget-total-row small {
            color: #6c757d;
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .budget-status-card .budget-total-row span {
            display: block;
            font-weight: 800;
            overflow-wrap: anywhere;
            text-align: right;
        }

        /* ---------- Schedule table ---------- */
        .issuance-schedule-card {
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .schedule-card-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 1rem 1.25rem;
        }
        .schedule-title-wrap {
            align-items: center;
            display: flex;
            gap: 0.75rem;
        }
        .schedule-title-icon {
            align-items: center;
            background: #e7f1ff;
            border-radius: 0.65rem;
            color: #0d6efd;
            display: inline-flex;
            height: 42px;
            justify-content: center;
            width: 42px;
        }
        .schedule-toolbar {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.65rem;
            display: grid;
            gap: 0.6rem;
            grid-template-columns: minmax(220px, 1.4fr) minmax(130px, 0.7fr) minmax(150px, 0.8fr) auto;
            padding: 0.65rem;
            width: 100%;
        }
        .schedule-toolbar .input-group-text,
        .schedule-toolbar .form-control,
        .schedule-toolbar .form-select,
        .schedule-toolbar .btn {
            border-color: #dee2e6;
            min-height: 36px;
        }
        .schedule-toolbar .input-group-text {
            background: #fff;
            color: #6c757d;
        }
        .schedule-toolbar .btn {
            white-space: nowrap;
        }
        .schedule-toolbar .form-control:focus,
        .schedule-toolbar .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.14);
        }
        .schedule-table-wrap {
            background: #fff;
            max-height: 62vh;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .schedule-table {
            min-width: 100%;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .schedule-table th {
            border-bottom: 1px solid #e9ecef !important;
            color: #64748b;
            font-size: 0.76rem;
            letter-spacing: 0.04em;
            padding: 0.8rem 0.75rem;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .schedule-table thead th {
            background: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .schedule-table td {
            border-bottom: 1px solid #eef2f7;
            color: #334155;
            font-size: 0.88rem;
            padding: 0.85rem 0.75rem;
            vertical-align: middle;
        }
        .schedule-table tbody tr {
            transition: background-color 0.16s ease, box-shadow 0.16s ease;
        }
        .schedule-table tbody tr:hover {
            background: #f8fbff;
        }
        .schedule-table .serial-cell {
            color: #6c757d;
            font-size: 0.8rem;
            max-width: 145px;
            overflow-wrap: anywhere;
            white-space: normal;
        }
        .schedule-table .plate-cell {
            min-width: 132px;
        }
        .schedule-table .plate-value {
            color: #212529;
            font-weight: 700;
        }
        .schedule-table .vehicle-type {
            color: #6c757d;
            display: block;
            font-size: 0.78rem;
            margin-top: 0.1rem;
        }
        .schedule-table .liters-cell {
            font-variant-numeric: tabular-nums;
            text-align: right;
            white-space: nowrap;
        }
        .schedule-table .expiry-note {
            display: block;
            font-size: 0.72rem;
            margin-top: 0.15rem;
        }
        .schedule-table .actions-column {
            position: sticky;
            right: 0;
            z-index: 2;
        }
        .schedule-table td.actions-column {
            background: inherit;
            box-shadow: -6px 0 12px rgba(0, 0, 0, 0.04);
        }
        .schedule-table thead th.actions-column {
            background: #f8fafc;
            z-index: 11;
            box-shadow: -6px 0 12px rgba(0, 0, 0, 0.04);
        }
        .schedule-table .badge {
            border-radius: 999px;
            font-size: 0.72rem;
            min-width: 68px;
            padding: 0.4rem 0.55rem;
        }
        .schedule-table .actions-column {
            width: 126px;
        }
        .issuance-row-actions {
            display: inline-flex;
            gap: 0.35rem;
            white-space: nowrap;
        }
        .issuance-row-actions .btn {
            align-items: center;
            background: #fff;
            display: inline-flex;
            height: 30px;
            justify-content: center;
            padding: 0;
            width: 30px;
        }
        .issuance-action-toast {
            position: fixed;
            right: 1rem;
            top: 4.5rem;
            z-index: 1080;
        }
        .issuance-detail-list dt {
            color: #6c757d;
            font-size: 0.78rem;
            text-transform: uppercase;
        }
        .issuance-detail-list dd {
            font-weight: 600;
            overflow-wrap: anywhere;
        }
        .vehicle-manager-layout {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(230px, 0.75fr) minmax(0, 1.25fr);
        }
        .vehicle-manager-list {
            border: 1px solid #e9ecef;
            border-radius: 0.65rem;
            max-height: 470px;
            overflow: auto;
        }
        .vehicle-manager-item {
            background: #fff;
            border: 0;
            border-bottom: 1px solid #eef2f7;
            color: #334155;
            display: block;
            padding: 0.75rem 0.85rem;
            text-align: left;
            width: 100%;
        }
        .vehicle-manager-item:last-child {
            border-bottom: 0;
        }
        .vehicle-manager-item:hover,
        .vehicle-manager-item.active {
            background: #f0f8f0;
        }
        .vehicle-manager-item .plate {
            color: #18351c;
            display: block;
            font-weight: 800;
        }
        .vehicle-manager-item .meta {
            color: #64748b;
            display: block;
            font-size: 0.8rem;
            margin-top: 0.1rem;
        }
        .vehicle-detail-panel {
            border: 1px solid #e9ecef;
            border-radius: 0.65rem;
            padding: 1rem;
        }
        .vehicle-form-section {
            border-top: 1px solid #eef2f7;
            margin-top: 1rem;
            padding-top: 1rem;
        }
        .vehicle-form-section:first-of-type {
            border-top: 0;
            margin-top: 0;
            padding-top: 0;
        }
        .vehicle-section-title {
            color: #475569;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
        }
        .vehicle-odometer-reference {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.6rem;
            padding: 0.8rem 0.9rem;
        }
        .vehicle-odometer-reference .label {
            color: #64748b;
            display: block;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .vehicle-odometer-reference .value {
            color: #18351c;
            display: block;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.2;
            margin-top: 0.2rem;
        }
        @media (max-width: 1199.98px) {
            .issuance-sidebar {
                position: static;
            }
        }
        @media (max-width: 767.98px) {
            .issuance-page-header {
                flex-direction: column;
            }
            .issuance-page-actions,
            .issuance-page-actions .btn,
            .schedule-toolbar .btn {
                width: 100%;
            }
            .schedule-table {
                min-width: 820px;
            }
            .schedule-card-header {
                padding: 1rem;
            }
            .schedule-toolbar {
                grid-template-columns: 1fr;
            }
            .vehicle-manager-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="fuel_dashboard.php">
                <i class="fas fa-gas-pump text-primary me-2"></i>
                <span class="fw-bold text-dark">Fuel Tracker</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="fuel_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="gas_issuance.php">
                            <i class="fas fa-receipt me-1"></i>Gas Issuance
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="../logo.png" alt="User" class="rounded-circle me-2" width="32" height="32">
                            <span class="text-dark"><?php echo isset($_SESSION['pay_name']) ? htmlspecialchars((string) $_SESSION['pay_name']) : 'User'; ?></span>
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

    <main class="container-fluid issuance-shell mt-4">
        <div class="issuance-page-header mb-4">
            <div>
                <h1 class="mb-1">Gas Issuance</h1>
                <p class="text-muted mb-0">Prepare and track vehicle fuel issuance vouchers.</p>
            </div>
            <div class="issuance-page-actions">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIssuanceModal">
                    <i class="fas fa-plus-circle me-1"></i>Create Issuance
                </button>
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                    <i class="fas fa-car me-1"></i>Add Vehicle
                </button>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vehicleManagerModal">
                    <i class="fas fa-clipboard-list me-1"></i>Vehicle Details
                </button>
                <a href="fuel_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Dashboard
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- ========== MAIN COLUMN ========== -->
            <div class="col-xl-9">
                <!-- Scheduled Issuances Table -->
                <div class="card border-0 shadow-sm issuance-schedule-card">
                    <div class="card-header schedule-card-header border-bottom">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div class="schedule-title-wrap">
                                <span class="schedule-title-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <div>
                                    <h5 class="card-title mb-0">Scheduled Issuances</h5>
                                    <small class="text-muted">Filter and manage gas issuance vouchers.</small>
                                </div>
                            </div>
                        </div>
                        <div class="schedule-toolbar">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control" id="filterSearch" placeholder="Search plate, driver, or office">
                            </div>
                            <select class="form-select form-select-sm" id="filterStatus" aria-label="Filter status">
                                <option value="">All Status</option>
                                <option value="draft">Draft</option>
                                <option value="approved">Approved</option>
                                <option value="valid">Valid</option>
                                <option value="used">Used</option>
                                <option value="expired">Expired</option>
                                <option value="revoked">Revoked</option>
                            </select>
                            <input type="date" class="form-control form-control-sm" id="filterDate" aria-label="Filter issue date">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFiltersBtn" title="Clear filters">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                        <div class="active-filter-chips w-100 mt-2" id="activeFilterChips" aria-live="polite"></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive schedule-table-wrap">
                            <table class="table table-hover mb-0 schedule-table" id="scheduleTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Serial #</th>
                                        <th>Vehicle</th>
                                        <th>Office</th>
                                        <th>Driver</th>
                                        <th class="text-end">Liters</th>
                                        <th>Issue Date</th>
                                        <th>Expiry</th>
                                        <th class="text-center">Approved</th>
                                        <th>Status</th>
                                        <th class="actions-column text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="scheduleTableBody">
                                    <?php if (empty($allIssuances)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">No issuance records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($allIssuances as $iss): 
                                            $s = strtolower((string) ($iss['status'] ?? 'valid'));
                                            $badge = 'bg-success';
                                            if ($s === 'used') $badge = 'bg-primary';
                                            elseif ($s === 'approved') $badge = 'bg-success';
                                            elseif ($s === 'draft') $badge = 'bg-warning text-dark';
                                            elseif ($s === 'expired') $badge = 'bg-secondary';
                                            elseif ($s === 'revoked') $badge = 'bg-danger';
                                            $isApproved = in_array($s, ['approved', 'valid', 'used'], true);
                                            $approvalDisabled = in_array($s, ['used', 'expired', 'revoked'], true);
                                            $expiryDate = (string) ($iss['expiry_date'] ?? '');
                                            $expiryNote = '';
                                            if ($s === 'valid' && $expiryDate !== '') {
                                                if ($expiryDate === $today) {
                                                    $expiryNote = '<span class="expiry-note text-warning">Expires today</span>';
                                                } elseif ($expiryDate < $today) {
                                                    $expiryNote = '<span class="expiry-note text-danger">Overdue</span>';
                                                }
                                            }
                                        ?>
                                        <tr data-status="<?php echo htmlspecialchars($s); ?>"
                                            data-plate="<?php echo htmlspecialchars(strtolower($iss['plate_no'] ?? '')); ?>"
                                            data-office="<?php echo htmlspecialchars(strtolower($iss['office'] ?? '')); ?>"
                                            data-driver="<?php echo htmlspecialchars(strtolower($iss['driver_name'] ?? '')); ?>"
                                            data-issue="<?php echo htmlspecialchars($iss['issue_date'] ?? ''); ?>">
                                            <td class="font-monospace serial-cell"><?php echo htmlspecialchars((string) $iss['serial_no']); ?></td>
                                            <td class="plate-cell">
                                                <span class="plate-value"><?php echo htmlspecialchars((string) ($iss['plate_no'] ?? '-')); ?></span>
                                                <span class="vehicle-type"><?php echo htmlspecialchars((string) ($iss['vehicle_type'] ?? '-')); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars((string) ($iss['office'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($iss['driver_name'] ?? '-')); ?></td>
                                            <td class="liters-cell"><?php echo htmlspecialchars((string) ($iss['authorized_liters'] ?? '0')) . ' L'; ?></td>
                                            <td><?php echo htmlspecialchars($iss['issue_date'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($expiryDate !== '' ? $expiryDate : '-'); ?><?php echo $expiryNote; ?></td>
                                            <td class="text-center">
                                                <input class="form-check-input issuance-approval-checkbox"
                                                    type="checkbox"
                                                    data-id="<?php echo htmlspecialchars((string) $iss['id']); ?>"
                                                    aria-label="Approve issuance <?php echo htmlspecialchars((string) $iss['serial_no']); ?>"
                                                    <?php echo $isApproved ? 'checked' : ''; ?>
                                                    <?php echo $approvalDisabled ? 'disabled' : ''; ?>>
                                            </td>
                                            <td><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars(ucfirst($s)); ?></span></td>
                                            <td class="actions-column text-center">
                                                <div class="issuance-row-actions" role="group" aria-label="Issuance actions">
                                                    <button type="button"
                                                        class="btn btn-outline-primary btn-sm action-view-issuance"
                                                        data-id="<?php echo htmlspecialchars((string) $iss['id']); ?>"
                                                        data-serial="<?php echo htmlspecialchars((string) $iss['serial_no']); ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="View issuance"
                                                        aria-label="View issuance <?php echo htmlspecialchars((string) $iss['serial_no']); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-outline-warning btn-sm action-edit-issuance"
                                                        data-id="<?php echo htmlspecialchars((string) $iss['id']); ?>"
                                                        data-serial="<?php echo htmlspecialchars((string) $iss['serial_no']); ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Edit issuance"
                                                        aria-label="Edit issuance <?php echo htmlspecialchars((string) $iss['serial_no']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-outline-success btn-sm action-print-gas"
                                                        data-id="<?php echo htmlspecialchars((string) $iss['id']); ?>"
                                                        data-serial="<?php echo htmlspecialchars((string) $iss['serial_no']); ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Print gas issuance PDF"
                                                        aria-label="Print gas issuance <?php echo htmlspecialchars((string) $iss['serial_no']); ?>">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm action-delete-issuance"
                                                        data-id="<?php echo htmlspecialchars((string) $iss['id']); ?>"
                                                        data-serial="<?php echo htmlspecialchars((string) $iss['serial_no']); ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Delete issuance"
                                                        aria-label="Delete issuance <?php echo htmlspecialchars((string) $iss['serial_no']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <tr id="noFilteredIssuancesRow" class="d-none">
                                        <td colspan="10" class="text-center text-muted py-4">
                                            <i class="fas fa-filter me-1"></i>No issuances match the current filters.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== RIGHT SIDEBAR ========== -->
            <div class="col-xl-3">
                <div class="issuance-sidebar">
                <!-- Mini Calendar -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-days me-2"></i>Issuance Calendar
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="miniCalendar" class="mini-calendar"></div>
                        <div class="mt-2 text-muted small">
                            <i class="fas fa-circle text-danger me-1" style="font-size:0.5rem;"></i> Days with scheduled issuances
                        </div>
                        <div class="mt-1 small" id="selectedDateLabel">
                            <i class="fas fa-filter me-1"></i> Click a date to filter the table below.
                        </div>
                        <div class="calendar-schedule-list" id="calendarScheduleList">
                            <div class="text-muted small">Select a date to see scheduled vehicles.</div>
                        </div>
                    </div>
                </div>

                <!-- Budget Status -->
                <div class="card border-0 shadow-sm mb-4 budget-status-card">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-wallet me-2 text-primary"></i>Budget Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="budget-row">
                            <div class="budget-head">
                                <span class="budget-label">Diesel</span>
                                <span class="budget-amount text-warning">&#8369;<?php echo htmlspecialchars(number_format($dieselBudgetRemaining, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="budget-track" role="progressbar" aria-label="Diesel budget remaining" aria-valuenow="<?php echo htmlspecialchars(number_format($dieselBudgetPercent, 0), ENT_QUOTES, 'UTF-8'); ?>" aria-valuemin="0" aria-valuemax="100">
                                <span class="budget-fill budget-fill-diesel" style="width: <?php echo htmlspecialchars(number_format($dieselBudgetPercent, 2), ENT_QUOTES, 'UTF-8'); ?>%;"></span>
                            </div>
                            <div class="budget-meta">
                                <span><?php echo htmlspecialchars(number_format($dieselBudgetPercent, 0), ENT_QUOTES, 'UTF-8'); ?>% left</span>
                                <span>of &#8369;<?php echo htmlspecialchars(number_format($dieselBudgetTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>

                        <div class="budget-row">
                            <div class="budget-head">
                                <span class="budget-label">Unleaded</span>
                                <span class="budget-amount text-success">&#8369;<?php echo htmlspecialchars(number_format($unleadedBudgetRemaining, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="budget-track" role="progressbar" aria-label="Unleaded budget remaining" aria-valuenow="<?php echo htmlspecialchars(number_format($unleadedBudgetPercent, 0), ENT_QUOTES, 'UTF-8'); ?>" aria-valuemin="0" aria-valuemax="100">
                                <span class="budget-fill budget-fill-unleaded" style="width: <?php echo htmlspecialchars(number_format($unleadedBudgetPercent, 2), ENT_QUOTES, 'UTF-8'); ?>%;"></span>
                            </div>
                            <div class="budget-meta">
                                <span><?php echo htmlspecialchars(number_format($unleadedBudgetPercent, 0), ENT_QUOTES, 'UTF-8'); ?>% left</span>
                                <span>of &#8369;<?php echo htmlspecialchars(number_format($unleadedBudgetTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>

                        <div class="budget-total-row">
                            <div>
                                <small>Total Budget</small>
                                <span class="text-primary">&#8369;<?php echo htmlspecialchars(number_format($budgetTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div>
                                <small>Deducted</small>
                                <span class="text-danger">&#8369;<?php echo htmlspecialchars(number_format($budgetUsed, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-simple me-2"></i>Overview
                        </h5>
                    </div>
                    <div class="card-body summary-stats">
                        <div class="row g-2" id="statsRow">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                </div>
            </div>
        </div>
    </main>

    <div class="toast align-items-center text-bg-primary border-0 issuance-action-toast" id="issuanceActionToast" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="issuanceActionToastBody">Issuance action selected.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addVehicleModalLabel">
                        <i class="fas fa-car me-2"></i>Add Vehicle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addVehicleForm">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="vehicleCode" class="form-label">Vehicle ID</label>
                                <input type="text" class="form-control" id="vehicleCode" placeholder="Auto-generated if empty">
                            </div>
                            <div class="col-md-4">
                                <label for="vehiclePlateNo" class="form-label">Plate No.</label>
                                <input type="text" class="form-control text-uppercase" id="vehiclePlateNo" placeholder="e.g. ABC 1234" required>
                            </div>
                            <div class="col-md-4">
                                <label for="vehicleOffice" class="form-label">Office</label>
                                <input type="text" class="form-control" id="vehicleOffice" placeholder="e.g. GSO" required>
                            </div>
                            <div class="col-md-6">
                                <label for="vehicleType" class="form-label">Vehicle Type</label>
                                <input type="text" class="form-control" id="vehicleType" placeholder="e.g. Motorcycle, Pickup" required>
                            </div>
                            <div class="col-md-3">
                                <label for="vehicleFuelType" class="form-label">Fuel Type</label>
                                <select class="form-select" id="vehicleFuelType" required>
                                    <option value="unleaded" selected>Unleaded</option>
                                    <option value="diesel">Diesel</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="vehicleStatus" class="form-label">Status</label>
                                <select class="form-select" id="vehicleStatus" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="vehicleSchedules" class="form-label">Weekly Schedule</label>
                                <input type="hidden" id="vehicleSchedules">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="vehicleSchedulesButton" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                        No weekly schedule
                                    </button>
                                    <div class="dropdown-menu p-2 schedule-dropdown-menu" aria-labelledby="vehicleSchedulesButton">
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="daily" data-schedule-input="vehicleSchedules">Every day</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="as_needed" data-schedule-input="vehicleSchedules">As needed</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="monday" data-schedule-input="vehicleSchedules">Monday</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="tuesday" data-schedule-input="vehicleSchedules">Tuesday</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="wednesday" data-schedule-input="vehicleSchedules">Wednesday</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="thursday" data-schedule-input="vehicleSchedules">Thursday</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="friday" data-schedule-input="vehicleSchedules">Friday</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="saturday" data-schedule-input="vehicleSchedules">Saturday</label>
                                        <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="sunday" data-schedule-input="vehicleSchedules">Sunday</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="vehicleCylinders" class="form-label">Cylinders</label>
                                <input type="number" class="form-control" id="vehicleCylinders" min="1" step="1" value="4" required>
                            </div>
                            <div class="col-md-3">
                                <label for="vehicleNormalKm" class="form-label">Normal km/liter</label>
                                <input type="number" class="form-control" id="vehicleNormalKm" min="0" step="0.01" value="20" required>
                            </div>
                            <div class="col-md-3">
                                <label for="vehicleCurrentOdo" class="form-label">Current Odometer</label>
                                <input type="number" class="form-control" id="vehicleCurrentOdo" min="0" step="0.01" value="0" required>
                            </div>
                            <div class="col-md-3">
                                <label for="vehicleFuelCapacity" class="form-label">Fuel Capacity</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="vehicleFuelCapacity" min="0" step="0.01" value="0" required>
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveVehicleBtn">
                        <i class="fas fa-save me-1"></i>Add Vehicle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vehicleManagerModal" tabindex="-1" aria-labelledby="vehicleManagerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="vehicleManagerModalLabel">
                        <i class="fas fa-clipboard-list me-2"></i>Vehicle Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="vehicle-manager-layout">
                        <div>
                            <label for="vehicleManagerSearch" class="form-label">Search Vehicle</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                                <input type="search" class="form-control" id="vehicleManagerSearch" placeholder="Plate, type, office, or ID">
                            </div>
                            <div class="vehicle-manager-list" id="vehicleManagerList" aria-label="Vehicle list">
                                <?php if (empty($vehicles)): ?>
                                    <div class="text-muted p-3">No vehicles found.</div>
                                <?php else: ?>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <button type="button"
                                            class="vehicle-manager-item"
                                            data-id="<?php echo htmlspecialchars((string) $vehicle['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-search="<?php echo htmlspecialchars(strtolower(implode(' ', [
                                                $vehicle['vehicle_id'] ?? '',
                                                $vehicle['plate_no'] ?? '',
                                                $vehicle['type_of_vehicle'] ?? '',
                                                $vehicle['office'] ?? '',
                                                $vehicle['fuel_type'] ?? '',
                                                $vehicle['schedules'] ?? '',
                                                $vehicle['status'] ?? '',
                                            ])), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="plate"><?php echo htmlspecialchars((string) ($vehicle['plate_no'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="meta">
                                                <?php echo htmlspecialchars((string) ($vehicle['type_of_vehicle'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                                | <?php echo htmlspecialchars((string) ($vehicle['office'] ?? 'No office'), ENT_QUOTES, 'UTF-8'); ?>
                                                | <?php echo htmlspecialchars(ucfirst((string) ($vehicle['fuel_type'] ?? 'unleaded')), ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (trim((string) ($vehicle['schedules'] ?? '')) !== ''): ?>
                                                    | <?php echo htmlspecialchars((string) $vehicle['schedules'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                                | Last: <?php echo htmlspecialchars(number_format((float) ($vehicle['past_odometer'] ?? $vehicle['current_odo'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?> km
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="vehicle-detail-panel">
                            <form id="editVehicleForm">
                                <input type="hidden" id="editVehicleDbId">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div>
                                        <h6 class="mb-0 fw-bold" id="editVehicleTitle">Select a vehicle</h6>
                                        <small class="text-muted" id="editVehicleSubtitle">Vehicle information will appear here.</small>
                                    </div>
                                    <span class="badge bg-secondary" id="editVehicleStatusBadge">--</span>
                                </div>
                                <div class="vehicle-form-section">
                                    <div class="vehicle-section-title">Vehicle Identity</div>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label for="editVehicleCode" class="form-label">Vehicle ID</label>
                                            <input type="text" class="form-control" id="editVehicleCode" disabled>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="editVehiclePlateNo" class="form-label">Plate No.</label>
                                            <input type="text" class="form-control text-uppercase" id="editVehiclePlateNo" required disabled>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="editVehicleOffice" class="form-label">Office</label>
                                            <input type="text" class="form-control" id="editVehicleOffice" required disabled>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="editVehicleStatus" class="form-label">Status</label>
                                            <select class="form-select" id="editVehicleStatus" required disabled>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label for="editVehicleType" class="form-label">Vehicle Type</label>
                                            <input type="text" class="form-control" id="editVehicleType" required disabled>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="editVehicleFuelType" class="form-label">Fuel Type</label>
                                            <select class="form-select" id="editVehicleFuelType" required disabled>
                                                <option value="unleaded">Unleaded</option>
                                                <option value="diesel">Diesel</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="editVehicleSchedules" class="form-label">Weekly Schedule</label>
                                            <input type="hidden" id="editVehicleSchedules" disabled>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="editVehicleSchedulesButton" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" disabled>
                                                    No weekly schedule
                                                </button>
                                                <div class="dropdown-menu p-2 schedule-dropdown-menu" aria-labelledby="editVehicleSchedulesButton">
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="daily" data-schedule-input="editVehicleSchedules">Every day</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="as_needed" data-schedule-input="editVehicleSchedules">As needed</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="monday" data-schedule-input="editVehicleSchedules">Monday</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="tuesday" data-schedule-input="editVehicleSchedules">Tuesday</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="wednesday" data-schedule-input="editVehicleSchedules">Wednesday</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="thursday" data-schedule-input="editVehicleSchedules">Thursday</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="friday" data-schedule-input="editVehicleSchedules">Friday</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="saturday" data-schedule-input="editVehicleSchedules">Saturday</label>
                                                    <label class="dropdown-item"><input class="form-check-input me-2 schedule-option" type="checkbox" value="sunday" data-schedule-input="editVehicleSchedules">Sunday</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="vehicle-form-section">
                                    <div class="vehicle-section-title">Odometer and Fuel Profile</div>
                                    <div class="row g-3 align-items-end">
                                        <div class="col-lg-4">
                                            <div class="vehicle-odometer-reference">
                                                <span class="label">Last Odometer</span>
                                                <span class="value"><span id="editVehicleLastOdoDisplay">0</span> km</span>
                                                <input type="hidden" id="editVehicleLastOdo">
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-4">
                                            <label for="editVehicleCurrentOdo" class="form-label">Current Odometer</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control bg-light" id="editVehicleCurrentOdo" min="0" step="0.01" required readonly disabled>
                                                <span class="input-group-text">km</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-4">
                                            <label for="editVehicleFuelCapacity" class="form-label">Fuel Capacity</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="editVehicleFuelCapacity" min="0" step="0.01" required disabled>
                                                <span class="input-group-text">L</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-4">
                                            <label for="editVehicleFixedLiters" class="form-label">Fixed Liters</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="editVehicleFixedLiters" min="0" step="0.01" value="0" disabled>
                                                <span class="input-group-text">L</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="editVehicleCylinders" class="form-label">Cylinders</label>
                                            <input type="number" class="form-control" id="editVehicleCylinders" min="1" step="1" required disabled>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="editVehicleNormalKm" class="form-label">Normal km/liter</label>
                                            <input type="number" class="form-control" id="editVehicleNormalKm" min="0" step="0.01" required disabled>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveEditVehicleBtn" disabled>
                        <i class="fas fa-save me-1"></i>Save Vehicle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createIssuanceModal" tabindex="-1" aria-labelledby="createIssuanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createIssuanceModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Create Gas Issuance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createIssuanceForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="createSerialNo" class="form-label">Serial No.</label>
                                <input type="text" class="form-control" id="createSerialNo" value="<?php echo htmlspecialchars($serialNo); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="createVehicle" class="form-label">Vehicle</label>
                                <select class="form-select" id="createVehicle" required>
                                    <option value="">Select vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo htmlspecialchars((string) $vehicle['id']); ?>"
                                            data-office="<?php echo htmlspecialchars((string) ($vehicle['office'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-fuel-type="<?php echo htmlspecialchars(str_contains(strtolower((string) ($vehicle['fuel_type'] ?? '')), 'diesel') ? 'diesel' : 'unleaded', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($vehicle['plate_no'] . ' - ' . $vehicle['type_of_vehicle']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="createOffice" class="form-label">Office</label>
                                <input type="text" class="form-control bg-light" id="createOffice" placeholder="Selected vehicle office" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="createDriver" class="form-label">Driver</label>
                                <input type="text" class="form-control" id="createDriver" placeholder="Driver full name" >
                            </div>
                            <div class="col-md-6">
                                <label for="createLiters" class="form-label">Authorized Liters</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="createLiters" min="0" step="0.01" placeholder="0.00" required>
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="createIssueDate" class="form-label">Issue Date</label>
                                <input type="date" class="form-control" id="createIssueDate" value="<?php echo htmlspecialchars($today); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="createExpiryDate" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="createExpiryDate" value="<?php echo htmlspecialchars($defaultExpiryDate); ?>" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="createIssuanceBtn">
                        <i class="fas fa-save me-1"></i>Save Issuance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewIssuanceModal" tabindex="-1" aria-labelledby="viewIssuanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewIssuanceModalLabel">
                        <i class="fas fa-eye me-2"></i>View Gas Issuance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row issuance-detail-list mb-0">
                        <dt class="col-sm-4">Serial No.</dt>
                        <dd class="col-sm-8" id="viewSerialNo"></dd>
                        <dt class="col-sm-4">Vehicle</dt>
                        <dd class="col-sm-8" id="viewVehicle"></dd>
                        <dt class="col-sm-4">Office</dt>
                        <dd class="col-sm-8" id="viewOffice"></dd>
                        <dt class="col-sm-4">Driver</dt>
                        <dd class="col-sm-8" id="viewDriver"></dd>
                        <dt class="col-sm-4">Authorized Liters</dt>
                        <dd class="col-sm-8" id="viewLiters"></dd>
                        <dt class="col-sm-4">Issue Date</dt>
                        <dd class="col-sm-8" id="viewIssueDate"></dd>
                        <dt class="col-sm-4">Expiry Date</dt>
                        <dd class="col-sm-8" id="viewExpiryDate"></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8" id="viewStatus"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editIssuanceModal" tabindex="-1" aria-labelledby="editIssuanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editIssuanceModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Gas Issuance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editIssuanceForm">
                        <input type="hidden" id="editIssuanceId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="editSerialNo" class="form-label">Serial No.</label>
                                <input type="text" class="form-control" id="editSerialNo" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="editPlateNo" class="form-label">Plate No.</label>
                                <input type="text" class="form-control" id="editPlateNo">
                            </div>
                            <div class="col-md-6">
                                <label for="editDriverName" class="form-label">Driver</label>
                                <input type="text" class="form-control" id="editDriverName">
                            </div>
                            <div class="col-md-6">
                                <label for="editAuthorizedLiters" class="form-label">Authorized Liters</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="editAuthorizedLiters" min="0" step="0.01">
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="editIssueDate" class="form-label">Issue Date</label>
                                <input type="date" class="form-control" id="editIssueDate">
                            </div>
                            <div class="col-md-6">
                                <label for="editExpiryDate" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="editExpiryDate">
                            </div>
                            <div class="col-md-6">
                                <label for="editStatus" class="form-label">Status</label>
                                <select class="form-select" id="editStatus">
                                    <option value="draft">Draft</option>
                                    <option value="approved">Approved</option>
                                    <option value="valid">Valid</option>
                                    <option value="used">Used</option>
                                    <option value="expired">Expired</option>
                                    <option value="revoked">Revoked</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="saveEditIssuanceBtn">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // ========== DOM refs ==========
        const filterSearch = document.getElementById('filterSearch');
        const filterStatus = document.getElementById('filterStatus');
        const filterDate = document.getElementById('filterDate');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        const scheduleTableBody = document.getElementById('scheduleTableBody');
        const activeFilterChips = document.getElementById('activeFilterChips');
        const noFilteredIssuancesRow = document.getElementById('noFilteredIssuancesRow');

        // ========== ISSUANCE DATA (from PHP) ==========
        var issuanceData = <?php echo json_encode(array_map(function($iss) {
            global $vehicleLookup;
            $plate = strtoupper((string) ($iss['plate_no'] ?? ''));
            $vehicleMeta = $vehicleLookup[$plate] ?? [];
            $currentOdometer = (float) ($iss['current_odometer'] ?? $vehicleMeta['current_odo'] ?? 0);
            $liters = (float) ($iss['authorized_liters'] ?? 0);
            $pastOdometer = (float) ($iss['past_odometer'] ?? max(0, $currentOdometer - round($liters * 20)));

            return [
                'id' => $iss['id'],
                'serial_no' => $iss['serial_no'],
                'plate_no' => $iss['plate_no'] ?? '-',
                'driver_name' => $iss['driver_name'] ?? '-',
                'liters' => $iss['authorized_liters'] ?? '0',
                'issue_date' => $iss['issue_date'] ?? null,
                'expiry_date' => $iss['expiry_date'] ?? null,
                'status' => strtolower($iss['status'] ?? 'valid'),
                'vehicle_type' => $iss['vehicle_type'] ?? '',
                'actual_liters_fueled' => $iss['actual_liters_fueled'] ?? null,
                'unit' => $iss['unit'] ?? 'Liters',
                'past_odometer' => $pastOdometer,
                'current_odometer' => $currentOdometer,
                'cylinders' => $iss['cylinders'] ?? $vehicleMeta['cylinders'] ?? 4,
                'normal_km_per_liter' => $iss['normal_km_per_liter'] ?? 20,
                'office' => $iss['office'] ?? 'Office'
            ];
        }, $allIssuances), JSON_UNESCAPED_UNICODE); ?>;

        var vehicleData = <?php echo json_encode(array_map(static function(array $vehicle): array {
            return [
                'id' => $vehicle['id'],
                'vehicle_id' => $vehicle['vehicle_id'] ?? '',
                'plate_no' => $vehicle['plate_no'] ?? '',
                'type_of_vehicle' => $vehicle['type_of_vehicle'] ?? '',
                'office' => $vehicle['office'] ?? '',
                'fuel_type' => str_contains(strtolower((string) ($vehicle['fuel_type'] ?? '')), 'diesel') ? 'diesel' : 'unleaded',
                'schedules' => $vehicle['schedules'] ?? '',
                'number_of_cylinder' => $vehicle['cylinders'] ?? 4,
                'normal_km_per_liter' => $vehicle['normal_km_per_liter'] ?? 20,
                'past_odometer' => $vehicle['past_odometer'] ?? $vehicle['current_odo'] ?? 0,
                'current_odometer' => $vehicle['current_odo'] ?? 0,
                'fuel_capacity' => $vehicle['capacity'] ?? 0,
                'fixed_liters' => $vehicle['fixed_liters'] ?? 0,
                'status' => $vehicle['status'] ?? 'active',
            ];
        }, $vehicles), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        // ========== SUMMARY STATS ==========
        function renderStats() {
            var now = new Date();
            var todayStr = formatLocalDate(now);
            var counts = { draft: 0, approved: 0, valid: 0, used: 0, expired: 0, revoked: 0 };
            var upcoming = 0, overdue = 0;
            issuanceData.forEach(function(item) {
                var s = item.status || 'valid';
                if (counts[s] !== undefined) counts[s]++;
                if (s === 'valid') {
                    if (item.expiry_date >= todayStr) upcoming++;
                    else overdue++;
                }
            });

            document.getElementById('statsRow').innerHTML =
                '<div class="col-6"><div class="stat-card bg-light"><div class="stat-number text-success">' + counts.valid + '</div><div class="stat-label text-muted">Valid</div></div></div>' +
                '<div class="col-6"><div class="stat-card bg-light"><div class="stat-number text-primary">' + counts.used + '</div><div class="stat-label text-muted">Used</div></div></div>' +
                '<div class="col-6"><div class="stat-card bg-light"><div class="stat-number text-warning">' + upcoming + '</div><div class="stat-label text-muted">Upcoming</div></div></div>' +
                '<div class="col-6"><div class="stat-card bg-light"><div class="stat-number text-danger">' + overdue + '</div><div class="stat-label text-muted">Overdue</div></div></div>';
        }
        renderStats();

        // ========== MINI CALENDAR ==========
        var scheduleLabels = {
            daily: 'Every day',
            as_needed: 'As needed',
            monday: 'Monday',
            tuesday: 'Tuesday',
            wednesday: 'Wednesday',
            thursday: 'Thursday',
            friday: 'Friday',
            saturday: 'Saturday',
            sunday: 'Sunday'
        };
        var calendarDate = new Date();
        calendarDate.setDate(1); // start of current month
        var selectedCalendarDate = formatLocalDate(new Date());
        filterDate.value = selectedCalendarDate;

        function formatLocalDate(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function calendarEscape(value) {
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

        function dateFromString(dateStr) {
            return new Date(dateStr + 'T00:00:00');
        }

        function scheduleMatchesCalendarDate(schedule, dateStr) {
            var normalized = normalizeScheduleValue(schedule);
            if (!normalized) {
                return false;
            }
            if (normalized === 'daily') {
                return true;
            }
            if (normalized === 'as_needed') {
                return dateStr === formatLocalDate(new Date());
            }

            var date = dateFromString(dateStr);
            var dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            return normalized.split(',').indexOf(dayNames[date.getDay()]) !== -1;
        }

        function buildCalendarMap(year, month) {
            var map = {};
            issuanceData.forEach(function(item) {
                var d = item.issue_date;
                if (d) {
                    if (!map[d]) map[d] = { issued: [], scheduled: [] };
                    map[d].issued.push(item);
                }
            });

            var daysInMonth = new Date(year, month + 1, 0).getDate();
            for (var day = 1; day <= daysInMonth; day++) {
                var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                vehicleData.forEach(function(vehicle) {
                    if (!scheduleMatchesCalendarDate(vehicle.schedules, dateStr)) {
                        return;
                    }

                    if (!map[dateStr]) map[dateStr] = { issued: [], scheduled: [] };
                    map[dateStr].scheduled.push(vehicle);
                });
            }

            return map;
        }

        function renderCalendarScheduleList(dateStr, calendarMap) {
            var list = document.getElementById('calendarScheduleList');
            if (!list) {
                return;
            }
            if (!dateStr) {
                list.innerHTML = '<div class="text-muted small">Select a date to see scheduled vehicles.</div>';
                return;
            }

            var entry = calendarMap[dateStr] || { issued: [], scheduled: [] };
            var vehicles = entry.scheduled || [];
            if (vehicles.length === 0) {
                list.innerHTML = '<div class="text-muted small">No vehicles scheduled on this date.</div>';
                return;
            }

            list.innerHTML = vehicles.slice(0, 8).map(function(vehicle) {
                var alreadyIssued = (entry.issued || []).some(function(item) {
                    return String(item.plate_no || '') === String(vehicle.plate_no || '');
                });
                return '<div class="calendar-schedule-item">' +
                    '<span class="schedule-dot"></span>' +
                    '<span>' +
                        '<span class="schedule-title">' + calendarEscape(vehicle.plate_no || vehicle.vehicle_id || 'No plate') + '</span>' +
                        '<span class="schedule-meta">' + calendarEscape(vehicle.type_of_vehicle || 'Vehicle') + ' | ' + calendarEscape(vehicle.office || 'Office') + ' | ' + calendarEscape(scheduleLabel(vehicle.schedules)) + (alreadyIssued ? ' | Issuance created' : '') + '</span>' +
                    '</span>' +
                '</div>';
            }).join('') + (vehicles.length > 8 ? '<div class="text-muted small mt-2">+' + (vehicles.length - 8) + ' more scheduled vehicles</div>' : '');
        }

        function renderMiniCalendar(year, month) {
            var issuanceMap = buildCalendarMap(year, month);
            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var startDay = firstDay.getDay(); // 0=Sun
            var totalDays = lastDay.getDate();

            var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            var dayLabels = ['Su','Mo','Tu','We','Th','Fr','Sa'];

            var todayStr = formatLocalDate(new Date());

            var html = '<div class="mc-header">';
            html += '<button type="button" data-action="prev"><i class="fas fa-chevron-left"></i></button>';
            html += '<span class="mc-month">' + monthNames[month] + ' ' + year + '</span>';
            html += '<button type="button" data-action="next"><i class="fas fa-chevron-right"></i></button>';
            html += '</div>';
            html += '<div class="mc-grid">';
            dayLabels.forEach(function(l) { html += '<div class="day-label">' + l + '</div>'; });

            // empty cells before first day
            for (var i = 0; i < startDay; i++) {
                html += '<div class="day-cell other-month"></div>';
            }

            for (var d = 1; d <= totalDays; d++) {
                var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                var isToday = dateStr === todayStr;
                var isSelected = dateStr === selectedCalendarDate;
                var entry = issuanceMap[dateStr] || { issued: [], scheduled: [] };
                var count = entry.issued.length;
                var hasIss = count > 0;

                var cls = 'day-cell';
                if (isToday) cls += ' today';
                if (isSelected) cls += ' selected';
                if (hasIss) cls += ' has-issuance';

                html += '<div class="' + cls + '" data-date="' + dateStr + '">';
                html += d;
                if (hasIss && count > 1) {
                    html += '<span class="issuance-count">' + count + '</span>';
                }
                html += '</div>';
            }

            // fill remaining cells
            var totalCells = startDay + totalDays;
            var remainder = totalCells % 7;
            if (remainder > 0) {
                for (var i = 0; i < 7 - remainder; i++) {
                    html += '<div class="day-cell other-month"></div>';
                }
            }

            html += '</div>';
            document.getElementById('miniCalendar').innerHTML = html;
            renderCalendarScheduleList(selectedCalendarDate, issuanceMap);

            // Event listeners
            document.querySelectorAll('#miniCalendar .mc-header button').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var action = this.dataset.action;
                    if (action === 'prev') {
                        calendarDate.setMonth(calendarDate.getMonth() - 1);
                    } else {
                        calendarDate.setMonth(calendarDate.getMonth() + 1);
                    }
                    renderMiniCalendar(calendarDate.getFullYear(), calendarDate.getMonth());
                });
            });

            document.querySelectorAll('#miniCalendar .day-cell:not(.other-month)').forEach(function(cell) {
                cell.addEventListener('click', function() {
                    var date = this.dataset.date;
                    if (selectedCalendarDate === date) {
                        selectedCalendarDate = null;
                    } else {
                        selectedCalendarDate = date;
                    }
                    filterDate.value = selectedCalendarDate || '';
                    applyFilters();
                    renderMiniCalendar(calendarDate.getFullYear(), calendarDate.getMonth());
                    document.getElementById('selectedDateLabel').innerHTML = selectedCalendarDate ?
                        '<i class="fas fa-filter me-1"></i> Filtered: <strong>' + selectedCalendarDate + '</strong>' :
                        '<i class="fas fa-filter me-1"></i> Click a date to filter the table below.';
                });
            });
        }

        renderMiniCalendar(calendarDate.getFullYear(), calendarDate.getMonth());

        // ========== TABLE FILTERS ==========
        function getIssuanceById(id) {
            return issuanceData.find(function(item) {
                return String(item.id) === String(id);
            });
        }

        function renderFilterChips() {
            var chips = [];
            var searchVal = filterSearch.value.trim();
            var statusVal = filterStatus.value;
            var dateVal = filterDate.value;

            if (searchVal) {
                chips.push({ key: 'search', label: 'Search: ' + searchVal });
            }
            if (statusVal) {
                chips.push({ key: 'status', label: 'Status: ' + statusVal.charAt(0).toUpperCase() + statusVal.slice(1) });
            }
            if (dateVal) {
                chips.push({ key: 'date', label: 'Date: ' + dateVal });
            }

            activeFilterChips.innerHTML = chips.map(function(chip) {
                return '<span class="filter-chip">' + chip.label +
                    '<button type="button" data-clear-filter="' + chip.key + '" aria-label="Clear ' + chip.key + ' filter">' +
                    '<i class="fas fa-times"></i></button></span>';
            }).join('');
        }

        function applyFilters() {
            var searchVal = filterSearch.value.toLowerCase().trim();
            var statusVal = filterStatus.value.toLowerCase();
            var dateVal = filterDate.value;
            var visibleCount = 0;

            var rows = scheduleTableBody.querySelectorAll('tr');
            rows.forEach(function(row) {
                if (row.id === 'noFilteredIssuancesRow') {
                    return;
                }

                var plate = (row.dataset.plate || '');
                var office = (row.dataset.office || '');
                var driver = (row.dataset.driver || '');
                var rowStatus = (row.dataset.status || '');
                var rowIssue = (row.dataset.issue || '');

                var match = true;
                if (searchVal && plate.indexOf(searchVal) === -1 && driver.indexOf(searchVal) === -1 && office.indexOf(searchVal) === -1) match = false;
                if (statusVal && rowStatus !== statusVal) match = false;
                if (dateVal && rowIssue !== dateVal) match = false;

                row.style.display = match ? '' : 'none';
                if (match) {
                    visibleCount++;
                }
            });

            noFilteredIssuancesRow.classList.toggle('d-none', visibleCount > 0);
            renderFilterChips();
        }

        filterSearch.addEventListener('input', applyFilters);
        filterStatus.addEventListener('change', applyFilters);
        filterDate.addEventListener('change', applyFilters);

        document.getElementById('selectedDateLabel').innerHTML =
            '<i class="fas fa-filter me-1"></i> Filtered: <strong>' + selectedCalendarDate + '</strong>';
        applyFilters();

        clearFiltersBtn.addEventListener('click', function() {
            filterSearch.value = '';
            filterStatus.value = '';
            filterDate.value = '';
            selectedCalendarDate = null;
            renderMiniCalendar(calendarDate.getFullYear(), calendarDate.getMonth());
            document.getElementById('selectedDateLabel').innerHTML =
                '<i class="fas fa-filter me-1"></i> Click a date to filter the table below.';
            applyFilters();
        });

        activeFilterChips.addEventListener('click', function(event) {
            var button = event.target.closest('[data-clear-filter]');
            if (!button) {
                return;
            }

            var filter = button.dataset.clearFilter;
            if (filter === 'search') {
                filterSearch.value = '';
            } else if (filter === 'status') {
                filterStatus.value = '';
            } else if (filter === 'date') {
                filterDate.value = '';
                selectedCalendarDate = null;
                renderMiniCalendar(calendarDate.getFullYear(), calendarDate.getMonth());
                document.getElementById('selectedDateLabel').innerHTML =
                    '<i class="fas fa-filter me-1"></i> Click a date to filter the table below.';
            }
            applyFilters();
        });

        // Sync calendar click with filter date input
        filterDate.addEventListener('input', function() {
            if (this.value) {
                selectedCalendarDate = this.value;
                renderMiniCalendar(calendarDate.getFullYear(), calendarDate.getMonth());
                document.getElementById('selectedDateLabel').innerHTML =
                    '<i class="fas fa-filter me-1"></i> Filtered: <strong>' + this.value + '</strong>';
            } else {
                selectedCalendarDate = null;
                renderMiniCalendar(calendarDate.getFullYear(), calendarDate.getMonth());
                document.getElementById('selectedDateLabel').innerHTML =
                    '<i class="fas fa-filter me-1"></i> Click a date to filter the table below.';
            }
            applyFilters();
        });

        function getStatusBadgeClass(status) {
            var statusClass = 'bg-success';
            if (status === 'used') statusClass = 'bg-primary';
            else if (status === 'approved') statusClass = 'bg-success';
            else if (status === 'draft') statusClass = 'bg-warning text-dark';
            else if (status === 'expired') statusClass = 'bg-secondary';
            else if (status === 'revoked') statusClass = 'bg-danger';

            return statusClass;
        }

        function statusBadge(status) {
            var statusClass = getStatusBadgeClass(status);
            return '<span class="badge ' + statusClass + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
        }

        function showIssuanceToast(message, type) {
            var toastBody = document.getElementById('issuanceActionToastBody');
            var toastElement = document.getElementById('issuanceActionToast');
            if (!toastBody || !toastElement || typeof bootstrap === 'undefined' || !bootstrap.Toast) {
                return;
            }

            toastElement.className = 'toast align-items-center border-0 issuance-action-toast text-bg-' + (type || 'primary');
            toastBody.textContent = message;
            bootstrap.Toast.getOrCreateInstance(toastElement).show();
        }

        function openViewModal(item) {
            document.getElementById('viewSerialNo').textContent = item.serial_no || '-';
            document.getElementById('viewVehicle').textContent = (item.plate_no || '-') + ' - ' + (item.vehicle_type || '-');
            document.getElementById('viewOffice').textContent = item.office || '-';
            document.getElementById('viewDriver').textContent = item.driver_name || '-';
            document.getElementById('viewLiters').textContent = (item.liters || '0') + ' L';
            document.getElementById('viewIssueDate').textContent = item.issue_date || '-';
            document.getElementById('viewExpiryDate').textContent = item.expiry_date || '-';
            document.getElementById('viewStatus').innerHTML = statusBadge(item.status || 'valid');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('viewIssuanceModal')).show();
        }

        function openEditModal(item) {
            document.getElementById('editIssuanceId').value = item.id || '';
            document.getElementById('editSerialNo').value = item.serial_no || '';
            document.getElementById('editPlateNo').value = item.plate_no || '';
            document.getElementById('editDriverName').value = item.driver_name || '';
            document.getElementById('editAuthorizedLiters').value = item.liters || '';
            document.getElementById('editIssueDate').value = item.issue_date || '';
            document.getElementById('editExpiryDate').value = item.expiry_date || '';
            document.getElementById('editStatus').value = item.status || 'valid';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editIssuanceModal')).show();
        }

        function saveGasIssuance(payload, button) {
            var originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            fetch('gas_issuance_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
                .then(function(response) {
                    return response.json().then(function(data) {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Unable to save gas issuance.');
                        }
                        return data;
                    });
                })
                .then(function(data) {
                    showIssuanceToast(data.message || 'Gas issuance saved.', 'success');
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 700);
                })
                .catch(function(error) {
                    showIssuanceToast(error.message, 'danger');
                })
                .finally(function() {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                });
        }

        function saveIssuanceApproval(checkbox) {
            checkbox.disabled = true;
            var row = checkbox.closest('tr');
            var previousChecked = !checkbox.checked;
            var previousStatus = row ? (row.dataset.status || '') : '';

            fetch('gas_issuance_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'approval',
                    id: checkbox.dataset.id,
                    approved: checkbox.checked
                })
            })
                .then(function(response) {
                    return response.json().then(function(data) {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Unable to update approval.');
                        }
                        return data;
                    });
                })
                .then(function(data) {
                    var status = data.status || (checkbox.checked ? 'approved' : 'draft');
                    if (row) {
                        row.dataset.status = status;
                        var badge = row.querySelector('td:nth-child(9) .badge');
                        if (badge) {
                            badge.className = 'badge ' + getStatusBadgeClass(status);
                            badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                        }
                    }

                    var issuance = getIssuanceById(checkbox.dataset.id);
                    if (issuance) {
                        issuance.status = status;
                    }

                    checkbox.checked = status === 'approved' || status === 'valid' || status === 'used';
                    checkbox.disabled = status === 'used' || status === 'expired' || status === 'revoked';
                    renderStats();
                    applyFilters();
                    showIssuanceToast(data.message || 'Approval updated.', 'success');
                })
                .catch(function(error) {
                    checkbox.checked = previousChecked;
                    checkbox.disabled = false;
                    if (row && previousStatus) {
                        row.dataset.status = previousStatus;
                    }
                    showIssuanceToast(error.message, 'danger');
                });
        }

        function saveVehicle(payload, button) {
            var originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            fetch('vehicle_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
                .then(function(response) {
                    return response.json().then(function(data) {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Unable to save vehicle.');
                        }
                        return data;
                    });
                })
                .then(function(data) {
                    showIssuanceToast(data.message || 'Vehicle added.', 'success');
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 700);
                })
                .catch(function(error) {
                    showIssuanceToast(error.message, 'danger');
                })
                .finally(function() {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                });
        }

        function getVehicleById(id) {
            return vehicleData.find(function(vehicle) {
                return String(vehicle.id) === String(id);
            });
        }

        function updateCreateOfficeFromVehicle() {
            var vehicleSelect = document.getElementById('createVehicle');
            var selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
            document.getElementById('createOffice').value = selectedOption ? (selectedOption.dataset.office || '') : '';
        }

        function getCreateFuelTypeFromVehicle() {
            var vehicleSelect = document.getElementById('createVehicle');
            var selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
            var fuelType = normalizeFuelType(selectedOption ? (selectedOption.dataset.fuelType || 'unleaded') : 'unleaded');
            return fuelType.charAt(0).toUpperCase() + fuelType.slice(1).toLowerCase();
        }

        function normalizeFuelType(value) {
            var fuelType = String(value || '').trim().toLowerCase();
            if (fuelType.indexOf('diesel') !== -1) {
                return 'diesel';
            }
            if (fuelType.indexOf('gasoline') !== -1 || fuelType.indexOf('unleaded') !== -1) {
                return 'unleaded';
            }
            return 'unleaded';
        }

        function normalizeScheduleValue(value) {
            var text = String(value || '').toLowerCase();
            if (text.indexOf('daily') !== -1 || text.indexOf('everyday') !== -1 || text.indexOf('every day') !== -1) {
                return 'daily';
            }
            if (text.indexOf('as_needed') !== -1 || text.indexOf('as needed') !== -1 || text.indexOf('as-needed') !== -1) {
                return 'as_needed';
            }

            return Object.keys(scheduleLabels).filter(function(day) {
                return day !== 'daily' && day !== 'as_needed' && text.indexOf(day) !== -1;
            }).join(',');
        }

        function scheduleLabel(value) {
            var normalized = normalizeScheduleValue(value);
            if (!normalized) {
                return 'No weekly schedule';
            }
            if (normalized === 'daily') {
                return 'Every day';
            }
            if (normalized === 'as_needed') {
                return 'As needed';
            }

            return normalized.split(',').filter(Boolean).map(function(day) {
                return scheduleLabels[day] || day;
            }).join(', ');
        }

        function syncScheduleDropdown(inputId, value) {
            var normalized = normalizeScheduleValue(value);
            var input = document.getElementById(inputId);
            var button = document.getElementById(inputId + 'Button');
            var selected = normalized ? normalized.split(',') : [];

            if (input) {
                input.value = normalized;
            }
            if (button) {
                button.textContent = scheduleLabel(normalized);
            }

            document.querySelectorAll('.schedule-option[data-schedule-input="' + inputId + '"]').forEach(function(option) {
                option.checked = selected.indexOf(option.value) !== -1;
            });
        }

        function handleScheduleOptionChange(option) {
            var inputId = option.dataset.scheduleInput;
            var options = Array.from(document.querySelectorAll('.schedule-option[data-schedule-input="' + inputId + '"]'));

            if ((option.value === 'daily' || option.value === 'as_needed') && option.checked) {
                options.forEach(function(item) {
                    item.checked = item.value === option.value;
                });
            } else if (option.value !== 'daily' && option.value !== 'as_needed' && option.checked) {
                options.forEach(function(item) {
                    if (item.value === 'daily' || item.value === 'as_needed') {
                        item.checked = false;
                    }
                });
            }

            var values = options.filter(function(item) {
                return item.checked;
            }).map(function(item) {
                return item.value;
            });

            syncScheduleDropdown(inputId, values.join(','));
        }

        function setVehicleFormEnabled(enabled) {
            document.querySelectorAll('#editVehicleForm input, #editVehicleForm select').forEach(function(element) {
                if (element.id !== 'editVehicleDbId' && element.id !== 'editVehicleCurrentOdo') {
                    element.disabled = !enabled;
                }
            });
            document.getElementById('editVehicleCurrentOdo').disabled = true;
            document.getElementById('editVehicleSchedulesButton').disabled = !enabled;
            document.getElementById('saveEditVehicleBtn').disabled = !enabled;
        }

        function openVehicleDetails(vehicle) {
            if (!vehicle) {
                return;
            }

            setVehicleFormEnabled(true);
            document.getElementById('editVehicleDbId').value = vehicle.id || '';
            document.getElementById('editVehicleCode').value = vehicle.vehicle_id || '';
            document.getElementById('editVehiclePlateNo').value = vehicle.plate_no || '';
            document.getElementById('editVehicleOffice').value = vehicle.office || '';
            document.getElementById('editVehicleType').value = vehicle.type_of_vehicle || '';
            document.getElementById('editVehicleFuelType').value = normalizeFuelType(vehicle.fuel_type);
            syncScheduleDropdown('editVehicleSchedules', vehicle.schedules || '');
            document.getElementById('editVehicleCylinders').value = vehicle.number_of_cylinder || 4;
            document.getElementById('editVehicleNormalKm').value = vehicle.normal_km_per_liter || 0;
            document.getElementById('editVehicleLastOdo').value = vehicle.past_odometer || 0;
            document.getElementById('editVehicleLastOdoDisplay').textContent = Number(vehicle.past_odometer || 0).toLocaleString(undefined, {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            });
            document.getElementById('editVehicleCurrentOdo').value = vehicle.current_odometer || 0;
            document.getElementById('editVehicleFuelCapacity').value = vehicle.fuel_capacity || 0;
            document.getElementById('editVehicleFixedLiters').value = vehicle.fixed_liters || 0;
            document.getElementById('editVehicleStatus').value = vehicle.status || 'active';
            document.getElementById('editVehicleTitle').textContent = vehicle.plate_no || 'Vehicle details';
            document.getElementById('editVehicleSubtitle').textContent = (vehicle.type_of_vehicle || '-') + ' | ' + (vehicle.office || 'No office') + ' | ' + (vehicle.fuel_type || 'unleaded') + ' | ' + (vehicle.schedules || 'No schedule') + ' | ' + (vehicle.vehicle_id || 'No vehicle ID');

            var badge = document.getElementById('editVehicleStatusBadge');
            var status = vehicle.status || 'active';
            badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            badge.className = 'badge ' + (status === 'active' ? 'bg-success' : 'bg-secondary');

            document.querySelectorAll('.vehicle-manager-item').forEach(function(item) {
                item.classList.toggle('active', String(item.dataset.id) === String(vehicle.id));
            });
        }

        function filterVehicleManagerList() {
            var query = document.getElementById('vehicleManagerSearch').value.trim().toLowerCase();
            document.querySelectorAll('.vehicle-manager-item').forEach(function(item) {
                item.classList.toggle('d-none', query !== '' && item.dataset.search.indexOf(query) === -1);
            });
        }

        scheduleTableBody.addEventListener('click', function(event) {
            var viewButton = event.target.closest('.action-view-issuance');
            var editButton = event.target.closest('.action-edit-issuance');
            var printButton = event.target.closest('.action-print-gas');
            var deleteButton = event.target.closest('.action-delete-issuance');
            var button = viewButton || editButton || printButton || deleteButton;

            if (!button) {
                return;
            }

            var item = getIssuanceById(button.dataset.id);
            if (!item) {
                showIssuanceToast('Unable to find this issuance record.', 'danger');
                return;
            }

            if (viewButton) {
                openViewModal(item);
            } else if (editButton) {
                openEditModal(item);
            } else if (printButton) {
                var params = new URLSearchParams({
                    serial_no: item.serial_no || printButton.dataset.serial || ''
                });
                window.open('fuel_withdrawal.php?' + params.toString(), '_blank', 'noopener');
                showIssuanceToast('Opening gas issuance PDF for ' + (item.serial_no || 'this record') + '...', 'success');
            } else if (deleteButton) {
                var serial = item.serial_no || deleteButton.dataset.serial || 'this issuance';
                if (!window.confirm('Delete gas issuance ' + serial + '? This cannot be undone.')) {
                    return;
                }

                saveGasIssuance({
                    action: 'delete',
                    id: item.id
                }, deleteButton);
            }
        });

        scheduleTableBody.addEventListener('change', function(event) {
            if (event.target.classList.contains('issuance-approval-checkbox')) {
                saveIssuanceApproval(event.target);
            }
        });

        document.getElementById('createIssuanceBtn').addEventListener('click', function() {
            var form = document.getElementById('createIssuanceForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            saveGasIssuance({
                action: 'create',
                serial_no: document.getElementById('createSerialNo').value,
                vehicle_id: document.getElementById('createVehicle').value,
                driver_name: document.getElementById('createDriver').value,
                authorized_liters: document.getElementById('createLiters').value,
                issue_date: document.getElementById('createIssueDate').value,
                expiry_date: document.getElementById('createExpiryDate').value,
                status: 'draft',
                office: document.getElementById('createOffice').value,
                purpose: 'OFFICIAL TRAVEL',
                fuel_type: getCreateFuelTypeFromVehicle(),
                unit: 'Liters'
            }, this);
        });

        document.getElementById('createVehicle').addEventListener('change', updateCreateOfficeFromVehicle);
        document.getElementById('createIssuanceModal').addEventListener('shown.bs.modal', updateCreateOfficeFromVehicle);
        document.querySelectorAll('.schedule-option').forEach(function(option) {
            option.addEventListener('change', function() {
                handleScheduleOptionChange(option);
            });
        });
        syncScheduleDropdown('vehicleSchedules', '');

        document.getElementById('saveVehicleBtn').addEventListener('click', function() {
            var form = document.getElementById('addVehicleForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            saveVehicle({
                vehicle_id: document.getElementById('vehicleCode').value,
                plate_no: document.getElementById('vehiclePlateNo').value,
                type_of_vehicle: document.getElementById('vehicleType').value,
                office: document.getElementById('vehicleOffice').value,
                fuel_type: document.getElementById('vehicleFuelType').value,
                schedules: document.getElementById('vehicleSchedules').value,
                number_of_cylinder: document.getElementById('vehicleCylinders').value,
                normal_km_per_liter: document.getElementById('vehicleNormalKm').value,
                current_odometer: document.getElementById('vehicleCurrentOdo').value,
                fuel_capacity: document.getElementById('vehicleFuelCapacity').value,
                status: document.getElementById('vehicleStatus').value
            }, this);
        });

        document.getElementById('vehicleManagerList').addEventListener('click', function(event) {
            var item = event.target.closest('.vehicle-manager-item');
            if (!item) {
                return;
            }

            openVehicleDetails(getVehicleById(item.dataset.id));
        });

        document.getElementById('vehicleManagerSearch').addEventListener('input', filterVehicleManagerList);

        document.getElementById('vehicleManagerModal').addEventListener('shown.bs.modal', function() {
            if (vehicleData.length > 0 && !document.getElementById('editVehicleDbId').value) {
                openVehicleDetails(vehicleData[0]);
            }
        });

        document.getElementById('saveEditVehicleBtn').addEventListener('click', function() {
            var form = document.getElementById('editVehicleForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            saveVehicle({
                action: 'update',
                id: document.getElementById('editVehicleDbId').value,
                vehicle_id: document.getElementById('editVehicleCode').value,
                plate_no: document.getElementById('editVehiclePlateNo').value,
                type_of_vehicle: document.getElementById('editVehicleType').value,
                office: document.getElementById('editVehicleOffice').value,
                fuel_type: document.getElementById('editVehicleFuelType').value,
                schedules: document.getElementById('editVehicleSchedules').value,
                number_of_cylinder: document.getElementById('editVehicleCylinders').value,
                normal_km_per_liter: document.getElementById('editVehicleNormalKm').value,
                current_odometer: document.getElementById('editVehicleCurrentOdo').value,
                fuel_capacity: document.getElementById('editVehicleFuelCapacity').value,
                fixed_liters: document.getElementById('editVehicleFixedLiters').value,
                status: document.getElementById('editVehicleStatus').value
            }, this);
        });

        document.getElementById('saveEditIssuanceBtn').addEventListener('click', function() {
            var form = document.getElementById('editIssuanceForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            saveGasIssuance({
                action: 'update',
                id: document.getElementById('editIssuanceId').value,
                plate_no: document.getElementById('editPlateNo').value,
                driver_name: document.getElementById('editDriverName').value,
                authorized_liters: document.getElementById('editAuthorizedLiters').value,
                issue_date: document.getElementById('editIssueDate').value,
                expiry_date: document.getElementById('editExpiryDate').value,
                status: document.getElementById('editStatus').value
            }, this);
        });

        renderFilterChips();

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(element) {
                new bootstrap.Tooltip(element);
            });
        }
    </script>
</body>

</html>
