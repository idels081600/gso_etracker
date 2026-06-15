<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
header('Content-Type: application/json; charset=utf-8');

try {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $row = $id && $id > 0 ? db_fetch_one($conn, 'SELECT * FROM tent WHERE id = ?', 'i', [$id]) : null;
    echo json_encode($row ?: (object) []);
} catch (Throwable $error) {
    error_log($error->getMessage());
    echo json_encode((object) []);
}
