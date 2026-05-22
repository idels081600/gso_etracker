<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();

$root = dirname(__DIR__);
set_include_path($root . PATH_SEPARATOR . get_include_path());
require_once $root . '/db_asset.php';
require_once $root . '/motorpool_data_display.php';

$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['master_action'] ?? '';

    if ($action === 'add_vehicle') {
        $stmt = $conn->prepare('INSERT INTO vehicle_records (plate_no, car_model, office, no_dispatch, old_mileage, latest_mileage, no_of_repairs, status, date_procured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $plate = trim($_POST['plate_no'] ?? '');
        $model = trim($_POST['car_model'] ?? '');
        $office = trim($_POST['office'] ?? '');
        $dispatch = (int)($_POST['no_dispatch'] ?? 0);
        $oldMileage = (int)($_POST['old_mileage'] ?? 0);
        $latestMileage = (int)($_POST['latest_mileage'] ?? 0);
        $repairs = (int)($_POST['no_of_repairs'] ?? 0);
        $status = trim($_POST['status'] ?? 'Active');
        $procured = $_POST['date_procured'] !== '' ? $_POST['date_procured'] : null;
        $stmt->bind_param('sssiiiiss', $plate, $model, $office, $dispatch, $oldMileage, $latestMileage, $repairs, $status, $procured);
        $notice = $stmt->execute() ? 'Vehicle added.' : 'Unable to add vehicle.';
        $stmt->close();
    }

    if ($action === 'add_repair') {
        $plate = trim($_POST['vehicle_id'] ?? '');
        $model = '';
        $vehicleStmt = $conn->prepare('SELECT car_model FROM vehicle_records WHERE plate_no = ? LIMIT 1');
        $vehicleStmt->bind_param('s', $plate);
        $vehicleStmt->execute();
        $vehicleResult = $vehicleStmt->get_result();
        if ($vehicleResult && $vehicleRow = $vehicleResult->fetch_assoc()) {
            $model = $vehicleRow['car_model'] ?? '';
        }
        $vehicleStmt->close();

        $types = isset($_POST['repair_type']) && is_array($_POST['repair_type']) ? implode(', ', $_POST['repair_type']) : '';
        $stmt = $conn->prepare('INSERT INTO motorpool_repair (plate_no, car_model, repair_date, repair_type, mileage, parts_replaced, cost, remarks, status, office) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $date = $_POST['repair_date'] ?? date('Y-m-d');
        $mileage = (int)($_POST['mileage'] ?? 0);
        $parts = trim($_POST['parts_replaced'] ?? '');
        $cost = (float)($_POST['cost'] ?? 0);
        $remarks = trim($_POST['notes'] ?? '');
        $status = 'Pending';
        $office = trim($_POST['office'] ?? '');
        $stmt->bind_param('ssssisdsss', $plate, $model, $date, $types, $mileage, $parts, $cost, $remarks, $status, $office);
        $notice = $stmt->execute() ? 'Repair added.' : 'Unable to add repair.';
        $stmt->close();
    }
}

$vehicles = get_vehicles_list();
$activeRepairs = get_motorpool_repairs();
$completedRepairs = get_completed_repairs();
$allRepairs = array_merge($activeRepairs, array_slice($completedRepairs, 0, 120));
$daily = array_reverse(array_slice(count_daily_repairs(), 0, 7));
$mostRepaired = count_completed_repairs_by_car();
$repairTypes = ['Battery Replacement', 'Brake Repair', 'Engine Tune-up', 'Oil Change', 'Suspension Repair', 'Tire Replacement', 'Transmission Service', 'Other'];
$dailyLabels = [];
$dailyValues = [];
foreach ($daily as $dayRow) {
    $dailyLabels[] = master_short_date($dayRow['repair_day'] ?? '');
    $dailyValues[] = (int)($dayRow['repair_count'] ?? 0);
}

master_page_start('motorpool', 'Motorpool', 'Search, update, and manage repair records from the master portal.');
?>
<?php if ($notice !== ''): ?><div class="notice-banner reveal-on-load"><?php echo master_h($notice); ?></div><?php endif; ?>
<section class="kpi-grid reveal-on-load">
    <article class="metric-card"><span class="metric-icon warning"><i class="fas fa-hourglass-half"></i></span><div><span class="metric-label">Pending</span><strong class="count-up" data-count="<?php echo (int)count_pending_repairs(); ?>"><?php echo master_n(count_pending_repairs()); ?></strong><small>Awaiting action</small></div></article>
    <article class="metric-card"><span class="metric-icon info"><i class="fas fa-tools"></i></span><div><span class="metric-label">In Progress</span><strong class="count-up" data-count="<?php echo (int)count_in_progress_repairs(); ?>"><?php echo master_n(count_in_progress_repairs()); ?></strong><small>Ongoing repairs</small></div></article>
    <article class="metric-card"><span class="metric-icon success"><i class="fas fa-check-circle"></i></span><div><span class="metric-label">Completed</span><strong class="count-up" data-count="<?php echo (int)count_repaired_repairs(); ?>"><?php echo master_n(count_repaired_repairs()); ?></strong><small>Repair history</small></div></article>
    <article class="metric-card"><span class="metric-icon primary"><i class="fas fa-car"></i></span><div><span class="metric-label">Vehicles</span><strong class="count-up" data-count="<?php echo count($vehicles); ?>"><?php echo master_n(count($vehicles)); ?></strong><small>Motorpool records</small></div></article>
</section>

<section class="dashboard-card workspace-card reveal-on-load">
    <div class="workspace-toolbar">
        <div class="task-tabs">
            <button type="button" class="active" data-modal-open="addRepairModal">Add Repair</button>
            <button type="button" data-modal-open="addVehicleModal">Add Vehicle</button>
        </div>
        <div class="workspace-search">
            <i class="fas fa-search"></i>
            <input type="search" class="master-table-search" placeholder="Search repairs" aria-label="Search repairs">
        </div>
    </div>

    <div class="split-grid">
        <div class="line-chart-card">
            <div class="chart-title"><span>Repairs per Day</span><strong><?php echo master_n(array_sum($dailyValues)); ?></strong></div>
            <canvas class="master-line-chart" width="640" height="220" data-labels="<?php echo master_h(json_encode($dailyLabels)); ?>" data-values="<?php echo master_h(json_encode($dailyValues)); ?>"></canvas>
        </div>
        <div class="rank-list">
            <h3>Most Repaired Vehicles</h3>
            <?php foreach ($mostRepaired as $plate => $count): ?><div class="rank-item"><span><?php echo master_h($plate); ?></span><strong>Completed</strong><em><?php echo master_n($count); ?> repairs</em></div><?php endforeach; ?>
            <?php if (!$mostRepaired): ?><div class="empty-state">No completed repair data.</div><?php endif; ?>
        </div>
    </div>

    <div class="master-table is-active">
        <div class="master-row master-head motorpool-row"><span>Plate</span><span>Date</span><span>Office</span><span>Repair</span><span>Parts</span><span>Remarks</span><span>Status</span><span>Actions</span></div>
        <?php foreach ($allRepairs as $repair): ?>
            <div class="master-row motorpool-row">
                <span><?php echo master_h($repair['plate_no'] ?? ''); ?></span>
                <span><?php echo master_h(master_short_date($repair['repair_date'] ?? '')); ?></span>
                <strong><?php echo master_h($repair['office'] ?? ''); ?></strong>
                <span><?php echo master_h($repair['repair_type'] ?? ''); ?></span>
                <span><?php echo master_h($repair['parts_replaced'] ?? ''); ?></span>
                <span><?php echo master_h($repair['remarks'] ?? ''); ?></span>
                <select class="inline-select repair-status-select" data-record-id="<?php echo (int)$repair['id']; ?>">
                    <?php foreach (['Pending', 'In Progress', 'Completed', 'Cancelled'] as $status): ?><option value="<?php echo master_h($status); ?>" <?php echo ($repair['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo master_h($status); ?></option><?php endforeach; ?>
                </select>
                <span class="row-actions">
                    <button type="button" class="icon-button edit-repair-btn" data-modal-open="editRepairModal"
                        data-id="<?php echo (int)$repair['id']; ?>"
                        data-plate="<?php echo master_h($repair['plate_no'] ?? ''); ?>"
                        data-date="<?php echo master_h($repair['repair_date'] ?? ''); ?>"
                        data-type="<?php echo master_h($repair['repair_type'] ?? ''); ?>"
                        data-mileage="<?php echo master_h($repair['mileage'] ?? ''); ?>"
                        data-parts="<?php echo master_h($repair['parts_replaced'] ?? ''); ?>"
                        data-cost="<?php echo master_h($repair['cost'] ?? ''); ?>"
                        data-office="<?php echo master_h($repair['office'] ?? ''); ?>"
                        data-notes="<?php echo master_h($repair['remarks'] ?? ''); ?>"
                        data-status="<?php echo master_h($repair['status'] ?? 'Pending'); ?>"><i class="fas fa-edit"></i></button>
                    <button type="button" class="icon-button danger delete-repair-btn" data-record-id="<?php echo (int)$repair['id']; ?>"><i class="fas fa-trash"></i></button>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="master-modal" id="addRepairModal" aria-hidden="true"><div class="master-modal-panel"><button type="button" class="modal-close" data-modal-close>&times;</button><h2>Add Repair</h2><form method="post" class="master-form"><input type="hidden" name="master_action" value="add_repair"><label>Vehicle<select name="vehicle_id" required><option value="">Select vehicle</option><?php foreach ($vehicles as $vehicle): ?><option value="<?php echo master_h($vehicle['plate_no']); ?>"><?php echo master_h($vehicle['plate_no'] . ' - ' . ($vehicle['car_model'] ?? '')); ?></option><?php endforeach; ?></select></label><label>Date<input type="date" name="repair_date" value="<?php echo date('Y-m-d'); ?>" required></label><label>Repair Type<select name="repair_type[]" multiple><?php foreach ($repairTypes as $type): ?><option value="<?php echo master_h($type); ?>"><?php echo master_h($type); ?></option><?php endforeach; ?></select></label><label>Parts Replaced<textarea name="parts_replaced"></textarea></label><label>Mileage<input type="number" name="mileage" min="0"></label><label>Cost<input type="number" name="cost" min="0" step="0.01"></label><label>Office<input type="text" name="office" required></label><label>Service Type<select name="notes"><option value="PMS">PMS</option><option value="REPAIR">REPAIR</option><option value="RESCUE">RESCUE</option><option value="REPAIR & PMS">REPAIR & PMS</option></select></label><button class="primary-button" type="submit">Save Repair</button></form></div></div>

<div class="master-modal" id="addVehicleModal" aria-hidden="true"><div class="master-modal-panel"><button type="button" class="modal-close" data-modal-close>&times;</button><h2>Add Vehicle</h2><form method="post" class="master-form"><input type="hidden" name="master_action" value="add_vehicle"><label>Plate No.<input type="text" name="plate_no" required></label><label>Car Model<input type="text" name="car_model"></label><label>Office<input type="text" name="office"></label><label>Status<select name="status"><option>Active</option><option>Under Repair</option><option>Out of Service</option></select></label><label>No. Dispatch<input type="number" name="no_dispatch" min="0"></label><label>Old Mileage<input type="number" name="old_mileage" min="0"></label><label>Latest Mileage<input type="number" name="latest_mileage" min="0"></label><label>No. Repairs<input type="number" name="no_of_repairs" min="0"></label><label>Date Procured<input type="date" name="date_procured"></label><button class="primary-button" type="submit">Save Vehicle</button></form></div></div>

<div class="master-modal" id="editRepairModal" aria-hidden="true"><div class="master-modal-panel"><button type="button" class="modal-close" data-modal-close>&times;</button><h2>Edit Repair</h2><form class="master-form" id="editRepairForm"><input type="hidden" name="edit_repair_id" id="edit_repair_id"><label>Vehicle<select name="edit_vehicle_id" id="edit_vehicle_id" required><?php foreach ($vehicles as $vehicle): ?><option value="<?php echo master_h($vehicle['plate_no']); ?>"><?php echo master_h($vehicle['plate_no'] . ' - ' . ($vehicle['car_model'] ?? '')); ?></option><?php endforeach; ?></select></label><label>Date<input type="date" name="edit_repair_date" id="edit_repair_date" required></label><label>Repair Type<input type="text" name="edit_repair_type[]" id="edit_repair_type"></label><label>Mileage<input type="number" name="edit_mileage" id="edit_mileage"></label><label>Parts<input type="text" name="edit_parts_replaced" id="edit_parts_replaced"></label><label>Cost<input type="number" name="edit_cost" id="edit_cost" step="0.01"></label><label>Office<input type="text" name="edit_office" id="edit_office"></label><label>Notes<input type="text" name="edit_notes" id="edit_notes"></label><label>Status<select name="edit_status" id="edit_status"><?php foreach (['Pending', 'In Progress', 'Completed', 'Cancelled'] as $status): ?><option value="<?php echo master_h($status); ?>"><?php echo master_h($status); ?></option><?php endforeach; ?></select></label><button class="primary-button" type="submit">Update Repair</button></form></div></div>
<?php master_page_end(); ?>
