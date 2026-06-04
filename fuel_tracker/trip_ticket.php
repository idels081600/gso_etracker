<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('coa_admin', 'text');
$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

if (!is_file($tcpdfPath)) {
    http_response_code(500);
    exit('TCPDF library not found. Please run composer install from the project root.');
}

require_once $tcpdfPath;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_signature.php';

if (!class_exists('TCPDF')) {
    http_response_code(500);
    exit('TCPDF failed to load.');
}

function tripParam(string $key, string $default = ''): string
{
    $value = isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
    return $value !== '' ? $value : $default;
}

function tripDateLabel(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('F j, Y', $timestamp);
}

function tripNumber(string $value, float $default = 0): float
{
    $normalized = str_replace(',', '', trim($value));
    return is_numeric($normalized) ? (float) $normalized : $default;
}

function tripFormatNumber(float $value): string
{
    $formatted = number_format($value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.') ?: '0';
}

function tripIssuanceOfficeBySerial(mysqli $conn, string $serialNo): string
{
    $serialNo = trim($serialNo);
    if ($serialNo === '') {
        return '';
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(NULLIF(TRIM(gi.office), ''), NULLIF(TRIM(v.office), ''), '') AS office
        FROM gas_issuances gi
        LEFT JOIN vehicles v ON v.id = gi.vehicle_id
        WHERE gi.serial_no = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('s', $serialNo);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return trim((string) ($row['office'] ?? ''));
}

class TripTicketPdf extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

function drawValueLine(TripTicketPdf $pdf, float $x, float $y, float $w, string $value = '', string $align = 'C'): void
{
    $pdf->SetXY($x, $y - 4.4);
    $pdf->Cell($w, 4.4, $value, 0, 0, $align);
    $pdf->Line($x, $y, $x + $w, $y);
}

function drawNumberedLine(
    TripTicketPdf $pdf,
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
    drawValueLine($pdf, 139, $labelY + 4, 33, $value);
    if ($unit !== '') {
        $pdf->SetXY(174, $labelY);
        $pdf->Cell(18, 4.5, $unit, 0, 0, 'L');
    }
}

function drawFuelLine(
    TripTicketPdf $pdf,
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
    drawValueLine($pdf, 139, $labelY + 4, 33, $value);
    $pdf->SetXY(174, $labelY);
    $pdf->Cell(20, 4.5, $unit, 0, 0, 'L');
}

$serialNo = tripParam('serial_no', 'TT-SAMPLE');
$issuanceOfficeName = tripIssuanceOfficeBySerial($conn, $serialNo);
$officeName = tripParam('office', $issuanceOfficeName !== '' ? $issuanceOfficeName : '(Name of Office)');
$dateLabel = tripDateLabel(tripParam('date', date('Y-m-d')));
$driver = strtoupper(tripParam('driver', 'ROLLY PILOT'));
$plateNo = strtoupper(tripParam('plate_no', 'MULTI 16 GAE 9098'));
$passenger = strtoupper(tripParam('passenger', ''));
$places = tripParam('places', '');
$purpose = tripParam('purpose', '');
$approvedBy = strtoupper(tripParam('approved_by', 'CHRIS JOHN RENER G. TORRALBA'));
$approverTitle = tripParam('approver_title', 'Chief of Bureau or Office or his Duly Authorized Representative');
$vehicle = strtoupper(tripParam('vehicle', ''));
$driverSignature = fuelTrackerFetchDriverSignature($conn, $serialNo);
$outputMode = isset($_GET['download']) && $_GET['download'] === '1' ? 'D' : 'I';

$departureGarage = tripParam('departure_garage', '8:00');
$arrivalPlace = tripParam('arrival_place', '');
$departurePlace = tripParam('departure_place', '');
$arrivalGarage = tripParam('arrival_garage', '5:00');
$distance = tripParam('distance', '');
$gasIssuedValue = tripNumber(tripParam(
    'gas_stock_issued',
    tripParam('issued_liters', tripParam('authorized_liters', tripParam('quantity', tripParam('gas_issued', '0'))))
));
$gasBalanceValue = tripNumber(tripParam('gas_balance_tank', '2'), 2);
$gasPurchasedValue = tripNumber(tripParam('gas_purchased', tripFormatNumber($gasBalanceValue + $gasIssuedValue)));
$gasTotalValue = tripNumber(tripParam('gas_total', tripFormatNumber($gasBalanceValue + $gasIssuedValue)));
$gasDeductedValue = tripNumber(tripParam('gas_deducted', tripFormatNumber($gasIssuedValue)));
$gasEndBalanceValue = tripNumber(tripParam('gas_end_balance', tripFormatNumber(max(0, $gasTotalValue - $gasDeductedValue))));
$gasBalanceTank = tripFormatNumber($gasBalanceValue);
$gasStockIssued = tripFormatNumber($gasIssuedValue);
$gasPurchased = tripFormatNumber($gasPurchasedValue);
$gasTotal = tripFormatNumber($gasTotalValue);
$gasDeducted = tripFormatNumber($gasDeductedValue);
$gasEndBalance = tripFormatNumber($gasEndBalanceValue);
$gearOil = tripParam('gear_oil', '');
$lubricantOil = tripParam('lubricant_oil', '');
$grease = tripParam('grease', '');
$speedometerStart = tripParam('speedometer_start', '');
$speedometerEnd = tripParam('speedometer_end', '');
$speedometerDistance = tripParam('speedometer_distance', '');
$remarks = tripParam('remarks', '');

$pdf = new TripTicketPdf('P', 'mm', [216, 330], true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('Driver Trip Ticket');
$pdf->SetSubject('Driver Trip Ticket');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
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
drawValueLine($pdf, 82, 34, 44, $officeName);
$pdf->SetXY(82, 35);
$pdf->Cell(44, 4, '(Name of Office)', 0, 0, 'C');
drawValueLine($pdf, 150, 34, 34, $dateLabel);
$pdf->SetXY(151, 35);
$pdf->Cell(32, 4, '(Date)', 0, 0, 'C');

$pdf->SetFont('times', 'B', 11);
$pdf->SetXY(0, 48);
$pdf->Cell(210, 6, "DRIVER'S TRIP TICKET", 0, 1, 'C');

$pdf->SetFont('times', '', 8.7);
$pdf->SetXY(17, 59);
$pdf->Cell(120, 4.5, 'A. To be filled by the Administrative Official authorizing official travel:', 0, 1, 'L');

$itemsA = [
    [1, 'Name of driver of the vehicle', $driver],
    [2, 'Government car to be used, Plate No.', trim($vehicle . ' ' . $plateNo)],
    [3, 'Name of authorized passenger', $passenger],
    [4, 'Place or places to be visited/inspected', $places],
    [5, 'Purpose', $purpose],
];

$y = 69;
foreach ($itemsA as [$number, $label, $value]) {
    $pdf->SetXY(25, $y);
    $pdf->Cell(7, 4.3, (string) $number, 0, 0, 'R');
    $pdf->SetXY(35, $y);
    $pdf->Cell(70, 4.3, $label, 0, 0, 'L');
    drawValueLine($pdf, 111, $y + 3.6, 72, (string) $value);
    $y += 5;
}

$pdf->SetFont('times', 'B', 8);
$pdf->SetXY(108, 98);
$pdf->Cell(70, 4.5, $approvedBy, 0, 1, 'C');
$pdf->Line(112, 102, 174, 102);
$pdf->SetFont('times', '', 8);
$pdf->SetXY(108, 102.5);
$pdf->MultiCell(70, 8, $approverTitle, 0, 'C');

$pdf->SetFont('times', '', 8.7);
$pdf->SetXY(17, 119);
$pdf->Cell(120, 4.5, 'B. To be filled by the driver', 0, 1, 'L');

drawNumberedLine($pdf, 1, 'Time of departure from Office/Garage', 132, $departureGarage, 'a.m./p.m.');
drawNumberedLine($pdf, 2, 'Time of arrival at (per No. 4 above)', 137.5, $arrivalPlace, 'a.m./p.m.');
drawNumberedLine($pdf, 3, 'Time of departure from (per No. 4)', 143, $departurePlace, 'a.m./p.m.');
drawNumberedLine($pdf, 4, 'Time of arrival back to Office/Garage', 148.5, $arrivalGarage, 'a.m./p.m.');
drawNumberedLine($pdf, 5, 'Approximate distance traveled (to & from)', 154, $distance, 'miles/km');
$pdf->SetXY(32, 159.5);
$pdf->Cell(94, 4.5, '(gasoline issued, purchased and consumed)', 0, 1, 'L');

drawFuelLine($pdf, 'a.', 'Balance in tank', 171, $gasBalanceTank);
drawFuelLine($pdf, 'b.', 'Issued by office from stock', 176.5, $gasStockIssued);
drawFuelLine($pdf, 'c.', 'Add: Purchased during trip', 182, $gasPurchased);
$pdf->SetFont('times', 'B', 8.5);
drawFuelLine($pdf, '', 'T O T A L', 187.5, $gasTotal);
$pdf->SetFont('times', '', 8.7);
drawFuelLine($pdf, 'd.', 'Deduct: Used during the trip (to & from)', 193, $gasDeducted);
drawFuelLine($pdf, 'e.', 'Balance in tank at the end of trip', 198.5, $gasEndBalance);

drawNumberedLine($pdf, 7, 'Gear oil issued', 210, $gearOil, 'Liters');
drawNumberedLine($pdf, 8, 'Lubricant oil issued', 215.5, $lubricantOil, 'liters');
drawNumberedLine($pdf, 9, 'Grease issued', 221, $grease, 'liters');
$pdf->SetXY(22, 226.5);
$pdf->Cell(7, 4.5, '10', 0, 0, 'R');
$pdf->SetXY(32, 226.5);
$pdf->Cell(80, 4.5, 'Speedometer readings, if any:', 0, 0, 'L');

$pdf->SetXY(51, 238);
$pdf->Cell(78, 4.2, 'At the beginning of the trip', 0, 0, 'L');
drawValueLine($pdf, 139, 242, 33, $speedometerStart);
$pdf->SetXY(174, 238);
$pdf->Cell(22, 4.2, 'miles/km', 0, 0, 'L');
$pdf->SetXY(51, 243.5);
$pdf->Cell(78, 4.2, 'At the end of the trip', 0, 0, 'L');
drawValueLine($pdf, 139, 247.5, 33, $speedometerEnd);
$pdf->SetXY(174, 243.5);
$pdf->Cell(22, 4.2, 'miles/km', 0, 0, 'L');
$pdf->SetXY(51, 249);
$pdf->Cell(78, 4.2, 'Distance traveled (per No. 5 above)', 0, 0, 'L');
drawValueLine($pdf, 139, 253, 33, $speedometerDistance);
$pdf->SetXY(174, 249);
$pdf->Cell(22, 4.2, 'miles/km', 0, 0, 'L');

$pdf->SetXY(22, 263);
$pdf->Cell(7, 4.5, '11', 0, 0, 'R');
$pdf->SetXY(32, 263);
$pdf->Cell(22, 4.5, 'Remarks', 0, 0, 'L');
drawValueLine($pdf, 56, 267, 126, $remarks, 'L');

$pdf->SetFont('times', '', 8);
$pdf->SetXY(39, 282);
$pdf->Cell(132, 4.5, 'I hereby certify to the correctness of the above statement of record of travel.', 0, 0, 'C');
$driverSignatureBinary = fuelTrackerSignatureBinary($driverSignature);
if ($driverSignatureBinary !== '') {
    $signatureType = fuelTrackerSignatureImageType($driverSignature);
    if ($signatureType === 'JPEG') {
        $pdf->Image('@' . $driverSignatureBinary, 135, 279, 34, 11, 'JPEG');
    } elseif (extension_loaded('gd') || extension_loaded('imagick')) {
        $pdf->Image('@' . $driverSignatureBinary, 135, 279, 34, 11, 'PNG');
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

$pdf->Output('Drivers_Trip_Ticket.pdf', $outputMode);
?>
