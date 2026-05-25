<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('coa_admin', 'text');
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(20, 60, 'monthly_official_travels', 'text');

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

function motParam(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    $value = is_scalar($value) ? trim((string) $value) : $default;
    return $value !== '' ? $value : $default;
}

function motFloat(mixed $value, float $default = 0.0): float
{
    if (is_string($value)) {
        $value = str_replace(',', '', trim($value));
    }

    return is_numeric($value) ? (float) $value : $default;
}

function motFormatNumber(float $value): string
{
    if (abs($value) < 0.00001) {
        return '';
    }

    $formatted = number_format($value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function motRowsFromRequest(): array
{
    $raw = $_POST['records'] ?? $_GET['records'] ?? '';
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function motIdsFromRequest(): array
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

function motDateRangeLabel(array $issuances): string
{
    $dates = array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['issue_date'] ?? ''), $issuances)));
    sort($dates);
    if ($dates === []) {
        return '';
    }

    $first = strtotime($dates[0]);
    $last = strtotime($dates[count($dates) - 1]);
    if ($first === false || $last === false) {
        return $dates[0] . ' - ' . $dates[count($dates) - 1];
    }

    if (date('F Y', $first) === date('F Y', $last)) {
        return strtoupper(date('F', $first) . ' ' . date('j', $first) . '-' . date('j, Y', $last));
    }

    return strtoupper(date('F j, Y', $first) . ' - ' . date('F j, Y', $last));
}

function motCell(TCPDF $pdf, float $w, float $h, string $text, string $align = 'C', bool $bold = false): void
{
    $pdf->SetFont('times', $bold ? 'B' : '', 7.2);
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->MultiCell($w, $h, $text, 1, $align, false, 0, '', '', true, 0, false, true, $h, 'M');
    $pdf->SetXY($x + $w, $y);
}

class MonthlyOfficialTravelsPdf extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

$selectedIssuanceIds = motIdsFromRequest();
$selectedIssuances = [];
if ($selectedIssuanceIds !== []) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/gas_issuance_data.php';
    $selectedIssuances = fuelTrackerFetchGasIssuancesByIds($conn, $selectedIssuanceIds);
}

$vehicle = strtoupper(motParam('vehicle', $selectedIssuances[0]['vehicle_type'] ?? 'MULTICAB PICK UP#1'));
$plateNo = strtoupper(motParam('plate_no', $selectedIssuances[0]['plate_no'] ?? '0901-547669'));
$dateLabel = strtoupper(motParam('date', $selectedIssuances !== [] ? motDateRangeLabel($selectedIssuances) : 'FEBRUARY 12-16, 2026'));
$driver = strtoupper(motParam('driver', $selectedIssuances[0]['driver_name'] ?? 'CHONA NELSON'));
$preparedBy = strtoupper(motParam('prepared_by', ''));
$approvedBy = strtoupper(motParam('approved_by', 'CHRIS JOHN RENER TORRALBA'));
$approvedTitle = motParam('approved_title', 'City Goverment Department Head I - GSO');
$outputMode = motParam('download', '0') === '1' ? 'D' : 'I';

$rows = motRowsFromRequest();
if ($selectedIssuances !== []) {
    $rows = array_map(static function (array $issuance): array {
        $timestamp = strtotime((string) ($issuance['issue_date'] ?? ''));
        return [
            'date' => $timestamp !== false ? (int) date('j', $timestamp) : 0,
            'gasoline_consumed' => (float) ($issuance['authorized_liters'] ?? 0),
            'remarks' => (string) ($issuance['office'] ?? 'Office'),
        ];
    }, $selectedIssuances);
}
if (empty($rows)) {
    $rows = [
        ['date' => 12, 'gasoline_consumed' => 35],
        ['date' => 16, 'gasoline_consumed' => 35],
    ];
}

$rowsByDay = [];
foreach ($rows as $row) {
    $day = (int) ($row['date'] ?? $row['day'] ?? 0);
    if ($day < 1 || $day > 31) {
        continue;
    }

    if (!isset($rowsByDay[$day])) {
        $rowsByDay[$day] = [
            'distance' => 0.0,
            'gasoline' => 0.0,
            'oil' => 0.0,
            'grease' => 0.0,
            'remarks' => '',
        ];
    }

    $remarks = is_scalar($row['remarks'] ?? '') ? trim((string) ($row['remarks'] ?? '')) : '';
    $rowsByDay[$day]['distance'] += motFloat($row['distance_travelled'] ?? $row['distance'] ?? 0);
    $rowsByDay[$day]['gasoline'] += motFloat($row['gasoline_consumed'] ?? $row['gasoline'] ?? $row['liters'] ?? 0);
    $rowsByDay[$day]['oil'] += motFloat($row['oil_used'] ?? $row['oil'] ?? 0);
    $rowsByDay[$day]['grease'] += motFloat($row['grease_used'] ?? $row['grease'] ?? 0);
    if ($remarks !== '') {
        $rowsByDay[$day]['remarks'] = $rowsByDay[$day]['remarks'] !== '' ? $rowsByDay[$day]['remarks'] . '; ' . $remarks : $remarks;
    }
}

$totals = [
    'distance' => 0.0,
    'gasoline' => 0.0,
    'oil' => 0.0,
    'grease' => 0.0,
];
foreach ($rowsByDay as $row) {
    $totals['distance'] += $row['distance'];
    $totals['gasoline'] += $row['gasoline'];
    $totals['oil'] += $row['oil'];
    $totals['grease'] += $row['grease'];
}

$pdf = new MonthlyOfficialTravelsPdf('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('Monthly Report of Official Travels');
$pdf->SetSubject('Form B Monthly Report of Official Travels');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.2);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('times', 'B', 7);
$pdf->SetXY(18, 4);
$pdf->Cell(25, 5, 'Form B', 0, 0, 'L');

$pdf->SetFont('times', '', 8);
$pdf->SetXY(0, 10);
$pdf->Cell(210, 4, 'Republic of the Philippines', 0, 1, 'C');
$pdf->Cell(210, 4, 'Department of Interior & Local Government', 0, 1, 'C');
$pdf->Cell(210, 4, 'City Government of Tagbilaran', 0, 1, 'C');
$pdf->Cell(210, 4, 'City General Services Office', 0, 1, 'C');

$pdf->SetFont('times', 'B', 9);
$pdf->SetXY(0, 31);
$pdf->Cell(210, 5, 'MONTHLY REPORT OF OFFICIAL TRAVELS', 0, 1, 'C');
$pdf->SetFont('times', '', 8);
$pdf->Cell(210, 4, '(To be accomplished for each motor vehicle)', 0, 1, 'C');

$pdf->SetFont('times', '', 8);
$pdf->SetXY(22, 49);
$pdf->Cell(17, 4, 'Vehicle', 0, 0, 'L');
$pdf->SetFont('times', 'B', 8);
$pdf->Cell(58, 4, $vehicle, 0, 0, 'L');
$pdf->SetFont('times', '', 8);
$pdf->SetXY(116, 49);
$pdf->Cell(16, 4, 'Date:', 0, 0, 'L');
$pdf->SetFont('times', 'B', 8);
$pdf->Cell(46, 4, $dateLabel, 0, 0, 'L');

$pdf->SetFont('times', '', 8);
$pdf->SetXY(22, 55);
$pdf->Cell(17, 4, 'Plate No.:', 0, 0, 'L');
$pdf->SetFont('times', 'B', 8);
$pdf->Cell(58, 4, $plateNo, 0, 0, 'L');
$pdf->SetFont('times', '', 8);
$pdf->SetXY(116, 55);
$pdf->Cell(24, 4, 'Driver`s Name:', 0, 0, 'L');
$pdf->SetFont('times', 'B', 8);
$pdf->Cell(54, 4, $driver, 0, 0, 'L');

$tableX = 10;
$tableY = 70;
$widths = [20, 38, 28, 20, 26, 58];
$headerH = 15;
$rowH = 5.08;

$pdf->SetXY($tableX, $tableY);
motCell($pdf, $widths[0], $headerH, 'Date', 'C', true);
motCell($pdf, $widths[1], $headerH, "Distance Travelled\n(in kilometers)", 'C', true);
motCell($pdf, $widths[2], $headerH, "Gasoline\nConsumed\n(in liters)", 'C', true);
motCell($pdf, $widths[3], $headerH, "Oil Used\n(in liters)", 'C', true);
motCell($pdf, $widths[4], $headerH, 'Grease Used', 'C', true);
motCell($pdf, $widths[5], $headerH, 'Remarks', 'C', true);

$currentY = $tableY + $headerH;
for ($day = 1; $day <= 31; $day++) {
    $row = $rowsByDay[$day] ?? ['distance' => 0.0, 'gasoline' => 0.0, 'oil' => 0.0, 'grease' => 0.0, 'remarks' => ''];
    $pdf->SetXY($tableX, $currentY);
    motCell($pdf, $widths[0], $rowH, (string) $day, 'R');
    motCell($pdf, $widths[1], $rowH, motFormatNumber($row['distance']), 'C');
    motCell($pdf, $widths[2], $rowH, motFormatNumber($row['gasoline']), 'C');
    motCell($pdf, $widths[3], $rowH, motFormatNumber($row['oil']), 'C');
    motCell($pdf, $widths[4], $rowH, motFormatNumber($row['grease']), 'C');
    motCell($pdf, $widths[5], $rowH, $row['remarks'], 'L');
    $currentY += $rowH;
}

$pdf->SetXY($tableX, $currentY);
motCell($pdf, $widths[0], $rowH, 'TOTALS', 'L', true);
motCell($pdf, $widths[1], $rowH, motFormatNumber($totals['distance']), 'C', true);
motCell($pdf, $widths[2], $rowH, motFormatNumber($totals['gasoline']), 'C', true);
motCell($pdf, $widths[3], $rowH, motFormatNumber($totals['oil']), 'C', true);
motCell($pdf, $widths[4], $rowH, motFormatNumber($totals['grease']), 'C', true);
motCell($pdf, $widths[5], $rowH, '', 'L', true);

$pdf->SetFont('times', '', 8);
$pdf->SetXY(43, $currentY + 12);
$pdf->MultiCell(126, 8, 'I hereby certify to the correctness of the above statement and that the motor vehicle', 0, 'C');
$pdf->SetXY(36, $currentY + 18);
$pdf->MultiCell(140, 8, 'was used on strictly official business only.', 0, 'C');

if ($preparedBy !== '') {
    $pdf->SetFont('times', 'B', 8);
    $pdf->SetXY(72, $currentY + 31);
    $pdf->Cell(66, 4, $preparedBy, 0, 0, 'C');
    $pdf->Line(72, $currentY + 35, 138, $currentY + 35);
}

$approvalY = min(277, $currentY + 30);
$pdf->SetFont('times', '', 8);
$pdf->SetXY(132, $approvalY);
$pdf->Cell(48, 4, 'Approved by:', 0, 1, 'L');
$pdf->SetFont('times', 'B', 8);
$pdf->SetXY(128, $approvalY + 10);
$pdf->Cell(68, 4, $approvedBy, 0, 1, 'C');
$pdf->Line(128, $approvalY + 14, 196, $approvalY + 14);
$pdf->SetFont('times', '', 7.5);
$pdf->SetXY(128, $approvalY + 15);
$pdf->MultiCell(68, 7, $approvedTitle, 0, 'C');

$pdf->Output('Monthly_Official_Travels_Form_B.pdf', $outputMode);
?>
