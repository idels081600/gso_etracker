<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();
header('Content-Type: application/json');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$token = $_POST['csrf_token'] ?? '';
if (!$token || empty($_SESSION['_master_csrf']) || !hash_equals($_SESSION['_master_csrf'], $token)) {
    respond(['success' => false, 'message' => 'Security token expired. Refresh and try again.'], 403);
}

$envPath = dirname(__DIR__) . '/Payables/.env';
$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '=') === false || strpos(trim($line), '#') === 0) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$conn = mysqli_connect($env['DB_HOST'] ?? 'localhost', $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', $env['DB_DATABASE'] ?? '', (int)($env['DB_PORT'] ?? 3306));
if (!$conn) {
    respond(['success' => false, 'message' => 'Unable to connect to Payables database.'], 500);
}
$conn->set_charset('utf8mb4');

$action = $_POST['action'] ?? '';
$id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
if ($id <= 0) {
    respond(['success' => false, 'message' => 'Invalid record.'], 422);
}

$updatedBy = $_SESSION['username'] ?? 'Master Admin';

if ($action === 'status') {
    $status = strtoupper(trim($_POST['main_status'] ?? ''));
    $valid = ['GSO', 'BUDGET', 'ACCOUNTING', 'CTO'];
    if (!in_array($status, $valid, true)) {
        respond(['success' => false, 'message' => 'Invalid workflow status.'], 422);
    }

    $inspection = !empty($_POST['inspection']) ? 1 : 0;
    $obr = !empty($_POST['obr']) ? 1 : 0;
    $ics = !empty($_POST['ics']) ? 1 : 0;
    $par = !empty($_POST['par']) ? 1 : 0;
    $ris = !empty($_POST['ris']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO payables_workflow_status (record_type, record_id, main_status, inspection, obr, ics, par, ris, updated_by)
        VALUES ('bac', ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE main_status = VALUES(main_status), inspection = VALUES(inspection), obr = VALUES(obr), ics = VALUES(ics), par = VALUES(par), ris = VALUES(ris), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param('isiiiiis', $id, $status, $inspection, $obr, $ics, $par, $ris, $updatedBy);
    respond(['success' => $stmt->execute(), 'message' => $stmt->error ?: 'Workflow updated.', 'status' => $status]);
}

if ($action === 'location') {
    $location = strtoupper(trim($_POST['location'] ?? 'ACCOUNTING'));
    $location = in_array($location, ['GSO', 'ACCOUNTING'], true) ? $location : 'ACCOUNTING';
    $stmt = $conn->prepare("INSERT INTO payables_workflow_status (record_type, record_id, main_status, current_location, updated_by)
        VALUES ('bac', ?, 'ACCOUNTING', ?, ?)
        ON DUPLICATE KEY UPDATE current_location = VALUES(current_location), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param('iss', $id, $location, $updatedBy);
    respond(['success' => $stmt->execute(), 'message' => $stmt->error ?: 'Location updated.', 'location' => $location]);
}

if ($action === 'released') {
    $released = !empty($_POST['released']) ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO payables_workflow_status (record_type, record_id, main_status, released, updated_by)
        VALUES ('bac', ?, 'CTO', ?, ?)
        ON DUPLICATE KEY UPDATE released = VALUES(released), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param('iis', $id, $released, $updatedBy);
    respond(['success' => $stmt->execute(), 'message' => $stmt->error ?: 'Release updated.', 'released' => $released]);
}

respond(['success' => false, 'message' => 'Unknown action.'], 422);

