<?php
session_start();
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_document_tracking.php';

$recordType = strtoupper(trim($_GET['type'] ?? ''));
$recordId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$document = $recordId > 0 ? payables_get_document_by_record($recordType, $recordId) : null;

if (!$document) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$label = $recordType === 'IB' ? 'IB Document' : 'RFQ Document';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recordType . ' Barcode ' . $document['document_no'], ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { margin: 0; color: #101828; font-family: Arial, sans-serif; background: #f6f8fb; }
        .print-actions { padding: 16px; text-align: right; }
        .print-actions button { border: 0; border-radius: 8px; background: #20a797; color: #fff; cursor: pointer; font-weight: 800; padding: 9px 14px; }
        .barcode-sheet { display: grid; justify-content: center; padding: 12px; }
        .barcode-card { width: 520px; border: 1px solid #d9dee8; border-radius: 8px; background: #fff; padding: 22px; }
        .barcode-meta { display: flex; justify-content: space-between; gap: 18px; border-bottom: 1px solid #edf0f4; margin-bottom: 18px; padding-bottom: 12px; }
        .barcode-meta span { color: #667085; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .barcode-meta strong { display: block; margin-top: 4px; color: #101828; font-size: 18px; }
        .barcode-title { margin-bottom: 16px; }
        .barcode-title h1 { margin: 0 0 4px; color: #101828; font-size: 18px; }
        .barcode-title p { margin: 0; color: #475467; font-size: 12px; font-weight: 700; }
        .barcode-render { border: 1px solid #edf0f4; border-radius: 8px; background: #fff; padding: 16px; }
        .barcode-number { margin-top: 12px; color: #101828; font-family: "Courier New", monospace; font-size: 22px; font-weight: 900; letter-spacing: 1px; text-align: center; }
        @media print {
            body { background: #fff; }
            .print-actions { display: none; }
            .barcode-sheet { padding: 0; }
            .barcode-card { border-color: #101828; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print Barcode</button></div>
    <main class="barcode-sheet">
        <section class="barcode-card">
            <div class="barcode-meta">
                <div><span>Document Type</span><strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div><span>Code Value</span><strong><?php echo htmlspecialchars($document['document_no'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
            </div>
            <div class="barcode-title">
                <h1><?php echo htmlspecialchars($document['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars($document['party'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="barcode-render" data-barcode-value="<?php echo htmlspecialchars($document['document_no'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="barcode-number"><?php echo htmlspecialchars($document['document_no'], ENT_QUOTES, 'UTF-8'); ?></div>
        </section>
    </main>
    <script src="payables_code128.js"></script>
    <script>
        document.querySelectorAll("[data-barcode-value]").forEach(function (element) {
            window.PayablesCode128.render(element, element.dataset.barcodeValue, { height: 86, moduleWidth: 2 });
        });
    </script>
</body>
</html>
