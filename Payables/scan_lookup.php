<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_document_tracking.php';

$rawBarcode = trim($_GET['code'] ?? '');
$barcodeCode = payables_normalize_barcode_code($rawBarcode);
if ($barcodeCode === '') {
    payables_json_response(['success' => false, 'error' => 'Scan or enter a registered barcode.'], 422);
}

$document = payables_find_assigned_document_by_barcode($barcodeCode);
if (!$document) {
    payables_json_response([
        'success' => false,
        'error' => 'No registered document found for barcode ' . $barcodeCode . '.',
        'matches' => [],
    ], 404);
}

payables_json_response([
    'success' => true,
    'code' => $barcodeCode,
    'matches' => [$document],
]);
