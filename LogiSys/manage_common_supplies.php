<?php
require_once 'logi_db.php';
header('Content-Type: application/json');

if (isset($_GET['action']) && $_GET['action'] === 'search_items') {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $query = "SELECT item_no, item_name, unit, current_balance FROM inventory_items ";
    if ($search !== '') {
        $query .= "WHERE item_no LIKE '%$search%' OR item_name LIKE '%$search%' ";
    }
    $query .= "ORDER BY item_name";
    $result = mysqli_query($conn, $query);
    $items = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = [
                'item_no' => $row['item_no'],
                'item_name' => $row['item_name'],
                'unit' => $row['unit'],
                'current_balance' => (int)$row['current_balance'],
            ];
        }
        echo json_encode(['success' => true, 'items' => $items]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . mysqli_error($conn)]);
    }
    exit;
}
// ... existing code for other actions ... 