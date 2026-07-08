<?php

require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';
require_once dirname(__DIR__) . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $plateNo = input_string($_POST, 'plate_no', 30);
    $stmt = db_execute($conn, 'DELETE FROM vehicle_records WHERE plate_no = ?', 's', [$plateNo]);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    api_response(true, $affected > 0 ? 'Vehicle deleted successfully.' : 'Vehicle was not found.', [
        'affected_rows' => $affected,
    ]);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
