<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();

$root = dirname(__DIR__);
set_include_path($root . PATH_SEPARATOR . get_include_path());
require_once $root . '/db_asset.php';
require_once $root . '/display_data_asset.php';

$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['master_action'] ?? '';

    if ($action === 'dispatch') {
        $plateNo = mysqli_real_escape_string($conn, $_POST['plate_no'] ?? '');
        $vehicle = mysqli_real_escape_string($conn, $_POST['vehicle'] ?? '');
        $driver = mysqli_real_escape_string($conn, $_POST['driver'] ?? '');
        $date = mysqli_real_escape_string($conn, $_POST['date'] ?? date('Y-m-d'));
        $purpose = mysqli_real_escape_string($conn, $_POST['purpose'] ?? '');
        $requestor = mysqli_real_escape_string($conn, $_POST['requestor'] ?? '');
        $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');

        if ($requestor !== '') {
            mysqli_query($conn, "INSERT INTO requestingParty (requestor) VALUES ('$requestor')");
        }

        $query = "INSERT INTO Transportation(Plate_no, Date, Requestor, Vehicle, Driver, Purpose, Location, Status, Status1)
                  VALUES ('$plateNo', '$date', '$requestor', '$vehicle', '$driver', '$purpose', '$location', 'Stand By', 'Stand By')";
        $notice = mysqli_query($conn, $query) ? 'Dispatch record added.' : 'Unable to add dispatch record.';
    }

    if ($action === 'vehicle_driver') {
        $plateNo = mysqli_real_escape_string($conn, $_POST['plate_no'] ?? '');
        $vehicle = mysqli_real_escape_string($conn, $_POST['vehicle'] ?? '');
        $driver = mysqli_real_escape_string($conn, $_POST['driver'] ?? '');

        if ($plateNo !== '' && $vehicle !== '') {
            mysqli_query($conn, "INSERT INTO Vehicle(Plate_no, Name, Status) VALUES ('$plateNo', '$vehicle', 'Stand By')");
        }

        if ($driver !== '') {
            mysqli_query($conn, "INSERT INTO Drivers(Name) VALUES ('$driver')");
        }

        $notice = 'Vehicle/driver details saved.';
    }
}

$rows = master_rows_from_result(display_data_transpo(), 400);
$plates = display_data_vehicle() ?: [];
$drivers = master_rows_from_result(display_data_driver(), 400);
$weekly = get_daily_dispatch_counts();
$topVehicles = get_top_5_vehicle_counts();

master_page_start('transportation', 'Transportation', 'Dispatch, search, and monitor vehicle movement inside the master portal.');
?>
<?php if ($notice !== ''): ?><div class="notice-banner reveal-on-load"><?php echo master_h($notice); ?></div><?php endif; ?>
<section class="kpi-grid reveal-on-load">
    <article class="metric-card"><span class="metric-icon info"><i class="fas fa-warehouse"></i></span><div><span class="metric-label">Available</span><strong class="count-up" data-count="<?php echo (int)display_vehicle_ongarage(); ?>"><?php echo master_n(display_vehicle_ongarage()); ?></strong><small>Vehicles in garage</small></div></article>
    <article class="metric-card"><span class="metric-icon warning"><i class="fas fa-route"></i></span><div><span class="metric-label">On Field</span><strong class="count-up" data-count="<?php echo (int)display_vehicle_departed(); ?>"><?php echo master_n(display_vehicle_departed()); ?></strong><small>Departed vehicles</small></div></article>
    <article class="metric-card"><span class="metric-icon success"><i class="fas fa-calendar-day"></i></span><div><span class="metric-label">Week Dispatch</span><strong class="count-up" data-count="<?php echo (int)array_sum(array_map('intval', array_values($weekly ?: []))); ?>"><?php echo master_n(array_sum(array_map('intval', array_values($weekly ?: [])))); ?></strong><small>Monday to Friday</small></div></article>
    <article class="metric-card"><span class="metric-icon primary"><i class="fas fa-list"></i></span><div><span class="metric-label">Loaded Records</span><strong class="count-up" data-count="<?php echo count($rows); ?>"><?php echo master_n(count($rows)); ?></strong><small>Searchable rows</small></div></article>
</section>

<section class="dashboard-card workspace-card reveal-on-load">
    <div class="workspace-toolbar">
        <div class="task-tabs">
            <button type="button" class="active" data-modal-open="dispatchForm">Dispatch</button>
            <button type="button" data-modal-open="vehicleDriverForm">Add Vehicle/Driver</button>
        </div>
        <div class="workspace-search">
            <i class="fas fa-search"></i>
            <input type="search" class="master-table-search" placeholder="Search transportation records" aria-label="Search transportation records">
            <input type="date" class="master-start-date" aria-label="Start date">
            <input type="date" class="master-end-date" aria-label="End date">
        </div>
    </div>

    <div class="split-grid">
        <div class="line-chart-card">
            <div class="chart-title"><span>Dispatch per Day</span><strong><?php echo master_n(array_sum(array_map('intval', array_values($weekly ?: [])))); ?></strong></div>
            <canvas class="master-line-chart" width="640" height="220" data-labels="<?php echo master_h(json_encode(array_keys($weekly))); ?>" data-values="<?php echo master_h(json_encode(array_map('intval', array_values($weekly)))); ?>"></canvas>
        </div>
        <div class="rank-list">
            <h3>Top Used Vehicles</h3>
            <?php foreach ($topVehicles as $vehicle): ?>
                <div class="rank-item"><span><?php echo master_h($vehicle['plate_no'] ?? ''); ?></span><strong><?php echo master_h($vehicle['vehicle'] ?? 'Vehicle'); ?></strong><em><?php echo master_n($vehicle['count'] ?? 0); ?> trips</em></div>
            <?php endforeach; ?>
            <?php if (!$topVehicles): ?><div class="empty-state">No vehicle usage data.</div><?php endif; ?>
        </div>
    </div>

    <div class="master-table is-active" data-date-column="1">
        <div class="master-row master-head transport-row"><span>Plate</span><span>Date</span><span>Vehicle</span><span>Driver</span><span>Purpose</span><span>Location</span><span>Requestor</span><span>Status</span></div>
        <?php foreach ($rows as $row): ?>
            <div class="master-row transport-row">
                <span><?php echo master_h($row['Plate_no'] ?? ''); ?></span>
                <span><?php echo master_h($row['Date'] ?? ''); ?></span>
                <strong><?php echo master_h($row['Vehicle'] ?? ''); ?></strong>
                <span><?php echo master_h($row['Driver'] ?? ''); ?></span>
                <span><?php echo master_h($row['Purpose'] ?? ''); ?></span>
                <span><?php echo master_h($row['Location'] ?? ''); ?></span>
                <span><?php echo master_h($row['Requestor'] ?? ''); ?></span>
                <select class="inline-select transport-status-select" data-record-id="<?php echo (int)($row['id'] ?? 0); ?>">
                    <?php foreach (['Stand By', 'Departed', 'Arrived'] as $status): ?>
                        <option value="<?php echo master_h($status); ?>" <?php echo (($row['Status1'] ?? $row['Status'] ?? '') === $status) ? 'selected' : ''; ?>><?php echo master_h($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="master-modal" id="dispatchForm" aria-hidden="true">
    <div class="master-modal-panel">
        <button type="button" class="modal-close" data-modal-close>&times;</button>
        <h2>Dispatch Vehicle</h2>
        <form method="post" class="master-form">
            <input type="hidden" name="master_action" value="dispatch">
            <label>Plate No.<select name="plate_no" required><option value="">Select plate</option><?php foreach ($plates as $plate): ?><option value="<?php echo master_h($plate['Plate_No'] ?? $plate['Plate_no'] ?? ''); ?>"><?php echo master_h($plate['Plate_No'] ?? $plate['Plate_no'] ?? ''); ?></option><?php endforeach; ?></select></label>
            <label>Date<input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></label>
            <label>Vehicle<input type="text" name="vehicle" required placeholder="Vehicle type"></label>
            <label>Driver<select name="driver" required><option value="">Select driver</option><?php foreach ($drivers as $driver): ?><option value="<?php echo master_h($driver['Name'] ?? ''); ?>"><?php echo master_h($driver['Name'] ?? ''); ?></option><?php endforeach; ?></select></label>
            <label>Purpose<input type="text" name="purpose" required></label>
            <label>Location<input type="text" name="location" required></label>
            <label>Requestor<input type="text" name="requestor" required></label>
            <button class="primary-button" type="submit">Save Dispatch</button>
        </form>
    </div>
</div>

<div class="master-modal" id="vehicleDriverForm" aria-hidden="true">
    <div class="master-modal-panel">
        <button type="button" class="modal-close" data-modal-close>&times;</button>
        <h2>Add Vehicle / Driver</h2>
        <form method="post" class="master-form">
            <input type="hidden" name="master_action" value="vehicle_driver">
            <label>Plate No.<input type="text" name="plate_no"></label>
            <label>Vehicle Type<input type="text" name="vehicle"></label>
            <label>Driver Name<input type="text" name="driver"></label>
            <button class="primary-button" type="submit">Save Details</button>
        </form>
    </div>
</div>
<?php master_page_end(); ?>
