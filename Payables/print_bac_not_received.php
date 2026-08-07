<?php
session_start();
require_once 'auth_payables.php';
require_once 'transmit_db.php';

function print_date_value($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y', $timestamp) : $value;
}

$rows = [];
$stmt = $conn->prepare("
    SELECT ib_no, project_name, abc, final_amount, bidder,
        pre_procurement, pre_bid, date_of_bidding, evaluation, post_qual, status,
        date_transmitted_from_bac, office, noa_no, notice_to_proceed_date, contract_date,
        calendar_days_delivery, deadline, received_by
    FROM bac_monitoring
    WHERE LOWER(TRIM(COALESCE(status, ''))) = 'not yet received'
        OR TRIM(COALESCE(received_by, '')) = ''
    ORDER BY date_transmitted_from_bac IS NULL ASC, date_transmitted_from_bac DESC, id DESC
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result ? $result->fetch_assoc() : null) {
        $rows[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BAC Not Yet Received</title>
    <style>
        @page { size: legal landscape; margin: 10mm; }
        body { color: #101828; font-family: Arial, sans-serif; margin: 24px; }
        .print-header { display: flex; align-items: flex-end; justify-content: space-between; border-bottom: 2px solid #101828; margin-bottom: 14px; padding-bottom: 10px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .print-meta { color: #475467; font-size: 12px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; table-layout: fixed; }
        th, td { border: 1px solid #d0d5dd; padding: 5px 6px; text-align: left; vertical-align: top; word-break: break-word; }
        th { background: #f2f4f7; font-weight: 800; }
        .amount { text-align: right; white-space: nowrap; }
        .empty { padding: 24px; text-align: center; }
        .print-actions { margin-bottom: 14px; text-align: right; }
        button { background: #20a797; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        .ib { width: 6%; }
        .project { width: 13%; }
        .money { width: 7%; }
        .bidder { width: 11%; }
        .date { width: 6%; }
        .status { width: 7%; }
        .small { width: 5%; }
        @media print { body { margin: 0; } .print-actions { display: none; } }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print</button></div>
    <div class="print-header">
        <div><h1>BAC Monitoring - Not Yet Received</h1><div class="print-meta">All BAC monitoring records not yet received</div></div>
        <div class="print-meta"><?php echo date('M d, Y h:i A'); ?> | <?php echo number_format(count($rows)); ?> record(s)</div>
    </div>
    <table>
        <thead>
            <tr>
                <th class="ib">IB No.</th>
                <th class="project">Name of Project</th>
                <th class="money">ABC</th>
                <th class="money">Final Amount</th>
                <th class="bidder">Bidder</th>
                <th class="date">Pre Procurement</th>
                <th class="date">Pre Bid</th>
                <th class="date">Bidding</th>
                <th class="date">Evaluation</th>
                <th class="date">Post Qual.</th>
                <th class="status">Status</th>
                <th class="date">Date Transmitted</th>
                <th class="small">Office</th>
                <th class="date">NOA Date</th>
                <th class="date">NTP Date</th>
                <th class="date">Contract Date</th>
                <th class="small">Calendar Days</th>
                <th class="date">Deadline</th>
                <th class="small">Received by</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows): ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $displayStatus = trim((string)($row['status'] ?? '')) !== '' ? $row['status'] : 'Not Yet Received';
                    if (trim((string)($row['received_by'] ?? '')) === '') {
                        $displayStatus = 'Not Yet Received';
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="amount">&#8369;<?php echo number_format((float)($row['abc'] ?? 0), 2); ?></td>
                        <td class="amount">&#8369;<?php echo number_format((float)($row['final_amount'] ?? 0), 2); ?></td>
                        <td><?php echo htmlspecialchars($row['bidder'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['pre_procurement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['pre_bid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['date_of_bidding'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['evaluation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['post_qual'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['date_transmitted_from_bac'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['office'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['noa_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['notice_to_proceed_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(print_date_value($row['contract_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['calendar_days_delivery'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['deadline'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['received_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td class="empty" colspan="19">No not-yet-received BAC monitoring records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>