<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $rowId = input_int($_POST, 'rowId');
    $status = input_enum($_POST, 'newValue', ['Office Clerk', 'CGSO-Head', 'SAP', 'Completed', 'Cancelled']);
    $stmt = db_execute($conn, 'UPDATE RFQ SET Status = ? WHERE id = ?', 'si', [$status, $rowId]);
    mysqli_stmt_close($stmt);
    api_response(true, 'RFQ status updated successfully.');
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
