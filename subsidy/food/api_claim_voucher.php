<?php
session_start();

// 1. Security Check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_fuel.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Use consistent naming and null coalescing
$benificiary_no = $input['benificiary_no'] ?? '';
$vouchers       = $input['vouchers'] ?? [];
$claimant_name  = $input['claimant_name'] ?? '';
$e_signature    = $input['e_signature'] ?? '';
$station_id     = (int)($_SESSION['station_id'] ?? 0);
$is_verified    = 1;
$is_redeemed    = 0;


// 2. Validation
if (empty($benificiary_no) || $station_id <= 0 || empty($vouchers) || !is_array($vouchers)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed. Check input and station selection.']);
    exit;
}

try {
    mysqli_begin_transaction($conn);

    // 3. Get Beneficiary (Using Prepared Statement)
    $stmt = $conn->prepare("SELECT id FROM food_beneficiaries WHERE beneficiary_code = ?");
    $stmt->bind_param("s", $benificiary_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $beneficiary = $result->fetch_assoc();

    if (!$beneficiary) {
        throw new Exception('Beneficiary not found');
    }

    $b_id = $beneficiary['id'];
    $claimed_count = 0;

    // 4. Process Vouchers
    $checkStmt = $conn->prepare("SELECT id FROM food_voucher_claims WHERE beneficiary_id = ? AND voucher_number = ?");
    $insertStmt = $conn->prepare("INSERT INTO food_voucher_claims (beneficiary_id, voucher_number, claimant_name, e_signature, is_verified, is_redeemed, station_id) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($vouchers as $voucher_num) {
        $voucher_num = (int)$voucher_num;
        
        $checkStmt->bind_param("ii", $b_id, $voucher_num);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $insertStmt->bind_param("iissiii", $b_id, $voucher_num, $claimant_name, $e_signature, $is_verified, $is_redeemed, $station_id);
            if ($insertStmt->execute()) {
                $claimed_count++;
            }
        }
    }

    // 5. Update Totals
    if ($claimed_count > 0) {
        $updateStmt = $conn->prepare("UPDATE food_beneficiaries SET claimed_vouchers = claimed_vouchers + ?, last_claim_date = CURDATE() WHERE id = ?");
        $updateStmt->bind_param("ii", $claimed_count, $b_id);
        $updateStmt->execute();
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => "Successfully claimed $claimed_count voucher(s)", 'claimed_count' => $claimed_count]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>