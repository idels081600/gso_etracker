<?php

function riceReleaseBatch($source)
{
    $batches = [
        'first_wave' => [
            'households' => 'rice_households',
            'claims' => 'rice_voucher_claims',
            'label' => 'First Batch',
            'previous_households' => null,
            'default_active' => true,
        ],
        'next_wave' => [
            'households' => 'rice_claimed_households',
            'claims' => 'rice_next_wave_claims',
            'label' => 'Second Batch',
            'previous_households' => 'rice_households',
            'default_active' => true,
        ],
        'third_wave' => [
            'households' => 'rice_third_wave_households',
            'claims' => 'rice_third_wave_claims',
            'label' => 'Third Batch',
            'previous_households' => 'rice_claimed_households',
            'default_active' => true,
        ],
        'fourth_wave' => [
            'households' => 'rice_fourth_wave_households',
            'claims' => 'rice_fourth_wave_claims',
            'label' => 'Fourth Batch',
            'previous_households' => 'rice_third_wave_households',
            'default_active' => false,
        ],
    ];

    return is_string($source) ? ($batches[$source] ?? null) : null;
}

function riceReleaseBatchIsActive(mysqli $conn, $source)
{
    $batch = riceReleaseBatch($source);
    if (!$batch) {
        return false;
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'rice_release_batch_status'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return (bool)($batch['default_active'] ?? false);
    }

    $stmt = $conn->prepare('SELECT is_active FROM rice_release_batch_status WHERE wave = ? LIMIT 1');
    $stmt->bind_param('s', $source);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ? (int)$row['is_active'] === 1 : (bool)($batch['default_active'] ?? false);
}
