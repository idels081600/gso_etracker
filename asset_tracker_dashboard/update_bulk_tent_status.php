<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $ids = input_id_list($_POST['tent_ids'] ?? []);
    $status = input_enum($_POST, 'new_status', ['null', 'Installed', 'For Retrieval', 'Retrieved', 'Long Term', 'Pending']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db_execute(
        $conn,
        "UPDATE tent_status SET Status = ? WHERE id IN ({$placeholders})",
        's' . str_repeat('i', count($ids)),
        array_merge([$status], $ids)
    );
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    api_response(true, "Updated {$affected} tent status record(s).", ['updated_count' => $affected, 'status' => $status]);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
