<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole(['fuel_admin', 'coa_admin'], 'text');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

function odometerHistoryParam(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function odometerHistoryNumber(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, '.', '');
}

function odometerHistoryDatePart(?string $value, string $format): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date($format, $timestamp);
}

$month = odometerHistoryParam('month');
$startDate = odometerHistoryParam('start_date');
$endDate = odometerHistoryParam('end_date');
$scope = strtolower(odometerHistoryParam('scope', 'all'));
if (!in_array($scope, ['all', 'government', 'private'], true)) {
    $scope = 'all';
}

$filters = [
    'month' => $month,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'scope' => $scope,
    'vehicle_id' => odometerHistoryParam('vehicle_id'),
    'plate_no' => odometerHistoryParam('plate_no'),
    'fuel_type' => odometerHistoryParam('fuel_type'),
];

try {
    $rows = fuelTrackerFetchOdometerHistory($conn, $filters);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $error->getMessage();
    exit;
} catch (Throwable $error) {
    error_log('Odometer history export error: ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to export odometer history.';
    exit;
}

$rangeLabel = $month !== '' ? $month : (($startDate !== '' || $endDate !== '') ? trim($startDate . '_to_' . $endDate, '_') : date('Y-m'));
$safeRange = preg_replace('/[^0-9A-Za-z_-]+/', '_', $rangeLabel) ?: date('Y-m');
$filename = 'vehicle_odometer_history_' . $safeRange . '_' . $scope . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit;
}

fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'recorded_date',
    'plate_no',
    'vehicle_name',
    'office',
    'driver_name',
    'fuel_type',
    'current_odometer',
    'km_traveled',
    'actual_liters_fueled',
]);

foreach ($rows as $row) {
    fputcsv($output, [
        odometerHistoryDatePart($row['recorded_at'] ?? '', 'Y-m-d'),
        (string) ($row['plate_no'] ?? ''),
        (string) ($row['vehicle_name'] ?? ''),
        (string) ($row['office'] ?? ''),
        (string) ($row['driver_name'] ?? ''),
        (string) ($row['fuel_type'] ?? ''),
        odometerHistoryNumber((float) ($row['current_odometer'] ?? 0), 1),
        odometerHistoryNumber((float) ($row['km_traveled'] ?? 0), 1),
        odometerHistoryNumber((float) ($row['actual_liters_fueled'] ?? 0), 2),
    ]);
}

fclose($output);