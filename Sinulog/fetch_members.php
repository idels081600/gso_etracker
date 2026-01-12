<?php
// Prevent any output before JSON
ob_start();

// Report errors to log file instead of output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    include 'sinulog_db.php';
    
    // Check if connection exists
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Fetch all members from Sinulog table
    $sql = "SELECT * FROM Sinulog ORDER BY id DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    $conn->close();
    
    // Clear output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $members,
        'count' => count($members)
    ]);
    
} catch (Exception $e) {
    error_log('Fetch Members Error: ' . $e->getMessage());
    
    // Clear output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    if (isset($conn)) $conn->close();
}

// End output buffering
ob_end_flush();
?>