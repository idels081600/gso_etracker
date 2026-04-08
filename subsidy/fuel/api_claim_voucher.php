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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data (JSON)
$input = json_decode(file_get_contents('php://input'), true);

$tricycle_no = isset($input['tricycle_no']) ? mysqli_real_escape_string($conn, $input['tricycle_no']) : '';
$vouchers = isset($input['vouchers']) ? $input['vouchers'] : [];
$claimant_name = isset($input['claimant_name']) ? mysqli_real_escape_string($conn, $input['claimant_name']) : '';
$e_signature = isset($input['e_signature']) ? $input['e_signature'] : '';
$station_id = isset($_SESSION['station_id']) ? (int)$_SESSION['station_id'] : 0;

// Validation
if (empty($tricycle_no)) {
    echo json_encode(['success' => false, 'message' => 'Tricycle number is required']);
    exit;
}

if ($station_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No station selected. Please select a station first.']);
    exit;
}

if (empty($vouchers) || !is_array($vouchers)) {
    echo json_encode(['success' => false, 'message' => 'No vouchers selected']);
    exit;
}

// Get tricycle record
$sql = "SELECT id, claimed_vouchers, total_vouchers FROM tricycle_records WHERE tricycle_no = '$tricycle_no'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Tricycle not found']);
    exit;
}

$tricycle = mysqli_fetch_assoc($result);
$tricycle_id = $tricycle['id'];

// Start transaction
mysqli_begin_transaction($conn);

try {
    $claimed_count = 0;
    
    foreach ($vouchers as $voucher_num) {
        $voucher_num = (int)$voucher_num;
        
        // Check if voucher already claimed
        $check_sql = "SELECT id FROM voucher_claims WHERE tricycle_id = $tricycle_id AND voucher_number = $voucher_num";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) === 0) {
            // Insert voucher claim with station_id
            $signature_escaped = mysqli_real_escape_string($conn, $e_signature);
            $insert_sql = "INSERT INTO voucher_claims (tricycle_id, voucher_number, claimant_name, e_signature, station_id) 
                           VALUES ($tricycle_id, $voucher_num, '$claimant_name', '$signature_escaped', $station_id)";
            
            if (mysqli_query($conn, $insert_sql)) {
                $claimed_count++;
            }
        }
    }
    
    // Update tricycle record
    $update_sql = "UPDATE tricycle_records 
                   SET claimed_vouchers = claimed_vouchers + $claimed_count, 
                       last_claim_date = CURDATE() 
                   WHERE id = $tricycle_id";
    mysqli_query($conn, $update_sql);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully claimed $claimed_count voucher(s)",
        'claimed_count' => $claimed_count
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error claiming vouchers: ' . $e->getMessage()]);
}
?>