<?php
require_once 'logi_db.php';

// Get all inventory items
$query = "SELECT item_no, item_name FROM inventory_items ORDER BY item_name";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory QR Code Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .qr-container {
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
            page-break-inside: avoid;
        }
        .qr-code {
            width: 150px;
            height: 150px;
            border: 1px solid #ddd;
            padding: 5px;
            background: white;
            border-radius: 4px;
            margin: 0 auto;
        }
        .item-name {
            margin-top: 8px;
            font-size: 13px;
            color: #333;
            font-weight: 500;
            text-align: center;
            line-height: 1.2;
            height: 32px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .item-no {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            text-align: center;
        }
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 15px;
        }
        @media (max-width: 768px) {
            .qr-grid {
                grid-template-columns: 1fr;
            }
        }
        .page-header {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Print Styles */
        @media print {
            @page {
                size: Long;
                margin: 1cm;
            }
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .qr-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0 !important;
                padding: 0;
                margin: 0;
            }
            .qr-container {
                box-shadow: none;
                border: 1px solid #000;
                margin: 0;
                padding: 5px;
                page-break-inside: avoid;
                break-inside: avoid;
                border-radius: 0;
            }
            .qr-code {
                width: 140px;
                height: 140px;
                border: 1px solid #000;
                padding: 3px;
                border-radius: 0;
            }
            .item-name {
                font-size: 12px;
                color: black;
                height: 28px;
                margin-top: 3px;
            }
            .item-no {
                font-size: 10px;
                color: #333;
                margin-top: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="page-header no-print">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col">
                        <h2 class="mb-0">Inventory QR Code Generator</h2>
                    </div>
                    <div class="col-auto">
                        <div class="d-flex gap-2">
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print QR Codes
                            </button>
                            <a href="Logi_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="qr-grid">
                <?php
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $itemNo = $row['item_no'];
                        $itemName = htmlspecialchars($row['item_name']);
                        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($itemNo);
                        ?>
                        <div class="qr-container">
                            <img src="<?php echo $qrUrl; ?>" 
                                 class="qr-code" 
                                 alt="QR Code for <?php echo $itemName; ?>"
                                 onerror="this.onerror=null; this.src='https://chart.googleapis.com/chart?cht=qr&chs=150x150&chl=<?php echo urlencode($itemNo); ?>&choe=UTF-8';">
                            <div class="item-name"><?php echo $itemName; ?></div>
                            <div class="item-no"><?php echo $itemNo; ?></div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='col'><p>No items found in inventory.</p></div>";
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html> 