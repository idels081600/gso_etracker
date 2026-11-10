<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';
require_once 'payables_ib_payments.php';

function bac_monitoring_payment_return_url(string $queryString, int $recordId = 0): string
{
    parse_str(ltrim($queryString, '?'), $query);
    $allowed = [];
    if (!empty($query['search']) && is_scalar($query['search'])) {
        $allowed['search'] = trim((string)$query['search']);
    }
    if (!empty($query['sort']) && is_scalar($query['sort'])) {
        $allowed['sort'] = trim((string)$query['sort']);
    }
    if (!empty($query['page']) && filter_var($query['page'], FILTER_VALIDATE_INT)) {
        $allowed['page'] = max(1, (int)$query['page']);
    }
    if ($recordId > 0) {
        $allowed['payment_record'] = $recordId;
    }

    return 'bac_monitoring.php' . ($allowed ? '?' . http_build_query($allowed) : '');
}

function bac_monitoring_set_payment_flash(string $type, string $message, array $oldInput = []): void
{
    $_SESSION['_bac_payment_flash'] = [
        'type' => $type,
        'message' => $message,
        'old_input' => $oldInput,
    ];
}

function bac_monitoring_ensure_date_column(mysqli $conn, string $column): void
{
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bac_monitoring' AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        payables_log_error('Unable to inspect BAC monitoring column ' . $column . ': ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result();
    $hasColumn = $exists && $exists->num_rows > 0;
    $stmt->close();

    if (!$hasColumn && !$conn->query("ALTER TABLE bac_monitoring ADD COLUMN {$column} DATE NULL")) {
        payables_log_error('Unable to add BAC monitoring column ' . $column . ': ' . $conn->error);
    }
}

function bac_monitoring_ensure_text_column(mysqli $conn, string $column): void
{
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bac_monitoring' AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        payables_log_error('Unable to inspect BAC monitoring column ' . $column . ': ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result();
    $hasColumn = $exists && $exists->num_rows > 0;
    $stmt->close();

    if (!$hasColumn && !$conn->query("ALTER TABLE bac_monitoring ADD COLUMN {$column} TEXT NULL")) {
        payables_log_error('Unable to add BAC monitoring column ' . $column . ': ' . $conn->error);
    }
}

function bac_monitoring_pr_numbers(?string $value): array
{
    $parts = preg_split('/\s*,\s*/', trim((string)$value));
    if (!$parts) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $parts), static fn ($part) => $part !== ''));
}

foreach (['pre_procurement', 'pre_bid', 'evaluation'] as $bacDateColumn) {
    bac_monitoring_ensure_date_column($conn, $bacDateColumn);
}
bac_monitoring_ensure_text_column($conn, 'pr_included');

$paymentStorageError = '';
try {
    payables_ensure_ib_payments_table($conn);
} catch (Throwable $exception) {
    $paymentStorageError = 'Partial payments are temporarily unavailable.';
    payables_log_error('IB payment storage initialization failed: ' . $exception->getMessage());
}

$formErrors = [];
$formValues = [
    'id' => '',
    'ib_no' => '',
    'pr_included' => '',
    'project_name' => '',
    'abc' => '',
    'final_amount' => '',
    'bidder' => '',
    'pre_procurement' => '',
    'pre_bid' => '',
    'date_of_bidding' => '',
    'evaluation' => '',
    'post_qual' => '',
    'status' => '',
    'date_transmitted_from_bac' => '',
    'office' => '',
    'noa_no' => '',
    'notice_to_proceed_date' => '',
    'contract_date' => '',
    'calendar_days_delivery' => '',
    'deadline' => '',
    'received_by' => '',
];
$showAddModal = false;
$showEditModal = false;

$validateBacForm = function (array $values): array {
    $errors = [];

    if ($values['ib_no'] === '') {
        $errors[] = 'IB No. is required.';
    }
    if ($values['project_name'] === '') {
        $errors[] = 'Name of project is required.';
    }
    if ($values['abc'] === '') {
        $errors[] = 'ABC is required.';
    }
    if ($values['bidder'] === '') {
        $errors[] = 'Bidder is required.';
    }
    foreach ([
        'pre_procurement' => 'Pre Procurement',
        'pre_bid' => 'Pre Bid',
        'date_of_bidding' => 'Date of bidding',
        'evaluation' => 'Evaluation',
        'date_transmitted_from_bac' => 'Date transmitted from BAC',
        'noa_no' => 'NOA date',
        'notice_to_proceed_date' => 'Notice to proceed date',
        'contract_date' => 'Contract date',
    ] as $key => $label) {
        if ($values[$key] !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $values[$key]);
            if (!$date || $date->format('Y-m-d') !== $values[$key]) {
                $errors[] = $label . ' must be a valid date.';
            }
        }
    }
    if (!in_array($values['status'], ['Transmitted to GSO', 'Not Yet Received'], true)) {
        $errors[] = 'Status must be Not Yet Received or Transmitted to GSO.';
    }

    return $errors;
};

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_ib_payment', 'edit_ib_payment', 'delete_ib_payment'], true)) {
    $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $amount = trim((string)($_POST['payment_amount'] ?? ''));
    $paymentDate = trim((string)($_POST['payment_date'] ?? ''));
    $dvNumber = trim((string)($_POST['dv_number'] ?? ''));
    $returnQuery = trim((string)($_POST['return_query'] ?? ''));
    $oldInput = [
        'action' => $action,
        'payment_id' => $paymentId,
        'amount' => $amount,
        'payment_date' => $paymentDate,
        'dv_number' => $dvNumber,
    ];

    try {
        $token = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['_payables_csrf_token'] ?? '');
        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            throw new InvalidArgumentException('Security token expired. Please refresh and try again.');
        }
        if ($paymentStorageError !== '') {
            throw new RuntimeException($paymentStorageError);
        }
        if ($recordId < 1) {
            throw new InvalidArgumentException('Invalid IB monitoring record.');
        }

        $changedBy = trim((string)($_SESSION['pay_name'] ?? ''));
        if ($action === 'add_ib_payment') {
            payables_add_ib_payment($conn, $recordId, $amount, $paymentDate, $changedBy, $dvNumber);
            bac_monitoring_set_payment_flash('success', 'Partial payment recorded successfully.');
        } elseif ($action === 'edit_ib_payment') {
            if ($paymentId < 1) {
                throw new InvalidArgumentException('Invalid partial payment.');
            }
            payables_edit_ib_payment($conn, $recordId, $paymentId, $amount, $paymentDate, $changedBy, $dvNumber);
            bac_monitoring_set_payment_flash('success', 'Partial payment updated successfully.');
        } else {
            if ($paymentId < 1) {
                throw new InvalidArgumentException('Invalid partial payment.');
            }
            payables_delete_ib_payment($conn, $recordId, $paymentId);
            bac_monitoring_set_payment_flash('success', 'Partial payment deleted successfully.');
        }
    } catch (InvalidArgumentException $exception) {
        bac_monitoring_set_payment_flash('error', $exception->getMessage(), $oldInput);
    } catch (Throwable $exception) {
        payables_log_error('IB payment action failed: ' . $exception->getMessage());
        bac_monitoring_set_payment_flash('error', 'Unable to update partial payments right now.', $oldInput);
    }

    header('Location: ' . bac_monitoring_payment_return_url($returnQuery, $recordId));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_bac_monitoring', 'edit_bac_monitoring', 'delete_bac_monitoring'], true)) {
    $formValues = [
        'id' => trim($_POST['id'] ?? ''),
        'ib_no' => trim($_POST['ib_no'] ?? ''),
        'pr_included' => trim($_POST['pr_included'] ?? ''),
        'project_name' => trim($_POST['project_name'] ?? ''),
        'abc' => trim($_POST['abc'] ?? ''),
        'final_amount' => trim($_POST['final_amount'] ?? ''),
        'bidder' => trim($_POST['bidder'] ?? ''),
        'pre_procurement' => trim($_POST['pre_procurement'] ?? ''),
        'pre_bid' => trim($_POST['pre_bid'] ?? ''),
        'date_of_bidding' => trim($_POST['date_of_bidding'] ?? ''),
        'evaluation' => trim($_POST['evaluation'] ?? ''),
        'post_qual' => trim($_POST['post_qual'] ?? ''),
        'status' => trim($_POST['status'] ?? ''),
        'date_transmitted_from_bac' => trim($_POST['date_transmitted_from_bac'] ?? ''),
        'office' => trim($_POST['office'] ?? ''),
        'noa_no' => trim($_POST['noa_no'] ?? ''),
        'notice_to_proceed_date' => trim($_POST['notice_to_proceed_date'] ?? ''),
        'contract_date' => trim($_POST['contract_date'] ?? ''),
        'calendar_days_delivery' => trim($_POST['calendar_days_delivery'] ?? ''),
        'deadline' => trim($_POST['deadline'] ?? ''),
        'received_by' => trim($_POST['received_by'] ?? ''),
    ];
    $formValues['status'] = $formValues['received_by'] === '' ? 'Not Yet Received' : 'Transmitted to GSO';
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['_payables_csrf_token'] ?? '';

    if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
        $formErrors[] = 'Security token expired. Please refresh and try again.';
    }

    if ($action === 'delete_bac_monitoring') {
        $recordId = filter_var($formValues['id'], FILTER_VALIDATE_INT);
        if (!$recordId || $recordId < 1) {
            $formErrors[] = 'Invalid BAC monitoring record.';
        }

        if (!$formErrors) {
            $stmt = $conn->prepare("DELETE FROM bac_monitoring WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $recordId);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: bac_monitoring.php?deleted=1');
                    exit;
                }

                $formErrors[] = 'Unable to delete BAC monitoring record right now.';
                $stmt->close();
            } else {
                $formErrors[] = 'Unable to prepare BAC monitoring delete.';
            }
        }
    } else {
        $formErrors = array_merge($formErrors, $validateBacForm($formValues));
        $recordId = filter_var($formValues['id'], FILTER_VALIDATE_INT);
        if ($action === 'edit_bac_monitoring' && (!$recordId || $recordId < 1)) {
            $formErrors[] = 'Invalid BAC monitoring record.';
        }

        $abc = (float)str_replace(',', '', payables_sanitize_amount($formValues['abc']));
        $finalAmount = $formValues['final_amount'] !== ''
            ? (float)str_replace(',', '', payables_sanitize_amount($formValues['final_amount']))
            : 0;
        if ($formValues['status'] === 'Transmitted to GSO') {
            $finalAmount = $abc;
            $formValues['final_amount'] = number_format($abc, 2, '.', '');
        }
        if (!$formErrors && $action === 'edit_bac_monitoring') {
            try {
                $totalPaidCents = payables_ib_total_paid_cents($conn, (int)$recordId);
                if (payables_amount_to_cents($finalAmount) < $totalPaidCents) {
                    $formErrors[] = 'Final Amount cannot be lower than the recorded Total Paid of PHP ' . number_format($totalPaidCents / 100, 2) . '.';
                }
            } catch (Throwable $exception) {
                payables_log_error('Unable to validate Final Amount against payments: ' . $exception->getMessage());
                $formErrors[] = 'Unable to validate the Final Amount right now.';
            }
        }
        $preProcurement = $formValues['pre_procurement'] !== '' ? $formValues['pre_procurement'] : null;
        $preBid = $formValues['pre_bid'] !== '' ? $formValues['pre_bid'] : null;
        $dateOfBidding = $formValues['date_of_bidding'] !== '' ? $formValues['date_of_bidding'] : null;
        $evaluation = $formValues['evaluation'] !== '' ? $formValues['evaluation'] : null;
        $dateTransmittedFromBac = $formValues['date_transmitted_from_bac'] !== '' ? $formValues['date_transmitted_from_bac'] : null;
        $noaDate = $formValues['noa_no'] !== '' ? $formValues['noa_no'] : null;
        $noticeToProceedDate = $formValues['notice_to_proceed_date'] !== '' ? $formValues['notice_to_proceed_date'] : null;
        $contractDate = $formValues['contract_date'] !== '' ? $formValues['contract_date'] : null;

        if (!$formErrors && $action === 'add_bac_monitoring') {
            $stmt = $conn->prepare("
                INSERT INTO bac_monitoring (
                    ib_no, pr_included, project_name, abc, final_amount, bidder,
                    pre_procurement, pre_bid, date_of_bidding, evaluation, post_qual, status,
                    date_transmitted_from_bac, office, noa_no, notice_to_proceed_date, contract_date,
                    calendar_days_delivery, deadline, received_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'sssddsssssssssssssss',
                    $formValues['ib_no'],
                    $formValues['pr_included'],
                    $formValues['project_name'],
                    $abc,
                    $finalAmount,
                    $formValues['bidder'],
                    $preProcurement,
                    $preBid,
                    $dateOfBidding,
                    $evaluation,
                    $formValues['post_qual'],
                    $formValues['status'],
                    $dateTransmittedFromBac,
                    $formValues['office'],
                    $noaDate,
                    $noticeToProceedDate,
                    $contractDate,
                    $formValues['calendar_days_delivery'],
                    $formValues['deadline'],
                    $formValues['received_by']
                );
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: bac_monitoring.php?added=1');
                    exit;
                }

                $formErrors[] = 'Unable to add BAC monitoring record right now.';
                $stmt->close();
            } else {
                $formErrors[] = 'Unable to prepare BAC monitoring record.';
            }
        }

        if (!$formErrors && $action === 'edit_bac_monitoring') {
            $stmt = $conn->prepare("
                UPDATE bac_monitoring
                SET
                    ib_no = ?,
                    pr_included = ?,
                    project_name = ?,
                    abc = ?,
                    final_amount = ?,
                    bidder = ?,
                    pre_procurement = ?,
                    pre_bid = ?,
                    date_of_bidding = ?,
                    evaluation = ?,
                    post_qual = ?,
                    status = ?,
                    date_transmitted_from_bac = ?,
                    office = ?,
                    noa_no = ?,
                    notice_to_proceed_date = ?,
                    contract_date = ?,
                    calendar_days_delivery = ?,
                    deadline = ?,
                    received_by = ?
                WHERE id = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'sssddsssssssssssssssi',
                    $formValues['ib_no'],
                    $formValues['pr_included'],
                    $formValues['project_name'],
                    $abc,
                    $finalAmount,
                    $formValues['bidder'],
                    $preProcurement,
                    $preBid,
                    $dateOfBidding,
                    $evaluation,
                    $formValues['post_qual'],
                    $formValues['status'],
                    $dateTransmittedFromBac,
                    $formValues['office'],
                    $noaDate,
                    $noticeToProceedDate,
                    $contractDate,
                    $formValues['calendar_days_delivery'],
                    $formValues['deadline'],
                    $formValues['received_by'],
                    $recordId
                );
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: bac_monitoring.php?updated=1');
                    exit;
                }

                $formErrors[] = 'Unable to update BAC monitoring record right now.';
                $stmt->close();
            } else {
                $formErrors[] = 'Unable to prepare BAC monitoring update.';
            }
        }
    }

    $showAddModal = $action === 'add_bac_monitoring';
    $showEditModal = $action === 'edit_bac_monitoring';
}

$searchTerm = trim($_GET['search'] ?? '');
$sortOption = $_GET['sort'] ?? 'date_transmitted_desc';
$sortOptions = [
    'date_transmitted_desc' => [
        'label' => 'Date Transmitted',
        'order' => "date_transmitted_from_bac IS NULL ASC, date_transmitted_from_bac DESC, id DESC",
    ],
    'ib_desc' => [
        'label' => 'IB',
        'order' => "ib_no DESC, id DESC",
    ],
];
if (!isset($sortOptions[$sortOption])) {
    $sortOption = 'date_transmitted_desc';
}
$orderBySql = $sortOptions[$sortOption]['order'];
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

$perPage = 25;
$offset = ($currentPage - 1) * $perPage;
$where = ["1 = 1"];
$types = '';
$params = [];

if ($searchTerm !== '') {
    $searchLike = '%' . $searchTerm . '%';
    $where[] = "(ib_no LIKE ? OR pr_included LIKE ? OR project_name LIKE ? OR bidder LIKE ? OR CAST(abc AS CHAR) LIKE ? OR CAST(final_amount AS CHAR) LIKE ? OR CAST(pre_procurement AS CHAR) LIKE ? OR CAST(pre_bid AS CHAR) LIKE ? OR CAST(date_of_bidding AS CHAR) LIKE ? OR CAST(evaluation AS CHAR) LIKE ? OR post_qual LIKE ? OR status LIKE ? OR office LIKE ? OR noa_no LIKE ? OR contract_date LIKE ? OR received_by LIKE ?)";
    $types .= 'ssssssssssssssss';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
}

$whereSql = implode(' AND ', $where);

$totalRows = 0;
$countSql = "
    SELECT COUNT(*) AS total
    FROM bac_monitoring
    WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult && $countRow = $countResult->fetch_assoc()) {
        $totalRows = (int)$countRow['total'];
    }
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$buildPageUrl = function (int $page = 1) use ($searchTerm, $sortOption): string {
    $query = ['page' => $page];
    if ($searchTerm !== '') {
        $query['search'] = $searchTerm;
    }
    if ($sortOption !== '') {
        $query['sort'] = $sortOption;
    }

    return 'bac_monitoring.php?' . http_build_query($query);
};

$bacRows = [];
$paymentTotalSelect = '0 AS total_paid';
$paymentTotalJoin = '';
if ($paymentStorageError === '') {
    $paymentTotalSelect = 'COALESCE(payment_totals.total_paid, 0) AS total_paid';
    $paymentTotalJoin = "LEFT JOIN (
        SELECT bac_monitoring_id, SUM(amount) AS total_paid
        FROM payables_ib_payments
        GROUP BY bac_monitoring_id
    ) payment_totals ON payment_totals.bac_monitoring_id = bac_monitoring.id";
}
$bacSql = "
    SELECT
        id,
        ib_no,
        pr_included,
        project_name,
        abc,
        final_amount,
        bidder,
        pre_procurement,
        pre_bid,
        date_of_bidding,
        evaluation,
        post_qual,
        status,
        date_transmitted_from_bac,
        office,
        noa_no,
        notice_to_proceed_date,
        contract_date,
        calendar_days_delivery,
        deadline,
        received_by,
        {$paymentTotalSelect}
    FROM bac_monitoring
    {$paymentTotalJoin}
    WHERE {$whereSql}
    ORDER BY {$orderBySql}
    LIMIT ? OFFSET ?";
$pageTypes = $types . 'ii';
$pageParams = array_merge($params, [$perPage, $offset]);
$bacStmt = $conn->prepare($bacSql);
if ($bacStmt) {
    $bacStmt->bind_param($pageTypes, ...$pageParams);
    $bacStmt->execute();
    $bacResult = $bacStmt->get_result();
    while ($row = $bacResult ? $bacResult->fetch_assoc() : null) {
        $bacRows[] = $row;
    }
    $bacStmt->close();
}
$paymentMap = [];
if ($bacRows && $paymentStorageError === '') {
    try {
        $paymentMap = payables_get_ib_payment_map($conn, array_column($bacRows, 'id'));
    } catch (Throwable $exception) {
        $paymentStorageError = 'Partial-payment history could not be loaded.';
        payables_log_error('IB payment history load failed: ' . $exception->getMessage());
    }
}
$paymentFlash = $_SESSION['_bac_payment_flash'] ?? null;
unset($_SESSION['_bac_payment_flash']);
$openPaymentRecordId = filter_input(INPUT_GET, 'payment_record', FILTER_VALIDATE_INT) ?: 0;
$returnQueryValues = ['page' => $currentPage, 'sort' => $sortOption];
if ($searchTerm !== '') {
    $returnQueryValues['search'] = $searchTerm;
}
$paymentReturnQuery = http_build_query($returnQueryValues);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="payables_dashboard.css">
    <link rel="stylesheet" href="bac_monitoring.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>BAC Monitoring</title>
</head>
<body class="bac-monitoring-page">
    <?php $payablesActivePage = 'bac_monitoring'; require 'payables_sidebar.php'; ?>
    <main class="bac-monitoring-content">
        <section class="bac-monitoring-shell" aria-label="BAC monitoring list">
            <div class="bac-monitoring-header">
                <div>
                    <span class="bac-monitoring-eyebrow">IB Monitoring</span>
                    <h1>Active IB</h1>
                </div>
                <div class="bac-monitoring-actions">
                    <a class="bac-print-button" href="print_bac_not_received.php" target="_blank" rel="noopener">
                        <i class="fas fa-print"></i>
                        <span>Print Not Yet Received</span>
                    </a>
                    <a class="bac-print-button" href="print_bac_not_received.php?view=transmitted" target="_blank" rel="noopener">
                        <i class="fas fa-print"></i>
                        <span>Print Transmitted</span>
                    </a>
                    <button type="button" class="bac-add-button" data-bs-toggle="modal" data-bs-target="#addBacMonitoringModal">
                        <i class="fas fa-plus"></i>
                        <span>Add Data</span>
                    </button>
                    <form class="bac-monitoring-search" method="get" role="search">
                        <select name="sort" class="bac-sort-select" aria-label="Sort BAC monitoring records" onchange="this.form.submit()">
                            <?php foreach ($sortOptions as $value => $option): ?>
                                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $sortOption === $value ? 'selected' : ''; ?>>
                                    Sort by <?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="search" name="search" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search IB, PR no., project, bidder, office, or status" aria-label="Search BAC documents by IB, PR number, project, bidder, office, or status">
                        <button type="submit" aria-label="Search">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <?php if (isset($_GET['added'])): ?>
                <div class="bac-monitoring-alert is-success" role="status">
                    <i class="fas fa-check"></i>
                    BAC monitoring data added successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="bac-monitoring-alert is-success" role="status">
                    <i class="fas fa-check"></i>
                    BAC monitoring data updated successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="bac-monitoring-alert is-success" role="status">
                    <i class="fas fa-check"></i>
                    BAC monitoring data deleted successfully.
                </div>
            <?php endif; ?>

        

            <div class="bac-monitoring-table" role="table" aria-label="BAC documents not final">
                <div class="bac-monitoring-row bac-monitoring-row-head" role="row">
                    <div>IB No.</div>
                    <div>Pr Included</div>
                    <div>Name of Project</div>
                    <div>ABC</div>
                    <div>Final Amount</div>
                    <div>Total Paid</div>
                    <div>Balance</div>
                    <div>Bidder</div>
                    <div>Pre Procurement</div>
                    <div>Pre Bid</div>
                    <div>Date of Bidding</div>
                    <div>Evaluation</div>
                    <div>Post Qual.</div>
                    <div>Status</div>
                    <div>Date Transmitted From BAC</div>
                    <div>Office</div>
                    <div>NOA Date</div>
                    <div>Notice to Proceed Date</div>
                    <div>Contract Date</div>
                    <div>Calendar Days of Delivery</div>
                    <div>Deadline</div>
                    <div>Received by</div>
                    <div>Actions</div>
                </div>
                <?php if ($bacRows): ?>
                    <?php foreach ($bacRows as $row): ?>
                        <?php
                        $preProcurementValue = $row['pre_procurement'] ?? '';
                        $displayPreProcurement = $preProcurementValue ? date('M d, Y', strtotime((string)$preProcurementValue)) : '-';
                        $preBidValue = $row['pre_bid'] ?? '';
                        $displayPreBid = $preBidValue ? date('M d, Y', strtotime((string)$preBidValue)) : '-';
                        $dateValue = $row['date_of_bidding'] ?? '';
                        $displayDate = $dateValue ? date('M d, Y', strtotime((string)$dateValue)) : '-';
                        $evaluationValue = $row['evaluation'] ?? '';
                        $displayEvaluation = $evaluationValue ? date('M d, Y', strtotime((string)$evaluationValue)) : '-';
                        $postQual = trim((string)($row['post_qual'] ?? ''));
                        $hasPostQual = $postQual !== '';
                        $status = trim((string)($row['status'] ?? ''));
                        $hasStatus = $status !== '';
                        $receivedBy = trim((string)($row['received_by'] ?? ''));
                        $displayStatus = $hasStatus ? $status : 'Pending';
                        if (strcasecmp($displayStatus, 'Pending') === 0 && $receivedBy === '') {
                            $displayStatus = 'Not Yet Received';
                        }
                        $dateTransmittedValue = $row['date_transmitted_from_bac'] ?? '';
                        $displayDateTransmitted = $dateTransmittedValue ? date('M d, Y', strtotime((string)$dateTransmittedValue)) : '-';
                        $noaDateValue = $row['noa_no'] ?? '';
                        $noaDateTimestamp = $noaDateValue ? strtotime((string)$noaDateValue) : false;
                        $displayNoaDate = $noaDateTimestamp ? date('M d, Y', $noaDateTimestamp) : '-';
                        $noticeDateValue = $row['notice_to_proceed_date'] ?? '';
                        $displayNoticeDate = $noticeDateValue ? date('M d, Y', strtotime((string)$noticeDateValue)) : '-';
                        $contractDateValue = $row['contract_date'] ?? '';
                        $contractDateTimestamp = $contractDateValue ? strtotime((string)$contractDateValue) : false;
                        $displayContractDate = $contractDateTimestamp ? date('M d, Y', $contractDateTimestamp) : '-';
                        $prIncluded = trim((string)($row['pr_included'] ?? ''));
                        $prNumbers = bac_monitoring_pr_numbers($prIncluded);
                        $prCount = count($prNumbers);
                        $finalAmountValue = (float)($row['final_amount'] ?? 0);
                        $totalPaidValue = (float)($row['total_paid'] ?? 0);
                        $balanceValue = max(0, $finalAmountValue - $totalPaidValue);
                        $paymentState = $totalPaidValue <= 0
                            ? 'unpaid'
                            : ($balanceValue <= 0.004 ? 'fully-paid' : 'partially-paid');
                        $paymentStateLabel = $paymentState === 'fully-paid'
                            ? 'Fully Paid'
                            : ($paymentState === 'partially-paid' ? 'Partially Paid' : 'Unpaid');
                        $recordPayments = $paymentMap[(int)$row['id']] ?? [];
                        ?>
                        <div class="bac-monitoring-row" role="row">
                            <div class="bac-ref"><?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-pr-cell">
                                <?php if ($prCount === 0): ?>
                                    <span class="bac-pr-empty">-</span>
                                <?php elseif ($prCount === 1): ?>
                                    <span class="bac-pr-single"><?php echo htmlspecialchars($prNumbers[0], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                    <button type="button" class="bac-pr-trigger" aria-label="<?php echo htmlspecialchars('Show PR numbers for IB ' . ($row['ib_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false">
                                        <span><?php echo htmlspecialchars($prNumbers[0], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong>+<?php echo $prCount - 1; ?> more</strong>
                                    </button>
                                    <div class="bac-pr-dropdown" role="list" aria-label="PR numbers included">
                                        <?php foreach ($prNumbers as $prNumber): ?>
                                            <span role="listitem"><?php echo htmlspecialchars($prNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="bac-project"><?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-amount">&#8369;<?php echo number_format((float)($row['abc'] ?? 0), 2); ?></div>
                            <div class="bac-amount">&#8369;<?php echo number_format($finalAmountValue, 2); ?></div>
                            <div class="bac-amount bac-payment-paid">&#8369;<?php echo number_format($totalPaidValue, 2); ?></div>
                            <div class="bac-payment-balance">
                                <strong>&#8369;<?php echo number_format($balanceValue, 2); ?></strong>
                                <span class="bac-payment-status is-<?php echo htmlspecialchars($paymentState, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($paymentStateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="bac-bidder"><?php echo htmlspecialchars($row['bidder'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayPreProcurement, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayPreBid, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayEvaluation, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>
                                <span class="post-qual-badge <?php echo $hasPostQual ? 'is-done' : 'is-pending'; ?>">
                                    <?php echo htmlspecialchars($hasPostQual ? $postQual : '', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div>
                                <span class="bac-status-badge <?php echo $status === 'Transmitted to GSO' ? 'is-transmitted' : 'is-pending'; ?>">
                                    <?php echo htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayDateTransmitted, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-office"><?php echo htmlspecialchars($row['office'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayNoaDate, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayNoticeDate, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayContractDate, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-ref"><?php echo htmlspecialchars($row['calendar_days_delivery'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($row['deadline'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-bidder"><?php echo htmlspecialchars($receivedBy, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-row-actions">
                                <button
                                    type="button"
                                    class="bac-icon-button bac-payment-row"
                                    title="Manage partial payments"
                                    aria-label="<?php echo htmlspecialchars('Manage partial payments for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-record-id="<?php echo (int)$row['id']; ?>"
                                    data-ib-no="<?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-project-name="<?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-final-amount="<?php echo htmlspecialchars(number_format($finalAmountValue, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-total-paid="<?php echo htmlspecialchars(number_format($totalPaidValue, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-balance="<?php echo htmlspecialchars(number_format($balanceValue, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-payment-state="<?php echo htmlspecialchars($paymentState, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-payments="<?php echo htmlspecialchars(json_encode($recordPayments, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <i class="fas fa-money-check-dollar"></i>
                                </button>
                                <a
                                    class="bac-icon-button"
                                    href="print_document_barcode.php?type=IB&id=<?php echo (int)$row['id']; ?>"
                                    target="_blank"
                                    rel="noopener"
                                    title="Print barcode"
                                    aria-label="<?php echo htmlspecialchars('Print barcode for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <i class="fas fa-barcode"></i>
                                </a>
                                <button
                                    type="button"
                                    class="bac-icon-button bac-edit-row"
                                    title="Edit"
                                    aria-label="<?php echo htmlspecialchars('Edit ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-id="<?php echo (int)$row['id']; ?>"
                                    data-ib-no="<?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-pr-included="<?php echo htmlspecialchars($row['pr_included'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-project-name="<?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-abc="<?php echo htmlspecialchars((string)($row['abc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-final-amount="<?php echo htmlspecialchars((string)($row['final_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-bidder="<?php echo htmlspecialchars($row['bidder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-pre-procurement="<?php echo htmlspecialchars($row['pre_procurement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-pre-bid="<?php echo htmlspecialchars($row['pre_bid'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date-of-bidding="<?php echo htmlspecialchars($row['date_of_bidding'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-evaluation="<?php echo htmlspecialchars($row['evaluation'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-post-qual="<?php echo htmlspecialchars($row['post_qual'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date-transmitted-from-bac="<?php echo htmlspecialchars($row['date_transmitted_from_bac'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-office="<?php echo htmlspecialchars($row['office'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-noa-no="<?php echo htmlspecialchars($row['noa_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notice-to-proceed-date="<?php echo htmlspecialchars($row['notice_to_proceed_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-contract-date="<?php echo htmlspecialchars($row['contract_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-calendar-days-delivery="<?php echo htmlspecialchars($row['calendar_days_delivery'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-deadline="<?php echo htmlspecialchars($row['deadline'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-received-by="<?php echo htmlspecialchars($row['received_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="post" action="bac_monitoring.php" class="bac-delete-form">
                                    <?php echo payables_csrf_input(); ?>
                                    <input type="hidden" name="action" value="delete_bac_monitoring">
                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                    <button
                                        type="submit"
                                        class="bac-icon-button is-danger"
                                        title="Delete"
                                        aria-label="<?php echo htmlspecialchars('Delete ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bac-monitoring-empty">
                        <?php echo $searchTerm !== '' ? 'No matching active BAC documents found.' : 'No active BAC documents found.'; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bac-monitoring-pagination" aria-label="BAC monitoring pagination">
                <span>
                    Showing <?php echo $totalRows === 0 ? 0 : $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?>
                    of <?php echo number_format($totalRows); ?>
                </span>
                <div>
                    <a class="<?php echo $currentPage <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous page">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <strong>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></strong>
                    <a class="<?php echo $currentPage >= $totalPages ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next page">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </section>
    </main>
    <div class="modal fade" id="addBacMonitoringModal" tabindex="-1" aria-labelledby="addBacMonitoringModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered bac-add-dialog">
            <form class="modal-content bac-add-modal" method="post" action="bac_monitoring.php">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="addBacMonitoringModalLabel">Add BAC Data</h5>
                        <div class="bac-add-subtitle">Enter the BAC monitoring details for this document.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo payables_csrf_input(); ?>
                    <input type="hidden" name="action" value="add_bac_monitoring">
                    <?php if ($formErrors): ?>
                        <div class="bac-monitoring-alert is-error" role="alert">
                            <?php echo htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="bac-add-grid">
                        <label>
                            <span>IB No.</span>
                            <input type="text" name="ib_no" value="<?php echo htmlspecialchars($formValues['ib_no'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label>
                            <span>Pr Included</span>
                            <textarea name="pr_included" rows="2" placeholder="1234-1234, 4567-45789"><?php echo htmlspecialchars($formValues['pr_included'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label>
                            <span>ABC</span>
                            <input type="text" name="abc" value="<?php echo htmlspecialchars($formValues['abc'], ENT_QUOTES, 'UTF-8'); ?>" inputmode="decimal" required>
                        </label>
                        <label>
                            <span>Final Amount</span>
                            <input type="text" name="final_amount" value="<?php echo htmlspecialchars($formValues['final_amount'], ENT_QUOTES, 'UTF-8'); ?>" inputmode="decimal">
                        </label>
                        <label class="is-wide">
                            <span>Name of Project</span>
                            <input type="text" name="project_name" value="<?php echo htmlspecialchars($formValues['project_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="is-wide">
                            <span>Bidder</span>
                            <input type="text" name="bidder" value="<?php echo htmlspecialchars($formValues['bidder'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label>
                            <span>Pre Procurement</span>
                            <input type="date" name="pre_procurement" value="<?php echo htmlspecialchars($formValues['pre_procurement'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Pre Bid</span>
                            <input type="date" name="pre_bid" value="<?php echo htmlspecialchars($formValues['pre_bid'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Date of Bidding</span>
                            <input type="date" name="date_of_bidding" value="<?php echo htmlspecialchars($formValues['date_of_bidding'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Evaluation</span>
                            <input type="date" name="evaluation" value="<?php echo htmlspecialchars($formValues['evaluation'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Post Qual.</span>
                            <input type="text" name="post_qual" value="<?php echo htmlspecialchars($formValues['post_qual'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Pending, date, or remarks">
                        </label>
                        <label>
                            <span>Date Transmitted From BAC</span>
                            <input type="date" name="date_transmitted_from_bac" value="<?php echo htmlspecialchars($formValues['date_transmitted_from_bac'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Office</span>
                            <input type="text" name="office" value="<?php echo htmlspecialchars($formValues['office'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>NOA Date</span>
                            <input type="date" name="noa_no" value="<?php echo htmlspecialchars($formValues['noa_no'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Notice to Proceed Date</span>
                            <input type="date" name="notice_to_proceed_date" value="<?php echo htmlspecialchars($formValues['notice_to_proceed_date'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Contract Date</span>
                            <input type="date" name="contract_date" value="<?php echo htmlspecialchars($formValues['contract_date'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Calendar Days of Delivery</span>
                            <input type="text" name="calendar_days_delivery" value="<?php echo htmlspecialchars($formValues['calendar_days_delivery'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Deadline</span>
                            <input type="text" name="deadline" value="<?php echo htmlspecialchars($formValues['deadline'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="is-wide">
                            <span>Received by</span>
                            <input type="text" name="received_by" value="<?php echo htmlspecialchars($formValues['received_by'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success bac-save-button">Save Data</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="editBacMonitoringModal" tabindex="-1" aria-labelledby="editBacMonitoringModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered bac-add-dialog">
            <form class="modal-content bac-add-modal" method="post" action="bac_monitoring.php">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="editBacMonitoringModalLabel">Edit BAC Data</h5>
                        <div class="bac-add-subtitle">Update this BAC monitoring document.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo payables_csrf_input(); ?>
                    <input type="hidden" name="action" value="edit_bac_monitoring">
                    <input type="hidden" name="id" id="editBacId" value="<?php echo htmlspecialchars($formValues['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="status" id="editBacStatus" value="<?php echo htmlspecialchars($showEditModal ? $formValues['status'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($formErrors && $showEditModal): ?>
                        <div class="bac-monitoring-alert is-error" role="alert">
                            <?php echo htmlspecialchars(implode(' ', $formErrors), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="bac-add-grid">
                        <label>
                            <span>IB No.</span>
                            <input type="text" name="ib_no" id="editBacIbNo" value="<?php echo htmlspecialchars($showEditModal ? $formValues['ib_no'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label>
                            <span>Pr Included</span>
                            <textarea name="pr_included" id="editBacPrIncluded" rows="2" placeholder="1234-1234, 4567-45789"><?php echo htmlspecialchars($showEditModal ? $formValues['pr_included'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label>
                            <span>ABC</span>
                            <input type="text" name="abc" id="editBacAbc" value="<?php echo htmlspecialchars($showEditModal ? $formValues['abc'] : '', ENT_QUOTES, 'UTF-8'); ?>" inputmode="decimal" required>
                        </label>
                        <label>
                            <span>Final Amount</span>
                            <input type="text" name="final_amount" id="editBacFinalAmount" value="<?php echo htmlspecialchars($showEditModal ? $formValues['final_amount'] : '', ENT_QUOTES, 'UTF-8'); ?>" inputmode="decimal">
                        </label>
                        <label class="is-wide">
                            <span>Name of Project</span>
                            <input type="text" name="project_name" id="editBacProjectName" value="<?php echo htmlspecialchars($showEditModal ? $formValues['project_name'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="is-wide">
                            <span>Bidder</span>
                            <input type="text" name="bidder" id="editBacBidder" value="<?php echo htmlspecialchars($showEditModal ? $formValues['bidder'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label>
                            <span>Pre Procurement</span>
                            <input type="date" name="pre_procurement" id="editBacPreProcurement" value="<?php echo htmlspecialchars($showEditModal ? $formValues['pre_procurement'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Pre Bid</span>
                            <input type="date" name="pre_bid" id="editBacPreBid" value="<?php echo htmlspecialchars($showEditModal ? $formValues['pre_bid'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Date of Bidding</span>
                            <input type="date" name="date_of_bidding" id="editBacDateOfBidding" value="<?php echo htmlspecialchars($showEditModal ? $formValues['date_of_bidding'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Evaluation</span>
                            <input type="date" name="evaluation" id="editBacEvaluation" value="<?php echo htmlspecialchars($showEditModal ? $formValues['evaluation'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Post Qual.</span>
                            <input type="text" name="post_qual" id="editBacPostQual" value="<?php echo htmlspecialchars($showEditModal ? $formValues['post_qual'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Pending, date, or remarks">
                        </label>
                        <label>
                            <span>Date Transmitted From BAC</span>
                            <input type="date" name="date_transmitted_from_bac" id="editBacDateTransmittedFromBac" value="<?php echo htmlspecialchars($showEditModal ? $formValues['date_transmitted_from_bac'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Office</span>
                            <input type="text" name="office" id="editBacOffice" value="<?php echo htmlspecialchars($showEditModal ? $formValues['office'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>NOA Date</span>
                            <input type="date" name="noa_no" id="editBacNoaNo" value="<?php echo htmlspecialchars($showEditModal ? $formValues['noa_no'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Notice to Proceed Date</span>
                            <input type="date" name="notice_to_proceed_date" id="editBacNoticeToProceedDate" value="<?php echo htmlspecialchars($showEditModal ? $formValues['notice_to_proceed_date'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Contract Date</span>
                            <input type="date" name="contract_date" id="editBacContractDate" value="<?php echo htmlspecialchars($showEditModal ? $formValues['contract_date'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Calendar Days of Delivery</span>
                            <input type="text" name="calendar_days_delivery" id="editBacCalendarDaysDelivery" value="<?php echo htmlspecialchars($showEditModal ? $formValues['calendar_days_delivery'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Deadline</span>
                            <input type="text" name="deadline" id="editBacDeadline" value="<?php echo htmlspecialchars($showEditModal ? $formValues['deadline'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="is-wide">
                            <span>Received by</span>
                            <input type="text" name="received_by" id="editBacReceivedBy" value="<?php echo htmlspecialchars($showEditModal ? $formValues['received_by'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success bac-save-button">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="ibPaymentModal" tabindex="-1" aria-labelledby="ibPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered bac-payment-dialog">
            <div class="modal-content bac-payment-modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="ibPaymentModalLabel">Partial Payments</h5>
                        <div class="bac-add-subtitle" id="ibPaymentSubtitle">Record and review IB payments.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="bac-payment-summary" aria-label="Payment summary">
                        <div><span>Final Amount</span><strong id="paymentFinalAmount">&#8369;0.00</strong></div>
                        <div><span>Total Paid</span><strong id="paymentTotalPaid">&#8369;0.00</strong></div>
                        <div><span>Balance</span><strong id="paymentBalance">&#8369;0.00</strong></div>
                        <div><span>Status</span><strong class="bac-payment-status is-unpaid" id="paymentStatus">Unpaid</strong></div>
                    </div>

                    <div class="bac-monitoring-alert d-none" id="paymentModalAlert" role="status" aria-live="polite"></div>

                    <section class="bac-payment-entry" aria-labelledby="paymentEntryTitle">
                        <div class="bac-payment-section-head">
                            <div>
                                <h6 id="paymentEntryTitle">Record Partial Payment</h6>
                                <span id="paymentEntryHelp">Enter an amount up to the remaining balance.</span>
                            </div>
                            <button type="button" class="bac-payment-cancel-edit d-none" id="cancelPaymentEdit">Cancel edit</button>
                        </div>
                        <form method="post" action="bac_monitoring.php" id="ibPaymentForm">
                            <?php echo payables_csrf_input(); ?>
                            <input type="hidden" name="action" id="paymentAction" value="add_ib_payment">
                            <input type="hidden" name="record_id" id="paymentRecordId">
                            <input type="hidden" name="payment_id" id="paymentId">
                            <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($paymentReturnQuery, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="bac-payment-form-grid">
                                <label>
                                    <span>Payment Amount</span>
                                    <div class="bac-payment-money-input">
                                        <span>&#8369;</span>
                                        <input type="text" name="payment_amount" id="paymentAmount" inputmode="decimal" autocomplete="off" placeholder="0.00" required>
                                    </div>
                                </label>
                                <label>
                                    <span>Payment Date</span>
                                    <input type="date" name="payment_date" id="paymentDate" max="<?php echo date('Y-m-d'); ?>" required>
                                </label>
                                <label>
                                    <span>DV Number</span>
                                    <input type="text" name="dv_number" id="paymentDvNumber" placeholder="e.g. DV-2024-0001">
                                </label>
                                <button type="submit" class="btn btn-success bac-save-button" id="savePaymentButton">
                                    <i class="fas fa-plus"></i>
                                    <span>Record Payment</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="bac-payment-history" aria-labelledby="paymentHistoryTitle">
                        <div class="bac-payment-section-head">
                            <div>
                                <h6 id="paymentHistoryTitle">Payment History</h6>
                                <span>Most recent payment first</span>
                            </div>
                        </div>
                        <div id="paymentHistoryList"></div>
                    </section>
                </div>
            </div>
        </div>
    </div>
    <form method="post" action="bac_monitoring.php" id="deletePaymentForm" class="d-none">
        <?php echo payables_csrf_input(); ?>
        <input type="hidden" name="action" value="delete_ib_payment">
        <input type="hidden" name="record_id" id="deletePaymentRecordId">
        <input type="hidden" name="payment_id" id="deletePaymentId">
        <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($paymentReturnQuery, ENT_QUOTES, 'UTF-8'); ?>">
    </form>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const paymentFlash = <?php echo json_encode($paymentFlash, JSON_UNESCAPED_SLASHES); ?>;
        const openPaymentRecordId = <?php echo (int)$openPaymentRecordId; ?>;
        const paymentStorageError = <?php echo json_encode($paymentStorageError); ?>;

        (function () {
            var modalElement = document.getElementById("ibPaymentModal");
            var paymentForm = document.getElementById("ibPaymentForm");
            var paymentAction = document.getElementById("paymentAction");
            var paymentRecordId = document.getElementById("paymentRecordId");
            var paymentId = document.getElementById("paymentId");
            var paymentAmount = document.getElementById("paymentAmount");
            var paymentDate = document.getElementById("paymentDate");
            var paymentDvNumber = document.getElementById("paymentDvNumber");
            var saveButton = document.getElementById("savePaymentButton");
            var cancelEdit = document.getElementById("cancelPaymentEdit");
            var entryTitle = document.getElementById("paymentEntryTitle");
            var entryHelp = document.getElementById("paymentEntryHelp");
            var historyList = document.getElementById("paymentHistoryList");
            var modalAlert = document.getElementById("paymentModalAlert");
            var activePaymentButton = null;
            var activePayments = [];

            function escapeHtml(value) {
                return String(value == null ? "" : value)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            function money(value) {
                return "₱" + new Intl.NumberFormat("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(Number(value || 0));
            }

            function paymentStatusLabel(state) {
                if (state === "fully-paid") return "Fully Paid";
                if (state === "partially-paid") return "Partially Paid";
                return "Unpaid";
            }

            function showPaymentAlert(type, message) {
                if (!message) {
                    modalAlert.className = "bac-monitoring-alert d-none";
                    modalAlert.textContent = "";
                    return;
                }
                modalAlert.className = "bac-monitoring-alert is-" + (type === "success" ? "success" : "error");
                modalAlert.textContent = message;
            }

            function setAddAvailability() {
                if (!activePaymentButton) return;
                var finalAmount = Number(activePaymentButton.dataset.finalAmount || 0);
                var state = activePaymentButton.dataset.paymentState || "unpaid";
                var unavailableMessage = "";
                if (paymentStorageError) {
                    unavailableMessage = paymentStorageError;
                } else if (finalAmount <= 0) {
                    unavailableMessage = "Set a positive Final Amount before recording a payment.";
                } else if (state === "fully-paid") {
                    unavailableMessage = "This IB is fully paid. Edit or delete an existing payment to change the balance.";
                }

                paymentAmount.disabled = unavailableMessage !== "";
                paymentDate.disabled = unavailableMessage !== "";
                paymentDvNumber.disabled = unavailableMessage !== "";
                saveButton.disabled = unavailableMessage !== "";
                entryHelp.textContent = unavailableMessage || "Enter an amount up to " + money(activePaymentButton.dataset.balance) + ".";
            }

            function resetPaymentForm() {
                paymentAction.value = "add_ib_payment";
                paymentId.value = "";
                paymentAmount.value = "";
                paymentDvNumber.value = "";
                paymentDate.value = paymentDate.max;
                entryTitle.textContent = "Record Partial Payment";
                saveButton.querySelector("i").className = "fas fa-plus";
                saveButton.querySelector("span").textContent = "Record Payment";
                cancelEdit.classList.add("d-none");
                setAddAvailability();
            }

            function renderPaymentHistory() {
                if (!activePayments.length) {
                    historyList.innerHTML = '<div class="bac-payment-empty"><i class="fas fa-receipt"></i><span>No partial payments recorded.</span></div>';
                    return;
                }

                historyList.innerHTML = activePayments.map(function (payment, index) {
                    var changedBy = payment.updated_by || payment.created_by || "Payables admin";
                    var dvHtml = payment.dv_number ? '<span class="bac-payment-dv-number">' + escapeHtml(payment.dv_number) + '</span>' : '';
                    return '<div class="bac-payment-history-row">' +
                        '<div><strong>' + money(payment.amount) + '</strong><span>' + escapeHtml(payment.payment_date) + '</span>' + dvHtml + '</div>' +
                        '<div class="bac-payment-history-user"><span>Recorded by</span><strong>' + escapeHtml(changedBy) + '</strong></div>' +
                        '<div class="bac-payment-history-actions">' +
                            '<button type="button" data-edit-payment="' + index + '" aria-label="Edit payment"><i class="fas fa-pen"></i></button>' +
                            '<button type="button" class="is-danger" data-delete-payment="' + escapeHtml(payment.id) + '" aria-label="Delete payment"><i class="fas fa-trash"></i></button>' +
                        '</div>' +
                    '</div>';
                }).join("");
            }

            function openPaymentModal(button) {
                activePaymentButton = button;
                try {
                    activePayments = JSON.parse(button.dataset.payments || "[]");
                } catch (error) {
                    activePayments = [];
                }

                paymentRecordId.value = button.dataset.recordId || "";
                document.getElementById("ibPaymentModalLabel").textContent = "Partial Payments · " + (button.dataset.ibNo || "IB");
                document.getElementById("ibPaymentSubtitle").textContent = button.dataset.projectName || "Record and review IB payments.";
                document.getElementById("paymentFinalAmount").textContent = money(button.dataset.finalAmount);
                document.getElementById("paymentTotalPaid").textContent = money(button.dataset.totalPaid);
                document.getElementById("paymentBalance").textContent = money(button.dataset.balance);
                var status = document.getElementById("paymentStatus");
                var state = button.dataset.paymentState || "unpaid";
                status.className = "bac-payment-status is-" + state;
                status.textContent = paymentStatusLabel(state);
                showPaymentAlert("", "");
                resetPaymentForm();
                renderPaymentHistory();
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }

            document.querySelectorAll(".bac-payment-row").forEach(function (button) {
                button.addEventListener("click", function () {
                    openPaymentModal(button);
                });
            });

            historyList.addEventListener("click", function (event) {
                var editButton = event.target.closest("[data-edit-payment]");
                if (editButton) {
                    var payment = activePayments[Number(editButton.dataset.editPayment)];
                    if (!payment) return;
                    paymentAction.value = "edit_ib_payment";
                    paymentId.value = payment.id || "";
                    paymentAmount.disabled = false;
                    paymentDate.disabled = false;
                    paymentDvNumber.disabled = false;
                    saveButton.disabled = false;
                    paymentAmount.value = Number(payment.amount || 0).toFixed(2);
                    paymentDvNumber.value = payment.dv_number || "";
                    paymentDate.value = payment.payment_date || paymentDate.max;
                    entryTitle.textContent = "Edit Partial Payment";
                    entryHelp.textContent = "The balance will be recalculated after saving.";
                    saveButton.querySelector("i").className = "fas fa-check";
                    saveButton.querySelector("span").textContent = "Save Payment";
                    cancelEdit.classList.remove("d-none");
                    paymentAmount.focus();
                    return;
                }

                var deleteButton = event.target.closest("[data-delete-payment]");
                if (!deleteButton || !activePaymentButton) return;
                if (!confirm("Delete this partial payment? The IB balance will be recalculated.")) return;
                document.getElementById("deletePaymentRecordId").value = activePaymentButton.dataset.recordId || "";
                document.getElementById("deletePaymentId").value = deleteButton.dataset.deletePayment || "";
                document.getElementById("deletePaymentForm").submit();
            });

            cancelEdit.addEventListener("click", resetPaymentForm);

            if (openPaymentRecordId > 0) {
                var targetButton = document.querySelector('.bac-payment-row[data-record-id="' + openPaymentRecordId + '"]');
                if (targetButton) {
                    openPaymentModal(targetButton);
                    if (paymentFlash) {
                        showPaymentAlert(paymentFlash.type, paymentFlash.message);
                        var oldInput = paymentFlash.old_input || {};
                        if (paymentFlash.type === "error" && oldInput.action !== "delete_ib_payment") {
                            if (oldInput.action === "edit_ib_payment") {
                                paymentAction.value = "edit_ib_payment";
                                paymentId.value = oldInput.payment_id || "";
                                entryTitle.textContent = "Edit Partial Payment";
                                saveButton.querySelector("i").className = "fas fa-check";
                                saveButton.querySelector("span").textContent = "Save Payment";
                                cancelEdit.classList.remove("d-none");
                            }
                            paymentAmount.disabled = false;
                            paymentDate.disabled = false;
                            paymentDvNumber.disabled = false;
                            saveButton.disabled = false;
                            paymentAmount.value = oldInput.amount || "";
                            paymentDvNumber.value = oldInput.dv_number || "";
                            paymentDate.value = oldInput.payment_date || paymentDate.max;
                        }
                    }
                }

                var cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete("payment_record");
                window.history.replaceState({}, "", cleanUrl.pathname + (cleanUrl.search ? cleanUrl.search : ""));
            }
        })();

        document.querySelectorAll(".bac-edit-row").forEach(function (button) {
            button.addEventListener("click", function () {
                document.getElementById("editBacId").value = button.dataset.id || "";
                document.getElementById("editBacIbNo").value = button.dataset.ibNo || "";
                document.getElementById("editBacPrIncluded").value = button.dataset.prIncluded || "";
                document.getElementById("editBacProjectName").value = button.dataset.projectName || "";
                document.getElementById("editBacAbc").value = button.dataset.abc || "";
                document.getElementById("editBacFinalAmount").value = button.dataset.finalAmount || "";
                document.getElementById("editBacBidder").value = button.dataset.bidder || "";
                document.getElementById("editBacPreProcurement").value = button.dataset.preProcurement || "";
                document.getElementById("editBacPreBid").value = button.dataset.preBid || "";
                document.getElementById("editBacDateOfBidding").value = button.dataset.dateOfBidding || "";
                document.getElementById("editBacEvaluation").value = button.dataset.evaluation || "";
                document.getElementById("editBacPostQual").value = button.dataset.postQual || "";
                document.getElementById("editBacStatus").value = button.dataset.status || "";
                document.getElementById("editBacDateTransmittedFromBac").value = button.dataset.dateTransmittedFromBac || "";
                document.getElementById("editBacOffice").value = button.dataset.office || "";
                document.getElementById("editBacNoaNo").value = button.dataset.noaNo || "";
                document.getElementById("editBacNoticeToProceedDate").value = button.dataset.noticeToProceedDate || "";
                document.getElementById("editBacContractDate").value = button.dataset.contractDate || "";
                document.getElementById("editBacCalendarDaysDelivery").value = button.dataset.calendarDaysDelivery || "";
                document.getElementById("editBacDeadline").value = button.dataset.deadline || "";
                document.getElementById("editBacReceivedBy").value = button.dataset.receivedBy || "";
                bootstrap.Modal.getOrCreateInstance(document.getElementById("editBacMonitoringModal")).show();
            });
        });

        document.querySelectorAll(".bac-delete-form").forEach(function (form) {
            form.addEventListener("submit", function (event) {
                if (!confirm("Delete this BAC monitoring record?")) {
                    event.preventDefault();
                }
            });
        });

        (function () {
            var floatingPrList = document.createElement("div");
            var activeTrigger = null;
            var hideTimer = null;

            floatingPrList.className = "bac-pr-floating";
            floatingPrList.setAttribute("role", "list");
            floatingPrList.setAttribute("aria-label", "PR numbers included");
            document.body.appendChild(floatingPrList);

            function hidePrList() {
                if (activeTrigger) {
                    activeTrigger.setAttribute("aria-expanded", "false");
                }
                activeTrigger = null;
                floatingPrList.classList.remove("is-visible");
                floatingPrList.innerHTML = "";
            }

            function queueHidePrList() {
                clearTimeout(hideTimer);
                hideTimer = setTimeout(hidePrList, 120);
            }

            function positionPrList(trigger) {
                var rect = trigger.getBoundingClientRect();
                var width = Math.max(190, Math.min(280, floatingPrList.offsetWidth || 220));
                var left = Math.min(Math.max(12, rect.left), window.innerWidth - width - 12);
                var top = rect.bottom + 8;

                if (top + floatingPrList.offsetHeight + 12 > window.innerHeight) {
                    top = Math.max(12, rect.top - floatingPrList.offsetHeight - 8);
                }

                floatingPrList.style.left = left + "px";
                floatingPrList.style.top = top + "px";
            }

            function showPrList(trigger) {
                var sourceList = trigger.parentElement.querySelector(".bac-pr-dropdown");
                if (!sourceList) {
                    return;
                }

                clearTimeout(hideTimer);
                activeTrigger = trigger;
                floatingPrList.innerHTML = sourceList.innerHTML;
                floatingPrList.classList.add("is-visible");
                trigger.setAttribute("aria-expanded", "true");
                positionPrList(trigger);
            }

            document.querySelectorAll(".bac-pr-trigger").forEach(function (trigger) {
                trigger.addEventListener("mouseenter", function () {
                    showPrList(trigger);
                });
                trigger.addEventListener("focus", function () {
                    showPrList(trigger);
                });
                trigger.addEventListener("mouseleave", queueHidePrList);
                trigger.addEventListener("blur", queueHidePrList);
                trigger.addEventListener("click", function () {
                    if (activeTrigger === trigger && floatingPrList.classList.contains("is-visible")) {
                        hidePrList();
                    } else {
                        showPrList(trigger);
                    }
                });
            });

            floatingPrList.addEventListener("mouseenter", function () {
                clearTimeout(hideTimer);
            });
            floatingPrList.addEventListener("mouseleave", queueHidePrList);
            window.addEventListener("scroll", function () {
                if (activeTrigger) {
                    positionPrList(activeTrigger);
                }
            }, true);
            window.addEventListener("resize", function () {
                if (activeTrigger) {
                    positionPrList(activeTrigger);
                }
            });
            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape") {
                    hidePrList();
                }
            });
        })();
    </script>
    <?php if ($showAddModal): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById("addBacMonitoringModal")).show();
            });
        </script>
    <?php endif; ?>
    <?php if ($showEditModal): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById("editBacMonitoringModal")).show();
            });
        </script>
    <?php endif; ?>
</body>
</html>
