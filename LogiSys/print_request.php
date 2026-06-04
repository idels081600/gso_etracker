<?php
require_once('../fpdf/fpdf.php');
require_once('logi_db.php');

class PDF extends FPDF
{
    private $officeHeadNames = [];
    private $compactMode = false;

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
        $logoSize = $this->compactMode ? 15 : 20;
        $headerFont = $this->compactMode ? 9 : 12;
        $lineHeight = $this->compactMode ? 4 : 5;
        $textOffsetX = $this->compactMode ? 20 : 25;
        $rightLogoOffset = $this->compactMode ? 148 : 145;

        // Position logos relative to table position
        $this->Image($logoLeft, $x, $y, $logoSize);
        $this->Image($logoRight, $x + $rightLogoOffset, $y, $logoSize);

        $this->SetFont('Arial', 'B', $headerFont);
        $this->SetXY($x + $textOffsetX, $y + 1);
        $this->Cell(120, $lineHeight, 'Republic of the Philippines', 0, 1, 'C');
        $this->SetXY($x + $textOffsetX, $y + 1 + $lineHeight);
        $this->Cell(120, $lineHeight, 'City Government of Tagbilaran', 0, 1, 'C');
        $this->SetXY($x + $textOffsetX, $y + 1 + (2 * $lineHeight));
        $this->Cell(120, $lineHeight, 'General Services Office', 0, 1, 'C');

        return $y + ($this->compactMode ? 18 : 28); // Return Y position after header
    }

    // Calculate dynamic layout based on total rows
    function getDynamicLayout($totalRows)
    {
        // Keep table text readable like an office document. When there are many
        // rows, paginate instead of shrinking below 12pt.
        if ($totalRows <= 20) {
            return [
                'titleFont' => 14,
                'infoFont' => 12,
                'headerFont' => 12,
                'rowFont' => 12,
                'rowHeight' => 9,
                'headerRowHeight' => 9,
                'titleCellHeight' => 8,
                'infoLineHeight' => 7,
                'afterInfoGap' => 5,
            ];
        }

        return [
            'titleFont' => 13,
            'infoFont' => 12,
            'headerFont' => 12,
            'rowFont' => 12,
            'rowHeight' => 8,
            'headerRowHeight' => 9,
            'titleCellHeight' => 8,
            'infoLineHeight' => 7,
            'afterInfoGap' => 4,
        ];
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
        $availableHeight = $pageHeight - 35 - $currentY; // keep space for footer/signatures
        $possibleRows = max(1, (int)floor(max(0, $availableHeight) / $layout['rowHeight']));
        $totalRows = count($data);
        $remaining = max(0, $totalRows - $startIndex);
        if (!empty($layout['forceRows'])) {
            $rowsToRender = ($maxRows === null) ? $remaining : min($remaining, $maxRows);
        } else {
            $rowsToRender = ($maxRows === null) ? min($possibleRows, $remaining) : min($possibleRows, $remaining, $maxRows);
        }

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

    // Render both left and right tables on a single page and FORCE all rows to fit
    function renderBothTablesSinglePage($leftX, $rightX, $startY, $title, $requestInfo, $approvedInfo, $header, $data, $officeHeadName)
    {
        $totalRows = count($data);
        $pageHeight = method_exists($this, 'GetPageHeight') ? $this->GetPageHeight() : $this->h;

        // Reserve space for footer/signatures; compact mode keeps everything on one page.
        $bottomReserve = 34; // mm
        $headerEndY = $startY + 18; // compact renderTableHeader()

        $baseTitleCellH = 5.0;
        $baseInfoLineH  = 4.0;
        $baseHeaderRowH = 5.0;
        $afterInfoGap   = 1.0;

        // Compute available height for rows given the fixed parts above
        $tableY    = $headerEndY + 2 + $baseTitleCellH + 1 + (2 * $baseInfoLineH) + $afterInfoGap;
        $currentY  = $tableY + $baseHeaderRowH;
        $availH    = $pageHeight - $bottomReserve - $currentY;
        if ($availH < 5) { $availH = 5; }

        $rowHeight = ($totalRows > 0) ? max(1.8, $availH / $totalRows) : 6.0;
        $rowFont = max(4.5, min(10.0, $rowHeight * 1.3));
        $headerFont = max(6.5, min(9.0, $rowFont + 0.5));
        $infoFont = max(7.0, min(9.0, $rowFont + 0.5));
        $titleFont = max(9.0, min(11.0, $rowFont + 2.0));

        $titleCellH = $baseTitleCellH;
        $infoLineH = $baseInfoLineH;
        $headerRowH = $baseHeaderRowH;

        $layoutFinal = [
            'titleFont'       => $titleFont,
            'infoFont'        => $infoFont,
            'headerFont'      => $headerFont,
            'rowFont'         => $rowFont,
            'titleCellHeight' => $titleCellH,
            'infoLineHeight'  => $infoLineH,
            'headerRowHeight' => $headerRowH,
            'rowHeight'       => $rowHeight,
            'afterInfoGap'    => $afterInfoGap,
            'forceRows'       => true,
        ];

        // Render once on a single page (both left and right sides)
        $this->setOfficeHeadName($officeHeadName);
        $this->AddPage();
        $this->compactMode = true;
        $renderedLeft = $this->renderSingleTablePage($leftX, $startY, $title, $requestInfo, $header, $data, 0, $layoutFinal, $totalRows);
        $this->renderSingleTablePage($rightX, $startY, $title, $approvedInfo, $header, $data, 0, $layoutFinal, $renderedLeft);
        $this->compactMode = false;
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
        $sql = "SELECT *
FROM items_requested
WHERE office_name = ?
  AND date_requested = ?
ORDER BY date_requested DESC;"; // Get only the latest request

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
            $startY = 5;

            // Compact one-page print: reduce spacing and font as needed so the
            // office/request copy pair stays on one page.
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
