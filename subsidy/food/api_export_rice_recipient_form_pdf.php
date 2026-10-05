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

const RICE_RECIPIENT_FORM_LIMIT = 5404;

$referenceRecords = [];
$referenceResult = mysqli_query(
    $conn,
    'SELECT id, household_code, first_name, last_name, middle_name, designation
     FROM rice_claimed_households
     ORDER BY id ASC'
);
if (!$referenceResult) {
    http_response_code(500);
    die('Unable to load the rice assistance recipient reference data.');
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
        LIMIT ' . RICE_RECIPIENT_FORM_LIMIT;

$result = mysqli_query($conn, $sql);
if (!$result) {
    http_response_code(500);
    die('Unable to generate the rice assistance recipient form.');
}

$records = [];
while ($row = mysqli_fetch_assoc($result)) {
    $codeKey = strtoupper(trim((string) $row['household_code']));
    $reference = $referenceRecords[$codeKey] ?? [];
    $row['reference_first_name'] = $reference['first_name'] ?? null;
    $row['reference_last_name'] = $reference['last_name'] ?? null;
    $row['reference_middle_name'] = $reference['middle_name'] ?? null;
    $row['sector_source'] = $reference['designation'] ?? null;
    $records[] = $row;
}

riceRecipientFormRender(
    $records,
    'I',
    'Rice_Assistance_Recipient_Form_' . date('Y-m-d') . '.pdf'
);
exit();
