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
    'bidder' => '',
    'date_of_bidding' => '',
    'post_qual' => '',
    'status' => '',
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
    if ($values['date_of_bidding'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $values['date_of_bidding']);
        if (!$date || $date->format('Y-m-d') !== $values['date_of_bidding']) {
            $errors[] = 'Date of bidding must be a valid date.';
        }
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
        'bidder' => trim($_POST['bidder'] ?? ''),
        'date_of_bidding' => trim($_POST['date_of_bidding'] ?? ''),
        'post_qual' => trim($_POST['post_qual'] ?? ''),
        'status' => trim($_POST['status'] ?? ''),
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
        $dateOfBidding = $formValues['date_of_bidding'] !== '' ? $formValues['date_of_bidding'] : null;

        if (!$formErrors && $action === 'add_bac_monitoring') {
            $stmt = $conn->prepare("
                INSERT INTO bac_monitoring (ib_no, project_name, abc, bidder, date_of_bidding, post_qual, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'ssdssss',
                    $formValues['ib_no'],
                    $formValues['project_name'],
                    $abc,
                    $formValues['bidder'],
                    $dateOfBidding,
                    $formValues['post_qual'],
                    $formValues['status']
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
                SET ib_no = ?, project_name = ?, abc = ?, bidder = ?, date_of_bidding = ?, post_qual = ?, status = ?
                WHERE id = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'ssdssssi',
                    $formValues['ib_no'],
                    $formValues['project_name'],
                    $abc,
                    $formValues['bidder'],
                    $dateOfBidding,
                    $formValues['post_qual'],
                    $formValues['status'],
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
    $where[] = "(ib_no LIKE ? OR project_name LIKE ? OR bidder LIKE ? OR CAST(abc AS CHAR) LIKE ? OR post_qual LIKE ? OR status LIKE ?)";
    $types .= 'ssssss';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
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

$buildPageUrl = function (int $page = 1) use ($searchTerm): string {
    $query = ['page' => $page];
    if ($searchTerm !== '') {
        $query['search'] = $searchTerm;
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
        bidder,
        date_of_bidding,
        post_qual,
        status
    FROM bac_monitoring
    WHERE {$whereSql}
    ORDER BY id DESC
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
                    <span class="bac-monitoring-eyebrow">BAC Monitoring</span>
                    <h1>Active BAC Documents</h1>
                    <p>Imported BAC monitoring records from the BAC monitoring table.</p>
                </div>
                <div class="bac-monitoring-actions">
                    <button type="button" class="bac-add-button" data-bs-toggle="modal" data-bs-target="#addBacMonitoringModal">
                        <i class="fas fa-plus"></i>
                        <span>Add Data</span>
                    </button>
                    <form class="bac-monitoring-search" method="get" role="search">
                        <input type="search" name="search" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search IB, project, bidder, ABC, or status" aria-label="Search BAC documents">
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

            <div class="bac-monitoring-summary">
                <span><?php echo number_format($totalRows); ?></span>
                <strong>not final</strong>
            </div>

            <div class="bac-monitoring-table" role="table" aria-label="BAC documents not final">
                <div class="bac-monitoring-row bac-monitoring-row-head" role="row">
                    <div>IB No.</div>
                    <div>Name of Project</div>
                    <div>ABC</div>
                    <div>Bidder</div>
                    <div>Date of Bidding</div>
                    <div>Post Qual.</div>
                    <div>Status</div>
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
                        ?>
                        <div class="bac-monitoring-row" role="row">
                            <div class="bac-ref"><?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-project"><?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bac-amount">&#8369;<?php echo number_format((float)($row['abc'] ?? 0), 2); ?></div>
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
                                    data-bidder="<?php echo htmlspecialchars($row['bidder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date-of-bidding="<?php echo htmlspecialchars($row['date_of_bidding'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-post-qual="<?php echo htmlspecialchars($row['post_qual'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
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
                            <input type="text" name="status" value="<?php echo htmlspecialchars($formValues['status'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Pending, Transmitted to GSO, etc.">
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
                            <input type="text" name="status" id="editBacStatus" value="<?php echo htmlspecialchars($showEditModal ? $formValues['status'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Pending, Transmitted to GSO, etc.">
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
                document.getElementById("editBacBidder").value = button.dataset.bidder || "";
                document.getElementById("editBacDateOfBidding").value = button.dataset.dateOfBidding || "";
                document.getElementById("editBacPostQual").value = button.dataset.postQual || "";
                document.getElementById("editBacStatus").value = button.dataset.status || "";
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
