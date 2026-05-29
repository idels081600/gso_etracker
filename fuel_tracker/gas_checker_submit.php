<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('gas_checker', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'gas_checker_submit', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';
require_once __DIR__ . '/gas_issuance_signature.php';

header('Content-Type: application/json');

function gasCheckerSubmitJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function gasCheckerSubmitInput(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return $_POST;
}

function gasCheckerSubmitNumber(mixed $value, string $label): float
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        gasCheckerSubmitJson(['success' => false, 'message' => $label . ' must be a valid number.'], 422);
    }

    $number = (float) $value;
    if ($number < 0) {
        gasCheckerSubmitJson(['success' => false, 'message' => $label . ' must be zero or greater.'], 422);
    }

    return $number;
}

$input = gasCheckerSubmitInput();
$transactionStarted = false;

try {
    fuelTrackerEnsureVehiclePastOdometer($conn);

    $id = (int) ($input['db_id'] ?? 0);
    $serialNo = strtoupper(trim((string) ($input['serial_no'] ?? '')));
    $driverName = trim((string) ($input['driver_name'] ?? ''));
    $signature = trim((string) ($input['signature'] ?? ''));
    $currentOdometer = gasCheckerSubmitNumber($input['current_odometer'] ?? null, 'Current odometer');
    $actualLiters = gasCheckerSubmitNumber($input['actual_liters_fueled'] ?? null, 'Actual fueled up');

    if ($id <= 0 && $serialNo === '') {
        gasCheckerSubmitJson(['success' => false, 'message' => 'Gas issuance reference is required.'], 422);
    }
    if ($driverName === '') {
        gasCheckerSubmitJson(['success' => false, 'message' => 'Driver name is required.'], 422);
    }
    if (fuelTrackerNormalizeSignature($signature) === '') {
        gasCheckerSubmitJson(['success' => false, 'message' => 'Driver e-signature is required.'], 422);
    }

    if ($id > 0) {
        $stmt = $conn->prepare("
            SELECT gi.id, gi.vehicle_id, gi.status, COALESCE(v.past_odometer, v.current_odometer, 0) AS past_odometer
            FROM gas_issuances gi
            INNER JOIN vehicles v ON v.id = gi.vehicle_id
            WHERE gi.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare("
            SELECT gi.id, gi.vehicle_id, gi.status, COALESCE(v.past_odometer, v.current_odometer, 0) AS past_odometer
            FROM gas_issuances gi
            INNER JOIN vehicles v ON v.id = gi.vehicle_id
            WHERE UPPER(gi.serial_no) = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $serialNo);
    }

    $stmt->execute();
    $issuance = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$issuance) {
        gasCheckerSubmitJson(['success' => false, 'message' => 'Gas issuance record was not found.'], 404);
    }

    $status = strtolower((string) ($issuance['status'] ?? ''));
    if (!in_array($status, ['approved', 'valid'], true)) {
        gasCheckerSubmitJson(['success' => false, 'message' => 'This gas issuance is ' . ($status !== '' ? $status : 'not approved') . ' and cannot be submitted until approved.'], 409);
    }

    $gasIssuanceId = (int) $issuance['id'];
    $vehicleId = (int) $issuance['vehicle_id'];
    $pastOdometer = (float) ($issuance['past_odometer'] ?? 0);

    if ($currentOdometer < $pastOdometer) {
        gasCheckerSubmitJson([
            'success' => false,
            'message' => 'Current odometer cannot be lower than the last vehicle odometer (' . number_format($pastOdometer, 1) . ' km).',
        ], 422);
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("
        UPDATE gas_issuances
        SET driver_name = ?,
            actual_liters_fueled = ?,
            status = 'used',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param('sdi', $driverName, $actualLiters, $gasIssuanceId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO vehicle_odometer_logs
            (gas_issuance_id, vehicle_id, past_odometer, current_odometer, recorded_at)
        VALUES
            (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('iidd', $gasIssuanceId, $vehicleId, $pastOdometer, $currentOdometer);
    $stmt->execute();
    $stmt->close();

    fuelTrackerSaveDriverSignature($conn, $gasIssuanceId, $signature, $driverName);

    $conn->commit();
    $transactionStarted = false;

    gasCheckerSubmitJson([
        'success' => true,
        'message' => 'Fuel-up record submitted.',
        'past_odometer' => $pastOdometer,
        'current_odometer' => $currentOdometer,
        'actual_liters_fueled' => $actualLiters,
    ]);
} catch (mysqli_sql_exception $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Gas checker submit error: ' . $e->getMessage());
    gasCheckerSubmitJson(['success' => false, 'message' => 'Unable to submit fuel-up record.'], 500);
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Gas checker submit unexpected error: ' . $e->getMessage());
    gasCheckerSubmitJson(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
