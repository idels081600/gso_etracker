<?php
require_once __DIR__ . '/app_security.php';
asset_require_auth();
header('Content-Type: application/json');
include __DIR__ . '/db_asset.php';

try {
    $query = "SELECT id, status FROM tent WHERE status IS NOT NULL AND status != ''";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . mysqli_error($conn));
    }
    
    $statuses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $statuses[] = [
            'id' => $row['id'],
            'status' => $row['status']
        ];
    }
    
    echo json_encode($statuses);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>
