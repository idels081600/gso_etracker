<?php
session_start();
$conn = require __DIR__ . '/config/database.php';

if (!isset($_SESSION['username'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login_v2.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../../fpdf/fpdf.php';
require_once __DIR__ . '/rice_recipient_form_pdf.php';

$sql = "SELECT id, household_code, household_name, first_name, last_name, middle_name,
               designation, designation AS sector_source
        FROM rice_claimed_households
        WHERE status = 'Active'
          AND UPPER(TRIM(COALESCE(address, ''))) = 'ADDITIONAL'
        ORDER BY designation ASC, last_name ASC, first_name ASC, middle_name ASC, household_code_number ASC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    http_response_code(500);
    exit('Unable to generate the additional recipient form.');
}

$records = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['force_barangay_group'] = true;
    $records[] = $row;
}

riceRecipientFormRender(
    $records,
    'I',
    'Rice_Assistance_Additional_Recipient_Form_' . date('Y-m-d') . '.pdf',
    ['', '', ''],
    ['2ND', '3RD', '4TH'],
    'RICE ASSISTANCE RECIPIENT'
);
exit();
