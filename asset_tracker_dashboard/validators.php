<?php

function input_string(array $source, string $key, int $maxLength = 255, bool $required = true): string
{
    $value = trim((string) ($source[$key] ?? ''));
    if ($required && $value === '') {
        throw new InvalidArgumentException("{$key} is required.");
    }
    if (mb_strlen($value) > $maxLength) {
        throw new InvalidArgumentException("{$key} is too long.");
    }
    return $value;
}

function input_int(array $source, string $key, int $min = 1): int
{
    $value = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < $min) {
        throw new InvalidArgumentException("{$key} must be a valid number.");
    }
    return $value;
}

function input_enum(array $source, string $key, array $allowed): string
{
    $value = input_string($source, $key, 50);
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException("Invalid {$key}.");
    }
    return $value;
}

function input_date(array $source, string $key, bool $required = true): ?string
{
    $value = trim((string) ($source[$key] ?? ''));
    if ($value === '' && !$required) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException("{$key} must be a valid date.");
    }
    return $value;
}

function input_id_list(mixed $value): array
{
    $items = is_array($value) ? $value : explode(',', (string) $value);
    $ids = array_values(array_unique(array_filter(array_map('intval', $items), fn ($id) => $id > 0)));
    if ($ids === []) {
        throw new InvalidArgumentException('At least one valid ID is required.');
    }
    return $ids;
}

