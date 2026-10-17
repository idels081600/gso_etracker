<?php

declare(strict_types=1);

function pickleballEnvironment(): array
{
    static $environment = null;
    if (is_array($environment)) return $environment;

    $path = __DIR__ . DIRECTORY_SEPARATOR . ".env";
    if (!is_file($path)) return $environment = [];

    $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
    return $environment = is_array($parsed) ? $parsed : [];
}

function pickleballDatabase(bool $selectDatabase = true): ?mysqli
{
    $environment = pickleballEnvironment();
    $host = trim((string) ($environment["DB_HOST"] ?? ""));
    $user = trim((string) ($environment["DB_USERNAME"] ?? ""));
    $password = (string) ($environment["DB_PASSWORD"] ?? "");
    $database = trim((string) ($environment["DB_DATABASE"] ?? PICKLEBALL_DATABASE_NAME));
    $port = (int) ($environment["DB_PORT"] ?? 3306);
    $persistent = filter_var($environment["DB_PERSISTENT"] ?? true, FILTER_VALIDATE_BOOL);
    if ($host === "" || $user === "") return null;

    mysqli_report(MYSQLI_REPORT_OFF);
    $connectionHost = $persistent && !str_starts_with($host, "p:") ? "p:" . $host : $host;
    $connection = new mysqli($connectionHost, $user, $password, $selectDatabase ? $database : "", $port);
    if ($connection->connect_errno) return null;
    $connection->set_charset("utf8mb4");
    return $connection;
}

function pickleballDatabaseName(): string
{
    return trim((string) (pickleballEnvironment()["DB_DATABASE"] ?? PICKLEBALL_DATABASE_NAME));
}
