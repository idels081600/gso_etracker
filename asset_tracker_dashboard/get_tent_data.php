<?php

$assetRoot = is_dir(__DIR__ . '/asset_tracker_dashboard')
    ? __DIR__ . '/asset_tracker_dashboard'
    : dirname(__DIR__) . '/asset_tracker_dashboard';

require_once $assetRoot . '/app_security.php';
require_once $assetRoot . '/api_helpers.php';
require_once $assetRoot . '/db_asset.php';

asset_require_auth(['TENT INSTALLERS', 'ASSET', 'ASSET2', 'Admin', 'master_admin']);
date_default_timezone_set('Asia/Manila');

try {
    $stmt = db_execute(
        $conn,
        "SELECT id, name, Contact_no AS contact_no, location, address, status, tent_no,
                CASE WHEN status = 'Pending' THEN date ELSE retrieval_date END AS scheduled_date,
                no_of_tents,
                CASE
                    WHEN status = 'Pending' AND date < CURDATE() THEN 'overdue-installation-row'
                    WHEN status IN ('Installed', 'For Retrieval') AND retrieval_date < CURDATE() THEN 'overdue-row'
                    WHEN status IN ('Installed', 'For Retrieval') THEN 'for-retrieval-row'
                    ELSE 'pending-row'
                END AS row_class
           FROM tent
          WHERE (status = 'Pending' AND date <= CURDATE())
             OR (status IN ('Installed', 'For Retrieval') AND retrieval_date <= CURDATE())
          ORDER BY
                CASE
                    WHEN status = 'Pending' AND date < CURDATE() THEN 0
                    WHEN status IN ('Installed', 'For Retrieval') AND retrieval_date < CURDATE() THEN 1
                    WHEN status = 'Pending' THEN 2
                    ELSE 3
                END,
                scheduled_date ASC,
                id ASC"
    );
    $result = mysqli_stmt_get_result($stmt);
    $records = [];

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $records[] = [
            'id' => (int) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'contact_no' => (string) ($row['contact_no'] ?? ''),
            'no_of_tents' => (int) ($row['no_of_tents'] ?? 0),
            'date' => (string) ($row['scheduled_date'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'tent_no' => (string) ($row['tent_no'] ?? ''),
            'row_class' => (string) ($row['row_class'] ?? ''),
        ];
    }
    mysqli_stmt_close($stmt);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $records,
        'total_records' => count($records),
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    api_database_error($error);
}
