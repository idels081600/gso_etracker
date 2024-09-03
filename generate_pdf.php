<?php
require_once 'db.php'; // Assuming this file contains your database connection code
require_once 'display_data.php';
require_once('vendor/autoload.php');

// Fetch data
$result = display_data_sir_bayong_print();
$payment = display_data_sir_bayong_payments();

$paymentNames = array(); // Array to store payment names
$paymentAmounts = array(); // Array to store payment amounts

// Loop through each payment and store payment name and amount
foreach ($payment as $row) {
    $paymentNames[] = $row['name'];
    $paymentAmounts[] = $row['amount'];
}

// Debugging: Log the result data to a file
$logFile = 'debug_log.txt';
$logData = '';

mysqli_data_seek($result, 0); // Reset the result pointer
while ($row = mysqli_fetch_assoc($result)) {
    $logData .= print_r($row, true) . "\n";
}

// Write log data to file
file_put_contents($logFile, $logData);

// Create a new PDF instance
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

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
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

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

// Add table with items
$html = '
<style>
.table-container1 {
    position: relative;
    max-height: 590px;
    overflow-y: auto;
    overflow-x: auto;
    width: 100%;
}

.table_tent1 {
    border-collapse: collapse;
    width: 100%;
    margin-top: 20px;
}

th {
    background-color: #009532;
    color: #ffffff;
    text-align: left;
    padding: 8px;
}

th, td {
    font-weight: normal;
    font-size: 11px;
    border: 1px solid #dddddd;
    padding: 8px;
}

td {
    text-align: left;
}
</style>

<div class="table-container1">
    <table id="table_tent1" class="table_tent1">
        <thead>
            <tr>
                <th>SR/DR</th>
                <th>Date</th>
                <th>Supplier</th>
                <th>Quantity</th>
                <th>Description</th>
                <th>Office</th>
                <th>Vehicle</th>
                <th>Plate</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>';

// Calculate total amount
$totalAmount = 0;

mysqli_data_seek($result, 0); // Reset the result pointer
while ($row = mysqli_fetch_assoc($result)) {
    $html .= '<tr data-id="' . $row['id'] . '">
                <td>' . $row["SR_DR"] . '</td>
                <td>' . $row["Date"] . '</td>
                <td>' . $row["Supplier"] . '</td>
                <td>' . $row["Quantity"] . '</td>
                <td>' . $row["Description"] . '</td>
                <td>' . $row["Office"] . '</td>
                <td>' . $row["Vehicle"] . '</td>
                <td>' . $row["Plate"] . '</td>
                <td>₱' . number_format($row["Amount"], 2) . '</td>
            </tr>';
    $totalAmount += $row["Amount"];
}

// Add payment details to HTML
$html .= '<tr>
            <td colspan="9" style="font-weight: bold;">Payment Details</td>
          </tr>';

foreach ($paymentNames as $key => $paymentName) {
    $html .= '<tr>
                <td colspan="8">' . $paymentName . '</td>
                <td>₱' . number_format($paymentAmounts[$key], 2) . '</td>
              </tr>';
}

// Calculate final total amount
$finalTotalAmount = $totalAmount - array_sum($paymentAmounts);

// Add total amount rows
$html .= '<tr>
            <td colspan="8" style="text-align: right; font-weight: bold;">Total Amount:</td>
            <td style="font-weight: bold;">₱' . number_format($totalAmount, 2) . '</td>
          </tr>';

$html .= '<tr>
            <td colspan="8" style="text-align: right; font-weight: bold;">Total Payments:</td>
            <td style="font-weight: bold;">₱' . number_format(array_sum($paymentAmounts), 2) . '</td>
          </tr>';

$html .= '<tr>
            <td colspan="8" style="text-align: right; font-weight: bold;">Final Total Amount:</td>
            <td style="font-weight: bold;">₱' . number_format($finalTotalAmount, 2) . '</td>
          </tr>';

$html .= '</tbody>
          </table>
        </div>';

$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('sample.pdf', 'I');
