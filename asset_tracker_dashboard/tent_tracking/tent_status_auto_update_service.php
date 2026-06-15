<?php

require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';

/**
 * Moves installed requests due today into For Retrieval.
 *
 * Eligibility and assigned tent IDs are read exclusively from the database.
 * The caller is responsible for providing an open mysqli connection.
 */
function auto_update_due_tent_statuses(mysqli $conn): array
{
    mysqli_begin_transaction($conn);

    try {
        $stmt = db_execute($conn, "SET time_zone = '+08:00'");
        mysqli_stmt_close($stmt);

        $stmt = db_execute(
            $conn,
            "SELECT id, tent_no, retrieval_date
             FROM tent
             WHERE status = 'Installed'
               AND retrieval_date IS NOT NULL
               AND retrieval_date = CURDATE()
             FOR UPDATE"
        );
        $result = mysqli_stmt_get_result($stmt);
        $requests = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);

        $updatedRequestIds = [];
        $updatedTentIds = [];
        $skippedRequests = [];

        foreach ($requests as $request) {
            $requestId = (int) $request['id'];
            $tentNumberText = trim((string) ($request['tent_no'] ?? ''));

            if ($tentNumberText === '') {
                $skippedRequests[] = [
                    'request_id' => $requestId,
                    'reason' => 'No assigned tents.',
                ];
                continue;
            }

            try {
                $tentIds = input_id_list($tentNumberText);
            } catch (InvalidArgumentException) {
                $skippedRequests[] = [
                    'request_id' => $requestId,
                    'reason' => 'Assigned tent numbers are invalid.',
                ];
                continue;
            }

            $tentStates = [];
            $compatible = true;
            foreach ($tentIds as $tentId) {
                $tentState = db_fetch_one(
                    $conn,
                    'SELECT Status FROM tent_status WHERE id = ? FOR UPDATE',
                    'i',
                    [$tentId]
                );
                if (!$tentState || !in_array($tentState['Status'], ['Installed', 'For Retrieval'], true)) {
                    $compatible = false;
                    break;
                }
                $tentStates[$tentId] = $tentState['Status'];
            }

            if (!$compatible || count($tentStates) !== count($tentIds)) {
                $skippedRequests[] = [
                    'request_id' => $requestId,
                    'reason' => 'One or more assigned tents are missing or not installed.',
                ];
                continue;
            }

            $stmt = db_execute(
                $conn,
                "UPDATE tent
                 SET status = 'For Retrieval'
                 WHERE id = ?
                   AND status = 'Installed'
                   AND retrieval_date = CURDATE()",
                'i',
                [$requestId]
            );
            $requestUpdated = mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);

            if (!$requestUpdated) {
                continue;
            }

            foreach ($tentStates as $tentId => $tentStatus) {
                if ($tentStatus === 'For Retrieval') {
                    continue;
                }
                $stmt = db_execute(
                    $conn,
                    "UPDATE tent_status
                     SET Status = 'For Retrieval'
                     WHERE id = ?
                       AND Status = 'Installed'",
                    'i',
                    [$tentId]
                );
                if (mysqli_stmt_affected_rows($stmt) === 1) {
                    $updatedTentIds[] = $tentId;
                }
                mysqli_stmt_close($stmt);
            }

            $updatedRequestIds[] = $requestId;
        }

        mysqli_commit($conn);

        return [
            'updated_count' => count($updatedRequestIds),
            'updated_request_ids' => $updatedRequestIds,
            'updated_tent_ids' => array_values(array_unique($updatedTentIds)),
            'skipped_count' => count($skippedRequests),
            'skipped_requests' => $skippedRequests,
        ];
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}
