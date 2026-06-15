<?php

require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/db_asset.php';
require_once __DIR__ . '/tent_status_auto_update_service.php';

asset_require_auth();
asset_require_post();

try {
    $result = auto_update_due_tent_statuses($conn);
    api_response(
        true,
        $result['updated_count'] > 0
            ? "Updated {$result['updated_count']} due tent request(s)."
            : 'No due tent requests required an update.',
        $result
    );
} catch (Throwable $error) {
    api_database_error($error);
}
