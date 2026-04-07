<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Security check
if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../dbh.php';

header('Content-Type: application/json');

// Get current time
$currentDateTime = new DateTime();
$currentDateTimeStr = $currentDateTime->format('Y-m-d H:i:s');

// Query employees who are still outside (Status = 'Approved') and overdue by more than 1 hour
// Overdue = current time > esttime + 1 hour
$sql = "SELECT 
    id,
    name,
    destination,
    typeofbusiness,
    esttime,
    timedept,
    date,
    TIMESTAMPDIFF(MINUTE, CONCAT(date, ' ', esttime), NOW()) as minutes_overdue
FROM request 
WHERE Status = 'Approved' 
    AND date = CURDATE()
    AND TIMESTAMPDIFF(MINUTE, CONCAT(date, ' ', esttime), NOW()) > 60
ORDER BY minutes_overdue DESC";

$result = mysqli_query($conn, $sql);

$overdueEmployees = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Calculate time overdue string
    $minutes = (int)$row['minutes_overdue'];
    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;
    
    $timeOverdue = '';
    if ($hours > 0) {
        $timeOverdue .= $hours . ' hour' . ($hours > 1 ? 's' : '');
        if ($remainingMinutes > 0) {
            $timeOverdue .= ' ' . $remainingMinutes . ' min' . ($remainingMinutes > 1 ? 's' : '');
        }
    } else {
        $timeOverdue = $remainingMinutes . ' minute' . ($remainingMinutes > 1 ? 's' : '');
    }
    
    $overdueEmployees[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'destination' => $row['destination'],
        'typeofbusiness' => $row['typeofbusiness'],
        'esttime' => $row['esttime'],
        'timedept' => $row['timedept'],
        'minutes_overdue' => $minutes,
        'time_overdue_display' => $timeOverdue
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($overdueEmployees),
    'current_time' => $currentDateTimeStr,
    'data' => $overdueEmployees
]);
?>