<?php
declare(strict_types=1);

namespace PassSlip\Core;

use mysqli;
use mysqli_result;
use RuntimeException;

final class Database
{
    private static ?mysqli $connection = null;

    public static function connection(): mysqli
    {
        if (self::$connection instanceof mysqli) {
            return self::$connection;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        self::$connection = new mysqli(
            (string) config_value('DB_HOST', 'localhost'),
            (string) config_value('DB_USER', 'root'),
            (string) config_value('DB_PASS', ''),
            (string) config_value('DB_NAME', '')
        );
        self::$connection->set_charset('utf8mb4');

        return self::$connection;
    }

    public static function rows(string $sql, array $params = []): array
    {
        $result = self::query($sql, $params);
        if (!$result instanceof mysqli_result) {
            return [];
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public static function row(string $sql, array $params = []): ?array
    {
        return self::rows($sql, $params)[0] ?? null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return self::connection()->affected_rows;
    }

    public static function transaction(callable $callback): mixed
    {
        $db = self::connection();
        $db->begin_transaction();

        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Throwable $error) {
            $db->rollback();
            throw $error;
        }
    }

    private static function query(string $sql, array $params = []): mysqli_result|bool
    {
        $db = self::connection();
        if ($params === []) {
            return $db->query($sql);
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare database statement.');
        }

        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    }
}
