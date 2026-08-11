<?php
require_once 'payables_helpers.php';

const PAYABLES_SCAN_OFFICES = ['BAC', 'GSO', 'Budget', 'Accounting', 'CTO', 'Receiving'];
const PAYABLES_SCAN_DIRECTIONS = ['IN', 'OUT'];
const PAYABLES_SCAN_SOURCES = ['USB', 'CAMERA', 'MANUAL'];

function payables_ensure_scan_events_table(): void
{
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS payables_document_scan_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_type VARCHAR(10) NOT NULL,
        record_id INT NOT NULL,
        document_no VARCHAR(100) NOT NULL,
        direction VARCHAR(5) NOT NULL,
        office VARCHAR(40) NOT NULL,
        scan_source VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
        scanned_by VARCHAR(150) NULL,
        scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_record (record_type, record_id),
        INDEX idx_document_no (document_no),
        INDEX idx_scanned_at (scanned_at),
        INDEX idx_office_direction (office, direction)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        payables_log_error('Scan events table creation failed: ' . $conn->error);
    }
}

function payables_normalize_scan_office(string $office): string
{
    $office = trim($office);
    foreach (PAYABLES_SCAN_OFFICES as $option) {
        if (strcasecmp($office, $option) === 0) {
            return $option;
        }
    }

    return '';
}

function payables_normalize_scan_direction(string $direction): string
{
    $direction = strtoupper(trim($direction));
    return in_array($direction, PAYABLES_SCAN_DIRECTIONS, true) ? $direction : '';
}

function payables_normalize_scan_source(string $source): string
{
    $source = strtoupper(trim($source));
    return in_array($source, PAYABLES_SCAN_SOURCES, true) ? $source : 'MANUAL';
}

function payables_find_documents_by_number(string $documentNo): array
{
    global $conn;

    $documentNo = trim($documentNo);
    if ($documentNo === '') {
        return [];
    }

    $matches = [];
    $bacStmt = $conn->prepare("
        SELECT id, ib_no AS document_no, project_name AS title, bidder AS party
        FROM bac_monitoring
        WHERE TRIM(ib_no) = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    if ($bacStmt) {
        $bacStmt->bind_param('s', $documentNo);
        $bacStmt->execute();
        $result = $bacStmt->get_result();
        while ($result && $row = $result->fetch_assoc()) {
            $matches[] = [
                'record_type' => 'IB',
                'record_id' => (int)$row['id'],
                'document_no' => $row['document_no'] ?? '',
                'title' => $row['title'] ?? '',
                'party' => $row['party'] ?? '',
            ];
        }
        $bacStmt->close();
    }

    $rfqStmt = $conn->prepare("
        SELECT id, RFQ_no AS document_no, description AS title, supplier AS party
        FROM PO_sap
        WHERE delete_status = 0
          AND TRIM(RFQ_no) = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    if ($rfqStmt) {
        $rfqStmt->bind_param('s', $documentNo);
        $rfqStmt->execute();
        $result = $rfqStmt->get_result();
        while ($result && $row = $result->fetch_assoc()) {
            $matches[] = [
                'record_type' => 'RFQ',
                'record_id' => (int)$row['id'],
                'document_no' => $row['document_no'] ?? '',
                'title' => $row['title'] ?? '',
                'party' => $row['party'] ?? '',
            ];
        }
        $rfqStmt->close();
    }

    return $matches;
}

function payables_get_document_by_record(string $recordType, int $recordId): ?array
{
    global $conn;

    if ($recordType === 'IB') {
        $stmt = $conn->prepare("
            SELECT id, ib_no AS document_no, project_name AS title, bidder AS party
            FROM bac_monitoring
            WHERE id = ?
            LIMIT 1
        ");
    } elseif ($recordType === 'RFQ') {
        $stmt = $conn->prepare("
            SELECT id, RFQ_no AS document_no, description AS title, supplier AS party
            FROM PO_sap
            WHERE id = ?
              AND delete_status = 0
            LIMIT 1
        ");
    } else {
        return null;
    }

    if (!$stmt) {
        payables_log_error('Document lookup prepare failed: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'record_type' => $recordType,
        'record_id' => (int)$row['id'],
        'document_no' => $row['document_no'] ?? '',
        'title' => $row['title'] ?? '',
        'party' => $row['party'] ?? '',
    ];
}

function payables_format_scan_event(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'record_type' => $row['record_type'] ?? '',
        'record_id' => (int)($row['record_id'] ?? 0),
        'document_no' => $row['document_no'] ?? '',
        'direction' => $row['direction'] ?? '',
        'office' => $row['office'] ?? '',
        'scan_source' => $row['scan_source'] ?? '',
        'scanned_by' => $row['scanned_by'] ?? '',
        'scanned_at' => $row['scanned_at'] ?? '',
        'title' => $row['title'] ?? '',
        'party' => $row['party'] ?? '',
    ];
}

function payables_recent_scan_events(array $filters = [], int $limit = 40): array
{
    global $conn;

    payables_ensure_scan_events_table();
    $where = ['1 = 1'];
    $types = '';
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = "(e.document_no LIKE ? OR e.record_type LIKE ? OR e.office LIKE ? OR e.direction LIKE ? OR e.scanned_by LIKE ?)";
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $type = strtoupper(trim((string)($filters['record_type'] ?? '')));
    if (in_array($type, ['IB', 'RFQ'], true)) {
        $where[] = 'e.record_type = ?';
        $types .= 's';
        $params[] = $type;
    }

    $direction = payables_normalize_scan_direction((string)($filters['direction'] ?? ''));
    if ($direction !== '') {
        $where[] = 'e.direction = ?';
        $types .= 's';
        $params[] = $direction;
    }

    $office = payables_normalize_scan_office((string)($filters['office'] ?? ''));
    if ($office !== '') {
        $where[] = 'e.office = ?';
        $types .= 's';
        $params[] = $office;
    }

    $date = payables_normalize_date_or_empty((string)($filters['date'] ?? ''));
    if ($date !== '') {
        $where[] = 'DATE(e.scanned_at) = ?';
        $types .= 's';
        $params[] = $date;
    }

    $limit = max(1, min(100, $limit));
    $whereSql = implode(' AND ', $where);
    $sql = "
        SELECT
            e.*,
            COALESCE(b.project_name, r.description, '') AS title,
            COALESCE(b.bidder, r.supplier, '') AS party
        FROM payables_document_scan_events e
        LEFT JOIN bac_monitoring b
            ON e.record_type = 'IB'
           AND e.record_id = b.id
        LEFT JOIN PO_sap r
            ON e.record_type = 'RFQ'
           AND e.record_id = r.id
        WHERE {$whereSql}
        ORDER BY e.scanned_at DESC, e.id DESC
        LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        payables_log_error('Recent scan events prepare failed: ' . $conn->error);
        return [];
    }

    $types .= 'i';
    $params[] = $limit;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($result && $row = $result->fetch_assoc()) {
        $events[] = payables_format_scan_event($row);
    }
    $stmt->close();

    return $events;
}
