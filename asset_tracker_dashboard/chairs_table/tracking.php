<?php
require_once dirname(__DIR__) . '/page_bootstrap.php';
require_once dirname(__DIR__) . '/db_asset.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once __DIR__ . '/equipment_helpers.php';
date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');
if (isset($_POST['save_data'])) {
    require_once dirname(__DIR__) . '/validators.php';
    asset_validate_csrf();
    try {
        $name = input_string($_POST, 'name', 255);
        $contact = input_string($_POST, 'contact', 30);
        if (!preg_match('/^\+639\d{9}$/', $contact)) {
            throw new InvalidArgumentException('Contact must be +639 followed by 9 digits.');
        }
        $date = input_date($_POST, 'datepicker');
        if ($date < $today) {
            throw new InvalidArgumentException('Installation date cannot be in the past.');
        }
        $duration = input_int($_POST, 'duration', 0);
        if ($duration < 0 || $duration > 365) {
            throw new InvalidArgumentException('Duration must be between 0 and 365 days.');
        }
        $retrievalDate = (new DateTimeImmutable($date))->modify("+{$duration} days")->format('Y-m-d');
        $purpose = input_string($_POST, 'purpose', 255);
        $locationChoice = input_enum($_POST, 'Location', ['Bool','Booy','Cabawan','Cogon','Dao','Dampas','Manga','Mansasa','Poblacion I','Poblacion II','Poblacion III','San Isidro','Taloto','Tiptip','Ubujan','Outside Tagbilaran','Other']);
        $location = $locationChoice === 'Other' ? input_string($_POST, 'other', 255) : $locationChoice;
        $address = input_string($_POST, 'address', 500);
        $typeIds = $_POST['equipment_type_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        if (!is_array($typeIds) || !is_array($quantities) || count($typeIds) !== count($quantities)) {
            throw new InvalidArgumentException('Invalid equipment rows.');
        }
        $items = [];
        foreach ($typeIds as $index => $typeId) {
            $items[] = ['equipment_type_id' => (int) $typeId, 'quantity' => (int) ($quantities[$index] ?? 0)];
        }
        $duplicate = db_fetch_one($conn, 'SELECT id FROM deployments WHERE name = ? AND contact_no = ? AND date = ? AND location = ? LIMIT 1', 'ssss', [$name, $contact, $date, $location]);
        if ($duplicate) {
            throw new InvalidArgumentException('A matching deployment already exists.');
        }
        insert_deployment($name, $contact, $purpose, $location, $address, $date, $retrievalDate, $items);
        header('Location: tracking.php?success=1');
        exit;
    } catch (InvalidArgumentException $error) {
        header('Location: tracking.php?error=' . rawurlencode($error->getMessage()));
        exit;
    } catch (Throwable $error) {
        error_log($error->getMessage());
        header('Location: tracking.php?error=Unable%20to%20save%20the%20deployment.');
        exit;
    }
}

$metrics = get_deployment_metrics();
$deployments = get_deployments_with_items();
$chairTypes = get_equipment_types('Chair');
$tableTypes = get_equipment_types('Table');
$allTypes = array_merge($chairTypes, $tableTypes);
$locations = ['Bool','Booy','Cabawan','Cogon','Dao','Dampas','Manga','Mansasa','Poblacion I','Poblacion II','Poblacion III','San Isidro','Taloto','Tiptip','Ubujan','Outside Tagbilaran','Other'];

function status_slug(string $status, string $retrievalDate, string $today): string
{
    if ($status === 'For Retrieval' && $retrievalDate < $today) {
        return 'overdue';
    }
    return strtolower(str_replace(' ', '-', $status));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo asset_csrf_meta(); ?>
    <title>Chairs & Table Tracking</title>
    <script src="../assets/app-security.js" defer></script>
    <link rel="stylesheet" href="../assets/app-ui.css">
    <link rel="stylesheet" href="../assets/white-theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../sidebar_asset.css">
    <link rel="stylesheet" href="assets/tracking-redesign.css">
</head>
<body>
<aside class="sidebar">
    <div class="logo"><img src="../logo.png" alt="Logo"><span class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?></span><span class="user-name"><?php echo htmlspecialchars($_SESSION['pay_name'] ?? $_SESSION['username'] ?? 'User'); ?></span></div>
    <hr class="divider">
    <ul>
        <li><a href="../dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
        <li class="dropdown"><a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a><ul class="dropdown-menu"><li><a href="../pay_track.php">Payables</a></li><li><a href="../rfq_tracking.php">RFQ</a></li></ul></li>
        <li><a href="../tent_tracking/tracking.php"><i class="fas fa-campground icon-size"></i> Tent</a></li>
        <li><a class="active" href="tracking.php"><i class="fas fa-chair icon-size"></i> Chairs & Table</a></li>
        <li><a href="../motorpool/motorpool_admin.php"><i class="fas fa-wrench icon-size"></i> Motorpool</a></li>
        <li><a href="../transpo.php"><i class="fas fa-truck icon-size"></i> Transportation</a></li>
        <li><a href="../../create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
    </ul>
    <a href="../../logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
</aside>

<main class="content ct-page">
    <div id="system-status" class="ct-toast <?php echo isset($_GET['error']) ? 'is-error' : 'is-success'; ?>" <?php echo isset($_GET['error']) || isset($_GET['success']) ? '' : 'hidden'; ?> role="status" aria-live="polite">
        <i class="fas <?php echo isset($_GET['error']) ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
        <span><?php echo isset($_GET['error']) ? e((string) $_GET['error']) : 'Equipment deployment saved successfully.'; ?></span>
        <button type="button" aria-label="Dismiss message"><i class="fas fa-times"></i></button>
    </div>

    <header class="ct-hero">
        <div><span class="ct-eyebrow">General Services Office</span><h1>Chairs & Table Tracking</h1><p>Monitor deployment, retrieval, and available inventory in one workspace.</p></div>
        <div class="ct-hero-date"><i class="far fa-calendar-alt"></i><span><?php echo e(date('F j, Y')); ?></span></div>
    </header>

    <?php if ($metrics['overdue'] > 0): ?>
    <div class="ct-overdue-alert">
        <span class="ct-alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
        <div><strong><?php echo $metrics['overdue']; ?> overdue deployment<?php echo $metrics['overdue'] === 1 ? '' : 's'; ?></strong><span>These records remain visible until their equipment is retrieved.</span></div>
        <button type="button" aria-label="Dismiss overdue alert"><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>

    <section class="ct-metrics" aria-label="Deployment metrics">
        <?php
        $cards = [
            ['chairs_deployed', 'Chairs Reserved', 'fa-chair', 'teal'],
            ['tables_deployed', 'Tables Reserved', 'fa-table', 'green'],
            ['pending', 'Pending', 'fa-clock', 'amber'],
            ['due_today', 'Due Today', 'fa-truck-loading', 'blue'],
            ['overdue', 'Overdue', 'fa-exclamation-circle', 'red'],
        ];
        foreach ($cards as [$key, $label, $icon, $tone]):
        ?>
        <article class="ct-metric ct-tone-<?php echo $tone; ?>"><span class="ct-metric-icon"><i class="fas <?php echo $icon; ?>"></i></span><div><strong><?php echo (int) $metrics[$key]; ?></strong><span><?php echo e($label); ?></span></div></article>
        <?php endforeach; ?>
    </section>

    <section class="ct-inventory-panel">
        <div class="ct-section-heading"><div><span class="ct-eyebrow">Live inventory</span><h2>Available equipment</h2><p>Quantities update with each deployment lifecycle change.</p></div><button type="button" class="ct-btn" data-bs-toggle="modal" data-bs-target="#inventoryModal"><i class="fas fa-boxes"></i> Manage inventory</button></div>
        <div class="ct-inventory-groups">
            <?php foreach ([['Chairs', $chairTypes, 'fa-chair'], ['Tables', $tableTypes, 'fa-table']] as [$title, $types, $icon]): ?>
            <div class="ct-inventory-group"><h3><i class="fas <?php echo $icon; ?>"></i><?php echo $title; ?></h3><div class="ct-inventory-grid">
                <?php foreach ($types as $type): ?>
                <article class="ct-stock-card"><div><strong><?php echo e($type['display_name']); ?></strong><span><?php echo (int) $type['available_qty']; ?> available of <?php echo (int) $type['total_qty']; ?></span></div><span class="ct-stock-count"><?php echo (int) $type['reserved_qty']; ?> reserved</span></article>
                <?php endforeach; ?>
            </div></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="ct-records-panel">
        <div class="ct-section-heading ct-records-heading"><div><span class="ct-eyebrow">Operations</span><h2>Deployment records</h2><p id="recordSummary"><?php echo count($deployments); ?> total records</p></div>
            <div class="ct-actions">
                <button type="button" class="ct-btn ct-btn-primary" data-bs-toggle="modal" data-bs-target="#deployModal"><i class="fas fa-plus"></i> Deploy equipment</button>
                <button type="button" class="ct-btn" id="openBulkModal"><i class="fas fa-edit"></i> Bulk status</button>
                <button type="button" class="ct-btn" data-bs-toggle="modal" data-bs-target="#printModal"><i class="fas fa-print"></i> Print</button>
                <a class="ct-btn" href="export_csv.php"><i class="fas fa-download"></i> Export CSV</a>
                <button type="button" class="ct-btn" id="openRetrieved"><i class="fas fa-history"></i> History</button>
            </div>
        </div>
        <div class="ct-filterbar">
            <div class="ct-filter-chips" role="group" aria-label="Filter status">
                <?php foreach (['all' => 'All', 'deployed' => 'Installed', 'pending' => 'Pending', 'for-retrieval' => 'For Retrieval', 'overdue' => 'Overdue', 'retrieved' => 'Retrieved'] as $key => $label): ?>
                <button type="button" class="ct-filter-chip <?php echo $key === 'all' ? 'is-active' : ''; ?>" data-filter="<?php echo $key; ?>"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>
            <div class="ct-filter-controls">
                <div class="ct-date-filter"><i class="far fa-calendar-alt"></i><label>From<input type="date" id="dateFrom"></label><span>to</span><label>To<input type="date" id="dateTo"></label><button type="button" id="clearDates">Clear</button><small id="dateError" aria-live="polite"></small></div>
                <label class="ct-search"><i class="fas fa-search"></i><span class="visually-hidden">Search records</span><input type="search" id="searchInput" placeholder="Search records"></label>
            </div>
        </div>
        <div class="ct-table-wrap">
            <table class="ct-table" id="deploymentTable">
                <thead><tr><th>ID</th><th>Requestor</th><th>Equipment</th><th>Location</th><th>Purpose</th><th>Installed</th><th>Retrieval</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($deployments as $deployment):
                    $slug = status_slug($deployment['status'], $deployment['retrieval_date'], $today);
                    $displayStatus = $slug === 'overdue' ? 'Overdue' : ($deployment['status'] === 'Deployed' ? 'Installed' : $deployment['status']);
                ?>
                    <tr data-id="<?php echo (int) $deployment['id']; ?>" data-status="<?php echo e($slug); ?>" data-installed-date="<?php echo e($deployment['date']); ?>">
                        <td><strong>#<?php echo (int) $deployment['id']; ?></strong></td>
                        <td><strong><?php echo e($deployment['name']); ?></strong><span class="ct-subtext"><?php echo e($deployment['contact_no']); ?></span></td>
                        <td><div class="ct-item-chips"><?php foreach ($deployment['items'] as $item): ?><span class="ct-item-chip ct-chip-<?php echo strtolower($item['category']); ?>"><?php echo e($item['display_name']); ?> <b>x<?php echo (int) $item['quantity']; ?></b></span><?php endforeach; ?></div></td>
                        <td><strong><?php echo e($deployment['location']); ?></strong><span class="ct-subtext"><?php echo e($deployment['address']); ?></span></td>
                        <td><?php echo e($deployment['purpose']); ?></td>
                        <td><?php echo e(date('M j, Y', strtotime($deployment['date']))); ?></td>
                        <td><?php echo e(date('M j, Y', strtotime($deployment['retrieval_date']))); ?></td>
                        <td><span class="ct-status ct-status-<?php echo e($slug); ?>"><?php echo e($displayStatus); ?></span></td>
                        <td><div class="ct-row-actions">
                            <button type="button" class="ct-icon-btn edit-record" data-id="<?php echo (int) $deployment['id']; ?>" aria-label="Edit deployment #<?php echo (int) $deployment['id']; ?>"><i class="fas fa-pen"></i></button>
                            <?php if ($deployment['status'] === 'Pending'): ?><button type="button" class="ct-icon-btn ct-icon-success install-record" data-id="<?php echo (int) $deployment['id']; ?>" aria-label="Mark deployment #<?php echo (int) $deployment['id']; ?> installed" title="Mark installed"><i class="fas fa-check"></i></button><?php endif; ?>
                            <?php if ($deployment['status'] !== 'Retrieved'): ?><button type="button" class="ct-icon-btn ct-icon-retrieve retrieve-record" data-id="<?php echo (int) $deployment['id']; ?>" aria-label="Mark deployment #<?php echo (int) $deployment['id']; ?> retrieved" title="Mark retrieved"><i class="fas fa-truck-loading"></i></button><?php endif; ?>
                            <button type="button" class="ct-icon-btn ct-icon-danger archive-record" data-id="<?php echo (int) $deployment['id']; ?>" aria-label="Archive deployment #<?php echo (int) $deployment['id']; ?>"><i class="fas fa-archive"></i></button>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="ct-empty" id="emptyState" hidden><i class="fas fa-inbox"></i><strong>No matching deployments</strong><span>Try changing the filters or search term.</span></div>
        </div>
        <div class="ct-pagination"><span id="pageSummary"></span><div id="paginationButtons"></div></div>
    </section>
</main>

<div class="modal fade ct-modal" id="deployModal" tabindex="-1" aria-labelledby="deployModalLabel" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <form method="post" id="deployForm">
        <?php echo asset_csrf_input(); ?><input type="hidden" name="save_data" value="1">
        <div class="modal-header"><div><span class="ct-eyebrow">New record</span><h2 class="modal-title" id="deployModalLabel">Deploy equipment</h2><p>Add chairs, tables, or both depending on the request.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="ct-form-grid">
                <label>Requestor name<input class="form-control" name="name" required maxlength="255"></label>
                <label>Contact number<input class="form-control" name="contact" value="+639" pattern="\+639\d{9}" required></label>
                <label>Installation date<input class="form-control" type="date" name="datepicker" min="<?php echo $today; ?>" value="<?php echo $today; ?>" required></label>
                <label>Duration (days)<input class="form-control" type="number" name="duration" min="0" max="365" value="1" required></label>
                <label>Purpose<input class="form-control" name="purpose" required maxlength="255"></label>
                <label>Barangay<select class="form-select location-select" name="Location" required><option value="">Choose location</option><?php foreach ($locations as $location): ?><option><?php echo e($location); ?></option><?php endforeach; ?></select></label>
                <label class="other-location" hidden>Other location<input class="form-control" name="other" maxlength="255"></label>
                <label class="ct-field-wide">Complete address<input class="form-control" name="address" required maxlength="500"></label>
            </div>
            <div class="ct-equipment-sections">
                <section class="ct-equipment-section"><div><h3><i class="fas fa-chair"></i> Chair colors <small>Optional</small></h3><button type="button" class="ct-add-row" data-category="Chair"><i class="fas fa-plus"></i> Add chair</button></div><div class="equipment-rows" data-category="Chair"></div><p class="ct-equipment-empty">No chairs selected.</p></section>
                <section class="ct-equipment-section"><div><h3><i class="fas fa-table"></i> Table shapes <small>Optional</small></h3><button type="button" class="ct-add-row" data-category="Table"><i class="fas fa-plus"></i> Add table</button></div><div class="equipment-rows" data-category="Table"></div><p class="ct-equipment-empty">No tables selected.</p></section>
            </div>
            <div class="ct-form-feedback" aria-live="polite"></div>
        </div>
        <div class="modal-footer"><button type="button" class="ct-btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="ct-btn ct-btn-primary">Save deployment</button></div>
    </form>
</div></div></div>

<div class="modal fade ct-modal" id="inventoryModal" tabindex="-1" aria-labelledby="inventoryModalLabel" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable ct-inventory-dialog"><div class="modal-content">
<div class="modal-header"><div><span class="ct-eyebrow">Inventory controls</span><h2 class="modal-title" id="inventoryModalLabel">Manage chairs and tables</h2><p>Add equipment types, edit available balances, or remove unused items. Reserved equipment remains protected.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
<form id="addEquipmentForm" class="ct-add-equipment-form">
    <div><span class="ct-eyebrow">New equipment type</span><h3>Add chair or table category</h3></div>
    <label>Category<select class="form-select" name="category" required><option value="Chair">Chair</option><option value="Table">Table</option></select></label>
    <label>Subtype<input class="form-control" name="subtype_name" maxlength="50" placeholder="e.g. Green" required></label>
    <label>Display name<input class="form-control" name="display_name" maxlength="100" placeholder="e.g. Green Monoblock Chair" required></label>
    <label>Available balance<input class="form-control" type="number" name="available_qty" min="0" max="1000000" value="0" required></label>
    <button type="submit" class="ct-btn ct-btn-primary"><i class="fas fa-plus"></i> Add equipment</button>
    <div class="ct-form-feedback" aria-live="polite"></div>
</form>
<div class="ct-inventory-editor">
<?php foreach ([['Chairs', $chairTypes, 'fa-chair'], ['Tables', $tableTypes, 'fa-table']] as [$title, $types, $icon]): ?>
    <section><div class="ct-inventory-section-title"><h3><i class="fas <?php echo $icon; ?>"></i><?php echo $title; ?></h3><span><?php echo count($types); ?> equipment type<?php echo count($types) === 1 ? '' : 's'; ?></span></div><div class="ct-inventory-edit-list">
    <?php if ($types === []): ?><div class="ct-inventory-empty">No <?php echo strtolower($title); ?> added yet.</div><?php endif; ?>
    <?php foreach ($types as $type): ?>
        <article class="ct-inventory-edit-row" data-id="<?php echo (int) $type['id']; ?>" data-reserved="<?php echo (int) $type['reserved_qty']; ?>">
            <label>Category<select class="form-select inventory-category"><option value="Chair" <?php echo $type['category'] === 'Chair' ? 'selected' : ''; ?>>Chair</option><option value="Table" <?php echo $type['category'] === 'Table' ? 'selected' : ''; ?>>Table</option></select></label>
            <label>Subtype<input class="form-control inventory-subtype" maxlength="50" value="<?php echo e($type['subtype_name']); ?>" required></label>
            <label class="ct-inventory-name-field">Display name<input class="form-control inventory-display-name" maxlength="100" value="<?php echo e($type['display_name']); ?>" required></label>
            <label>Available balance<input class="form-control inventory-available" type="number" min="0" max="1000000" step="1" value="<?php echo (int) $type['available_qty']; ?>" required></label>
            <div class="ct-inventory-row-meta"><span class="ct-after-save"><?php echo (int) $type['reserved_qty']; ?> reserved · <?php echo (int) $type['total_qty']; ?> total after save</span><span class="ct-inventory-row-actions"><button type="button" class="ct-btn save-equipment-type"><i class="fas fa-save"></i> Save changes</button><button type="button" class="ct-btn ct-btn-danger delete-equipment-type"><i class="fas fa-trash"></i> Delete</button></span></div>
            <div class="ct-form-feedback" aria-live="polite"></div>
        </article>
    <?php endforeach; ?>
    </div></section>
<?php endforeach; ?>
</div></div>
<div class="modal-footer"><button type="button" class="ct-btn" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<div class="modal fade ct-modal" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<form id="editForm"><div class="modal-header"><div><span class="ct-eyebrow">Deployment record</span><h2 class="modal-title" id="editModalLabel">Edit deployment</h2><p>Changes to equipment automatically adjust available inventory.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><input type="hidden" name="action" value="update_deployment"><input type="hidden" name="id">
    <div class="ct-form-grid">
        <label>Requestor name<input class="form-control" name="name" required></label><label>Contact number<input class="form-control" name="contact" pattern="\+639\d{9}" required></label>
        <label>Installation date<input class="form-control" type="date" name="date" required></label><label>Retrieval date<input class="form-control" type="date" name="retrieval_date" required></label>
        <label>Status<select class="form-select" name="status" required><?php foreach (EQUIPMENT_DEPLOYMENT_STATUSES as $status): ?><option value="<?php echo e($status); ?>"><?php echo e($status === 'Deployed' ? 'Installed' : $status); ?></option><?php endforeach; ?></select></label>
        <label>Purpose<input class="form-control" name="purpose" required></label><label>Location<input class="form-control" name="location" required></label><label>Address<input class="form-control" name="address" required></label>
    </div>
    <div class="ct-equipment-sections"><section class="ct-equipment-section"><div><h3><i class="fas fa-boxes"></i> Equipment subtypes</h3><button type="button" class="ct-add-row" data-category="All"><i class="fas fa-plus"></i> Add equipment</button></div><div class="equipment-rows" data-category="All"></div></section></div>
    <div class="ct-form-feedback" aria-live="polite"></div>
</div><div class="modal-footer"><button type="button" class="ct-btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="ct-btn ct-btn-primary">Save changes</button></div></form>
</div></div></div>

<div class="modal fade ct-modal" id="bulkModal" tabindex="-1" aria-labelledby="bulkModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><div><span class="ct-eyebrow">Batch operation</span><h2 class="modal-title" id="bulkModalLabel">Bulk status update</h2><p>Select records and choose each new status.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><div id="bulkRows" class="ct-bulk-list"></div><div class="ct-form-feedback" aria-live="polite"></div></div>
<div class="modal-footer"><button type="button" class="ct-btn" data-bs-dismiss="modal">Cancel</button><button type="button" class="ct-btn ct-btn-primary" id="applyBulk">Apply selected changes</button></div>
</div></div></div>

<div class="modal fade ct-modal" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true"><div class="modal-dialog modal-md"><div class="modal-content">
<div class="modal-header"><div><span class="ct-eyebrow">Reports</span><h2 class="modal-title" id="printModalLabel">Print deployment records</h2><p>Open a print-ready chairs and table report.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body ct-print-options"><a href="print_records.php?status=Pending" target="_blank"><i class="fas fa-clock"></i><span><strong>Pending deployments</strong><small>Requests waiting for deployment</small></span><i class="fas fa-chevron-right"></i></a><a href="print_records.php?status=For%20Retrieval" target="_blank"><i class="fas fa-truck-loading"></i><span><strong>For retrieval</strong><small>Due and overdue retrieval records</small></span><i class="fas fa-chevron-right"></i></a></div>
</div></div></div>

<div class="modal fade ct-modal" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true"><div class="modal-dialog modal-md"><div class="modal-content">
<div class="modal-header"><div><span class="ct-eyebrow">Archive record</span><h2 class="modal-title" id="archiveModalLabel">Archive deployment?</h2><p>The record will be removed and any reserved inventory will be restored.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><div class="ct-archive-warning"><i class="fas fa-archive"></i><div><strong id="archiveRecordLabel">Selected deployment</strong><span>This action cannot be undone.</span></div></div><div class="ct-form-feedback" aria-live="polite"></div></div>
<div class="modal-footer"><button type="button" class="ct-btn" data-bs-dismiss="modal">Cancel</button><button type="button" class="ct-btn ct-btn-danger" id="confirmArchive">Archive deployment</button></div>
</div></div></div>

<div class="modal fade ct-modal" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><div><span class="ct-eyebrow">Archive</span><h2 class="modal-title" id="historyModalLabel">Retrieved history</h2><p>Review or reactivate completed deployments.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><div id="historyRows" class="ct-history-list"></div><div class="ct-form-feedback" aria-live="polite"></div></div>
</div></div></div>

<script>window.chairsTableConfig = <?php echo json_encode(['types' => $allTypes, 'statuses' => EQUIPMENT_DEPLOYMENT_STATUSES], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../sidebar_asset.js"></script>
<script src="tracking.js"></script>
</body>
</html>
