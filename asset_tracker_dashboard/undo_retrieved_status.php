<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $tentId = input_int($_POST, 'tent_Id');
    mysqli_begin_transaction($conn);
    $record = db_fetch_one($conn, 'SELECT tent_no FROM tent WHERE id = ? FOR UPDATE', 'i', [$tentId]);
    if (!$record) {
        throw new InvalidArgumentException('Tent request was not found.');
    }
    foreach (input_id_list($record['tent_no'] ?? '') as $tentNo) {
        $stmt = db_execute($conn, "UPDATE tent_status SET Status = 'For Retrieval' WHERE id = ?", 'i', [$tentNo]);
        mysqli_stmt_close($stmt);
    }
    $stmt = db_execute($conn, "UPDATE tent SET status = 'For Retrieval' WHERE id = ?", 'i', [$tentId]);
    mysqli_stmt_close($stmt);
    mysqli_commit($conn);
    api_response(true, 'Status updated successfully.');
} catch (InvalidArgumentException $error) {
    mysqli_rollback($conn);
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    api_database_error($error);
}
