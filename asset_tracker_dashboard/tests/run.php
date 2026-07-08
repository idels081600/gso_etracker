<?php

require_once dirname(__DIR__) . '/validators.php';
require_once dirname(__DIR__) . '/ui_helpers.php';

$failures = [];
$passes = 0;

function check(bool $condition, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes++;
        return;
    }
    $failures[] = $message;
}

function expectInvalid(callable $callback, string $message): void
{
    try {
        $callback();
        check(false, $message);
    } catch (InvalidArgumentException) {
        check(true, $message);
    }
}

check(e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;', 'HTML escaping must neutralize script tags.');
check(input_int(['id' => '42'], 'id') === 42, 'Integer validation must accept valid IDs.');
expectInvalid(fn () => input_int(['id' => '0'], 'id'), 'Integer validation must reject invalid IDs.');
check(input_id_list(['1', '2', '2', '-1']) === [1, 2], 'ID list validation must normalize and deduplicate IDs.');
expectInvalid(fn () => input_enum(['status' => 'Hacked'], 'status', ['Pending']), 'Enum validation must reject unknown values.');
check(input_date(['date' => '2026-06-05'], 'date') === '2026-06-05', 'Date validation must accept ISO dates.');
expectInvalid(fn () => input_date(['date' => '06/05/2026'], 'date'), 'Date validation must reject ambiguous dates.');

$root = dirname(__DIR__);
$trackingSource = file_get_contents($root . DIRECTORY_SEPARATOR . 'tent_tracking' . DIRECTORY_SEPARATOR . 'tracking.php');
check(str_contains($trackingSource, 'id="installTentForm"'), 'Tracking page must include the refactored Install Tent form.');
check(str_contains($trackingSource, 'asset_csrf_input()'), 'Install Tent form must include an explicit CSRF token.');
check(str_contains($trackingSource, 'name="save_data" value="1"'), 'Install Tent form must include the create action marker.');
check(str_contains($trackingSource, 'id="installTentSubmit"'), 'Install Tent form must include a submit action.');
check(str_contains($trackingSource, 'id="printModal"'), 'Tracking page must keep Print actions in a separate modal.');

$mutationFiles = [
    'delete_data.php',
    'motorpool/delete_vehicle_record_motorpool.php',
    'motorpool/delete_repair_motorpool.php',
    'delete_rfq.php',
    'update_status.php',
    'tent_tracking/update_data.php',
    'update_tent_status.php',
    'update_bulk_tent_status.php',
    'motorpool/update_vehicle_record_motorpool.php',
    'motorpool/update_repair_motorpool.php',
    'motorpool/update_repair_status_motorpool.php',
    'update_rfq_status1.php',
    'scan_data.php',
    'undo_retrieved_status.php',
];

foreach ($mutationFiles as $file) {
    $source = file_get_contents($root . DIRECTORY_SEPARATOR . $file);
    check(str_contains($source, 'asset_require_auth'), "{$file} must require authentication.");
    check(str_contains($source, 'asset_require_post'), "{$file} must require POST and CSRF validation.");
}

$transactionFiles = ['update_status.php', 'tent_tracking/update_data.php', 'scan_data.php', 'motorpool/add_repair_motorpool.php'];
foreach ($transactionFiles as $file) {
    $source = file_get_contents($root . DIRECTORY_SEPARATOR . $file);
    check(str_contains($source, 'mysqli_begin_transaction'), "{$file} must start a transaction.");
    check(str_contains($source, 'mysqli_commit'), "{$file} must commit its transaction.");
    check(str_contains($source, 'mysqli_rollback'), "{$file} must roll back on failure.");
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: {$passes} security and validation checks\n";
