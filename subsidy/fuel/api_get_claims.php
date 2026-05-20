<?php
session_start();

// Security check - return error if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_fuel.php';

header('Content-Type: application/json');

// Get tricycle number from query parameter
$tricycle_no = isset($_GET['tricycle_no']) ? mysqli_real_escape_string($conn, $_GET['tricycle_no']) : '';

if (empty($tricycle_no)) {
    echo json_encode(['success' => false, 'message' => 'Tricycle number is required']);
    exit;
}

// For 4-digit tricycle numbers, use exact matching so "0025" does not load
// longer boat/tag records such as "B TAG-000025-2021".
$exactSql = "SELECT tr.id, tr.tricycle_no, tr.driver_name, tr.address, tr.contact_number,
        tr.total_vouchers, tr.claimed_vouchers, tr.status, tr.last_claim_date
        FROM tricycle_records tr
        WHERE TRIM(tr.tricycle_no) = TRIM('$tricycle_no')
        LIMIT 1";

$result = mysqli_query($conn, $exactSql);

if (mysqli_num_rows($result) === 0 && !preg_match('/^\d{4}$/', $tricycle_no)) {
    $sql = "SELECT tr.id, tr.tricycle_no, tr.driver_name, tr.address, tr.contact_number,
            tr.total_vouchers, tr.claimed_vouchers, tr.status, tr.last_claim_date
            FROM tricycle_records tr
            WHERE tr.tricycle_no LIKE '%$tricycle_no%'
            OR tr.driver_name LIKE '%$tricycle_no%'
            ORDER BY
                CASE
                    WHEN TRIM(tr.tricycle_no) = TRIM('$tricycle_no') THEN 0
                    WHEN tr.tricycle_no LIKE '$tricycle_no%' THEN 1
                    ELSE 2
                END,
                tr.tricycle_no
            LIMIT 1";

    $result = mysqli_query($conn, $sql);
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Tricycle not found']);
    exit;
}

$tricycle = mysqli_fetch_assoc($result);

// Get claimed vouchers with e-signature data
$tricycle_id = $tricycle['id'];
$station_order = '0';
if (isset($_SESSION['station_id']) && !empty($_SESSION['station_id'])) {
    $station_id = (int)$_SESSION['station_id'];
    $station_order = "CASE WHEN station_id = $station_id THEN 0 ELSE 1 END,";
}

$claims_sql = "SELECT voucher_number, claimant_name, claim_date, e_signature 
               FROM voucher_claims 
               WHERE tricycle_id = $tricycle_id
               AND e_signature IS NOT NULL AND e_signature != ''
               ORDER BY $station_order voucher_number";

$claims_result = mysqli_query($conn, $claims_sql);
$claimed_vouchers = [];
$claims_data = [];

while ($row = mysqli_fetch_assoc($claims_result)) {
    $claimed_vouchers[] = (int)$row['voucher_number'];
    $claims_data[] = [
        'voucher_number' => (int)$row['voucher_number'],
        'claimant_name' => $row['claimant_name'],
        'claim_date' => $row['claim_date'],
        'e_signature' => $row['e_signature']
    ];
}

$tricycle['claimed_vouchers_list'] = $claimed_vouchers;

$tricycle['claims_data'] = $claims_data;

echo json_encode([
    'success' => true,
    'data' => $tricycle
]);
?>
