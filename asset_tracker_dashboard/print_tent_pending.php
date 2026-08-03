<?php
require_once __DIR__ . '/page_bootstrap.php';
require_once __DIR__ . '/db_asset.php';
date_default_timezone_set('Asia/Manila');

// Include every unresolved pending installation through the end of this week.
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$query = "SELECT * FROM tent WHERE status = 'Pending' AND date <= ? ORDER BY date ASC, id ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 's', $week_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$past_due_rows = [];
$today_rows = [];
$weekday_rows = [];
$weekend_rows = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row_date = $row['date'];
        if ($row_date < $today) {
            $past_due_rows[] = $row;
        } elseif ($row_date === $today) {
            $today_rows[] = $row;
        } elseif ($row_date >= $week_start && $row_date <= $week_end) {
            $dow = date('N', strtotime($row_date)); // 6=Sat, 7=Sun
            if ($dow == 6 || $dow == 7) {
                $weekend_rows[] = $row;
            } else {
                $weekday_rows[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Tent Pending List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .header-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 2px solid #333;
            gap: 20px;
        }

        .header-logo img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .header-text {
            text-align: center;
            flex-grow: 1;
        }

        .header-text .tagbil {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 5px 0;
        }

        .header-text .tagbil:first-child {
            font-size: 16px;
        }

        .header-text .tagbil:last-child {
            font-size: 20px;
        }

        h2 {
            text-align: center;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
        }

        th {
            background: #f2f2f2;
        }

        .past-due-heading {
            color: #a61b24;
        }

        .past-due-table tbody tr {
            background: #fff2f2;
        }

        .report-section {
            break-inside: avoid;
            margin-bottom: 34px;
        }

        .print-btn {
            background: #28a745;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 10px 24px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 16px;
            transition: background 0.2s;
        }

        .print-btn:hover {
            background: #218838;
        }

        @media print {
            button {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Header with Logo for First Page -->
    <div class="header-logo">
        <img src="tagbi_seal.png" alt="Tagbilaran Seal">
        <div class="header-text">
            <span class="tagbil">Republic of the Philippines</span>
            <span class="tagbil">City Government of Tagbilaran</span>
        </div>
        <img src="logo.png" alt="Logo">
    </div>

    <button class="print-btn" onclick="window.print()">Print</button>

    <?php if (count($past_due_rows) > 0): ?>
        <section class="report-section">
            <h2 class="past-due-heading">PAST DUE PENDING TENT INSTALLATIONS (<?= count($past_due_rows) ?>)</h2>
            <table class="past-due-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name of Recipient</th>
                        <th>Contact No.</th>
                        <th>Address</th>
                        <th>Location</th>
                        <th>No. of Tents</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($past_due_rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['Contact_no']) ?></td>
                            <td><?= htmlspecialchars($row['address']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= htmlspecialchars($row['no_of_tents']) ?></td>
                            <td><?= htmlspecialchars($row['purpose']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <section class="report-section">
    <h2>PENDING TENT SCHEDULE FOR TODAY (<?= htmlspecialchars($today) ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Name of Recipient</th>
                <th>Contact No.</th>
                <th>Address</th>
                <th>Location</th>
                <th>No. of Tents</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($today_rows) > 0): ?>
                <?php foreach ($today_rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['Contact_no']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><?= htmlspecialchars($row['no_of_tents']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No pending tents found for today.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </section>
    <div style="width: 100%; margin-top: 60px;">
        <div style="width: 350px; float: right; text-align: center;">
            <div style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-decoration: underline;">CHRIS JOHN RENER G. TORRALBA</div>
            <div style="font-size: 15px;">CGSO HEAD</div>
        </div>
    </div>

    <?php if (count($weekend_rows) > 0): ?>
        <div style="page-break-before: always;"></div>

        <!-- Header with Logo for Second Page -->
        <div class="header-logo">
            <img src="tagbi_seal.png" alt="Tagbilaran Seal">
            <div class="header-text">
                <span class="tagbil">Republic of the Philippines</span>
                <span class="tagbil">City Government of Tagbilaran</span>
            </div>
            <img src="logo.png" alt="Logo">
        </div>

        <h2>PENDING TENT SCHEDULE (SATURDAY & SUNDAY)</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name of Recipient</th>
                    <th>Contact No.</th>
                    <th>Address</th>
                    <th>Location</th>
                    <th>No. of Tents</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weekend_rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['Contact_no']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><?= htmlspecialchars($row['no_of_tents']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="width: 100%; margin-top: 60px;">
            <div style="width: 350px; float: right; text-align: center;">
                <div style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-decoration: underline;">CHRIS JOHN RENER G. TORRALBA</div>
                <div style="font-size: 15px;">CGSO HEAD</div>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>
