<?php
declare(strict_types=1);

namespace PassSlip\Repositories;

use PassSlip\Core\Database;

final class UserRepository
{
    public function findPosition(string $username): string
    {
        $row = Database::row('SELECT position FROM logindb WHERE username = ? LIMIT 1', [$username]);
        return (string) ($row['position'] ?? '');
    }

    public function all(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(username LIKE ? OR name LIKE ? OR position LIKE ?)';
            $needle = '%' . $filters['search'] . '%';
            array_push($params, $needle, $needle, $needle);
        }

        return Database::rows(
            'SELECT Id, username, name, position, role FROM logindb WHERE ' . implode(' AND ', $where) . ' ORDER BY name, username LIMIT 300',
            $params
        );
    }
}
