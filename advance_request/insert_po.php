<?php
session_start();

// ========================== AUTH CHECK ==========================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// ========================== HEADERS ==========================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'advance_po_db.php';

// ========================== DEBUG SETTINGS ==========================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ========================== METHOD CHECK ==========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
    exit;
}

// ========================== READ JSON ==========================
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    if (isset($data['single_item']) && $data['single_item'] === true) {
        $item = $data['item'];
        $success = insertSinglePOItem($conn, $item);
        if ($success) {
            $response = ['success' => true, 'message' => 'PO item inserted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to insert PO item'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid data format'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
}

mysqli_close($conn);
echo json_encode($response);
exit;

// ======================== FUNCTION ==========================

function insertSinglePOItem($conn, $item)
{
    $supplier = trim($item['supplier'] ?? '');
    $po_date = trim($item['po_date'] ?? '');
    $po_number = trim($item['po_number'] ?? '');
    $description = trim($item['description'] ?? '');
    $office = trim($item['office'] ?? '');
    $price = trim($item['amount'] ?? '');
    $destination = trim($item['destination'] ?? '');
    $delete_status = 0;
    $status = 'Pending';
    $edited = date('Y-m-d H:i:s');

    // ✅ Field names exactly match your DB columns
    $fields = [
        'preAuditchecklist_cb',
        'preAuditchecklist_remarks',
        'obr_cb',
        'obr_remarks',
        'dv_cb',
        'dv_remarks',
        'billing_request_cb',
        'billing_request_remarks',
        'certWarranty_cb',
        'certWarranty_remarks',
        'omnibus_cb',
        'omnibus_remarks',
        'ris_cb',
        'ris_remarks',
        'acceptance_cb',
        'acceptance_remarks',
        'rfq_cb',
        'rfq_remarks',
        'recommending_cb',
        'recommending_remarks',
        'PR_cb',
        'PR_remarks',
        'PO_cb',
        'PO_remarks',
        'receipts_cb',
        'receipts_remarks',
        'delegation_cb',
        'delegation_remarks',
        'mayorsPermit_cb',
        'mayorsPermit_remarks',
        'justification_cb',
        'justification_remarks',
        'pre_repair_inspection_cb',
        'pre_repair_inspection_remarks',
        'jetsCert_cb',
        'jetsCert_remarks',
        'waste_material_report_cb',
        'waste_material_report_remarks',
        'post_repair_inspection_cb',
        'post_repair_inspection_remarks',
        'repair_history_of_property_cb',
        'repair_history_of_property_remarks',
        'warranty_certificate_cb',
        'warranty_certificate_remarks'
    ];

    // Prepare values from input
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim($item[$field] ?? '');
    }

    // ✅ SQL Statement — exactly 54 placeholders
    $stmt = $conn->prepare("
        INSERT INTO poMonitoring (
            supplier, po_date, po_number, description, office, price, destination,
            preAuditchecklist_cb, preAuditchecklist_remarks, obr_cb, obr_remarks, dv_cb, dv_remarks,
            billing_request_cb, billing_request_remarks, certWarranty_cb, certWarranty_remarks,
            omnibus_cb, omnibus_remarks, ris_cb, ris_remarks, acceptance_cb, acceptance_remarks,
            rfq_cb, rfq_remarks, recommending_cb, recommending_remarks, PR_cb, PR_remarks,
            PO_cb, PO_remarks, receipts_cb, receipts_remarks, delegation_cb, delegation_remarks,
            mayorsPermit_cb, mayorsPermit_remarks, justification_cb, justification_remarks, pre_repair_inspection_cb, pre_repair_inspection_remarks, jetsCert_cb, jetsCert_remarks,
            waste_material_report_cb, waste_material_report_remarks,
            post_repair_inspection_cb, post_repair_inspection_remarks,
            repair_history_of_property_cb, repair_history_of_property_remarks,
            warranty_certificate_cb, warranty_certificate_remarks,
            delete_status, status, edited
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?
        )
    ");

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // ✅ Bind parameters (54 total, all strings)
    $stmt->bind_param(
        str_repeat('s', 54),
        $supplier,
        $po_date,
        $po_number,
        $description,
        $office,
        $price,
        $destination,
        $values['preAuditchecklist_cb'],
        $values['preAuditchecklist_remarks'],
        $values['obr_cb'],
        $values['obr_remarks'],
        $values['dv_cb'],
        $values['dv_remarks'],
        $values['billing_request_cb'],
        $values['billing_request_remarks'],
        $values['certWarranty_cb'],
        $values['certWarranty_remarks'],
        $values['omnibus_cb'],
        $values['omnibus_remarks'],
        $values['ris_cb'],
        $values['ris_remarks'],
        $values['acceptance_cb'],
        $values['acceptance_remarks'],
        $values['rfq_cb'],
        $values['rfq_remarks'],
        $values['recommending_cb'],
        $values['recommending_remarks'],
        $values['PR_cb'],
        $values['PR_remarks'],
        $values['PO_cb'],
        $values['PO_remarks'],
        $values['receipts_cb'],
        $values['receipts_remarks'],
        $values['delegation_cb'],
        $values['delegation_remarks'],
        $values['mayorsPermit_cb'],
        $values['mayorsPermit_remarks'],
        $values['justification_cb'],
        $values['justification_remarks'],
        $values['pre_repair_inspection_cb'],
        $values['pre_repair_inspection_remarks'],
        $values['jetsCert_cb'],
        $values['jetsCert_remarks'],
        $values['waste_material_report_cb'],
        $values['waste_material_report_remarks'],
        $values['post_repair_inspection_cb'],
        $values['post_repair_inspection_remarks'],
        $values['repair_history_of_property_cb'],
        $values['repair_history_of_property_remarks'],
        $values['warranty_certificate_cb'],
        $values['warranty_certificate_remarks'],
        $delete_status,
        $status,
        $edited
    );

    $success = $stmt->execute();

    if (!$success) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->close();
    return $success;
}
