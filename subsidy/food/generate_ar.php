<?php

/**
 * FIXED: Food Voucher Receipt PDF Generator
 * Supports BOLD + FULL JUSTIFICATION + FIRST LINE INDENT
 */

require_once('../../fpdf/fpdf.php');

class VoucherReceiptPDF extends FPDF
{
    private $headerImage;

    public function __construct($headerImage = 'header.png')
    {
        parent::__construct();
        $this->headerImage = $headerImage;
    }

    public function Header()
    {
        if (file_exists($this->headerImage)) {
            $this->Image($this->headerImage, 10, 8, 190);
            $this->Ln(45);
        } else {
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 10, '[Header Image Not Found]', 0, 1, 'C');
        }
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    /**
     * Advanced MultiCell with Bold tags, Justification, and Indentation
     */
    function MultiCellTag($w, $h, $txt, $align = 'J', $indent = 0)
    {
        $xStart = $this->GetX();
        $w = ($w == 0) ? ($this->w - $this->rMargin - $this->lMargin) : $w;

        $paragraphs = explode("\n", $txt);
        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/(<b>|<\/b>|\s+)/', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            $line = [];
            $lineWidth = $indent; // Start the first line with the indent width
            $isFirstLine = true;

            foreach ($words as $word) {
                if ($word == '<b>' || $word == '</b>') {
                    $line[] = $word;
                    continue;
                }
                if (trim($word) == '') continue;

                $this->SetFont('', (in_array('<b>', $line) && !in_array('</b>', array_slice($line, array_search('<b>', $line)))) ? 'B' : '');
                $wordWidth = $this->GetStringWidth($word . ' ');

                if ($lineWidth + $wordWidth > $w) {
                    // Print current line
                    $this->PrintTagLine($w, $h, $line, $align, ($isFirstLine ? $indent : 0));

                    // Reset for next line
                    $line = (in_array('<b>', $line) && !in_array('</b>', array_slice($line, array_search('<b>', $line)))) ? ['<b>'] : [];
                    $lineWidth = 0;
                    $isFirstLine = false;
                }

                $line[] = $word;
                $lineWidth += $wordWidth;
            }
            // Print the last line of the paragraph (always left aligned)
            $this->PrintTagLine($w, $h, $line, 'L', ($isFirstLine ? $indent : 0));
        }
    }

    private function PrintTagLine($w, $h, $line, $align, $currentIndent)
    {
        $this->SetX($this->lMargin + $currentIndent);

        $totalTextWidth = 0;
        $wordCount = 0;
        $isBold = false;

        foreach ($line as $element) {
            if ($element == '<b>') {
                $isBold = true;
                continue;
            }
            if ($element == '</b>') {
                $isBold = false;
                continue;
            }
            $this->SetFont('', $isBold ? 'B' : '');
            $totalTextWidth += $this->GetStringWidth($element);
            $wordCount++;
        }

        $availableWidth = $w - $currentIndent;
        $spacing = 0;
        if ($align == 'J' && $wordCount > 1) {
            $spacing = ($availableWidth - $totalTextWidth) / ($wordCount - 1);
        } else {
            $spacing = $this->GetStringWidth(' ');
        }

        $isBold = false;
        foreach ($line as $element) {
            if ($element == '<b>') {
                $isBold = true;
                continue;
            }
            if ($element == '</b>') {
                $isBold = false;
                continue;
            }

            $this->SetFont('', $isBold ? 'B' : '');
            $this->Cell($this->GetStringWidth($element), $h, $element, 0, 0, 'L');
            $this->SetX($this->GetX() + $spacing);
        }
        $this->Ln($h);
    }
}

// Amount to Words Converter
function numberToWordsPesos($number)
{
    $dictionary = array(0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety', 100 => 'hundred', 1000 => 'thousand', 1000000 => 'million');
    if (!is_numeric($number)) return false;
    $string = $fraction = null;
    if (strpos($number, '.') !== false) list($number, $fraction) = explode('.', $number);
    if ($number < 21) $string = $dictionary[$number];
    elseif ($number < 100) {
        $tens = ((int) ($number / 10)) * 10;
        $units = $number % 10;
        $string = $dictionary[$tens] . ($units ? '-' . $dictionary[$units] : '');
    } elseif ($number < 1000) {
        $hundreds = (int)($number / 100);
        $remainder = $number % 100;
        $string = $dictionary[$hundreds] . ' hundred' . ($remainder ? ' and ' . numberToWordsPesos($remainder) : '');
    } else {
        $baseUnit = pow(1000, floor(log($number, 1000)));
        $numBaseUnits = (int) ($number / $baseUnit);
        $remainder = $number % $baseUnit;
        $string = numberToWordsPesos($numBaseUnits) . ' ' . $dictionary[$baseUnit] . ($remainder ? ', ' . numberToWordsPesos($remainder) : '');
    }
    if (null !== $fraction && is_numeric($fraction)) {
        $fraction = (int) substr(str_pad($fraction, 2, '0'), 0, 2);
        if ($fraction > 0) $string .= ' and ' . numberToWordsPesos($fraction) . ' centavos';
    }
    return ucwords($string);
}

function generateAndDownloadReceipt($data = array())
{
    $pdf = new VoucherReceiptPDF('header.png');
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);

    // Titles
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'TAGBILARAN CITY', 0, 1, 'C');
    $pdf->Cell(0, 4, 'FOOD VOUCHER PROGRAM', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'ACKNOWLEDGMENT RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);

    // Metadata
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'A R NO: ' . $data['ar_no'], 0, 0);
    $pdf->SetX(150);
    $pdf->Cell(0, 7, 'Date: ' . $data['date'], 0, 1);
    $pdf->Ln(5);

    // --- INDENTATION SETTING ---
    $indentWidth = 12; // 12mm indentation
    $pdf->SetFont('Arial', '', 10);
    $amount_text = number_format($data['amount'], 2);
    $amount_words = numberToWordsPesos($data['amount']);

    $text = "Received from the <b>City Government of Tagbilaran the total amount of (" . $amount_words . " Pesos)  P" . $amount_text . " </b> as payment/reimbursement for food vouchers accepted by the undersigned as an accredited vendor under the City's Food Voucher Program, covered by <b>Claim Form with Control Number " . $data['claim_form'] . "</b>.";

    $pdf->MultiCellTag(0, 5, $text, 'J', $indentWidth);
    $pdf->Ln(5);

    $text2 = "The undersigned hereby acknowledges that the said amount corresponds to the value of food vouchers duly submitted, examined, and approved for reimbursement in accordance with existing guidelines.";
    $pdf->MultiCellTag(0, 5, $text2, 'J', $indentWidth);
    $pdf->Ln(5);

    // Certifications
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'The undersigned further certifies that:', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $certs = [
        '1. The vouchers reimbursed have not been previously claimed or paid;',
        '2. The amount received is correct and in full settlement of the claim; and',
        '3. This receipt is issued voluntarily for all legal intents and purposes.'
    ];
    foreach ($certs as $cert) {
        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->MultiCell(0, 6, $cert, 0, 'L');
    }

    $pdf->Ln(15);

    // Signatures
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(80, 7, 'Received by:', 0, 0);
    $pdf->SetX(130);
    $pdf->Cell(60, 7, 'Issued by:', 0, 1);
    $pdf->Ln(12);

    $pdf->Cell(80, 0.1, '', 'T', 0);
    $pdf->SetX(130);
    $pdf->Cell(60, 0.1, '', 'T', 1);
    $pdf->Ln(-5);

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(80, 5, strtoupper($data['vendor_name'] ?? 'VENDOR NAME'), 0, 0);
    $pdf->SetX(130);
    $pdf->Cell(60, 5, "HUBERT M. INAS CPA, BCLTE", 0, 1);

    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(80, 5, "VENDOR'S NAME", 0, 0);
    $pdf->SetX(130);
    $pdf->Cell(60, 5, "City Treasurer's Office", 0, 1);

    $pdf->Output('I', 'receipt.pdf');
}

// Handle GET request from batch system
if (isset($_GET['batch_id'])) {
    require_once 'db_fuel.php';

    $batch_id = (int)$_GET['batch_id'];

    // Get batch information from database
    $batch_sql = "SELECT b.batch_number, b.total_amount, b.created_at, v.vendor_name
                  FROM food_redemption_batches b
                  LEFT JOIN food_vendors v ON b.vendor_id = v.id
                  WHERE b.id = ? LIMIT 1";

    $stmt = mysqli_prepare($conn, $batch_sql);
    mysqli_stmt_bind_param($stmt, 'i', $batch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $batch = mysqli_fetch_assoc($result);

        // Generate AR automatically
        $ar_data = [
            'ar_no' => 'AR-' . date('Ymd') . '-' . str_pad($batch_id, 4, '0', STR_PAD_LEFT),
            'date' => date('Y-m-d', strtotime($batch['created_at'])),
            'amount' => $batch['total_amount'],
            'vendor_name' => $batch['vendor_name'],
            'claim_form' => $batch['batch_number']
        ];

        generateAndDownloadReceipt($ar_data);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    generateAndDownloadReceipt($_POST);
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Receipt Generator</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            padding: 50px;
        }

        form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 450px;
        }

        h2 {
            text-align: center;
            color: #1a73e8;
            margin-bottom: 25px;
        }

        label {
            font-weight: bold;
            font-size: 13px;
            color: #555;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <form method="POST">
        <h2>Receipt Details</h2>
        <label>A R Number</label>
        <input type="text" name="ar_no" required>
        <label>Date</label>
        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
        <label>Amount (PHP)</label>
        <input type="number" name="amount" step="0.01" required>
        <label>Vendor Name</label>
        <input type="text" name="vendor_name" required>
        <label>Claim Form Control No.</label>
        <input type="text" name="claim_form" required>
        <button type="submit">Generate Indented PDF</button>
    </form>
</body>

</html>