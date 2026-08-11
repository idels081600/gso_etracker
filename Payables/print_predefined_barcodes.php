<?php
session_start();
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_document_tracking.php';

payables_ensure_document_barcodes_table();
$batchCode = trim($_GET['batch'] ?? '');

if ($batchCode === '') {
    http_response_code(422);
    echo 'Batch is required.';
    exit;
}

$stmt = $conn->prepare("SELECT barcode_code FROM payables_document_barcodes WHERE batch_code = ? ORDER BY id ASC LIMIT 120");
if (!$stmt) {
    http_response_code(500);
    echo 'Unable to load barcode batch.';
    exit;
}
$stmt->bind_param('s', $batchCode);
$stmt->execute();
$result = $stmt->get_result();
$labels = [];
while ($result && $row = $result->fetch_assoc()) {
    $labels[] = $row['barcode_code'];
}
$stmt->close();

if (!$labels) {
    http_response_code(404);
    echo 'No labels found for this batch.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Sticker Sheet <?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        @page { size: A4; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f6; color: #101828; font-family: Arial, sans-serif; }
        .print-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; }
        .print-actions strong { font-size: 14px; }
        .print-actions button { border: 0; border-radius: 8px; background: #20a797; color: #fff; cursor: pointer; font-weight: 800; padding: 9px 14px; }
        .sheet { width: 194mm; min-height: 281mm; margin: 0 auto 18px; display: grid; grid-template-columns: repeat(5, 1fr); align-content: start; gap: 3mm; background: #fff; padding: 4mm; }
        .label { height: 18mm; display: grid; align-content: center; justify-items: center; overflow: hidden; border: 1px dashed #d0d5dd; border-radius: 3px; padding: 1.6mm 1.2mm 1mm; break-inside: avoid; }
        .barcode { width: 100%; height: 10mm; }
        .code { margin-top: 1mm; font-family: "Courier New", monospace; font-size: 8px; font-weight: 900; letter-spacing: 0.2px; text-align: center; }
        .hint { color: #667085; font-size: 6.5px; font-weight: 700; text-align: center; }
        @media print {
            body { background: #fff; }
            .print-actions { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; gap: 2mm; }
            .label { border-color: #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <strong><?php echo htmlspecialchars($batchCode, ENT_QUOTES, 'UTF-8'); ?> - <?php echo count($labels); ?> sticker labels</strong>
        <button type="button" onclick="window.print()">Print Sticker Sheet</button>
    </div>
    <main class="sheet">
        <?php foreach ($labels as $code): ?>
            <section class="label">
                <div class="barcode" data-barcode-value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="code"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="hint">Register before use</div>
            </section>
        <?php endforeach; ?>
    </main>
    <script src="payables_code128.js"></script>
    <script>
        document.querySelectorAll('[data-barcode-value]').forEach(function (element) {
            window.PayablesCode128.render(element, element.dataset.barcodeValue, { height: 36, moduleWidth: 1, quiet: 4 });
        });
    </script>
</body>
</html>