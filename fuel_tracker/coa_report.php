<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole(['coa_admin', 'fuel_admin'], 'text');
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(20, 60, 'coa_report', 'text');

$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

if (!is_file($tcpdfPath)) {
    http_response_code(500);
    exit('TCPDF library not found. Please run composer install from the project root.');
}

require_once $tcpdfPath;

if (!class_exists('TCPDF')) {
    http_response_code(500);
    exit('TCPDF failed to load.');
}

function coaParam(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    $value = is_scalar($value) ? trim((string) $value) : $default;
    return $value !== '' ? $value : $default;
}

function coaFloat(mixed $value, float $default = 0.0): float
{
    if (is_string($value)) {
        $value = str_replace(',', '', trim($value));
    }

    return is_numeric($value) ? (float) $value : $default;
}

function coaText(mixed $value, string $default = ''): string
{
    $text = is_scalar($value) ? trim((string) $value) : $default;
    return $text !== '' ? $text : $default;
}

function coaNumber(float $value, int $decimals = 0, bool $trimDecimals = true): string
{
    $formatted = number_format($value, $decimals, '.', '');
    if ($decimals === 0 || !$trimDecimals) {
        return $formatted;
    }

    return rtrim(rtrim($formatted, '0'), '.') ?: '0';
}

function coaPeriodLabel(string $startDate, string $endDate): string
{
    $start = strtotime($startDate);
    $end = strtotime($endDate);

    if ($start === false || $end === false) {
        return strtoupper(coaParam('period', 'APRIL 9-19, 2024'));
    }

    if (date('F Y', $start) === date('F Y', $end)) {
        return strtoupper(date('F', $start) . ' ' . date('j', $start) . '-' . date('j, Y', $end));
    }

    return strtoupper(date('F j, Y', $start) . ' - ' . date('F j, Y', $end));
}

function coaRowsFromRequest(): array
{
    $raw = $_POST['records'] ?? $_GET['records'] ?? '';
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function coaIdsFromRequest(): array
{
    $raw = $_POST['issuance_ids'] ?? $_GET['issuance_ids'] ?? '';
    if (is_array($raw)) {
        return array_values(array_filter(array_map('intval', $raw), static fn(int $id): bool => $id > 0));
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    return array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw)), static fn(int $id): bool => $id > 0));
}

function coaSummaryRowFromIssuances(array $issuances): array
{
    $first = $issuances[0] ?? [];
    $totalFuel = 0.0;
    $pastOdometers = [];
    $currentOdometers = [];

    foreach ($issuances as $issuance) {
        $totalFuel += coaFloat($issuance['authorized_liters'] ?? 0);
        $past = coaFloat($issuance['past_odometer'] ?? 0);
        $current = coaFloat($issuance['current_odometer'] ?? $issuance['current_odo'] ?? 0);
        if ($past > 0) {
            $pastOdometers[] = $past;
        }
        if ($current > 0) {
            $currentOdometers[] = $current;
        }
    }

    $normal = coaFloat($first['normal_km_per_liter'] ?? 20, 20);
    if ($normal <= 0) {
        $normal = 20;
    }
    $ending = $currentOdometers !== [] ? max($currentOdometers) : 0;
    $beginning = $pastOdometers !== [] ? min($pastOdometers) : max(0, $ending - ($totalFuel * $normal));

    return [
        'type_of_vehicle' => $first['vehicle_type'] ?? '',
        'plate_number' => $first['plate_no'] ?? '',
        'cylinders' => $first['cylinders'] ?? 4,
        'past_odometer' => $beginning,
        'current_odometer' => $ending,
        'fuel_used' => $totalFuel,
        'normal_km_per_liter' => $normal,
        'remarks' => $first['office'] ?? 'Office',
    ];
}

function coaSummaryRowsFromIssuances(array $issuances): array
{
    $groups = [];

    foreach ($issuances as $issuance) {
        $vehicleId = (int) ($issuance['vehicle_id'] ?? 0);
        $plateNo = coaText($issuance['plate_no'] ?? '', '');
        $key = $vehicleId > 0 ? 'vehicle-' . $vehicleId : 'plate-' . $plateNo;

        if (!isset($groups[$key])) {
            $groups[$key] = [];
        }

        $groups[$key][] = $issuance;
    }

    return array_values(array_map('coaSummaryRowFromIssuances', $groups));
}

function coaUpdateVehicleOdometersAfterReport(mysqli $conn, array $issuances): void
{
    $latestByVehicle = [];
    foreach ($issuances as $issuance) {
        $vehicleId = (int) ($issuance['vehicle_id'] ?? 0);
        $currentOdometer = coaFloat($issuance['current_odometer'] ?? 0);
        if ($vehicleId <= 0 || $currentOdometer <= 0) {
            continue;
        }

        if (!isset($latestByVehicle[$vehicleId]) || $currentOdometer > $latestByVehicle[$vehicleId]['current']) {
            $latestByVehicle[$vehicleId] = [
                'current' => $currentOdometer,
            ];
        }
    }

    if ($latestByVehicle === []) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'past_odometer'");
    if (!$result || $result->num_rows < 1) {
        if (!$conn->query('ALTER TABLE vehicles ADD COLUMN past_odometer DECIMAL(12,1) NULL AFTER current_odometer')) {
            throw new RuntimeException('Unable to add vehicles.past_odometer: ' . $conn->error);
        }
    }

    $stmt = $conn->prepare("
        UPDATE vehicles
        SET past_odometer = ?,
            current_odometer = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    foreach ($latestByVehicle as $vehicleId => $odometers) {
        $currentOdometer = (float) $odometers['current'];
        $pastOdometer = $currentOdometer;
        $stmt->bind_param('ddi', $pastOdometer, $currentOdometer, $vehicleId);
        $stmt->execute();
    }

    $stmt->close();
}

function coaBuildRow(array $row): array
{
    $beginning = round(coaFloat($row['beginning_odometer'] ?? $row['past_odometer'] ?? $row['previous_odometer'] ?? $row['last_odometer'] ?? $row['beginning'] ?? 0));
    $ending = round(coaFloat($row['ending_odometer'] ?? $row['current_odometer'] ?? $row['odometer'] ?? $row['ending'] ?? 0));
    $fuelUsed = coaFloat($row['fuel_used'] ?? $row['total_fuel_used'] ?? $row['liters_issued'] ?? 0);
    $normalKmPerLiter = coaFloat($row['normal_km_per_liter'] ?? $row['normal_travel'] ?? 20, 20);
    if ($normalKmPerLiter <= 0) {
        $normalKmPerLiter = 20;
    }
    $distance = max(0, $ending - $beginning);
    $distancePerLiter = $fuelUsed > 0 ? $distance / $fuelUsed : 0;
    $allowable = $normalKmPerLiter > 0 ? ($distance / $normalKmPerLiter) * 1.10 : 0;
    $excess = round(max(0, $fuelUsed - $allowable));

    return [
        'type_of_vehicle' => coaText($row['type_of_vehicle'] ?? $row['vehicle_type'] ?? $row['vehicle'] ?? '', 'Motorcycle'),
        'plate_number' => coaText($row['plate_number'] ?? $row['plate_no'] ?? '', 'New # 2'),
        'cylinders' => coaText($row['cylinders'] ?? $row['number_of_cylinder'] ?? '', '1'),
        'past_odometer' => $beginning,
        'beginning_odometer' => $beginning,
        'ending_odometer' => $ending,
        'total_distance' => $distance,
        'total_fuel_used' => $fuelUsed,
        'distance_per_liter' => $distancePerLiter,
        'normal_km_per_liter' => $normalKmPerLiter,
        'allowable_liters' => $allowable,
        'excess' => $excess,
        'remarks' => coaText($row['remarks'] ?? $row['office'] ?? '', 'ALERT Supervisor'),
    ];
}

function coaDrawCell(TCPDF $pdf, float $w, float $h, string $text, string $align = 'C', int $border = 1, bool $bold = false): void
{
    $pdf->SetFont('times', $bold ? 'B' : '', 8);
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->MultiCell($w, $h, $text, $border, $align, false, 0, '', '', true, 0, false, true, $h, 'M');
    $pdf->SetXY($x + $w, $y);
}

class CoaReportPdf extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

$selectedIssuanceIds = coaIdsFromRequest();
$selectedIssuances = [];
if ($selectedIssuanceIds !== []) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/gas_issuance_data.php';
    $selectedIssuances = fuelTrackerFetchGasIssuancesByIds($conn, $selectedIssuanceIds);
}

$selectedDates = array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['issue_date'] ?? ''), $selectedIssuances)));
sort($selectedDates);
$startDate = coaParam('start_date', $selectedDates[0] ?? '2024-04-09');
$endDate = coaParam('end_date', $selectedDates[count($selectedDates) - 1] ?? '2024-04-19');
$periodLabel = coaParam('period', coaPeriodLabel($startDate, $endDate));
$preparedBy = strtoupper(coaParam('prepared_by', 'KAREN APRIL SAPONG'));
$verifiedBy = strtoupper(coaParam('verified_by', 'CHRIS JOHN RENER G. TORRALBA'));
$verifiedTitle = coaParam('verified_title', 'City Government Department Head I - (GSO)');
$outputMode = coaParam('download', '0') === '1' ? 'D' : 'I';

$requestRows = coaRowsFromRequest();
if ($selectedIssuances !== []) {
    $requestRows = coaSummaryRowsFromIssuances($selectedIssuances);
}
if (empty($requestRows)) {
    $requestRows = [[
        'type_of_vehicle' => 'Motorcycle',
        'plate_number' => 'New # 2',
        'cylinders' => '1',
        'past_odometer' => 20582.0,
        'current_odometer' => 20742.0,
        'fuel_used' => 8,
        'normal_km_per_liter' => 20,
        'remarks' => 'ALERT Supervisor',
    ]];
}

$rows = array_map('coaBuildRow', $requestRows);

$pdf = new CoaReportPdf('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('COA Fuel Consumption Report');
$pdf->SetSubject('Report of Fuel Consumption');
$pdf->SetMargins(8, 0, 8);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.2);
$pdf->Rect(1.5, 1.5, 294, 207);

$pdf->SetFont('times', '', 9);
$pdf->SetXY(0, 25);
$pdf->Cell(297, 4.5, 'Republic of the Philippines', 0, 1, 'C');
$pdf->Cell(297, 4.5, 'Department of Interior & Local Government', 0, 1, 'C');
$pdf->Cell(297, 4.5, 'City Government of Tagbilaran', 0, 1, 'C');
$pdf->Cell(297, 4.5, 'Report of Fuel Consumption for the Month of ' . strtoupper($periodLabel), 0, 1, 'C');

$tableX = 8;
$tableY = 50;
$widths = [32, 23, 20, 20, 20, 23, 21, 20, 22, 25, 20, 20];
$headerH1 = 18;
$headerH2 = 6;
$rowH = 15;

$pdf->SetXY($tableX, $tableY);
$headers = [
    'Type of Vehicle',
    'Plate Number',
    "Number of\nCylinder",
    "Odometer Reading",
    '',
    "Total\nDistance\nTravelled",
    "Total Fuel\nUsed",
    "Distance\nTravelled per\nLiter",
    "Normal Travel\nkilometer per\nliter",
    "Total Liters\nConsumed Plus\n10% Allowance",
    'Excess',
    'Remarks',
];

foreach ($headers as $index => $header) {
    if ($index === 3) {
        coaDrawCell($pdf, $widths[3] + $widths[4], $headerH1, $header, 'C', 1, false);
        $index++;
        continue;
    }

    if ($index === 4) {
        continue;
    }

    coaDrawCell($pdf, $widths[$index], $headerH1 + $headerH2, $header, 'C', 1, false);
}

$pdf->SetXY($tableX + array_sum(array_slice($widths, 0, 3)), $tableY + $headerH1);
coaDrawCell($pdf, $widths[3], $headerH2, 'Beginning', 'C', 1, false);
coaDrawCell($pdf, $widths[4], $headerH2, 'Ending', 'C', 1, false);

$pdf->SetXY($tableX, $tableY + $headerH1 + $headerH2);
$formulaRow = ['', '', '', '', '', 'A', 'B', 'C = A / B', 'D', 'E = (A / D) (1.1)', 'F = B - E', ''];
foreach ($formulaRow as $index => $formula) {
    coaDrawCell($pdf, $widths[$index], 6, $formula, 'C', 1, false);
}

$pdf->SetFont('times', '', 8.5);
$currentY = $tableY + $headerH1 + $headerH2 + 6;
$reportTotals = [
    'distance' => 0.0,
    'fuel' => 0.0,
    'allowance' => 0.0,
    'excess' => 0.0,
];
foreach ($rows as $row) {
    if ($currentY + $rowH > 145) {
        $pdf->AddPage();
        $currentY = 20;
    }

    $reportTotals['distance'] += $row['total_distance'];
    $reportTotals['fuel'] += $row['total_fuel_used'];
    $reportTotals['allowance'] += $row['allowable_liters'];
    $reportTotals['excess'] += $row['excess'];

    $pdf->SetXY($tableX, $currentY);
    $values = [
        $row['type_of_vehicle'],
        $row['plate_number'],
        $row['cylinders'],
        coaNumber(round($row['beginning_odometer']), 0),
        coaNumber(round($row['ending_odometer']), 0),
        coaNumber($row['total_distance'], 0),
        coaNumber($row['total_fuel_used'], 2),
        coaNumber($row['distance_per_liter'], 2),
        coaNumber($row['normal_km_per_liter'], 2),
        coaNumber($row['allowable_liters'], 2),
        coaNumber($row['excess'], 0),
        $row['remarks'],
    ];

    foreach ($values as $index => $value) {
        coaDrawCell($pdf, $widths[$index], $rowH, $value, 'C', 1, false);
    }

    $currentY += $rowH;
}

$totalDistancePerLiter = $reportTotals['fuel'] > 0 ? $reportTotals['distance'] / $reportTotals['fuel'] : 0;
if (count($rows) > 1) {
    $pdf->SetXY($tableX, $currentY);
    $totalValues = [
        'TOTALS',
        '',
        '',
        '',
        '',
        coaNumber($reportTotals['distance'], 0),
        coaNumber($reportTotals['fuel'], 2),
        coaNumber($totalDistancePerLiter, 2),
        '',
        coaNumber($reportTotals['allowance'], 2),
        coaNumber($reportTotals['excess'], 0),
        '',
    ];

    foreach ($totalValues as $index => $value) {
        coaDrawCell($pdf, $widths[$index], 8, $value, 'C', 1, true);
    }

    $currentY += 8;
}

$signatureY = 115;
$pdf->SetFont('times', '', 9);
$pdf->SetXY(9, $signatureY);
$pdf->Cell(70, 5, 'Prepared by:', 0, 0, 'L');
$pdf->SetXY(147, $signatureY);
$pdf->Cell(90, 5, 'Verified and Found Correct:', 0, 0, 'L');

$pdf->SetFont('times', 'B', 9);
$pdf->SetXY(9, $signatureY + 17);
$pdf->Cell(70, 5, $preparedBy, 0, 0, 'L');
$pdf->Line(9, $signatureY + 22, 48, $signatureY + 22);

$pdf->SetXY(147, $signatureY + 17);
$pdf->Cell(95, 5, $verifiedBy, 0, 0, 'L');
$pdf->Line(147, $signatureY + 22, 224, $signatureY + 22);
$pdf->SetFont('times', '', 8.5);
$pdf->SetXY(147, $signatureY + 22);
$pdf->Cell(95, 5, $verifiedTitle, 0, 0, 'L');

$pdf->Output('COA_Fuel_Consumption_Report.pdf', $outputMode);
?>
