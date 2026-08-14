<?php

function riceDefaultPrefix(string $barangay): string
{
    $prefix = strtoupper(trim($barangay));
    $prefix = preg_replace('/\s+/', ' ', $prefix);
    return $prefix;
}

function riceParseHouseholdCode(string $household_code, ?string $default_prefix = null): array
{
    $household_code = trim($household_code);
    $default_prefix = $default_prefix !== null ? riceDefaultPrefix($default_prefix) : null;

    if ($household_code === '') {
        return [
            'prefix' => $default_prefix ?? '',
            'number' => 0,
        ];
    }

    if (preg_match('/^(.*?)([0-9]+)\s*$/', $household_code, $matches)) {
        $prefix = strtoupper(trim($matches[1]));
        $prefix = preg_replace('/\s+/', ' ', $prefix);
        $prefix = preg_replace('/\s*-\s*$/', '', $prefix);

        return [
            'prefix' => $prefix !== '' ? $prefix : ($default_prefix ?? ''),
            'number' => (int)$matches[2],
        ];
    }

    return [
        'prefix' => strtoupper($household_code),
        'number' => 0,
    ];
}

function riceCompareHouseholdCodes(string $left_code, string $right_code, ?string $left_default_prefix = null, ?string $right_default_prefix = null): int
{
    $left = riceParseHouseholdCode($left_code, $left_default_prefix);
    $right = riceParseHouseholdCode($right_code, $right_default_prefix);

    $prefix_compare = strnatcasecmp($left['prefix'], $right['prefix']);
    if ($prefix_compare !== 0) {
        return $prefix_compare;
    }

    if ($left['number'] !== $right['number']) {
        return $left['number'] <=> $right['number'];
    }

    return strnatcasecmp(trim($left_code), trim($right_code));
}