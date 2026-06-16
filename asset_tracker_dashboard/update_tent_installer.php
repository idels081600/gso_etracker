<?php

$assetRoot = is_dir(__DIR__ . '/asset_tracker_dashboard')
    ? __DIR__ . '/asset_tracker_dashboard'
    : dirname(__DIR__) . '/asset_tracker_dashboard';

require_once $assetRoot . '/app_security.php';
require_once $assetRoot . '/api_helpers.php';
require_once $assetRoot . '/validators.php';
require_once $assetRoot . '/db_asset.php';

asset_require_auth(['TENT INSTALLERS', 'ASSET', 'ASSET2', 'Admin', 'master_admin']);
asset_require_post();

try {
    $clientId = input_int($_POST, 'clientId');
    $newStatus = input_enum($_POST, 'clientStatus', ['Installed', 'For Retrieval', 'Retrieved']);
    $tentIds = input_id_list($_POST['tentNumber'] ?? '');
    sort($tentIds);

    mysqli_begin_transaction($conn);
    $record = db_fetch_one(
        $conn,
        'SELECT status, tent_no, no_of_tents FROM tent WHERE id = ? FOR UPDATE',
        'i',
        [$clientId]
    );
    if (!$record) {
        throw new InvalidArgumentException('Tent request was not found.');
    }

    $currentStatus = (string) ($record['status'] ?? '');
    $allowedTransitions = [
        'Pending' => ['Installed'],
        'Installed' => ['For Retrieval', 'Retrieved'],
        'For Retrieval' => ['Retrieved'],
    ];
    if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
        throw new InvalidArgumentException("The status cannot be changed from {$currentStatus} to {$newStatus}.");
    }

    $currentTentIds = trim((string) ($record['tent_no'] ?? '')) === ''
        ? []
        : input_id_list($record['tent_no']);
    sort($currentTentIds);

    if ($currentStatus === 'Pending') {
        if (count($tentIds) !== (int) $record['no_of_tents']) {
            throw new InvalidArgumentException('Select exactly ' . (int) $record['no_of_tents'] . ' available tent(s).');
        }
    } elseif ($tentIds !== $currentTentIds) {
        throw new InvalidArgumentException('Assigned tent numbers cannot be changed while completing this job.');
    }

    foreach ($tentIds as $tentId) {
        $inventory = db_fetch_one($conn, 'SELECT Status FROM tent_status WHERE id = ? FOR UPDATE', 'i', [$tentId]);
        if (!$inventory) {
            throw new InvalidArgumentException("Tent {$tentId} does not exist.");
        }
        if ($currentStatus === 'Pending' && (string) $inventory['Status'] !== 'Retrieved') {
            throw new InvalidArgumentException("Tent {$tentId} is no longer available.");
        }
        if ($currentStatus === 'Pending') {
            $conflict = db_fetch_one(
                $conn,
                "SELECT id
                   FROM tent
                  WHERE id <> ?
                    AND status IN ('Installed', 'For Retrieval', 'Long Term')
                    AND FIND_IN_SET(?, REPLACE(tent_no, ' ', '')) > 0
                  LIMIT 1
                  FOR UPDATE",
                'ii',
                [$clientId, $tentId]
            );
            if ($conflict) {
                throw new InvalidArgumentException("Tent {$tentId} is assigned to another active request.");
            }
        }
    }

    foreach (array_diff($currentTentIds, $tentIds) as $releasedTentId) {
        $inventory = db_fetch_one($conn, 'SELECT Status FROM tent_status WHERE id = ? FOR UPDATE', 'i', [$releasedTentId]);
        if ($inventory) {
            $stmt = db_execute($conn, "UPDATE tent_status SET Status = 'Retrieved' WHERE id = ?", 'i', [$releasedTentId]);
            mysqli_stmt_close($stmt);
        }
    }

    $stmt = db_execute(
        $conn,
        'UPDATE tent SET status = ?, tent_no = ? WHERE id = ?',
        'ssi',
        [$newStatus, implode(',', $tentIds), $clientId]
    );
    mysqli_stmt_close($stmt);

    foreach ($tentIds as $tentId) {
        $stmt = db_execute($conn, 'UPDATE tent_status SET Status = ? WHERE id = ?', 'si', [$newStatus, $tentId]);
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
    api_response(true, 'Tent job updated successfully.', [
        'status' => $newStatus,
        'tent_ids' => $tentIds,
    ]);
} catch (InvalidArgumentException $error) {
    mysqli_rollback($conn);
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    api_database_error($error);
}
