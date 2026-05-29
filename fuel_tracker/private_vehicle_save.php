<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'private_vehicle_save', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

header('Content-Type: application/json');

function privateVehicleJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function privateVehicleInput(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return $_POST;
}

function privateVehicleNumber(mixed $value, float $default = 0, float $minimum = 0): float
{
    if ($value === '' || $value === null) {
        return $default;
    }
    if (!is_numeric($value)) {
        privateVehicleJson(['success' => false, 'message' => 'Numeric fields must use valid numbers.'], 422);
    }

    $number = (float) $value;
    if ($number < $minimum) {
        privateVehicleJson(['success' => false, 'message' => 'Numeric fields must be ' . $minimum . ' or greater.'], 422);
    }

    return $number;
}

$input = privateVehicleInput();
$action = strtolower(trim((string) ($input['action'] ?? 'create')));

try {
    if ($action !== 'create') {
        privateVehicleJson(['success' => false, 'message' => 'Unsupported action.'], 400);
    }

    fuelTrackerEnsureVehiclePastOdometer($conn);
    fuelTrackerEnsureVehicleSchedules($conn);
    fuelTrackerEnsureVehicleBalanceTank($conn);
    fuelTrackerEnsureVehicleFixedLiters($conn);
    fuelTrackerEnsureVehicleDriverName($conn);
    fuelTrackerEnsureScopeColumns($conn);

    $plateNo = strtoupper(trim((string) ($input['plate_no'] ?? '')));
    $typeOfVehicle = trim((string) ($input['type_of_vehicle'] ?? ''));
    $driverName = trim((string) ($input['driver_name'] ?? ''));
    $fuelTypeRaw = strtolower(trim((string) ($input['fuel_type'] ?? '')));
    $fuelType = str_contains($fuelTypeRaw, 'diesel') ? 'diesel' : (str_contains($fuelTypeRaw, 'unleaded') || str_contains($fuelTypeRaw, 'gas') ? 'unleaded' : '');

    if ($plateNo === '' && $typeOfVehicle === '') {
        privateVehicleJson(['success' => false, 'message' => 'Enter a plate number or vehicle name.'], 422);
    }
    if ($fuelType === '') {
        privateVehicleJson(['success' => false, 'message' => 'Fuel type is required.'], 422);
    }

    $vehicleId = strtoupper(trim((string) ($input['vehicle_id'] ?? '')));
    if ($vehicleId === '') {
        $vehicleId = 'PV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
    if ($plateNo === '') {
        $plateNo = $vehicleId;
    }
    if ($typeOfVehicle === '') {
        $typeOfVehicle = 'Private Vehicle';
    }

    $office = trim((string) ($input['office'] ?? '')) ?: 'PRIVATE';
    $schedules = fuelTrackerNormalizeSchedule($input['schedules'] ?? '');
    $status = strtolower(trim((string) ($input['status'] ?? 'active')));
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    $cylinders = (int) privateVehicleNumber($input['number_of_cylinder'] ?? '', 4, 0);
    $normalKmPerLiter = privateVehicleNumber($input['normal_km_per_liter'] ?? '', 0);
    $currentOdometer = privateVehicleNumber($input['current_odometer'] ?? '', 0);
    $pastOdometer = privateVehicleNumber($input['past_odometer'] ?? '', 0);
    $fuelCapacity = privateVehicleNumber($input['fuel_capacity'] ?? '', 0);
    $fixedLiters = privateVehicleNumber($input['fixed_liters'] ?? '', 0);
    $balanceTank = privateVehicleNumber($input['balance_tank'] ?? '', 0);

    $stmt = $conn->prepare("
        INSERT INTO vehicles
            (vehicle_id, plate_no, type_of_vehicle, driver_name, number_of_cylinder, normal_km_per_liter, current_odometer, past_odometer, fuel_capacity, status, office, balance_tank, fuel_type, schedules, fixed_liters, vehicle_scope)
        VALUES
            (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'private')
    ");
    $stmt->bind_param(
        'ssssiddddssdssd',
        $vehicleId,
        $plateNo,
        $typeOfVehicle,
        $driverName,
        $cylinders,
        $normalKmPerLiter,
        $currentOdometer,
        $pastOdometer,
        $fuelCapacity,
        $status,
        $office,
        $balanceTank,
        $fuelType,
        $schedules,
        $fixedLiters
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    privateVehicleJson([
        'success' => true,
        'message' => 'Private vehicle added.',
        'id' => $newId,
        'vehicle_id' => $vehicleId,
        'plate_no' => $plateNo,
        'type_of_vehicle' => $typeOfVehicle,
        'driver_name' => $driverName,
        'office' => $office,
        'fuel_type' => $fuelType,
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('Private vehicle save error: ' . $e->getMessage());
    if ((int) $e->getCode() === 1062) {
        privateVehicleJson(['success' => false, 'message' => 'A vehicle with this plate number or vehicle ID already exists.'], 409);
    }

    privateVehicleJson(['success' => false, 'message' => 'Unable to save private vehicle.'], 500);
} catch (Throwable $e) {
    error_log('Private vehicle save unexpected error: ' . $e->getMessage());
    privateVehicleJson(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
