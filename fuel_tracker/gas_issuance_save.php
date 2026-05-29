<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'gas_issuance_save', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

header('Content-Type: application/json');

function gasIssuanceJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function gasIssuanceInput(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return $_POST;
}

function gasIssuanceRequireDate(string $value, string $label): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        gasIssuanceJson(['success' => false, 'message' => $label . ' must use YYYY-MM-DD format.'], 422);
    }

    return $value;
}

function gasIssuanceVehicleId(mysqli $conn, array $data): int
{
    fuelTrackerEnsureScopeColumns($conn);

    if (!empty($data['vehicle_id']) && ctype_digit((string) $data['vehicle_id'])) {
        $vehicleId = (int) $data['vehicle_id'];
        $stmt = $conn->prepare("
            SELECT id
            FROM vehicles
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) <> 'private'
            LIMIT 1
        ");
        $stmt->bind_param('i', $vehicleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            return $vehicleId;
        }

        gasIssuanceJson(['success' => false, 'message' => 'Selected vehicle is not available for government gas issuance.'], 422);
    }

    $plate = trim((string) ($data['plate_no'] ?? ''));
    if ($plate === '') {
        gasIssuanceJson(['success' => false, 'message' => 'Vehicle is required.'], 422);
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM vehicles
        WHERE plate_no = ?
            AND LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) <> 'private'
        LIMIT 1
    ");
    $stmt->bind_param('s', $plate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        gasIssuanceJson(['success' => false, 'message' => 'No vehicle found for plate number ' . $plate . '.'], 422);
    }

    return (int) $row['id'];
}

function gasIssuanceVehicleOffice(mysqli $conn, int $vehicleId): string
{
    fuelTrackerEnsureScopeColumns($conn);

    $stmt = $conn->prepare("
        SELECT office
        FROM vehicles
        WHERE id = ?
            AND LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) <> 'private'
        LIMIT 1
    ");
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return trim((string) ($row['office'] ?? ''));
}

$input = gasIssuanceInput();
$action = strtolower(trim((string) ($input['action'] ?? 'create')));
$allowedStatuses = ['draft', 'approved', 'valid', 'used', 'expired', 'revoked'];
$transactionStarted = false;

try {
    fuelTrackerEnsureScopeColumns($conn);

    if ($action === 'create') {
        $serialNo = trim((string) ($input['serial_no'] ?? ''));
        $driverName = trim((string) ($input['driver_name'] ?? ''));
        $authorizedLiters = (float) ($input['authorized_liters'] ?? -1);
        $vehicleId = gasIssuanceVehicleId($conn, $input);
        $issueDate = gasIssuanceRequireDate(trim((string) ($input['issue_date'] ?? '')), 'Issue date');
        $expiryDate = gasIssuanceRequireDate(trim((string) ($input['expiry_date'] ?? '')), 'Expiry date');

        if ($serialNo === '') {
            $serialNo = 'FI-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        if ($driverName === '') {
            gasIssuanceJson(['success' => false, 'message' => 'Driver name is required.'], 422);
        }
        if ($authorizedLiters < 0) {
            gasIssuanceJson(['success' => false, 'message' => 'Authorized liters must be zero or greater.'], 422);
        }

        $vehicleOffice = gasIssuanceVehicleOffice($conn, $vehicleId);
        $submittedOffice = trim((string) ($input['office'] ?? ''));
        $office = $vehicleOffice !== '' ? $vehicleOffice : ($submittedOffice !== '' ? $submittedOffice : 'Office');
        $purpose = trim((string) ($input['purpose'] ?? 'OFFICIAL TRAVEL'));
        $fuelType = trim((string) ($input['fuel_type'] ?? 'Unleaded'));
        $unit = trim((string) ($input['unit'] ?? 'Liters'));
        $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'draft';
        }

        $stmt = $conn->prepare("
            INSERT INTO gas_issuances
                (serial_no, vehicle_id, driver_name, office, purpose, fuel_type, authorized_liters, unit, issue_date, expiry_date, status, issuance_scope, approved_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'government', IF(? IN ('approved', 'valid'), NOW(), NULL))
        ");
        $stmt->bind_param('sissssdsssss', $serialNo, $vehicleId, $driverName, $office, $purpose, $fuelType, $authorizedLiters, $unit, $issueDate, $expiryDate, $status, $status);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        gasIssuanceJson(['success' => true, 'message' => 'Gas issuance saved.', 'id' => $newId, 'serial_no' => $serialNo]);
    }

    if ($action === 'update') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            gasIssuanceJson(['success' => false, 'message' => 'Issuance ID is required.'], 422);
        }

        $vehicleId = gasIssuanceVehicleId($conn, $input);
        $driverName = trim((string) ($input['driver_name'] ?? ''));
        $authorizedLiters = (float) ($input['authorized_liters'] ?? -1);
        $issueDate = gasIssuanceRequireDate(trim((string) ($input['issue_date'] ?? '')), 'Issue date');
        $expiryDate = gasIssuanceRequireDate(trim((string) ($input['expiry_date'] ?? '')), 'Expiry date');
        $status = strtolower(trim((string) ($input['status'] ?? 'valid')));
        $office = gasIssuanceVehicleOffice($conn, $vehicleId) ?: trim((string) ($input['office'] ?? 'Office'));

        if ($driverName === '') {
            gasIssuanceJson(['success' => false, 'message' => 'Driver name is required.'], 422);
        }
        if ($authorizedLiters < 0) {
            gasIssuanceJson(['success' => false, 'message' => 'Authorized liters must be zero or greater.'], 422);
        }
        if (!in_array($status, $allowedStatuses, true)) {
            gasIssuanceJson(['success' => false, 'message' => 'Invalid status.'], 422);
        }

        $stmt = $conn->prepare("
            UPDATE gas_issuances
            SET vehicle_id = ?,
                driver_name = ?,
                office = ?,
                authorized_liters = ?,
                issue_date = ?,
                expiry_date = ?,
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(issuance_scope, 'government'))) <> 'private'
        ");
        $stmt->bind_param('issdsssi', $vehicleId, $driverName, $office, $authorizedLiters, $issueDate, $expiryDate, $status, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        gasIssuanceJson(['success' => true, 'message' => 'Gas issuance updated.', 'updated' => $affected]);
    }

    if ($action === 'delete') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            gasIssuanceJson(['success' => false, 'message' => 'Issuance ID is required.'], 422);
        }

        $conn->begin_transaction();
        $transactionStarted = true;

        $stmt = $conn->prepare('DELETE FROM vehicle_odometer_logs WHERE gas_issuance_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            DELETE FROM gas_issuances
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(issuance_scope, 'government'))) <> 'private'
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected < 1) {
            $conn->rollback();
            $transactionStarted = false;
            gasIssuanceJson(['success' => false, 'message' => 'Gas issuance record was not found.'], 404);
        }

        $conn->commit();
        $transactionStarted = false;

        gasIssuanceJson(['success' => true, 'message' => 'Gas issuance deleted.', 'deleted' => $affected]);
    }

    if ($action === 'approval') {
        $id = (int) ($input['id'] ?? 0);
        $approved = filter_var($input['approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($id <= 0) {
            gasIssuanceJson(['success' => false, 'message' => 'Issuance ID is required.'], 422);
        }

        $stmt = $conn->prepare("
            SELECT status
            FROM gas_issuances
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(issuance_scope, 'government'))) <> 'private'
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            gasIssuanceJson(['success' => false, 'message' => 'Gas issuance record was not found.'], 404);
        }

        $currentStatus = strtolower((string) ($row['status'] ?? ''));
        if ($currentStatus === 'used') {
            gasIssuanceJson(['success' => false, 'message' => 'Used issuances can no longer be changed.'], 409);
        }
        if (in_array($currentStatus, ['expired', 'revoked'], true) && $approved) {
            gasIssuanceJson(['success' => false, 'message' => 'Expired or revoked issuances cannot be approved.'], 409);
        }

        $newStatus = $approved ? 'approved' : 'draft';
        $stmt = $conn->prepare("
            UPDATE gas_issuances
            SET status = ?,
                approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
                AND LOWER(TRIM(COALESCE(issuance_scope, 'government'))) <> 'private'
        ");
        $stmt->bind_param('ssi', $newStatus, $newStatus, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        gasIssuanceJson([
            'success' => true,
            'message' => $approved ? 'Gas issuance approved.' : 'Gas issuance moved back to draft.',
            'status' => $newStatus,
            'updated' => $affected,
        ]);
    }

    gasIssuanceJson(['success' => false, 'message' => 'Unsupported action.'], 400);
} catch (mysqli_sql_exception $e) {
    if ($transactionStarted && isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Gas issuance rollback error: ' . $rollbackError->getMessage());
        }
    }
    error_log('Gas issuance save error: ' . $e->getMessage());
    gasIssuanceJson(['success' => false, 'message' => 'Unable to save gas issuance.'], 500);
} catch (Throwable $e) {
    if ($transactionStarted && isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Gas issuance rollback error: ' . $rollbackError->getMessage());
        }
    }
    error_log('Gas issuance unexpected error: ' . $e->getMessage());
    gasIssuanceJson(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
