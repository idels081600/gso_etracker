<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    if (isset($_SESSION['last_scan_time']) && time() - (int) $_SESSION['last_scan_time'] < 5) {
        api_response(false, 'Wait five seconds before scanning again.', [], 429);
    }

    $plateNo = input_string($_POST, 'scannedData', 30);
    mysqli_begin_transaction($conn);
    $record = db_fetch_one(
        $conn,
        "SELECT id, Status FROM Transportation WHERE Plate_no = ? AND Status IN ('Stand By', 'Departed') ORDER BY FIELD(Status, 'Departed', 'Stand By'), id DESC LIMIT 1 FOR UPDATE",
        's',
        [$plateNo]
    );

    if (!$record) {
        mysqli_rollback($conn);
        api_response(false, 'Vehicle dispatch record was not found.', ['result' => 'not_exists'], 404);
    }

    $arriving = $record['Status'] === 'Departed';
    $transportStatus = $arriving ? 'Arrived' : 'Departed';
    $vehicleStatus = $arriving ? 'Stand By' : 'Departed';
    $timeColumn = $arriving ? 'Arrival' : 'Departure';

    $stmt = db_execute(
        $conn,
        "UPDATE Transportation SET {$timeColumn} = NOW(), Status = ?, Status1 = ? WHERE id = ?",
        'ssi',
        [$transportStatus, $transportStatus, $record['id']]
    );
    mysqli_stmt_close($stmt);

    $stmt = db_execute($conn, 'UPDATE Vehicle SET Status = ? WHERE Plate_no = ?', 'ss', [$vehicleStatus, $plateNo]);
    mysqli_stmt_close($stmt);
    mysqli_commit($conn);

    $_SESSION['last_scan_time'] = time();
    api_response(true, $arriving ? 'Vehicle marked as arrived.' : 'Vehicle marked as departed.', [
        'result' => $arriving ? 'Arrived' : 'exists',
    ]);
} catch (InvalidArgumentException $error) {
    mysqli_rollback($conn);
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    api_database_error($error);
}
