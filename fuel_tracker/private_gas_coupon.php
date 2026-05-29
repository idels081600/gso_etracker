<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'text');
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'private_gas_coupon', 'text');

$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) {
    http_response_code(500);
    exit('TCPDF library not found. Please run composer install from the project root.');
}

require_once $tcpdfPath;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

if (!class_exists('TCPDF')) {
    http_response_code(500);
    exit('TCPDF failed to load.');
}

fuelTrackerEnsureScopeColumns($conn);

function privateCouponParam(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
}

function privateCouponDate(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp === false ? $date : strtoupper(date('M d, Y', $timestamp));
}

function privateCouponData(mysqli $conn, string $reference): array
{
    if ($reference === '') {
        http_response_code(422);
        exit('Private gas issuance serial number is required.');
    }

    $stmt = $conn->prepare("
        SELECT
            gi.id,
            gi.serial_no,
            gi.fuel_type,
            gi.authorized_liters,
            gi.unit,
            gi.issue_date,
            gi.expiry_date,
            gi.status,
            v.plate_no,
            v.type_of_vehicle
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        WHERE (gi.serial_no = ? OR CAST(gi.id AS CHAR) = ?)
            AND LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) = 'private'
        LIMIT 1
    ");
    $stmt->bind_param('ss', $reference, $reference);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        exit('Private gas issuance was not found.');
    }

    return $row;
}

class PrivateGasCouponPdf extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

$reference = privateCouponParam('serial_no');
$coupon = privateCouponData($conn, $reference);
$status = strtolower((string) ($coupon['status'] ?? ''));
if (!in_array($status, ['approved', 'valid', 'used'], true)) {
    http_response_code(409);
    exit('Private gas issuance must be approved before printing a coupon.');
}

$serialNo = (string) ($coupon['serial_no'] ?? '');
$vehicle = trim((string) ($coupon['type_of_vehicle'] ?? 'Private Vehicle'));
$plateNo = trim((string) ($coupon['plate_no'] ?? ''));
$fuelType = strtoupper((string) ($coupon['fuel_type'] ?? 'UNLEADED'));
$liters = number_format((float) ($coupon['authorized_liters'] ?? 0), 2);
$unit = strtoupper((string) ($coupon['unit'] ?? 'L'));
$issueDate = privateCouponDate((string) ($coupon['issue_date'] ?? ''));
$expiryDate = privateCouponDate((string) ($coupon['expiry_date'] ?? ''));
$outputMode = isset($_GET['download']) && $_GET['download'] === '1' ? 'D' : 'I';

$pdf = new PrivateGasCouponPdf('L', 'mm', [140, 80], true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('Gas Slip');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 140, 80, 'F');
$pdf->SetDrawColor(25, 35, 50);
$pdf->SetLineWidth(0.45);
$pdf->RoundedRect(5, 5, 130, 70, 2.5, '1111');

$pdf->SetFillColor(3, 154, 0);
$pdf->RoundedRect(5.25, 5.25, 129.5, 12, 2.2, '1001', 'F');
$pdf->Rect(5.25, 13, 129.5, 4.25, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetXY(9, 7.2);
$pdf->Cell(82, 7, 'GAS SLIP', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 7.5);
$pdf->SetXY(92, 7.8);
$pdf->Cell(39, 6, $serialNo, 0, 0, 'R');

$pdf->SetTextColor(25, 35, 50);
$pdf->SetFont('helvetica', '', 6.7);
$pdf->SetXY(9, 21);
$pdf->Cell(18, 4, 'VEHICLE', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 8.2);
$pdf->SetXY(28, 20.5);
$pdf->MultiCell(62, 5, strtoupper($vehicle), 0, 'L', false, 1);

$pdf->SetFont('helvetica', '', 6.7);
$pdf->SetXY(9, 30);
$pdf->Cell(18, 4, 'PLATE', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 8.2);
$pdf->SetXY(28, 29.5);
$pdf->Cell(62, 5, strtoupper($plateNo), 0, 1, 'L');

$pdf->SetDrawColor(210, 216, 225);
$pdf->Line(94, 20, 94, 68);

$pdf->SetTextColor(3, 111, 0);
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetXY(96, 20);
$pdf->Cell(34, 10, $liters, 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY(96, 31);
$pdf->Cell(34, 5, $unit . ' ' . $fuelType, 0, 1, 'C');

$pdf->SetTextColor(25, 35, 50);
$qrStyle = [
    'border' => 0,
    'vpadding' => 0,
    'hpadding' => 0,
    'fgcolor' => [0, 0, 0],
    'bgcolor' => false,
    'module_width' => 1,
    'module_height' => 1,
];
$pdf->write2DBarcode($serialNo, 'QRCODE,H', 102, 38, 22, 22, $qrStyle, 'N');
$pdf->SetFont('helvetica', '', 5.8);
$pdf->SetXY(96, 61);
$pdf->Cell(34, 3.5, 'Scan in Gas Checker', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 6.5);
$pdf->SetXY(9, 67);
$pdf->Cell(40, 4, 'ISSUED: ' . $issueDate, 0, 0, 'L');
$pdf->SetXY(50, 67);
$pdf->Cell(40, 4, 'EXPIRES: ' . $expiryDate, 0, 0, 'L');

$filename = 'Private_Gas_Coupon_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $serialNo) . '.pdf';
$pdf->Output($filename, $outputMode);
