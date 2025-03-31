<?php
require_once 'db.php'; // Assuming this file contains your database connection code
require_once 'display_data.php'; // Adjust this to your actual display data file
require_once('vendor/autoload.php');

// Fetch data
$result = display_data_maam_mariecris_print();
$payment = display_data_Maam_mariecris_payments();

$paymentNames = array(); // Array to store payment names

// Create a new PDF instance with landscape orientation
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('GSO');
$pdf->SetTitle('Maam Maricris Summary');
$pdf->SetSubject('Maam Maricris Summary Document');
$pdf->SetKeywords('TCPDF, PDF, summary, maricris');

// Set header and footer fonts
$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(10, 10, 10); // Adjusted margins for landscape
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Define language-dependent strings
$l = array(
    'a_meta_charset' => 'UTF-8',
    'a_meta_dir' => 'ltr',
    'a_meta_language' => 'en',
    'w_page' => 'page',
);
// Set some language-dependent strings
$pdf->setLanguageArray($l);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('dejavusans', '', 11);

// Add summary details
$summaryDetails = '
<h1>Maam Maricris Summary</h1>
<table cellspacing="0" cellpadding="2">
</table>';

$pdf->writeHTML($summaryDetails, true, false, true, false, '');

// Define the table header in a reusable variable with adjusted widths
$tableHeader = '
<thead>
    <tr>
        <th style="width:8%; background-color: #009532; color: #ffffff;">SR/DR</th>
        <th style="width:8%; background-color: #009532; color: #ffffff;">Date</th>
        <th style="width:10%; background-color: #009532; color: #ffffff;">Department</th>
        <th style="width:10%; background-color: #009532; color: #ffffff;">Store</th>
        <th style="width:20%; background-color: #009532; color: #ffffff;">Activity</th>
        <th style="width:6%; background-color: #009532; color: #ffffff;">No. of Pax</th>
        <th style="width:7%; background-color: #009532; color: #ffffff;">PO No.</th>
        <th style="width:8%; background-color: #009532; color: #ffffff;">Remarks</th>
        <th style="width:10%; background-color: #009532; color: #ffffff;">Amount</th>
        <th style="width:10%; background-color: #009532; color: #ffffff;">Total</th>
    </tr>
</thead>';

// Start the table body with adjusted cell styling
$html = '
<style>
th, td {
    font-weight: normal;
    font-size: 10px;
    border: 1px solid #dddddd;
    padding: 6px;
    vertical-align: middle;
}
</style>

<table cellspacing="0" cellpadding="4" border="1">';

// Add the header
$html .= $tableHeader . '<tbody>';

// Calculate total amount, excluding paid items
$totalAmount = 0;
mysqli_data_seek($result, 0);

// Loop through each row of data with adjusted cell structure
while ($row = mysqli_fetch_assoc($result)) {
    // Only add to total if remarks is not 'paid' (case insensitive)
    if (strtolower($row["remarks"]) !== 'paid') {
        $totalAmount += $row["total"];
    }

    $html .= '<tr>
                <td style="width:8%;">' . $row["SR_DR"] . '</td>
                <td style="width:8%;">' . $row["date"] . '</td>
                <td style="width:10%;">' . $row["department"] . '</td>
                <td style="width:10%;">' . $row["store"] . '</td>
                <td style="width:20%;">' . $row["activity"] . '</td>
                <td style="width:6%; text-align: center;">' . $row["no_of_pax"] . '</td>
                <td style="width:7%;">' . $row["PO_no"] . '</td>
                <td style="width:8%;">' . $row["remarks"] . '</td>
                <td style="width:10%; text-align: right;">₱' . number_format($row["amount"], 2) . '</td>
                <td style="width:10%; text-align: right;">₱' . number_format($row["total"], 2) . '</td>
            </tr>';

    // Check for page break and repeat header if necessary
    if (($pdf->getY() + 10) > $pdf->getPageHeight() - PDF_MARGIN_BOTTOM) {
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->AddPage();
        $html = '<table cellspacing="0" cellpadding="5" border="1">' . $tableHeader . '<tbody>';
    }
}

$html .= '<tr>
            <td colspan="9" style="font-weight: bold;">Payment Details</td>
          </tr>';

// Reset the result pointer
mysqli_data_seek($result, 0);

// Get unique PO details
$processed_pos = array();
while ($row = mysqli_fetch_assoc($result)) {
    if (!empty($row['PO_no']) && !empty($row['PO_amount']) && !in_array($row['PO_no'], $processed_pos)) {
        $html .= '<tr>
                    <td colspan="9">PO No.: ' . $row['PO_no'] . '</td>
                    <td style="font-weight: bold;">₱' . number_format($row['PO_amount'], 2) . '</td>
                  </tr>';
        $processed_pos[] = $row['PO_no'];
    }
}

// Reset pointer to get all PO amounts
mysqli_data_seek($result, 0);

// Initialize array to store PO amounts
$poAmounts = array();

// Collect all unique PO amounts
while ($row = mysqli_fetch_assoc($result)) {
    if (!empty($row['PO_amount']) && !empty($row['PO_no'])) {
        $poAmounts[$row['PO_no']] = $row['PO_amount'];
    }
}

// Calculate final total amount using array_sum
$finalTotalAmount = array_sum($poAmounts) - $totalAmount;

// Add total amount rows
$html .= '<tr>
            <td colspan="9" style="text-align: right; font-weight: bold;">Total Amount:</td>
            <td style="font-weight: bold;">₱' . number_format($totalAmount, 2) . '</td>
          </tr>';

$html .= '<tr>
            <td colspan="9" style="text-align: right; font-weight: bold;">Total Payments:</td>
            <td style="font-weight: bold;">₱' . number_format(array_sum($poAmounts), 2) . '</td>
          </tr>';

$html .= '<tr>
            <td colspan="9" style="text-align: right; font-weight: bold;">Final Total Amount:</td>
            <td style="font-weight: bold;">₱' . number_format($finalTotalAmount, 2) . '</td>
          </tr>';

$html .= '</tbody></table>';

// Write the remaining part of the table
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('maricris_summary.pdf', 'I');
