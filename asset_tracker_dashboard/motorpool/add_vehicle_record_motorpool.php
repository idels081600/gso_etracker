<?php

require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';
require_once dirname(__DIR__) . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
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
        'INSERT INTO vehicle_records (plate_no, car_model, office, status, date_procured, old_mileage, latest_mileage, no_dispatch, no_of_repairs, new_repair_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssiiiis',
        [$plateNo, $carModel, $office, $status, $dateProcured, $oldMileage, $latestMileage, $noDispatch, $noRepairs, $latestRepairDate]
    );
    mysqli_stmt_close($stmt);

    api_response(true, 'Vehicle added successfully.');
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
