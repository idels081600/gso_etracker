<?php
declare(strict_types=1);

namespace PassSlip\Controllers;

use PassSlip\Repositories\AuditRepository;
use PassSlip\Repositories\RequestRepository;
use PassSlip\Repositories\UserRepository;
use PassSlip\Services\RequestService;

final class AppController
{
    private RequestRepository $requests;
    private UserRepository $users;
    private AuditRepository $audit;
    private RequestService $service;

    public function __construct()
    {
        $this->requests = new RequestRepository();
        $this->users = new UserRepository();
        $this->audit = new AuditRepository();
        $this->service = new RequestService();
    }

    public function handle(): void
    {
        $this->handlePost();

        if (!is_authenticated()) {
            render_view('auth/login-required', ['title' => 'Sign in required', 'active' => 'dashboard']);
            return;
        }

        $page = $_GET['page'] ?? $this->defaultPage();

        match ($page) {
            'employee' => $this->employee(),
            'request-new' => $this->newRequest(),
            'scanner' => $this->scanner(),
            'tracking' => $this->tracking(),
            'reports' => $this->reports(),
            'users' => $this->users(),
            'audit' => $this->audit(),
            default => $this->dashboard(),
        };
    }

    private function handlePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        verify_csrf();
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create-request') {
                $this->service->createEmployeeRequest($_POST);
                flash('success', 'Request submitted for approval.');
                redirect_to(['page' => 'employee']);
            }

            if ($action === 'approve-batch') {
                $result = $this->service->approveBatch($_POST);
                $message = $result['updated'] . ' request(s) processed.';
                if ($result['errors']) {
                    $message .= ' Some requests failed and were left unchanged.';
                    flash('warning', $message);
                } else {
                    flash('success', $message);
                }
                redirect_to(['page' => 'dashboard']);
            }
        } catch (\Throwable $error) {
            flash('danger', $error->getMessage());
            redirect_to(['page' => $_GET['page'] ?? 'dashboard']);
        }
    }

    private function defaultPage(): string
    {
        return match (role_area()) {
            'employee' => 'employee',
            'desk' => 'scanner',
            default => 'dashboard',
        };
    }

    private function dashboard(): void
    {
        $this->allow(['approver', 'desk', 'super_admin']);
        render_view('dashboard', [
            'title' => 'Approver Dashboard',
            'active' => 'dashboard',
            'stats' => $this->requests->dashboardStats(),
            'pending' => $this->requests->pending([
                'search' => $_GET['search'] ?? '',
                'type' => $_GET['type'] ?? '',
            ]),
            'overdue' => $this->requests->overdue(),
        ]);
    }

    private function employee(): void
    {
        $this->allow(['employee', 'super_admin']);
        $user = current_user();
        render_view('employee/home', [
            'title' => 'My Pass Slip',
            'active' => 'employee',
            'current' => $this->requests->activeForEmployee((string) $user['username']),
            'history' => $this->requests->employeeHistory((string) $user['username']),
        ]);
    }

    private function newRequest(): void
    {
        $this->allow(['employee', 'super_admin']);
        $user = current_user();
        render_view('employee/request-form', [
            'title' => 'New Request',
            'active' => 'request-new',
            'position' => $this->users->findPosition((string) $user['username']),
        ]);
    }

    private function scanner(): void
    {
        $this->allow(['desk', 'approver', 'super_admin']);
        render_view('scanner', ['title' => 'Scanner', 'active' => 'scanner']);
    }

    private function tracking(): void
    {
        $this->allow(['desk', 'approver', 'super_admin']);
        render_view('tracking', [
            'title' => 'Employee Tracking',
            'active' => 'tracking',
            'rows' => $this->requests->tracking([
                'search' => $_GET['search'] ?? '',
                'status1' => $_GET['status1'] ?? '',
            ]),
            'overdue' => $this->requests->overdue(),
        ]);
    }

    private function reports(): void
    {
        $this->allow(['desk', 'approver', 'super_admin']);
        render_view('reports', ['title' => 'Reports', 'active' => 'reports']);
    }

    private function users(): void
    {
        $this->allow(['super_admin']);
        render_view('users', [
            'title' => 'Users',
            'active' => 'users',
            'users' => $this->users->all(['search' => $_GET['search'] ?? '']),
        ]);
    }

    private function audit(): void
    {
        $this->allow(['super_admin']);
        render_view('audit', [
            'title' => 'Audit Log',
            'active' => 'audit',
            'logs' => $this->audit->latest(),
        ]);
    }

    private function allow(array $areas): void
    {
        if (!in_array(role_area(), $areas, true)) {
            flash('danger', 'Your role cannot access that workspace.');
            redirect_to(['page' => $this->defaultPage()]);
        }
    }
}
