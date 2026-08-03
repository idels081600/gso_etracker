<?php
require_once __DIR__ . '/page_bootstrap.php';
require_once __DIR__ . '/db_asset.php';
date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$query = "SELECT *
          FROM tent
          WHERE status IN ('Installed', 'For Retrieval')
            AND retrieval_date IS NOT NULL
            AND retrieval_date <= ?
          ORDER BY retrieval_date ASC, id ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 's', $weekEnd);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$pastDueRows = [];
$todayRows = [];
$weekendRows = [];
while ($result && ($row = mysqli_fetch_assoc($result))) {
    $retrievalDate = (string) ($row['retrieval_date'] ?? '');
    if ($retrievalDate < $today) {
        $pastDueRows[] = $row;
    } elseif ($retrievalDate === $today) {
        $todayRows[] = $row;
    } elseif (in_array((int) date('N', strtotime($retrievalDate)), [6, 7], true)) {
        $weekendRows[] = $row;
    }
}
mysqli_stmt_close($stmt);

function renderRetrievalRows(array $rows, string $emptyMessage): void
{
    if ($rows === []) {
        echo '<tr><td colspan="8" class="empty-row">' . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        return;
    }

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? '');
        $statusClass = $status === 'For Retrieval' ? 'is-retrieval' : 'is-installed';
        ?>
        <tr>
            <td><?= htmlspecialchars((string) $row['retrieval_date']) ?></td>
            <td><?= htmlspecialchars((string) $row['name']) ?></td>
            <td><?= htmlspecialchars((string) $row['Contact_no']) ?></td>
            <td><?= htmlspecialchars((string) $row['address']) ?></td>
            <td><?= htmlspecialchars((string) $row['location']) ?></td>
            <td><?= htmlspecialchars((string) $row['no_of_tents']) ?></td>
            <td><?= htmlspecialchars((string) $row['purpose']) ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
        </tr>
        <?php
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Tent Retrieval Schedule</title>
    <style>
        body {
            margin: 20px;
            color: #172033;
            font-family: Arial, sans-serif;
        }

        .header-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 2px solid #333;
        }

        .header-logo img {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border-radius: 50%;
            object-fit: cover;
        }

        .header-text {
            flex-grow: 1;
            text-align: center;
        }

        .header-text .tagbil {
            display: block;
            margin: 5px 0;
            color: #333;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .header-text .tagbil:first-child { font-size: 16px; }
        .header-text .tagbil:last-child { font-size: 20px; }

        h2 {
            margin: 22px 0 0;
            text-align: center;
        }

        .past-due-heading { color: #a61b24; }

        .report-section {
            margin-bottom: 34px;
            break-inside: avoid;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #333;
            text-align: center;
        }

        th {
            background: #f2f2f2;
            font-size: 13px;
        }

        td { font-size: 13px; }
        .past-due-table tbody tr { background: #fff2f2; }
        .empty-row { padding: 16px; color: #667085; }

        .status-badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-badge.is-installed {
            background: #dff5ed;
            color: #087d67;
        }

        .status-badge.is-retrieval {
            background: #fff0d5;
            color: #956100;
        }

        .print-btn {
            margin-bottom: 16px;
            padding: 10px 24px;
            border: 0;
            border-radius: 5px;
            background: #ffc107;
            color: #333;
            font-size: 16px;
            cursor: pointer;
        }

        .print-btn:hover { background: #e0a800; }

        .signature {
            width: 350px;
            margin: 60px 0 0 auto;
            text-align: center;
        }

        .signature strong {
            display: block;
            letter-spacing: 1px;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .page-break { page-break-before: always; }

        @media print {
            .print-btn { display: none; }
            body { margin: 12px; }
        }
    </style>
</head>
<body>
    <div class="header-logo">
        <img src="tagbi_seal.png" alt="Tagbilaran Seal">
        <div class="header-text">
            <span class="tagbil">Republic of the Philippines</span>
            <span class="tagbil">City Government of Tagbilaran</span>
        </div>
        <img src="logo.png" alt="General Services Office logo">
    </div>

    <button class="print-btn" type="button" onclick="window.print()">Print</button>

    <?php if ($pastDueRows !== []): ?>
        <section class="report-section">
            <h2 class="past-due-heading">PAST DUE TENT RETRIEVALS (<?= count($pastDueRows) ?>)</h2>
            <table class="past-due-table">
                <thead>
                    <tr>
                        <th>Retrieval Date</th>
                        <th>Name of Recipient</th>
                        <th>Contact No.</th>
                        <th>Address</th>
                        <th>Location</th>
                        <th>No. of Tents</th>
                        <th>Purpose</th>
                        <th>Current Status</th>
                    </tr>
                </thead>
                <tbody><?php renderRetrievalRows($pastDueRows, 'No past-due tent retrievals found.'); ?></tbody>
            </table>
        </section>
    <?php endif; ?>

    <section class="report-section">
        <h2>FOR RETRIEVAL TENT SCHEDULE FOR TODAY (<?= htmlspecialchars($today) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Retrieval Date</th>
                    <th>Name of Recipient</th>
                    <th>Contact No.</th>
                    <th>Address</th>
                    <th>Location</th>
                    <th>No. of Tents</th>
                    <th>Purpose</th>
                    <th>Current Status</th>
                </tr>
            </thead>
            <tbody><?php renderRetrievalRows($todayRows, 'No tents scheduled for retrieval today.'); ?></tbody>
        </table>
    </section>

    <div class="signature">
        <strong>Chris John Rener G. Torralba</strong>
        <span>CGSO HEAD</span>
    </div>

    <?php if ($weekendRows !== []): ?>
        <div class="page-break"></div>
        <div class="header-logo">
            <img src="tagbi_seal.png" alt="Tagbilaran Seal">
            <div class="header-text">
                <span class="tagbil">Republic of the Philippines</span>
                <span class="tagbil">City Government of Tagbilaran</span>
            </div>
            <img src="logo.png" alt="General Services Office logo">
        </div>

        <section class="report-section">
            <h2>FOR RETRIEVAL TENT SCHEDULE (SATURDAY &amp; SUNDAY)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Retrieval Date</th>
                        <th>Name of Recipient</th>
                        <th>Contact No.</th>
                        <th>Address</th>
                        <th>Location</th>
                        <th>No. of Tents</th>
                        <th>Purpose</th>
                        <th>Current Status</th>
                    </tr>
                </thead>
                <tbody><?php renderRetrievalRows($weekendRows, 'No weekend tent retrievals found.'); ?></tbody>
            </table>
        </section>

        <div class="signature">
            <strong>Chris John Rener G. Torralba</strong>
            <span>CGSO HEAD</span>
        </div>
    <?php endif; ?>
</body>
</html>