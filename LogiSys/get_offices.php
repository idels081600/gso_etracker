<?php
header('Content-Type: application/json');

try {
    require_once 'logi_db.php';

    $query = "SELECT DISTINCT office_name FROM items_requested WHERE office_name IS NOT NULL ORDER BY office_name";
    $result = mysqli_query($conn, $query);

    $offices = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $offices[] = $row['office_name'];
        }
    }

    echo json_encode([
        'success' => true,
        'offices' => $offices
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
