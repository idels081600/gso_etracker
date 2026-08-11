<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_document_tracking.php';

payables_ensure_scan_events_table();

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'record_type' => trim($_GET['record_type'] ?? ''),
    'direction' => trim($_GET['direction'] ?? ''),
    'office' => trim($_GET['office'] ?? ''),
    'date' => trim($_GET['date'] ?? ''),
];
$scanEvents = payables_recent_scan_events($filters, 60);

function scanner_event_row(array $event): string
{
    $directionClass = $event['direction'] === 'IN' ? 'is-in' : 'is-out';
    ob_start();
    ?>
    <tr>
        <td><span class="scanner-type-badge"><?php echo htmlspecialchars($event['record_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
        <td class="scanner-doc-cell"><strong><?php echo htmlspecialchars($event['document_no'], ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></span></td>
        <td><span class="scanner-direction <?php echo $directionClass; ?>"><?php echo htmlspecialchars($event['direction'], ENT_QUOTES, 'UTF-8'); ?></span></td>
        <td><?php echo htmlspecialchars($event['office'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($event['scan_source'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($event['scanned_by'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($event['scanned_at'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php
    return trim(ob_get_clean());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="payables-csrf-token" content="<?php echo htmlspecialchars(payables_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="document_scanner.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Payables - Document Scanner</title>
</head>
<body class="document-scanner-page">
    <?php $payablesActivePage = 'document_scanner'; require 'payables_sidebar.php'; ?>
    <main class="scanner-content">
        <section class="scanner-shell" aria-label="Document barcode scanner">
            <div class="scanner-header">
                <div>
                    <span class="scanner-eyebrow">Routing Tracker</span>
                    <h1>Document Scanner</h1>
                </div>
                <div class="scanner-status" id="scannerStatus" role="status">Ready to scan</div>
            </div>

            <div class="scanner-grid">
                <section class="scanner-card scanner-control-card">
                    <div class="scanner-card-head">
                        <h2>Scan Document</h2>
                        <span>IB and RFQ barcodes</span>
                    </div>
                    <label class="scanner-bulk-toggle">
                        <input type="checkbox" id="bulkModeToggle">
                        <span>Bulk Scan Mode</span>
                    </label>
                    <div class="scanner-mode-group" aria-label="Scan direction">
                        <button type="button" class="is-active" data-scan-direction="IN"><i class="fas fa-sign-in-alt"></i> Scan In</button>
                        <button type="button" data-scan-direction="OUT"><i class="fas fa-sign-out-alt"></i> Scan Out</button>
                    </div>
                    <label class="scanner-field">
                        <span>Office</span>
                        <select id="scannerOffice">
                            <?php foreach (PAYABLES_SCAN_OFFICES as $office): ?>
                                <option value="<?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <form id="scannerForm" class="scanner-input-row">
                        <label>
                            <span>Barcode / Document No.</span>
                            <input type="text" id="scannerInput" placeholder="Scan or type IB/RFQ no." autocomplete="off" autofocus>
                        </label>
                        <button type="submit"><i class="fas fa-barcode"></i> Save Scan</button>
                    </form>
                    <div class="scanner-camera-actions">
                        <button type="button" id="toggleCameraBtn"><i class="fas fa-camera"></i> Open Camera Scanner</button>
                    </div>
                    <div class="scanner-camera-panel" id="cameraPanel" hidden>
                        <video id="scannerVideo" muted playsinline></video>
                        <div class="scanner-camera-line"></div>
                        <p id="cameraHelp">Point the camera at a Code 128 barcode.</p>
                    </div>
                    <div class="scanner-bulk-panel d-none" id="bulkPanel">
                        <div class="scanner-bulk-head">
                            <div>
                                <strong>Pending Batch</strong>
                                <span id="bulkCount">0 documents ready</span>
                            </div>
                            <div class="scanner-bulk-actions">
                                <button type="button" id="clearBulkBtn"><i class="fas fa-times"></i> Clear</button>
                                <button type="button" id="saveBulkBtn" disabled><i class="fas fa-save"></i> Save Batch</button>
                            </div>
                        </div>
                        <div class="scanner-bulk-list" id="bulkList">
                            <div class="scanner-bulk-empty">No documents in batch yet.</div>
                        </div>
                    </div>
                </section>

                <section class="scanner-card scanner-result-card" id="scanResultCard">
                    <div class="scanner-card-head">
                        <h2>Latest Scan</h2>
                        <span id="latestScanTime">No scan yet</span>
                    </div>
                    <div class="scanner-empty-result" id="emptyResult">
                        <i class="fas fa-barcode"></i>
                        <strong>Waiting for document</strong>
                        <span>Scan an IB or RFQ barcode to record routing.</span>
                    </div>
                    <div class="scanner-result d-none" id="scanResult">
                        <div><span>Type</span><strong data-result="record_type"></strong></div>
                        <div><span>Document No.</span><strong data-result="document_no"></strong></div>
                        <div><span>Direction</span><strong data-result="direction"></strong></div>
                        <div><span>Office</span><strong data-result="office"></strong></div>
                        <div class="is-wide"><span>Project / Description</span><strong data-result="title"></strong></div>
                        <div class="is-wide"><span>Scanned by</span><strong data-result="scanned_by"></strong></div>
                    </div>
                    <div class="scanner-choice-panel d-none" id="matchChoicePanel">
                        <strong>Multiple matches found</strong>
                        <span>Choose the document to save this scan.</span>
                        <div id="matchChoices"></div>
                    </div>
                </section>
            </div>

            <section class="scanner-card scanner-history-card">
                <div class="scanner-history-head">
                    <div>
                        <h2>Scan History</h2>
                        <span>Latest routing events</span>
                    </div>
                    <form class="scanner-filter-form" method="get">
                        <input type="search" name="search" placeholder="Search document, office, or user" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
                        <select name="record_type">
                            <option value="">All Types</option>
                            <option value="IB" <?php echo strtoupper($filters['record_type']) === 'IB' ? 'selected' : ''; ?>>IB</option>
                            <option value="RFQ" <?php echo strtoupper($filters['record_type']) === 'RFQ' ? 'selected' : ''; ?>>RFQ</option>
                        </select>
                        <select name="direction">
                            <option value="">All Directions</option>
                            <option value="IN" <?php echo strtoupper($filters['direction']) === 'IN' ? 'selected' : ''; ?>>IN</option>
                            <option value="OUT" <?php echo strtoupper($filters['direction']) === 'OUT' ? 'selected' : ''; ?>>OUT</option>
                        </select>
                        <select name="office">
                            <option value="">All Offices</option>
                            <?php foreach (PAYABLES_SCAN_OFFICES as $office): ?>
                                <option value="<?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($filters['office'], $office) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date" value="<?php echo htmlspecialchars(payables_normalize_date_or_empty($filters['date']), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                        <a href="document_scanner.php" aria-label="Clear filters"><i class="fas fa-times"></i></a>
                    </form>
                </div>
                <div class="scanner-table-wrap">
                    <table class="scanner-history-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Document</th>
                                <th>Direction</th>
                                <th>Office</th>
                                <th>Source</th>
                                <th>Scanned by</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody id="scanHistoryBody">
                            <?php if ($scanEvents): ?>
                                <?php foreach ($scanEvents as $event): ?>
                                    <?php echo scanner_event_row($event); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="scanner-empty-row"><td colspan="7">No scan history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
    <script>
        window.initialScannerOffice = "BAC";
    </script>
    <script src="document_scanner.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
