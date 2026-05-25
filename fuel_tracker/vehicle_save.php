<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'vehicle_save', 'json');
require_once __DIR__ . '/db.php';

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

$input = vehicleSaveInput();
$action = strtolower(trim((string) ($input['action'] ?? 'create')));

try {
    $id = (int) ($input['id'] ?? 0);
    $vehicleId = strtoupper(trim((string) ($input['vehicle_id'] ?? '')));
    $plateNo = strtoupper(trim((string) ($input['plate_no'] ?? '')));
    $typeOfVehicle = trim((string) ($input['type_of_vehicle'] ?? ''));
    $office = trim((string) ($input['office'] ?? ''));
    $fuelType = strtolower(trim((string) ($input['fuel_type'] ?? 'unleaded')));
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
    if (!in_array($fuelType, ['unleaded', 'diesel'], true)) {
        $fuelType = 'unleaded';
    }

    $cylinders = (int) vehicleSaveNumber($input['number_of_cylinder'] ?? 4, 'Number of cylinders', 1);
    $normalKmPerLiter = vehicleSaveNumber($input['normal_km_per_liter'] ?? 20, 'Normal km/liter');
    $currentOdometer = vehicleSaveNumber($input['current_odometer'] ?? 0, 'Current odometer');
    $fuelCapacity = vehicleSaveNumber($input['fuel_capacity'] ?? 0, 'Fuel capacity');

    if ($action === 'update') {
        $stmt = $conn->prepare("
            UPDATE vehicles
            SET vehicle_id = ?,
                plate_no = ?,
                type_of_vehicle = ?,
                office = ?,
                fuel_type = ?,
                number_of_cylinder = ?,
                normal_km_per_liter = ?,
                fuel_capacity = ?,
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->bind_param(
            'sssssiddsi',
            $vehicleId,
            $plateNo,
            $typeOfVehicle,
            $office,
            $fuelType,
            $cylinders,
            $normalKmPerLiter,
            $fuelCapacity,
            $status,
            $id
        );
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        vehicleSaveJson([
            'success' => true,
            'message' => 'Vehicle updated.',
            'id' => $id,
            'updated' => $affected,
            'vehicle_id' => $vehicleId,
            'plate_no' => $plateNo,
            'office' => $office,
            'fuel_type' => $fuelType,
        ]);
    }

    if ($action !== 'create') {
        vehicleSaveJson(['success' => false, 'message' => 'Unsupported action.'], 400);
    }

    $stmt = $conn->prepare("
        INSERT INTO vehicles
            (vehicle_id, plate_no, type_of_vehicle, office, fuel_type, number_of_cylinder, normal_km_per_liter, current_odometer, fuel_capacity, status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sssssiddds',
        $vehicleId,
        $plateNo,
        $typeOfVehicle,
        $office,
        $fuelType,
        $cylinders,
        $normalKmPerLiter,
        $currentOdometer,
        $fuelCapacity,
        $status
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    vehicleSaveJson([
        'success' => true,
        'message' => 'Vehicle added.',
        'id' => $newId,
        'vehicle_id' => $vehicleId,
        'plate_no' => $plateNo,
        'office' => $office,
        'fuel_type' => $fuelType,
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
