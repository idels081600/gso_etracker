<?php
require_once 'db_asset.php';

// Get today's date in Y-m-d format
$today = date('Y-m-d');

// Fetch all tents with status = 'Pending' and today's date
$query = "SELECT * FROM tent WHERE status = 'Pending' AND date = '$today' ORDER BY id DESC";
$result = mysqli_query($conn, $query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Tent Pending List</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
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
            button { display: none; }
        }
    </style>
</head>
<body>
    <h2>PENDING TENT SCHEDULE (<?= htmlspecialchars($today) ?>)</h2>
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
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['Contact_no']) ?></td>
                        <td><?= htmlspecialchars($row['no_of_tents']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No pending tents found for today.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div style="width: 100%; margin-top: 60px;">
        <div style="width: 350px; float: right; text-align: center;">
            <div style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-decoration: underline;">CHRIS JOHN RENER G. TORRALBA</div>
            <div style="font-size: 15px;">CGSO HEAD</div>
        </div>
    </div>
</body>
</html> 