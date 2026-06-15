<?php
require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $stmt = db_execute($conn, "
        UPDATE deployments
        SET status = 'For Retrieval'
        WHERE status = 'Deployed' AND retrieval_date <= CURDATE()
    ");
    $updatedCount = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    $overdue = db_fetch_one($conn, "
        SELECT COUNT(*) AS count
        FROM deployments
        WHERE status = 'For Retrieval' AND retrieval_date < CURDATE()
    ") ?? [];

    api_response(true, '', [
        'updated_count' => $updatedCount,
        'overdue_count' => (int) ($overdue['count'] ?? 0),
    ]);
} catch (Throwable $error) {
    api_database_error($error);
}
