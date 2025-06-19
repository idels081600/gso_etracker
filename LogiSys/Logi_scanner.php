<?php
require_once 'logi_db.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner - LogiSys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <style>
        :root {
            --primary-color: #2196F3;
            --secondary-color: #1976D2;
            --success-color: #4CAF50;
            --danger-color: #f44336;
            --background-color: #f5f5f5;
            --card-background: #ffffff;
            --text-color: #333333;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .scanner-container {
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .scanner-video-container {
            position: relative;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            border-radius: var(--border-radius);
            overflow: hidden;
            background: #000;
            aspect-ratio: 4/3;
        }

        #scanner-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid var(--primary-color);
            border-radius: var(--border-radius);
            pointer-events: none;
        }

        .scanner-overlay::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 2px solid var(--primary-color);
            border-radius: 20px;
            animation: scan 2s linear infinite;
        }

        @keyframes scan {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }

            50% {
                transform: translate(-50%, -50%) scale(1.1);
                opacity: 0.5;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }

        .scanner-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #d32f2f;
            border-color: #d32f2f;
            transform: translateY(-2px);
        }

        .scanner-status {
            text-align: center;
            padding: 0.75rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .status-active {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .status-inactive {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }

        .manual-input-container {
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-control {
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(33, 150, 243, 0.25);
        }

        .item-info-container {
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .item-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }

        .info-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1.125rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .transaction-history {
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
        }

        .transaction-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .transaction-list::-webkit-scrollbar {
            width: 6px;
        }

        .transaction-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .transaction-list::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .transaction-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .transaction-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .transaction-item:hover {
            transform: translateX(5px);
            background: #e9ecef;
        }

        .transaction-type {
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .type-addition {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .type-deduction {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .scanner-video-container {
                aspect-ratio: 3/4;
            }

            .btn {
                padding: 0.5rem 1rem;
            }

            .item-info {
                grid-template-columns: 1fr;
            }
        }

        .action-card {
            border-radius: 18px;
            transition: box-shadow 0.2s, transform 0.2s;
            background: #fff;
        }

        .action-card:hover {
            box-shadow: 0 8px 24px rgba(33, 150, 243, 0.08), 0 1.5px 6px rgba(0, 0, 0, 0.06);
            transform: translateY(-4px) scale(1.02);
        }

        .add-action-card {
            border-left: 6px solid #4CAF50;
        }

        .deduct-action-card {
            border-left: 6px solid #f44336;
        }

        .action-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 60px;
            width: 60px;
            margin: 0 auto 1rem auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
        }

        .deduct-action-card .action-icon {
            background: linear-gradient(135deg, #ffebee 0%, #fbe9e7 100%);
        }

        @media (max-width: 767px) {
            .action-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-qrcode me-2"></i>
            QR Code Scanner
        </h1>

        <div class="row">
            <div class="col-lg-8">
                <div class="scanner-container">
                    <div class="scanner-video-container">
                        <video id="scanner-video" playsinline></video>
                        <div class="scanner-overlay"></div>
                    </div>

                    <div class="scanner-controls">
                        <button id="startBtn" class="btn btn-primary">
                            <i class="fas fa-play"></i>
                            Start Scanner
                        </button>
                        <button id="stopBtn" class="btn btn-danger" disabled>
                            <i class="fas fa-stop"></i>
                            Stop Scanner
                        </button>
                    </div>

                    <div id="scannerStatus" class="scanner-status status-inactive">
                        Scanner Inactive
                    </div>
                </div>

                <div class="manual-input-container">
                    <h5 class="mb-3">
                        <i class="fas fa-keyboard me-2"></i>
                        Manual QR Code Input
                    </h5>
                    <div class="input-group">
                        <input type="text" id="manualQRInput" class="form-control"
                            placeholder="Enter QR code or item number">
                        <button id="searchManualQR" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                </div>

                <div class="item-info-container">
                    <h5 class="mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Item Information
                    </h5>
                    <div id="itemInfo" class="item-info">
                        <div class="info-card">
                            <div class="info-label">Item Number</div>
                            <div id="itemNo" class="info-value">-</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Item Name</div>
                            <div id="itemName" class="info-value">-</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Current Balance</div>
                            <div id="currentBalance" class="info-value">-</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Unit</div>
                            <div id="itemUnit" class="info-value">-</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Rack Number</div>
                            <div id="rackNo" class="info-value">-</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Status</div>
                            <div id="itemStatus" class="info-value">-</div>
                        </div>
                    </div>
                </div>

                <!-- Modern Row Layout for Addition and Deduction Controls -->
                <div class="row justify-content-center my-4 g-4">
                    <div class="col-md-5">
                        <div class="card shadow-sm border-0 h-100 action-card add-action-card text-center p-4">
                            <div class="card-body d-flex flex-column h-100">
                                <div class="action-icon mb-3">
                                    <i class="fas fa-plus-circle fa-3x text-success"></i>
                                </div>
                                <h5 class="card-title mb-2">Add Item</h5>
                                <p class="card-text text-muted mb-4">Increase inventory by adding new items or restocking.</p>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="number" class="form-control" id="addCardQuantity" min="1" placeholder="How many to add?">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                                    <input type="text" class="form-control" id="addCardIBNo" placeholder="IB No.">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="fas fa-comment"></i></span>
                                    <input type="text" class="form-control" id="addCardReason" placeholder="Reason (optional)">
                                </div>
                                <button id="addItemBtn" class="btn btn-success btn-lg w-100 mt-auto"><i class="fas fa-plus"></i> Add Item</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card shadow-sm border-0 h-100 action-card deduct-action-card text-center p-4">
                            <div class="card-body d-flex flex-column h-100">
                                <div class="action-icon mb-3">
                                    <i class="fas fa-minus-circle fa-3x text-danger"></i>
                                </div>
                                <h5 class="card-title mb-2">Deduct Item</h5>
                                <p class="card-text text-muted mb-4">Decrease inventory by deducting items for use or transfer.</p>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="number" class="form-control" id="deductCardQuantity" min="1" placeholder="How many to deduct?">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="deductCardRequestor" placeholder="Requestor's name">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="fas fa-comment"></i></span>
                                    <input type="text" class="form-control" id="deductCardReason" placeholder="Reason (optional)">
                                </div>
                                <button id="deductItemBtn" class="btn btn-danger btn-lg w-100 mt-auto"><i class="fas fa-minus"></i> Deduct Item</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="transaction-history">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Recent Transactions
                        </h5>
                        <button id="clearHistoryBtn" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-trash"></i>
                            Clear
                        </button>
                    </div>
                    <div id="transactionList" class="transaction-list">
                        <!-- Transactions will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Success
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                        Error
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Add/Deduct Item -->
    <div class="modal fade" id="itemActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center" id="itemActionModalTitle">
                        <span id="modalActionIcon" class="me-2"></span>
                        <span id="modalActionText"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="itemActionForm">
                        <div class="mb-3">
                            <label for="actionQuantity" class="form-label">How many?</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="number" class="form-control" id="actionQuantity" min="1" placeholder="Enter quantity" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="actionRequestor" class="form-label">Requestor</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="actionRequestor" placeholder="Enter requestor's name" required>
                            </div>
                        </div>
                        <div class="mb-3" id="actionIBNoContainer" style="display:none;">
                            <label for="actionIBNo" class="form-label">IB No.</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                                <input type="text" class="form-control" id="actionIBNo" placeholder="Enter IB No.">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="actionReason" class="form-label">Reason (optional)</label>
                            <input type="text" class="form-control" id="actionReason" placeholder="Enter reason (optional)">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="Logi_scanner.js"></script>

 
</body>

</html>