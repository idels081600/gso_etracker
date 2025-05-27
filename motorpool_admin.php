<?php
// Include database connection
require_once 'db_asset.php';
require_once 'motorpool_data_display.php';

// Fetch vehicles from the database
$vehicles = get_vehicles_list();
$repair_list = get_motorpool_repairs();
$most_repaired_vehicles = count_completed_repairs_by_car();

// Extract plate numbers (labels) and repair counts (data) for the chart
$vehicle_labels = array_keys($most_repaired_vehicles);
$repair_counts = array_values($most_repaired_vehicles);

// Convert to JSON for JavaScript
$vehicle_labels_json = json_encode($vehicle_labels);
$repair_counts_json = json_encode($repair_counts);

// Get completed repairs by office
$office_repairs = count_completed_repairs_by_office();

// Extract office names (labels) and repair counts (data) for the chart
$office_labels = array_keys($office_repairs);
$office_counts = array_values($office_repairs);

// Convert to JSON for JavaScript
$office_labels_json = json_encode($office_labels);
$office_counts_json = json_encode($office_counts);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transportation Tracker</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="motorpool_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.1.10/vue.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

</head>

<body>
    <?php
    // // Database connection
    // $conn = mysqli_connect("localhost", "username", "password", "database_name");

    // Fetch vehicle repair history
    function getVehicleRepairHistory()
    {
        global $conn;
        // In a real implementation, this would fetch from your database
        // For demo purposes, we'll create sample data
        return [
            ['vehicle_id' => 1, 'plate_no' => 'ABC-123', 'repair_date' => '2023-01-15', 'repair_type' => 'Oil Change', 'mileage' => 15000, 'parts_replaced' => 'Oil Filter', 'cost' => 1500],
            ['vehicle_id' => 1, 'plate_no' => 'ABC-123', 'repair_date' => '2023-03-20', 'repair_type' => 'Brake Repair', 'mileage' => 18000, 'parts_replaced' => 'Brake Pads', 'cost' => 3500],
            ['vehicle_id' => 1, 'plate_no' => 'ABC-123', 'repair_date' => '2023-05-25', 'repair_type' => 'Tire Replacement', 'mileage' => 21000, 'parts_replaced' => 'Tires', 'cost' => 8000],
            ['vehicle_id' => 2, 'plate_no' => 'XYZ-789', 'repair_date' => '2023-02-10', 'repair_type' => 'Battery Replacement', 'mileage' => 25000, 'parts_replaced' => 'Battery', 'cost' => 4500],
            ['vehicle_id' => 2, 'plate_no' => 'XYZ-789', 'repair_date' => '2023-06-05', 'repair_type' => 'AC Repair', 'mileage' => 28000, 'parts_replaced' => 'Compressor', 'cost' => 7500],
            ['vehicle_id' => 3, 'plate_no' => 'DEF-456', 'repair_date' => '2023-01-05', 'repair_type' => 'Engine Tune-up', 'mileage' => 30000, 'parts_replaced' => 'Spark Plugs', 'cost' => 2500],
            ['vehicle_id' => 3, 'plate_no' => 'DEF-456', 'repair_date' => '2023-02-20', 'repair_type' => 'Transmission Service', 'mileage' => 32000, 'parts_replaced' => 'Transmission Fluid', 'cost' => 3000],
            ['vehicle_id' => 3, 'plate_no' => 'DEF-456', 'repair_date' => '2023-04-10', 'repair_type' => 'Suspension Repair', 'mileage' => 35000, 'parts_replaced' => 'Shock Absorbers', 'cost' => 6000],
            ['vehicle_id' => 4, 'plate_no' => 'GHI-789', 'repair_date' => '2023-03-15', 'repair_type' => 'Oil Change', 'mileage' => 10000, 'parts_replaced' => 'Oil Filter', 'cost' => 1500],
            ['vehicle_id' => 5, 'plate_no' => 'JKL-012', 'repair_date' => '2023-01-25', 'repair_type' => 'Brake Repair', 'mileage' => 22000, 'parts_replaced' => 'Brake Pads', 'cost' => 3500],
            ['vehicle_id' => 5, 'plate_no' => 'JKL-012', 'repair_date' => '2023-05-10', 'repair_type' => 'Tire Rotation', 'mileage' => 25000, 'parts_replaced' => 'None', 'cost' => 1000],
        ];
    }

    // Group repair history by vehicle
    function groupRepairsByVehicle($repairs)
    {
        $grouped = [];
        foreach ($repairs as $repair) {
            $vehicleId = $repair['vehicle_id'];
            if (!isset($grouped[$vehicleId])) {
                $grouped[$vehicleId] = [
                    'plate_no' => $repair['plate_no'],
                    'repairs' => []
                ];
            }
            $grouped[$vehicleId]['repairs'][] = $repair;
        }
        return $grouped;
    }

    // Predictive maintenance algorithm
    function predictNextMaintenance($vehicleRepairs)
    {
        $predictions = [];

        foreach ($vehicleRepairs as $vehicleId => $data) {
            $plateNo = $data['plate_no'];
            $repairs = $data['repairs'];

            // Calculate average time between repairs
            $repairDates = array_map(function ($repair) {
                return strtotime($repair['repair_date']);
            }, $repairs);

            if (count($repairDates) >= 2) {
                sort($repairDates);
                $intervals = [];
                for ($i = 1; $i < count($repairDates); $i++) {
                    $intervals[] = $repairDates[$i] - $repairDates[$i - 1];
                }

                $avgInterval = array_sum($intervals) / count($intervals);
                $lastRepairDate = max($repairDates);
                $predictedNextDate = $lastRepairDate + $avgInterval;

                // Calculate days until next predicted maintenance
                $daysUntil = ceil(($predictedNextDate - time()) / (60 * 60 * 24));

                // Determine maintenance urgency
                $urgency = 'normal';
                if ($daysUntil < 7) {
                    $urgency = 'urgent';
                } else if ($daysUntil < 30) {
                    $urgency = 'upcoming';
                }

                $predictions[$vehicleId] = [
                    'plate_no' => $plateNo,
                    'last_repair' => date('Y-m-d', $lastRepairDate),
                    'predicted_date' => date('Y-m-d', $predictedNextDate),
                    'days_until' => $daysUntil,
                    'urgency' => $urgency,
                    'confidence' => min(95, 50 + (count($repairs) * 5)) // Higher confidence with more data points
                ];
            } else {
                // Not enough repair history for prediction
                $predictions[$vehicleId] = [
                    'plate_no' => $plateNo,
                    'last_repair' => count($repairs) > 0 ? date('Y-m-d', $repairDates[0]) : 'N/A',
                    'predicted_date' => 'Insufficient data',
                    'days_until' => null,
                    'urgency' => 'unknown',
                    'confidence' => 0
                ];
            }
        }

        return $predictions;
    }

    $repairHistory = getVehicleRepairHistory();
    $vehicleRepairs = groupRepairsByVehicle($repairHistory);
    $maintenancePredictions = predictNextMaintenance($vehicleRepairs);
    ?>

    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Mark Angelo Manding</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="container-fluid">
        <h1 class="Title_header">Motorpool</h1>
        <div class="row g-3">

            <div class="col-lg-3">
                <div class="bento-box mb-3" style="height: 170px;">
                    <div class="bento_title">
                        Vehicle Repair Status
                    </div>
                    <div class="status-container">
                        <div class="status-item">
                            <div class="status-label">Repaired</div>
                            <div class="status-value"><?php echo count_repaired_repairs(); ?></div>
                        </div>
                        <div class="status-item">
                            <div class="status-label">In Progress</div>
                            <div class="status-value"><?php echo count_in_progress_repairs(); ?></div>
                        </div>
                        <div class="status-item">
                            <div class="status-label">Pending</div>
                            <div class="status-value"><?php echo count_pending_repairs(); ?></div>
                        </div>
                    </div>
                </div>


                <div class="bento-box mb-3" style="height: 230px; overflow: hidden;">
                    <div class="bento_title">
                        Repairs per day
                    </div>
                    <div class="chart-container" style="position: relative; height: calc(100% - 30px); width: 100%;">
                        <canvas id="dailyRepairsChart"></canvas>
                    </div>

                    <?php
                    // Prepare data for the chart in PHP
                    $daily_repairs = count_daily_repairs();

                    // Prepare data for chart
                    $dates = [];
                    $counts = [];

                    // Get the last 7 days of data (or fewer if not enough data)
                    $chart_data = array_slice($daily_repairs, 0, 7);

                    // Reverse to show chronological order
                    $chart_data = array_reverse($chart_data);

                    foreach ($chart_data as $repair) {
                        $dates[] = date('M d', strtotime($repair['repair_day']));
                        $counts[] = $repair['repair_count'];
                    }

                    // Convert PHP arrays to JSON for JavaScript
                    $dates_json = json_encode($dates);
                    $counts_json = json_encode($counts);
                    ?>

                    <!-- Data attributes to pass PHP data to JavaScript -->
                    <div id="repairChartData"
                        data-dates='<?php echo $dates_json; ?>'
                        data-counts='<?php echo $counts_json; ?>'
                        style="display: none;"></div>
                </div>

            </div>

            <!-- Second column -->
            <div class="col-lg-4">
                <div class="bento-box" style="height: 416px;">
                    <div class="bento_title">
                        Car with Most Repairs
                    </div>
                    <div class="chart-container">
                        <canvas id="repairsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Third column -->
            <div class="col-lg-4">
                <div class="bento-box" style="height: 416px;">
                    <div class="bento_title">
                        Offices of Repaired Vehicles
                    </div>
                    <div class="chart-container-small">
                        <canvas id="officesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Predictive Maintenance Section -->


        <!-- Add a new row for the table -->
        <div class="row mt-3">
            <div class="col-lg-11">
                <!-- Header section outside of table -->
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h4 class="mb-0">Repair List</h4>
                    <div class="d-flex align-items-center">
                        <div class="input-group me-2" style="width: 250px;">
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
                    </div>
                </div>

                <!-- Scrollable table with sticky header -->
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-borderless">
                        <thead class="sticky-top bg-light border-bottom" style="border-bottom: 2px solid #dee2e6 !important;">
                            <tr>
                                <th scope="col">Plate no.</th>
                                <th scope="col">Model</th>
                                <th scope="col">Date</th>
                                <th scope="col">Office</th>
                                <th scope="col">Repair Type</th>
                                <th scope="col">mileage</th>
                                <th scope="col">Parts Replaced</th>
                                <th scope="col">Cost</th>
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
                                        <td><?php echo htmlspecialchars($repair['car_model'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($repair['repair_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($repair['office'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($repair['repair_type'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($repair['mileage'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($repair['parts_replaced'] ?? ''); ?></td>
                                        <td><?php echo '₱' . number_format($repair['cost'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($repair['remarks'] ?? ''); ?></td>
                                        <td>
                                            <form class="status-form" data-repair-id="<?php echo $repair['id']; ?>">
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
                                    <td colspan="11" class="text-center">No repair records found</td>
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
    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addVehicleForm" method="post" action="add_vehicle_record_motorpool.php">
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
    <div class="modal fade" id="editRepairModal" tabindex="-1" aria-labelledby="editRepairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRepairModalLabel">Edit Repair</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editRepairForm">
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
                                <label for="edit_repair_type" class="form-label">Repair Type</label>
                                <select class="form-select" id="edit_repair_type" name="edit_repair_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Oil Change">Oil Change</option>
                                    <option value="Brake Repair">Brake Repair</option>
                                    <option value="Tire Replacement">Tire Replacement</option>
                                    <option value="Battery Replacement">Battery Replacement</option>
                                    <option value="Engine Tune-up">Engine Tune-up</option>
                                    <option value="AC Repair">AC Repair</option>
                                    <option value="Transmission Service">Transmission Service</option>
                                    <option value="Suspension Repair">Suspension Repair</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_mileage" class="form-label">Current Mileage</label>
                                <input type="number" class="form-control" id="edit_mileage" name="edit_mileage" min="0" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_parts_replaced" class="form-label">Parts Replaced</label>
                                <input type="text" class="form-control" id="edit_parts_replaced" name="edit_parts_replaced">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_cost" class="form-label">Repair Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="edit_cost" name="edit_cost" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_office" class="form-label">Office</label>
                                <select class="form-select" id="edit_office" name="edit_office" required>
                                    <option value="">Select Office</option>
                                    <option value="CGSO">CGSO</option>
                                    <option value="CTO">CTO</option>
                                    <option value="HRMO">HRMO</option>
                                    <option value="PNP">PNP</option>
                                    <option value="CMO">CMO</option>
                                    <option value="CEE">CEE</option>
                                    <option value="CVMO">CVMO</option>
                                    <option value="CEO">CEO</option>
                                    <option value="CHO">CHO</option>
                                    <option value="CDRRMO">CDRRMO</option>
                                    <option value="PDAO">PDAO</option>
                                    <option value="ALERT">ALERT</option>
                                    <option value="CAVO">CAVO</option>
                                    <option value="ADMIN">ADMIN</option>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRepairModalLabel">Add New Repair</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addRepairForm" method="post" action="add_repair_motorpool.php">
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
                                <label for="repair_type" class="form-label">Repair Type</label>
                                <select class="form-select" id="repair_type" name="repair_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Oil Change">Oil Change</option>
                                    <option value="Brake Repair">Brake Repair</option>
                                    <option value="Tire Replacement">Tire Replacement</option>
                                    <option value="Battery Replacement">Battery Replacement</option>
                                    <option value="Engine Tune-up">Engine Tune-up</option>
                                    <option value="AC Repair">AC Repair</option>
                                    <option value="Transmission Service">Transmission Service</option>
                                    <option value="Suspension Repair">Suspension Repair</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="mileage" class="form-label">Current Mileage</label>
                                <input type="number" class="form-control" id="mileage" name="mileage" min="0" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parts_replaced" class="form-label">Parts Replaced</label>
                                <input type="text" class="form-control" id="parts_replaced" name="parts_replaced">
                            </div>
                            <div class="col-md-6">
                                <label for="cost" class="form-label">Repair Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="cost" name="cost" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="office" class="form-label">Office</label>
                                <select class="form-select" id="office" name="office" required>
                                    <option value="">Select Office</option>
                                    <option value="CGSO">CGSO</option>
                                    <option value="CTO">CTO</option>
                                    <option value="HRMO">HRMO</option>
                                    <option value="PNP">PNP</option>
                                    <option value="CMO">CMO</option>
                                    <option value="CEE">CEE</option>
                                    <option value="CVMO">CVMO</option>
                                    <option value="CEO">CEO</option>
                                    <option value="CHO">CHO</option>
                                    <option value="CDRRMO">CDRRMO</option>
                                    <option value="PDAO">PDAO</option>
                                    <option value="ALERT">ALERT</option>
                                    <option value="CAVO">CAVO</option>
                                    <option value="ADMIN">ADMIN</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="notes" class="form-label">Service Type</label>
                                <select class="form-select" id="notes" name="notes" required>
                                    <option value="">Select Service Type</option>
                                    <option value="PMS">PMS</option>
                                    <option value="REPAIR">REPAIR</option>
                                    <option value="RESCUE">RESCUE</option>
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
                    <form id="updateVehicleForm" method="post" action=" update_vehicle_record_motorpool.php">

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
                        </div>
                    </form>
                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Select Vehicle to Update</h5>
                        <div class="input-group" style="width: 250px;">
                            <input type="text" id="vehicleSearchInput" class="form-control form-control-sm" placeholder="Search vehicles...">
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="clearVehicleSearch">
                                <i class="fas fa-window-close"></i>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive mt-3" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-striped table-hover" id="vehicleSelectionTable">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Plate No.</th>
                                    <th>Car Model</th>
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
        const ctx = document.getElementById("repairsChart").getContext("2d");
        const repairsChart = new Chart(ctx, {
            type: "bar",
            data: {
                labels: <?php echo $vehicle_labels_json; ?>,
                datasets: [{
                    label: "Number of Completed Repairs",
                    data: <?php echo $repair_counts_json; ?>,
                    backgroundColor: [
                        "rgba(75, 192, 192, 0.6)",
                        "rgba(54, 162, 235, 0.6)",
                        "rgba(153, 102, 255, 0.6)",
                        "rgba(255, 159, 64, 0.6)",
                        "rgba(255, 99, 132, 0.6)",
                    ],
                    borderColor: [
                        "rgba(75, 192, 192, 1)",
                        "rgba(54, 162, 235, 1)",
                        "rgba(153, 102, 255, 1)",
                        "rgba(255, 159, 64, 1)",
                        "rgba(255, 99, 132, 1)",
                    ],
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Completed Repairs'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Vehicle Plate Number'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                return 'Vehicle: ' + tooltipItems[0].label;
                            },
                            label: function(context) {
                                return 'Completed Repairs: ' + context.raw;
                            }
                        }
                    }
                }
            },
        });
        const officesCtx = document.getElementById("officesChart").getContext("2d");
        const officesChart = new Chart(officesCtx, {
            type: "pie",
            data: {
                labels: <?php echo $office_labels_json; ?>,
                datasets: [{
                    data: <?php echo $office_counts_json; ?>,
                    backgroundColor: [
                        "rgba(255, 99, 132, 0.8)",
                        "rgba(54, 162, 235, 0.8)",
                        "rgba(255, 206, 86, 0.8)",
                        "rgba(75, 192, 192, 0.8)",
                        "rgba(153, 102, 255, 0.8)",
                        "rgba(201, 203, 207, 0.8)",
                        "rgba(255, 159, 64, 0.8)",
                        "rgba(142, 65, 64, 0.8)",
                        "rgba(59, 72, 169, 0.8)",
                        "rgba(100, 120, 140, 0.8)",
                    ],
                    borderWidth: 1,
                }],
            },
            plugins: [ChartDataLabels],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: (value, context) => {
                            return `${value}`;
                        }
                    },
                    legend: {
                        position: "bottom",
                    },
                    title: {
                        display: true,
                        text: 'Completed Repairs by Office',
                        font: {
                            size: 16
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            },
        });
    </script>

</body>

</html>