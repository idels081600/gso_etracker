<?php

require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';
require_once dirname(__DIR__) . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $repairId = input_int($_POST, 'repair_id');
    $status = input_enum($_POST, 'status', ['Pending', 'In Progress', 'Completed', 'Cancelled']);
    $stmt = db_execute($conn, 'UPDATE motorpool_repair SET status = ? WHERE id = ?', 'si', [$status, $repairId]);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    api_response(true, 'Repair status updated successfully.', ['affected_rows' => $affected]);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
