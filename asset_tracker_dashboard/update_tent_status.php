<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $tentNumber = input_int($_POST, 'tentno');
    $status = input_enum($_POST, 'status', ['Pending', 'Installed', 'For Retrieval', 'Retrieved', 'Long Term', 'Available']);
    $stmt = db_execute($conn, 'UPDATE tent_status SET Status = ? WHERE id = ?', 'si', [$status, $tentNumber]);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    api_response(true, 'Tent status updated successfully.', ['affected_rows' => $affected]);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
