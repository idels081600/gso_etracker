<?php
session_start();
require_once __DIR__ . '/rice_release_batches.php';
$conn = require(__DIR__ . '/config/database.php');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    die('Unauthorized');
}

require_once '../../fpdf/fpdf.php';
$wave = $_GET['wave'] ?? 'first_wave';
$batch = riceReleaseBatch($wave);
if (!$batch) {
    http_response_code(400);
    exit('Invalid rice batch.');
}
$isFirstBatch = $wave === 'first_wave';
$householdTable = $batch['households'];
$claimsTable = $batch['claims'];
$batchLabel = strtoupper($batch['label']);
session_write_close();

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
        FROM {$claimsTable} rvc
        INNER JOIN {$householdTable} rh ON rvc.household_id = rh.id
        WHERE rh.is_claimed = 1
        ORDER BY rvc.claim_date ASC, rh.household_name ASC, rvc.id ASC";
if ($isFirstBatch) {
    $sql .= ' LIMIT 5404';
}

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
define('RICE_CLAIMED_LINE_HEIGHT', 4);
define('RICE_CLAIMED_MIN_ROW_HEIGHT', 14);
define('RICE_CLAIMED_ROWS_PER_PAGE', 10);
define('RICE_CLAIMED_LEFT_MARGIN', 10);

class RiceClaimedDataPDF extends FPDF
{
    public $batchLabel = 'FIRST BATCH';

    function Header()
    {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'RICE CLAIMED DATA - ' . $this->batchLabel, 0, 1, 'C');
        $this->Ln(2);
        $this->drawTableHeader();
    }

    function Footer()
    {
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(RICE_CLAIMED_LEFT_MARGIN, -32);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(95, 5, 'CERTIFIED TRUE AND CORRECT', 0, 1, 'C');
        $this->SetX(RICE_CLAIMED_LEFT_MARGIN);
        $this->Ln(4);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(95, 5, 'CHRIS JOHN RENER G. TORRALBA', 0, 1, 'C');
        $this->SetX(RICE_CLAIMED_LEFT_MARGIN);
        $this->SetFont('Arial', '', 8);
        $this->Cell(95, 4, '(CGDH I - CGSO)', 0, 0, 'C');

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
$pdf->batchLabel = $batchLabel;
$pdf->AliasNbPages();
$pdf->SetMargins(RICE_CLAIMED_LEFT_MARGIN, 10, 10);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

if (empty($records)) {
    $pdf->Cell(RICE_CLAIMED_COL_NO + RICE_CLAIMED_COL_NAME + RICE_CLAIMED_COL_DATE + RICE_CLAIMED_COL_SIG, 10, 'No claimed rice data found.', 1, 1, 'C');
} else {
    foreach ($records as $index => $record) {
        if ($index > 0 && $index % RICE_CLAIMED_ROWS_PER_PAGE === 0) {
            $pdf->AddPage();
        }

        $rowNumber = (string)($index + 1);
        $name = riceClaimedPdfText($record['household_name']);
        $claimedDate = riceClaimedPdfText($record['claim_date']);

        $rowH = RICE_CLAIMED_MIN_ROW_HEIGHT;

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

        if (!empty($record['e_signature']) && preg_match('/^data:image\/[^;]+;base64,(.+)$/s', $record['e_signature'], $signatureMatch)) {
            $imageData = base64_decode($signatureMatch[1], true);
            $imageInfo = $imageData !== false && function_exists('getimagesizefromstring')
                ? @getimagesizefromstring($imageData)
                : false;
            $supportedTypes = [
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_GIF => 'gif',
            ];
            $imageType = $imageInfo !== false && isset($supportedTypes[$imageInfo[2]])
                ? $supportedTypes[$imageInfo[2]]
                : null;

            if ($imageType !== null) {
                $tempFile = @tempnam(__DIR__, 'rice_claim_sig_');
                if ($tempFile !== false && @file_put_contents($tempFile, $imageData) !== false) {
                    try {
                        $maxImgW = 95;
                        $maxImgH = $rowH - 1;
                        $imageRatio = $imageInfo[0] / max(1, $imageInfo[1]);
                        $imgW = min($maxImgW, $maxImgH * $imageRatio);
                        $imgH = min($maxImgH, $imgW / max(0.01, $imageRatio));
                        $imgX = $xSig + (RICE_CLAIMED_COL_SIG - $imgW) / 2;
                        $imgY = $rowY + ($rowH - $imgH) / 2;
                        $pdf->Image($tempFile, $imgX, $imgY, $imgW, $imgH, $imageType);
                    } catch (Throwable $exception) {
                        // A malformed signature must not stop the rest of the export.
                    } finally {
                        @unlink($tempFile);
                    }
                } elseif ($tempFile !== false) {
                    @unlink($tempFile);
                }
            }
        }

        $pdf->SetXY($rowX, $rowY + $rowH);
    }
}

$filename = 'Rice_Claimed_Data_' . str_replace(' ', '_', $batch['label']) . '_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
exit();
?>
