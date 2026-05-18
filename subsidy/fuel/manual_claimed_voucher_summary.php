<?php
session_start();
require_once 'db_fuel.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

if (!isset($_SESSION['station_id']) || empty($_SESSION['station_id'])) {
    $username = mysqli_real_escape_string($conn, $_SESSION['username']);
    $check_sql = "SELECT us.station_id, gs.station_name
                  FROM user_stations us
                  JOIN gas_stations gs ON us.station_id = gs.id
                  WHERE us.username = '$username'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $station_data = mysqli_fetch_assoc($check_result);
        $_SESSION['station_id'] = $station_data['station_id'];
        $_SESSION['station_name'] = $station_data['station_name'];
    } else {
        header("Location: select_station.php");
        exit();
    }
}

$station_name = isset($_SESSION['station_name']) ? $_SESSION['station_name'] : 'Unknown Station';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Claimed Voucher Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <style>
        :root {
            --surface: #f6f8fb;
            --ink: #172033;
            --muted: #667085;
            --line: #d9e1ec;
            --brand: #0f766e;
            --brand-dark: #115e59;
            --danger: #b42318;
        }

        body {
            min-height: 100vh;
            background: var(--surface);
            color: var(--ink);
        }

        .app-shell {
            padding-top: 72px;
        }

        .page-band {
            background: #ffffff;
            border-bottom: 1px solid var(--line);
        }

        .station-pill {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: .45rem .8rem;
            color: var(--muted);
            background: #fff;
            max-width: 100%;
        }

        .work-panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .panel-title {
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .search-wrap {
            position: relative;
        }

        .suggestion-menu {
            position: absolute;
            inset: calc(100% + 4px) 0 auto 0;
            z-index: 1050;
            max-height: 480px;
            overflow-y: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
        }

        .suggestion-item {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #eef2f7;
            background: #fff;
            padding: .85rem 1rem;
            text-align: left;
        }

        .suggestion-item:hover,
        .suggestion-item:focus {
            background: #ecfdf5;
        }

        .suggestion-item:last-child {
            border-bottom: 0;
        }

        .selected-count {
            min-width: 2.4rem;
            height: 2.4rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #ecfdf5;
            color: var(--brand-dark);
            font-weight: 700;
        }

        .btn-brand {
            --bs-btn-color: #fff;
            --bs-btn-bg: var(--brand);
            --bs-btn-border-color: var(--brand);
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: var(--brand-dark);
            --bs-btn-hover-border-color: var(--brand-dark);
        }

        .table thead th {
            color: var(--muted);
            font-size: .76rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .order-cell {
            width: 3rem;
            text-align: center;
            font-weight: 700;
            color: var(--brand-dark);
        }

        .empty-state {
            border: 1px dashed var(--line);
            border-radius: 8px;
            background: #fbfcfe;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(86px, 1fr));
            gap: .5rem;
        }

        .voucher-btn {
            min-height: 42px;
            font-weight: 700;
        }

        .voucher-btn.is-selected {
            --bs-btn-color: #fff;
            --bs-btn-bg: #198754;
            --bs-btn-border-color: #198754;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: #157347;
            --bs-btn-hover-border-color: #146c43;
        }

        .draft-bar {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 0.65rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: #92400e;
        }

        .draft-bar .btn-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.6rem;
        }

        .total-strip {
            border-top: 1px solid var(--line);
            background: #fbfcfe;
        }

        @media (max-width: 767.98px) {
            .app-shell {
                padding-top: 64px;
            }

            .table-action-label {
                display: none;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar bg-white fixed-top border-bottom">
        <div class="container-fluid">
            <a class="navbar-brand fw-semibold" href="dashboard_fuel.php">Fuel Subsidy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Fuel Subsidy Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard_fuel.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="releasing_fuel.php"><i class="bi bi-fuel-pump me-2"></i>Releasing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="manual_claimed_voucher_summary.php"><i class="bi bi-list-ol me-2"></i>Manual Export</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="select_station.php"><i class="bi bi-arrow-repeat me-2"></i>Change Gas Station</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="app-shell">
        <section class="page-band">
            <div class="container py-4">
                <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-end justify-content-between">
                    <div>
                        <p class="panel-title mb-2">Manual Claimed Voucher Export</p>
                        <h1 class="h3 mb-2">Select tricycle numbers for PDF export</h1>
                        <div class="station-pill">
                            <i class="bi bi-geo-alt"></i>
                            <span class="text-truncate"><?php echo htmlspecialchars($station_name); ?></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="dashboard_fuel.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Dashboard
                        </a>
                        <button type="button" class="btn btn-brand" id="exportManualBtnTop">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                        </button>
                    </div>
                </div>
                <!-- Draft restore bar (hidden initially) -->
                <div id="draftRestoreBar" class="draft-bar mt-3 d-none">
                    <i class="bi bi-save2"></i>
                    <span class="flex-grow-1">A draft was found from your previous session.</span>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="restoreDraftBtn">Restore</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="discardDraftBtn">Discard</button>
                </div>
            </div>
        </section>

        <section class="container py-4">
            <div class="row g-4">
                <div class="col-12 col-xl-4">
                    <div class="work-panel p-3 p-md-4 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <p class="panel-title mb-1">Add Entry</p>
                                <h2 class="h5 mb-0">Tricycle vouchers</h2>
                            </div>
                            <i class="bi bi-search h4 mb-0 text-secondary"></i>
                        </div>

                        <label for="manualSearch" class="form-label fw-semibold">Search tricycle number</label>
                        <div class="search-wrap mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input id="manualSearch" type="text" class="form-control" placeholder="Example: 0105" autocomplete="off">
                                <button class="btn btn-brand" type="button" id="addSelectedBtn">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    <span class="table-action-label ms-1">Load</span>
                                </button>
                            </div>
                            <div id="searchDropdown" class="suggestion-menu d-none"></div>
                        </div>

                        <div class="mb-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Fuel Pump Prices</label>
                                <div class="vstack gap-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input class="form-check-input mt-0" type="radio" name="groupFuelType" value="Silver" id="fuelSilver" checked>
                                        </div>
                                        <label class="input-group-text" for="fuelSilver">Silver</label>
                                        <span class="input-group-text">PHP</span>
                                        <input type="number" min="0" step="0.01" class="form-control fuel-price-input" data-fuel="Silver" id="priceSilver" placeholder="0.00">
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input class="form-check-input mt-0" type="radio" name="groupFuelType" value="Platinum" id="fuelPlatinum">
                                        </div>
                                        <label class="input-group-text" for="fuelPlatinum">Platinum</label>
                                        <span class="input-group-text">PHP</span>
                                        <input type="number" min="0" step="0.01" class="form-control fuel-price-input" data-fuel="Platinum" id="pricePlatinum" placeholder="0.00">
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input class="form-check-input mt-0" type="radio" name="groupFuelType" value="Diesel" id="fuelDiesel">
                                        </div>
                                        <label class="input-group-text" for="fuelDiesel">Diesel</label>
                                        <span class="input-group-text">PHP</span>
                                        <input type="number" min="0" step="0.01" class="form-control fuel-price-input" data-fuel="Diesel" id="priceDiesel" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="groupLiters" class="form-label fw-semibold">Actual Liters For This Voucher Group</label>
                                <div class="input-group">
                                    <input type="number" min="0" step="0.01" class="form-control" id="groupLiters" placeholder="Example: 3.11">
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="groupAmount" class="form-label fw-semibold">Manual Amount For This Voucher Group</label>
                                <div class="input-group">
                                    <span class="input-group-text">PHP</span>
                                    <input type="number" min="0" step="0.01" class="form-control" id="groupAmount" placeholder="Example: 400.00">
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-semibold mb-0">Claimed Vouchers</label>
                                <span class="small text-muted" id="loadedTricycleLabel"></span>
                            </div>
                            <div id="voucherList" class="empty-state text-center text-muted py-4 px-3">
                                Enter a tricycle number to show claimed vouchers.
                            </div>
                        </div>

                        <div class="alert alert-light border small mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            Set pump price, choose the fuel, enter actual liters and the manual amount for the selected voucher group, then click vouchers.
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label for="startDate" class="form-label fw-semibold">Start Date</label>
                                <input type="date" class="form-control" id="startDate">
                            </div>
                            <div class="col-6">
                                <label for="endDate" class="form-label fw-semibold">End Date</label>
                                <input type="date" class="form-control" id="endDate">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-8">
                    <div class="work-panel">
                        <div class="p-3 p-md-4 border-bottom">
                            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
                                <div class="d-flex gap-3 align-items-center">
                                    <span class="selected-count" id="selectedCount">0</span>
                                    <div>
                                        <p class="panel-title mb-1">Export Order</p>
                                        <h2 class="h5 mb-0">Selected tricycles</h2>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" id="openBatchModalBtn">
                                        <i class="bi bi-folder2-open me-1"></i>Select Batch
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="clearListBtn">
                                        <i class="bi bi-x-lg me-1"></i>Clear
                                    </button>
                                    <button type="button" class="btn btn-danger" id="exportManualBtn">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 p-md-4">
                            <div id="emptyState" class="empty-state text-center text-muted py-5">
                                <i class="bi bi-list-check h2 d-block mb-2"></i>
                                Click claimed voucher numbers to build the exact order for the manual PDF.
                            </div>

                            <div id="selectedTableWrap" class="table-responsive d-none">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="order-cell">Order</th>
                                            <th>Tricycle No.</th>
                                            <th>Voucher No.</th>
                                            <th>Driver</th>
                                            <th>Type</th>
                                            <th>Group Liters</th>
                                            <th>Pump Price</th>
                                            <th>Amount</th>
                                            <th class="text-center">Move</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="selectedTable"></tbody>
                                </table>
                                <div id="selectedTotals" class="total-strip d-flex flex-column flex-md-row gap-2 justify-content-md-end align-items-md-center px-3 py-3 text-end">
                                    <span class="text-muted small" id="selectedTotalGroups">0 selected groups</span>
                                    <span class="fw-semibold">Total Amount: PHP <span id="selectedTotalAmount">0</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <form id="exportForm" action="export_manual_claimed_vouchers.php" method="POST" target="_blank" class="d-none">
        <input type="hidden" name="selected_tricycles" id="selectedTricyclesInput">
        <input type="hidden" name="selected_entries" id="selectedEntriesInput">
        <input type="hidden" name="pump_price" id="pumpPriceInput">
        <input type="hidden" name="start_date" id="startDateInput">
        <input type="hidden" name="end_date" id="endDateInput">
        <input type="hidden" name="station_id" value="<?php echo (int)$_SESSION['station_id']; ?>">
    </form>

    <div class="modal fade" id="batchModal" tabindex="-1" aria-labelledby="batchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="panel-title mb-1">Saved Export Batches</p>
                        <h5 class="modal-title" id="batchModalLabel">Select created selection batch</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="batchList" class="vstack gap-2">
                        <div class="text-muted text-center py-4">Loading batches...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // ========== DRAFT – localStorage key ==========
        const DRAFT_KEY = 'manual_claimed_voucher_draft';

        // ========== DOM refs ==========
        const manualSearch = document.getElementById('manualSearch');
        const searchDropdown = document.getElementById('searchDropdown');
        const voucherList = document.getElementById('voucherList');
        const loadedTricycleLabel = document.getElementById('loadedTricycleLabel');
        const fuelTypeRadios = Array.from(document.querySelectorAll('input[name="groupFuelType"]'));
        const fuelPriceInputs = Array.from(document.querySelectorAll('.fuel-price-input'));
        const groupLiters = document.getElementById('groupLiters');
        const groupAmount = document.getElementById('groupAmount');
        const selectedTable = document.getElementById('selectedTable');
        const selectedTableWrap = document.getElementById('selectedTableWrap');
        const emptyState = document.getElementById('emptyState');
        const selectedCount = document.getElementById('selectedCount');
        const selectedTotalGroups = document.getElementById('selectedTotalGroups');
        const selectedTotalAmount = document.getElementById('selectedTotalAmount');
        const batchList = document.getElementById('batchList');
        const selected = [];
        const voucherButtons = new Map();
        let currentTricycleNo = '';
        let chosenSuggestion = null;
        let searchTimer = null;

        // ========== Draft helpers ==========
        function saveDraft(keepalive = false) {
            const draft = {
                selected: selected.map(function(item) {
                    return {
                        tricycle_no: item.tricycle_no,
                        voucher_number: item.voucher_number,
                        driver_name: item.driver_name,
                        fuel_type: item.fuel_type,
                        liters: item.liters,
                        pump_price: item.pump_price,
                        amount: item.amount,
                        claim_date: item.claim_date || ''
                    };
                }),
                fuel_prices: {},
                fuel_type: getSelectedFuelType(),
                group_liters: groupLiters.value,
                group_amount: groupAmount.value,
                search_value: manualSearch.value,
                start_date: document.getElementById('startDate').value,
                end_date: document.getElementById('endDate').value
            };
            fuelPriceInputs.forEach(function(input) {
                draft.fuel_prices[input.dataset.fuel] = input.value;
            });
            try {
                localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
                saveDraftToDatabase(draft, keepalive);
            } catch (e) {
                // storage full – ignore
            }
        }
        function saveDraftToDatabase(draft, keepalive = false) {
            return fetch('api_manual_voucher_draft.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ draft: draft }),
                keepalive: keepalive
            }).catch(function() {
                // localStorage remains the immediate fallback if the backup save fails
            });
        }

        function deleteDatabaseDraft() {
            return fetch('api_manual_voucher_draft.php?action=delete', {
                method: 'POST'
            }).catch(function() {
                // local draft is still removed
            });
        }

        async function loadDatabaseDraft() {
            try {
                const response = await fetch('api_manual_voucher_draft.php?action=load');
                const data = await response.json();
                if (data.success && data.draft) {
                    return data.draft;
                }
            } catch (e) {
                // ignore; local draft may still be available
            }
            return null;
        }

        function loadDraft() {
            var raw;
            try {
                raw = localStorage.getItem(DRAFT_KEY);
            } catch (e) {
                return null;
            }
            if (!raw) return null;
            try {
                return JSON.parse(raw);
            } catch (e) {
                return null;
            }
        }

        function clearDraft() {
            try {
                localStorage.removeItem(DRAFT_KEY);
            } catch (e) { /* ignore */ }
            deleteDatabaseDraft();
        }

        function hasDraft() {
            return loadDraft() !== null;
        }

        // ========== Draft restore bar ==========
        var draftRestoreBar = document.getElementById('draftRestoreBar');

        function showDraftRestoreBar() {
            if (hasDraft()) {
                draftRestoreBar.classList.remove('d-none');
            }
        }

        function hideDraftRestoreBar() {
            draftRestoreBar.classList.add('d-none');
        }

        async function initializeDraftBackup() {
            const localDraft = loadDraft();
            if (localDraft) {
                showDraftRestoreBar();
                saveDraftToDatabase(localDraft);
                return;
            }

            const databaseDraft = await loadDatabaseDraft();
            if (!databaseDraft) return;

            try {
                localStorage.setItem(DRAFT_KEY, JSON.stringify(databaseDraft));
            } catch (e) { /* ignore */ }
            showDraftRestoreBar();
        }

        document.getElementById('restoreDraftBtn').addEventListener('click', function() {
            var draft = loadDraft();
            if (!draft) return;
            hideDraftRestoreBar();
            // Restore fuel prices
            fuelPriceInputs.forEach(function(input) {
                if (draft.fuel_prices && draft.fuel_prices[input.dataset.fuel] !== undefined) {
                    input.value = draft.fuel_prices[input.dataset.fuel];
                }
            });
            // Restore fuel type radio
            if (draft.fuel_type) {
                var radio = fuelTypeRadios.find(function(r) { return r.value === draft.fuel_type; });
                if (radio) radio.checked = true;
            }
            // Restore group liters
            if (draft.group_liters !== undefined && draft.group_liters !== null) {
                groupLiters.value = draft.group_liters;
            }
            if (draft.group_amount !== undefined && draft.group_amount !== null) {
                groupAmount.value = draft.group_amount;
            }
            // Restore dates
            if (draft.start_date) document.getElementById('startDate').value = draft.start_date;
            if (draft.end_date) document.getElementById('endDate').value = draft.end_date;
            // Restore search
            if (draft.search_value) manualSearch.value = draft.search_value;
            // Restore selected entries
            if (draft.selected && Array.isArray(draft.selected)) {
                selected.splice(0, selected.length);
                draft.selected.forEach(function(item) {
                    selected.push({
                        tricycle_no: item.tricycle_no,
                        voucher_number: item.voucher_number,
                        driver_name: item.driver_name || 'Manual entry',
                        fuel_type: item.fuel_type || getSelectedFuelType(),
                        liters: item.liters || '',
                        pump_price: item.pump_price || getFuelPrice(item.fuel_type || getSelectedFuelType()),
                        amount: item.amount || '',
                        claim_date: item.claim_date || ''
                    });
                });
                currentTricycleNo = selected.length > 0 ? selected[0].tricycle_no : '';
                renderSelected();
                syncVoucherButtons();
            }
            // Save current state as draft
            saveDraft();
        });

        document.getElementById('discardDraftBtn').addEventListener('click', function() {
            clearDraft();
            hideDraftRestoreBar();
        });

        // ========== Auto-save draft on change (debounced) ==========
        var draftSaveTimer = null;

        function autoSaveDraft() {
            clearTimeout(draftSaveTimer);
            draftSaveTimer = setTimeout(saveDraft, 400);
        }

        function saveDraftNow() {
            clearTimeout(draftSaveTimer);
            saveDraft();
        }

        // Watch all relevant inputs for changes
        manualSearch.addEventListener('input', autoSaveDraft);
        fuelTypeRadios.forEach(function(radio) {
            radio.addEventListener('change', autoSaveDraft);
        });
        fuelPriceInputs.forEach(function(input) {
            input.addEventListener('input', autoSaveDraft);
        });
        groupLiters.addEventListener('input', autoSaveDraft);
        groupAmount.addEventListener('input', autoSaveDraft);
        document.getElementById('startDate').addEventListener('change', autoSaveDraft);
        document.getElementById('endDate').addEventListener('change', autoSaveDraft);
        document.getElementById('startDate').addEventListener('change', renderSelected);
        document.getElementById('endDate').addEventListener('change', renderSelected);

        // ========== Original code (unchanged below) ==========

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, (char) => ({
                '&': '&',
                '<': '<',
                '>': '>',
                '"': '"',
                "'": '&#039;'
            }[char]));
        }

        function setDropdown(html) {
            searchDropdown.innerHTML = html;
            searchDropdown.classList.toggle('d-none', html === '');
        }

        function restoreSelectedEntries(entries, dates = {}) {
            selected.splice(0, selected.length);
            entries.forEach((item) => {
                selected.push({
                    tricycle_no: item.tricycle_no || '',
                    voucher_number: normalizeVoucher(item.voucher_number),
                    driver_name: item.driver_name || 'Manual entry',
                    fuel_type: item.fuel_type || getSelectedFuelType(),
                    liters: item.liters || '',
                    pump_price: item.pump_price || getFuelPrice(item.fuel_type || getSelectedFuelType()),
                    claim_date: item.claim_date || ''
                });
            });

            if (dates.start_date) document.getElementById('startDate').value = dates.start_date;
            if (dates.end_date) document.getElementById('endDate').value = dates.end_date;

            currentTricycleNo = selected.length > 0 ? selected[0].tricycle_no : '';
            renderSelected();
            syncVoucherButtons();
            saveDraftNow();
        }

        function renderBatchList(batches) {
            if (!batches || batches.length === 0) {
                batchList.innerHTML = '<div class="empty-state text-center text-muted py-4 px-3">No saved batches found.</div>';
                return;
            }

            batchList.innerHTML = '';
            batches.forEach((batch) => {
                const item = document.createElement('div');
                item.className = 'border rounded-2 p-3 d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between';
                item.innerHTML = `
                    <div>
                        <div class="fw-semibold">${escapeHtml(batch.export_code)}</div>
                        <div class="small text-muted">${escapeHtml(batch.date_range_text)} · ${escapeHtml(batch.created_at)}</div>
                        <div class="small">PHP ${escapeHtml(formatRoundedAmount(batch.total_amount))} · ${escapeHtml(Number(batch.total_liters || 0).toFixed(2))} L · ${escapeHtml(batch.total_vouchers)} voucher(s)</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-brand" data-batch-id="${batch.id}">Load</button>
                `;
                batchList.appendChild(item);
            });
        }

        async function loadBatchList() {
            batchList.innerHTML = '<div class="text-muted text-center py-4">Loading batches...</div>';
            try {
                const response = await fetch('api_manual_claimed_voucher_batches.php?action=list');
                const data = await response.json();
                if (!data.success) {
                    batchList.innerHTML = `<div class="text-danger text-center py-4">${escapeHtml(data.message || 'Unable to load batches.')}</div>`;
                    return;
                }
                renderBatchList(data.batches);
            } catch (error) {
                batchList.innerHTML = '<div class="text-danger text-center py-4">Unable to load batches.</div>';
            }
        }

        async function loadBatch(batchId) {
            try {
                const response = await fetch(`api_manual_claimed_voucher_batches.php?action=load&id=${encodeURIComponent(batchId)}`);
                const data = await response.json();
                if (!data.success || !data.batch) {
                    alert(data.message || 'Unable to load this batch.');
                    return;
                }

                restoreSelectedEntries(data.batch.selected_entries || [], {
                    start_date: data.batch.start_date || '',
                    end_date: data.batch.end_date || ''
                });
                bootstrap.Modal.getOrCreateInstance(document.getElementById('batchModal')).hide();
            } catch (error) {
                alert('Unable to load this batch.');
            }
        }

        function normalizeVoucher(value) {
            const trimmed = String(value || '').trim();
            if (trimmed === '') return '';
            const numberValue = parseInt(trimmed, 10);
            return Number.isNaN(numberValue) ? trimmed : String(numberValue).padStart(3, '0');
        }

        function normalizeClaimDate(value) {
            return String(value || '').slice(0, 10);
        }

        function formatRoundedAmount(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function selectedAmount(item) {
            return Number(item.amount || 0);
        }

        function isWithinSelectedDateRange(item) {
            const claimDate = normalizeClaimDate(item.claim_date);
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            if (!claimDate) return true;
            if (startDate && claimDate < startDate) return false;
            if (endDate && claimDate > endDate) return false;
            return true;
        }

        function getExportGroups() {
            const groups = new Map();

            selected.forEach((item) => {
                if (!isWithinSelectedDateRange(item)) return;

                const key = [
                    item.tricycle_no,
                    item.fuel_type,
                    Number(item.pump_price || 0).toFixed(2),
                    Number(item.liters || 0).toFixed(2),
                    Number(item.amount || 0).toFixed(2),
                    normalizeClaimDate(item.claim_date)
                ].join('|');

                if (!groups.has(key)) {
                    groups.set(key, selectedAmount(item));
                }
            });

            return groups;
        }

        function getSelectedGroups() {
            const groups = new Map();

            selected.forEach((item) => {
                const key = [
                    item.tricycle_no,
                    item.fuel_type,
                    Number(item.pump_price || 0).toFixed(2),
                    Number(item.liters || 0).toFixed(2),
                    Number(item.amount || 0).toFixed(2)
                ].join('|');

                if (!groups.has(key)) {
                    groups.set(key, selectedAmount(item));
                }
            });

            return groups;
        }

        function updateSelectedTotals() {
            if (!selectedTotalGroups || !selectedTotalAmount) return;

            const groups = getSelectedGroups();
            let total = 0;
            groups.forEach((amount) => {
                total += amount;
            });

            selectedTotalGroups.textContent = `${groups.size} selected group${groups.size === 1 ? '' : 's'}`;
            selectedTotalAmount.textContent = formatRoundedAmount(total);
        }

        function getSelectedFuelType() {
            const selectedRadio = fuelTypeRadios.find((radio) => radio.checked);
            return selectedRadio ? selectedRadio.value : 'Silver';
        }

        function getFuelPrice(fuelType = getSelectedFuelType()) {
            const priceInput = fuelPriceInputs.find((input) => input.dataset.fuel === fuelType);
            return priceInput ? priceInput.value : '';
        }

        function setSelectedFuelType(fuelType) {
            const radio = fuelTypeRadios.find((item) => item.value === fuelType);
            if (radio) {
                radio.checked = true;
            }
        }

        async function loadSuggestions(query) {
            if (query.length < 2) {
                setDropdown('');
                return;
            }

            setDropdown('<div class="text-muted text-center py-3 small">Searching...</div>');

            try {
                const response = await fetch(`api_search_suggestions.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (!data.success || data.results.length === 0) {
                    setDropdown('<div class="text-muted text-center py-3 small">No matches found.</div>');
                    return;
                }

                searchDropdown.innerHTML = '';
                searchDropdown.classList.remove('d-none');
                data.results.forEach((item) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'suggestion-item';
                    option.innerHTML = `
                        <span class="d-flex justify-content-between gap-3 align-items-center">
                            <span>
                                <strong>${escapeHtml(item.tricycle_no)}</strong>
                                <span class="d-block small text-muted">${escapeHtml(item.driver_name)}</span>
                            </span>
                            <span class="badge text-bg-light">${escapeHtml(item.remaining)} left</span>
                        </span>
                    `;
                    option.addEventListener('click', () => {
                        chosenSuggestion = item;
                        manualSearch.value = item.tricycle_no;
                        setDropdown('');
                        loadClaimedVouchers(item);
                    });
                    searchDropdown.appendChild(option);
                });
            } catch (error) {
                setDropdown('<div class="text-danger text-center py-3 small">Unable to load tricycle results.</div>');
            }
        }

        async function loadClaimedVouchers(item) {
            const tricycleNo = (item.tricycle_no || '').trim();
            if (!tricycleNo) {
                alert('Please enter a tricycle number.');
                return;
            }

            loadedTricycleLabel.textContent = tricycleNo;
            currentTricycleNo = tricycleNo;
            voucherList.className = 'empty-state text-center text-muted py-4 px-3';
            voucherList.textContent = 'Loading claimed vouchers...';

            try {
                const response = await fetch(`api_get_claims.php?tricycle_no=${encodeURIComponent(tricycleNo)}`);
                const data = await response.json();

                if (!data.success || !data.data || !Array.isArray(data.data.claims_data) || data.data.claims_data.length === 0) {
                    voucherList.className = 'empty-state text-center text-muted py-4 px-3';
                    voucherList.textContent = 'No claimed vouchers found for this tricycle.';
                    return;
                }

                const tricycle = data.data;
                const existingGroup = selected.find((item) => item.tricycle_no.toLowerCase() === tricycle.tricycle_no.toLowerCase());
                if (existingGroup) {
                    setSelectedFuelType(existingGroup.fuel_type);
                    groupLiters.value = existingGroup.liters;
                    groupAmount.value = existingGroup.amount || '';
                    const priceInput = fuelPriceInputs.find((input) => input.dataset.fuel === existingGroup.fuel_type);
                    if (priceInput && priceInput.value === '') {
                        priceInput.value = existingGroup.pump_price;
                    }
                } else {
                    groupLiters.value = '';
                    groupAmount.value = '';
                }

                const claims = tricycle.claims_data;
                voucherList.className = 'voucher-grid';
                voucherList.innerHTML = '';
                voucherButtons.clear();

                claims.forEach((claim) => {
                    const voucherNumber = normalizeVoucher(claim.voucher_number);
                    const buttonKey = `${tricycle.tricycle_no.toLowerCase()}|${voucherNumber}`;
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-success voucher-btn';
                    button.textContent = voucherNumber;
                    button.title = claim.claim_date ? `Claimed ${claim.claim_date}` : 'Claimed voucher';
                    button.dataset.key = buttonKey;
                    button.addEventListener('click', () => {
                        const existingIndex = selected.findIndex((item) => `${item.tricycle_no.toLowerCase()}|${item.voucher_number}` === buttonKey);
                        if (existingIndex !== -1) {
                            selected.splice(existingIndex, 1);
                            renderSelected();
                            syncVoucherButtons();
                            saveDraftNow();
                            return;
                        }

                        const fuelType = getSelectedFuelType();
                        const pumpPrice = getFuelPrice(fuelType);
                        const liters = groupLiters.value;
                        const amount = groupAmount.value;
                        if (Number(pumpPrice) <= 0) {
                            alert(`Please enter the ${fuelType} pump price before selecting vouchers.`);
                            const priceInput = fuelPriceInputs.find((input) => input.dataset.fuel === fuelType);
                            if (priceInput) priceInput.focus();
                            return;
                        }
                        if (Number(liters) <= 0) {
                            alert('Please enter the actual liters for this voucher group before selecting vouchers.');
                            groupLiters.focus();
                            return;
                        }
                        if (Number(amount) <= 0) {
                            alert('Please enter the manual amount for this voucher group before selecting vouchers.');
                            groupAmount.focus();
                            return;
                        }

                        addSelection({
                            tricycle_no: tricycle.tricycle_no,
                            voucher_number: voucherNumber,
                            driver_name: tricycle.driver_name,
                            remaining: Math.max(0, Number(tricycle.total_vouchers || 0) - Number(tricycle.claimed_vouchers || 0)),
                            fuel_type: fuelType,
                            liters: liters,
                            pump_price: pumpPrice,
                            amount: amount,
                            claim_date: claim.claim_date || ''
                        });
                    });
                    voucherButtons.set(button.dataset.key, button);
                    voucherList.appendChild(button);
                });
                syncVoucherButtons();
            } catch (error) {
                voucherList.className = 'empty-state text-center text-danger py-4 px-3';
                voucherList.textContent = 'Unable to load claimed vouchers.';
            }
        }

        function addSelection(item) {
            const tricycleNo = (item.tricycle_no || '').trim();
            const voucherNumber = normalizeVoucher(item.voucher_number);
            if (!tricycleNo || !voucherNumber) {
                alert('Please choose a claimed voucher number.');
                return;
            }

            if (selected.some((row) => row.tricycle_no.toLowerCase() === tricycleNo.toLowerCase() && row.voucher_number === voucherNumber)) {
                alert('This tricycle and voucher is already in the list.');
                return;
            }

            selected.push({
                tricycle_no: tricycleNo,
                voucher_number: voucherNumber,
                driver_name: item.driver_name || 'Manual entry',
                remaining: item.remaining ?? '-',
                fuel_type: item.fuel_type || getSelectedFuelType(),
                liters: item.liters || '',
                pump_price: item.pump_price || getFuelPrice(item.fuel_type || getSelectedFuelType()),
                amount: item.amount || groupAmount.value,
                claim_date: item.claim_date || ''
            });

            chosenSuggestion = null;
            setDropdown('');
            renderSelected();
            syncVoucherButtons();
            saveDraftNow();
        }

        function syncVoucherButtons() {
            voucherButtons.forEach((button, key) => {
                const isSelected = selected.some((item) => `${item.tricycle_no.toLowerCase()}|${item.voucher_number}` === key);
                button.classList.toggle('is-selected', isSelected);
                button.classList.toggle('btn-outline-success', !isSelected);
                button.classList.toggle('btn-success', isSelected);
            });
        }

        function renderSelected() {
            selectedCount.textContent = selected.length;
            emptyState.classList.toggle('d-none', selected.length !== 0);
            selectedTableWrap.classList.toggle('d-none', selected.length === 0);
            selectedTable.innerHTML = '';

            selected.forEach((item, index) => {
                const amount = selectedAmount(item);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="order-cell">${index + 1}</td>
                    <td class="fw-semibold">${escapeHtml(item.tricycle_no)}</td>
                    <td>${item.voucher_number ? escapeHtml(item.voucher_number) : '<span class="text-muted">All claimed</span>'}</td>
                    <td>${escapeHtml(item.driver_name)}</td>
                    <td>${escapeHtml(item.fuel_type)}</td>
                    <td>${escapeHtml(Number(item.liters || 0).toFixed(2))} L</td>
                    <td>PHP ${escapeHtml(Number(item.pump_price || 0).toFixed(2))}</td>
                    <td>PHP ${escapeHtml(formatRoundedAmount(amount))}</td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Move selected row">
                            <button type="button" class="btn btn-outline-secondary" title="Move up" data-action="up" data-index="${index}" ${index === 0 ? 'disabled' : ''}>
                                <i class="bi bi-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" title="Move down" data-action="down" data-index="${index}" ${index === selected.length - 1 ? 'disabled' : ''}>
                                <i class="bi bi-arrow-down"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" title="Remove" data-action="remove" data-index="${index}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                selectedTable.appendChild(row);
            });

            updateSelectedTotals();
        }

        function addTypedOrChosen() {
            if (chosenSuggestion && chosenSuggestion.tricycle_no === manualSearch.value.trim()) {
                loadClaimedVouchers(chosenSuggestion);
                return;
            }

            loadClaimedVouchers({
                tricycle_no: manualSearch.value.trim(),
                driver_name: 'Manual entry',
                remaining: '-'
            });
        }

        async function submitExport() {
            if (selected.length === 0) {
                alert('Please add at least one tricycle number.');
                return;
            }

            const incomplete = selected.find((item) => Number(item.liters) <= 0 || Number(item.pump_price) <= 0 || Number(item.amount) <= 0 || !item.fuel_type);
            if (incomplete) {
                alert('Please enter actual liters, fuel type, pump price, and manual amount for every selected group.');
                return;
            }

            const selectedEntries = selected.map((item) => ({
                tricycle_no: item.tricycle_no,
                voucher_number: item.voucher_number,
                driver_name: item.driver_name,
                fuel_type: item.fuel_type,
                liters: item.liters,
                pump_price: item.pump_price,
                amount: item.amount,
                claim_date: item.claim_date || ''
            }));
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const exportButtons = [document.getElementById('exportManualBtn'), document.getElementById('exportManualBtnTop')];

            exportButtons.forEach((button) => {
                if (button) button.disabled = true;
            });

            try {
                const saveResponse = await fetch('api_save_manual_claimed_voucher_export.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        selected_entries: selectedEntries,
                        start_date: startDate,
                        end_date: endDate,
                        station_id: Number(document.querySelector('input[name="station_id"]').value || 0)
                    })
                });
                const saveResult = await saveResponse.json();

                if (!saveResult.success) {
                    alert(saveResult.message || 'Unable to save the export data. Please try again.');
                    return;
                }
            } catch (error) {
                alert('Unable to save the export data. Please check your connection and try again.');
                return;
            } finally {
                exportButtons.forEach((button) => {
                    if (button) button.disabled = false;
                });
            }

            document.getElementById('selectedTricyclesInput').value = JSON.stringify(selected.map((item) => item.tricycle_no));
            document.getElementById('selectedEntriesInput').value = JSON.stringify(selectedEntries);
            document.getElementById('pumpPriceInput').value = selected[0].pump_price;
            document.getElementById('startDateInput').value = startDate;
            document.getElementById('endDateInput').value = endDate;
            document.getElementById('exportForm').submit();

            saveDraftNow();
        }

        function applyGroupSettingsToCurrentTricycle() {
            const fuelType = getSelectedFuelType();
            const pumpPrice = getFuelPrice(fuelType);
            const liters = groupLiters.value;
            const amount = groupAmount.value;
            let targetTricycleNo = currentTricycleNo;

            if (!targetTricycleNo) {
                const selectedTricycles = [...new Set(selected.map((item) => item.tricycle_no.toLowerCase()))];
                if (selectedTricycles.length === 1) {
                    targetTricycleNo = selectedTricycles[0];
                }
            }

            if (!targetTricycleNo) {
                updateSelectedTotals();
                return;
            }

            selected.forEach((item) => {
                if (item.tricycle_no.toLowerCase() === targetTricycleNo.toLowerCase()) {
                    item.fuel_type = fuelType;
                    item.pump_price = pumpPrice;
                    item.liters = liters;
                    item.amount = amount;
                }
            });
            renderSelected();
        }

        manualSearch.addEventListener('input', (event) => {
            chosenSuggestion = null;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadSuggestions(event.target.value.trim()), 220);
        });

        manualSearch.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addTypedOrChosen();
            }
        });

        document.getElementById('addSelectedBtn').addEventListener('click', addTypedOrChosen);

        fuelTypeRadios.forEach((radio) => {
            radio.addEventListener('change', applyGroupSettingsToCurrentTricycle);
        });
        fuelPriceInputs.forEach((input) => {
            input.addEventListener('input', () => {
                if (input.dataset.fuel === getSelectedFuelType()) {
                    applyGroupSettingsToCurrentTricycle();
                }
            });
        });
        groupLiters.addEventListener('input', applyGroupSettingsToCurrentTricycle);
        groupAmount.addEventListener('input', applyGroupSettingsToCurrentTricycle);

        selectedTable.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-action]');
            if (!button) return;

            const index = parseInt(button.dataset.index, 10);
            const action = button.dataset.action;

            if (action === 'remove') {
                selected.splice(index, 1);
            } else if (action === 'up' && index > 0) {
                [selected[index - 1], selected[index]] = [selected[index], selected[index - 1]];
            } else if (action === 'down' && index < selected.length - 1) {
                [selected[index], selected[index + 1]] = [selected[index + 1], selected[index]];
            }

            renderSelected();
            syncVoucherButtons();
            saveDraftNow();
        });

        document.getElementById('clearListBtn').addEventListener('click', () => {
            selected.splice(0, selected.length);
            renderSelected();
            syncVoucherButtons();
            saveDraftNow();
        });

        document.getElementById('openBatchModalBtn').addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('batchModal')).show();
            loadBatchList();
        });

        batchList.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-batch-id]');
            if (!button) return;
            loadBatch(button.dataset.batchId);
        });

        window.addEventListener('beforeunload', function() {
            clearTimeout(draftSaveTimer);
            saveDraft(true);
        });

        document.getElementById('exportManualBtn').addEventListener('click', submitExport);
        document.getElementById('exportManualBtnTop').addEventListener('click', submitExport);

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.search-wrap')) {
                setDropdown('');
            }
        });

        // ========== On page load: show local draft or database backup ==========
        initializeDraftBackup();
    </script>
</body>

</html>
