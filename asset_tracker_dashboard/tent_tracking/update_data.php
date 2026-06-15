<?php

require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';
require_once dirname(__DIR__) . '/db_asset.php';

asset_require_auth();
asset_require_post();

try {
    $id = input_int($_POST, 'id');

    if (!isset($_POST['name1'])) {
        $location = trim((string) ($_POST['other1'] ?? '')) ?: input_string($_POST, 'Location1', 255);
        $stmt = db_execute($conn, 'UPDATE tent SET location = ? WHERE id = ?', 'si', [$location, $id]);
        mysqli_stmt_close($stmt);
        api_response(true, 'Location updated successfully.');
    }

    $numberOfTents = input_int($_POST, 'tent_no1');
    if ($numberOfTents > 300) {
        throw new InvalidArgumentException('tent_no1 exceeds available inventory.');
    }
    $date = input_date($_POST, 'datepicker1');
    $name = input_string($_POST, 'name1', 255);
    $contact = input_string($_POST, 'contact1', 30);
    if (!preg_match('/^\+639\d{9}$/', $contact)) {
        throw new InvalidArgumentException('contact1 must use the format +639 followed by 9 digits.');
    }
    $location = input_string($_POST, 'Location1', 255);
    $purpose = input_string($_POST, 'purpose1', 255);
    $address = input_string($_POST, 'address1', 500);
    $retrievalDate = input_date($_POST, 'duration1');
    $tentIds = trim((string) ($_POST['tentno'] ?? '')) === '' ? [] : input_id_list($_POST['tentno']);
    $requestedStatus = trim((string) ($_POST['status'] ?? 'Pending'));
    $finalStatus = input_enum(['status' => $requestedStatus], 'status', ['Pending', 'Installed', 'For Retrieval', 'Retrieved', 'Long Term']);

    mysqli_begin_transaction($conn);
    $stmt = db_execute(
        $conn,
        'UPDATE tent SET tent_no = ?, date = ?, retrieval_date = ?, name = ?, Contact_no = ?, no_of_tents = ?, location = ?, purpose = ?, address = ?, status = ? WHERE id = ?',
        'sssssissssi',
        [implode(',', $tentIds), $date, $retrievalDate, $name, $contact, $numberOfTents, $location, $purpose, $address, $finalStatus, $id]
    );
    mysqli_stmt_close($stmt);

    foreach ($tentIds as $tentId) {
        $stmt = db_execute($conn, 'UPDATE tent_status SET Status = ? WHERE id = ?', 'si', [$finalStatus, $tentId]);
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
    api_response(true, 'Tent request updated successfully.');
} catch (InvalidArgumentException $error) {
    mysqli_rollback($conn);
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    api_database_error($error);
}
