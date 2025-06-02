<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Scanner - Inventory Deduction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .scanner-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        #video {
            width: 100%;
            max-width: 500px;
            height: auto;
            border: 2px solid #007bff;
            border-radius: 8px;
        }

        .scan-result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }

        .item-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 10px 0;
        }

        .scanner-status {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .transaction-history {
            max-height: 400px;
            overflow-y: auto;
        }

        .transaction-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .quantity-input {
            max-width: 120px;
        }

        .manual-input {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="scanner-container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-4">
                        <i class="fas fa-barcode"></i> Barcode Scanner - LogiSys - SAP Inventory System
                    </h2>
                </div>
            </div>

            <div class="row">
                <!-- Scanner Section -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-camera"></i> Scanner</h5>
                        </div>
                        <div class="card-body text-center">
                            <!-- Scanner Status -->
                            <div id="scannerStatus" class="scanner-status status-inactive">
                                Scanner Inactive
                            </div>

                            <!-- Video Element for Camera -->
                            <video id="video" style="display: none;"></video>

                            <!-- Scanner Controls -->
                            <div class="mb-3">
                                <button id="startScan" class="btn btn-success me-2">
                                    <i class="fas fa-play"></i> Start Scanner
                                </button>
                                <button id="stopScan" class="btn btn-danger" disabled>
                                    <i class="fas fa-stop"></i> Stop Scanner
                                </button>
                            </div>

                            <!-- Manual Barcode Input -->
                            <div class="manual-input">
                                <h6><i class="fas fa-keyboard"></i> Manual Input</h6>
                                <div class="input-group">
                                    <input type="text" id="manualBarcode" class="form-control" placeholder="Enter barcode manually">
                                    <button class="btn btn-primary" id="manualSubmit">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>

                            <!-- Last Scanned Result -->
                            <div id="scanResult" class="scan-result" style="display: none;">
                                <h6>Last Scanned:</h6>
                                <p id="scannedCode" class="mb-0"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Item Information and Deduction -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-box"></i> Item Information</h5>
                        </div>
                        <div class="card-body">
                            <!-- Item Details -->
                            <div id="itemInfo" style="display: none;">
                                <div class="item-info">
                                    <div class="row">
                                        <div class="col-6"><strong>Item Code:</strong></div>
                                        <div class="col-6" id="itemCode">-</div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6"><strong>Item Name:</strong></div>
                                        <div class="col-6" id="itemName">-</div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6"><strong>Current Stock:</strong></div>
                                        <div class="col-6" id="currentStock">-</div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6"><strong>Unit:</strong></div>
                                        <div class="col-6" id="itemUnit">-</div>
                                    </div>
                                </div>

                                <!-- Deduction Form -->
                                <div class="mt-3">
                                    <div class="row">
                                        <!-- Deduct Section -->
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-minus-circle text-danger"></i> Deduct Items</h6>
                                            <div class="row">
                                                <div class="col-8">
                                                    <label for="deductQuantity" class="form-label">Quantity to Deduct:</label>
                                                    <input type="number" id="deductQuantity" class="form-control quantity-input" min="1" value="1">
                                                </div>
                                                <div class="col-4 d-flex align-items-end">
                                                    <button id="deductBtn" class="btn btn-danger w-100">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <label for="deductRequestor" class="form-label">Requestor:</label>
                                                <input type="text" id="deductRequestor" class="form-control" placeholder="e.g., John Doe, IT Department">
                                            </div>
                                        </div>



                                        <!-- Add Section -->
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-plus-circle text-success"></i> Add Items</h6>
                                            <div class="row">
                                                <div class="col-8">
                                                    <label for="addQuantity" class="form-label">Quantity to Add:</label>
                                                    <input type="number" id="addQuantity" class="form-control quantity-input" min="1" value="1">
                                                </div>
                                                <div class="col-4 d-flex align-items-end">
                                                    <button id="addBtn" class="btn btn-success w-100">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <label for="addReference" class="form-label">PO No./IB No.:</label>
                                                <input type="text" id="addReference" class="form-control" placeholder="e.g., PO-2024-001 or IB-2024-001">
                                            </div>
                                        </div>

                                    </div>
                                </div>

                            </div>

                            <!-- No Item Message -->
                            <div id="noItemMessage" class="text-center text-muted">
                                <i class="fas fa-search fa-3x mb-3"></i>
                                <p>Scan or enter a barcode to view item details</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-history"></i> Transaction History</h5>
                            <button id="clearHistory" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i> Clear History
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="transactionHistory" class="transaction-history">
                                <div class="text-center text-muted">
                                    <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                                    <p>No transactions yet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle"></i> Success
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage">Operation completed successfully!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Error
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage">An error occurred!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <script src="Logi_scanner.js"></script>
</body>

</html>