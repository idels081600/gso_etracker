<?php

function payables_dashboard_render_table_block(array $context): string
{
    $activeStatus = $context['active_status'] ?? 'GSO';
    $tableStatus = $context['table_status'] ?? $activeStatus;
    $isGlobalSearch = !empty($context['is_global_search']);
    $payablesRows = $context['rows'] ?? [];
    $searchTerm = $context['search_term'] ?? '';
    $currentPage = (int)($context['current_page'] ?? 1);
    $totalPages = (int)($context['total_pages'] ?? 1);
    $totalRows = (int)($context['total_rows'] ?? 0);
    $offset = (int)($context['offset'] ?? 0);
    $perPage = (int)($context['per_page'] ?? 25);
    $locationHistoryMap = $context['location_history_map'] ?? [];
    $remarksHistoryMap = $context['remarks_history_map'] ?? [];
    $buildPageUrl = $context['build_page_url'] ?? static fn (string $status, int $page = 1): string => 'bac_dashboard.php';

    ob_start();
    ?>
    <div class="task-table" role="table" aria-label="Payables workflow task list" data-active-status="<?php echo htmlspecialchars($tableStatus, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="task-row task-row-head" role="row">
            <div>IB / RFQ No.</div>
            <div>Winning Bidder / Project</div>
            <div>Amount</div>
            <div class="search-status-head">Status</div>
            <div id="workflowDetailHeader"><?php echo $isGlobalSearch ? 'Details' : ($activeStatus === 'GSO' ? 'Checklist' : 'Remarks'); ?></div>
            <div class="accounting-location-head">Location</div>
            <div id="workflowActionHeader"><?php
                echo $isGlobalSearch
                    ? 'Action'
                    : ($activeStatus === 'BUDGET'
                        ? 'To Accounting'
                        : ($activeStatus === 'ACCOUNTING'
                            ? 'To CTO'
                            : ($activeStatus === 'CTO' ? 'Completed' : 'Transmit to Budget')));
            ?></div>
            <div class="cto-release-head">Check Released</div>
        </div>
        <?php if ($payablesRows): ?>
            <?php foreach ($payablesRows as $row): ?>
                <?php
                $checkKeys = ['inspection', 'obr'];
                $gsoChecklistItems = [
                    'inspection' => 'Inspection',
                    'obr' => 'OBR',
                    'ics' => 'ICS',
                    'par' => 'PAR',
                    'ris' => 'RIS',
                ];
                $mainStatus = in_array($row['main_status'] ?? '', PAYABLES_WORKFLOW_STATUSES, true) ? $row['main_status'] : 'GSO';
                $canTransmitToBudget = $mainStatus === 'GSO';
                $canTransmitToAccounting = $mainStatus === 'BUDGET';
                $canTransmitToCto = $mainStatus === 'ACCOUNTING';
                $actionEnabled = $canTransmitToBudget || $canTransmitToAccounting || $canTransmitToCto;
                $currentLocation = payables_normalize_location($row['current_location'] ?? '');
                $locationHistory = $locationHistoryMap[(int)$row['id']] ?? [];
                if (!$locationHistory) {
                    $locationHistory[] = [
                        'location' => $currentLocation,
                        'changed_by' => '',
                        'changed_at' => '',
                    ];
                }
                $locationHistoryJson = htmlspecialchars(json_encode($locationHistory), ENT_QUOTES, 'UTF-8');
                $remarksHistory = $remarksHistoryMap[(int)$row['id']] ?? [];
                if (!$remarksHistory) {
                    $remarksHistory[] = [
                        'remarks' => trim($row['remarks'] ?? '') !== '' ? trim($row['remarks']) : 'No remarks yet.',
                        'changed_by' => '',
                        'changed_at' => '',
                    ];
                }
                $remarksHistoryJson = htmlspecialchars(json_encode($remarksHistory), ENT_QUOTES, 'UTF-8');
                $actionTitle = $canTransmitToBudget
                    ? 'Transmit to Budget'
                    : ($canTransmitToAccounting
                        ? 'Transmit to Accounting'
                        : ($canTransmitToCto ? 'Transmit to CTO' : 'Complete the previous stage first'));
                ?>
                <div class="task-row payables-row" role="row" data-main-status="<?php echo htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="task-name"><strong><?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><span>BAC transmittal</span></div>
                    <div class="task-name task-project-stack">
                        <strong><?php echo htmlspecialchars($row['winning_bidders'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="amount-cell">&#8369;<?php echo number_format((float)$row['amount'], 2); ?></div>
                    <div class="search-status-cell">
                        <span class="search-status-badge status-<?php echo strtolower(htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8')); ?>">
                            <?php echo htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <div class="checklist-cell">
                        <div class="gso-checklist-strip" aria-label="GSO checklist">
                            <?php foreach ($gsoChecklistItems as $key => $label): ?>
                                <?php $isComplete = !empty($row[$key]); ?>
                                <button
                                    type="button"
                                    class="gso-check-chip <?php echo $isComplete ? 'is-complete' : 'is-missing'; ?>"
                                    title="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-pressed="<?php echo $isComplete ? 'true' : 'false'; ?>"
                                    data-check-key="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo in_array($key, $checkKeys, true) ? 'data-required-check="1"' : ''; ?>
                                >
                                    <i class="fas <?php echo $isComplete ? 'fa-check' : 'fa-minus'; ?>"></i>
                                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="budget-remarks">
                            <span data-remark-for="BUDGET" class="workflow-remarks-preview">
                                <?php echo htmlspecialchars(trim($row['remarks'] ?? '') !== '' ? $row['remarks'] : 'No remarks yet.', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span data-remark-for="ACCOUNTING" class="workflow-remarks-preview">
                                <?php echo htmlspecialchars(trim($row['remarks'] ?? '') !== '' ? $row['remarks'] : 'No remarks yet.', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <strong data-remark-for="CTO"></strong>
                            <button
                                type="button"
                                class="workflow-remarks-edit"
                                data-remark-for="CTO"
                                data-record-id="<?php echo (int)$row['id']; ?>"
                                data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                data-current-remarks="<?php echo htmlspecialchars(trim($row['remarks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-remarks-stage="CTO"
                            >
                                <?php echo htmlspecialchars(trim($row['remarks'] ?? '') !== '' ? $row['remarks'] : 'No remarks yet.', ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="accounting-location-cell">
                        <select
                            class="accounting-location-select"
                            aria-label="Location for <?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                            data-record-id="<?php echo (int)$row['id']; ?>"
                        >
                            <?php foreach (PAYABLES_LOCATION_OPTIONS as $locationOption): ?>
                                <option value="<?php echo htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentLocation === $locationOption ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="transmit-action-cell">
                        <?php if ($mainStatus === 'CTO'): ?>
                            <span class="completed-check-icon" title="Completed" aria-label="Completed">
                                <i class="fas fa-check"></i>
                            </span>
                        <?php else: ?>
                            <div class="transmit-action-group">
                                <button
                                    type="button"
                                    class="transmit-budget-btn"
                                    title="<?php echo htmlspecialchars($actionTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="<?php echo htmlspecialchars($actionTitle . ' for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-record-id="<?php echo (int)$row['id']; ?>"
                                    data-inspection="<?php echo !empty($row['inspection']) ? '1' : '0'; ?>"
                                    data-obr="<?php echo !empty($row['obr']) ? '1' : '0'; ?>"
                                    data-ics="<?php echo !empty($row['ics']) ? '1' : '0'; ?>"
                                    data-par="<?php echo !empty($row['par']) ? '1' : '0'; ?>"
                                    data-ris="<?php echo !empty($row['ris']) ? '1' : '0'; ?>"
                                    <?php echo $actionEnabled ? '' : 'disabled'; ?>
                                >
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <?php if (in_array($mainStatus, ['BUDGET', 'ACCOUNTING'], true)): ?>
                                    <button
                                        type="button"
                                        class="workflow-remarks-action"
                                        title="Edit remarks"
                                        aria-label="<?php echo htmlspecialchars('Edit remarks for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-record-id="<?php echo (int)$row['id']; ?>"
                                        data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-current-remarks="<?php echo htmlspecialchars(trim($row['remarks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-remarks-stage="<?php echo htmlspecialchars(ucfirst(strtolower($mainStatus)), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <i class="fas fa-pen"></i>
                                    </button>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="location-history-btn"
                                    title="View location history"
                                    aria-label="<?php echo htmlspecialchars('View location history for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-location-history="<?php echo $locationHistoryJson; ?>"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="cto-release-cell">
                        <div class="cto-release-actions">
                            <label class="cto-release-check" title="Mark as released">
                                <input
                                    type="checkbox"
                                    class="cto-release-checkbox"
                                    aria-label="Check released for <?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-record-id="<?php echo (int)$row['id']; ?>"
                                    <?php echo !empty($row['released']) ? 'checked' : ''; ?>
                                >
                                <span></span>
                            </label>
                            <button
                                type="button"
                                class="remarks-history-btn"
                                title="View remarks history"
                                aria-label="<?php echo htmlspecialchars('View remarks history for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                data-remarks-history="<?php echo $remarksHistoryJson; ?>"
                            >
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="task-row payables-row empty-row" role="row">
                <div class="text-muted"><?php echo $searchTerm !== '' ? 'No matching BAC records found.' : 'No BAC records found.'; ?></div>
            </div>
        <?php endif; ?>
    </div>
    <div class="task-pagination" aria-label="Payables pagination">
        <span>
            Showing <?php echo $totalRows === 0 ? 0 : $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?>
            of <?php echo $totalRows; ?>
        </span>
        <div class="task-page-buttons">
            <a class="<?php echo $currentPage <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl($activeStatus, max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous page">
                <i class="fas fa-chevron-left"></i>
            </a>
            <strong>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></strong>
            <a class="<?php echo $currentPage >= $totalPages ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl($activeStatus, min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next page">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php

    return trim(ob_get_clean());
}
