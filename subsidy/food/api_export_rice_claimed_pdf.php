<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    die('Unauthorized');
}

require_once '../../fpdf/fpdf.php';

function riceClaimedPdfText($text)
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    return $converted !== false ? $converted : $text;
}

$sql = "SELECT rh.household_name,
               rvc.claim_date,
               rvc.e_signature
        FROM rice_voucher_claims rvc
        INNER JOIN rice_households rh ON rvc.household_id = rh.id
        WHERE rh.is_claimed = 1
        ORDER BY rvc.claim_date ASC, rh.household_name ASC";

$result = mysqli_query($conn, $sql);
$records = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}

define('RICE_CLAIMED_COL_NO', 20);
define('RICE_CLAIMED_COL_NAME', 70);
define('RICE_CLAIMED_COL_DATE', 50);
define('RICE_CLAIMED_COL_SIG', 130);
define('RICE_CLAIMED_LINE_HEIGHT', 5);
define('RICE_CLAIMED_MIN_ROW_HEIGHT', 20);
define('RICE_CLAIMED_LEFT_MARGIN', 10);

class RiceClaimedDataPDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'RICE CLAIMED DATA', 0, 1, 'C');
        $this->Ln(2);
        $this->drawTableHeader();
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function drawTableHeader()
    {
        $this->SetFillColor(200, 200, 200);
        $this->SetFont('Arial', 'B', 10);
        $this->SetXY(RICE_CLAIMED_LEFT_MARGIN, $this->GetY());
        $this->Cell(RICE_CLAIMED_COL_NO, 10, '#', 1, 0, 'C', true);
        $this->Cell(RICE_CLAIMED_COL_NAME, 10, 'Name', 1, 0, 'C', true);
        $this->Cell(RICE_CLAIMED_COL_DATE, 10, 'Claimed Date', 1, 0, 'C', true);
        $this->Cell(RICE_CLAIMED_COL_SIG, 10, 'Signature', 1, 1, 'C', true);
        $this->SetFont('Arial', '', 9);
    }

    function wordWrapText($txt, $maxW)
    {
        $words = explode(' ', $txt);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if ($this->GetStringWidth($test) <= $maxW) {
                $current = $test;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    function calcRowHeight($txt, $colW, $lineH = RICE_CLAIMED_LINE_HEIGHT, $minH = RICE_CLAIMED_MIN_ROW_HEIGHT)
    {
        $innerW = $colW - 6;
        $lines = $this->wordWrapText($txt, $innerW);
        $needed = count($lines) * $lineH + 10;
        return max($minH, $needed);
    }

    function fixedCell($x, $y, $w, $h, $txt, $lineH = RICE_CLAIMED_LINE_HEIGHT)
    {
        $this->Rect($x, $y, $w, $h);

        if ($txt === '') {
            return;
        }

        $innerW = $w - 6;
        $lines = $this->wordWrapText($txt, $innerW);
        $blockH = count($lines) * $lineH;
        $startY = $y + ($h - $blockH) / 2;

        foreach ($lines as $i => $line) {
            $lineY = $startY + ($i * $lineH);
            $this->SetXY($x, $lineY);
            $this->Cell($w, $lineH, $line, 0, 0, 'C');
        }
    }
}

$pdf = new RiceClaimedDataPDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(RICE_CLAIMED_LEFT_MARGIN, 10, 10);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

if (empty($records)) {
    $pdf->Cell(RICE_CLAIMED_COL_NO + RICE_CLAIMED_COL_NAME + RICE_CLAIMED_COL_DATE + RICE_CLAIMED_COL_SIG, 10, 'No claimed rice data found.', 1, 1, 'C');
} else {
    foreach ($records as $index => $record) {
        $rowNumber = (string)($index + 1);
        $name = riceClaimedPdfText($record['household_name']);
        $claimedDate = riceClaimedPdfText($record['claim_date']);

        $h1 = $pdf->calcRowHeight($rowNumber, RICE_CLAIMED_COL_NO);
        $h2 = $pdf->calcRowHeight($name, RICE_CLAIMED_COL_NAME);
        $h3 = $pdf->calcRowHeight($claimedDate, RICE_CLAIMED_COL_DATE);
        $rowH = max($h1, $h2, $h3, RICE_CLAIMED_MIN_ROW_HEIGHT);

        if ($pdf->GetY() + $rowH > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
        }

        $rowX = RICE_CLAIMED_LEFT_MARGIN;
        $rowY = $pdf->GetY();
        $xNo = $rowX;
        $xName = $xNo + RICE_CLAIMED_COL_NO;
        $xDate = $xName + RICE_CLAIMED_COL_NAME;
        $xSig = $xDate + RICE_CLAIMED_COL_DATE;

        $pdf->fixedCell($xNo, $rowY, RICE_CLAIMED_COL_NO, $rowH, $rowNumber);
        $pdf->fixedCell($xName, $rowY, RICE_CLAIMED_COL_NAME, $rowH, $name);
        $pdf->fixedCell($xDate, $rowY, RICE_CLAIMED_COL_DATE, $rowH, $claimedDate);
        $pdf->Rect($xSig, $rowY, RICE_CLAIMED_COL_SIG, $rowH);

        if (!empty($record['e_signature']) && strpos($record['e_signature'], 'data:image') === 0) {
            preg_match('/data:image\/(\w+);base64,/', $record['e_signature'], $typeMatch);
            $imageType = isset($typeMatch[1]) ? strtolower($typeMatch[1]) : 'png';
            $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $record['e_signature']);
            $imageData = base64_decode($base64String);

            if ($imageData !== false) {
                $tempFile = tempnam(sys_get_temp_dir(), 'rice_claim_sig_') . '.' . $imageType;
                file_put_contents($tempFile, $imageData);

                $imgW = 72;
                $imgH = min(16, $rowH - 4);
                $imgX = $xSig + (RICE_CLAIMED_COL_SIG - $imgW) / 2;
                $imgY = $rowY + ($rowH - $imgH) / 2;
                $pdf->Image($tempFile, $imgX, $imgY, $imgW, $imgH);
                @unlink($tempFile);
            }
        }

        $pdf->SetXY($rowX, $rowY + $rowH);
    }
}

$filename = 'Rice_Claimed_Data_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
exit();
?>
