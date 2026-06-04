<?php
declare(strict_types=1);

namespace PassSlip\Controllers;

use PassSlip\Repositories\RequestRepository;
use PassSlip\Services\ScannerService;

final class ApiController
{
    public function handle(): void
    {
        if (!is_authenticated()) {
            json_response(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        try {
            match ($action) {
                'scan' => $this->scan(),
                'pending' => $this->pending(),
                'tracking' => $this->tracking(),
                'overdue' => $this->overdue(),
                default => json_response(['success' => false, 'message' => 'Unknown API action.'], 404),
            };
        } catch (\Throwable $error) {
            json_response(['success' => false, 'message' => $error->getMessage()], 500);
        }
    }

    private function scan(): void
    {
        $this->allow(['desk', 'approver', 'super_admin']);
        verify_csrf();
        $result = (new ScannerService())->scan((string) ($_POST['scannedData'] ?? ''));
        json_response($result, $result['success'] ? 200 : 422);
    }

    private function pending(): void
    {
        $this->allow(['approver', 'desk', 'super_admin']);
        $repo = new RequestRepository();
        json_response(['success' => true, 'data' => $repo->pending($_GET)]);
    }

    private function tracking(): void
    {
        $this->allow(['approver', 'desk', 'super_admin']);
        $repo = new RequestRepository();
        json_response(['success' => true, 'data' => $repo->tracking($_GET)]);
    }

    private function overdue(): void
    {
        $this->allow(['approver', 'desk', 'super_admin']);
        $repo = new RequestRepository();
        $rows = $repo->overdue();
        json_response(['success' => true, 'count' => count($rows), 'data' => $rows]);
    }

    private function allow(array $areas): void
    {
        if (!in_array(role_area(), $areas, true)) {
            json_response(['success' => false, 'message' => 'Forbidden for this role.'], 403);
        }
    }
}
