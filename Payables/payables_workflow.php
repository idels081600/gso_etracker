<?php
require_once 'payables_helpers.php';

const PAYABLES_WORKFLOW_STATUSES = ['GSO', 'BUDGET', 'ACCOUNTING', 'CTO'];
const PAYABLES_LOCATION_OPTIONS = ['GSO', 'ACCOUNTING'];
const PAYABLES_GSO_CHECKLIST = [
    'inspection' => 'Inspection',
    'obr' => 'OBR',
    'ics' => 'ICS',
    'par' => 'PAR',
    'ris' => 'RIS',
];
const PAYABLES_GSO_REQUIRED_CHECKLIST = ['inspection', 'obr'];

function payables_ensure_workflow_table(): void
{
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS payables_workflow_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_type VARCHAR(20) NOT NULL,
        record_id INT NOT NULL,
        main_status VARCHAR(20) NOT NULL DEFAULT 'GSO',
        inspection TINYINT(1) NOT NULL DEFAULT 0,
        obr TINYINT(1) NOT NULL DEFAULT 0,
        ics TINYINT(1) NOT NULL DEFAULT 0,
        par TINYINT(1) NOT NULL DEFAULT 0,
        ris TINYINT(1) NOT NULL DEFAULT 0,
        released TINYINT(1) NOT NULL DEFAULT 0,
        current_location VARCHAR(20) NOT NULL DEFAULT 'ACCOUNTING',
        updated_by VARCHAR(150) NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_record (record_type, record_id),
        INDEX idx_main_status (main_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        payables_log_error('Workflow table creation failed: ' . $conn->error);
        return;
    }

    $columns = [
        'main_status' => "ALTER TABLE payables_workflow_status ADD COLUMN main_status VARCHAR(20) NOT NULL DEFAULT 'GSO' AFTER record_id",
        'inspection' => "ALTER TABLE payables_workflow_status ADD COLUMN inspection TINYINT(1) NOT NULL DEFAULT 0 AFTER main_status",
        'obr' => "ALTER TABLE payables_workflow_status ADD COLUMN obr TINYINT(1) NOT NULL DEFAULT 0 AFTER inspection",
        'ics' => "ALTER TABLE payables_workflow_status ADD COLUMN ics TINYINT(1) NOT NULL DEFAULT 0 AFTER obr",
        'par' => "ALTER TABLE payables_workflow_status ADD COLUMN par TINYINT(1) NOT NULL DEFAULT 0 AFTER ics",
        'ris' => "ALTER TABLE payables_workflow_status ADD COLUMN ris TINYINT(1) NOT NULL DEFAULT 0 AFTER par",
        'released' => "ALTER TABLE payables_workflow_status ADD COLUMN released TINYINT(1) NOT NULL DEFAULT 0 AFTER ris",
        'current_location' => "ALTER TABLE payables_workflow_status ADD COLUMN current_location VARCHAR(20) NOT NULL DEFAULT 'ACCOUNTING' AFTER released",
        'updated_by' => "ALTER TABLE payables_workflow_status ADD COLUMN updated_by VARCHAR(150) NULL AFTER current_location",
        'updated_at' => "ALTER TABLE payables_workflow_status ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by",
        'created_at' => "ALTER TABLE payables_workflow_status ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_at",
    ];

    foreach ($columns as $column => $alterSql) {
        $columnName = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM payables_workflow_status LIKE '{$columnName}'");
        if ($result && $result->num_rows > 0) {
            continue;
        }

        if (!$conn->query($alterSql)) {
            payables_log_error("Workflow table migration failed for {$column}: " . $conn->error);
        }
    }

    $historySql = "CREATE TABLE IF NOT EXISTS payables_location_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_type VARCHAR(20) NOT NULL,
        record_id INT NOT NULL,
        location VARCHAR(20) NOT NULL,
        changed_by VARCHAR(150) NULL,
        changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_record_location (record_type, record_id),
        INDEX idx_changed_at (changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($historySql)) {
        payables_log_error('Location history table creation failed: ' . $conn->error);
    }
}

function payables_normalize_record_type(string $recordType): string
{
    return $recordType === 'rfq' ? 'rfq' : 'bac';
}

function payables_default_workflow(string $recordType, int $recordId): array
{
    return [
        'record_type' => payables_normalize_record_type($recordType),
        'record_id' => $recordId,
        'main_status' => 'GSO',
        'inspection' => 0,
        'obr' => 0,
        'ics' => 0,
        'par' => 0,
        'ris' => 0,
        'released' => 0,
        'current_location' => 'ACCOUNTING',
    ];
}

function payables_normalize_location(string $location): string
{
    $location = strtoupper(trim($location));
    return in_array($location, PAYABLES_LOCATION_OPTIONS, true) ? $location : 'ACCOUNTING';
}

function payables_get_workflow(string $recordType, int $recordId): array
{
    global $conn;

    payables_ensure_workflow_table();
    $recordType = payables_normalize_record_type($recordType);
    $stmt = $conn->prepare("SELECT * FROM payables_workflow_status WHERE record_type = ? AND record_id = ? LIMIT 1");
    if (!$stmt) {
        payables_log_error('Workflow lookup prepare failed: ' . $conn->error);
        return payables_default_workflow($recordType, $recordId);
    }

    $stmt->bind_param('si', $recordType, $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $workflow = $row ?: payables_default_workflow($recordType, $recordId);
    $stmt->close();

    return $workflow;
}

function payables_get_workflow_map(string $recordType): array
{
    global $conn;

    payables_ensure_workflow_table();
    $recordType = payables_normalize_record_type($recordType);
    $stmt = $conn->prepare("SELECT * FROM payables_workflow_status WHERE record_type = ?");
    if (!$stmt) {
        payables_log_error('Workflow map prepare failed: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('s', $recordType);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    while ($result && $row = $result->fetch_assoc()) {
        $map[(int)$row['record_id']] = $row;
    }
    $stmt->close();

    return $map;
}

function payables_get_location_history_map(string $recordType, array $recordIds): array
{
    global $conn;

    payables_ensure_workflow_table();
    $recordType = payables_normalize_record_type($recordType);
    $recordIds = array_values(array_unique(array_filter(array_map('intval', $recordIds))));
    if (!$recordIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
    $types = 's' . str_repeat('i', count($recordIds));
    $params = array_merge([$recordType], $recordIds);
    $stmt = $conn->prepare("
        SELECT record_id, location, changed_by, changed_at
        FROM payables_location_history
        WHERE record_type = ?
          AND record_id IN ({$placeholders})
        ORDER BY changed_at DESC, id DESC
    ");
    if (!$stmt) {
        payables_log_error('Location history map prepare failed: ' . $conn->error);
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    while ($result && $row = $result->fetch_assoc()) {
        $id = (int)$row['record_id'];
        if (!isset($map[$id])) {
            $map[$id] = [];
        }
        $map[$id][] = [
            'location' => $row['location'] ?? '',
            'changed_by' => $row['changed_by'] ?? '',
            'changed_at' => $row['changed_at'] ?? '',
        ];
    }
    $stmt->close();

    return $map;
}

function payables_gso_completed_count(array $workflow): int
{
    $count = 0;
    foreach (PAYABLES_GSO_CHECKLIST as $key => $label) {
        if (!empty($workflow[$key])) {
            $count++;
        }
    }

    return $count;
}

function payables_gso_required_completed_count(array $workflow): int
{
    $count = 0;
    foreach (PAYABLES_GSO_REQUIRED_CHECKLIST as $key) {
        if (!empty($workflow[$key])) {
            $count++;
        }
    }

    return $count;
}

function payables_render_workflow_cell(string $recordType, int $recordId, array $workflow): string
{
    $recordType = payables_normalize_record_type($recordType);
    $mainStatus = in_array($workflow['main_status'] ?? '', PAYABLES_WORKFLOW_STATUSES, true) ? $workflow['main_status'] : 'GSO';
    $completed = payables_gso_completed_count($workflow);
    $requiredCompleted = payables_gso_required_completed_count($workflow);
    $requiredTotal = count(PAYABLES_GSO_REQUIRED_CHECKLIST);
    $statusIndex = array_search($mainStatus, PAYABLES_WORKFLOW_STATUSES, true);
    $missing = [];
    foreach (PAYABLES_GSO_REQUIRED_CHECKLIST as $key) {
        if (empty($workflow[$key])) {
            $missing[] = PAYABLES_GSO_CHECKLIST[$key];
        }
    }
    $supportText = $requiredCompleted === $requiredTotal ? 'Ready to proceed to Budget' : implode(', ', $missing) . ' pending';
    if ($supportText === '') {
        $supportText = 'Ready for next routing step';
    }
    $attrs = [
        'data-record-type="' . htmlspecialchars($recordType, ENT_QUOTES, 'UTF-8') . '"',
        'data-record-id="' . $recordId . '"',
        'data-main-status="' . htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8') . '"',
    ];
    foreach (PAYABLES_GSO_CHECKLIST as $key => $label) {
        $attrs[] = 'data-' . $key . '="' . (!empty($workflow[$key]) ? '1' : '0') . '"';
    }

    $html = '<div class="workflow-cell" data-workflow-status="' . htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8') . '" data-gso-complete="' . $requiredCompleted . '">';
    $html .= '<button type="button" class="workflow-summary update-workflow-btn" ' . implode(' ', $attrs) . '>';
    $html .= '<span class="workflow-summary-main">';
    $html .= '<span class="workflow-current status-' . strtolower($mainStatus) . '">';
    $html .= '<span class="workflow-dot"></span>';
    $html .= '<span>' . htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '</span>';
    $html .= '<span class="workflow-support-text">' . htmlspecialchars($supportText, ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '</span>';
    $html .= '<span class="workflow-check-count">' . $requiredCompleted . '/' . $requiredTotal . '</span>';
    $html .= '</button>';
    $html .= '<div class="workflow-rail" aria-label="Workflow status">';
    foreach (PAYABLES_WORKFLOW_STATUSES as $index => $status) {
        $class = 'workflow-step ';
        if ($status === $mainStatus) {
            $class .= 'is-current status-' . strtolower($status);
        } elseif ($index < $statusIndex) {
            $class .= 'is-done';
        } else {
            $class .= 'is-pending';
        }

        $label = $index < $statusIndex ? '&#10003;' : (string)($index + 1);
        $html .= '<span class="' . $class . '" title="' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<span class="workflow-step-marker">' . $label . '</span>';
        $html .= '<span class="workflow-step-label">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</span>';
    }
    $html .= '</div>';
    $html .= '<div class="workflow-mini-checks" aria-label="GSO checklist progress">';
    foreach (PAYABLES_GSO_CHECKLIST as $key => $label) {
        $class = !empty($workflow[$key]) ? 'is-complete' : '';
        $html .= '<span class="workflow-mini-check ' . $class . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"></span>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function payables_render_workflow_modal(): void
{
    ?>
    <div class="modal fade" id="workflowStatusModal" tabindex="-1" aria-labelledby="workflowStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered workflow-modal-dialog">
            <div class="modal-content workflow-modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="workflowStatusModalLabel">Workflow Status</h5>
                        <div class="workflow-modal-subtitle">Update the current office and GSO requirements for this row.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="workflowStatusForm">
                    <div class="modal-body">
                        <?php echo payables_csrf_input(); ?>
                        <input type="hidden" id="workflow_record_type" name="record_type">
                        <input type="hidden" id="workflow_record_id" name="record_id">
                        <input type="hidden" id="workflow_main_status" name="main_status" value="GSO">
                        <div id="gsoChecklistGroup" class="workflow-checklist-group">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label workflow-section-label mb-0">GSO Checklist</label>
                                <span class="workflow-count-badge" id="gsoChecklistCount">0/2 required</span>
                            </div>
                            <div class="workflow-check-actions mb-2">
                                <button type="button" class="btn btn-sm btn-light" id="markAllGsoChecks">Mark all complete</button>
                                <button type="button" class="btn btn-sm btn-light" id="clearGsoChecks">Clear checklist</button>
                            </div>
                            <div class="workflow-checklist-grid">
                                <?php foreach (PAYABLES_GSO_CHECKLIST as $key => $label): ?>
                                    <?php $isRequired = in_array($key, PAYABLES_GSO_REQUIRED_CHECKLIST, true); ?>
                                    <label class="workflow-check-item">
                                        <input type="checkbox" name="checklist[]" value="<?php echo $key; ?>" data-checklist-item="<?php echo $key; ?>"<?php echo $isRequired ? ' data-required-check="1"' : ''; ?>>
                                        <span class="workflow-check-box"><i class="fas fa-check"></i></span>
                                        <span class="workflow-check-copy">
                                            <span class="workflow-check-title"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="workflow-check-meta"><?php echo $isRequired ? 'Required at GSO' : 'Optional document'; ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="alert alert-danger d-none mt-3 mb-0" id="workflowStatusError"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light workflow-close-btn" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-warning workflow-proceed-btn" id="proceedToBudgetBtn">Proceed to Budget</button>
                        <button type="submit" class="btn btn-success workflow-save-btn">Save Workflow</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
