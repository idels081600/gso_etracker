<?php
/** @var mysqli $conn */
session_start();
$conn = require(__DIR__ . '/config/database.php');
require_once '../../vendor/autoload.php';

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized');
}

// Helper function to handle UTF-8 (TCPDF handles this natively)
function sanitizeText($str) {
    return $str ? trim($str) : '';
}

// Get validating officer name from session
$validating_officer_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';

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

// Get batch items in user selection order
$items_sql = "SELECT 
                bi.id,
                bi.voucher_id,
                bi.amount,
                bi.beneficiary_name,
                bi.beneficiary_code,
                bi.voucher_number,
                bi.selection_order,
                vc.claimant_name
            FROM food_redemption_items bi
            LEFT JOIN food_voucher_claims vc ON bi.voucher_id = vc.id
            WHERE bi.batch_id = $batch_id
            ORDER BY bi.selection_order ASC, bi.id ASC";

$items_result = mysqli_query($conn, $items_sql);

$items = [];
if ($items_result) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $items[] = $row;
    }
}

// Create PDF with TCPDF (handles UTF-8 natively)
class BatchPDF extends TCPDF {
    
    private $batch_number = '';
    
    public function setBatchNumber($number) {
        $this->batch_number = $number;
    }
    
    public function Header() {
        // Custom header - intentionally empty for this design
        // We'll add barcode in the main body
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new BatchPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetDefaultMonospacedFont('courier');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

$pdf->setBatchNumber($batch['batch_number']);

// ============================================================
// BARCODE AT TOP - CODE39 FORMAT (Using TCPDF's write1DBarcode)
// ============================================================
$barcode_y = $pdf->GetY();
$pdf->write1DBarcode($batch['batch_number'], 'C128', 10, $barcode_y, 100, 12, 0.4, array('text' => ''), 'C');

$pdf->SetXY(-240, $barcode_y + 13);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(70, 5, $batch['batch_number'], 0, 1, 'C');
$pdf->Ln(5);

// ============================================================
// TITLE SECTION
// ============================================================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'City Government of Tagbilaran', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 7, 'TAGBILARAN CITY', 0, 1, 'C');
$pdf->Cell(0, 7, 'FOOD VOUCHER PROGRAM', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 7, 'ACCREDITED VENDOR\'S CLAIM FORM', 0, 1, 'C');
$pdf->Ln(3);

// ============================================================
// HORIZONTAL LINE
// ============================================================
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// ============================================================
// INFO SECTION
// ============================================================
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(35, 7, 'MARKET:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$market_name = sanitizeText($batch['area']) ? $batch['area'] : '____________________';
$stall_no = sanitizeText($batch['stall_no']) ? $batch['stall_no'] : '____________________';

$market_max_width = 60;
$market_full_width = 115;
$market_font_size = 10;
$min_market_font_size = 7;
while ($market_font_size > $min_market_font_size &&
       $pdf->getStringWidth($market_name, 'helvetica', '', $market_font_size) > $market_max_width) {
    $market_font_size -= 0.5;
}
$pdf->SetFont('helvetica', '', $market_font_size);

if ($pdf->getStringWidth($market_name, 'helvetica', '', $market_font_size) <= $market_max_width) {
    $pdf->Cell($market_max_width, 7, $market_name, 'B', 0);
    $pdf->Cell(10, 7, '', 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 7, 'STALL NO.:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 7, $stall_no, 'B', 1);
} else {
    $pdf->MultiCell($market_full_width, 7, $market_name, 'B', 'L', false, 1);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 7, 'STALL NO.:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 7, $stall_no, 'B', 1);
}

$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 7, 'SECTION:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$section = sanitizeText($batch['section']) ? $batch['section'] : '____________________';
$pdf->Cell(0, 7, $section, 'B', 1);

$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 7, 'VENDOR\'S NAME:', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, sanitizeText($batch['vendor_name']), 'B', 1);

$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(65, 7, 'CLAIM FORM CONTROL NO:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 139);
$pdf->Cell(0, 7, $batch['batch_number'], 'B', 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(5);

// ============================================================
// VOUCHER TABLE - 3 COLUMNS, 25 ROWS PER COLUMN
// ============================================================
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetDrawColor(100, 100, 100);

$col_x = [10, 73, 136];
$no_width = 12;
$code_width = 39;
$amt_width = 12;
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
    $pdf->SetFont('helvetica', '', 8);
    for ($row = 0; $row < $rows_per_col; $row++) {
        $base_y = $pdf->GetY();
        for ($c = 0; $c < 3; $c++) {
            $item = $col_data[$c][$row];
            $pdf->SetXY($col_x[$c], $base_y);
            if ($item) {
                $voucher_code = ($item['beneficiary_code'] ? $item['beneficiary_code'] : 'N/A') . ' - 00' . $item['voucher_number'];
                // Show selection order as indicator (e.g., in NO. column)
                $pdf->Cell($no_width, $row_height, isset($item['selection_order']) ? $item['selection_order'] : $global_row, 1, 0, 'C');
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

// ============================================================
// TOTAL ROW
// ============================================================
$pdf->SetXY(10, $pdf->GetY());
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 9, 'TOTAL AMOUNT TO BE CLAIMED: ' . number_format($batch['total_amount'], 2), 1, 1, 'C', true);

$pdf->Ln(5);

// ============================================================
// PAGE BREAK CHECK
// ============================================================
$currentY = $pdf->GetY();
// If not enough space (less than 90mm remaining), add new page
// A4 height is approximately 277mm, so 187mm is a good threshold
if ($currentY > 187) {
    $pdf->AddPage();
}

// ============================================================
// CERTIFICATION & SIGNATURE SECTION HEADER
// ============================================================
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'CERTIFICATION & SIGNATURE', 0, 1, 'C');
$pdf->Ln(3);

$colWidth = 90;
$lineHeight = 5;

$certStartY = $pdf->GetY();

// --- LEFT COLUMN: Certification A HEADER ---
$pdf->SetXY(10, $certStartY);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell($colWidth, 6, 'A. Certification of Validating Officer', 0, 1);

// --- RIGHT COLUMN: Certification B HEADER ---
$pdf->SetXY(105, $certStartY);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell($colWidth, 6, 'B. Certification of Accredited Vendor', 0, 1);

// --- LEFT COLUMN: Certification A TEXT ---
$certTextStartY = $pdf->GetY();
$pdf->SetXY(10, $certTextStartY);
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell($colWidth - 2, $lineHeight, 'I hereby certify that the foregoing information is true and correct, and that all vouchers have been duly verified as issued by the vendor\'s authority and validity were examined as to their authenticity and validity.', 0, 'L');
$leftCertBottom = $pdf->GetY();

// --- RIGHT COLUMN: Certification B TEXT ---
$pdf->SetXY(105, $certTextStartY);
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell($colWidth - 2, $lineHeight, 'I hereby certify that all food vouchers submitted herewith were accepted as payment for food items valid for payment of food items in the Tagbilaran Food Voucher Program. I further certify that the vouchers are genuine, were received in the ordinary course of business, and have not been previously claimed for reimbursement.', 0, 'L');
$rightCertBottom = $pdf->GetY();

// Move to below the taller certification + spacing for signatures
$certMaxBottom = max($leftCertBottom, $rightCertBottom);
$pdf->SetY($certMaxBottom + 10);

$sigStartY = $pdf->GetY();

// --- LEFT COLUMN: Signature A ---
$pdf->SetXY(10, $sigStartY);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colWidth, 6, 'Validating Officer\'s Name and Signature', 0, 1, 'C');
$pdf->SetXY(10, $sigStartY + 12);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colWidth, 4, $validating_officer_name, 0, 1, 'C');
$pdf->Line(15, $sigStartY + 20, 100, $sigStartY + 20);

// --- RIGHT COLUMN: Signature B ---
$pdf->SetXY(105, $sigStartY);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colWidth, 6, 'Accredited Vendor\'s Name and Signature', 0, 1, 'C');
$pdf->Line(110, $sigStartY + 18, 195, $sigStartY + 18);
$pdf->SetXY(105, $sigStartY + 20);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell($colWidth, 4, 'Print Name & Sign Above', 0, 1, 'C');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetY($sigStartY + 28);

// ============================================================
// FOOTER INFO
// ============================================================
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y h:i A') . ' | Batch Status: ' . ucfirst($batch['status']) . ' | Total Vouchers: ' . $batch['total_vouchers'], 0, 1, 'C');

// ============================================================
// OUTPUT PDF
// ============================================================
$filename = 'Batch_' . $batch['batch_number'] . '.pdf';
$pdf->Output($filename, 'I'); // 'I' = display inline
exit();
?>