<?php
session_start();

require_once __DIR__ . '/logi_db.php';
require_once __DIR__ . '/Logi_security.php';
require_once __DIR__ . '/fpdf/fpdf.php';

logi_require_admin_page($conn);

function inventory_pdf_text($value)
{
    $text = (string)$value;
    $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    return $converted === false ? $text : $converted;
}

class InventoryCountSheetPdf extends FPDF
{
    private $columnWidths = array(12, 32, 153, 30, 50);

    public function Header()
    {
        $this->SetFillColor(23, 107, 58);
        $this->Rect(0, 0, 297, 7, 'F');

        $this->SetY(12);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(23, 33, 28);
        $this->Cell(0, 8, 'Inventory Physical Count Sheet', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(95, 108, 100);
        $this->Cell(0, 5, 'Items are arranged alphabetically. Write the actual count in the blank Balance column.', 0, 1, 'C');
        $this->Cell(0, 5, 'Printed: ' . date('F j, Y'), 0, 1, 'C');
        $this->Ln(3);

        $headers = array('No.', 'Stock No.', 'Item', 'Unit', 'Balance');
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(234, 242, 236);
        $this->SetTextColor(23, 33, 28);
        foreach ($headers as $index => $header) {
            $this->Cell($this->columnWidths[$index], 8, $header, 1, 0, 'C', true);
        }
        $this->Ln();
    }

    public function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(95, 108, 100);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    private function wrappedLineCount($width, $text)
    {
        $text = str_replace("\r", '', (string)$text);
        if ($text === '') {
            return 1;
        }

        $availableWidth = $width - 4;
        $words = preg_split('/\s+/', trim($text));
        $lines = 1;
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->GetStringWidth($candidate) <= $availableWidth) {
                $line = $candidate;
            } else {
                $lines++;
                $line = $word;
            }
        }
        return $lines;
    }

    public function InventoryRow($number, $itemNo, $itemName, $unit)
    {
        $lineHeight = 6;
        $itemLines = $this->wrappedLineCount($this->columnWidths[2], $itemName);
        $rowHeight = max(8, $itemLines * $lineHeight);

        if ($this->GetY() + $rowHeight > $this->GetPageHeight() - 13) {
            $this->AddPage();
        }

        $x = $this->GetX();
        $y = $this->GetY();
        $values = array($number, $itemNo, $itemName, $unit, '');
        $alignments = array('C', 'C', 'L', 'C', 'C');

        $this->SetDrawColor(205, 214, 208);
        foreach ($values as $index => $value) {
            $width = $this->columnWidths[$index];
            $this->Rect($x, $y, $width, $rowHeight);
            $this->SetXY($x, $y + 1);
            $this->MultiCell($width, $lineHeight, $value, 0, $alignments[$index]);
            $x += $width;
        }
        $this->SetXY(10, $y + $rowHeight);
    }
}

$query = "SELECT item_no, item_name, unit
          FROM inventory_items
          ORDER BY LOWER(TRIM(item_name)) ASC, item_no ASC";
$result = mysqli_query($conn, $query);
if (!$result) {
    http_response_code(500);
    exit('Unable to prepare the inventory list.');
}

$pdf = new InventoryCountSheetPdf('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 13);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(23, 33, 28);

$number = 1;
while ($item = mysqli_fetch_assoc($result)) {
    $pdf->InventoryRow(
        (string)$number,
        inventory_pdf_text($item['item_no']),
        inventory_pdf_text($item['item_name']),
        inventory_pdf_text($item['unit'])
    );
    $number++;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Inventory_Physical_Count_Sheet.pdf"');
$pdf->Output('I', 'Inventory_Physical_Count_Sheet.pdf');
