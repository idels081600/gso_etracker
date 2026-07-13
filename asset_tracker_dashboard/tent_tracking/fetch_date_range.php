<?php

require_once dirname(__DIR__) . '/page_bootstrap.php';
require_once dirname(__DIR__) . '/db_asset.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';

asset_require_post();

try {
    $from = input_date($_POST, 'from');
    $to = input_date($_POST, 'to');

    if ($from > $to) {
        throw new InvalidArgumentException('The start date must be on or before the end date.');
    }

    $stmt = db_execute(
        $conn,
        "SELECT id, tent_no, no_of_tents, name, Contact_no, location, address, purpose, date, retrieval_date, status
         FROM tent
         WHERE date BETWEEN ? AND ?
         ORDER BY date ASC, id ASC",
        'ss',
        [$from, $to]
    );
    $result = mysqli_stmt_get_result($stmt);
    $records = [];

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $records[] = [
            'id' => (int) $row['id'],
            'tent_no' => (string) ($row['tent_no'] ?? ''),
            'no_of_tents' => (int) ($row['no_of_tents'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'contact' => (string) ($row['Contact_no'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'purpose' => (string) ($row['purpose'] ?? ''),
            'installation_date' => (string) ($row['date'] ?? ''),
            'retrieval_date' => (string) ($row['retrieval_date'] ?? ''),
            'status' => (string) ($row['status'] ?? 'Pending'),
        ];
    }
    mysqli_stmt_close($stmt);

    api_response(true, 'Date range records loaded.', [
        'from' => $from,
        'to' => $to,
        'count' => count($records),
        'records' => $records,
    ]);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
