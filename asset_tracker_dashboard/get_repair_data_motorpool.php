<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $repairId = input_int($_POST, 'repair_id');
    $repair = db_fetch_one($conn, 'SELECT * FROM motorpool_repair WHERE id = ?', 'i', [$repairId]);
    if (!$repair) {
        api_response(false, 'Repair record was not found.', [], 404);
    }
    api_response(true, 'Repair record loaded.', ['repair' => $repair, 'success' => true] + $repair);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
