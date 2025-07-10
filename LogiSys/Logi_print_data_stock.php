<?php
require_once 'logi_display_data.php'; // Include database connection file
require('fpdf/fpdf.php'); // Include FPDF library

// Check if statuses are provided
if (!isset($_POST['statuses']) || !is_array($_POST['statuses'])) {
    die('No statuses provided.');
}

$selectedStatuses = $_POST['statuses'];

// Create new PDF document in landscape, A4
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// Modern green accent bar at the top
$pdf->SetFillColor(46, 204, 113); // Green
$pdf->Rect(0, 0, 297, 12, 'F'); // Full width for A4 landscape

// Title
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(46, 204, 113); // Green
$pdf->Cell(0, 20, 'Inventory Report', 0, 1, 'C');
$pdf->Ln(2);

// Table header styling
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(39, 174, 96); // Darker green
$pdf->SetTextColor(255, 255, 255); // White

// Column widths for landscape
$w = array(40, 40, 100, 40, 50);
$headers = array('Item No.', 'Rack No.', 'Name', 'Balance', 'Status');
for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($w[$i], 12, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Fetch all items ordered by rack number only
$query = "SELECT item_no, rack_no, item_name, current_balance FROM inventory_items ORDER BY rack_no ASC";
$result = mysqli_query($conn, $query);

// Table body styling
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(34, 49, 63); // Dark gray for text
$fill = false;
$rowColor1 = array(236, 253, 245); // Light green
$rowColor2 = array(255, 255, 255); // White

while ($row = mysqli_fetch_assoc($result)) {
    $balance = (int)$row['current_balance'];
    // Calculate status
    if ($balance == 0) {
        $status = 'Out of Stock';
    } else if ($balance <= 10) {
        $status = 'Low Stock';
    } else {
        $status = 'Available';
    }
    // Only include if status is selected
    if (!in_array($status, $selectedStatuses)) continue;

    // Set row fill color
    if ($fill) {
        $pdf->SetFillColor($rowColor1[0], $rowColor1[1], $rowColor1[2]);
    } else {
        $pdf->SetFillColor($rowColor2[0], $rowColor2[1], $rowColor2[2]);
    }

    $pdf->Cell($w[0], 10, $row['item_no'], 1, 0, 'C', true);
    $pdf->Cell($w[1], 10, $row['rack_no'], 1, 0, 'C', true);
    $pdf->Cell($w[2], 10, $row['item_name'], 1, 0, 'L', true);
    $pdf->Cell($w[3], 10, $row['current_balance'], 1, 0, 'C', true);
    // Status cell with color
    if ($status == 'Available') {
        $pdf->SetTextColor(39, 174, 96); // Green
    } else if ($status == 'Low Stock') {
        $pdf->SetTextColor(241, 196, 15); // Yellow
    } else {
        $pdf->SetTextColor(231, 76, 60); // Red
    }
    $pdf->Cell($w[4], 10, $status, 1, 0, 'C', true);
    $pdf->SetTextColor(34, 49, 63); // Reset text color
    $pdf->Ln();
    $fill = !$fill;
}

// Set headers for PDF preview in browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Inventory_Report.pdf"');

// Output PDF for inline preview in browser
$pdf->Output('I', 'Inventory_Report.pdf');
?>
