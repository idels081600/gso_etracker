<?php
declare(strict_types=1);

namespace PassSlip\Services;

use InvalidArgumentException;
use PassSlip\Core\Database;
use PassSlip\Repositories\AuditRepository;
use PassSlip\Repositories\RequestRepository;

final class RequestService
{
    public function __construct(
        private readonly RequestRepository $requests = new RequestRepository(),
        private readonly AuditRepository $audit = new AuditRepository(),
    ) {
    }

    public function createEmployeeRequest(array $input): int
    {
        $user = current_user();
        if (!$user['username']) {
            throw new InvalidArgumentException('You must be signed in to create a request.');
        }

        if ($this->requests->hasActiveRequestToday($user['username'])) {
            throw new InvalidArgumentException('You already have an active request today, or you still need to scan your return QR.');
        }

        $type = trim((string) ($input['typeofbusiness'] ?? ''));
        if (!in_array($type, ['Official Business', 'Personal'], true)) {
            throw new InvalidArgumentException('Choose a valid request type.');
        }

        $payload = [
            'name' => $user['username'],
            'position' => trim((string) ($input['position'] ?? '')),
            'date' => date('Y-m-d'),
            'destination' => trim((string) ($input['destination'] ?? '')),
            'purpose' => trim((string) ($input['purpose'] ?? '')),
            'typeofbusiness' => $type,
            'role' => $user['role'] ?? 'Employee',
        ];

        foreach (['position', 'destination', 'purpose'] as $field) {
            if ($payload[$field] === '') {
                throw new InvalidArgumentException(ucfirst($field) . ' is required.');
            }
        }

        $id = Database::transaction(fn () => $this->requests->create($payload));
        $this->audit->log('request.created', $id, $payload['name'] . ' created a ' . $type . ' request.', $payload);

        return $id;
    }

    public function approveBatch(array $input): array
    {
        $ids = json_decode((string) ($input['selected_ids'] ?? '[]'), true);
        if (!is_array($ids) || $ids === []) {
            throw new InvalidArgumentException('Select at least one request.');
        }

        $status = (string) ($input['status'] ?? '');
        if (!in_array($status, ['Partially Approved', 'Declined'], true)) {
            throw new InvalidArgumentException('Choose a valid approval status.');
        }

        $confirmedBy = trim((string) ($input['confirmed_by'] ?? ''));
        if ($confirmedBy === '') {
            throw new InvalidArgumentException('Confirmed by is required.');
        }

        $minutes = max(0, ((int) ($input['fix_hours'] ?? 0) * 60) + (int) ($input['fix_minutes'] ?? 0));
        if ($status !== 'Declined' && $minutes < 1) {
            throw new InvalidArgumentException('Set an allotted time before approving.');
        }

        $reason = trim((string) ($input['decline_reason'] ?? ''));
        if ($status === 'Declined' && $reason === '') {
            throw new InvalidArgumentException('Decline reason is required.');
        }

        $result = Database::transaction(fn () => $this->requests->approveMany($ids, $status, $confirmedBy, $minutes, $reason));

        foreach ($ids as $id) {
            $this->audit->log(
                $status === 'Declined' ? 'request.declined' : 'request.partially_approved',
                (int) $id,
                'Request ' . (int) $id . ' was updated to ' . $status . '.',
                ['confirmed_by' => $confirmedBy, 'minutes' => $minutes, 'reason' => $reason]
            );
        }

        return $result;
    }
}
