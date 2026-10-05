<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This synchronization can only be run from the command line.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = require __DIR__ . '/config/database.php';
$lockName = 'rice_wave_names_from_first_sync';
$lock = $conn->query("SELECT GET_LOCK('{$lockName}', 30) AS acquired")->fetch_assoc();

if ((int)($lock['acquired'] ?? 0) !== 1) {
    throw new RuntimeException('Another rice name synchronization is already running.');
}

$targets = [
    'third_wave' => 'rice_third_wave_households',
    'fourth_wave' => 'rice_fourth_wave_households',
];

try {
    $duplicateCodes = (int)$conn->query(
        "SELECT COUNT(*) AS total
         FROM (
             SELECT household_code
             FROM rice_households
             GROUP BY household_code
             HAVING COUNT(*) > 1
         ) duplicate_codes"
    )->fetch_assoc()['total'];

    if ($duplicateCodes !== 0) {
        throw new RuntimeException("First batch contains {$duplicateCodes} duplicate household codes; synchronization was cancelled.");
    }

    $before = [];
    foreach ($targets as $wave => $table) {
        $summary = $conn->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(source.id IS NULL), 0) AS unmatched,
                    COALESCE(SUM(
                        NOT (COALESCE(target.household_name, '') <=> COALESCE(source.household_name, ''))
                        OR NOT (COALESCE(target.last_name, '') <=> COALESCE(source.last_name, ''))
                        OR NOT (COALESCE(target.first_name, '') <=> COALESCE(source.first_name, ''))
                    ), 0) AS differing
             FROM {$table} target
             LEFT JOIN rice_households source
                ON source.household_code = target.household_code"
        )->fetch_assoc();

        if ((int)$summary['unmatched'] !== 0) {
            throw new RuntimeException("{$wave} contains {$summary['unmatched']} household codes not found in the first batch; synchronization was cancelled.");
        }
        $before[$wave] = $summary;
    }

    $conn->begin_transaction();
    try {
        $changed = [];
        foreach ($targets as $wave => $table) {
            $conn->query(
                "UPDATE {$table} target
                 INNER JOIN rice_households source
                    ON source.household_code = target.household_code
                 SET target.household_name = source.household_name,
                     target.last_name = source.last_name,
                     target.first_name = source.first_name
                 WHERE NOT (COALESCE(target.household_name, '') <=> COALESCE(source.household_name, ''))
                    OR NOT (COALESCE(target.last_name, '') <=> COALESCE(source.last_name, ''))
                    OR NOT (COALESCE(target.first_name, '') <=> COALESCE(source.first_name, ''))"
            );
            $changed[$wave] = $conn->affected_rows;
        }

        foreach ($targets as $wave => $table) {
            $remaining = (int)$conn->query(
                "SELECT COUNT(*) AS total
                 FROM {$table} target
                 INNER JOIN rice_households source
                    ON source.household_code = target.household_code
                 WHERE NOT (COALESCE(target.household_name, '') <=> COALESCE(source.household_name, ''))
                    OR NOT (COALESCE(target.last_name, '') <=> COALESCE(source.last_name, ''))
                    OR NOT (COALESCE(target.first_name, '') <=> COALESCE(source.first_name, ''))"
            )->fetch_assoc()['total'];

            if ($remaining !== 0) {
                throw new RuntimeException("{$wave} still has {$remaining} name mismatches after synchronization.");
            }
        }

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }

    foreach ($targets as $wave => $table) {
        echo sprintf(
            "%s: %d total, %d names updated, 0 unmatched, 0 remaining mismatches.\n",
            $wave,
            (int)$before[$wave]['total'],
            (int)$changed[$wave]
        );
    }
    echo "Copied household name, last name, and first name from the first batch by exact household code.\n";
    echo "Middle names, recipient attributes, claim records, proof records, and claim statuses were not changed.\n";
} finally {
    $conn->query("SELECT RELEASE_LOCK('{$lockName}')");
}
