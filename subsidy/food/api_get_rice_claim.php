<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$household_id = isset($_GET['household_id']) ? (int)$_GET['household_id'] : 0;
$household_code = isset($_GET['household_code']) ? trim($_GET['household_code']) : '';

if ($household_id <= 0 && $household_code === '') {
    echo json_encode(['success' => false, 'message' => 'Household lookup is required']);
    exit();
}

$sql = '';
$types = '';
$params = [];

if ($household_id > 0) {
    $sql = "SELECT rh.id, rh.household_code, rh.household_name, rh.address, rh.status, rh.is_claimed, rh.claimed_at
            FROM rice_households rh
            WHERE rh.id = ?
            LIMIT 1";
    $types = 'i';
    $params[] = $household_id;
} else {
    $sql = "SELECT rh.id, rh.household_code, rh.household_name, rh.address, rh.status, rh.is_claimed, rh.claimed_at
            FROM rice_households rh
            WHERE rh.household_code = ?
               OR rh.household_name LIKE ?
            ORDER BY CASE WHEN rh.household_code = ? THEN 0 ELSE 1 END
            LIMIT 1";
    $like = $household_code . '%';
    $types = 'sss';
    $params[] = $household_code;
    $params[] = $like;
    $params[] = $household_code;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$household = $result ? $result->fetch_assoc() : null;

if (!$household) {
    echo json_encode(['success' => false, 'message' => 'Household not found']);
    exit();
}

$claim_stmt = $conn->prepare(
    "SELECT rvc.claimant_name, rvc.claim_date, rvc.e_signature, rvc.verifier_name
     FROM rice_voucher_claims rvc
     WHERE rvc.household_id = ?
     LIMIT 1"
);
$household_id = (int)$household['id'];
$claim_stmt->bind_param('i', $household_id);
$claim_stmt->execute();
$claim_result = $claim_stmt->get_result();
$claim = $claim_result ? $claim_result->fetch_assoc() : null;

$household['is_claimed'] = (int)$household['is_claimed'];
$household['claim_data'] = $claim ?: null;

echo json_encode([
    'success' => true,
    'data' => $household
]);
exit();
?>
