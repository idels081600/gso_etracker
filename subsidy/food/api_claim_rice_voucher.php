<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = require(__DIR__ . '/config/database.php');
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$household_code = $input['household_code'] ?? '';
$claimant_name = trim($input['claimant_name'] ?? '');
$e_signature = $input['e_signature'] ?? '';
$verifier_name = $_SESSION['pay_name'] ?? $_SESSION['username'];

function riceSignatureHasInk($signature) {
    if (!is_string($signature) || !preg_match('/^data:image\/(?:png|jpeg|jpg);base64,/', $signature)) {
        return false;
    }

    $base64 = preg_replace('/^data:image\/(?:png|jpeg|jpg);base64,/', '', $signature);
    $imageData = base64_decode($base64, true);
    if ($imageData === false || strlen($imageData) < 500) {
        return false;
    }

    if (!function_exists('imagecreatefromstring')) {
        return true;
    }

    $image = @imagecreatefromstring($imageData);
    if (!$image) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $step = 10;

    for ($y = 0; $y < $height; $y += $step) {
        for ($x = 0; $x < $width; $x += $step) {
            $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            if ($rgba['alpha'] < 127 && ($rgba['red'] < 245 || $rgba['green'] < 245 || $rgba['blue'] < 245)) {
                imagedestroy($image);
                return true;
            }
        }
    }

    imagedestroy($image);
    return false;
}

if ($household_code === '' || $claimant_name === '') {
    echo json_encode(['success' => false, 'message' => 'Validation failed. Check household and claimant name.']);
    exit();
}

if (!riceSignatureHasInk($e_signature)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing e-signature. Please draw your signature before submitting.']);
    exit();
}

try {
    $conn->begin_transaction();

    $household_stmt = $conn->prepare(
        "SELECT id, household_name, status, is_claimed
         FROM rice_households
         WHERE household_code = ?
         FOR UPDATE"
    );
    $household_stmt->bind_param('s', $household_code);
    $household_stmt->execute();
    $household_result = $household_stmt->get_result();
    $household = $household_result ? $household_result->fetch_assoc() : null;

    if (!$household) {
        throw new Exception('Household not found');
    }

    if ($household['status'] !== 'Active') {
        throw new Exception('This household is inactive and cannot claim rice assistance.');
    }

    if ((int)$household['is_claimed'] === 1) {
        throw new Exception('This household has already claimed the rice assistance voucher.');
    }

    $household_id = (int)$household['id'];

    $insert_stmt = $conn->prepare(
        "INSERT INTO rice_voucher_claims (household_id, claimant_name, e_signature, verifier_name)
         VALUES (?, ?, ?, ?)"
    );
    $insert_stmt->bind_param('isss', $household_id, $claimant_name, $e_signature, $verifier_name);
    if (!$insert_stmt->execute()) {
        throw new Exception('Unable to save the rice claim record.');
    }

    $update_stmt = $conn->prepare(
        "UPDATE rice_households
         SET is_claimed = 1, claimed_at = NOW()
         WHERE id = ?"
    );
    $update_stmt->bind_param('i', $household_id);
    if (!$update_stmt->execute()) {
        throw new Exception('Unable to update the household claim status.');
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Rice voucher claimed successfully.',
        'household_name' => $household['household_name']
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();
?>
