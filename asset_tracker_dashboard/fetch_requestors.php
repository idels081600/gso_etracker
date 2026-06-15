<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
header('Content-Type: application/json; charset=utf-8');

try {
    $pattern = '%' . trim((string) ($_GET['query'] ?? '')) . '%';
    $stmt = db_execute($conn, 'SELECT requestor FROM requestingParty WHERE requestor LIKE ? ORDER BY requestor LIMIT 20', 's', [$pattern]);
    $result = mysqli_stmt_get_result($stmt);
    $requestors = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requestors[] = $row['requestor'];
    }
    mysqli_stmt_close($stmt);
    echo json_encode($requestors);
} catch (Throwable $error) {
    error_log($error->getMessage());
    echo json_encode([]);
}
