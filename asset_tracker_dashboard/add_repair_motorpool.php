<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $plateNo = input_string($_POST, 'vehicle_id', 30);
    $repairDate = input_date($_POST, 'repair_date');
    $repairTypes = array_values(array_filter(array_map('trim', (array) ($_POST['repair_type'] ?? []))));
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
    header('Location: motorpool_admin.php?success=repair_added');
    exit;
} catch (InvalidArgumentException $error) {
    mysqli_rollback($conn);
    header('Location: motorpool_admin.php?error=' . rawurlencode($error->getMessage()));
    exit;
} catch (Throwable $error) {
    mysqli_rollback($conn);
    error_log($error->getMessage());
    header('Location: motorpool_admin.php?error=repair_add_failed');
    exit;
}
