<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
require_once 'advance_po_db.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID not provided']);
    exit;
}

$id = (int) $_GET['id'];

$query = "SELECT id, supplier, po_date, po_number, description, office, price, destination,
                 preAuditchecklist_cb, obr_cb, dv_cb, billing_request_cb, certWarranty_cb, omnibus_cb,
                 ris_cb, acceptance_cb, rfq_cb, recommending_cb, PR_cb, PO_cb, receipts_cb,
                 delegation_cb, mayorsPermit_cb, jetsCert_cb, waste_material_report_cb, post_repair_inspection_cb, repair_history_of_property_cb, warranty_certificate_cb,
                 status,
                 preAuditchecklist_remarks, obr_remarks, dv_remarks, billing_request_remarks, certWarranty_remarks,
                 omnibus_remarks, ris_remarks, acceptance_remarks, rfq_remarks, recommending_remarks,
                 PR_remarks, PO_remarks, receipts_remarks, delegation_remarks, mayorsPermit_remarks, jetsCert_remarks,
                 waste_material_report_remarks, post_repair_inspection_remarks, repair_history_of_property_remarks, warranty_certificate_remarks
          FROM poMonitoring WHERE id = ? AND delete_status = 0";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

mysqli_close($conn);
