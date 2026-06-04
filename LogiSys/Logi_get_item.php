<?php
// Prevent any HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

require_once 'logi_db.php';

function hasInventoryColumn($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items' AND COLUMN_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return ((int)($result->fetch_assoc()['count'] ?? 0)) > 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['item_no'])) {
    try {
        $item_no = mysqli_real_escape_string($conn, $_POST['item_no']);
        $has_low_stock_threshold = hasInventoryColumn($conn, 'low_stock_threshold');

        // Get item details from database
        $query = "SELECT * FROM inventory_items WHERE item_no = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $item_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $item['id'],
                    'item_no' => $item['item_no'],
                    'item_name' => $item['item_name'],
                    'rack_no' => $item['rack_no'],
                    'unit' => $item['unit'] ?? '', // Added unit field
                    'current_balance' => $item['current_balance'],
                    'low_stock_threshold' => $has_low_stock_threshold ? ($item['low_stock_threshold'] ?? 10) : 10,
                    'status' => $item['status'],
                    'description' => $item['description'] ?? '',
                    'updated_at' => $item['updated_at'] ?? null // Added updated_at for last modified info
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Item not found'
            ]);
        }

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Item number is required.'
    ]);
}

$conn->close();
