<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $plateNo = input_string($_POST, 'plate_no', 30);
    $carModel = input_string($_POST, 'car_model', 120);
    $office = input_string($_POST, 'office', 120, false);
    $status = input_enum($_POST, 'status', ['Active', 'Under Repair', 'Out of Service']);
    $dateProcured = input_date($_POST, 'date_procured', false);
    $oldMileage = max(0, (int) ($_POST['old_mileage'] ?? 0));
    $latestMileage = max(0, (int) ($_POST['latest_mileage'] ?? 0));

    $stmt = db_execute(
        $conn,
        'INSERT INTO vehicle_records (plate_no, car_model, office, status, date_procured, old_mileage, latest_mileage, no_dispatch, no_of_repairs) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)',
        'sssssii',
        [$plateNo, $carModel, $office, $status, $dateProcured, $oldMileage, $latestMileage]
    );
    mysqli_stmt_close($stmt);
    header('Location: motorpool_admin.php?success=vehicle_added');
    exit;
} catch (InvalidArgumentException $error) {
    header('Location: motorpool_admin.php?error=' . rawurlencode($error->getMessage()));
    exit;
} catch (Throwable $error) {
    error_log($error->getMessage());
    header('Location: motorpool_admin.php?error=vehicle_add_failed');
    exit;
}
