<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'vehicle_save', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

header('Content-Type: application/json');

function vehicleSaveJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function vehicleSaveInput(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return $_POST;
}

function vehicleSaveNumber(mixed $value, string $label, float $minimum = 0): float
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        vehicleSaveJson(['success' => false, 'message' => $label . ' must be a valid number.'], 422);
    }

    $number = (float) $value;
    if ($number < $minimum) {
        vehicleSaveJson(['success' => false, 'message' => $label . ' must be ' . $minimum . ' or greater.'], 422);
    }

    return $number;
}

function vehicleSaveEnsureSchedulesColumn(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'schedules'");
    if ($result && $result->num_rows > 0) {
        $ensured = true;
        return;
    }

    if (!$conn->query("ALTER TABLE vehicles ADD COLUMN schedules VARCHAR(120) NULL AFTER fuel_type")) {
        throw new RuntimeException('Unable to add vehicle schedule field: ' . $conn->error);
    }

    $ensured = true;
}

$input = vehicleSaveInput();
$action = strtolower(trim((string) ($input['action'] ?? 'create')));

try {
    vehicleSaveEnsureSchedulesColumn($conn);
    fuelTrackerEnsureVehicleFixedLiters($conn);
    fuelTrackerEnsureVehicleDriverName($conn);
    fuelTrackerEnsureScopeColumns($conn);

    $id = (int) ($input['id'] ?? 0);
    $vehicleId = strtoupper(trim((string) ($input['vehicle_id'] ?? '')));
    $plateNo = strtoupper(trim((string) ($input['plate_no'] ?? '')));
    $typeOfVehicle = trim((string) ($input['type_of_vehicle'] ?? ''));
    $driverName = trim((string) ($input['driver_name'] ?? ''));
    $office = trim((string) ($input['office'] ?? ''));
    $fuelTypeRaw = strtolower(trim((string) ($input['fuel_type'] ?? 'unleaded')));
    $fuelType = str_contains($fuelTypeRaw, 'diesel') ? 'diesel' : 'unleaded';
    $schedules = fuelTrackerNormalizeSchedule($input['schedules'] ?? '');
    $status = strtolower(trim((string) ($input['status'] ?? 'active')));

    if ($action === 'update' && $id <= 0) {
        vehicleSaveJson(['success' => false, 'message' => 'Vehicle ID is required.'], 422);
    }
    if ($vehicleId === '') {
        $vehicleId = 'VH-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
    if ($plateNo === '') {
        vehicleSaveJson(['success' => false, 'message' => 'Plate number is required.'], 422);
    }
    if ($typeOfVehicle === '') {
        vehicleSaveJson(['success' => false, 'message' => 'Vehicle type is required.'], 422);
    }
    if ($office === '') {
        vehicleSaveJson(['success' => false, 'message' => 'Office is required.'], 422);
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }
    $cylinders = (int) vehicleSaveNumber($input['number_of_cylinder'] ?? 4, 'Number of cylinders', 1);
    $normalKmPerLiter = vehicleSaveNumber($input['normal_km_per_liter'] ?? 20, 'Normal km/liter');
    $currentOdometer = vehicleSaveNumber($input['current_odometer'] ?? 0, 'Current odometer');
    $fuelCapacity = vehicleSaveNumber($input['fuel_capacity'] ?? 0, 'Fuel capacity');
    $fixedLiters = vehicleSaveNumber($input['fixed_liters'] ?? 0, 'Fixed liters');

    if ($action === 'update') {
        $stmt = $conn->prepare("
            UPDATE vehicles
            SET vehicle_id = ?,
                plate_no = ?,
                type_of_vehicle = ?,
                driver_name = NULLIF(?, ''),
                office = ?,
                fuel_type = ?,
                schedules = ?,
                number_of_cylinder = ?,
                normal_km_per_liter = ?,
                fuel_capacity = ?,
                fixed_liters = ?,
                status = ?,
                vehicle_scope = 'government',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) <> 'private'
        ");
        $stmt->bind_param(
            'sssssssidddsi',
            $vehicleId,
            $plateNo,
            $typeOfVehicle,
            $driverName,
            $office,
            $fuelType,
            $schedules,
            $cylinders,
            $normalKmPerLiter,
            $fuelCapacity,
            $fixedLiters,
            $status,
            $id
        );
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        fuelTrackerClearGasIssuanceCache();
        vehicleSaveJson([
            'success' => true,
            'message' => 'Vehicle updated.',
            'id' => $id,
            'updated' => $affected,
            'vehicle_id' => $vehicleId,
            'plate_no' => $plateNo,
            'driver_name' => $driverName,
            'office' => $office,
            'fuel_type' => $fuelType,
            'schedules' => $schedules,
            'fixed_liters' => $fixedLiters,
        ]);
    }

    if ($action !== 'create') {
        vehicleSaveJson(['success' => false, 'message' => 'Unsupported action.'], 400);
    }

    $stmt = $conn->prepare("
        INSERT INTO vehicles
            (vehicle_id, plate_no, type_of_vehicle, driver_name, office, fuel_type, schedules, number_of_cylinder, normal_km_per_liter, current_odometer, fuel_capacity, fixed_liters, status, vehicle_scope)
        VALUES
            (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'government')
    ");
    $stmt->bind_param(
        'sssssssidddds',
        $vehicleId,
        $plateNo,
        $typeOfVehicle,
        $driverName,
        $office,
        $fuelType,
        $schedules,
        $cylinders,
        $normalKmPerLiter,
        $currentOdometer,
        $fuelCapacity,
        $fixedLiters,
        $status
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    fuelTrackerClearGasIssuanceCache();
    vehicleSaveJson([
        'success' => true,
        'message' => 'Vehicle added.',
        'id' => $newId,
        'vehicle_id' => $vehicleId,
        'plate_no' => $plateNo,
        'driver_name' => $driverName,
        'office' => $office,
        'fuel_type' => $fuelType,
        'schedules' => $schedules,
        'fixed_liters' => $fixedLiters,
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('Vehicle save error: ' . $e->getMessage());
    if ((int) $e->getCode() === 1062) {
        vehicleSaveJson(['success' => false, 'message' => 'A vehicle with this plate number or vehicle ID already exists.'], 409);
    }

    vehicleSaveJson(['success' => false, 'message' => 'Unable to save vehicle.'], 500);
} catch (Throwable $e) {
    error_log('Vehicle save unexpected error: ' . $e->getMessage());
    vehicleSaveJson(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
