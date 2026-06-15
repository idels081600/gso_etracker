<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/db_asset.php';
require_once __DIR__ . '/tent_status_auto_update_service.php';

date_default_timezone_set('Asia/Manila');

try {
    $result = auto_update_due_tent_statuses($conn);
    echo sprintf(
        "[%s] Updated %d request(s) and %d tent inventory record(s).\n",
        date('Y-m-d H:i:s'),
        $result['updated_count'],
        count($result['updated_tent_ids'])
    );
    if ($result['skipped_requests'] !== []) {
        fwrite(STDERR, sprintf(
            "[%s] Skipped %d inconsistent request(s). Check assigned tent statuses.\n",
            date('Y-m-d H:i:s'),
            count($result['skipped_requests'])
        ));
    }
    exit(0);
} catch (Throwable $error) {
    error_log($error->getMessage());
    fwrite(STDERR, sprintf("[%s] Tent status auto-update failed.\n", date('Y-m-d H:i:s')));
    exit(1);
}
