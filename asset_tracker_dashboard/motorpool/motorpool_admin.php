<?php
require_once dirname(__DIR__) . '/page_bootstrap.php';
// Include database connection
require_once dirname(__DIR__) . '/db_asset.php';
require_once __DIR__ . '/motorpool_data_display.php';

// Fetch vehicles from the database
$vehicles = get_vehicles_list();
$repair_list = get_motorpool_repairs();
$most_repaired_vehicles = count_completed_repairs_by_car();
$pending_repairs_count = count_pending_repairs();
$in_progress_repairs_count = count_in_progress_repairs();
$completed_repairs_count = count_repaired_repairs();
$active_repairs_count = count($repair_list);
$vehicle_count = count($vehicles);

// Extract plate numbers (labels) and repair counts (data) for the chart
$vehicle_labels = array_keys($most_repaired_vehicles);
$repair_counts = array_values($most_repaired_vehicles);

// Convert to JSON for JavaScript
$vehicle_labels_json = json_encode($vehicle_labels);
$repair_counts_json = json_encode($repair_counts);

$repair_type_breakdown = count_repairs_by_type(10);
$repair_type_labels = array_keys($repair_type_breakdown);
$repair_type_counts = array_values($repair_type_breakdown);
$repair_type_labels_json = json_encode($repair_type_labels);
$repair_type_counts_json = json_encode($repair_type_counts);

$daily_repairs = count_daily_repairs();
$dates = [];
$counts = [];
$chart_data = array_reverse(array_slice($daily_repairs, 0, 7));

foreach ($chart_data as $repair) {
    $dates[] = date('M d', strtotime($repair['repair_day']));
    $counts[] = $repair['repair_count'];
}

$dates_json = json_encode($dates);
$counts_json = json_encode($counts);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo asset_page_security_tags('../'); ?>
    <title>Motorpool Tracker</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../sidebar_asset.css">
    <link rel="stylesheet" href="motorpool_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="Logo">
            <span class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?></span>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['pay_name'] ?? $_SESSION['username'] ?? 'User'); ?></span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="../dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="../pay_track.php">Payables</a></li>
                    <li><a href="../rfq_tracking.php">RFQ</a></li>
                </ul>
            </li>
            <li><a href="../tent_tracking/tracking.php"><i class="fas fa-campground icon-size"></i> Tent</a></li>
            <li><a href="../chairs_table/tracking.php"><i class="fas fa-chair icon-size"></i> Chairs & Table</a></li>
            <li><a href="motorpool_admin.php"><i class="fas fa-wrench icon-size"></i> Motorpool</a></li>
            <li><a href="../transpo.php"><i class="fas fa-truck icon-size"></i> Transportation</a></li>
            <li><a href="../../create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="../../logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="container-fluid motorpool-page">
        <div class="motorpool-header">
            <div>
                <p class="motorpool-eyebrow">General Services Office</p>
                <h1 class="Title_header">Motorpool</h1>
                <p class="motorpool-subtitle">Monitor vehicle repairs, maintenance activity, and fleet records.</p>
            </div>
        </div>
        <section class="motorpool-kpi-grid" aria-label="Motorpool repair summary">
            <article class="motorpool-kpi-card motorpool-kpi-card--active">
                <span class="motorpool-kpi-label">Active Jobs</span>
                <strong class="motorpool-kpi-value"><?php echo $active_repairs_count; ?></strong>
                <span class="motorpool-kpi-note">Pending and in progress</span>
            </article>
            <article class="motorpool-kpi-card">
                <span class="motorpool-kpi-label">Pending</span>
                <strong class="motorpool-kpi-value"><?php echo $pending_repairs_count; ?></strong>
                <span class="motorpool-kpi-note">Waiting to start</span>
            </article>
            <article class="motorpool-kpi-card">
                <span class="motorpool-kpi-label">In Progress</span>
                <strong class="motorpool-kpi-value"><?php echo $in_progress_repairs_count; ?></strong>
                <span class="motorpool-kpi-note">Currently serviced</span>
            </article>
            <article class="motorpool-kpi-card">
                <span class="motorpool-kpi-label">Completed</span>
                <strong class="motorpool-kpi-value"><?php echo $completed_repairs_count; ?></strong>
                <span class="motorpool-kpi-note">Repair history</span>
            </article>
            <article class="motorpool-kpi-card">
                <span class="motorpool-kpi-label">Fleet Vehicles</span>
                <strong class="motorpool-kpi-value"><?php echo $vehicle_count; ?></strong>
                <span class="motorpool-kpi-note">Registered units</span>
            </article>
        </section>

        <div class="motorpool-chart-grid">
            <div class="motorpool-chart-column">
                <div class="bento-box motorpool-chart-card">
                    <div class="motorpool-card-heading">
                        <div>
                            <div class="bento_title">Repair Trend</div>
                            <p class="motorpool-card-subtitle">Daily repair volume from recent records.</p>
                        </div>
                        <span class="motorpool-chart-badge">Last 7 days</span>
                    </div>
                    <div class="chart-container chart-container--line">
                        <canvas id="dailyRepairsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="motorpool-chart-column">
                <div class="bento-box motorpool-chart-card">
                    <div class="motorpool-card-heading">
                        <div>
                            <div class="bento_title">Most Repaired Vehicles</div>
                            <p class="motorpool-card-subtitle">Top completed repair counts by plate number.</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="repairsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="motorpool-chart-column">
                <div class="bento-box motorpool-chart-card">
                    <div class="motorpool-card-heading">
                        <div>
                            <div class="bento_title">Repair Type Breakdown</div>
                            <p class="motorpool-card-subtitle">Most common repair problems from all records.</p>
                        </div>
                    </div>
                    <div class="chart-container-small">
                        <canvas id="repairTypesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div id="repairChartData"
            data-dates='<?php echo htmlspecialchars($dates_json, ENT_QUOTES, 'UTF-8'); ?>'
            data-counts='<?php echo htmlspecialchars($counts_json, ENT_QUOTES, 'UTF-8'); ?>'
            data-vehicle-labels='<?php echo htmlspecialchars($vehicle_labels_json, ENT_QUOTES, 'UTF-8'); ?>'
            data-vehicle-counts='<?php echo htmlspecialchars($repair_counts_json, ENT_QUOTES, 'UTF-8'); ?>'
            data-repair-type-labels='<?php echo htmlspecialchars($repair_type_labels_json, ENT_QUOTES, 'UTF-8'); ?>'
            data-repair-type-counts='<?php echo htmlspecialchars($repair_type_counts_json, ENT_QUOTES, 'UTF-8'); ?>'
            hidden></div>

        <!-- AI Predictive Maintenance Section -->


        <!-- Add a new row for the table -->
        <div class="motorpool-table-section">
            <div>
                <!-- Header section outside of table -->
                <div class="motorpool-toolbar">
                    <h4 class="mb-0">Repair List</h4>
                    <div class="motorpool-actions">
                        <div class="input-group motorpool-search">
                            <input type="text" id="repairSearch" class="form-control form-control-sm" placeholder="Search repairs...">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addRepairModal">
                            <i class="fas fa-wrench"></i> Add Repair
                        </button>
                        <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                            <i class="fas fa-plus"></i> Add Vehicle
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#updateVehicleModal">
                            <i class="fas fa-edit"></i> Update Vehicle
                        </button>
                        <button type="button" class="btn btn-info btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#completedRepairsModal">
                            <i class="fas fa-check-circle"></i> Show Repaired Data
                        </button>
                    </div>
                </div>

                <!-- Scrollable table with sticky header -->
                <div class="table-responsive motorpool-table-wrap">
                    <table class="table table-borderless motorpool-table">
                        <thead class="sticky-top bg-light border-bottom">
                            <tr>
                                <th scope="col">Plate no.</th>

                                <th scope="col">Date</th>
                                <th scope="col">Office</th>
                                <th scope="col">Repair Type</th>
                                <th scope="col">Parts Replaced</th>
                                <th scope="col">Remarks</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            <?php
                            $repair_list = get_motorpool_repairs();
                            if (!empty($repair_list)) {
                                foreach ($repair_list as $repair) {
                            ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($repair['plate_no'] ?? ''); ?></td>

                                        <td><?php echo htmlspecialchars($repair['repair_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($repair['office'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($repair['repair_type'] ?? ''); ?></td>
                                        <td>
                                            <?php
                                            $parts = $repair['parts_replaced'] ?? '';
                                            // Replace literal \r\n with actual line breaks, then convert to HTML
                                            $parts = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $parts);
                                            echo nl2br(htmlspecialchars($parts));
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($repair['remarks'] ?? ''); ?></td>
                                        <td>
                                            <form class="status-form" data-repair-id="<?php echo $repair['id']; ?>">
                                                <?php echo asset_csrf_input(); ?>
                                                <select class="form-select form-select-sm status-select" name="status">
                                                    <option value="Pending" <?php echo ($repair['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="In Progress" <?php echo ($repair['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="Completed" <?php echo ($repair['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="Cancelled" <?php echo ($repair['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm edit-repair-btn me-1" data-repair-id="<?php echo $repair['id']; ?>" title="Edit Repair">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm delete-repair-btn" data-repair-id="<?php echo $repair['id']; ?>" title="Delete Repair">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php
                                }
                            } else {
                                ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No active repair records found.</td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Repairs Modal -->
    <div class="modal fade" id="completedRepairsModal" tabindex="-1" aria-labelledby="completedRepairsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="completedRepairsModalLabel">Completed Repairs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="completedRepairSearch" class="form-control" placeholder="Search completed repairs...">
                    </div>
                    <div class="table-responsive motorpool-modal-table-wrap">
                        <table class="table table-striped" id="completedRepairsTable">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Plate No.</th>
                                    <th>Date</th>
                                    <th>Office</th>
                                    <th>Repair Type</th>
                                    <th>Parts Replaced</th>
                                    <th>Remarks</th>
                                 
                                </tr>
                            </thead>
                            <tbody>
                                <?php $completed_repairs = get_completed_repairs(); ?>
                                <?php if (!empty($completed_repairs)) { ?>
                                    <?php foreach ($completed_repairs as $repair) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($repair['plate_no'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($repair['repair_date'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($repair['office'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($repair['repair_type'] ?? ''); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars(str_replace(['\\r\\n', '\\n', '\\r'], "\n", $repair['parts_replaced'] ?? ''))); ?></td>
                                            <td><?php echo htmlspecialchars($repair['remarks'] ?? ''); ?></td>
                                            
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No completed repair records found</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addVehicleForm" method="post" action="add_vehicle_record_motorpool.php">
                        <?php echo asset_csrf_input(); ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="plate_no" class="form-label">Plate Number</label>
                                <input type="text" class="form-control" id="plate_no" name="plate_no" required>
                            </div>
                            <div class="col-md-6">
                                <label for="car_model" class="form-label">Car Model</label>
                                <input type="text" class="form-control" id="car_model" name="car_model" placeholder="e.g. Toyota Hilux, Mitsubishi L300">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="no_dispatch" class="form-label">Number of Dispatches</label>
                                <input type="number" class="form-control" id="no_dispatch" name="no_dispatch" min="0">
                            </div>
                            <div class="col-md-6">
                                <label for="old_mileage" class="form-label">Old Mileage</label>
                                <input type="number" class="form-control" id="old_mileage" name="old_mileage" min="0">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="latest_mileage" class="form-label">Latest Mileage</label>
                                <input type="number" class="form-control" id="latest_mileage" name="latest_mileage" min="0">
                            </div>
                            <div class="col-md-6">
                                <label for="no_of_repairs" class="form-label">Number of Repairs</label>
                                <input type="number" class="form-control" id="no_of_repairs" name="no_of_repairs" min="0">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="latest_repair_date" class="form-label">Latest Repair Date</label>
                                <input type="date" class="form-control" id="latest_repair_date" name="latest_repair_date">
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="Active">Active</option>
                                    <option value="Under Repair">Under Repair</option>
                                    <option value="Out of Service">Out of Service</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="date_procured" class="form-label">Date Procured</label>
                                <input type="date" class="form-control" id="date_procured" name="date_procured">
                            </div>
                            <div class="col-md-6">
                                <label for="add_office" class="form-label">Office</label>
                                <select class="form-select" id="add_office" name="office">
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
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="addVehicleForm" class="btn btn-primary">Save Vehicle</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Edit Repair Modal -->
    <!-- Edit Repair Modal -->
    <div class="modal fade" id="editRepairModal" tabindex="-1" aria-labelledby="editRepairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRepairModalLabel">Edit Repair</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editRepairForm">
                        <?php echo asset_csrf_input(); ?>
                        <input type="hidden" id="edit_repair_id" name="edit_repair_id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_vehicle_id" class="form-label">Vehicle</label>
                                <select class="form-select" id="edit_vehicle_id" name="edit_vehicle_id" required>
                                    <option value="">Select Vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo htmlspecialchars($vehicle['plate_no']); ?>">
                                            <?php echo htmlspecialchars($vehicle['plate_no']); ?>
                                            <?php if (!empty($vehicle['car_model'])): ?>
                                                - <?php echo htmlspecialchars($vehicle['car_model']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_repair_date" class="form-label">Repair Date</label>
                                <input type="date" class="form-control" id="edit_repair_date" name="edit_repair_date" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Repair Type</label>
                                <div class="border rounded p-3 motorpool-checklist">
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="AC Repair" id="edit_repair_ac">
                                        <label class="form-check-label" for="edit_repair_ac">AC Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Battery Replacement" id="edit_repair_battery">
                                        <label class="form-check-label" for="edit_repair_battery">Battery Replacement</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Body Repair" id="edit_repair_body">
                                        <label class="form-check-label" for="edit_repair_body">Body Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Body Work" id="edit_repair_body_work">
                                        <label class="form-check-label" for="edit_repair_body_work">Body Work</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Brake Repair" id="edit_repair_brake">
                                        <label class="form-check-label" for="edit_repair_brake">Brake Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Cooling System" id="edit_repair_cooling">
                                        <label class="form-check-label" for="edit_repair_cooling">Cooling System</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Electrical Repair" id="edit_repair_electrical">
                                        <label class="form-check-label" for="edit_repair_electrical">Electrical Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Engine Repair" id="edit_repair_engine">
                                        <label class="form-check-label" for="edit_repair_engine">Engine Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Engine Tune-up" id="edit_repair_tuneup">
                                        <label class="form-check-label" for="edit_repair_tuneup">Engine Tune-up</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Exhaust System" id="edit_repair_exhaust">
                                        <label class="form-check-label" for="edit_repair_exhaust">Exhaust System</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Fuel System" id="edit_repair_fuel">
                                        <label class="form-check-label" for="edit_repair_fuel">Fuel System</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Oil Change" id="edit_repair_oil">
                                        <label class="form-check-label" for="edit_repair_oil">Oil Change</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Preventive Maintenance" id="edit_repair_preventive">
                                        <label class="form-check-label" for="edit_repair_preventive">Preventive Maintenance</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Suspension Repair" id="edit_repair_suspension">
                                        <label class="form-check-label" for="edit_repair_suspension">Suspension Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Tire Replacement" id="edit_repair_tire">
                                        <label class="form-check-label" for="edit_repair_tire">Tire Replacement</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Transmission Service" id="edit_repair_transmission">
                                        <label class="form-check-label" for="edit_repair_transmission">Transmission Service</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input edit-repair-type-checkbox" type="checkbox" name="edit_repair_type[]" value="Other" id="edit_repair_other">
                                        <label class="form-check-label" for="edit_repair_other">Other</label>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Select all applicable repair types</small>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_parts_replaced" class="form-label">Parts Replaced</label>
                                <textarea class="form-control motorpool-textarea" id="edit_parts_replaced" name="edit_parts_replaced" rows="4" placeholder="Enter parts replaced (press Enter for new line)"></textarea>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_mileage" class="form-label">Current Mileage</label>
                                <input type="number" class="form-control" id="edit_mileage" name="edit_mileage" min="0">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_cost" class="form-label">Repair Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="edit_cost" name="edit_cost" min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_office" class="form-label">Office</label>
                                <select class="form-select" id="edit_office" name="edit_office" required>
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
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_notes" class="form-label">Service Type</label>
                                <select class="form-select" id="edit_notes" name="edit_notes" required>
                                    <option value="">Select Service Type</option>
                                    <option value="PMS">PMS</option>
                                    <option value="REPAIR">REPAIR</option>
                                    <option value="RESCUE">RESCUE</option>
                                    <option value="REPAIR & PMS">REPAIR & PMS</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="edit_status" required>
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="editRepairForm" class="btn btn-primary">Update Repair</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Add Repair Modal -->
    <div class="modal fade" id="addRepairModal" tabindex="-1" aria-labelledby="addRepairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable motorpool-repair-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRepairModalLabel">Add New Repair</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addRepairForm" method="post" action="add_repair_motorpool.php">
                        <?php echo asset_csrf_input(); ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="vehicle_id" class="form-label">Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                    <option value="">Select Vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo htmlspecialchars($vehicle['plate_no']); ?>">
                                            <?php echo htmlspecialchars($vehicle['plate_no']); ?>
                                            <?php if (!empty($vehicle['car_model'])): ?>
                                                - <?php echo htmlspecialchars($vehicle['car_model']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="repair_date" class="form-label">Repair Date</label>
                                <input type="date" class="form-control" id="repair_date" name="repair_date" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Repair Type</label>
                                <div class="border rounded p-3 motorpool-checklist">
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="AC Repair" id="repair_ac">
                                        <label class="form-check-label" for="repair_ac">AC Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Battery Replacement" id="repair_battery">
                                        <label class="form-check-label" for="repair_battery">Battery Replacement</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Body Repair" id="repair_body">
                                        <label class="form-check-label" for="repair_body">Body Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Brake Repair" id="repair_brake">
                                        <label class="form-check-label" for="repair_brake">Brake Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Engine Repair" id="repair_engine">
                                        <label class="form-check-label" for="repair_engine">Engine Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Engine Tune-up" id="repair_tuneup">
                                        <label class="form-check-label" for="repair_tuneup">Engine Tune-up</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Oil Change" id="repair_oil">
                                        <label class="form-check-label" for="repair_oil">Oil Change</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Suspension Repair" id="repair_suspension">
                                        <label class="form-check-label" for="repair_suspension">Suspension Repair</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Tire Replacement" id="repair_tire">
                                        <label class="form-check-label" for="repair_tire">Tire Replacement</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Transmission Service" id="repair_transmission">
                                        <label class="form-check-label" for="repair_transmission">Transmission Service</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input repair-type-checkbox" type="checkbox" name="repair_type[]" value="Other" id="repair_other">
                                        <label class="form-check-label" for="repair_other">Other</label>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Select all applicable repair types</small>
                            </div>
                            <div class="col-md-6">
                                <label for="parts_replaced" class="form-label">Parts Replaced</label>
                                <textarea class="form-control motorpool-textarea" id="parts_replaced" name="parts_replaced" rows="3" placeholder="Enter parts replaced (press Enter for new line)"></textarea>
                            </div>


                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="mileage" class="form-label">Current Mileage</label>
                                <input type="number" class="form-control" id="mileage" name="mileage" min="0">
                            </div>

                            <div class="col-md-6">
                                <label for="cost" class="form-label">Repair Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="cost" name="cost" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="office" class="form-label">Office</label>
                                <input type="text" class="form-control" id="office" name="office" required placeholder="Enter Office">
                            </div>

                            <div class="col-md-6">
                                <label for="notes" class="form-label">Service Type</label>
                                <select class="form-select" id="notes" name="notes" required>
                                    <option value="">Select Service Type</option>
                                    <option value="PMS">PMS</option>
                                    <option value="REPAIR">REPAIR</option>
                                    <option value="RESCUE">RESCUE</option>
                                    <option value="REPAIR & PMS">REPAIR & PMS</option>
                                </select>
                            </div>
                        </div>


                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="addRepairForm" class="btn btn-primary">Save Repair</button>
                </div>
            </div>
        </div>
    </div>
   <div class="modal fade" id="updateVehicleModal" tabindex="-1" aria-labelledby="updateVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateVehicleModalLabel">Update Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateVehicleForm" method="post" action="update_vehicle_record_motorpool.php">
                    <?php echo asset_csrf_input(); ?>
                    <!-- Hidden field to store original plate number for updates -->
                    <input type="hidden" id="original_plate_no" name="original_plate_no">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="update_plate_no" class="form-label">Plate Number</label>
                            <input type="text" class="form-control" id="update_plate_no" name="plate_no" required>
                        </div>
                        <div class="col-md-6">
                            <label for="update_car_model" class="form-label">Car Model</label>
                            <input type="text" class="form-control" id="update_car_model" name="car_model" placeholder="e.g. Toyota Hilux, Mitsubishi L300">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="update_no_dispatch" class="form-label">Number of Dispatches</label>
                            <input type="number" class="form-control" id="update_no_dispatch" name="no_dispatch" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="update_old_mileage" class="form-label">Old Mileage</label>
                            <input type="number" class="form-control" id="update_old_mileage" name="old_mileage" min="0">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="update_latest_mileage" class="form-label">Latest Mileage</label>
                            <input type="number" class="form-control" id="update_latest_mileage" name="latest_mileage" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="update_no_of_repairs" class="form-label">Number of Repairs</label>
                            <input type="number" class="form-control" id="update_no_of_repairs" name="no_of_repairs" min="0">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="update_latest_repair_date" class="form-label">Latest Repair Date</label>
                            <input type="date" class="form-control" id="update_latest_repair_date" name="latest_repair_date">
                        </div>
                        <div class="col-md-6">
                            <label for="update_status" class="form-label">Status</label>
                            <select class="form-select" id="update_status" name="status">
                                <option value="Active">Active</option>
                                <option value="Under Repair">Under Repair</option>
                                <option value="Out of Service">Out of Service</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="update_date_procured" class="form-label">Date Procured</label>
                            <input type="date" class="form-control" id="update_date_procured" name="date_procured">
                        </div>
                        <div class="col-md-6">
                            <label for="update_office" class="form-label">Office</label>
                            <!-- Fixed: Changed id from "edit_office" to "update_office" and name to "office" -->
                            <select class="form-select" id="update_office" name="office">
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
                            </select>
                        </div>
                    </div>
                </form>
                <hr class="my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Select Vehicle to Update</h5>
                    <div class="input-group motorpool-search">
                        <input type="text" id="vehicleSearchInput" class="form-control form-control-sm" placeholder="Search vehicles...">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="clearVehicleSearch">
                            <i class="fas fa-window-close"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive mt-3 motorpool-modal-table-wrap motorpool-modal-table-wrap--short">
                    <table class="table table-striped table-hover" id="vehicleSelectionTable">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Plate No.</th>
                                <th>Car Model</th>
                                <th>Office</th>
                                <th>Status</th>
                                <th>Old Mileage</th>
                                <th>Latest Mileage</th>
                                <th>NO. of Repairs</th>
                                <th>Latest Repair Date</th>
                                <th>Date Procured</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- This will be populated dynamically with JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="updateVehicleForm" class="btn btn-warning">Update Vehicle</button>
            </div>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="motorpool_admin.js"></script>
    <script>
        window.motorpoolVehicleData = <?php echo json_encode($vehicles); ?>;
    </script>

</body>

</html>
