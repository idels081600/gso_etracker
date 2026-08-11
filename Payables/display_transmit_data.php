<?php
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';

function payables_clamp_page($page): int
{
    $page = filter_var($page, FILTER_VALIDATE_INT);
    return $page && $page > 0 ? $page : 1;
}

function payables_ensure_index(string $table, string $indexName, string $columns): void
{
    global $conn;

    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $safeIndex = preg_replace('/[^A-Za-z0-9_]/', '', $indexName);
    $result = $conn->query("SHOW INDEX FROM {$safeTable} WHERE Key_name = '{$safeIndex}'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    if (!$conn->query("ALTER TABLE {$safeTable} ADD INDEX {$safeIndex} ({$columns})")) {
        payables_log_error("Index creation failed for {$safeTable}.{$safeIndex}: " . $conn->error);
    }
}

function payables_ensure_listing_indexes(): void
{
    payables_ensure_index('transmittal_bac', 'idx_bac_delete_id', 'delete_status, id');
    payables_ensure_index('transmittal_bac', 'idx_bac_ib_no', 'ib_no');
    payables_ensure_index('transmittal_bac', 'idx_bac_winning_bidders', 'winning_bidders');
    payables_ensure_index('PO_sap', 'idx_rfq_delete_id', 'delete_status, id');
    payables_ensure_index('PO_sap', 'idx_rfq_no', 'RFQ_no');
    payables_ensure_index('PO_sap', 'idx_rfq_supplier', 'supplier');
}

function payables_count_rows(string $table, string $whereSql, string $types = '', array $params = []): int
{
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM {$table} WHERE {$whereSql}");
    if (!$stmt) {
        payables_log_error("Count prepare failed for {$table}: " . $conn->error);
        return 0;
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function payables_render_pagination(string $basePage, int $currentPage, int $totalRows, int $perPage, string $searchTerm): void
{
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $buildUrl = function (int $page) use ($basePage, $searchTerm): string {
        $query = ['page' => $page];
        if ($searchTerm !== '') {
            $query['search'] = $searchTerm;
        }

        return $basePage . '?' . http_build_query($query);
    };
    ?>
    <div class="table-pagination" aria-label="Table pagination">
        <span>
            Showing <?php echo $totalRows === 0 ? 0 : (($currentPage - 1) * $perPage) + 1; ?>-<?php echo min($currentPage * $perPage, $totalRows); ?>
            of <?php echo $totalRows; ?>
        </span>
        <div class="table-page-buttons">
            <a class="<?php echo $currentPage <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous page">
                <i class="fas fa-chevron-left"></i>
            </a>
            <strong>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></strong>
            <a class="<?php echo $currentPage >= $totalPages ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next page">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php
}

function display_transmittal_bac_data(string $searchTerm = '', int $page = 1, int $perPage = 25): array
{
    global $conn;

    payables_ensure_listing_indexes();
    $page = payables_clamp_page($page);
    $where = ['delete_status = 0'];
    $types = '';
    $params = [];
    $searchTerm = trim($searchTerm);

    if ($searchTerm !== '') {
        $searchLike = '%' . $searchTerm . '%';
        $where[] = "(ib_no LIKE ? OR winning_bidders LIKE ? OR project_name LIKE ? OR office LIKE ? OR received_by LIKE ? OR NOA_no LIKE ? OR transmittal_type LIKE ? OR CAST(amount AS CHAR) LIKE ?)";
        $types .= 'ssssssss';
        array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $whereSql = implode(' AND ', $where);
    $totalRows = payables_count_rows('transmittal_bac', $whereSql, $types, $params);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT id, ib_no, winning_bidders, project_name, amount, date_received, office, NOA_no, notice_proceed, calendar_days, deadline, received_by
            FROM transmittal_bac
            WHERE {$whereSql}
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        payables_log_error('BAC list prepare failed: ' . $conn->error);
        echo '<tr><td colspan="12" class="text-center text-danger">Unable to load records.</td></tr>';
        return ['page' => $page, 'per_page' => $perPage, 'total_rows' => $totalRows];
    }

    $queryTypes = $types . 'ii';
    $queryParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($queryTypes, ...$queryParams);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['ib_no'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['winning_bidders'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['project_name'] ?? '') . '</td>';
            echo '<td>&#8369;' . number_format((float)$row['amount'], 2) . '</td>';
            echo '<td>' . htmlspecialchars(substr((string)($row['date_received'] ?? ''), 0, 10)) . '</td>';
            echo '<td>' . htmlspecialchars($row['office'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['NOA_no'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['notice_proceed'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['calendar_days'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['deadline'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['received_by'] ?? '') . '</td>';
            echo '<td class="text-center action-cell">';
            echo '<button type="button" class="btn btn-sm btn-primary edit-btn" data-id="' . (int)$row['id'] . '" title="Edit" aria-label="Edit record"><i class="fas fa-edit"></i></button> ';
            echo '<button type="button" class="btn btn-sm btn-danger delete-btn" data-id="' . (int)$row['id'] . '" title="Delete" aria-label="Delete record"><i class="fas fa-trash-alt"></i></button>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="12" class="text-center text-muted">' . ($searchTerm !== '' ? 'No matching BAC records found.' : 'No BAC records found.') . '</td></tr>';
    }

    $stmt->close();
    return ['page' => $page, 'per_page' => $perPage, 'total_rows' => $totalRows];
}

function display_transmittal_rfq_data(string $searchTerm = '', int $page = 1, int $perPage = 25): array
{
    global $conn;

    payables_ensure_date_column($conn, 'PO_sap', 'award');
    payables_ensure_text_column($conn, 'PO_sap', 'pr_included');
    payables_ensure_listing_indexes();
    $page = payables_clamp_page($page);
    $where = ['delete_status = 0'];
    $types = '';
    $params = [];
    $searchTerm = trim($searchTerm);

    if ($searchTerm !== '') {
        $searchLike = '%' . $searchTerm . '%';
        $where[] = "(RFQ_no LIKE ? OR pr_included LIKE ? OR supplier LIKE ? OR description LIKE ? OR office LIKE ? OR received_by LIKE ? OR status LIKE ? OR CAST(amount AS CHAR) LIKE ? OR CAST(award AS CHAR) LIKE ?)";
        $types .= 'sssssssss';
        array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $whereSql = implode(' AND ', $where);
    $totalRows = payables_count_rows('PO_sap', $whereSql, $types, $params);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT id, RFQ_no, pr_included, supplier, description, amount, date_received, award, office, received_by, status
            FROM PO_sap
            WHERE {$whereSql}
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        payables_log_error('RFQ list prepare failed: ' . $conn->error);
        echo '<tr><td colspan="11" class="text-center text-danger">Unable to load records.</td></tr>';
        return ['page' => $page, 'per_page' => $perPage, 'total_rows' => $totalRows];
    }

    $queryTypes = $types . 'ii';
    $queryParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($queryTypes, ...$queryParams);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status = trim((string)($row['status'] ?? ''));
            $statusClass = strtolower(preg_replace('/[^a-z0-9]+/', '-', $status));
            $statusClass = trim($statusClass, '-');
            if ($statusClass === '') {
                $statusClass = 'pending';
            }
            $prNumbers = payables_split_comma_values($row['pr_included'] ?? '');
            $prCount = count($prNumbers);

            echo '<tr>';
            echo '<td class="rfq-ref">' . htmlspecialchars($row['RFQ_no'] ?? '') . '</td>';
            echo '<td class="rfq-pr-cell">';
            if ($prCount === 0) {
                echo '<span class="rfq-pr-empty">-</span>';
            } elseif ($prCount === 1) {
                echo '<span class="rfq-pr-single">' . htmlspecialchars($prNumbers[0], ENT_QUOTES, 'UTF-8') . '</span>';
            } else {
                echo '<button type="button" class="rfq-pr-trigger" aria-label="' . htmlspecialchars('Show PR numbers for RFQ ' . ($row['RFQ_no'] ?? ''), ENT_QUOTES, 'UTF-8') . '" aria-expanded="false">';
                echo '<span>' . htmlspecialchars($prNumbers[0], ENT_QUOTES, 'UTF-8') . '</span>';
                echo '<strong>+' . ($prCount - 1) . ' more</strong>';
                echo '</button>';
                echo '<div class="rfq-pr-dropdown" role="list" aria-label="PR numbers included">';
                foreach ($prNumbers as $prNumber) {
                    echo '<span role="listitem">' . htmlspecialchars($prNumber, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                echo '</div>';
            }
            echo '</td>';
            echo '<td class="rfq-supplier">' . htmlspecialchars($row['supplier'] ?? '') . '</td>';
            echo '<td class="rfq-description">' . htmlspecialchars($row['description'] ?? '') . '</td>';
            echo '<td class="rfq-amount">&#8369;' . number_format((float)$row['amount'], 2) . '</td>';
            echo '<td class="rfq-date">' . htmlspecialchars(substr((string)($row['date_received'] ?? ''), 0, 10)) . '</td>';
            echo '<td class="rfq-date">' . htmlspecialchars(substr((string)($row['award'] ?? ''), 0, 10)) . '</td>';
            echo '<td class="rfq-office">' . htmlspecialchars($row['office'] ?? '') . '</td>';
            echo '<td class="rfq-person">' . htmlspecialchars($row['received_by'] ?? '') . '</td>';
            echo '<td><span class="rfq-status-badge is-' . htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($status !== '' ? $status : 'Pending') . '</span></td>';
            echo '<td class="text-center action-cell">';
            echo '<button type="button" class="rfq-icon-button edit-btn" data-id="' . (int)$row['id'] . '" title="Edit" aria-label="Edit RFQ"><i class="fas fa-edit"></i></button> ';
            echo '<button type="button" class="rfq-icon-button is-danger delete-btn" data-id="' . (int)$row['id'] . '" title="Delete" aria-label="Delete RFQ"><i class="fas fa-trash-alt"></i></button>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="11" class="rfq-empty">' . ($searchTerm !== '' ? 'No matching RFQ records found.' : 'No RFQ records found.') . '</td></tr>';
    }

    $stmt->close();
    return ['page' => $page, 'per_page' => $perPage, 'total_rows' => $totalRows];
}
