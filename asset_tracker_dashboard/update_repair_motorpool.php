<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $repairId = input_int($_POST, 'edit_repair_id');
    $plateNo = input_string($_POST, 'edit_vehicle_id', 30);
    $repairDate = input_date($_POST, 'edit_repair_date');
    $repairTypes = array_values(array_filter(array_map('trim', (array) ($_POST['edit_repair_type'] ?? []))));
    if ($repairTypes === []) {
        throw new InvalidArgumentException('At least one repair type is required.');
    }
    $mileage = max(0, (int) ($_POST['edit_mileage'] ?? 0));
    $parts = input_string($_POST, 'edit_parts_replaced', 2000, false);
    $cost = max(0, (float) ($_POST['edit_cost'] ?? 0));
    $office = input_string($_POST, 'edit_office', 120, false);
    $remarks = input_string($_POST, 'edit_notes', 2000, false);
    $status = input_enum($_POST, 'edit_status', ['Pending', 'In Progress', 'Completed', 'Cancelled']);
    $vehicle = db_fetch_one($conn, 'SELECT car_model FROM vehicle_records WHERE plate_no = ?', 's', [$plateNo]);
    if (!$vehicle) {
        throw new InvalidArgumentException('Selected vehicle was not found.');
    }

    $stmt = db_execute(
        $conn,
        'UPDATE motorpool_repair SET plate_no = ?, car_model = ?, repair_date = ?, repair_type = ?, mileage = ?, parts_replaced = ?, cost = ?, office = ?, remarks = ?, status = ? WHERE id = ?',
        'ssssisdsssi',
        [$plateNo, $vehicle['car_model'], $repairDate, implode(', ', $repairTypes), $mileage, $parts, $cost, $office, $remarks, $status, $repairId]
    );
    mysqli_stmt_close($stmt);
    api_response(true, 'Repair record updated successfully.');
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
