<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'private_gas_issuance_save', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

header('Content-Type: application/json');

function privateIssuanceJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function privateIssuanceInput(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return $_POST;
}

function privateIssuanceDate(string $value, string $label, bool $required = true): string
{
    $value = trim($value);
    if ($value === '' && !$required) {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        privateIssuanceJson(['success' => false, 'message' => $label . ' must use YYYY-MM-DD format.'], 422);
    }

    return $value;
}

function privateIssuanceVehicle(mysqli $conn, int $vehicleId): array
{
    if ($vehicleId <= 0) {
        privateIssuanceJson(['success' => false, 'message' => 'Private vehicle is required.'], 422);
    }

    $stmt = $conn->prepare("
        SELECT id, office, fuel_type
        FROM vehicles
        WHERE id = ?
            AND LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) = 'private'
        LIMIT 1
    ");
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        privateIssuanceJson(['success' => false, 'message' => 'Selected private vehicle was not found.'], 422);
    }

    return $row;
}

$input = privateIssuanceInput();
$action = strtolower(trim((string) ($input['action'] ?? 'create')));

try {
    fuelTrackerEnsureScopeColumns($conn);

    if ($action === 'approval') {
        $id = (int) ($input['id'] ?? 0);
        $approved = filter_var($input['approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($id <= 0) {
            privateIssuanceJson(['success' => false, 'message' => 'Private gas issuance ID is required.'], 422);
        }

        $stmt = $conn->prepare("
            SELECT status
            FROM gas_issuances
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(issuance_scope, 'government'))) = 'private'
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            privateIssuanceJson(['success' => false, 'message' => 'Private gas issuance record was not found.'], 404);
        }

        $currentStatus = strtolower((string) ($row['status'] ?? ''));
        if ($currentStatus === 'used') {
            privateIssuanceJson(['success' => false, 'message' => 'Used private issuances can no longer be changed.'], 409);
        }
        if (in_array($currentStatus, ['expired', 'revoked'], true) && $approved) {
            privateIssuanceJson(['success' => false, 'message' => 'Expired or revoked private issuances cannot be approved.'], 409);
        }

        $newStatus = $approved ? 'approved' : 'draft';
        $stmt = $conn->prepare("
            UPDATE gas_issuances
            SET status = ?,
                approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(issuance_scope, 'government'))) = 'private'
        ");
        $stmt->bind_param('ssi', $newStatus, $newStatus, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        privateIssuanceJson([
            'success' => true,
            'message' => $approved ? 'Private gas issuance approved.' : 'Private gas issuance moved back to draft.',
            'status' => $newStatus,
            'updated' => $affected,
        ]);
    }

    if ($action !== 'create') {
        privateIssuanceJson(['success' => false, 'message' => 'Unsupported action.'], 400);
    }

    $vehicleId = (int) ($input['vehicle_id'] ?? 0);
    $vehicle = privateIssuanceVehicle($conn, $vehicleId);
    $issueDate = privateIssuanceDate((string) ($input['issue_date'] ?? ''), 'Issue date');
    $expiryDate = privateIssuanceDate((string) ($input['expiry_date'] ?? ''), 'Expiry date', false);
    if ($expiryDate === '') {
        $expiryDate = (new DateTimeImmutable($issueDate))->modify('+7 days')->format('Y-m-d');
    }
    if ($expiryDate < $issueDate) {
        privateIssuanceJson(['success' => false, 'message' => 'Expiry date cannot be earlier than the issue date.'], 422);
    }

    $authorizedLiters = $input['authorized_liters'] ?? null;
    if ($authorizedLiters === null || $authorizedLiters === '' || !is_numeric($authorizedLiters) || (float) $authorizedLiters <= 0) {
        privateIssuanceJson(['success' => false, 'message' => 'Authorized liters must be greater than zero.'], 422);
    }
    $authorizedLiters = (float) $authorizedLiters;

    $serialNo = trim((string) ($input['serial_no'] ?? ''));
    if ($serialNo === '') {
        $serialNo = 'PFI-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    $driverName = trim((string) ($input['driver_name'] ?? ''));
    $office = trim((string) ($input['office'] ?? ''));
    if ($office === '') {
        $office = trim((string) ($vehicle['office'] ?? '')) ?: 'PRIVATE';
    }

    $purpose = trim((string) ($input['purpose'] ?? '')) ?: 'PRIVATE VEHICLE FUEL ISSUANCE';
    $fuelType = trim((string) ($input['fuel_type'] ?? ''));
    if ($fuelType === '') {
        $fuelType = trim((string) ($vehicle['fuel_type'] ?? 'unleaded')) ?: 'unleaded';
    }
    $fuelType = str_contains(strtolower($fuelType), 'diesel') ? 'diesel' : 'unleaded';
    $unit = trim((string) ($input['unit'] ?? 'Liters')) ?: 'Liters';
    $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
    if (!in_array($status, ['draft', 'approved', 'valid'], true)) {
        $status = 'draft';
    }

    $stmt = $conn->prepare("
        INSERT INTO gas_issuances
            (serial_no, vehicle_id, driver_name, office, purpose, fuel_type, authorized_liters, unit, issue_date, expiry_date, status, issuance_scope, approved_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'private', IF(? IN ('approved', 'valid'), NOW(), NULL))
    ");
    $stmt->bind_param('sissssdsssss', $serialNo, $vehicleId, $driverName, $office, $purpose, $fuelType, $authorizedLiters, $unit, $issueDate, $expiryDate, $status, $status);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    privateIssuanceJson([
        'success' => true,
        'message' => 'Private gas issuance saved.',
        'id' => $newId,
        'serial_no' => $serialNo,
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('Private gas issuance save error: ' . $e->getMessage());
    if ((int) $e->getCode() === 1062) {
        privateIssuanceJson(['success' => false, 'message' => 'This serial number already exists.'], 409);
    }

    privateIssuanceJson(['success' => false, 'message' => 'Unable to save private gas issuance.'], 500);
} catch (Throwable $e) {
    error_log('Private gas issuance unexpected error: ' . $e->getMessage());
    privateIssuanceJson(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
