<?php
require('fpdf/fpdf.php'); // Ensure the FPDF library is correctly included

class OrderSlip extends FPDF
{
    function __construct()
    {
        parent::__construct('P', 'in', array(8.5, 13)); // Set paper size to 8.5x13 inches
    }

    function Header()
    {
        $this->Image('logo.png', 0.5, 0.3, 1); // Adjusted logo size and position

        $this->SetFont('Arial', 'B', 14); // Reduced font size
        $this->Cell(0, 0.3, 'ORDER SLIP', 0, 1, 'C');
        $this->SetFont('Arial', '', 8); // Smaller font for header text
        $this->Cell(0, 0.2, 'Republic of the Philippines', 0, 1, 'C');
        $this->Cell(0, 0.2, 'City Government of Tagbilaran', 0, 1, 'C');

        $this->Ln(0.2);
    }

    function Footer()
    {
        $this->SetY(-0.5);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 0.3, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

$pdf = new OrderSlip();
$pdf->AddPage();

// Order details header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(2, 0.3, 'Date: ' . date('F d, Y', strtotime($data['date'])), 0, 0);
$pdf->Cell(4, 0.3, 'Activity: ' . $data['activity'], 0, 1);
$pdf->Cell(0, 0.3, 'P.O/ITB No.: ' . $data['poItb'], 0, 1);
$pdf->Ln(0.1);

// Orders section
$grandTotal = 0;
foreach ($data['orders'] as $order) {
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 0.25, 'Category: ' . str_replace(' Menu', '', $order['modalTitle']), 0, 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 0.25, 'Selected Package: ' . $order['packageTitle'], 1, 1, 'L', true);

    // Package details
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 0.2, 'Package Includes:', 0, 1);
    foreach ($order['catDetails'] as $detail) {
        $cleanDetail = str_replace('•', '-', $detail);
        $pdf->Cell(0.4, 0.2, '', 0, 0);
        $pdf->Cell(0, 0.2, '- ' . trim($cleanDetail), 0, 1);
    }

    if (!empty($order['selections'])) {
        $pdf->Cell(0, 0.2, 'Selected Items:', 0, 1);
        foreach ($order['selections'] as $selection) {
            $cleanSelection = str_replace('•', '-', $selection);
            $pdf->Cell(0.4, 0.2, '', 0, 0);
            $pdf->Cell(0, 0.2, '- ' . trim($cleanSelection), 0, 1);
        }
    }

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 0.2, 'Number of Pax: ' . $order['pax'], 0, 1);

    preg_match('/(\d+)/', $order['packageTitle'], $matches);
    $price = isset($matches[1]) ? floatval($matches[1]) : 0;
    $total = $price * intval($order['pax']);
    $grandTotal += $total;

    $pdf->Cell(0, 0.2, 'Amount per Pax: Php ' . number_format($price, 2), 0, 1);
    $pdf->Cell(0, 0.2, 'Total: Php ' . number_format($total, 2), 0, 1);
    $pdf->Ln(0.1);
}

// Grand total section 
$grandTotalText = 'Grand Total:';
$grandTotalNumber = 'Php ' . number_format($grandTotal, 2);
$textWidth = $pdf->GetStringWidth($grandTotalText) + 0.3;
$numberWidth = $pdf->GetStringWidth($grandTotalNumber) + 0.3;

$pdf->Cell(5.6, 0.3, '', 0, 0);
$pdf->Cell($textWidth, 0.3, $grandTotalText, 1, 0, 'L', true);
$pdf->Cell($numberWidth, 0.3, $grandTotalNumber, 1, 1, 'R', true);
$pdf->Ln(0.3);

// Signature lines
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(4, 0.2, 'Ordered by: _________________', 0, 0, 'C');
$pdf->SetXY($pdf->GetX(), $pdf->GetY());
$pdf->MultiCell(4, 0.2, "Approved by:\nCHRIS JOHN RENER TORRALBA\n", 0, 'C');

// Output the PDF as a download
$pdf->Output('I', 'OrderSlip.pdf');
exit;