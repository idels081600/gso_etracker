<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $id = input_int($_POST, 'original_plate_no');
    $plateNo = input_string($_POST, 'plate_no', 30);
    $carModel = input_string($_POST, 'car_model', 120);
    $office = input_string($_POST, 'office', 120, false);
    $status = input_enum($_POST, 'status', ['Active', 'Under Repair', 'Out of Service']);
    $dateProcured = input_date($_POST, 'date_procured', false);
    $latestRepairDate = input_date($_POST, 'latest_repair_date', false);
    $noDispatch = max(0, (int) ($_POST['no_dispatch'] ?? 0));
    $oldMileage = max(0, (int) ($_POST['old_mileage'] ?? 0));
    $latestMileage = max(0, (int) ($_POST['latest_mileage'] ?? 0));
    $noRepairs = max(0, (int) ($_POST['no_of_repairs'] ?? 0));

    $stmt = db_execute(
        $conn,
        'UPDATE vehicle_records SET plate_no = ?, car_model = ?, office = ?, no_dispatch = ?, old_mileage = ?, latest_mileage = ?, no_of_repairs = ?, new_repair_date = ?, status = ?, date_procured = ? WHERE id = ?',
        'sssiiiisssi',
        [$plateNo, $carModel, $office, $noDispatch, $oldMileage, $latestMileage, $noRepairs, $latestRepairDate, $status, $dateProcured, $id]
    );
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    api_response(true, $affected > 0 ? 'Vehicle updated successfully.' : 'No vehicle changes were needed.', ['affected_rows' => $affected]);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
