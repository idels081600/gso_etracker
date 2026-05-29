<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('gas_checker', 'json');
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(60, 60, 'gas_checker_lookup', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

header('Content-Type: application/json');

function gasCheckerLookupJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$issuanceId = strtoupper(trim((string) ($_GET['issuance_id'] ?? '')));
if ($issuanceId === '') {
    gasCheckerLookupJson(['success' => false, 'message' => 'Gas issuance ID is required.'], 422);
}

try {
    fuelTrackerEnsureVehiclePastOdometer($conn);

    $stmt = $conn->prepare("
        SELECT
            gi.id,
            gi.serial_no,
            gi.driver_name,
            gi.office,
            gi.purpose,
            gi.fuel_type,
            gi.authorized_liters,
            gi.unit,
            gi.issue_date,
            gi.expiry_date,
            gi.status,
            v.plate_no,
            COALESCE(v.past_odometer, v.current_odometer, 0) AS past_odometer,
            v.type_of_vehicle AS vehicle_type
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        WHERE UPPER(gi.serial_no) = ?
           OR CAST(gi.id AS CHAR) = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $issuanceId, $issuanceId);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$record) {
        gasCheckerLookupJson(['success' => false, 'message' => 'No gas issuance record found for ' . $issuanceId . '.'], 404);
    }

    $status = strtolower((string) ($record['status'] ?? ''));
    if (!in_array($status, ['approved', 'valid', 'used'], true)) {
        gasCheckerLookupJson(['success' => false, 'message' => 'This gas issuance is ' . ($status !== '' ? $status : 'not approved') . ' and cannot be checked until approved.'], 409);
    }

    gasCheckerLookupJson([
        'success' => true,
        'record' => [
            'db_id' => (int) $record['id'],
            'id' => (string) $record['serial_no'],
            'vehicle' => (string) $record['vehicle_type'],
            'plate_no' => (string) $record['plate_no'],
            'office' => (string) ($record['office'] ?: 'Office'),
            'fuel_type' => (string) ($record['fuel_type'] ?: 'Unleaded'),
            'liters_issued' => (float) $record['authorized_liters'],
            'unit' => (string) ($record['unit'] ?: 'Liters'),
            'date' => (string) $record['issue_date'],
            'expiry_date' => (string) ($record['expiry_date'] ?? ''),
            'driver' => (string) $record['driver_name'],
            'purpose' => (string) ($record['purpose'] ?: 'Office'),
            'status' => ucfirst($status !== '' ? $status : 'valid'),
            'past_odometer' => (float) ($record['past_odometer'] ?? 0),
        ],
    ]);
} catch (mysqli_sql_exception $e) {
    error_log('Gas checker lookup error: ' . $e->getMessage());
    gasCheckerLookupJson(['success' => false, 'message' => 'Unable to search gas issuance records.'], 500);
} catch (Throwable $e) {
    error_log('Gas checker lookup unexpected error: ' . $e->getMessage());
    gasCheckerLookupJson(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
