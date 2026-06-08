<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'text');
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(20, 60, 'private_gas_coupon_batch', 'text');

$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) {
    http_response_code(500);
    exit('TCPDF library not found. Please run composer install from the project root.');
}

require_once $tcpdfPath;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

function privateBatchCouponDate(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp === false ? $date : strtoupper(date('M d, Y', $timestamp));
}

class PrivateBatchGasCouponPdf extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

function privateBatchDrawCoupon(PrivateBatchGasCouponPdf $pdf, array $coupon): void
{
    $serialNo = (string) ($coupon['serial_no'] ?? '');
    $vehicle = trim((string) ($coupon['vehicle_type'] ?? 'Private Vehicle'));
    $plateNo = trim((string) ($coupon['plate_no'] ?? ''));
    $fuelType = strtoupper((string) ($coupon['fuel_type'] ?? 'UNLEADED'));
    $liters = number_format((float) ($coupon['authorized_liters'] ?? 0), 2);
    $unit = strtoupper((string) ($coupon['unit'] ?? 'L'));
    $issueDate = privateBatchCouponDate((string) ($coupon['issue_date'] ?? ''));
    $expiryDate = privateBatchCouponDate((string) ($coupon['expiry_date'] ?? ''));

    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(0, 0, 140, 80, 'F');
    $pdf->SetDrawColor(25, 35, 50);
    $pdf->SetLineWidth(0.45);
    $pdf->Rect(0, 0, 140, 80);

    $pdf->SetFillColor(3, 154, 0);
    $pdf->Rect(0.25, 0.25, 139.5, 17, 'F');
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

    $qrStyle = [
        'border' => 0,
        'vpadding' => 0,
        'hpadding' => 0,
        'fgcolor' => [0, 0, 0],
        'bgcolor' => false,
        'module_width' => 1,
        'module_height' => 1,
    ];
    $pdf->SetTextColor(25, 35, 50);
    $pdf->write2DBarcode($serialNo, 'QRCODE,H', 102, 38, 22, 22, $qrStyle, 'N');
    $pdf->SetFont('helvetica', '', 5.8);
    $pdf->SetXY(96, 61);
    $pdf->Cell(34, 3.5, 'Scan in Gas Checker', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 6.5);
    $pdf->SetXY(9, 67);
    $pdf->Cell(40, 4, 'ISSUED: ' . $issueDate, 0, 0, 'L');
    $pdf->SetXY(50, 67);
    $pdf->Cell(40, 4, 'EXPIRES: ' . $expiryDate, 0, 0, 'L');
}

$ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', (string) ($_GET['issuance_ids'] ?? ''))),
    static fn(int $id): bool => $id > 0
)));
if ($ids === []) {
    http_response_code(422);
    exit('Select at least one private gas issuance.');
}

$issuances = array_values(array_filter(
    fuelTrackerFetchGasIssuancesByIds($conn, $ids, 'private'),
    static fn(array $issuance): bool => in_array(
        strtolower((string) ($issuance['status'] ?? '')),
        ['approved', 'valid', 'used'],
        true
    )
));
if ($issuances === []) {
    http_response_code(409);
    exit('Selected private gas issuances must be approved before printing.');
}

$pdf = new PrivateBatchGasCouponPdf('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('GSO Fuel Tracker');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('Selected Private Gas Coupons');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);

$pageWidth = 210.0;
$pageHeight = 297.0;
$couponWidth = 101.6;
$couponHeight = 50.8;
$columns = 2;
$rowsPerPage = 5;
$couponsPerPage = $columns * $rowsPerPage;
$columnGap = 0.0;
$rowGap = 0.0;
$gridWidth = ($couponWidth * $columns) + ($columnGap * ($columns - 1));
$gridHeight = ($couponHeight * $rowsPerPage) + ($rowGap * ($rowsPerPage - 1));
$startX = ($pageWidth - $gridWidth) / 2;
$startY = ($pageHeight - $gridHeight) / 2;

foreach ($issuances as $index => $issuance) {
    $pagePosition = $index % $couponsPerPage;
    if ($pagePosition === 0) {
        $pdf->AddPage();
    }

    $templateId = $pdf->startTemplate(140, 80);
    privateBatchDrawCoupon($pdf, $issuance);
    $pdf->endTemplate();

    $column = $pagePosition % $columns;
    $row = intdiv($pagePosition, $columns);
    $x = $startX + ($column * ($couponWidth + $columnGap));
    $y = $startY + ($row * ($couponHeight + $rowGap));
    $pdf->printTemplate($templateId, $x, $y, $couponWidth, $couponHeight);
}

$pdf->Output('Selected_Private_Gas_Coupons.pdf', 'I');
