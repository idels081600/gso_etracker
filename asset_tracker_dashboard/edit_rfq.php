<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $id = input_int($_POST, 'id');
    $rfqNo = input_string($_POST, 'rfq_no', 80);
    $prNo = input_string($_POST, 'pr_no', 80);
    $rfqName = input_string($_POST, 'rfq_name', 255);
    $date = input_date($_POST, 'date');
    $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount < 0) {
        throw new InvalidArgumentException('amount must be a valid non-negative number.');
    }
    $requestor = input_string($_POST, 'requestor', 255);
    $supplier = input_string($_POST, 'supplier', 255);

    $stmt = db_execute(
        $conn,
        'UPDATE RFQ SET rfq_no = ?, pr_no = ?, rfq_name = ?, date = ?, amount = ?, requestor = ?, supplier = ? WHERE id = ?',
        'ssssdssi',
        [$rfqNo, $prNo, $rfqName, $date, $amount, $requestor, $supplier, $id]
    );
    mysqli_stmt_close($stmt);
    api_response(true, 'RFQ updated successfully.');
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
