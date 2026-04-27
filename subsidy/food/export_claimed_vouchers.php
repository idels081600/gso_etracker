<?php
session_start();
require_once 'db_fuel.php';

// Security check - redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

// Check if station_id is provided via GET parameter (for export modal selection)
if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
    $station_id = (int)$_GET['station_id'];
    
    $station_sql = "SELECT station_name FROM gas_stations WHERE id = $station_id";
    $station_result = mysqli_query($conn, $station_sql);
    
    if (mysqli_num_rows($station_result) > 0) {
        $station_data = mysqli_fetch_assoc($station_result);
        $station_name = $station_data['station_name'];
    } else {
        die('Invalid station selected.');
    }
} else {
    if (!isset($_SESSION['station_id']) || empty($_SESSION['station_id'])) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $check_sql = "SELECT us.station_id, gs.station_name 
                      FROM user_stations us 
                      JOIN gas_stations gs ON us.station_id = gs.id 
                      WHERE us.username = '$username'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $station_data = mysqli_fetch_assoc($check_result);
            $_SESSION['station_id'] = $station_data['station_id'];
            $_SESSION['station_name'] = $station_data['station_name'];
            $station_id = (int)$_SESSION['station_id'];
            $station_name = $_SESSION['station_name'];
        } else {
            header("Location: select_station.php");
            exit();
        }
    } else {
        $station_id = (int)$_SESSION['station_id'];
        $station_name = isset($_SESSION['station_name']) ? $_SESSION['station_name'] : 'Unknown Station';
    }
}

// Get pump price
$pumpPrice = isset($_GET['pump_price']) ? (float)$_GET['pump_price'] : 0;

// Get date range filters
$dateFilter = '';
$dateRangeText = 'All Dates';

if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $startDate = mysqli_real_escape_string($conn, $_GET['start_date']);
    $dateFilter .= " AND DATE(vc.claim_date) >= '$startDate' ";
    $dateRangeText = 'From ' . date('F j, Y', strtotime($startDate));
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $endDate = mysqli_real_escape_string($conn, $_GET['end_date']);
    $dateFilter .= " AND DATE(vc.claim_date) <= '$endDate' ";
    $dateRangeText = isset($startDate) 
        ? $dateRangeText . ' to ' . date('F j, Y', strtotime($endDate)) 
        : 'Until ' . date('F j, Y', strtotime($endDate));
}

require_once '../../fpdf/fpdf.php';

$currentDate = date('F j, Y');

// Fetch claimed vouchers
$sql = "SELECT tr.driver_name, tr.tricycle_no, vc.voucher_number, vc.claim_date, vc.e_signature, gs.station_name
        FROM voucher_claims vc
        INNER JOIN tricycle_records tr ON vc.tricycle_id = tr.id
        LEFT JOIN gas_stations gs ON vc.station_id = gs.id
        WHERE vc.e_signature IS NOT NULL AND vc.e_signature != ''
        AND vc.station_id = $station_id
        $dateFilter
        ORDER BY tr.tricycle_no, vc.claim_date, vc.voucher_number";

$result = mysqli_query($conn, $sql);

// Initialize metrics
$boatVouchers = 0;
$boatFueledPrice = 0;
$boatLiters = 0;
$boatCount = 0;
$tricycleVouchers = 0;
$tricycleFueledPrice = 0;
$tricycleLiters = 0;
$tricycleCount = 0;
$trackedVehicles = [];

// Group data
$groupedData = [];
$totalVouchers = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $key = $row['tricycle_no'] . '_' . date('Y-m-d', strtotime($row['claim_date']));
    $vehicleKey = $row['tricycle_no'];
    
    if (!isset($groupedData[$key])) {
        $isBoat = strlen($row['tricycle_no']) > 4;
        $groupedData[$key] = [
            'driver_name' => $row['driver_name'],
            'tricycle_no' => $row['tricycle_no'],
            'vouchers' => [],
            'claim_date' => $row['claim_date'],
            'e_signature' => $row['e_signature'],
            'is_boat' => $isBoat
        ];
        
        // Count unique vehicles per day
        if (!isset($trackedVehicles[$key])) {
            $trackedVehicles[$key] = true;
            if ($isBoat) {
                $boatCount++;
            } else {
                $tricycleCount++;
            }
        }
    }
    $groupedData[$key]['vouchers'][] = $row['voucher_number'];
    $totalVouchers++;

    // Track specific metrics
    if (strlen($row['tricycle_no']) > 4) {
        $boatVouchers++;
        $boatFueledPrice += 200;
    } else {
        $tricycleVouchers++;
        $tricycleFueledPrice += 200;
    }
}

// Correct calculation: Each voucher = ₱200 worth of fuel
$totalFueledPrice = $totalVouchers * 200;
$totalLiters = $pumpPrice > 0 ? $totalFueledPrice / $pumpPrice : 0;

// Vehicle-specific calculations
$boatLiters = $pumpPrice > 0 ? $boatFueledPrice / $pumpPrice : 0;
$tricycleLiters = $pumpPrice > 0 ? $tricycleFueledPrice / $pumpPrice : 0;

// ── Column widths ──────────────────────────────────────────
define('COL_NAME',    50);
define('COL_TRIC',    30);
define('COL_VOUCH',   50);
define('COL_DATE',    40);
define('COL_SIG',    100);
define('LINE_HEIGHT',  5);   // line height inside multi-line cell
define('MIN_ROW_H',   20);   // minimum row height
define('LEFT_MARGIN',  10);  // page left margin

class PDF extends FPDF
{
    function Header() {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $GLOBALS['station_name'], 0, 1, 'C');
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'CLAIMED VOUCHERS AS OF ' . $GLOBALS['currentDate'], 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 6, $GLOBALS['dateRangeText'], 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    /**
     * Draw a bordered rectangle that exactly fills the cell area,
     * then write text centered inside it — supports multiple lines.
     *
     * @param float  $x         Left edge of cell
     * @param float  $y         Top edge of cell
     * @param float  $w         Cell width
     * @param float  $h         Cell height  (full row height)
     * @param string $txt       Text to display
     * @param int    $lineH     Height of each text line
     */
    function FixedCell($x, $y, $w, $h, $txt, $lineH = LINE_HEIGHT) {
        // Draw the border rectangle
        $this->Rect($x, $y, $w, $h);

        if ($txt === '') return;

        // Word-wrap the text to fit inside the cell (with 3 mm padding each side)
        $innerW = $w - 6;
        $lines  = $this->wordWrapText($txt, $innerW);

        // Calculate vertical starting position to center the block of lines
        $blockH   = count($lines) * $lineH;
        $startY   = $y + ($h - $blockH) / 2;

        foreach ($lines as $i => $line) {
            $lineY = $startY + $i * $lineH;
            // Clip to cell bounds just in case
            $this->SetXY($x, $lineY);
            $this->Cell($w, $lineH, $line, 0, 0, 'C');
        }
    }

    /**
     * Break text into lines that fit within $maxW mm using GetStringWidth.
     */
    function wordWrapText($txt, $maxW) {
        $words  = explode(' ', $txt);
        $lines  = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if ($this->GetStringWidth($test) <= $maxW) {
                $current = $test;
            } else {
                if ($current !== '') $lines[] = $current;
                // If a single word is wider than the cell, force it
                $current = $word;
            }
        }
        if ($current !== '') $lines[] = $current;

        return $lines;
    }

    /**
     * Calculate the row height needed for a given text in a given column width.
     */
    function calcRowHeight($txt, $colW, $lineH = LINE_HEIGHT, $minH = MIN_ROW_H) {
        $innerW = $colW - 6;
        $lines  = $this->wordWrapText($txt, $innerW);
        $needed = count($lines) * $lineH + 10;   // +10 padding top & bottom
        return max($minH, $needed);
    }
}

// ── Build PDF ──────────────────────────────────────────────
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(LEFT_MARGIN, 10, 10);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// ── Table header ──
$pdf->SetFillColor(200, 200, 200);
$pdf->SetFont('Arial', 'B', 10);
$headerH = 10;
$hx = LEFT_MARGIN;
$hy = $pdf->GetY();

$pdf->SetXY($hx, $hy);
$pdf->Cell(COL_NAME,  $headerH, 'Name',                 1, 0, 'C', true);
$pdf->Cell(COL_TRIC,  $headerH, 'Tricycle No.',          1, 0, 'C', true);
$pdf->Cell(COL_VOUCH, $headerH, 'Voucher No. Claimed',   1, 0, 'C', true);
$pdf->Cell(COL_DATE,  $headerH, 'Date Claimed',          1, 0, 'C', true);
$pdf->Cell(COL_SIG,   $headerH, 'E-Signature',           1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

// ── Table rows ──
if (empty($groupedData)) {
    $pdf->Cell(COL_NAME + COL_TRIC + COL_VOUCH + COL_DATE + COL_SIG, 10,
               'No claimed vouchers found.', 1, 1, 'C');
} else {
    foreach ($groupedData as $data) {

        // Build voucher string
        $formattedVouchers = [];
        foreach ($data['vouchers'] as $vnum) {
            $formattedVouchers[] = $data['tricycle_no'] . '-' . $vnum;
        }
        $voucherStr = implode(', ', $formattedVouchers);

        // Calculate the required row height from each column's content
        $h1 = $pdf->calcRowHeight($data['driver_name'], COL_NAME);
        $h2 = $pdf->calcRowHeight($data['tricycle_no'],  COL_TRIC);
        $h3 = $pdf->calcRowHeight($voucherStr,           COL_VOUCH);
        $h4 = $pdf->calcRowHeight(date('M j, Y', strtotime($data['claim_date'])), COL_DATE);

        // All cells in the row share the tallest required height
        $rowH = max($h1, $h2, $h3, $h4, MIN_ROW_H);

        // Check for page break
        if ($pdf->GetY() + $rowH > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
            // Redraw header
            $pdf->SetFillColor(200, 200, 200);
            $pdf->SetFont('Arial', 'B', 10);
            $hx2 = LEFT_MARGIN;
            $hy2 = $pdf->GetY();
            $pdf->SetXY($hx2, $hy2);
            $pdf->Cell(COL_NAME,  $headerH, 'Name',                1, 0, 'C', true);
            $pdf->Cell(COL_TRIC,  $headerH, 'Tricycle No.',         1, 0, 'C', true);
            $pdf->Cell(COL_VOUCH, $headerH, 'Voucher No. Claimed',  1, 0, 'C', true);
            $pdf->Cell(COL_DATE,  $headerH, 'Date Claimed',         1, 0, 'C', true);
            $pdf->Cell(COL_SIG,   $headerH, 'E-Signature',          1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
        }

        // Top-left corner of this row
        $rowX = LEFT_MARGIN;
        $rowY = $pdf->GetY();

        // Column X positions
        $xName  = $rowX;
        $xTric  = $xName  + COL_NAME;
        $xVouch = $xTric  + COL_TRIC;
        $xDate  = $xVouch + COL_VOUCH;
        $xSig   = $xDate  + COL_DATE;

        // Draw all cells with the same $rowH
        $pdf->FixedCell($xName,  $rowY, COL_NAME,  $rowH, $data['driver_name']);
        $pdf->FixedCell($xTric,  $rowY, COL_TRIC,  $rowH, $data['tricycle_no']);
        $pdf->FixedCell($xVouch, $rowY, COL_VOUCH, $rowH, $voucherStr);
        $pdf->FixedCell($xDate,  $rowY, COL_DATE,  $rowH, date('M j, Y', strtotime($data['claim_date'])));

        // Signature cell border (no text)
        $pdf->Rect($xSig, $rowY, COL_SIG, $rowH);

        // Draw signature image if available
        if (!empty($data['e_signature']) && strpos($data['e_signature'], 'data:image') === 0) {
            preg_match('/data:image\/(\w+);base64,/', $data['e_signature'], $typeMatch);
            $imageType   = isset($typeMatch[1]) ? strtolower($typeMatch[1]) : 'png';
            $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $data['e_signature']);
            $imageData   = base64_decode($base64String);

            $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.' . $imageType;
            file_put_contents($tempFile, $imageData);

            // Max signature image size
            $imgW  = 60;
            $imgH  = min(16, $rowH - 4);
            $imgX  = $xSig + (COL_SIG - $imgW) / 2;
            $imgY  = $rowY + ($rowH - $imgH) / 2;

            $pdf->Image($tempFile, $imgX, $imgY, $imgW, $imgH);
            unlink($tempFile);
        }

        // Advance cursor past this row
        $pdf->SetXY($rowX, $rowY + $rowH);
    }
}

// ── Summary ──
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'SUMMARY TOTALS', 0, 1, 'C');
$pdf->Ln(2);

// Draw summary table
$colWidth = 65;
$rowHeight = 8;

// Headers
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell($colWidth, $rowHeight, 'METRIC', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'TRICYCLE', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'BOAT', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'TOTAL', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

// Row 1: Vehicle Count
$pdf->Cell($colWidth, $rowHeight, 'Total Fueled Today', 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, $tricycleCount, 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, $boatCount, 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, ($tricycleCount + $boatCount), 1, 1, 'C');

// Row 2: Vouchers Claimed
$pdf->Cell($colWidth, $rowHeight, 'Vouchers Claimed', 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, $tricycleVouchers, 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, $boatVouchers, 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, $totalVouchers, 1, 1, 'C');

// Row 3: Total Amount
$pdf->Cell($colWidth, $rowHeight, 'Total Amount (PHP)', 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($tricycleFueledPrice, 2), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($boatFueledPrice, 2), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($totalFueledPrice, 2), 1, 1, 'C');

// Row 4: Liters Dispensed
$pdf->Cell($colWidth, $rowHeight, 'Total Liters', 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($tricycleLiters, 2), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($boatLiters, 2), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($totalLiters, 2), 1, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, 'Pump Price Used: PHP ' . number_format($pumpPrice, 2) . ' / Liter', 0, 1, 'C');

// Signature line bottom-right
$pdf->SetY(-50);
$pdf->SetX(-80);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(70, 0, '', 'B', 1, 'C');
$pdf->SetX(-80);
$pdf->Cell(70, 8, 'Signature:', 0, 1, 'C');

$pdf->Output('D', 'Claimed_Vouchers_' . date('Y-m-d') . '.pdf');
?>