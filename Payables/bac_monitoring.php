<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';

$formErrors = [];
$formValues = [
    'id' => '',
    'ib_no' => '',
    'project_name' => '',
    'abc' => '',
    'final_amount' => '',
    'bidder' => '',
    'date_of_bidding' => '',
    'post_qual' => '',
    'status' => '',
    'date_transmitted_from_bac' => '',
    'office' => '',
    'noa_no' => '',
    'notice_to_proceed_date' => '',
    'calendar_days_delivery' => '',
    'deadline' => '',
    'received_by' => '',
];
$showAddModal = false;
$showEditModal = false;
$statusOptions = ['Pending', 'Transmitted to GSO'];

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
    if ($values['date_of_bidding'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $values['date_of_bidding']);
        if (!$date || $date->format('Y-m-d') !== $values['date_of_bidding']) {
            $errors[] = 'Date of bidding must be a valid date.';
        }
    }
    foreach (['date_transmitted_from_bac' => 'Date transmitted from BAC', 'notice_to_proceed_date' => 'Notice to proceed date'] as $key => $label) {
        if ($values[$key] !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $values[$key]);
            if (!$date || $date->format('Y-m-d') !== $values[$key]) {
                $errors[] = $label . ' must be a valid date.';
            }
        }
    }
    if (!in_array($values['status'], ['Pending', 'Transmitted to GSO'], true)) {
        $errors[] = 'Status must be Pending or Transmitted to GSO.';
    }

    return $errors;
};

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_bac_monitoring', 'edit_bac_monitoring', 'delete_bac_monitoring'], true)) {
    $formValues = [
        'id' => trim($_POST['id'] ?? ''),
        'ib_no' => trim($_POST['ib_no'] ?? ''),
        'project_name' => trim($_POST['project_name'] ?? ''),
        'abc' => trim($_POST['abc'] ?? ''),
        'final_amount' => trim($_POST['final_amount'] ?? ''),
        'bidder' => trim($_POST['bidder'] ?? ''),
        'date_of_bidding' => trim($_POST['date_of_bidding'] ?? ''),
        'post_qual' => trim($_POST['post_qual'] ?? ''),
        'status' => trim($_POST['status'] ?? ''),
        'date_transmitted_from_bac' => trim($_POST['date_transmitted_from_bac'] ?? ''),
        'office' => trim($_POST['office'] ?? ''),
        'noa_no' => trim($_POST['noa_no'] ?? ''),
        'notice_to_proceed_date' => trim($_POST['notice_to_proceed_date'] ?? ''),
        'calendar_days_delivery' => trim($_POST['calendar_days_delivery'] ?? ''),
        'deadline' => trim($_POST['deadline'] ?? ''),
        'received_by' => trim($_POST['received_by'] ?? ''),
    ];
    if ($formValues['status'] === '') {
        $formValues['status'] = 'Pending';
    }
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
        $dateOfBidding = $formValues['date_of_bidding'] !== '' ? $formValues['date_of_bidding'] : null;
        $dateTransmittedFromBac = $formValues['date_transmitted_from_bac'] !== '' ? $formValues['date_transmitted_from_bac'] : null;
        $noticeToProceedDate = $formValues['notice_to_proceed_date'] !== '' ? $formValues['notice_to_proceed_date'] : null;

        if (!$formErrors && $action === 'add_bac_monitoring') {
            $stmt = $conn->prepare("
                INSERT INTO bac_monitoring (
                    ib_no, project_name, abc, final_amount, bidder, date_of_bidding, post_qual, status,
                    date_transmitted_from_bac, office, noa_no, notice_to_proceed_date,
                    calendar_days_delivery, deadline, received_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'ssddsssssssssss',
                    $formValues['ib_no'],
                    $formValues['project_name'],
                    $abc,
                    $finalAmount,
                    $formValues['bidder'],
                    $dateOfBidding,
                    $formValues['post_qual'],
                    $formValues['status'],
                    $dateTransmittedFromBac,
                    $formValues['office'],
                    $formValues['noa_no'],
                    $noticeToProceedDate,
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
                    project_name = ?,
                    abc = ?,
                    final_amount = ?,
                    bidder = ?,
                    date_of_bidding = ?,
                    post_qual = ?,
                    status = ?,
                    date_transmitted_from_bac = ?,
                    office = ?,
                    noa_no = ?,
                    notice_to_proceed_date = ?,
                    calendar_days_delivery = ?,
                    deadline = ?,
                    received_by = ?
                WHERE id = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'ssddsssssssssssi',
                    $formValues['ib_no'],
                    $formValues['project_name'],
                    $abc,
                    $finalAmount,
                    $formValues['bidder'],
                    $dateOfBidding,
                    $formValues['post_qual'],
                    $formValues['status'],
                    $dateTransmittedFromBac,
                    $formValues['office'],
                    $formValues['noa_no'],
                    $noticeToProceedDate,
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
    $where[] = "(ib_no LIKE ? OR project_name LIKE ? OR bidder LIKE ? OR CAST(abc AS CHAR) LIKE ? OR CAST(final_amount AS CHAR) LIKE ? OR post_qual LIKE ? OR status LIKE ? OR office LIKE ? OR noa_no LIKE ? OR received_by LIKE ?)";
    $types .= 'ssssssssss';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
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
$bacSql = "
    SELECT
        id,
        ib_no,
        project_name,
        abc,
        final_amount,
        bidder,
        date_of_bidding,
        post_qual,
        status,
        date_transmitted_from_bac,
        office,
        noa_no,
        notice_to_proceed_date,
        calendar_days_delivery,
        deadline,
        received_by
    FROM bac_monitoring
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
                    <span class="bac-monitoring-eyebrow">IB/RFQ Monitoring</span>
                    <h1>Active IB/RFQ</h1>
                </div>
                <div class="bac-monitoring-actions">
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
                        <input type="search" name="search" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search IB, project, bidder, office, or status" aria-label="Search BAC documents">
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
                    <div>Name of Project</div>
                    <div>ABC</div>
                    <div>Final Amount</div>
                    <div>Bidder</div>
                    <div>Date of Bidding</div>
                    <div>Post Qual.</div>
                    <div>Status</div>
                    <div>Date Transmitted From BAC</div>
                    <div>Office</div>
                    <div>NOA no.</div>
                    <div>Notice to Proceed Date</div>
                    <div>Calendar Days of Delivery</div>
                    <div>Deadline</div>
                    <div>Received by</div>
                    <div>Actions</div>
                </div>
                <?php if ($bacRows): ?>
                    <?php foreach ($bacRows as $row): ?>
                        <?php
                        $dateValue = $row['date_of_bidding'] ?? '';
                        $displayDate = $dateValue ? date('M d, Y', strtotime((string)$dateValue)) : '-';
                        $postQual = trim((string)($row['post_qual'] ?? ''));
                        $hasPostQual = $postQual !== '';
                        $status = trim((string)($row['status'] ?? ''));
                        $hasStatus = $status !== '';
                        $dateTransmittedValue = $row['date_transmitted_from_bac'] ?? '';
                        $displayDateTransmitted = $dateTransmittedValue ? date('M d, Y', strtotime((string)$dateTransmittedValue)) : '-';
                        $noticeDateValue = $row['notice_to_proceed_date'] ?? '';
                        $displayNoticeDate = $noticeDateValue ? date('M d, Y', strtotime((string)$noticeDateValue)) : '-';
                        ?>
                        <div class="bac-monitoring-row" role="row">
                            <div class="bac-ref"><?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-project"><?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-amount">&#8369;<?php echo number_format((float)($row['abc'] ?? 0), 2); ?></div>
                            <div class="bac-amount">&#8369;<?php echo number_format((float)($row['final_amount'] ?? 0), 2); ?></div>
                            <div class="bac-bidder"><?php echo htmlspecialchars($row['bidder'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div>
                                <span class="post-qual-badge <?php echo $hasPostQual ? 'is-done' : 'is-pending'; ?>">
                                    <?php echo htmlspecialchars($hasPostQual ? $postQual : 'Pending', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div>
                                <span class="bac-status-badge <?php echo $status === 'Transmitted to GSO' ? 'is-transmitted' : 'is-pending'; ?>">
                                    <?php echo htmlspecialchars($hasStatus ? $status : 'Pending', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayDateTransmitted, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-office"><?php echo htmlspecialchars($row['office'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-ref"><?php echo htmlspecialchars($row['noa_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($displayNoticeDate, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-ref"><?php echo htmlspecialchars($row['calendar_days_delivery'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-date"><?php echo htmlspecialchars($row['deadline'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-bidder"><?php echo htmlspecialchars($row['received_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-row-actions">
                                <button
                                    type="button"
                                    class="bac-icon-button bac-edit-row"
                                    title="Edit"
                                    aria-label="<?php echo htmlspecialchars('Edit ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-id="<?php echo (int)$row['id']; ?>"
                                    data-ib-no="<?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-project-name="<?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-abc="<?php echo htmlspecialchars((string)($row['abc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-final-amount="<?php echo htmlspecialchars((string)($row['final_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-bidder="<?php echo htmlspecialchars($row['bidder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date-of-bidding="<?php echo htmlspecialchars($row['date_of_bidding'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-post-qual="<?php echo htmlspecialchars($row['post_qual'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date-transmitted-from-bac="<?php echo htmlspecialchars($row['date_transmitted_from_bac'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-office="<?php echo htmlspecialchars($row['office'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-noa-no="<?php echo htmlspecialchars($row['noa_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notice-to-proceed-date="<?php echo htmlspecialchars($row['notice_to_proceed_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
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
                            <span>Date of Bidding</span>
                            <input type="date" name="date_of_bidding" value="<?php echo htmlspecialchars($formValues['date_of_bidding'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Post Qual.</span>
                            <input type="text" name="post_qual" value="<?php echo htmlspecialchars($formValues['post_qual'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Pending, date, or remarks">
                        </label>
                        <label class="is-wide">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <?php $selectedStatus = ($formValues['status'] !== '' ? $formValues['status'] : 'Pending') === $statusOption; ?>
                                    <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedStatus ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <span>NOA no.</span>
                            <input type="text" name="noa_no" value="<?php echo htmlspecialchars($formValues['noa_no'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Notice to Proceed Date</span>
                            <input type="date" name="notice_to_proceed_date" value="<?php echo htmlspecialchars($formValues['notice_to_proceed_date'], ENT_QUOTES, 'UTF-8'); ?>">
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
                            <span>Date of Bidding</span>
                            <input type="date" name="date_of_bidding" id="editBacDateOfBidding" value="<?php echo htmlspecialchars($showEditModal ? $formValues['date_of_bidding'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Post Qual.</span>
                            <input type="text" name="post_qual" id="editBacPostQual" value="<?php echo htmlspecialchars($showEditModal ? $formValues['post_qual'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Pending, date, or remarks">
                        </label>
                        <label class="is-wide">
                            <span>Status</span>
                            <select name="status" id="editBacStatus">
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <?php $selectedStatus = ($showEditModal ? $formValues['status'] : 'Pending') === $statusOption; ?>
                                    <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedStatus ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <span>NOA no.</span>
                            <input type="text" name="noa_no" id="editBacNoaNo" value="<?php echo htmlspecialchars($showEditModal ? $formValues['noa_no'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            <span>Notice to Proceed Date</span>
                            <input type="date" name="notice_to_proceed_date" id="editBacNoticeToProceedDate" value="<?php echo htmlspecialchars($showEditModal ? $formValues['notice_to_proceed_date'] : '', ENT_QUOTES, 'UTF-8'); ?>">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll(".bac-edit-row").forEach(function (button) {
            button.addEventListener("click", function () {
                document.getElementById("editBacId").value = button.dataset.id || "";
                document.getElementById("editBacIbNo").value = button.dataset.ibNo || "";
                document.getElementById("editBacProjectName").value = button.dataset.projectName || "";
                document.getElementById("editBacAbc").value = button.dataset.abc || "";
                document.getElementById("editBacFinalAmount").value = button.dataset.finalAmount || "";
                document.getElementById("editBacBidder").value = button.dataset.bidder || "";
                document.getElementById("editBacDateOfBidding").value = button.dataset.dateOfBidding || "";
                document.getElementById("editBacPostQual").value = button.dataset.postQual || "";
                document.getElementById("editBacStatus").value = button.dataset.status || "";
                document.getElementById("editBacDateTransmittedFromBac").value = button.dataset.dateTransmittedFromBac || "";
                document.getElementById("editBacOffice").value = button.dataset.office || "";
                document.getElementById("editBacNoaNo").value = button.dataset.noaNo || "";
                document.getElementById("editBacNoticeToProceedDate").value = button.dataset.noticeToProceedDate || "";
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
