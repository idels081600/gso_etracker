<?php
require('../fpdf/fpdf.php');
require_once '../db_asset.php'; // Ensure this path is correct for your database connection

// Check if selected_ids are provided and not empty
if (!isset($_POST['selected_ids']) || empty($_POST['selected_ids'])) {
    die('No records selected for export.');
}

// Decode the JSON string into a PHP array
$selectedIdsJson = $_POST['selected_ids'];
$selectedIds = json_decode($selectedIdsJson, true);

// Validate the decoded array
if (!is_array($selectedIds) || empty($selectedIds)) {
    die('Invalid or no records selected.');
}

// Ensure all IDs are integers for security and implode them for the SQL query
$selectedIds = array_map('intval', $selectedIds);
$ids = implode(',', $selectedIds);

// Custom PDF class extending FPDF for header and footer
class PDF extends FPDF {
    // Page header
    function Header() {
        $this->SetFont('Arial', 'B', 16); // Larger, bold font for the main title
        $this->SetTextColor(50, 50, 50); // Darker grey for title
        $this->Cell(0, 10, 'Fuel Records Report', 0, 1, 'C'); // Main report title
        $this->Ln(8); // Add more vertical space after the title
    }

    // Page footer
    function Footer() {
        $this->SetY(-15); // Position at 1.5 cm from bottom
        $this->SetFont('Arial', 'I', 8); // Italic, smaller font for page number
        $this->SetTextColor(150, 150, 150); // Lighter grey for footer text
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C'); // Page number
    }
}

// Create new PDF object
$pdf = new PDF();
$pdf->AddPage('P', 'A4'); // Add a page in Portrait A4 format
$pdf->AliasNbPages(); // Enable {nb} alias for total pages in footer

// Set default font and text color for content
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(30, 30, 30); // Dark grey for content text

// Fetch records from the database, ordered by office and then by date
$query = "SELECT date, office, vehicle, fuel_type, liters_issued FROM fuel WHERE id IN ($ids) ORDER BY office ASC, fuel_type ASC, date ASC";
$result = mysqli_query($conn, $query);

// Check for query errors
if (!$result) {
    die('Database query failed: ' . mysqli_error($conn));
}

// Group records by office and fuel type and calculate total liters
$groupedData = [];
while ($row = mysqli_fetch_assoc($result)) {
    $office = $row['office'];
    $fuelType = $row['fuel_type'];
    if (!isset($groupedData[$office])) {
        $groupedData[$office] = [];
    }
    if (!isset($groupedData[$office][$fuelType])) {
        $groupedData[$office][$fuelType] = [
            'records' => [],
            'total_liters' => 0
        ];
    }
    $groupedData[$office][$fuelType]['records'][] = $row;
    $groupedData[$office][$fuelType]['total_liters'] += (float)$row['liters_issued'];
}

// Iterate through grouped data to add to PDF
foreach ($groupedData as $officeName => $fuelTypes) {
    // Office Sub-header
    $pdf->SetFont('Arial', 'B', 12); // Bold font for office name
    $pdf->SetFillColor(245, 245, 245); // Very light grey background for office header
    $pdf->Cell(0, 8, 'Office: ' . $officeName, 0, 1, 'L', true); // Office name
    $pdf->Ln(2); // Small space after office header

    foreach ($fuelTypes as $fuelType => $fuelData) {
        // Fuel Type Sub-header
        $pdf->SetFont('Arial', 'B', 11); // Slightly smaller bold font for fuel type
        $pdf->SetFillColor(240, 240, 240); // Slightly darker grey background for fuel type header
        $pdf->Cell(0, 8, 'Fuel Type: ' . $fuelType, 0, 1, 'L', true); // Fuel type
        $pdf->Ln(2); // Small space after fuel type header

        // Table header for records within this fuel type
        $pdf->SetFont('Arial', 'B', 10); // Bold font for column headers
        $pdf->SetFillColor(230, 230, 230); // Light grey background for table headers
        $pdf->SetDrawColor(200, 200, 200); // Light grey border for table headers

        // Column widths: Date (40), Vehicle (50), Fuel Type (40), Liters (40) = 170mm total
        $pdf->Cell(40, 7, 'Date', 1, 0, 'C', true);
        $pdf->Cell(70, 7, 'Vehicle', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Fuel Type', 1, 0, 'C', true); // Fuel Type column
        $pdf->Cell(40, 7, 'Liters', 1, 1, 'C', true);

        // Reset font and colors for data rows
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetFillColor(255, 255, 255); // White background for data rows
        $pdf->SetDrawColor(220, 220, 220); // Very light grey border for data rows (optional, can be 0 for no border)

        // Add individual records for the current fuel type
        foreach ($fuelData['records'] as $row) {
            $pdf->Cell(40, 6, $row['date'], 1, 0, 'C');
            $pdf->Cell(70, 6, $row['vehicle'], 1, 0, 'L');
            $pdf->Cell(40, 6, $row['fuel_type'], 1, 0, 'L'); // Fuel Type column
            $pdf->Cell(40, 6, number_format($row['liters_issued'], 2), 1, 1, 'R'); // Format liters to 2 decimal places
        }
        $pdf->Ln(2); // Small space after records

        // Total Liters for the fuel type
        $pdf->SetFont('Arial', 'B', 10); // Bold font for total line
        $pdf->SetFillColor(240, 240, 240); // Slightly darker grey for total line
        $pdf->Cell(150, 7, 'Total Liters for ' . $fuelType . ':', 1, 0, 'R', true); // Adjusted column span
        $pdf->Cell(40, 7, number_format($fuelData['total_liters'], 2), 1, 1, 'R', true); // Display total liters
        $pdf->Ln(5); // Small space before the next fuel type
    }
    $pdf->Ln(5); // Larger space before the next office group
}

// Output the PDF to the browser for download
$pdf->Output('I', 'fuel_records_report.pdf');

// Close database connection
mysqli_close($conn);
?>
