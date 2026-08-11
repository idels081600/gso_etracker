<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_document_tracking.php';

payables_ensure_document_barcodes_table();

$notice = '';
$error = '';
$generatedBatch = trim($_GET['batch'] ?? '');

function barcode_label_redirect(array $params = []): void
{
    $query = $params ? '?' . http_build_query($params) : '';
    header('Location: barcode_labels.php' . $query);
    exit;
}

function barcode_label_document_options(mysqli $conn): array
{
    $options = [];
    $ibResult = $conn->query("SELECT id, ib_no AS document_no, project_name AS title, bidder AS party FROM bac_monitoring ORDER BY ib_no DESC, id DESC LIMIT 700");
    while ($ibResult && $row = $ibResult->fetch_assoc()) {
        $options[] = [
            'value' => 'IB:' . (int)$row['id'],
            'type' => 'IB',
            'number' => $row['document_no'] ?? '',
            'title' => $row['title'] ?? '',
            'party' => $row['party'] ?? '',
        ];
    }

    $rfqResult = $conn->query("SELECT id, RFQ_no AS document_no, description AS title, supplier AS party FROM PO_sap WHERE delete_status = 0 ORDER BY RFQ_no DESC, id DESC LIMIT 700");
    while ($rfqResult && $row = $rfqResult->fetch_assoc()) {
        $options[] = [
            'value' => 'RFQ:' . (int)$row['id'],
            'type' => 'RFQ',
            'number' => $row['document_no'] ?? '',
            'title' => $row['title'] ?? '',
            'party' => $row['party'] ?? '',
        ];
    }

    return $options;
}

function barcode_label_document_label(array $option): string
{
    return trim(($option['type'] ?? '') . ' ' . ($option['number'] ?? '') . ' - ' . ($option['title'] ?? ''));
}

function barcode_label_recent(mysqli $conn): array
{
    payables_ensure_document_barcodes_table();
    $result = $conn->query("
        SELECT bl.*,
               COALESCE(b.ib_no, r.RFQ_no, '') AS document_no,
               COALESCE(b.project_name, r.description, '') AS title,
               COALESCE(b.bidder, r.supplier, '') AS party
        FROM payables_document_barcodes bl
        LEFT JOIN bac_monitoring b
            ON bl.record_type = 'IB'
           AND bl.record_id = b.id
        LEFT JOIN PO_sap r
            ON bl.record_type = 'RFQ'
           AND bl.record_id = r.id
        ORDER BY bl.id DESC
        LIMIT 80
    ");

    $rows = [];
    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $postedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['_payables_csrf_token'] ?? '';
    if (!$postedToken || !$sessionToken || !hash_equals($sessionToken, $postedToken)) {
        $error = 'Security token expired. Please refresh and try again.';
    } elseif ($action === 'generate_labels') {
        $count = filter_input(INPUT_POST, 'label_count', FILTER_VALIDATE_INT) ?: 0;
        $count = max(1, min(120, $count));
        $batchCode = 'LBL-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $createdBy = $full_name;
        $stmt = $conn->prepare("INSERT INTO payables_document_barcodes (barcode_code, batch_code, created_by) VALUES (?, ?, ?)");
        if (!$stmt) {
            $error = 'Unable to generate labels right now.';
            payables_log_error('Barcode label generate prepare failed: ' . $conn->error);
        } else {
            $created = 0;
            $attempts = 0;
            while ($created < $count && $attempts < ($count * 8)) {
                $attempts++;
                $barcodeCode = 'B' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $stmt->bind_param('sss', $barcodeCode, $batchCode, $createdBy);
                if ($stmt->execute()) {
                    $created++;
                }
            }
            $stmt->close();
            barcode_label_redirect(['batch' => $batchCode, 'generated' => $created]);
        }
    } elseif ($action === 'assign_label') {
        $barcodeCode = payables_normalize_barcode_code($_POST['barcode_code'] ?? '');
        $documentValue = trim($_POST['document_ref'] ?? '');
        $documentSearch = trim($_POST['document_search'] ?? '');
        if ($documentValue === '' && $documentSearch !== '') {
            foreach (barcode_label_document_options($conn) as $option) {
                if (strcasecmp($documentSearch, barcode_label_document_label($option)) === 0) {
                    $documentValue = $option['value'];
                    break;
                }
            }
        }
        [$recordType, $recordIdText] = array_pad(explode(':', $documentValue, 2), 2, '');
        $recordType = strtoupper(trim($recordType));
        $recordId = (int)$recordIdText;

        if ($barcodeCode === '') {
            $error = 'Scan or enter a sticker barcode.';
        } elseif (!in_array($recordType, ['IB', 'RFQ'], true) || $recordId < 1) {
            $error = 'Choose the IB or RFQ document for this sticker.';
        } elseif (!payables_get_document_by_record($recordType, $recordId)) {
            $error = 'Selected document was not found.';
        } else {
            $stmt = $conn->prepare("SELECT id, record_type, record_id FROM payables_document_barcodes WHERE barcode_code = ? LIMIT 1");
            if (!$stmt) {
                $error = 'Unable to check the sticker right now.';
                payables_log_error('Barcode label lookup prepare failed: ' . $conn->error);
            } else {
                $stmt->bind_param('s', $barcodeCode);
                $stmt->execute();
                $existing = $stmt->get_result();
                $row = $existing ? $existing->fetch_assoc() : null;
                $stmt->close();

                if (!$row) {
                    $createdBy = $full_name;
                    $batchCode = 'MANUAL';
                    $insert = $conn->prepare("INSERT INTO payables_document_barcodes (barcode_code, batch_code, created_by) VALUES (?, ?, ?)");
                    if ($insert) {
                        $insert->bind_param('sss', $barcodeCode, $batchCode, $createdBy);
                        $insert->execute();
                        $insert->close();
                    }
                } elseif (!empty($row['record_type']) && (int)$row['record_id'] > 0 && ($row['record_type'] !== $recordType || (int)$row['record_id'] !== $recordId)) {
                    $error = 'This sticker is already assigned to another document.';
                }

                if ($error === '') {
                    $assignedBy = $full_name;
                    $update = $conn->prepare("UPDATE payables_document_barcodes SET record_type = ?, record_id = ?, assigned_by = ?, assigned_at = NOW() WHERE barcode_code = ? LIMIT 1");
                    if (!$update) {
                        $error = 'Unable to assign the sticker right now.';
                        payables_log_error('Barcode label assign prepare failed: ' . $conn->error);
                    } else {
                        $update->bind_param('siss', $recordType, $recordId, $assignedBy, $barcodeCode);
                        if (!$update->execute()) {
                            $error = 'Unable to assign the sticker right now.';
                            payables_log_error('Barcode label assign failed: ' . $update->error);
                        }
                        $update->close();
                        if ($error === '') {
                            barcode_label_redirect(['assigned' => $barcodeCode]);
                        }
                    }
                }
            }
        }
    } elseif ($action === 'unassign_label') {
        $barcodeId = filter_input(INPUT_POST, 'barcode_id', FILTER_VALIDATE_INT) ?: 0;
        if ($barcodeId > 0) {
            $stmt = $conn->prepare("UPDATE payables_document_barcodes SET record_type = NULL, record_id = NULL, assigned_by = NULL, assigned_at = NULL WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $barcodeId);
                $stmt->execute();
                $stmt->close();
                barcode_label_redirect(['unassigned' => 1]);
            }
        }
        $error = 'Unable to unassign this sticker.';
    }
}

if (isset($_GET['generated'])) {
    $notice = (int)$_GET['generated'] . ' sticker barcodes generated.';
} elseif (isset($_GET['assigned'])) {
    $notice = 'Sticker ' . htmlspecialchars($_GET['assigned'], ENT_QUOTES, 'UTF-8') . ' assigned.';
} elseif (isset($_GET['unassigned'])) {
    $notice = 'Sticker assignment removed.';
}

$documentOptions = barcode_label_document_options($conn);
$recentLabels = barcode_label_recent($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="payables-csrf-token" content="<?php echo htmlspecialchars(payables_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="barcode_labels.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Payables - Barcode Labels</title>
</head>
<body class="barcode-labels-page">
    <?php $payablesActivePage = 'barcode_labels'; require 'payables_sidebar.php'; ?>
    <main class="barcode-labels-content">
        <section class="barcode-labels-shell">
            <div class="barcode-labels-header">
                <div>
                    <span>Sticker Registry</span>
                    <h1>Barcode Labels</h1>
                </div>
                <?php if ($generatedBatch !== ''): ?>
                    <a class="barcode-print-link" href="print_predefined_barcodes.php?batch=<?php echo urlencode($generatedBatch); ?>" target="_blank" rel="noopener"><i class="fas fa-print"></i> Print Latest Batch</a>
                <?php endif; ?>
            </div>

            <?php if ($notice !== ''): ?><div class="barcode-alert is-success"><?php echo $notice; ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="barcode-alert is-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

            <div class="barcode-grid">
                <section class="barcode-card">
                    <div class="barcode-card-head">
                        <h2>Generate Sticker Sheet</h2>
                        <span>Create blank stickers first, then register each sticker later.</span>
                    </div>
                    <form method="post" class="barcode-form">
                        <?php echo payables_csrf_input(); ?>
                        <input type="hidden" name="action" value="generate_labels">
                        <label><span>Number of stickers</span><input type="number" name="label_count" min="1" max="120" value="65" required></label>
                        <button type="submit"><i class="fas fa-qrcode"></i> Generate Sheet</button>
                    </form>
                </section>

                <section class="barcode-card">
                    <div class="barcode-card-head">
                        <h2>Register Sticker</h2>
                        <span>Scan a sticker, then choose its IB or RFQ document.</span>
                    </div>
                    <form method="post" class="barcode-form">
                        <?php echo payables_csrf_input(); ?>
                        <input type="hidden" name="action" value="assign_label">
                        <label><span>Sticker barcode</span><input type="text" name="barcode_code" placeholder="Scan sticker barcode" autocomplete="off" autofocus required></label>
                        <label>
                            <span>Document</span>
                            <input type="text" name="document_search" list="barcodeDocumentSuggestions" placeholder="Type IB/RFQ no., project, or supplier" autocomplete="off" required>
                            <input type="hidden" name="document_ref" id="documentRefValue">
                            <datalist id="barcodeDocumentSuggestions">
                                <?php foreach ($documentOptions as $option): ?>
                                    <?php $optionLabel = barcode_label_document_label($option); ?>
                                    <option value="<?php echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?php echo htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                        <button type="submit"><i class="fas fa-link"></i> Register Barcode</button>
                    </form>
                </section>
            </div>

            <section class="barcode-card barcode-table-card">
                <div class="barcode-card-head">
                    <h2>Recent Sticker Labels</h2>
                    <span>Assigned and available sticker barcodes.</span>
                </div>
                <div class="barcode-table-wrap">
                    <table class="barcode-table">
                        <thead><tr><th>Sticker Code</th><th>Status</th><th>Document</th><th>Batch</th><th>Assigned By</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if ($recentLabels): ?>
                                <?php foreach ($recentLabels as $label): ?>
                                    <?php $isAssigned = !empty($label['record_type']) && (int)$label['record_id'] > 0; ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($label['barcode_code'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                        <td><span class="barcode-status <?php echo $isAssigned ? 'is-assigned' : 'is-open'; ?>"><?php echo $isAssigned ? 'Registered' : 'Available'; ?></span></td>
                                        <td><?php if ($isAssigned): ?><strong><?php echo htmlspecialchars($label['record_type'] . ' ' . $label['document_no'], ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo htmlspecialchars($label['title'], ENT_QUOTES, 'UTF-8'); ?></span><?php else: ?>-<?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($label['batch_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($label['assigned_by'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php if ($isAssigned): ?><form method="post" class="barcode-inline-form"><?php echo payables_csrf_input(); ?><input type="hidden" name="action" value="unassign_label"><input type="hidden" name="barcode_id" value="<?php echo (int)$label['id']; ?>"><button type="submit" title="Unassign"><i class="fas fa-unlink"></i></button></form><?php else: ?><a href="print_predefined_barcodes.php?batch=<?php echo urlencode($label['batch_code'] ?? ''); ?>" target="_blank" rel="noopener" title="Print batch"><i class="fas fa-print"></i></a><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="barcode-empty">No sticker labels generated yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
    <script src="barcode-label-document-picker.js"></script>
</body>
</html>
