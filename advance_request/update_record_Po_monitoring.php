<?php
session_start();
header('Content-Type: application/json');

require_once 'advance_po_db.php';

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get ID
$id = intval($_POST['edit_row_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

try {
    // Set Philippine timezone
    date_default_timezone_set('Asia/Manila');
    $edited = date('Y-m-d H:i:s');

    // Basic fields
    $supplier = $_POST['edit_supplier'] ?? '';
    $po_date = $_POST['edit_po_date'] ?? '';
    $po_number = $_POST['edit_po_number'] ?? '';
    $description = $_POST['edit_description'] ?? '';
    $office = $_POST['edit_office'] ?? '';
    $price = floatval($_POST['edit_price'] ?? 0);
    $destination = $_POST['edit_destination'] ?? '';
    $status = $_POST['edit_status'] ?? '';

    // Checklist fields (1 if checked, else 0)
    $checkboxes = [
        'preAuditchecklist', 'obr', 'dv', 'billing_request', 'certWarranty',
        'omnibus', 'ris', 'acceptance', 'rfq', 'recommending',
        'PR', 'PO', 'receipts', 'delegation', 'mayorsPermit', 'jetsCert'
    ];

    $values = [
        $supplier, $po_date, $po_number, $description, $office,
        $price, $destination
    ];

    $query = "
        UPDATE poMonitoring SET
            supplier=?, po_date=?, po_number=?, description=?, office=?, price=?, destination=?,
    ";

    // Dynamically build columns and parameters
    foreach ($checkboxes as $c) {
        $cb = isset($_POST["edit_{$c}_cb"]) ? '1' : '0';
        $remarks = $_POST["edit_{$c}_remarks"] ?? '';
        $query .= "{$c}_cb=?, {$c}_remarks=?, ";
        array_push($values, $cb, $remarks);
    }

    $query .= "status=?, edited=? WHERE id=? AND delete_status=0";
    array_push($values, $status, $edited, $id);

    // Bind dynamically
    $stmt = $conn->prepare($query);
    $types = str_repeat('s', count($values) - 2) . 'si'; // All strings except price (float) and id (int)
    $types[5] = 'd'; // 6th param is price (double)
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    echo json_encode([
        'success' => $stmt->affected_rows > 0,
        'message' => $stmt->affected_rows > 0
            ? 'PO record updated successfully'
            : 'No changes detected or record not found'
    ]);
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
