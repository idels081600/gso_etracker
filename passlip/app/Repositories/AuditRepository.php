<?php
declare(strict_types=1);

namespace PassSlip\Repositories;

use PassSlip\Core\Database;

final class AuditRepository
{
    public function ensureTable(): void
    {
        Database::execute(
            "CREATE TABLE IF NOT EXISTS passlip_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_username VARCHAR(191) NULL,
                actor_role VARCHAR(100) NULL,
                action VARCHAR(100) NOT NULL,
                request_id INT NULL,
                summary TEXT NULL,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action_created (action, created_at),
                INDEX idx_request_id (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function log(string $action, ?int $requestId, string $summary, array $metadata = []): void
    {
        $this->ensureTable();
        $user = current_user();

        Database::execute(
            'INSERT INTO passlip_audit_logs (actor_username, actor_role, action, request_id, summary, metadata) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $user['username'],
                $user['role'],
                $action,
                $requestId,
                $summary,
                json_encode($metadata, JSON_THROW_ON_ERROR),
            ]
        );
    }

    public function latest(int $limit = 80): array
    {
        $this->ensureTable();
        return Database::rows(
            'SELECT * FROM passlip_audit_logs ORDER BY created_at DESC, id DESC LIMIT ?',
            [$limit]
        );
    }
}
