<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['q']) || strlen(trim($_GET['q'])) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit();
}

$query = trim($_GET['q']);
$source = isset($_GET['source']) ? trim($_GET['source']) : '';
if (!in_array($source, ['first_wave', 'next_wave'], true)) {
    echo json_encode(['success' => false, 'message' => 'A valid release wave is required']);
    exit();
}
$household_table = $source === 'next_wave' ? 'rice_claimed_households' : 'rice_households';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$like = $query . '%';

$count_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM {$household_table}
     WHERE household_code LIKE ?
        OR household_name LIKE ?"
);
$count_stmt->bind_param('ss', $like, $like);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

$stmt = $conn->prepare(
    "SELECT id, household_code, household_name, is_claimed, status
     FROM {$household_table}
     WHERE household_code LIKE ?
        OR household_name LIKE ?
     ORDER BY household_code_prefix ASC,
              household_code_number ASC,
              household_code ASC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param('ssii', $like, $like, $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

$results = [];
while ($row = $result->fetch_assoc()) {
    $results[] = [
        'household_id' => (int)$row['id'],
        'household_code' => $row['household_code'],
        'household_name' => $row['household_name'],
        'remaining' => ((int)$row['is_claimed'] === 1) ? 0 : 1,
        'status' => $row['status'],
        'is_claimed' => (int)$row['is_claimed']
    ];
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'page' => $page,
    'per_page' => $per_page,
    'total' => $total,
    'has_more' => ($page * $per_page) < $total
]);
exit();
?>
