<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole(['print_admin', 'fuel_admin'], 'text');
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

function withdrawalParam(string $key, string $default): string
{
    $value = isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
    return $value !== '' ? $value : $default;
}

function withdrawalDateLabel(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return strtoupper($date);
    }

    return strtoupper(date('F j, Y', $timestamp));
}

function withdrawalIssuanceData(mysqli $conn, string $gasIssuanceRef): array
{
    $gasIssuanceRef = trim($gasIssuanceRef);
    if ($gasIssuanceRef === '') {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT
            gi.serial_no,
            gi.issue_date,
            gi.driver_name,
            gi.office AS issuance_office,
            gi.fuel_type,
            gi.authorized_liters,
            gi.actual_liters_fueled,
            gi.unit,
            v.plate_no,
            v.type_of_vehicle,
            v.office AS vehicle_office,
            v.fuel_type AS vehicle_fuel_type
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        WHERE gi.serial_no = ?
           OR CAST(gi.id AS CHAR) = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $gasIssuanceRef, $gasIssuanceRef);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : [];
}

class WithdrawalForm extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

function drawWithdrawalCopy(
    WithdrawalForm $pdf,
    float $topY,
    string $serialNo,
    string $dateLabel,
    string $quantity,
    string $unit,
    string $description,
    string $vehicle,
    string $plateNo,
    string $purpose,
    string $driver,
    string $approvedBy,
    string $driverSignature
): void {
    $tableX = 16;
    $tableY = $topY + 20;
    $widths = [10, 17, 12, 53, 39, 25, 16];
    $headers = ['ITEM', 'QUANTITY', 'UNIT', 'DESCRIPTION', 'VEHICLE', 'PLATE #', 'OFFICE'];

    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.28);

    $qrStyle = [
        'border' => 0,
        'vpadding' => 0,
        'hpadding' => 0,
        'fgcolor' => [0, 0, 0],
        'bgcolor' => false,
        'module_width' => 1,
        'module_height' => 1,
    ];
    $pdf->write2DBarcode($serialNo, 'QRCODE,H', 174, $topY - 20, 20, 20, $qrStyle, 'N');

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(0, $topY);
    $pdf->Cell(210, 7, 'Withdrawal of Fuels and Lubricants', 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetXY(126, $topY + 13);
    $pdf->Cell(20, 5, 'Date:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 7.5);
    $pdf->SetXY(148, $topY + 13);
    $pdf->Cell(39, 5, $dateLabel, 0, 0, 'C');
    $pdf->Line(148, $topY + 18, 187, $topY + 18);

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 6.5);
    $pdf->SetXY($tableX, $tableY);
    foreach ($headers as $index => $header) {
        $pdf->Cell($widths[$index], 8, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('helvetica', 'B', 7.4);
    $rowY = $pdf->GetY();
    $rowHeight = 14;
    $rowValues = ['1', $quantity, $unit, $description, $vehicle, $plateNo, $purpose];
    $cellX = $tableX;
    foreach ($rowValues as $index => $value) {
        $pdf->MultiCell($widths[$index], $rowHeight, $value, 1, 'C', false, 0, $cellX, $rowY, true, 0, false, true, $rowHeight, 'M');
        $cellX += $widths[$index];
    }
    $pdf->SetXY($tableX, $rowY + $rowHeight);

    for ($row = 0; $row < 2; $row++) {
        $pdf->SetX($tableX);
        foreach ($widths as $width) {
            $pdf->Cell($width, 8, '', 1, 0, 'C');
        }
        $pdf->Ln();
    }

    $requestY = $pdf->GetY() + 2;
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY($tableX, $requestY);
    $pdf->Cell(50, 5, 'Requested by:', 0, 1, 'L');

    $driverLineY = $requestY + 17;
    $signatureBinary = fuelTrackerSignatureBinary($driverSignature);
    if ($signatureBinary !== '') {
        $pdf->Image('@' . $signatureBinary, $tableX + 22, $driverLineY - 16, 42, 12, 'PNG');
    }
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetXY($tableX + 15, $driverLineY - 5);
    $pdf->Cell(55, 6, $driver, 0, 0, 'C');
    $pdf->Line($tableX + 5, $driverLineY, $tableX + 80, $driverLineY);
    $pdf->SetFont('helvetica', '', 6.5);
    $pdf->SetXY($tableX + 15, $driverLineY + 1);
    $pdf->Cell(55, 5, 'Name of Driver', 0, 0, 'C');

    $approvedX = 109;
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY($approvedX, $requestY + 14);
    $pdf->Cell(35, 5, 'Approved by:', 0, 1, 'L');
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetXY($approvedX + 12, $requestY + 20);
    $pdf->Cell(60, 6, $approvedBy, 0, 1, 'C');

    $checkerY = $requestY + 47;
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY($tableX, $checkerY);
    $pdf->Cell(22, 5, 'Checker:', 0, 0, 'L');
    $pdf->Line($tableX + 24, $checkerY + 4, $tableX + 62, $checkerY + 4);
}

$withdrawalDate = withdrawalParam('date', date('Y-m-d'));
$withdrawalQuantity = withdrawalParam('quantity', '20');
$withdrawalUnit = strtoupper(withdrawalParam('unit', 'L'));
$withdrawalDescription = strtoupper(withdrawalParam('description', 'UNLEADED'));
$withdrawalVehicle = strtoupper(withdrawalParam('vehicle', ''));
$withdrawalPlate = strtoupper(withdrawalParam('plate_no', 'MULTI 16 GAE 9098'));
$withdrawalPurpose = strtoupper(withdrawalParam('purpose', 'OFFICE'));
$withdrawalDriver = strtoupper(withdrawalParam('driver', 'ROLLY PILOT'));
$approvedBy = strtoupper(withdrawalParam('approved_by', 'CHRIS JOHN RENER G. TORRALBA'));
$serialNo = withdrawalParam('serial_no', 'FI-SAMPLE');
$issuanceData = withdrawalIssuanceData($conn, $serialNo);
if ($issuanceData !== []) {
    $actualOffice = trim((string) ($issuanceData['vehicle_office'] ?? ''));
    if ($actualOffice === '') {
        $actualOffice = trim((string) ($issuanceData['issuance_office'] ?? ''));
    }
    if ($actualOffice !== '') {
        $withdrawalPurpose = strtoupper($actualOffice);
    }
    $withdrawalDate = withdrawalParam('date', (string) ($issuanceData['issue_date'] ?? $withdrawalDate));
    $withdrawalQuantity = withdrawalParam('quantity', (string) ($issuanceData['actual_liters_fueled'] ?? $issuanceData['authorized_liters'] ?? $withdrawalQuantity));
    $withdrawalUnit = strtoupper(withdrawalParam('unit', (string) ($issuanceData['unit'] ?? $withdrawalUnit)));
    $fuelType = trim((string) ($issuanceData['fuel_type'] ?? ''));
    if ($fuelType === '') {
        $fuelType = trim((string) ($issuanceData['vehicle_fuel_type'] ?? ''));
    }
    if ($fuelType !== '') {
        $withdrawalDescription = strtoupper($fuelType);
    }
    $withdrawalVehicle = strtoupper(withdrawalParam('vehicle', (string) ($issuanceData['type_of_vehicle'] ?? $withdrawalVehicle)));
    $withdrawalPlate = strtoupper(withdrawalParam('plate_no', (string) ($issuanceData['plate_no'] ?? $withdrawalPlate)));
    $withdrawalDriver = strtoupper(withdrawalParam('driver', (string) ($issuanceData['driver_name'] ?? $withdrawalDriver)));
}
$driverSignature = fuelTrackerFetchDriverSignature($conn, $serialNo);
if (strtoupper(trim($withdrawalDriver)) === 'TBD') {
    $withdrawalDriver = '';
    $driverSignature = '';
}
$outputMode = isset($_GET['download']) && $_GET['download'] === '1' ? 'D' : 'I';

$pdf = new WithdrawalForm('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('Withdrawal of Fuels and Lubricants');
$pdf->SetSubject('Fuel and Lubricants Withdrawal Form');
$pdf->SetDefaultMonospacedFont('courier');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

$dateLabel = withdrawalDateLabel($withdrawalDate);
drawWithdrawalCopy(
    $pdf,
    40,
    $serialNo,
    $dateLabel,
    $withdrawalQuantity,
    $withdrawalUnit,
    $withdrawalDescription,
    $withdrawalVehicle,
    $withdrawalPlate,
    $withdrawalPurpose,
    $withdrawalDriver,
    $approvedBy,
    $driverSignature
);
drawWithdrawalCopy(
    $pdf,
    170,
    $serialNo,
    $dateLabel,
    $withdrawalQuantity,
    $withdrawalUnit,
    $withdrawalDescription,
    $withdrawalVehicle,
    $withdrawalPlate,
    $withdrawalPurpose,
    $withdrawalDriver,
    $approvedBy,
    $driverSignature
);

$pdf->Output('Withdrawal_Form.pdf', $outputMode);
?>
