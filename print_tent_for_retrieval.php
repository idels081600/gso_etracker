<?php
require_once 'db_asset.php';

// Fetch all tents with status = 'For Retrieval'
$query = "SELECT * FROM tent WHERE status = 'For Retrieval' ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Fetch weekend tents with status = 'Installed'
$weekend_query = "SELECT * FROM tent WHERE status = 'Installed' ORDER BY id DESC";
$weekend_result = mysqli_query($conn, $weekend_query);

// Separate entries for Sat/Sun of current week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$sat = date('Y-m-d', strtotime('saturday this week'));
$sun = date('Y-m-d', strtotime('sunday this week'));

$weekday_rows = [];
$weekend_rows = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row_date = $row['retrieval_date'];
        if ($row_date >= $week_start && $row_date <= $week_end) {
            $dow = date('N', strtotime($row_date)); // 6=Sat, 7=Sun
            if ($dow == 6 || $dow == 7) {
                $weekend_rows[] = $row;
            } else {
                $weekday_rows[] = $row;
            }
        } else {
            $weekday_rows[] = $row; // Out-of-week entries go to main
        }
    }
}

// Add weekend entries with status = 'Installed'
if ($weekend_result && mysqli_num_rows($weekend_result) > 0) {
    while ($row = mysqli_fetch_assoc($weekend_result)) {
        $row_date = $row['retrieval_date'];
        if ($row_date >= $week_start && $row_date <= $week_end) {
            $dow = date('N', strtotime($row_date)); // 6=Sat, 7=Sun
            if ($dow == 6 || $dow == 7) {
                $weekend_rows[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Tent For Retrieval List</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
        .print-btn {
            background: #ffc107;
            color: #333;
            border: none;
            border-radius: 5px;
            padding: 10px 24px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 16px;
            transition: background 0.2s;
        }
        .print-btn:hover {
            background: #e0a800;
        }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>
    <h2>FOR RETRIEVAL TENT SCHEDULE</h2>
    <button class="print-btn" onclick="window.print()">Print</button>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Contact Number</th>
                <th>No. of Tents</th>
                <th>Purpose</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($weekday_rows) > 0): ?>
                <?php foreach ($weekday_rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['Contact_no']) ?></td>
                        <td><?= htmlspecialchars($row['no_of_tents']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">No tents for retrieval found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div style="width: 100%; margin-top: 60px;">
        <div style="width: 350px; float: right; text-align: center;">
            <div style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-decoration: underline;">CHRIS JOHN RENER G. TORRALBA</div>
            <div style="font-size: 15px;">CGSO HEAD</div>
        </div>
    </div>
    <?php if (count($weekend_rows) > 0): ?>
        <div style="page-break-before: always;"></div>
        <h2>FOR RETRIEVAL TENT SCHEDULE (SATURDAY & SUNDAY)</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Contact Number</th>
                    <th>No. of Tents</th>
                    <th>Purpose</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weekend_rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['Contact_no']) ?></td>
                        <td><?= htmlspecialchars($row['no_of_tents']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
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