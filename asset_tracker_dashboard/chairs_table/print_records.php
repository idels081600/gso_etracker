<?php
require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/ui_helpers.php';
require_once __DIR__ . '/equipment_helpers.php';

asset_require_auth();
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, ['Pending', 'For Retrieval'], true)) {
    http_response_code(400);
    exit('Invalid print status.');
}
$deployments = get_deployments_with_items($status);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($status); ?> Chairs & Table Deployments</title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:28px;font:12px Arial,sans-serif;color:#182230}
        header{display:flex;align-items:center;gap:16px;border-bottom:3px solid #157a6e;padding-bottom:14px;margin-bottom:20px}
        header img{width:58px;height:58px;object-fit:contain}h1{font-size:21px;margin:0 0 3px}p{margin:0;color:#667085}
        table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #d9dee4;text-align:left;vertical-align:top}
        th{background:#eef8f6;color:#155f56;font-size:10px;text-transform:uppercase}.items{margin:0;padding-left:16px}
        .print-actions{display:flex;justify-content:flex-end;margin-bottom:14px}button{padding:8px 14px;border:0;border-radius:5px;background:#157a6e;color:#fff;font-weight:700}
        @media print{body{padding:0}.print-actions{display:none}}@page{size:landscape;margin:12mm}
    </style>
</head>
<body>
<div class="print-actions"><button type="button" onclick="window.print()">Print records</button></div>
<header>
    <img src="../tagbi_seal.png" alt="City seal">
    <div><h1><?php echo e($status); ?> Chairs & Table Deployments</h1><p>Generated <?php echo e(date('F j, Y g:i A')); ?></p></div>
</header>
<table>
    <thead><tr><th>ID</th><th>Requestor</th><th>Contact</th><th>Equipment</th><th>Location</th><th>Purpose</th><th>Installed</th><th>Retrieval</th></tr></thead>
    <tbody>
    <?php if ($deployments === []): ?>
        <tr><td colspan="8">No <?php echo e(strtolower($status)); ?> records found.</td></tr>
    <?php else: foreach ($deployments as $deployment): ?>
        <tr>
            <td>#<?php echo (int) $deployment['id']; ?></td>
            <td><?php echo e($deployment['name']); ?></td>
            <td><?php echo e($deployment['contact_no']); ?></td>
            <td><ul class="items"><?php foreach ($deployment['items'] as $item): ?><li><?php echo e($item['display_name']); ?> x <?php echo (int) $item['quantity']; ?></li><?php endforeach; ?></ul></td>
            <td><?php echo e($deployment['location']); ?><br><?php echo e($deployment['address']); ?></td>
            <td><?php echo e($deployment['purpose']); ?></td>
            <td><?php echo e($deployment['date']); ?></td>
            <td><?php echo e($deployment['retrieval_date']); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</body>
</html>
