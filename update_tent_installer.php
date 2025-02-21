<?php
header('Content-Type: application/json');
require_once 'db_asset.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input values
    $status   = mysqli_real_escape_string($conn, $_POST['clientStatus']);
    $tentIds  = explode(',', $_POST['tentNumber']);
    $clientId = mysqli_real_escape_string($conn, $_POST['clientId']);
    $tent_no  = mysqli_real_escape_string($conn, $_POST['tentNumber']);
    $debug = array(
        'received_status'      => $status,
        'received_tent_ids'    => $tentIds,
        'received_client_id'   => $clientId,
        'updates'              => array()
    );

    $success = true;

    // Update the tent_status table using the client ID (only once)
    $updateTentStatusQuery = "UPDATE tent SET status = '$status', tent_no = '$tent_no' WHERE id = '$clientId'";
    $tentStatusResult = mysqli_query($conn, $updateTentStatusQuery);

    // Process each tent ID
    foreach ($tentIds as $tentId) {
        $tentId = mysqli_real_escape_string($conn, trim($tentId));

        // Update the tent table for the specific tent number
        $updateTentQuery = "UPDATE tent_status SET Status = '$status' WHERE id = '$tentId'";
        $tentResult = mysqli_query($conn, $updateTentQuery);

        // Collect debug info for this iteration
        $debug['updates'][] = array(
            'tent_id'             => $tentId,
            'tent_status_query'   => $updateTentStatusQuery,
            'tent_query'          => $updateTentQuery,
            'tent_status_success' => $tentStatusResult,
            'tent_success'        => $tentResult,
            'error'               => mysqli_error($conn)
        );

        if (!$tentStatusResult || !$tentResult) {
            $success = false;
        }
    }

    echo json_encode([
        'success' => $success,
        'debug'   => $debug
    ]);
}

mysqli_close($conn);
