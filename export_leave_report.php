<?php
require_once "fpdf/fpdf.php";
require_once 'leave_db.php'; // Include your database connection file

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255); // White text
        $this->SetFillColor(33, 37, 41); // Bootstrap dark table header background
        $this->Cell(0, 10, 'Leave Report (SPL, FPL, CTO)', 0, 1, 'C', true);
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Create instance of the PDF class
$pdf = new PDF();
$pdf->AddPage();

// Table 1: SPL and FPL Leaves
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(255); // White text
$pdf->SetFillColor(33, 37, 41); // Dark background
$pdf->Cell(0, 10, 'SPL and FPL Leaves', 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(255); // White text
$pdf->SetFillColor(33, 37, 41); // Table header
$pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Title', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Name', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Date', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0); // Black text
$fill = false;

// Fetch data for SPL and FPL
$sql_leave_reg = "SELECT id, title, name, dates FROM leave_reg WHERE title IN ('SPL', 'FPL')";
$result_leave_reg = $conn->query($sql_leave_reg);

if ($result_leave_reg->num_rows > 0) {
    while ($row = $result_leave_reg->fetch_assoc()) {
        $pdf->SetFillColor(240, 240, 240); // Alternate row color
        $pdf->Cell(20, 10, $row['id'], 1, 0, 'C', $fill);
        $pdf->Cell(50, 10, $row['title'], 1, 0, 'C', $fill);
        $pdf->Cell(60, 10, $row['name'], 1, 0, 'C', $fill);
        $pdf->Cell(60, 10, $row['dates'], 1, 1, 'C', $fill);
        $fill = !$fill; // Toggle fill
    }
} else {
    $pdf->SetTextColor(255, 0, 0); // Red text for no records
    $pdf->Cell(0, 10, 'No SPL or FPL records found.', 1, 1, 'C');
}

// Add spacing before the next table
$pdf->Ln(10);

// Table 2: CTO Leaves
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(255); // White text
$pdf->SetFillColor(33, 37, 41); // Dark background
$pdf->Cell(0, 10, 'CTO Leaves', 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(255); // White text
$pdf->SetFillColor(33, 37, 41); // Table header
$pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Title', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Name', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Date', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0); // Black text
$fill = false;

// Fetch data for CTO
$sql_cto = "SELECT id, 'CTO' AS title, name, dates FROM cto";
$result_cto = $conn->query($sql_cto);

if ($result_cto->num_rows > 0) {
    while ($row = $result_cto->fetch_assoc()) {
        $pdf->SetFillColor(240, 240, 240); // Alternate row color
        $pdf->Cell(20, 10, $row['id'], 1, 0, 'C', $fill);
        $pdf->Cell(50, 10, $row['title'], 1, 0, 'C', $fill);
        $pdf->Cell(60, 10, $row['name'], 1, 0, 'C', $fill);
        $pdf->Cell(60, 10, $row['dates'], 1, 1, 'C', $fill);
        $fill = !$fill; // Toggle fill
    }
} else {
    $pdf->SetTextColor(255, 0, 0); // Red text for no records
    $pdf->Cell(0, 10, 'No CTO records found.', 1, 1, 'C');
}

// Output the PDF
$pdf->Output('I', 'Leave_Report.pdf'); // Open the file in the browser
