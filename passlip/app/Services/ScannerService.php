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
                $this->requests->markDeparted((int) $departing['id']);
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
}
