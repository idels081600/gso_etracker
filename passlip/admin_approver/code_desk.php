<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'invalid_request']);
    exit;
}

include '../dbh.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

function send_json($payload)
{
    echo json_encode($payload);
    exit;
}

function add_scan_candidate(&$candidates, $value)
{
    $value = trim((string) $value);
    $value = trim($value, " \t\n\r\0\x0B\"'");

    if ($value !== '' && !in_array($value, $candidates, true)) {
        $candidates[] = $value;
    }
}

function bind_dynamic_params($stmt, $types, $params)
{
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }

    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function scan_candidates_from_raw($raw)
{
    $candidates = [];
    add_scan_candidate($candidates, $raw);

    $withoutPrefix = preg_replace('/^(TCWS|TWCS)[_\s-]*Employee[_\s:|#-]*/i', '', $raw);
    add_scan_candidate($candidates, $withoutPrefix);

    foreach (preg_split('/[\r\n|,;]+/', $raw) as $part) {
        add_scan_candidate($candidates, $part);
        add_scan_candidate($candidates, preg_replace('/^(TCWS|TWCS)[_\s-]*Employee[_\s:|#-]*/i', '', $part));
    }

    if (preg_match_all('/[A-Za-z0-9_. -]+/', $raw, $matches)) {
        foreach ($matches[0] as $part) {
            add_scan_candidate($candidates, preg_replace('/^(TCWS|TWCS)[_\s-]*Employee[_\s:|#-]*/i', '', $part));
        }
    }

    foreach ($candidates as $candidate) {
        if (strpos($candidate, '_') !== false) {
            add_scan_candidate($candidates, str_replace('_', ' ', $candidate));
        }
    }

    foreach ($candidates as $candidate) {
        if (strpos($candidate, '6') !== false) {
            add_scan_candidate($candidates, str_replace('6', 'ñ', $candidate));
            add_scan_candidate($candidates, str_replace('6', 'Ñ', $candidate));
        }
    }

    return $candidates;
}

function expand_candidates_from_accounts($conn, $candidates)
{
    $expanded = $candidates;

    foreach ($candidates as $candidate) {
        $stmt = $conn->prepare(
            "SELECT `username`, `name`
               FROM `logindb`
              WHERE `Id` = ?
                 OR BINARY `username` = ?
                 OR BINARY `name` = ?
              LIMIT 1"
        );

        if (!$stmt) {
            continue;
        }

        $id = ctype_digit($candidate) ? (int) $candidate : 0;
        $stmt->bind_param('iss', $id, $candidate, $candidate);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            add_scan_candidate($expanded, $row['username'] ?? '');
            add_scan_candidate($expanded, $row['name'] ?? '');
        }

        $stmt->close();
    }

    return $expanded;
}

function find_scannable_request($conn, $names, $status, $todayOnly)
{
    if (count($names) === 0) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $dateClause = $todayOnly ? ' AND DATE(`date`) = CURDATE()' : '';
    $sql = "SELECT *
              FROM `request`
             WHERE `name` IN ($placeholders)
               AND `Status` = ?
               AND (`Role` = 'Employee' OR `Role` = 'TCWS Employee')
               $dateClause
             ORDER BY `id` DESC
             LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $params = array_merge($names, [$status]);
    bind_dynamic_params($stmt, str_repeat('s', count($names)) . 's', $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function update_departure_scan($conn, $id)
{
    $stmt = $conn->prepare(
        "UPDATE `request`
            SET `timedept` = NOW(),
                `Status` = 'Approved',
                `status1` = 'Pass-Slip',
                `ImageName` = 'Check-Approved.png'
          WHERE `id` = ?"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function update_return_scan($conn, $id, $remarks, $durationSeconds)
{
    $currentTime = date('H:i');
    $stmt = $conn->prepare(
        "UPDATE `request`
            SET `time_returned` = ?,
                `Status` = 'Done',
                `status1` = 'Present',
                `remarks` = ?,
                `duration_seconds` = ?
          WHERE `id` = ?"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssii', $currentTime, $remarks, $durationSeconds, $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

$rawScan = trim((string) ($_POST['scannedData'] ?? ''));
if ($rawScan === '') {
    send_json(['status' => 'invalid_request', 'message' => 'Scan a valid employee QR code.']);
}

$candidates = scan_candidates_from_raw($rawScan);
$candidates = expand_candidates_from_accounts($conn, $candidates);

$departing = find_scannable_request($conn, $candidates, 'Partially Approved', true);
if ($departing) {
    if (update_departure_scan($conn, (int) $departing['id'])) {
        send_json([
            'status' => 'exists',
            'name' => $departing['name'],
            'message' => 'Pass slip activated. Take care.'
        ]);
    }

    send_json(['status' => 'update_error', 'message' => 'Unable to activate this pass slip.']);
}

// Return scans should still work after a day rolls over while someone is outside.
$returning = find_scannable_request($conn, $candidates, 'Approved', false);
if ($returning) {
    $estimatedTime = new DateTime($returning['esttime']);
    $actualTime = new DateTime();

    if ($actualTime < $estimatedTime) {
        $interval = $estimatedTime->diff($actualTime);
        $arrivalLabel = 'early';
    } else {
        $interval = $actualTime->diff($estimatedTime);
        $arrivalLabel = 'late';
    }

    $hours = ($interval->days * 24) + $interval->h;
    $minutes = $interval->i;
    $timeDifference = trim(($hours > 0 ? $hours . " hour" . ($hours > 1 ? "s " : " ") : "") . ($minutes > 0 ? $minutes . " minute" . ($minutes > 1 ? "s" : "") : ""));

    if ($timeDifference === '') {
        $remarks = 'Arrived on time';
        $statusText = 'Arrived on time';
    } else {
        $remarks = "Arrived $timeDifference $arrivalLabel";
        $statusText = $remarks;
    }

    $timedept = new DateTime($returning['timedept']);
    $durationSeconds = max(0, $actualTime->getTimestamp() - $timedept->getTimestamp());

    if (update_return_scan($conn, (int) $returning['id'], $remarks, $durationSeconds)) {
        send_json([
            'status' => $statusText,
            'name' => $returning['name'],
            'message' => 'Return scan recorded.'
        ]);
    }

    send_json(['status' => 'update_error', 'message' => 'Unable to record the return scan.']);
}

send_json([
    'status' => 'not_exists',
    'message' => 'No active approved or partially approved pass slip found for this QR code.'
]);

