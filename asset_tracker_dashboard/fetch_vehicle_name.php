<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $plateNo = input_string($_POST, 'plate_no', 30);
    $vehicle = db_fetch_one($conn, 'SELECT Name FROM Vehicle WHERE Plate_No = ?', 's', [$plateNo]);
    echo $vehicle ? $vehicle['Name'] : 'Vehicle not found';
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(422);
    echo 'Vehicle not found';
}
