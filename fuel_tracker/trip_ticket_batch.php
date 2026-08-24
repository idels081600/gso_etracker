<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole(['coa_admin', 'fuel_admin'], 'text');

$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) {
    http_response_code(500);
    exit('TCPDF library not found. Please run composer install from the project root.');
}

require_once $tcpdfPath;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';
require_once __DIR__ . '/gas_issuance_signature.php';

function batchTripFormatNumber(float $value): string
{
    $formatted = number_format($value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.') ?: '0';
}

function batchTripDateLabel(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp === false ? $date : date('F j, Y', $timestamp);
}

class BatchTripTicketPdf extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

function batchTripValueLine(
    BatchTripTicketPdf $pdf,
    float $x,
    float $y,
    float $w,
    string $value = '',
    string $align = 'C'
): void {
    $pdf->SetXY($x, $y - 4.4);
    $pdf->Cell($w, 4.4, $value, 0, 0, $align);
    $pdf->Line($x, $y, $x + $w, $y);
}

function batchTripNumberedLine(
    BatchTripTicketPdf $pdf,
    int $number,
    string $label,
    float $labelY,
    string $value = '',
    string $unit = ''
): void {
    $pdf->SetXY(22, $labelY);
    $pdf->Cell(7, 4.5, (string) $number, 0, 0, 'R');
    $pdf->SetXY(32, $labelY);
    $pdf->Cell(80, 4.5, $label, 0, 0, 'L');
    batchTripValueLine($pdf, 139, $labelY + 4, 33, $value);
    if ($unit !== '') {
        $pdf->SetXY(174, $labelY);
        $pdf->Cell(18, 4.5, $unit, 0, 0, 'L');
    }
}

function batchTripFuelLine(
    BatchTripTicketPdf $pdf,
    string $letter,
    string $label,
    float $labelY,
    string $value = '',
    string $unit = 'liters'
): void {
    $pdf->SetXY(37, $labelY);
    $pdf->Cell(6, 4.5, $letter, 0, 0, 'R');
    $pdf->SetXY(47, $labelY);
    $pdf->Cell(83, 4.5, $label, 0, 0, 'L');
    batchTripValueLine($pdf, 139, $labelY + 4, 33, $value);
    $pdf->SetXY(174, $labelY);
    $pdf->Cell(20, 4.5, $unit, 0, 0, 'L');
}


function batchTripVehiclePlateLabel(string $vehicleType, string $plateNo): string
{
    $vehicleType = trim($vehicleType);
    $plateNo = trim($plateNo);
    if ($vehicleType === '') {
        return strtoupper($plateNo);
    }
    if ($plateNo === '') {
        return strtoupper($vehicleType);
    }

    $normalize = static fn(string $value): string => preg_replace('/\s+/', ' ', strtoupper(trim($value))) ?? '';
    if ($normalize($vehicleType) === $normalize($plateNo)) {
        return strtoupper($vehicleType);
    }

    return strtoupper($vehicleType . ' ' . $plateNo);
}

function batchTripDrawPage(BatchTripTicketPdf $pdf, mysqli $conn, array $issuance, array $options = []): void
{
    $issuanceId = (int) ($issuance['id'] ?? 0);
    $serialNo = (string) ($issuance['serial_no'] ?? '');
    $office = trim((string) ($issuance['office'] ?? '')) ?: '(Name of Office)';
    $date = batchTripDateLabel((string) ($issuance['issue_date'] ?? date('Y-m-d')));
    $driver = strtoupper((string) ($issuance['driver_name'] ?? ''));
    $vehicle = batchTripVehiclePlateLabel(
        (string) ($issuance['vehicle_type'] ?? ''),
        (string) ($issuance['plate_no'] ?? '')
    );
    $purpose = (string) ($issuance['purpose'] ?? 'OFFICIAL TRAVEL');
    $approvedBy = strtoupper((string) ($issuance['approved_by'] ?? 'CHRIS JOHN RENER G. TORRALBA'));
    $blankFuelValues = !empty($options['blank_fuel_values']);
    $hideSignature = !empty($options['hide_signature']);
    $issuedValue = (float) ($issuance['authorized_liters'] ?? 0);
    $balanceValue = 2.0;
    $issued = $blankFuelValues ? '' : batchTripFormatNumber($issuedValue);
    $balance = $blankFuelValues ? '' : batchTripFormatNumber($balanceValue);
    $total = $blankFuelValues ? '' : batchTripFormatNumber($balanceValue + $issuedValue);
    $endBalance = $blankFuelValues ? '' : batchTripFormatNumber($balanceValue);
    $driverSignature = '';
    if (!$hideSignature) {
        $driverSignature = fuelTrackerFetchDriverSignatureByIssuanceId($conn, $issuanceId);
        if ($driverSignature === '') {
            $driverSignature = fuelTrackerFetchDriverSignature($conn, $serialNo);
        }
    }

    $pdf->AddPage();
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.18);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('times', '', 8);
    $pdf->SetXY(0, 14);
    $pdf->Cell(216, 4, 'Republic of the Philippines', 0, 1, 'C');
    $pdf->SetXY(0, 19);
    $pdf->Cell(216, 4, 'Department of Interior & Local Government', 0, 1, 'C');
    $pdf->SetXY(0, 24);
    $pdf->Cell(216, 4, 'City Government of Tagbilaran', 0, 1, 'C');

    $pdf->SetFont('times', '', 9);
    batchTripValueLine($pdf, 82, 34, 44, $office);
    $pdf->SetXY(82, 35);
    $pdf->Cell(44, 4, '(Name of Office)', 0, 0, 'C');
    batchTripValueLine($pdf, 150, 34, 34, $date);
    $pdf->SetXY(151, 35);
    $pdf->Cell(32, 4, '(Date)', 0, 0, 'C');

    $pdf->SetFont('times', 'B', 11);
    $pdf->SetXY(0, 48);
    $pdf->Cell(210, 6, "DRIVER'S TRIP TICKET", 0, 1, 'C');

    $pdf->SetFont('times', '', 8.7);
    $pdf->SetXY(17, 59);
    $pdf->Cell(120, 4.5, 'A. To be filled by the Administrative Official authorizing official travel:', 0, 1, 'L');

    $items = [
        [1, 'Name of driver of the vehicle', $driver],
        [2, 'Government car to be used, Plate No.', $vehicle],
        [3, 'Name of authorized passenger', ''],
        [4, 'Place or places to be visited/inspected', ''],
        [5, 'Purpose', $purpose],
    ];
    $y = 69;
    foreach ($items as [$number, $label, $value]) {
        $pdf->SetXY(25, $y);
        $pdf->Cell(7, 4.3, (string) $number, 0, 0, 'R');
        $pdf->SetXY(35, $y);
        $pdf->Cell(70, 4.3, $label, 0, 0, 'L');
        batchTripValueLine($pdf, 111, $y + 3.6, 72, (string) $value);
        $y += 5;
    }

    $pdf->SetFont('times', 'B', 8);
    $pdf->SetXY(108, 98);
    $pdf->Cell(70, 4.5, $approvedBy, 0, 1, 'C');
    $pdf->Line(112, 102, 174, 102);
    $pdf->SetFont('times', '', 8);
    $pdf->SetXY(108, 102.5);
    $pdf->MultiCell(70, 8, 'Chief of Bureau or Office or his Duly Authorized Representative', 0, 'C');

    $pdf->SetFont('times', '', 8.7);
    $pdf->SetXY(17, 119);
    $pdf->Cell(120, 4.5, 'B. To be filled by the driver', 0, 1, 'L');
    batchTripNumberedLine($pdf, 1, 'Time of departure from Office/Garage', 132, '8:00', 'a.m./p.m.');
    batchTripNumberedLine($pdf, 2, 'Time of arrival at (per No. 4 above)', 137.5, '', 'a.m./p.m.');
    batchTripNumberedLine($pdf, 3, 'Time of departure from (per No. 4)', 143, '', 'a.m./p.m.');
    batchTripNumberedLine($pdf, 4, 'Time of arrival back to Office/Garage', 148.5, '5:00', 'a.m./p.m.');
    batchTripNumberedLine($pdf, 5, 'Approximate distance traveled (to & from)', 154, '', 'miles/km');
    $pdf->SetXY(32, 159.5);
    $pdf->Cell(94, 4.5, '(gasoline issued, purchased and consumed)', 0, 1, 'L');

    batchTripFuelLine($pdf, 'a.', 'Balance in tank', 171, $balance);
    batchTripFuelLine($pdf, 'b.', 'Issued by office from stock', 176.5, $issued);
    batchTripFuelLine($pdf, 'c.', 'Add: Purchased during trip', 182, $total);
    $pdf->SetFont('times', 'B', 8.5);
    batchTripFuelLine($pdf, '', 'T O T A L', 187.5, $total);
    $pdf->SetFont('times', '', 8.7);
    batchTripFuelLine($pdf, 'd.', 'Deduct: Used during the trip (to & from)', 193, $issued);
    batchTripFuelLine($pdf, 'e.', 'Balance in tank at the end of trip', 198.5, $endBalance);

    batchTripNumberedLine($pdf, 7, 'Gear oil issued', 210, '', 'Liters');
    batchTripNumberedLine($pdf, 8, 'Lubricant oil issued', 215.5, '', 'liters');
    batchTripNumberedLine($pdf, 9, 'Grease issued', 221, '', 'liters');
    $pdf->SetXY(22, 226.5);
    $pdf->Cell(7, 4.5, '10', 0, 0, 'R');
    $pdf->SetXY(32, 226.5);
    $pdf->Cell(80, 4.5, 'Speedometer readings, if any:', 0, 0, 'L');

    foreach ([[238, 'At the beginning of the trip'], [243.5, 'At the end of the trip'], [249, 'Distance traveled (per No. 5 above)']] as [$lineY, $label]) {
        $pdf->SetXY(51, $lineY);
        $pdf->Cell(78, 4.2, $label, 0, 0, 'L');
        batchTripValueLine($pdf, 139, $lineY + 4, 33, '');
        $pdf->SetXY(174, $lineY);
        $pdf->Cell(22, 4.2, 'miles/km', 0, 0, 'L');
    }

    $pdf->SetXY(22, 263);
    $pdf->Cell(7, 4.5, '11', 0, 0, 'R');
    $pdf->SetXY(32, 263);
    $pdf->Cell(22, 4.5, 'Remarks', 0, 0, 'L');
    batchTripValueLine($pdf, 56, 267, 126, '', 'L');

    $pdf->SetFont('times', '', 8);
    $pdf->SetXY(39, 282);
    $pdf->Cell(132, 4.5, 'I hereby certify to the correctness of the above statement of record of travel.', 0, 0, 'C');
    $signatureBinary = fuelTrackerSignatureBinary($driverSignature);
    if ($signatureBinary !== '') {
        $signatureType = fuelTrackerSignatureImageType($driverSignature);
        if ($signatureType === 'JPEG') {
            $pdf->Image('@' . $signatureBinary, 135, 279, 34, 11, 'JPEG');
        } elseif (extension_loaded('gd') || extension_loaded('imagick')) {
            $pdf->Image('@' . $signatureBinary, 135, 279, 34, 11, 'PNG');
        }
    }
    $pdf->SetFont('times', 'B', 8);
    $pdf->SetXY(123, 287.5);
    $pdf->Cell(58, 4, $driver, 0, 0, 'C');
    $pdf->Line(133, 292, 171, 292);
    $pdf->SetFont('times', '', 8);
    $pdf->SetXY(139, 292.5);
    $pdf->Cell(26, 4, 'Driver', 0, 0, 'C');
    $pdf->SetXY(39, 307);
    $pdf->Cell(132, 4.5, 'I hereby certify that I used this car on official business as stated above.', 0, 0, 'C');
    $pdf->Line(123, 317, 181, 317);
    $pdf->SetXY(126, 317.5);
    $pdf->Cell(52, 4, 'Name of Passenger', 0, 0, 'C');
}

$ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', (string) ($_GET['issuance_ids'] ?? ''))),
    static fn(int $id): bool => $id > 0
)));
if ($ids === []) {
    http_response_code(422);
    exit('Select at least one gas issuance.');
}

$issuances = fuelTrackerFetchGasIssuancesByIds($conn, $ids, 'government');
if ($issuances === []) {
    http_response_code(404);
    exit('No selected gas issuances were found.');
}

$pdf = new BatchTripTicketPdf('P', 'mm', [216, 330], true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('GSO Fuel Tracker');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('Selected Driver Trip Tickets');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);

$copyCount = max(1, min(10, (int) ($_GET['copies'] ?? 1)));
$renderOptions = [
    'blank_fuel_values' => ($_GET['blank_fuel_values'] ?? '') === '1',
    'hide_signature' => ($_GET['hide_signature'] ?? '') === '1',
];

foreach ($issuances as $issuance) {
    for ($copyIndex = 0; $copyIndex < $copyCount; $copyIndex++) {
        batchTripDrawPage($pdf, $conn, $issuance, $renderOptions);
    }
}

$pdf->Output('Selected_Drivers_Trip_Tickets.pdf', 'I');
