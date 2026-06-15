<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $id = input_int($_POST, 'id');
    $status = input_enum($_POST, 'status', ['Pending', 'Installed', 'For Retrieval', 'Retrieved', 'Long Term']);

    mysqli_begin_transaction($conn);
    $record = db_fetch_one($conn, 'SELECT tent_no FROM tent WHERE id = ? FOR UPDATE', 'i', [$id]);
    if (!$record) {
        throw new InvalidArgumentException('Tent request was not found.');
    }

    $stmt = db_execute($conn, 'UPDATE tent SET status = ? WHERE id = ?', 'si', [$status, $id]);
    mysqli_stmt_close($stmt);

    foreach (input_id_list($record['tent_no'] ?? '') as $tentNo) {
        $stmt = db_execute($conn, 'UPDATE tent_status SET Status = ? WHERE id = ?', 'si', [$status, $tentNo]);
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
    api_response(true, 'Status updated successfully.');
} catch (InvalidArgumentException $error) {
    mysqli_rollback($conn);
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    api_database_error($error);
}
