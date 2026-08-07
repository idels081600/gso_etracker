<?php
session_start();
require_once 'auth_payables.php';
require_once 'transmit_db.php';

$rows = [];
$stmt = $conn->prepare("
    SELECT RFQ_no, supplier, description, amount, date_received, office, received_by, status
    FROM PO_sap
    WHERE delete_status = 0
        AND (status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) = 'pending')
    ORDER BY id DESC
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
    <title>Pending RFQ Receiving</title>
    <style>
        body { color: #101828; font-family: Arial, sans-serif; margin: 28px; }
        .print-header { display: flex; align-items: flex-end; justify-content: space-between; border-bottom: 2px solid #101828; margin-bottom: 18px; padding-bottom: 10px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .print-meta { color: #475467; font-size: 12px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #d0d5dd; padding: 7px 8px; text-align: left; vertical-align: top; }
        th { background: #f2f4f7; font-weight: 800; }
        .amount { text-align: right; white-space: nowrap; }
        .empty { padding: 24px; text-align: center; }
        .print-actions { margin-bottom: 14px; text-align: right; }
        button { background: #20a797; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        @media print { body { margin: 12mm; } .print-actions { display: none; } }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print</button></div>
    <div class="print-header">
        <div><h1>Pending RFQ Receiving</h1><div class="print-meta">PO SAP records with Pending status</div></div>
        <div class="print-meta"><?php echo date('M d, Y h:i A'); ?> | <?php echo number_format(count($rows)); ?> record(s)</div>
    </div>
    <table>
        <thead><tr><th>RFQ No.</th><th>Supplier</th><th>Description</th><th>Amount</th><th>Date Received</th><th>Office</th><th>Received by</th><th>Status</th></tr></thead>
        <tbody>
            <?php if ($rows): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['RFQ_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['supplier'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="amount">&#8369;<?php echo number_format((float)($row['amount'] ?? 0), 2); ?></td>
                        <td><?php echo htmlspecialchars(substr((string)($row['date_received'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['office'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['received_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(trim((string)($row['status'] ?? '')) !== '' ? $row['status'] : 'Pending', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td class="empty" colspan="8">No pending RFQ records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>