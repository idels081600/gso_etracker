<?php
require_once('../fpdf/fpdf.php');
require_once('logi_db.php');

class PDF extends FPDF
{
    private $officeHeadNames = [];

    function setOfficeHeadName($name)
    {
        // Store office head name for the upcoming page
        $this->officeHeadNames[$this->PageNo() + 1] = $name;
    }

    function Header() {}

    function Footer()
    {
        $this->SetFont('Arial', '', 8);
        $pageNo = $this->PageNo();
        $headName = $this->officeHeadNames[$pageNo] ?? '';

        error_log("Footer Debug - Page $pageNo Office Head Name: '$headName'");

        $this->SetY(-30);
        $leftY = $this->GetY();

        // First Copy - Left Side
        $this->SetXY(10, $leftY);
        $this->Cell(80, 4, 'BRYAN LAUREANO', 0, 2, 'C');
        $this->SetY($this->GetY() - 4); // Move back up to overlap
        $this->SetX(10);
        $this->Cell(80, 4, '_________________________', 0, 2, 'C');
        $this->Cell(80, 4, 'Issued by:', 0, 2, 'C');

        // First Copy - Right Side
        $this->SetXY(100, $leftY);
        $this->Cell(80, 4, $headName, 0, 2, 'C');
        $this->SetY($this->GetY() - 4); // Overlap name with underline
        $this->SetX(100);
        $this->Cell(80, 4, '_________________________', 0, 2, 'C');
        $this->Cell(80, 4, 'Supply Officer/Representative:', 0, 2, 'C');

        // Second Copy - Left Side
        $this->SetXY(190, $leftY);
        $this->Cell(80, 4, 'BRYAN LAUREANO', 0, 2, 'C');
        $this->SetY($this->GetY() - 4);
        $this->SetX(190);
        $this->Cell(80, 4, '_________________________', 0, 2, 'C');
        $this->Cell(80, 4, 'Issued by:', 0, 2, 'C');

        // Second Copy - Right Side
        $this->SetXY(280, $leftY);
        $this->Cell(80, 4, $headName, 0, 2, 'C');
        $this->SetY($this->GetY() - 4);
        $this->SetX(280);
        $this->Cell(80, 4, '_________________________', 0, 2, 'C');
        $this->Cell(80, 4, 'Supply Officer/Representative:', 0, 2, 'C');

        // Page number
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $pageNo . '/{nb}', 0, 0, 'C');
    }



    function renderTableHeader($x, $y)
    {
        $logoLeft = 'tagbi_seal.png';
        $logoRight = 'logo.png';

        // Position logos relative to table position
        $this->Image($logoLeft, $x, $y, 20);
        $this->Image($logoRight, $x + 145, $y, 20);

        $this->SetFont('Arial', 'B', 12);
        $this->SetXY($x + 25, $y + 2);
        $this->Cell(115, 5, 'Republic of the Philippines', 0, 1, 'C');
        $this->SetXY($x + 25, $y + 7);
        $this->Cell(115, 5, 'City Government of Tagbilaran', 0, 1, 'C');
        $this->SetXY($x + 25, $y + 12);
        $this->Cell(115, 5, 'General Services Office', 0, 1, 'C');

        return $y + 28; // Return Y position after header
    }

    function renderTable($x, $y, $header, $data)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetXY($x, $y);

        // Set column widths (adjusted for 5 columns)
        $colWidths = [20, 20, 60, 30, 35]; // total: 165

        // Header row with gray background
        $this->SetFillColor(230, 230, 230);
        foreach ($header as $i => $col) {
            $this->Cell($colWidths[$i], 8, $col, 1, 0, 'C', true);
        }
        $this->Ln();

        // Data rows
        $this->SetFont('Arial', '', 9);
        $currentY = $this->GetY();
        foreach ($data as $row) {
            $this->SetXY($x, $currentY); // Set both X and Y for each row
            foreach ($row as $i => $col) {
                $this->Cell($colWidths[$i], 6, $col, 1, 0, 'C');
            }
            $currentY += 6; // Move to next row
        }

        return $currentY; // Return the final Y position
    }

    function renderTableWithTitle($x, $y, $title, $requestInfo, $header, $data)
    {
        // Add individual header for this table
        $headerEndY = $this->renderTableHeader($x, $y);

        // Add table title
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY($x, $headerEndY + 5);
        $this->Cell(165, 8, $title, 0, 0, 'C');

        // Add request information
        $this->SetFont('Arial', '', 10);
        $this->SetXY($x, $headerEndY + 15);
        $this->Cell(165, 6, 'Requesting Office: ' . $requestInfo['office'], 0, 0, 'L');
        $this->SetXY($x, $headerEndY + 21);
        $this->Cell(165, 6, 'Request Date: ' . $requestInfo['date'], 0, 0, 'L');

        // Render the table
        $tableY = $headerEndY + 37;
        $finalY = $this->renderTable($x, $tableY, $header, $data);
        return $finalY; // Return the final Y position after the table
    }
}

// Function to get office head name
function getOfficeHeadName($conn, $officeName)
{
    $sql = "SELECT name FROM users WHERE username LIKE ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $searchTerm = '%' . trim($officeName) . '%';  // Flexible match
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['name'];
        }

        $stmt->close();
    }

    return ''; // No match found
}



// Handle POST data
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["offices"]) && is_array($_POST["offices"])) {
    $print_date = $_POST['print_date'] ?? date('Y-m-d');
    $selectedOffices = $_POST["offices"];
    $reportType = $_POST["type"] ?? 'request'; // Default to 'request' if type is not set

    // Create PDF
    $pdf = new PDF('L', 'mm', 'Legal');
    $pdf->AliasNbPages();

    // Column headers
    $requestHeader = ['Qty', 'Unit', 'Item Description', 'Approved Qty', 'Remarks'];

    // Loop through each selected office
    foreach ($selectedOffices as $officeName) {
        // Get office head name
        $officeHeadName = getOfficeHeadName($conn, $officeName);
        $pdf->setOfficeHeadName($officeHeadName);

        // Fetch data for the current office
        $sql = "SELECT * FROM items_requested                 
        WHERE office_name = ? 
        AND DATE(date_requested) = ?
        ORDER BY date_requested DESC"; // Get only the latest request

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $officeName, $print_date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $requestInfo = [];
            $requestData = [];

            // Fetch request information and items
            while ($row = $result->fetch_assoc()) {
                // Request information
                $requestInfo = [
                    'office' => htmlspecialchars($row['office_name']),
                    'date' => date('F j, Y', strtotime($row['date_requested'])),
                ];
                $approvedInfo = [
                    'office' => htmlspecialchars($row['office_name']),
                    'date' => date('F j, Y', strtotime($row['date_requested'])),
                ];
                // Add each item to the request data
                $requestData[] = [
                    $row['quantity'],
                    $row['unit'],
                    $row['item_name'],
                    $row['approved_quantity'],
                    $row['remarks_admin']
                ];
            }

            // Approval information for right table (same data, different context)

            // Add a new page for each office
            $pdf->AddPage();
            $pdf->setOfficeHeadName($officeHeadName);
            // Calculate positions with gap
            $tableWidth = 165;
            $gapWidth = 10;
            $leftTableX = 10;
            $rightTableX = $leftTableX + $tableWidth + $gapWidth; // 185

            // Starting Y position for both tables
            $startY = 10;

            // Render first table (REQUEST FORM) on the left
            $pdf->renderTableWithTitle(
                $leftTableX,
                $startY,
                'REQUEST FORM',
                $requestInfo,
                $requestHeader,
                $requestData
            );

            // Render second table (APPROVED ITEMS) on the right with same data
            $pdf->renderTableWithTitle(
                $rightTableX,
                $startY,
                'REQUEST FORM',
                $approvedInfo,
                $requestHeader,
                $requestData
            );
        } else {
            // Add a new page for each office
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'No requests found for ' . $officeName, 0, 1);
        }
        $stmt->close();
    }

    // Output the PDF
    $pdf->Output('consolidated_requests.pdf', 'I');
} else {
    echo "No offices selected.";
}
