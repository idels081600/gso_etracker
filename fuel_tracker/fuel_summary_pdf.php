<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('coa_admin', 'text');
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(20, 60, 'fuel_summary_pdf', 'text');

$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

if (!is_file($tcpdfPath)) {
    http_response_code(500);
    exit('TCPDF library not found. Please run composer install from the project root.');
}

require_once $tcpdfPath;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';
require_once __DIR__ . '/fuel_budget_data.php';

if (!class_exists('TCPDF')) {
    http_response_code(500);
    exit('TCPDF failed to load.');
}

function fuelSummaryParam(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    $value = is_scalar($value) ? trim((string) $value) : $default;
    return $value !== '' ? $value : $default;
}

function fuelSummaryFloat(mixed $value): float
{
    if (is_string($value)) {
        $value = str_replace(',', '', trim($value));
    }

    return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
}

function fuelSummaryDate(string $date): string
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
}

function fuelSummaryPeriodLabel(string $startDate, string $endDate): string
{
    if ($startDate === '' && $endDate === '') {
        return 'ALL DATES';
    }

    $startDate = $startDate !== '' ? $startDate : $endDate;
    $endDate = $endDate !== '' ? $endDate : $startDate;
    $start = strtotime($startDate);
    $end = strtotime($endDate);

    if ($start === false || $end === false) {
        return strtoupper($startDate . ($endDate !== $startDate ? ' - ' . $endDate : ''));
    }

    if (date('F Y', $start) === date('F Y', $end)) {
        return strtoupper(date('F', $start) . ' ' . date('j', $start) . '-' . date('j, Y', $end));
    }

    return strtoupper(date('F j, Y', $start) . ' - ' . date('F j, Y', $end));
}

function fuelSummaryMoney(float $value): string
{
    return number_format($value, 2);
}

function fuelSummaryLiters(float $value): string
{
    return $value > 0 ? number_format($value, 2) : '';
}

function fuelSummaryFetchRows(mysqli $conn, string $office, string $startDate, string $endDate): array
{
    fuelTrackerSyncIssuanceOffices($conn);

    $conditions = ["LOWER(gi.status) = 'used'"];
    $types = '';
    $params = [];

    if ($office !== '') {
        $conditions[] = 'gi.office = ?';
        $types .= 's';
        $params[] = $office;
    }
    if ($startDate !== '') {
        $conditions[] = 'gi.issue_date >= ?';
        $types .= 's';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $conditions[] = 'gi.issue_date <= ?';
        $types .= 's';
        $params[] = $endDate;
    }

    $sql = "
        SELECT
            COALESCE(NULLIF(TRIM(gi.office), ''), NULLIF(TRIM(v.office), ''), 'Office') AS office,
            CONCAT(v.type_of_vehicle, ' ', v.plate_no) AS vehicle_label,
            gi.fuel_type,
            SUM(COALESCE(gi.authorized_liters, 0)) AS liters
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        WHERE " . implode(' AND ', $conditions) . "
        GROUP BY office, vehicle_label, gi.fuel_type
        ORDER BY office ASC, vehicle_label ASC, gi.fuel_type ASC
    ";

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

class FuelSummaryPdf extends TCPDF
{
    public function Header(): void
    {
    }

    public function Footer(): void
    {
    }
}

$office = fuelSummaryParam('office');
$startDate = fuelSummaryDate(fuelSummaryParam('start_date'));
$endDate = fuelSummaryDate(fuelSummaryParam('end_date'));
$dieselPrice = fuelSummaryFloat(fuelSummaryParam('diesel_price'));
$unleadedPrice = fuelSummaryFloat(fuelSummaryParam('unleaded_price'));
$shouldDeduct = false;
$rows = fuelSummaryFetchRows($conn, $office, $startDate, $endDate);
$periodLabel = fuelSummaryPeriodLabel($startDate, $endDate);
$outputMode = isset($_GET['download']) && $_GET['download'] === '1' ? 'D' : 'I';

$grouped = [];
foreach ($rows as $row) {
    $officeName = (string) ($row['office'] ?? 'Office');
    $vehicle = trim((string) ($row['vehicle_label'] ?? 'Vehicle'));
    $fuelType = strtolower((string) ($row['fuel_type'] ?? ''));
    $bucket = str_contains($fuelType, 'diesel') ? 'diesel' : 'unleaded';
    $liters = fuelSummaryFloat($row['liters'] ?? 0);

    $grouped[$officeName] ??= [];
    $grouped[$officeName][$vehicle] ??= ['diesel' => 0.0, 'unleaded' => 0.0];
    $grouped[$officeName][$vehicle][$bucket] += $liters;
}

$grandDieselForBudget = 0.0;
$grandUnleadedForBudget = 0.0;
foreach ($grouped as $vehicles) {
    foreach ($vehicles as $totals) {
        $grandDieselForBudget += (float) ($totals['diesel'] ?? 0);
        $grandUnleadedForBudget += (float) ($totals['unleaded'] ?? 0);
    }
}
$totalBudgetAmount = ($grandDieselForBudget * $dieselPrice) + ($grandUnleadedForBudget * $unleadedPrice);
$deductionStatus = ['deducted' => false, 'message' => 'No budget deduction requested.'];
$budgetAllocations = [];

if ($shouldDeduct) {
    $summaryGroupHash = hash('sha256', implode('|', [
        $office,
        $startDate,
        $endDate,
        number_format($dieselPrice, 2, '.', ''),
        number_format($unleadedPrice, 2, '.', ''),
        number_format($grandDieselForBudget, 2, '.', ''),
        number_format($grandUnleadedForBudget, 2, '.', ''),
    ]));

    $deductionStatus = fuelBudgetRecordAutoDeduction(
        $conn,
        $summaryGroupHash,
        $office,
        $startDate,
        $endDate,
        $dieselPrice,
        $unleadedPrice,
        $grandDieselForBudget,
        $grandUnleadedForBudget,
        $totalBudgetAmount,
        (string) ($_SESSION['username'] ?? $_SESSION['pay_name'] ?? 'coa_admin')
    );
    $budgetAllocations = $deductionStatus['allocations'] ?? [];

    if (isset($deductionStatus['shortfall']) && (float) $deductionStatus['shortfall'] > 0) {
        http_response_code(422);
        exit($deductionStatus['message'] . ' Shortfall: PHP ' . fuelSummaryMoney((float) $deductionStatus['shortfall']));
    }
}

$pdf = new FuelSummaryPdf('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('GSO Fuel Tracker');
$pdf->SetTitle('Fuel Summary Computation');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

$x = 10.0;
$y = 12.0;
$tableWidth = 190.0;
$vehicleW = 102.0;
$dieselW = 44.0;
$unleadedW = 44.0;
$rowH = 7.0;
$grandDiesel = 0.0;
$grandUnleaded = 0.0;

$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor(39, 66, 94);
$pdf->SetLineWidth(0.35);

$pdf->SetXY($x, $y);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($tableWidth, 7, 'SUMMARY COMPUTATION: (' . $periodLabel . ')', 1, 1, 'L', true);
$pdf->SetX($x);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($tableWidth, 7, strtoupper($office !== '' ? $office : 'ALL OFFICES'), 1, 1, 'L', true);
if ($shouldDeduct) {
    $pdf->SetX($x);
    $pdf->Cell($tableWidth, 7, 'BUDGET: AUTO-ALLOCATE OLDEST ACTIVE IB FIRST | ' . (string) $deductionStatus['message'], 1, 1, 'L', true);
    if (!empty($budgetAllocations)) {
        foreach ($budgetAllocations as $allocation) {
            $pdf->SetX($x);
            $line = strtoupper((string) ($allocation['ib_no'] ?? 'IB')) .
                ' - PHP ' . fuelSummaryMoney((float) ($allocation['amount'] ?? 0)) .
                ' (Diesel PHP ' . fuelSummaryMoney((float) ($allocation['diesel_amount'] ?? 0)) .
                ', Unleaded PHP ' . fuelSummaryMoney((float) ($allocation['unleaded_amount'] ?? 0)) . ')' .
                ' | Remaining: PHP ' . fuelSummaryMoney((float) ($allocation['remaining_after'] ?? 0));
            $pdf->Cell($tableWidth, 6, $line, 1, 1, 'L', true);
        }
    }
}

$pdf->SetX($x);
$pdf->Cell($vehicleW, 9, 'VEHICLE', 1, 0, 'C', true);
$pdf->Cell($dieselW, 9, 'DIESEL', 1, 0, 'C', true);
$pdf->Cell($unleadedW, 9, 'UNLEADED', 1, 1, 'C', true);

if ($grouped === []) {
    $pdf->SetX($x);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($tableWidth, 16, 'No fuel records found for this office and date range.', 1, 1, 'C', true);
} else {
    foreach ($grouped as $officeName => $vehicles) {
        $officeDiesel = 0.0;
        $officeUnleaded = 0.0;

        $pdf->SetX($x);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(15, 36, 55);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($tableWidth, $rowH, strtoupper($officeName), 1, 1, 'L', true);

        $pdf->SetTextColor(15, 36, 55);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 8);

        foreach ($vehicles as $vehicle => $totals) {
            $officeDiesel += $totals['diesel'];
            $officeUnleaded += $totals['unleaded'];

            $pdf->SetX($x);
            $pdf->Cell($vehicleW, $rowH, $vehicle, 1, 0, 'L', true);
            $pdf->Cell($dieselW, $rowH, fuelSummaryLiters($totals['diesel']), 1, 0, 'R', true);
            $pdf->Cell($unleadedW, $rowH, fuelSummaryLiters($totals['unleaded']), 1, 1, 'R', true);
        }

        $grandDiesel += $officeDiesel;
        $grandUnleaded += $officeUnleaded;

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetX($x);
        $pdf->Cell($vehicleW, $rowH, 'OFFICE TOTAL', 1, 0, 'R', true);
        $pdf->Cell($dieselW, $rowH, fuelSummaryMoney($officeDiesel), 1, 0, 'R', true);
        $pdf->Cell($unleadedW, $rowH, fuelSummaryMoney($officeUnleaded), 1, 1, 'R', true);
        $pdf->SetFont('helvetica', '', 8);
    }
}

$dieselAmount = $grandDiesel * $dieselPrice;
$unleadedAmount = $grandUnleaded * $unleadedPrice;

$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetX($x);
$pdf->Cell($vehicleW, 8, 'TOTAL LITERS', 1, 0, 'R', true);
$pdf->Cell($dieselW, 8, fuelSummaryMoney($grandDiesel), 1, 0, 'R', true);
$pdf->Cell($unleadedW, 8, fuelSummaryMoney($grandUnleaded), 1, 1, 'R', true);

$pdf->SetX($x);
$pdf->Cell($vehicleW, 8, 'PRICE / LITER', 1, 0, 'R', true);
$pdf->Cell($dieselW, 8, 'PHP ' . fuelSummaryMoney($dieselPrice), 1, 0, 'R', true);
$pdf->Cell($unleadedW, 8, 'PHP ' . fuelSummaryMoney($unleadedPrice), 1, 1, 'R', true);

$pdf->SetX($x);
$pdf->Cell($vehicleW, 8, 'AMOUNT', 1, 0, 'R', true);
$pdf->Cell($dieselW, 8, 'PHP ' . fuelSummaryMoney($dieselAmount), 1, 0, 'R', true);
$pdf->Cell($unleadedW, 8, 'PHP ' . fuelSummaryMoney($unleadedAmount), 1, 1, 'R', true);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetX($x);
$pdf->Cell($vehicleW + $dieselW, 10, 'TOTAL', 1, 0, 'C', true);
$pdf->Cell($unleadedW, 10, 'PHP ' . fuelSummaryMoney($dieselAmount + $unleadedAmount), 1, 1, 'R', true);

$pdf->Output('Fuel_Summary_Computation.pdf', $outputMode);
