<?php
session_start();
require_once 'db_fuel.php';
require_once '../../fpdf/fpdf.php';

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized');
}

// Validate batch_id parameter
if (!isset($_GET['batch_id']) || empty(trim($_GET['batch_id']))) {
    die('Batch ID is required');
}

$batch_id = intval($_GET['batch_id']);

// Get batch header info
$batch_sql = "SELECT 
                b.id,
                b.batch_number,
                b.total_vouchers,
                b.total_amount,
                b.status,
                b.created_by,
                b.created_at,
                b.redeemed_at,
                b.remarks,
                v.vendor_serial,
                v.vendor_name,
                v.area,
                v.stall_no,
                v.section
            FROM food_redemption_batches b
            LEFT JOIN food_vendors v ON b.vendor_id = v.id
            WHERE b.id = $batch_id
            LIMIT 1";

$batch_result = mysqli_query($conn, $batch_sql);

if (!$batch_result || mysqli_num_rows($batch_result) === 0) {
    die('Batch not found');
}

$batch = mysqli_fetch_assoc($batch_result);

// Get batch items
$items_sql = "SELECT 
                bi.id,
                bi.voucher_id,
                bi.amount,
                bi.beneficiary_name,
                bi.beneficiary_code,
                bi.voucher_number,
                vc.claimant_name
            FROM food_redemption_items bi
            LEFT JOIN food_voucher_claims vc ON bi.voucher_id = vc.id
            WHERE bi.batch_id = $batch_id
            ORDER BY bi.id ASC";

$items_result = mysqli_query($conn, $items_sql);

$items = [];
if ($items_result) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $items[] = $row;
    }
}

// Create PDF with Code39 Barcode support
class BatchPDF extends FPDF {
    
    function Header() {
        // Title is handled in the main body
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }

    // Code 128 Barcode Generator - Works with Zebra LS-1203
    function Code128($x, $y, $value, $height=10, $xres=1) {
        // Code 128B character set
        $code128 = array(
            ' '=>'11011001100', '!'=>'11001101100', '"'=>'11001100110', '#'=>'10010011000',
            '$'=>'10010001100', '%'=>'10001001100', '&'=>'10011001000', '\''=>'10011000100',
            '('=>'10011000010', ')'=>'10101011000', '*'=>'10100011000', '+'=>'10001011000',
            ','=>'11010001100', '-'=>'11001001100', '.'=>'11000100110', '/'=>'10110001100',
            '0'=>'10001101100', '1'=>'10001100110', '2'=>'11000101100', '3'=>'11000100010',
            '4'=>'10101000110', '5'=>'10100011000', '6'=>'10001001110', '7'=>'10001110010',
            '8'=>'10111010100', '9'=>'10100101110', ':'=>'10010110110', ';'=>'10010110010',
            '<'=>'10110010010', '='=>'10110010100', '>'=>'10011010010', '?'=>'10101101110',
            '@'=>'10101100110', 'A'=>'10110101100', 'B'=>'10110100110', 'C'=>'10011010110',
            'D'=>'10011000110', 'E'=>'10000101110', 'F'=>'10000110110', 'G'=>'10010101110',
            'H'=>'10010110100', 'I'=>'10010011110', 'J'=>'10010100110', 'K'=>'10101010100',
            'L'=>'10101011110', 'M'=>'10100110110', 'N'=>'10100111010', 'O'=>'10110101010',
            'P'=>'10110110010', 'Q'=>'11010010010', 'R'=>'11010010100', 'S'=>'11010010010',
            'T'=>'11000010010', 'U'=>'11001010010', 'V'=>'11010101000', 'W'=>'11010100100',
            'X'=>'11010010010', 'Y'=>'11000101000', 'Z'=>'11000100100', '['=>'10101000100',
            '\\'=>'10100010100', ']'=>'10100101000', '^'=>'10010100100', '_'=>'10010101000',
            '`'=>'10010010100', 'a'=>'10000100100', 'b'=>'10010010010', 'c'=>'10100100010',
            'd'=>'10100010010', 'e'=>'10100010100', 'f'=>'10010010010', 'g'=>'11000010100',
            'h'=>'11001010100', 'i'=>'11010010010', 'j'=>'11000110100', 'k'=>'11001100100',
            'l'=>'11001101000', 'm'=>'11000110010', 'n'=>'11001011000', 'o'=>'11001000110',
            'p'=>'11000100010', 'q'=>'10110100100', 'r'=>'10110010100', 's'=>'10110010010',
            't'=>'10011010100', 'u'=>'10011010010', 'v'=>'10011000100', 'w'=>'10111010010',
            'x'=>'10111000010', 'y'=>'10010111010', 'z'=>'10010111000', '{'=>'10000110100',
            '|'=>'10000110010', '}'=>'10111101000', '~'=>'10111100010'
        );
        
        // START code B
        $barcode = '11010010000';
        $checksum = 104;
        $weight = 1;
        
        // Encode each character
        foreach (str_split($value) as $char) {
            if (isset($code128[$char])) {
                $code = array_search($code128[$char], $code128);
                $ascii = ord($char);
                $barcode .= $code128[$char];
                $checksum += ($ascii - 32) * $weight;
                $weight++;
            }
        }
        
        // Add checksum
        $checksum = $checksum % 103;
        $checksum_char = array_values($code128)[$checksum];
        $barcode .= $checksum_char;
        
        // STOP code
        $barcode .= '1100011101011';
        
        // Draw barcode
        $pos = $x;
        foreach (str_split($barcode) as $bar) {
            if ($bar == '1') {
                $this->Rect($pos, $y, $xres, $height, 'F');
            }
            $pos += $xres;
        }
    }
}

$pdf = new BatchPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Barcode at top RIGHT - Code 128 barcode format (Zebra LS-1203 compatible)
$pdf->Code128(120, 10, $batch['batch_number'], 8, 0.50);
$pdf->SetXY(130, 19);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(70, 5, $batch['batch_number'], 0, 1, 'C');
$pdf->Ln(5);

// Title Section
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'City Government of Tagbilaran', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, 'TAGBILARAN CITY', 0, 1, 'C');
$pdf->Cell(0, 7, 'FOOD VOUCHER PROGRAM', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'ACCREDITED VENDOR\'S CLAIM FORM', 0, 1, 'C');
$pdf->Ln(3);

// Horizontal line
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// Info Section
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, 'MARKET:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 7, $batch['area'] ? $batch['area'] : '____________________', 'B', 0);
$pdf->Cell(10, 7, '', 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, 'STALL NO.:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 7, $batch['stall_no'] ? $batch['stall_no'] : '____________________', 'B', 1);

$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 7, 'SECTION:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, $batch['section'] ? $batch['section'] : '____________________', 'B', 1);

$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 7, 'VENDOR\'S NAME:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, $batch['vendor_name'], 'B', 1);

$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(65, 7, 'CLAIM FORM CONTROL NO:', 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 0, 139);
$pdf->Cell(0, 7, $batch['batch_number'], 'B', 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(5);

// Voucher Table - 3 columns, 25 rows per column
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetDrawColor(100, 100, 100);

$col_x = [10, 73, 136];
$no_width = 12;
$code_width = 39;
$amt_width = 12;
// last column gets +1 width so all three columns span the full page width (190 mm)
$last_amt_width = 13;
$row_height = 6;
$rows_per_col = 25;
$max_per_page = $rows_per_col * 3;

$global_row = 1;
$remaining = $items;

while (count($remaining) > 0) {
    // Print headers for each column
    for ($c = 0; $c < 3; $c++) {
        $pdf->SetXY($col_x[$c], $pdf->GetY());
        $pdf->Cell($no_width, 7, 'NO.', 1, 0, 'C', true);
        $pdf->Cell($code_width, 7, 'VOUCHER CODE', 1, 0, 'C', true);
        $pdf->Cell($c == 2 ? $last_amt_width : $amt_width, 7, 'AMT', 1, 0, 'C', true);
    }
    $pdf->Ln(7);

    $page_items = array_slice($remaining, 0, $max_per_page);
    $remaining = array_slice($remaining, $max_per_page);

    // Build column data
    $col_data = [[], [], []];
    foreach ($page_items as $idx => $item) {
        $col_idx = (int) floor($idx / $rows_per_col);
        $col_data[$col_idx][] = $item;
    }

    // Pad each column to 25 rows
    for ($c = 0; $c < 3; $c++) {
        while (count($col_data[$c]) < $rows_per_col) {
            $col_data[$c][] = null;
        }
    }

    // Print rows
    $pdf->SetFont('Arial', '', 8);
    for ($row = 0; $row < $rows_per_col; $row++) {
        $base_y = $pdf->GetY();
        for ($c = 0; $c < 3; $c++) {
            $item = $col_data[$c][$row];
            $pdf->SetXY($col_x[$c], $base_y);
            if ($item) {
                $voucher_code = ($item['beneficiary_code'] ? $item['beneficiary_code'] : 'N/A') . ' - 00' . $item['voucher_number'];
                $pdf->Cell($no_width, $row_height, $global_row, 1, 0, 'C');
                $pdf->Cell($code_width, $row_height, $voucher_code, 1, 0, 'L');
                $pdf->Cell($c == 2 ? $last_amt_width : $amt_width, $row_height, number_format($item['amount'], 2), 1, 0, 'R');
                $global_row++;
            } else {
                $pdf->Cell($no_width, $row_height, '', 1, 0, 'C');
                $pdf->Cell($code_width, $row_height, '', 1, 0, 'L');
                $pdf->Cell($c == 2 ? $last_amt_width : $amt_width, $row_height, '', 1, 0, 'R');
            }
        }
        $pdf->SetY($base_y + $row_height);
    }

    if (count($remaining) > 0) {
        $pdf->AddPage();
    }
}

// Total row - reset X to left margin so it spans full page width
$pdf->SetXY(10, $pdf->GetY());
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 9, 'TOTAL AMOUNT TO BE CLAIMED: ' . number_format($batch['total_amount'], 2), 1, 1, 'C', true);

$pdf->Ln(5);

// Check if there's enough space for certifications on this page
$currentY = $pdf->GetY();

// If not enough space (less than 90mm remaining), add new page
if ($currentY > 187) { // A4 height 277mm - 90mm needed space = 187mm threshold
    $pdf->AddPage();
}

// Add section header
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'CERTIFICATION & SIGNATURE', 0, 1, 'C');
$pdf->Ln(3);

// ============================================================
// CERTIFICATION & SIGNATURE SECTION - TWO COLUMNS
// ============================================================
$colWidth = 90;
$lineHeight = 5;

$certStartY = $pdf->GetY();


// --- LEFT COLUMN: Certification A HEADER ---
$pdf->SetXY(10, $certStartY);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($colWidth, 6, 'A. Certification of Validating Officer', 0, 1);

// --- RIGHT COLUMN: Certification B HEADER ---
$pdf->SetXY(105, $certStartY);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($colWidth, 6, 'B. Certification of Accredited Vendor', 0, 1);

// --- LEFT COLUMN: Certification A TEXT ---
$certTextStartY = $pdf->GetY();
$pdf->SetXY(10, $certTextStartY);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell($colWidth - 2, $lineHeight, 'I hereby certify that the foregoing information is true and correct, and that all vouchers have been duly verified as issued by the vendor\'s authority and validity were examined as to their authenticity and validity.', 0, 'L');
$leftCertBottom = $pdf->GetY();

// --- RIGHT COLUMN: Certification B TEXT ---
$pdf->SetXY(105, $certTextStartY);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell($colWidth - 2, $lineHeight, 'I hereby certify that all food vouchers submitted herewith were accepted as payment for food items valid for payment of food items in the Tagbilaran Food Voucher Program.         I further certify that the vouchers are genuine, were received in the ordinary course of business, and have not been previously claimed for reimbursement.', 0, 'L');
$rightCertBottom = $pdf->GetY();

// Move to below the taller certification + spacing for signatures
$certMaxBottom = max($leftCertBottom, $rightCertBottom);
$pdf->SetY($certMaxBottom + 10);

$sigStartY = $pdf->GetY();

// --- LEFT COLUMN: Signature A ---
$pdf->SetXY(10, $sigStartY);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colWidth, 6, 'Validating Officer\'s Name and Signature', 0, 1, 'C');
$pdf->Line(15, $sigStartY + 18, 100, $sigStartY + 18);
$pdf->SetXY(10, $sigStartY + 20);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell($colWidth, 4, 'Print Name & Sign Above', 0, 1, 'C');

// --- RIGHT COLUMN: Signature B ---
$pdf->SetXY(105, $sigStartY);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colWidth, 6, 'Accredited Vendor\'s Name and Signature', 0, 1, 'C');
$pdf->Line(110, $sigStartY + 18, 195, $sigStartY + 18);
$pdf->SetXY(105, $sigStartY + 20);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell($colWidth, 4, 'Print Name & Sign Above', 0, 1, 'C');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetY($sigStartY + 28);

// Footer info
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y h:i A') . ' | Batch Status: ' . ucfirst($batch['status']) . ' | Total Vouchers: ' . $batch['total_vouchers'], 0, 1, 'C');

// Output PDF
$filename = 'Batch_' . $batch['batch_number'] . '.pdf';
$pdf->Output('I', $filename);
exit();
?>