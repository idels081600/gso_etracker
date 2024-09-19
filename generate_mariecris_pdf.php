<?php
require_once 'db.php'; // Assuming this file contains your database connection code
require_once 'display_data.php';
$result = display_data_maam_mariecris_print();
$payment = display_data_Maam_mariecris_payments();
require_once('vendor/autoload.php');

$paymentNames = array(); // Array to store payment names
$paymentAmounts = array(); // Array to store payment amounts

// Loop through each payment and store payment name and amount
foreach ($payment as $row) {
    $paymentNames[] = $row['name'];
    $paymentAmounts[] = $row['amount'];
}

// Create a new PDF instance with landscape orientation
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Your Name');
$pdf->SetTitle('Invoice');
$pdf->SetSubject('Invoice Document');
$pdf->SetKeywords('TCPDF, PDF, invoice');

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

// Add invoice details
$invoiceDetails = '
<h1>Summary</h1>
<table cellspacing="0" cellpadding="2">
</table>';

$pdf->writeHTML($invoiceDetails, true, false, true, false, '');

// Define the table header in a reusable variable
$tableHeader = '
<thead>
    <tr>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">SR/DR</th>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">Date</th>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">Quantity</th>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">Description</th>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">Office</th>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">Vehicle</th>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">Plate</th>
        <th style="width:12.5%; background-color: #009532; color: #ffffff;">Amount</th>
    </tr>
</thead>';

// Start the table body
$html = '
<style>
th, td {
    font-weight: normal;
    font-size: 10px;
    border: 1px solid #dddddd;
    padding: 8px;
}
</style>

<table cellspacing="0" cellpadding="5" border="1">';

// Add the header
$html .= $tableHeader . '<tbody>';

// Calculate total amount
$totalAmount = 0;
mysqli_data_seek($result, 0);

// Loop through each row of data
while ($row = mysqli_fetch_assoc($result)) {
    $html .= '<tr>
                <td>' . $row["SR_DR"] . '</td>
                <td>' . $row["date"] . '</td>
                <td>' . $row["department"] . '</td>
                <td>' . $row["store"] . '</td>
                <td>' . $row["activity"] . '</td>
                <td>' . $row["no_of_pax"] . '</td>
                <td>₱' . number_format($row["amount"], 2) . '</td>
                <td>₱' . number_format($row["total"], 2) . '</td>
            </tr>';
    $totalAmount += $row["total"];

    // Check for page break and repeat the header if necessary
    if (($pdf->getY() + 10) > $pdf->getPageHeight() - PDF_MARGIN_BOTTOM) {
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->AddPage();
        $html = '<table cellspacing="0" cellpadding="5" border="1">' . $tableHeader . '<tbody>';
    }
}

// Add payment details to HTML
$html .= '<tr>
            <td colspan="8" style="font-weight: bold;">Payment Details</td>
          </tr>';

foreach ($paymentNames as $key => $paymentName) {
    $html .= '<tr>
                <td colspan="7">' . $paymentName . '</td>
                <td>₱' . number_format($paymentAmounts[$key], 2) . '</td>
              </tr>';
}

// Calculate final total amount
$finalTotalAmount = $totalAmount - array_sum($paymentAmounts);

// Add total amount rows
$html .= '<tr>
            <td colspan="7" style="text-align: right; font-weight: bold;">Total Amount:</td>
            <td style="font-weight: bold;">₱' . number_format($totalAmount, 2) . '</td>
          </tr>';

$html .= '<tr>
            <td colspan="7" style="text-align: right; font-weight: bold;">Total Payments:</td>
            <td style="font-weight: bold;">₱' . number_format(array_sum($paymentAmounts), 2) . '</td>
          </tr>';

$html .= '<tr>
            <td colspan="7" style="text-align: right; font-weight: bold;">Final Total Amount:</td>
            <td style="font-weight: bold;">₱' . number_format($finalTotalAmount, 2) . '</td>
          </tr>';

$html .= '</tbody></table>';

// Write the remaining part of the table
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('sample_landscape.pdf', 'I');
