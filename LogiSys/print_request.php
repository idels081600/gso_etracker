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

    // Calculate dynamic layout based on total rows
    function getDynamicLayout($totalRows)
    {
        $layout = [];
        if ($totalRows <= 20) {
            $layout = [
                'titleFont' => 14,
                'infoFont' => 10,
                'headerFont' => 10,
                'rowFont' => 9,
                'rowHeight' => 6,
                'headerRowHeight' => 8,
                'titleCellHeight' => 8,
                'infoLineHeight' => 6,
                'afterInfoGap' => 5,
            ];
        } elseif ($totalRows <= 40) {
            $layout = [
                'titleFont' => 12,
                'infoFont' => 9,
                'headerFont' => 9,
                'rowFont' => 8,
                'rowHeight' => 5.5,
                'headerRowHeight' => 7,
                'titleCellHeight' => 7,
                'infoLineHeight' => 5,
                'afterInfoGap' => 4,
            ];
        } elseif ($totalRows <= 70) {
            $layout = [
                'titleFont' => 11,
                'infoFont' => 8,
                'headerFont' => 8,
                'rowFont' => 7,
                'rowHeight' => 5,
                'headerRowHeight' => 6,
                'titleCellHeight' => 6,
                'infoLineHeight' => 5,
                'afterInfoGap' => 3,
            ];
        } else {
            $layout = [
                'titleFont' => 10,
                'infoFont' => 7,
                'headerFont' => 7,
                'rowFont' => 6,
                'rowHeight' => 4.5,
                'headerRowHeight' => 5.5,
                'titleCellHeight' => 5.5,
                'infoLineHeight' => 4.5,
                'afterInfoGap' => 2.5,
            ];
        }
        return $layout;
    }

    // Render one table (with header, title, info, header row, and a segment of rows)
    function renderSingleTablePage($x, $y, $title, $info, $header, $data, $startIndex, $layout, $maxRows = null)
    {
        $colWidths = [20, 20, 60, 30, 35]; // total: 165

        // Header (logos and office header)
        $headerEndY = $this->renderTableHeader($x, $y);

        // Title
        $this->SetFont('Arial', 'B', $layout['titleFont']);
        $this->SetXY($x, $headerEndY + 2);
        $this->Cell(array_sum($colWidths), $layout['titleCellHeight'], $title, 0, 0, 'C');

        // Info lines
        $this->SetFont('Arial', '', $layout['infoFont']);
        $this->SetXY($x, $headerEndY + 2 + $layout['titleCellHeight'] + 1);
        $this->Cell(array_sum($colWidths), $layout['infoLineHeight'], 'Requesting Office: ' . ($info['office'] ?? ''), 0, 0, 'L');
        $this->SetXY($x, $headerEndY + 2 + $layout['titleCellHeight'] + 1 + $layout['infoLineHeight']);
        $this->Cell(array_sum($colWidths), $layout['infoLineHeight'], 'Request Date: ' . ($info['date'] ?? ''), 0, 0, 'L');

        // Table position start
        $afterInfoGap = $layout['afterInfoGap'] ?? 3;
        $tableY = $headerEndY + 2 + $layout['titleCellHeight'] + 1 + (2 * $layout['infoLineHeight']) + $afterInfoGap;
        $this->SetXY($x, $tableY);

        // Header row
        $this->SetFont('Arial', 'B', $layout['headerFont']);
        $this->SetFillColor(230, 230, 230);
        foreach ($header as $i => $col) {
            $this->Cell($colWidths[$i], $layout['headerRowHeight'], $col, 1, 0, 'C', true);
        }
        $this->Ln();

        // Rows calculation
        $currentY = $this->GetY();
        $pageHeight = method_exists($this, 'GetPageHeight') ? $this->GetPageHeight() : $this->h;
        $availableHeight = $pageHeight - 40 - $currentY; // keep 40mm for footer/signatures
        $possibleRows = (int)floor(max(0, $availableHeight) / $layout['rowHeight']);
        $totalRows = count($data);
        $remaining = max(0, $totalRows - $startIndex);
        $rowsToRender = ($maxRows === null) ? min($possibleRows, $remaining) : min($possibleRows, $remaining, $maxRows);

        // Data rows
        $this->SetFont('Arial', '', $layout['rowFont']);
        for ($r = 0; $r < $rowsToRender; $r++) {
            $row = $data[$startIndex + $r];
            $this->SetXY($x, $currentY);
            foreach ($row as $i => $col) {
                $this->Cell($colWidths[$i], $layout['rowHeight'], $col, 1, 0, 'C');
            }
            $currentY += $layout['rowHeight'];
        }

        return $rowsToRender; // number of rows rendered on this table
    }

    // Render both left and right tables across as many pages as needed
    function renderBothTablesPaginated($leftX, $rightX, $startY, $title, $requestInfo, $approvedInfo, $header, $data, $officeHeadName)
    {
        $totalRows = count($data);
        $layout = $this->getDynamicLayout($totalRows);

        $rowIndex = 0;
        while ($rowIndex < $totalRows) {
            // Set footer name for the page to be added
            $this->setOfficeHeadName($officeHeadName);
            $this->AddPage();

            // Left table determines how many rows fit this page
            $renderedLeft = $this->renderSingleTablePage($leftX, $startY, $title, $requestInfo, $header, $data, $rowIndex, $layout);
            if ($renderedLeft <= 0) {
                $renderedLeft = 1; // safety
            }
            // Right table renders exactly the same number for visual symmetry
            $this->renderSingleTablePage($rightX, $startY, $title, $approvedInfo, $header, $data, $rowIndex, $layout, $renderedLeft);

            $rowIndex += $renderedLeft;
        }
    }

    // Render both left and right tables on a single page by dynamically scaling
    function renderBothTablesSinglePage($leftX, $rightX, $startY, $title, $requestInfo, $approvedInfo, $header, $data, $officeHeadName)
    {
        $totalRows = count($data);

        // Base minimal sizes to allow aggressive scaling
        $minTitleFont = 8;       // pt
        $minInfoFont = 6;        // pt
        $minHeaderFont = 6;      // pt
        $minRowFont = 4.5;       // pt

        $minTitleCellH = 4.5;    // mm
        $minInfoLineH = 3.5;     // mm
        $minHeaderRowH = 4.0;    // mm
        $minRowH = 2.8;          // mm

        // Start with compact but readable defaults
        $layout = [
            'titleFont' => 11,
            'infoFont' => 8,
            'headerFont' => 8,
            'rowFont' => 7,
            'titleCellHeight' => 6,
            'infoLineHeight' => 5,
            'headerRowHeight' => 6,
        ];

        // Compute available height analytically (without drawing) for one table
        $pageHeight = method_exists($this, 'GetPageHeight') ? $this->GetPageHeight() : $this->h;
        $bottomReserve = 40; // mm reserved for footer/signatures
        $headerEndY = $startY + 28; // from renderTableHeader()

        // Iteratively reduce sizes until all rows fit on one page
        for ($iter = 0; $iter < 6; $iter++) {
            $tableY = $headerEndY + 2 + $layout['titleCellHeight'] + 1 + (2 * $layout['infoLineHeight']) + 2.5; // afterInfoGap ~= 2.5
            $currentY = $tableY + $layout['headerRowHeight'];
            $availableHeight = $pageHeight - $bottomReserve - $currentY;
            if ($availableHeight < 10) {
                $availableHeight = 10; // avoid zero/negative
            }
            $rowHeight = $availableHeight / max($totalRows, 1);

            if ($rowHeight >= $minRowH) {
                // We can fit; map font size to rowHeight
                $rowFont = max($minRowFont, min(9, $rowHeight - 0.5 + 4.5)); // simple mapping
                $layoutFinal = [
                    'titleFont' => max($minTitleFont, min(14, $layout['titleFont'])),
                    'infoFont' => max($minInfoFont, min(10, $layout['infoFont'])),
                    'headerFont' => max($minHeaderFont, min(10, $layout['headerFont'])),
                    'rowFont' => $rowFont,
                    'titleCellHeight' => max($minTitleCellH, $layout['titleCellHeight']),
                    'infoLineHeight' => max($minInfoLineH, $layout['infoLineHeight']),
                    'headerRowHeight' => max($minHeaderRowH, $layout['headerRowHeight']),
                    'rowHeight' => $rowHeight,
                ];

                // Render once on a single page
                $this->setOfficeHeadName($officeHeadName);
                $this->AddPage();
                $renderedLeft = $this->renderSingleTablePage($leftX, $startY, $title, $requestInfo, $header, $data, 0, $layoutFinal, $totalRows);
                // Right side identical count to keep symmetry
                $this->renderSingleTablePage($rightX, $startY, $title, $approvedInfo, $header, $data, 0, $layoutFinal, $renderedLeft);
                return;
            }

            // Reduce sizes further and try again
            $layout['titleFont'] = max($minTitleFont, $layout['titleFont'] - 1);
            $layout['infoFont'] = max($minInfoFont, $layout['infoFont'] - 1);
            $layout['headerFont'] = max($minHeaderFont, $layout['headerFont'] - 1);
            $layout['rowFont'] = max($minRowFont, $layout['rowFont'] - 0.5);
            $layout['titleCellHeight'] = max($minTitleCellH, $layout['titleCellHeight'] - 0.5);
            $layout['infoLineHeight'] = max($minInfoLineH, $layout['infoLineHeight'] - 0.5);
            $layout['headerRowHeight'] = max($minHeaderRowH, $layout['headerRowHeight'] - 0.5);
        }

        // Final fallback: force minimal layout to ensure single page
        $layoutFinal = [
            'titleFont' => $minTitleFont,
            'infoFont' => $minInfoFont,
            'headerFont' => $minHeaderFont,
            'rowFont' => $minRowFont,
            'titleCellHeight' => $minTitleCellH,
            'infoLineHeight' => $minInfoLineH,
            'headerRowHeight' => $minHeaderRowH,
            'rowHeight' => max($minRowH, ($pageHeight - $bottomReserve - ($startY + 28 + 2 + $minTitleCellH + 1 + 2 * $minInfoLineH + 2.5 + $minHeaderRowH)) / max($totalRows, 1)),
        ];

        $this->setOfficeHeadName($officeHeadName);
        $this->AddPage();
        $renderedLeft = $this->renderSingleTablePage($leftX, $startY, $title, $requestInfo, $header, $data, 0, $layoutFinal, $totalRows);
        $this->renderSingleTablePage($rightX, $startY, $title, $approvedInfo, $header, $data, 0, $layoutFinal, $renderedLeft);
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

            // Calculate positions with gap
            $tableWidth = 165;
            $gapWidth = 10;
            $leftTableX = 10;
            $rightTableX = $leftTableX + $tableWidth + $gapWidth; // 185

            // Starting Y position for both tables
            $startY = 10;

            // Render both tables on a single page by scaling down to fit
            $pdf->renderBothTablesSinglePage(
                $leftTableX,
                $rightTableX,
                $startY,
                'REQUEST FORM',
                $requestInfo,
                $approvedInfo,
                $requestHeader,
                $requestData,
                $officeHeadName
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
