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

function ricePdfText($text)
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    return $converted !== false ? $converted : $text;
}

$sql = "SELECT rh.household_code,
               rh.household_name,
               rh.address,
               rvc.e_signature
        FROM rice_households rh
        INNER JOIN rice_voucher_claims rvc ON rvc.household_id = rh.id
        WHERE rh.is_claimed = 1
        ORDER BY rh.address ASC, rh.household_code ASC";

$result = mysqli_query($conn, $sql);
$records = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}

define('RICE_COL_CODE', 35);
define('RICE_COL_NAME', 70);
define('RICE_COL_BRGY', 55);
define('RICE_COL_SIG', 110);
define('RICE_LINE_HEIGHT', 5);
define('RICE_MIN_ROW_HEIGHT', 20);
define('RICE_LEFT_MARGIN', 10);

class RiceBeneficiariesPDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'RICE ASSISTANCE', 0, 1, 'C');
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
        $this->SetXY(RICE_LEFT_MARGIN, $this->GetY());
        $this->Cell(RICE_COL_CODE, 10, 'Household Code', 1, 0, 'C', true);
        $this->Cell(RICE_COL_NAME, 10, 'Name', 1, 0, 'C', true);
        $this->Cell(RICE_COL_BRGY, 10, 'Barangay', 1, 0, 'C', true);
        $this->Cell(RICE_COL_SIG, 10, 'Signature', 1, 1, 'C', true);
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

    function calcRowHeight($txt, $colW, $lineH = RICE_LINE_HEIGHT, $minH = RICE_MIN_ROW_HEIGHT)
    {
        $innerW = $colW - 6;
        $lines = $this->wordWrapText($txt, $innerW);
        $needed = count($lines) * $lineH + 10;
        return max($minH, $needed);
    }

    function fixedCell($x, $y, $w, $h, $txt, $lineH = RICE_LINE_HEIGHT)
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

$pdf = new RiceBeneficiariesPDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(RICE_LEFT_MARGIN, 10, 10);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

if (empty($records)) {
    $pdf->Cell(RICE_COL_CODE + RICE_COL_NAME + RICE_COL_BRGY + RICE_COL_SIG, 10, 'No claimed rice beneficiaries found.', 1, 1, 'C');
} else {
    foreach ($records as $record) {
        $code = ricePdfText($record['household_code']);
        $name = ricePdfText($record['household_name']);
        $barangay = ricePdfText($record['address']);

        $h1 = $pdf->calcRowHeight($code, RICE_COL_CODE);
        $h2 = $pdf->calcRowHeight($name, RICE_COL_NAME);
        $h3 = $pdf->calcRowHeight($barangay, RICE_COL_BRGY);
        $rowH = max($h1, $h2, $h3, RICE_MIN_ROW_HEIGHT);

        if ($pdf->GetY() + $rowH > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
        }

        $rowX = RICE_LEFT_MARGIN;
        $rowY = $pdf->GetY();
        $xCode = $rowX;
        $xName = $xCode + RICE_COL_CODE;
        $xBrgy = $xName + RICE_COL_NAME;
        $xSig = $xBrgy + RICE_COL_BRGY;

        $pdf->fixedCell($xCode, $rowY, RICE_COL_CODE, $rowH, $code);
        $pdf->fixedCell($xName, $rowY, RICE_COL_NAME, $rowH, $name);
        $pdf->fixedCell($xBrgy, $rowY, RICE_COL_BRGY, $rowH, $barangay);
        $pdf->Rect($xSig, $rowY, RICE_COL_SIG, $rowH);

        if (!empty($record['e_signature']) && strpos($record['e_signature'], 'data:image') === 0) {
            preg_match('/data:image\/(\w+);base64,/', $record['e_signature'], $typeMatch);
            $imageType = isset($typeMatch[1]) ? strtolower($typeMatch[1]) : 'png';
            $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $record['e_signature']);
            $imageData = base64_decode($base64String);

            if ($imageData !== false) {
                $tempFile = tempnam(sys_get_temp_dir(), 'rice_sig_') . '.' . $imageType;
                file_put_contents($tempFile, $imageData);

                $imgW = 60;
                $imgH = min(16, $rowH - 4);
                $imgX = $xSig + (RICE_COL_SIG - $imgW) / 2;
                $imgY = $rowY + ($rowH - $imgH) / 2;
                $pdf->Image($tempFile, $imgX, $imgY, $imgW, $imgH);
                @unlink($tempFile);
            }
        }

        $pdf->SetXY($rowX, $rowY + $rowH);
    }
}

$filename = 'Rice_Beneficiaries_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
exit();
?>
