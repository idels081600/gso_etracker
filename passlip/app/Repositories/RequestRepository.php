<?php
declare(strict_types=1);

namespace PassSlip\Repositories;

use PassSlip\Core\Database;

final class RequestRepository
{
    public const ACTIVE_STATUSES = ['Pending', 'Partially Approved', 'Approved'];

    public function dashboardStats(): array
    {
        $today = date('Y-m-d');
        $counts = Database::rows(
            "SELECT Status, COUNT(*) total FROM `request` WHERE DATE(`date`) = ? GROUP BY Status",
            [$today]
        );

        $stats = ['Pending' => 0, 'Partially Approved' => 0, 'Approved' => 0, 'Done' => 0, 'Declined' => 0];
        foreach ($counts as $row) {
            $stats[$row['Status']] = (int) $row['total'];
        }

        $overdue = Database::row(
            "SELECT COUNT(*) total FROM `request`
             WHERE Status = 'Approved'
             AND esttime IS NOT NULL
             AND esttime <> '00:00:00'
             AND TIMESTAMPDIFF(MINUTE, esttime, NOW()) > 60"
        );

        return [
            'pending' => $stats['Pending'],
            'partial' => $stats['Partially Approved'],
            'outside' => $stats['Approved'],
            'done' => $stats['Done'],
            'declined' => $stats['Declined'],
            'overdue' => (int) ($overdue['total'] ?? 0),
        ];
    }

    public function pending(array $filters = []): array
    {
        $where = ["Status = 'Pending'"];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = 'Role = ?';
            $params[] = $filters['role'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'typeofbusiness = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR destination LIKE ? OR position LIKE ?)';
            $needle = '%' . $filters['search'] . '%';
            array_push($params, $needle, $needle, $needle);
        }

        $sql = "SELECT id, name, position, destination, purpose, typeofbusiness, Status, status1, Role, `date`, confirmed_by
                FROM `request`
                WHERE " . implode(' AND ', $where) . '
                ORDER BY id DESC
                LIMIT 250';

        return Database::rows($sql, $params);
    }

    public function tracking(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['status1'])) {
            $where[] = 'status1 = ?';
            $params[] = $filters['status1'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR destination LIKE ?)';
            $needle = '%' . $filters['search'] . '%';
            array_push($params, $needle, $needle);
        }

        return Database::rows(
            "SELECT id, name, destination, status1, Status, typeofbusiness, remarks, esttime, timedept, time_returned, Role, `date`
             FROM `request`
             WHERE " . implode(' AND ', $where) . '
             ORDER BY id DESC
             LIMIT 300',
            $params
        );
    }

    public function overdue(): array
    {
        return Database::rows(
            "SELECT id, name, destination, typeofbusiness, esttime, timedept, Role,
                    TIMESTAMPDIFF(MINUTE, esttime, NOW()) AS minutes_overdue
             FROM `request`
             WHERE Status = 'Approved'
             AND esttime IS NOT NULL
             AND esttime <> '00:00:00'
             AND TIMESTAMPDIFF(MINUTE, esttime, NOW()) > 60
             ORDER BY minutes_overdue DESC
             LIMIT 100"
        );
    }

    public function activeForEmployee(string $username): ?array
    {
        return Database::row(
            "SELECT id, name, position, destination, purpose, typeofbusiness, Status, status1, ImageName, confirmed_by, reason, remarks, `date`, timedept, esttime, time_returned
             FROM `request`
             WHERE name = ?
             ORDER BY id DESC
             LIMIT 1",
            [$username]
        );
    }

    public function employeeHistory(string $username): array
    {
        return Database::rows(
            "SELECT id, destination, typeofbusiness, Status, status1, `date`, timedept, esttime, time_returned, remarks
             FROM `request`
             WHERE name = ?
             ORDER BY id DESC
             LIMIT 30",
            [$username]
        );
    }

    public function create(array $request): int
    {
        Database::execute(
            "INSERT INTO `request`
             (name, position, `date`, destination, purpose, timedept, esttime, typeofbusiness, time_returned, Status, status1, dest2, ImageName, confirmed_by, remarks, reason, Role)
             VALUES (?, ?, ?, ?, ?, '00:00:00', '00:00:00', ?, '00:00:00', 'Pending', 'Waiting For Pass Slip Approval', ?, '../pending.png', '', '', '', ?)",
            [
                $request['name'],
                $request['position'],
                $request['date'],
                $request['destination'],
                $request['purpose'],
                $request['typeofbusiness'],
                $request['destination'],
                $request['role'],
            ]
        );

        return Database::connection()->insert_id;
    }

    public function hasActiveRequestToday(string $username): bool
    {
        $row = Database::row(
            "SELECT id FROM `request`
             WHERE name = ?
             AND DATE(`date`) = CURDATE()
             AND (Status = 'Pending' OR status1 IN ('Pass-Slip', 'Waiting For Pass Slip Approval', 'Scan Qrcode'))
             LIMIT 1",
            [$username]
        );

        return $row !== null;
    }

    public function approveMany(array $ids, string $status, string $confirmedBy, int $minutes, ?string $reason = null): array
    {
        $status1 = $status === 'Declined' ? 'Declined' : 'Scan Qrcode';
        $time = (new \DateTimeImmutable())->modify('+' . $minutes . ' minutes')->format('H:i:s');
        $timeText = intdiv($minutes, 60) . ' hours ' . ($minutes % 60) . ' minutes';
        $updated = 0;
        $errors = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            try {
                if ($status === 'Declined') {
                    Database::execute(
                        "UPDATE `request` SET Status = 'Declined', status1 = 'Declined', confirmed_by = ?, reason = ?, ImageName = 'declined.png' WHERE id = ?",
                        [$confirmedBy, $reason ?? '', $id]
                    );
                } else {
                    Database::execute(
                        "UPDATE `request` SET esttime = ?, time_allotted = ?, Status = ?, status1 = ?, confirmed_by = ? WHERE id = ?",
                        [$time, $timeText, $status, $status1, $confirmedBy, $id]
                    );
                }
                $updated++;
            } catch (\Throwable $error) {
                $errors[] = ['id' => $id, 'message' => $error->getMessage()];
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    public function findScannable(string $name, string $status): ?array
    {
        return Database::row(
            "SELECT * FROM `request`
             WHERE name = ?
             AND Status = ?
             AND DATE(`date`) = CURDATE()
             ORDER BY id DESC
             LIMIT 1",
            [$name, $status]
        );
    }

    public function markDeparted(int $id): void
    {
        Database::execute(
            "UPDATE `request`
             SET timedept = NOW(), Status = 'Approved', status1 = 'Pass-Slip', ImageName = 'Check-Approved.png'
             WHERE id = ?",
            [$id]
        );
    }

    public function markReturned(int $id, string $remarks, int $durationSeconds): void
    {
        Database::execute(
            "UPDATE `request`
             SET time_returned = ?, Status = 'Done', status1 = 'Present', remarks = ?, duration_seconds = ?
             WHERE id = ?",
            [date('H:i'), $remarks, $durationSeconds, $id]
        );
    }
}
