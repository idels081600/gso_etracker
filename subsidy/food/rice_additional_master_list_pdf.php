<?php

const RICE_ADDITIONAL_MASTER_MARGIN = 12;
const RICE_ADDITIONAL_MASTER_NO_WIDTH = 20;
const RICE_ADDITIONAL_MASTER_NAME_WIDTH = 105;
const RICE_ADDITIONAL_MASTER_BARANGAY_WIDTH = 55;
const RICE_ADDITIONAL_MASTER_SIGNATURE_WIDTH = 93;
const RICE_ADDITIONAL_MASTER_ROW_HEIGHT = 6;

class RiceAdditionalMasterListPDF extends FPDF
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
        $this->Cell(RICE_ADDITIONAL_MASTER_NO_WIDTH, 8, 'No.', 1, 0, 'C', true);
        $this->Cell(RICE_ADDITIONAL_MASTER_NAME_WIDTH, 8, 'Name', 1, 0, 'L', true);
        $this->Cell(RICE_ADDITIONAL_MASTER_BARANGAY_WIDTH, 8, 'Barangay', 1, 0, 'L', true);
        $this->Cell(RICE_ADDITIONAL_MASTER_SIGNATURE_WIDTH, 8, 'Signature', 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
    }

    public function ensureRecipientSpace()
    {
        if ($this->GetY() + RICE_ADDITIONAL_MASTER_ROW_HEIGHT > $this->GetPageHeight() - 16) {
            $this->AddPage();
        }
    }

    public function addSignatories()
    {
        if ($this->GetY() + 64 > $this->GetPageHeight() - 16) {
            $this->showTableHeader = false;
            $this->AddPage();
        }

        $blockY = max($this->GetY() + 24, $this->GetPageHeight() - 62);
        $leftX = RICE_ADDITIONAL_MASTER_MARGIN + 5;
        $blockWidth = 76;
        $rightX = $this->GetPageWidth() - RICE_ADDITIONAL_MASTER_MARGIN - 5 - $blockWidth;

        $this->SetDrawColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 8);
        $this->SetXY(RICE_ADDITIONAL_MASTER_MARGIN, $blockY - 14);
        $this->MultiCell(
            $this->GetPageWidth() - (RICE_ADDITIONAL_MASTER_MARGIN * 2),
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

function riceAdditionalMasterListRender(
    array $sourceRecords,
    $destination = 'I',
    $filename = 'Rice_Additional_Master_List.pdf'
) {
    $records = [];
    foreach (riceRecipientFormGroupedRecords($sourceRecords) as $group) {
        foreach ($group['records'] as $row) {
            $records[] = [
                'household_name' => riceRecipientFormPdfText($row['formatted_name']),
                'barangay' => riceRecipientFormPdfText($row['sector']),
            ];
        }
    }

    $pdf = new RiceAdditionalMasterListPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(RICE_ADDITIONAL_MASTER_MARGIN, 10, RICE_ADDITIONAL_MASTER_MARGIN);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    if (empty($records)) {
        $pdf->Cell(
            RICE_ADDITIONAL_MASTER_NO_WIDTH + RICE_ADDITIONAL_MASTER_NAME_WIDTH
                + RICE_ADDITIONAL_MASTER_BARANGAY_WIDTH + RICE_ADDITIONAL_MASTER_SIGNATURE_WIDTH,
            10,
            'No rice assistance recipients found.',
            1,
            1,
            'C'
        );
    } else {
        foreach ($records as $index => $record) {
            $pdf->ensureRecipientSpace();
            $pdf->Cell(RICE_ADDITIONAL_MASTER_NO_WIDTH, RICE_ADDITIONAL_MASTER_ROW_HEIGHT, (string)($index + 1), 1, 0, 'C');
            $pdf->Cell(RICE_ADDITIONAL_MASTER_NAME_WIDTH, RICE_ADDITIONAL_MASTER_ROW_HEIGHT, $record['household_name'], 1, 0, 'L');
            $pdf->Cell(RICE_ADDITIONAL_MASTER_BARANGAY_WIDTH, RICE_ADDITIONAL_MASTER_ROW_HEIGHT, $record['barangay'], 1, 0, 'L');
            $pdf->Cell(RICE_ADDITIONAL_MASTER_SIGNATURE_WIDTH, RICE_ADDITIONAL_MASTER_ROW_HEIGHT, '', 1, 1, 'L');
        }
        $pdf->addSignatories();
    }

    $pdf->Output($destination, $filename);
}
