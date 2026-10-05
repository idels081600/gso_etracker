<?php
session_start();
$conn = require __DIR__ . '/config/database.php';

if (!isset($_SESSION['username'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login_v2.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    die('Unauthorized');
}

require_once '../../fpdf/fpdf.php';
require_once __DIR__ . '/rice_recipient_form_pdf.php';

const RICE_MASTER_LIMIT = 5404;
const RICE_MASTER_MARGIN = 12;
const RICE_MASTER_NO_WIDTH = 20;
const RICE_MASTER_NAME_WIDTH = 105;
const RICE_MASTER_BARANGAY_WIDTH = 55;
const RICE_MASTER_SIGNATURE_WIDTH = 93;
const RICE_MASTER_ROW_HEIGHT = 6;

function riceMasterPdfText($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    return $converted !== false ? $converted : $text;
}

function riceMasterAddressLabel($address)
{
    $address = trim((string) $address);
    $normalizedAddress = strtoupper(preg_replace('/\s+/', ' ', $address));
    $lowIncomeLabels = [
        '',
        'TAGBI',
        'B2',
        'B3',
        'B4',
        'B5',
        'HONEST DRIVER',
        'HONEST DRIVERS',
        'JO',
        'PORTER',
        'URBAN POOR',
        'IND',
        'IND2',
        'INDIGENT',
        'INDIGENTS',
    ];

    if (in_array($normalizedAddress, $lowIncomeLabels, true)) {
        return 'LOW INCOME';
    }

    return $address;
}

$referenceRecords = [];
$referenceResult = mysqli_query(
    $conn,
    'SELECT id, household_code, first_name, last_name, middle_name, designation
     FROM rice_claimed_households
     ORDER BY id ASC'
);
if (!$referenceResult) {
    http_response_code(500);
    die('Unable to load the rice master-list reference data.');
}
while ($reference = mysqli_fetch_assoc($referenceResult)) {
    $codeKey = strtoupper(trim((string) $reference['household_code']));
    if ($codeKey !== '' && !isset($referenceRecords[$codeKey])) {
        $referenceRecords[$codeKey] = $reference;
    }
}

$sql = 'SELECT rh.id,
               rh.household_code,
               rh.household_name,
               rh.first_name,
               rh.last_name,
               rvc.claim_date,
               rvc.id AS claim_id
        FROM rice_voucher_claims rvc
        INNER JOIN rice_households rh ON rh.id = rvc.household_id
        WHERE rh.is_claimed = 1
        ORDER BY rvc.claim_date ASC, rh.household_name ASC, rvc.id ASC
        LIMIT ' . RICE_MASTER_LIMIT;

$result = mysqli_query($conn, $sql);
if (!$result) {
    http_response_code(500);
    die('Unable to generate the rice master list.');
}

$sourceRecords = [];
while ($row = mysqli_fetch_assoc($result)) {
    $codeKey = strtoupper(trim((string) $row['household_code']));
    $reference = $referenceRecords[$codeKey] ?? [];
    $row['reference_first_name'] = $reference['first_name'] ?? null;
    $row['reference_last_name'] = $reference['last_name'] ?? null;
    $row['reference_middle_name'] = $reference['middle_name'] ?? null;
    $row['sector_source'] = $reference['designation'] ?? '';
    $sourceRecords[] = $row;
}

$records = [];
foreach (riceRecipientFormGroupedRecords($sourceRecords) as $group) {
    foreach ($group['records'] as $row) {
        $records[] = [
            'household_name' => riceMasterPdfText($row['formatted_name']),
            'barangay' => riceMasterPdfText($row['sector']),
        ];
    }
}


class RiceMasterListPDF extends FPDF
{
    public $showTableHeader = true;

    public function Header()
    {
        if ($this->PageNo() === 1) {
            $this->SetFont('Arial', 'B', 12);
            $this->MultiCell(
                0,
                6,
                "MASTER LIST OF INDIGENT INDIVIDUALS, LOW INCOME AND\nVULNERABLE FAMILIES",
                0,
                'C'
            );
            $this->Ln(2);
        }

        if ($this->showTableHeader) {
            $this->drawTableHeader();
        }
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 7, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function drawTableHeader()
    {
        $this->SetFillColor(0, 128, 128);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(RICE_MASTER_NO_WIDTH, 8, 'No.', 1, 0, 'C', true);
        $this->Cell(RICE_MASTER_NAME_WIDTH, 8, 'Name', 1, 0, 'L', true);
        $this->Cell(RICE_MASTER_BARANGAY_WIDTH, 8, 'Barangay', 1, 0, 'L', true);
        $this->Cell(RICE_MASTER_SIGNATURE_WIDTH, 8, 'Signature', 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
    }

    public function addSignatories()
    {
        if ($this->GetY() + 64 > $this->GetPageHeight() - 16) {
            $this->showTableHeader = false;
            $this->AddPage();
        }

        $blockY = max($this->GetY() + 24, $this->GetPageHeight() - 62);
        $leftX = RICE_MASTER_MARGIN + 5;
        $blockWidth = 76;
        $rightX = $this->GetPageWidth() - RICE_MASTER_MARGIN - 5 - $blockWidth;

        $this->SetDrawColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 8);
        $this->SetXY(RICE_MASTER_MARGIN, $blockY - 14);
        $this->MultiCell(
            $this->GetPageWidth() - (RICE_MASTER_MARGIN * 2),
            4,
            'WE HEREBY CERTIFY THAT THE PERSONS WHOSE NAMES APPEARED ABOVE ARE REALLY DESERVING FAMILIES AND QUALIFIED BENEFICIARIES OF RICE ASSISTANCE',
            0,
            'C'
        );

        $this->Line($leftX, $blockY, $leftX + $blockWidth, $blockY);
        $this->Line($rightX, $blockY, $rightX + $blockWidth, $blockY);

        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($leftX, $blockY + 1);
        $this->Cell($blockWidth, 5, 'NISA P. RELAMPAGOS', 0, 0, 'C');
        $this->SetXY($rightX, $blockY + 1);
        $this->Cell($blockWidth, 5, 'ANGELINA C. COSTAN', 0, 0, 'C');

        $this->SetFont('Arial', '', 8);
        $this->SetXY($leftX, $blockY + 6);
        $this->Cell($blockWidth, 4, 'Administrative Officer V', 0, 0, 'C');
        $this->SetXY($leftX, $blockY + 10);
        $this->Cell($blockWidth, 4, 'OIC - BACU', 0, 0, 'C');
        $this->SetXY($rightX, $blockY + 6);
        $this->Cell($blockWidth, 4, 'OIC - DSWD', 0, 0, 'C');

        $mayorWidth = 90;
        $mayorX = ($this->GetPageWidth() - $mayorWidth) / 2;
        $mayorY = $blockY + 26;
        $this->Line($mayorX, $mayorY, $mayorX + $mayorWidth, $mayorY);

        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($mayorX, $mayorY + 1);
        $this->Cell($mayorWidth, 5, 'HON. JANE CENSORIA C. YAP', 0, 0, 'C');
        $this->SetFont('Arial', '', 8);
        $this->SetXY($mayorX, $mayorY + 6);
        $this->Cell($mayorWidth, 4, 'CITY MAYOR', 0, 0, 'C');
    }
}

$pdf = new RiceMasterListPDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(RICE_MASTER_MARGIN, 10, RICE_MASTER_MARGIN);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

if (empty($records)) {
    $pdf->Cell(
        RICE_MASTER_NO_WIDTH + RICE_MASTER_NAME_WIDTH + RICE_MASTER_BARANGAY_WIDTH + RICE_MASTER_SIGNATURE_WIDTH,
        10,
        'No claimed rice data found.',
        1,
        1,
        'C'
    );
} else {
    foreach ($records as $index => $record) {
        $pdf->Cell(RICE_MASTER_NO_WIDTH, RICE_MASTER_ROW_HEIGHT, (string) ($index + 1), 1, 0, 'C');
        $pdf->Cell(RICE_MASTER_NAME_WIDTH, RICE_MASTER_ROW_HEIGHT, $record['household_name'], 1, 0, 'L');
        $pdf->Cell(RICE_MASTER_BARANGAY_WIDTH, RICE_MASTER_ROW_HEIGHT, $record['barangay'], 1, 0, 'L');
        $pdf->Cell(RICE_MASTER_SIGNATURE_WIDTH, RICE_MASTER_ROW_HEIGHT, '', 1, 1, 'L');
    }

    $pdf->addSignatories();
}

$filename = 'Rice_Master_List_5404_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
exit();
