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
        $this->SetFont('Arial', '', 5.5);
        $pageNo = $this->PageNo();
        $headName = $this->officeHeadNames[$pageNo] ?? '';

        error_log("Footer Debug - Page $pageNo Office Head Name: '$headName'");

        $pageWidth = method_exists($this, 'GetPageWidth') ? $this->GetPageWidth() : $this->w;
        $margin = 5;
        $gap = 6;

        $tableWidth = ($pageWidth - (2 * $margin) - $gap) / 2;
        $leftX = $margin;
        $rightX = $margin + $tableWidth + $gap;
        $sigWidth = min(48, ($tableWidth - 12) / 2);
        $leftIssuedX = $leftX + 8;
        $leftSupplyX = $leftX + $tableWidth - $sigWidth - 8;
        $rightIssuedX = $rightX + 8;
        $rightSupplyX = $rightX + $tableWidth - $sigWidth - 8;

        $this->SetY(-20);
        $leftY = $this->GetY();

        // First Copy - Left Side
        $this->SetXY($leftIssuedX, $leftY);
        $this->Cell($sigWidth, 3, 'BRYAN LAUREANO', 0, 2, 'C');
        $this->SetY($this->GetY() - 3); // Move back up to overlap
        $this->SetX($leftIssuedX);
        $this->Cell($sigWidth, 3, '_________________________', 0, 2, 'C');
        $this->Cell($sigWidth, 3, 'Issued by:', 0, 2, 'C');

        // First Copy - Right Side
        $this->SetXY($leftSupplyX, $leftY);
        $this->Cell($sigWidth, 3, $headName, 0, 2, 'C');
        $this->SetY($this->GetY() - 3); // Overlap name with underline
        $this->SetX($leftSupplyX);
        $this->Cell($sigWidth, 3, '_________________________', 0, 2, 'C');
        $this->Cell($sigWidth, 3, 'Supply Officer/Representative:', 0, 2, 'C');

        // Second Copy - Left Side
        $this->SetXY($rightIssuedX, $leftY);
        $this->Cell($sigWidth, 3, 'BRYAN LAUREANO', 0, 2, 'C');
        $this->SetY($this->GetY() - 3);
        $this->SetX($rightIssuedX);
        $this->Cell($sigWidth, 3, '_________________________', 0, 2, 'C');
        $this->Cell($sigWidth, 3, 'Issued by:', 0, 2, 'C');

        // Second Copy - Right Side
        $this->SetXY($rightSupplyX, $leftY);
        $this->Cell($sigWidth, 3, $headName, 0, 2, 'C');
        $this->SetY($this->GetY() - 3);
        $this->SetX($rightSupplyX);
        $this->Cell($sigWidth, 3, '_________________________', 0, 2, 'C');
        $this->Cell($sigWidth, 3, 'Supply Officer/Representative:', 0, 2, 'C');

        // Page number
        $this->SetY(-7);
        $this->SetFont('Arial', 'I', 5.5);
        $this->Cell(0, 5, 'Page ' . $pageNo . '/{nb}', 0, 0, 'C');
    }



    function getScaledColWidths($tableWidth)
    {
        $baseWidths = [20, 20, 60, 30, 35];
        $baseTotal = array_sum($baseWidths);
        $scaled = [];
        $runningTotal = 0;

        foreach ($baseWidths as $index => $width) {
            if ($index === count($baseWidths) - 1) {
                $scaled[] = $tableWidth - $runningTotal;
                break;
            }
            $value = round(($width / $baseTotal) * $tableWidth, 2);
            $scaled[] = $value;
            $runningTotal += $value;
        }

        return $scaled;
    }

    function getPortraitColWidths($tableWidth)
    {
        $baseWidths = [10, 12, 48, 16, 17];
        $baseTotal = array_sum($baseWidths);
        $scaled = [];
        $runningTotal = 0;

        foreach ($baseWidths as $index => $width) {
            if ($index === count($baseWidths) - 1) {
                $scaled[] = $tableWidth - $runningTotal;
                break;
            }
            $value = round(($width / $baseTotal) * $tableWidth, 2);
            $scaled[] = $value;
            $runningTotal += $value;
        }

        return $scaled;
    }

    function renderTableHeader($x, $y, $tableWidth = 165)
    {
        $logoLeft = 'tagbi_seal.png';
        $logoRight = 'logo.png';
        $logoSize = $this->compactMode ? 8 : 20;
        $headerFont = $this->compactMode ? 6 : 12;
        $lineHeight = $this->compactMode ? 2.5 : 5;
        $textWidth = max(70, $tableWidth - (2 * $logoSize) - 8);
        $textX = $x + ($tableWidth - $textWidth) / 2;
        $rightLogoX = $x + $tableWidth - $logoSize;

        // Position logos relative to table position
        $this->Image($logoLeft, $x, $y, $logoSize);
        $this->Image($logoRight, $rightLogoX, $y, $logoSize);

        $this->SetFont('Arial', 'B', $headerFont);
        $this->SetXY($textX, $y + 1);
        $this->Cell($textWidth, $lineHeight, 'Republic of the Philippines', 0, 1, 'C');
        $this->SetXY($textX, $y + 1 + $lineHeight);
        $this->Cell($textWidth, $lineHeight, 'City Government of Tagbilaran', 0, 1, 'C');
        $this->SetXY($textX, $y + 1 + (2 * $lineHeight));
        $this->Cell($textWidth, $lineHeight, 'General Services Office', 0, 1, 'C');

        return $y + ($this->compactMode ? 9 : 28); // Return Y position after header
    }

    function renderFittedCell($width, $height, $text, $border = 1, $align = 'C', $minFontSize = 3.2)
    {
        $text = (string)$text;
        $originalFontSize = $this->FontSizePt;
        $fontSize = $originalFontSize;
        $maxTextWidth = max(1, $width - 1.5);

        while ($fontSize > $minFontSize && $this->GetStringWidth($text) > $maxTextWidth) {
            $fontSize -= 0.2;
            $this->SetFont($this->FontFamily, $this->FontStyle, $fontSize);
        }

        $this->Cell($width, $height, $text, $border, 0, $align);
        if ($fontSize !== $originalFontSize) {
            $this->SetFont($this->FontFamily, $this->FontStyle, $originalFontSize);
        }
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
        $tableWidth = $layout['tableWidth'] ?? 165;
        $colWidths = $layout['colWidths'] ?? $this->getScaledColWidths($tableWidth);

        // Header (logos and office header)
        $headerEndY = $this->renderTableHeader($x, $y, $tableWidth);

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
                $this->renderFittedCell($colWidths[$i], $layout['rowHeight'], $col, 1, 'C', $layout['minRowFont'] ?? 3.2);
            }
            $currentY += $layout['rowHeight'];
        }

        return $rowsToRender; // number of rows rendered on this table
    }

    // Render both left and right tables across as many pages as needed
    function renderBothTablesPaginated($leftX, $rightX, $startY, $title, $requestInfo, $approvedInfo, $header, $data, $officeHeadName, $tableWidth = 165)
    {
        $totalRows = count($data);
        $layout = $this->getDynamicLayout($totalRows);
        $layout['tableWidth'] = $tableWidth;
        $layout['colWidths'] = $this->getScaledColWidths($tableWidth);

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

    function renderBothTablesPortraitPaginated($startY, $title, $requestInfo, $approvedInfo, $header, $data, $officeHeadName)
    {
        $totalRows = count($data);
        $portraitHeader = ['Qty', 'Unit', 'Item Description', 'Approved', 'Remarks'];

        $this->setOfficeHeadName($officeHeadName);
        $this->AddPage('P');
        $this->compactMode = true;

        $pageWidth = method_exists($this, 'GetPageWidth') ? $this->GetPageWidth() : 210;
        $pageHeight = method_exists($this, 'GetPageHeight') ? $this->GetPageHeight() : 297;
        $sideMargin = 5;
        $gapWidth = 6;
        $tableWidth = ($pageWidth - (2 * $sideMargin) - $gapWidth) / 2;
        $leftTableX = $sideMargin;
        $rightTableX = $leftTableX + $tableWidth + $gapWidth;

        $titleCellHeight = 4.4;
        $infoLineHeight = 3.5;
        $headerRowHeight = 4.2;
        $afterInfoGap = 2.2;
        $headerEndY = $startY + 9;
        $tableY = $headerEndY + 2 + $titleCellHeight + 1 + (2 * $infoLineHeight) + $afterInfoGap;
        $firstRowY = $tableY + $headerRowHeight;
        $availableRowHeight = max(5, $pageHeight - 35 - $firstRowY);
        $rowHeight = ($totalRows > 0) ? ($availableRowHeight / $totalRows) : 5.2;
        $rowFont = min(5.5, max(3.0, $rowHeight * 1.03));

        $layout = [
            'titleFont' => 7.2,
            'infoFont' => 5.6,
            'headerFont' => 5.0,
            'rowFont' => $rowFont,
            'rowHeight' => $rowHeight,
            'headerRowHeight' => $headerRowHeight,
            'titleCellHeight' => $titleCellHeight,
            'infoLineHeight' => $infoLineHeight,
            'afterInfoGap' => $afterInfoGap,
            'forceRows' => true,
            'tableWidth' => $tableWidth,
            'colWidths' => $this->getPortraitColWidths($tableWidth),
        ];

        $renderedRows = $this->renderSingleTablePage($leftTableX, $startY, $title, $requestInfo, $portraitHeader, $data, 0, $layout, $totalRows);
        $this->renderSingleTablePage($rightTableX, $startY, $title, $approvedInfo, $portraitHeader, $data, 0, $layout, $renderedRows);
        $this->compactMode = false;
    }

    // Render both left and right tables on a single page and FORCE all rows to fit
    function renderBothTablesSinglePage($leftX, $rightX, $startY, $title, $requestInfo, $approvedInfo, $header, $data, $officeHeadName, $tableWidth = 165)
    {
        $totalRows = count($data);
        $pageHeight = method_exists($this, 'GetPageHeight') ? $this->GetPageHeight() : $this->h;

        // Reserve the exact lower band used by Footer() for two signatories per copy.
        $bottomReserve = 23; // mm
        $headerEndY = $startY + 9; // compact renderTableHeader()

        $baseTitleCellH = 3.6;
        $baseInfoLineH  = 2.8;
        $baseHeaderRowH = 3.2;
        $afterInfoGap   = 0.6;

        // Compute available height for rows given the fixed parts above
        $tableY    = $headerEndY + 2 + $baseTitleCellH + 1 + (2 * $baseInfoLineH) + $afterInfoGap;
        $currentY  = $tableY + $baseHeaderRowH;
        $availH    = $pageHeight - $bottomReserve - $currentY;
        if ($availH < 5) { $availH = 5; }

        $rowHeight = ($totalRows > 0) ? ($availH / $totalRows) : 6.0;
        $rowFont = max(4.2, min(7.0, $rowHeight * 1.15));
        $headerFont = max(5.0, min(7.0, $rowFont + 0.1));
        $infoFont = max(5.5, min(8.0, $rowFont + 0.1));
        $titleFont = max(7.0, min(9.5, $rowFont + 1.1));

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
            'tableWidth'      => $tableWidth,
            'colWidths'       => $this->getScaledColWidths($tableWidth),
            'minRowFont'      => 3.4,
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
    $pdf = new PDF('L', 'mm', 'A4');
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

            // Calculate positions from the actual A4 landscape paper width.
            $pageWidth = method_exists($pdf, 'GetPageWidth') ? $pdf->GetPageWidth() : 297;
            $sideMargin = 5;
            $gapWidth = 6;
            $tableWidth = ($pageWidth - (2 * $sideMargin) - $gapWidth) / 2;
            $leftTableX = $sideMargin;
            $rightTableX = $leftTableX + $tableWidth + $gapWidth;

            // Starting Y position for both tables
            $startY = 3;

            if (count($requestData) >= 30) {
                // Very long requests print in portrait so each page has more row height.
                $pdf->renderBothTablesPortraitPaginated(
                    $startY,
                    'REQUEST FORM',
                    $requestInfo,
                    $approvedInfo,
                    $requestHeader,
                    $requestData,
                    $officeHeadName
                );
            } else {
                // Requests below 30 rows stay in landscape with two copies on one page.
                $pdf->renderBothTablesSinglePage(
                    $leftTableX,
                    $rightTableX,
                    $startY,
                    'REQUEST FORM',
                    $requestInfo,
                    $approvedInfo,
                    $requestHeader,
                    $requestData,
                    $officeHeadName,
                    $tableWidth
                );
            }
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
