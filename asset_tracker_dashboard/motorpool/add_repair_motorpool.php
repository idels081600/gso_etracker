<?php

require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';
require_once dirname(__DIR__) . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $plateNo = input_string($_POST, 'vehicle_id', 30);
    $repairDate = input_date($_POST, 'repair_date');
    $allowedRepairTypes = [
        'AC Repair',
        'Battery Replacement',
        'Body Repair',
        'Brake Repair',
        'Engine Repair',
        'Engine Tune-up',
        'Oil Change',
        'Suspension Repair',
        'Tire Replacement',
        'Transmission Service',
        'Other',
    ];
    $submittedRepairTypes = $_POST['repair_type'] ?? [];
    if (!is_array($submittedRepairTypes)) {
        throw new InvalidArgumentException('Invalid repair type selection.');
    }
    $repairTypes = [];
    foreach ($submittedRepairTypes as $repairType) {
        if (!is_string($repairType)) {
            throw new InvalidArgumentException('Invalid repair type selection.');
        }
        $repairType = trim($repairType);
        if (!in_array($repairType, $allowedRepairTypes, true)) {
            throw new InvalidArgumentException('Invalid repair type selection.');
        }
        $repairTypes[$repairType] = $repairType;
    }
    $repairTypes = array_values($repairTypes);
    if ($repairTypes === []) {
        throw new InvalidArgumentException('At least one repair type is required.');
    }
    $mileage = max(0, (int) ($_POST['mileage'] ?? 0));
    $parts = input_string($_POST, 'parts_replaced', 2000, false);
    $cost = max(0, (float) ($_POST['cost'] ?? 0));
    $notes = input_string($_POST, 'notes', 2000, false);
    $office = input_string($_POST, 'office', 120, false);

    mysqli_begin_transaction($conn);
    $vehicle = db_fetch_one($conn, 'SELECT car_model FROM vehicle_records WHERE plate_no = ? FOR UPDATE', 's', [$plateNo]);
    if (!$vehicle) {
        throw new InvalidArgumentException('Selected vehicle was not found.');
    }

    $stmt = db_execute(
        $conn,
        'INSERT INTO motorpool_repair (plate_no, car_model, repair_date, repair_type, mileage, parts_replaced, cost, remarks, status, office) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'ssssisdsss',
        [$plateNo, $vehicle['car_model'], $repairDate, implode(', ', $repairTypes), $mileage, $parts, $cost, $notes, 'Pending', $office]
    );
    mysqli_stmt_close($stmt);

    $stmt = db_execute($conn, 'UPDATE vehicle_records SET no_of_repairs = no_of_repairs + 1, new_repair_date = ?, latest_mileage = ? WHERE plate_no = ?', 'sis', [$repairDate, $mileage, $plateNo]);
    mysqli_stmt_close($stmt);
    mysqli_commit($conn);

    api_response(true, 'Repair record added successfully.');
} catch (InvalidArgumentException $error) {
    mysqli_rollback($conn);
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    api_database_error($error);
}
