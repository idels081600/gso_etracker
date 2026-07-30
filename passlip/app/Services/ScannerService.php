<?php
declare(strict_types=1);

namespace PassSlip\Services;

use PassSlip\Core\Database;
use PassSlip\Repositories\AuditRepository;
use PassSlip\Repositories\RequestRepository;

final class ScannerService
{
    public function __construct(
        private readonly RequestRepository $requests = new RequestRepository(),
        private readonly AuditRepository $audit = new AuditRepository(),
    ) {
    }

    public function scan(string $rawName): array
    {
        $name = trim($rawName);
        if ($name === '') {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Scan a valid employee QR code.'];
        }

        return Database::transaction(function () use ($name): array {
            $departing = $this->requests->findScannable($name, 'Partially Approved');
            if ($departing) {
                $allottedMinutes = $this->allottedMinutes((string) ($departing['time_allotted'] ?? ''));
                $expectedReturn = (new \DateTimeImmutable())
                    ->modify('+' . $allottedMinutes . ' minutes')
                    ->format('H:i:s');
                $this->requests->markDeparted((int) $departing['id'], $expectedReturn);
                $this->audit->log('scan.departed', (int) $departing['id'], $name . ' scanned out.');
                return [
                    'success' => true,
                    'status' => 'departed',
                    'name' => $name,
                    'message' => 'Pass slip activated. Take care.',
                ];
            }

            $returning = $this->requests->findScannable($name, 'Approved');
            if ($returning) {
                $estimated = new \DateTimeImmutable((string) $returning['esttime']);
                $actual = new \DateTimeImmutable();
                $departedAt = new \DateTimeImmutable((string) $returning['timedept']);
                $difference = abs($actual->getTimestamp() - $estimated->getTimestamp());
                $minutes = (int) floor($difference / 60);
                $label = $minutes >= 60
                    ? intdiv($minutes, 60) . ' hour(s) ' . ($minutes % 60) . ' minute(s)'
                    : $minutes . ' minute(s)';
                $remarks = $actual <= $estimated ? 'Arrived ' . $label . ' early' : 'Arrived ' . $label . ' late';
                $duration = max(0, $actual->getTimestamp() - $departedAt->getTimestamp());

                $this->requests->markReturned((int) $returning['id'], $remarks, $duration);
                $this->audit->log('scan.returned', (int) $returning['id'], $name . ' returned. ' . $remarks);

                return [
                    'success' => true,
                    'status' => 'returned',
                    'name' => $name,
                    'message' => 'Welcome back. ' . $remarks . '.',
                ];
            }

            $this->audit->log('scan.failed', null, 'Failed scan for ' . $name);
            return [
                'success' => false,
                'status' => 'not_found',
                'name' => $name,
                'message' => 'No approved pass slip found for today.',
            ];
        });
    }
    private function allottedMinutes(string $value): int
    {
        $text = strtolower(trim($value));
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $text, $matches)) {
            $minutes = ((int) $matches[1] * 60) + (int) $matches[2];
        } elseif (preg_match('/(?:(\d+)\s*(?:hours?|hrs?|h))?\s*(?:(\d+)\s*(?:minutes?|mins?|m))?/i', $text, $matches)) {
            $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
            $remainder = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
            $minutes = ($hours * 60) + $remainder;
        } else {
            $minutes = 0;
        }

        if ($minutes < 1 || $minutes > 1440) {
            throw new \RuntimeException('The approved pass slip has an invalid allotted time.');
        }

        return $minutes;
    }
}
