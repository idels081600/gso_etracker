<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_document_tracking.php';

$documentNo = trim($_GET['code'] ?? '');
if ($documentNo === '') {
    payables_json_response(['success' => false, 'error' => 'Scan or enter a document number.'], 422);
}

$matches = payables_find_documents_by_number($documentNo);
if (!$matches) {
    payables_json_response([
        'success' => false,
        'error' => 'No document found for ' . $documentNo . '.',
        'matches' => [],
    ], 404);
}

payables_json_response([
    'success' => true,
    'code' => $documentNo,
    'matches' => $matches,
]);
