<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole(['fuel_admin', 'coa_admin'], 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

header('Content-Type: application/json');

function odometerVehiclesJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$month = trim((string) ($_GET['month'] ?? ''));
$startDate = trim((string) ($_GET['start_date'] ?? ''));
$endDate = trim((string) ($_GET['end_date'] ?? ''));
$scope = strtolower(trim((string) ($_GET['scope'] ?? 'all')));
if (!in_array($scope, ['all', 'government', 'private'], true)) {
    $scope = 'all';
}

try {
    $rows = fuelTrackerFetchOdometerHistory($conn, [
        'month' => $month,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'scope' => $scope,
    ]);

    $vehicles = [];
    foreach ($rows as $row) {
        $plate = trim((string) ($row['plate_no'] ?? ''));
        if ($plate === '' || isset($vehicles[$plate])) {
            continue;
        }
        $scopeValue = strtolower(trim((string) ($row['scope'] ?? 'government')));
        $vehicles[$plate] = [
            'plate_no' => $plate,
            'vehicle_name' => trim((string) ($row['vehicle_name'] ?? '')),
            'office' => trim((string) ($row['office'] ?? '')),
            'scope' => $scopeValue === 'private' ? 'private' : 'government',
        ];
    }

    ksort($vehicles, SORT_NATURAL | SORT_FLAG_CASE);
    odometerVehiclesJson([
        'success' => true,
        'vehicles' => array_values($vehicles),
        'count' => count($vehicles),
    ]);
} catch (InvalidArgumentException $error) {
    odometerVehiclesJson(['success' => false, 'message' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Odometer history vehicles error: ' . $error->getMessage());
    odometerVehiclesJson(['success' => false, 'message' => 'Unable to load odometer history vehicles.'], 500);
}