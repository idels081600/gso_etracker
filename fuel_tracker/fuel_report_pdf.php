<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole(['fuel_admin', 'coa_admin'], 'text');
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(12, 60, 'fuel_report_pdf', 'text');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fuel_report_data.php';
require_once dirname(__DIR__) . '/fpdf/fpdf.php';

try {
    $period = fuelReportResolvePeriod($_GET);
    $includePrivate = isset($_GET['include_private']) && (string) $_GET['include_private'] === '1';
    $rows = fuelReportFetchVehicleConsumption($conn, $period, $includePrivate);
    $report = fuelReportSummarize($rows);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
    exit;
} catch (Throwable $e) {
    error_log('Fuel report PDF error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'The report could not be generated.';
    exit;
}

final class FuelConsumptionPdf extends FPDF
{
    public string $reportTitle = '';

    public function Header(): void
    {
        $this->SetTextColor(23, 50, 77);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 8, 'Fuel Consumption Report', 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(94, 107, 120);
        $this->Cell(0, 6, $this->reportTitle, 0, 1, 'L');
        $this->Ln(3);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(220, 227, 232);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(94, 107, 120);
        $this->Cell(0, 8, 'Fuel Tracker | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

function fuelReportPdfCellText(string $value): string
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
}

function fuelReportPdfNumber(float $value): string
{
    return number_format($value, 2);
}

$scopeLabel = $includePrivate ? 'Government and Other Cars' : 'Government only; private/Other Cars excluded';
$pdf = new FuelConsumptionPdf('L', 'mm', 'A4');
$pdf->reportTitle = fuelReportPdfCellText($period['label'] . ' | ' . $period['start_date'] . ' to ' . $period['end_date'] . ' | ' . $scopeLabel);
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$totals = $report['totals'];
$pdf->SetFillColor(245, 248, 250);
$pdf->SetDrawColor(220, 227, 232);
$pdf->SetTextColor(29, 39, 51);
$pdf->SetFont('Arial', 'B', 9);
$summary = sprintf(
    'Offices: %d    Vehicles: %d    Fuelings: %d    Diesel: %s L    Unleaded: %s L    Total issued: %s L',
    (int) $totals['offices'],
    (int) $totals['vehicles'],
    (int) $totals['fuelings'],
    fuelReportPdfNumber((float) $totals['diesel_liters']),
    fuelReportPdfNumber((float) $totals['unleaded_liters']),
    fuelReportPdfNumber((float) $totals['total_liters'])
);
$pdf->Cell(0, 10, $summary, 1, 1, 'C', true);
$pdf->Ln(4);

$widths = [32, 60, 20, 32, 34, 34, 28];
$headers = ['Plate / ID', 'Vehicle type', 'Fuelings', 'Diesel issued L', 'Unleaded issued L', 'Total issued L', 'Latest odo'];

foreach ($report['offices'] as $office) {
    if ($pdf->GetY() > 170) {
        $pdf->AddPage();
    }

    $pdf->SetFillColor(234, 242, 251);
    $pdf->SetTextColor(23, 50, 77);
    $pdf->SetFont('Arial', 'B', 10);
    $officeHeading = sprintf('%s - %d vehicles, %s issued liters', $office['office'], $office['vehicles'], fuelReportPdfNumber((float) $office['total_liters']));
    $pdf->Cell(array_sum($widths), 8, fuelReportPdfCellText($officeHeading), 1, 1, 'L', true);

    $pdf->SetFillColor(23, 50, 77);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8);
    foreach ($headers as $index => $header) {
        $pdf->Cell($widths[$index], 7, $header, 1, $index === count($headers) - 1 ? 1 : 0, $index >= 2 ? 'R' : 'L', true);
    }

    $pdf->SetTextColor(29, 39, 51);
    $pdf->SetFont('Arial', '', 8);
    foreach ($office['rows'] as $row) {
        if ($pdf->GetY() > 188) {
            $pdf->AddPage();
            $pdf->SetFillColor(23, 50, 77);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 8);
            foreach ($headers as $index => $header) {
                $pdf->Cell($widths[$index], 7, $header, 1, $index === count($headers) - 1 ? 1 : 0, $index >= 2 ? 'R' : 'L', true);
            }
            $pdf->SetTextColor(29, 39, 51);
            $pdf->SetFont('Arial', '', 8);
        }
        $values = [
            fuelReportPdfCellText((string) $row['plate_no']),
            fuelReportPdfCellText((string) $row['vehicle_type']),
            number_format((int) $row['fueling_events']),
            fuelReportPdfNumber((float) $row['diesel_issued_liters']),
            fuelReportPdfNumber((float) $row['unleaded_issued_liters']),
            fuelReportPdfNumber((float) $row['total_issued_liters']),
            $row['latest_odometer'] !== null ? number_format((float) $row['latest_odometer'], 1) . ' km' : '-',
        ];
        foreach ($values as $index => $value) {
            $pdf->Cell($widths[$index], 6, $value, 1, $index === count($values) - 1 ? 1 : 0, $index >= 2 ? 'R' : 'L');
        }
    }

    $pdf->SetFillColor(245, 248, 250);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($widths[0] + $widths[1], 7, 'Office subtotal', 1, 0, 'L', true);
    $pdf->Cell($widths[2], 7, number_format((int) $office['fuelings']), 1, 0, 'R', true);
    $pdf->Cell($widths[3], 7, fuelReportPdfNumber((float) $office['diesel_liters']), 1, 0, 'R', true);
    $pdf->Cell($widths[4], 7, fuelReportPdfNumber((float) $office['unleaded_liters']), 1, 0, 'R', true);
    $pdf->Cell($widths[5], 7, fuelReportPdfNumber((float) $office['total_liters']), 1, 0, 'R', true);
    $pdf->Cell($widths[6], 7, '', 1, 1, 'R', true);
    $pdf->Ln(4);
}

if (empty($report['offices'])) {
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(94, 107, 120);
    $pdf->Cell(0, 20, 'No used fuel records were found for the selected period.', 0, 1, 'C');
}

$filename = sprintf('fuel_consumption_%s_%s_to_%s.pdf', $period['type'], $period['start_date'], $period['end_date']);
$pdf->Output('I', $filename);
