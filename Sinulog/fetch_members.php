<?php
// In fetch_members.php - only fetch active members for counting
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    include 'sinulog_db.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Fetch all members
    $sql = "SELECT * FROM Sinulog ORDER BY id DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    // Count active members by role
    $countSql = "SELECT 
                    SUM(CASE WHEN LOWER(role) LIKE '%dancer%' AND LOWER(status) = 'present' THEN 1 ELSE 0 END) as dancers,
                    SUM(CASE WHEN LOWER(role) LIKE '%props%' AND LOWER(status) = 'present' THEN 1 ELSE 0 END) as propsmen,
                    SUM(CASE WHEN LOWER(role) LIKE '%instrument%' AND LOWER(status) = 'present' THEN 1 ELSE 0 END) as instrumentals
                 FROM Sinulog";
    
    $countResult = $conn->query($countSql);
    $counts = $countResult->fetch_assoc();
    
    $conn->close();
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $members,
        'count' => count($members),
        'roleCounts' => [
            'dancers' => (int)$counts['dancers'],
            'propsmen' => (int)$counts['propsmen'],
            'instrumentals' => (int)$counts['instrumentals']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Fetch Members Error: ' . $e->getMessage());
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    if (isset($conn)) $conn->close();
}

ob_end_flush();
?>