<?php
require_once dirname(__DIR__) . '/page_bootstrap.php';
require_once dirname(__DIR__) . '/db_asset.php';
require_once dirname(__DIR__) . '/display_data_asset.php';
require_once dirname(__DIR__) . '/api_helpers.php';
date_default_timezone_set('Asia/Manila');
$on_stock = display_tent_status();
$on_field = display_tent_status_Installed();
$on_retrieval = display_tent_status_Retrieval();
$longterm = display_tent_status_Longterm();
$result = display_data();
$today = date('Y-m-d');
$todayLabel = date('F j, Y');
$trackingMetrics = db_fetch_one(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Installed') AS deployed,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'For Retrieval' AND retrieval_date = CURDATE()) AS due_today,
        SUM(status = 'For Retrieval' AND retrieval_date < CURDATE()) AS overdue
     FROM tent"
) ?? [];
$deployedCount = (int) ($trackingMetrics['deployed'] ?? 0);
$pendingCount = (int) ($trackingMetrics['pending'] ?? 0);
$dueTodayCount = (int) ($trackingMetrics['due_today'] ?? 0);
$overdueCount = (int) ($trackingMetrics['overdue'] ?? 0);
if (isset($_POST['save_data'])) {
    require_once dirname(__DIR__) . '/validators.php';
    asset_validate_csrf();

    try {
        $name = input_string($_POST, 'name', 255);
        $contactNo = input_string($_POST, 'contact', 30);
        if (!preg_match('/^\+639\d{9}$/', $contactNo)) {
            throw new InvalidArgumentException('invalid_contact');
        }
        $date = input_date($_POST, 'datepicker');
        if ($date < $today) {
            throw new InvalidArgumentException('datepicker must not be in the past.');
        }
        $numberOfTents = input_int($_POST, 'tent_no');
        if ($numberOfTents > 300) {
            throw new InvalidArgumentException('tent_no exceeds available inventory.');
        }
        $purpose = input_enum($_POST, 'No_tents', ['Wake', 'Fiesta', 'Birthday', 'Wedding', 'Baptism', 'Personal', 'Private', 'Church', 'School', 'City Government', 'LGU', 'Municipalities', 'Province', 'Burial']);
        $locationOption = input_enum($_POST, 'Location', ['Bool', 'Booy', 'Cabawan', 'Cogon', 'Dao', 'Dampas', 'Manga', 'Mansasa', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'San Isidro', 'Taloto', 'Tiptip', 'Ubujan', 'Outside Tagbilaran', 'Other']);
        $location = $locationOption === 'Other'
            ? input_string($_POST, 'other', 255)
            : $locationOption;
        $address = input_string($_POST, 'address', 500);
        $duration = input_int($_POST, 'duration');
        if ($duration > 365) {
            throw new InvalidArgumentException('duration must not exceed 365 days.');
        }
        $retrievalDate = (new DateTimeImmutable($date))->modify("+{$duration} days")->format('Y-m-d');

        $duplicate = db_fetch_one($conn, 'SELECT id FROM tent WHERE name = ? AND contact_no = ? AND date = ? AND location = ? LIMIT 1', 'ssss', [$name, $contactNo, $date, $location]);
        if ($duplicate) {
            throw new InvalidArgumentException('duplicate');
        }

        $stmt = db_execute(
            $conn,
            "INSERT INTO tent(name, contact_no, no_of_tents, purpose, location, address, status, date, retrieval_date) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)",
            'ssisssss',
            [$name, $contactNo, $numberOfTents, $purpose, $location, $address, $date, $retrievalDate]
        );
        mysqli_stmt_close($stmt);
        header('Location: tracking.php?success=1');
        exit;
    } catch (InvalidArgumentException $error) {
        header('Location: tracking.php?error=' . rawurlencode($error->getMessage()));
        exit;
    } catch (Throwable $error) {
        error_log($error->getMessage());
        header('Location: tracking.php?error=insert_failed');
        exit;
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo asset_page_security_tags(); ?>
    <title>Tent Tracker</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../sidebar_asset.css">
    <link rel="stylesheet" href="../tracking_style.css">
    <link rel="stylesheet" href="../style_box.css">
    <link rel="stylesheet" href="assets/tracking-redesign.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- jQuery -->

</head>

<body>
    <script>
        $(document).ready(function() {
            // Function to validate contact number input
            function validateContactNumber(input) {
                let value = input.val();
                const defaultPrefix = '+639';
                const digitRegex = /^[0-9]*$/;

                // If input doesn't start with +639, reset to default and clear extra digits
                if (!value.startsWith('+639')) {
                    value = '+639' + value.replace(/[^\d]/g, '').slice(0, 9);
                    input.val(value);
                } else {
                    // Allow only digits after +639 and limit to 9 digits total after +639
                    const afterPrefix = value.substring(4);
                    const digitsOnly = afterPrefix.replace(/[^\d]/g, '').substring(0, 9);
                    value = '+639' + digitsOnly;
                    input.val(value);
                }

                // Validate length (must be exactly +639 followed by 9 digits = 13 chars total)
                if (!value.match(/^\+639\d{9}$/)) {
                    input.get(0).setCustomValidity('Contact number must be +639 followed by exactly 9 digits');
                    input.addClass('is-invalid');
                } else {
                    input.get(0).setCustomValidity('');
                    input.removeClass('is-invalid');
                }
            }

            // Apply validation to add form contact input
            $('#contact').on('input', function() {
                validateContactNumber($(this));
            });

            // Apply validation to edit form contact input (when modal opens)
            $('#viewEditModal').on('shown.bs.modal', function() {
                $('#viewEditContactNo').on('input', function() {
                    validateContactNumber($(this));
                });
            });

            // Prevent form submission if contact is invalid
            $('form').on('submit', function(e) {
                const contactInput = $(this).find('input[name="contact"], input[name="contact_no"]');
                if (contactInput.length > 0) {
                    const value = contactInput.val();
                    if (!value.match(/^\+639\d{9}$/)) {
                        e.preventDefault();
                        alert('Please enter a valid contact number: +639 followed by exactly 9 digits');
                    }
                }
            });
        });
    </script>
    <div class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="Logo">
            <span class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?></span>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['pay_name'] ?? $_SESSION['username'] ?? 'User'); ?></span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="../dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="../pay_track.php">Payables</a></li>
                    <li><a href="../rfq_tracking.php">RFQ</a></li>
                </ul>
            </li>
            <li><a href="tracking.php"><i class="fas fa-campground icon-size"></i> Tent</a></li>
            <li><a href="../chairs_table/tracking.php"><i class="fas fa-chair icon-size"></i> Chairs & Table</a></li>
            <li><a href="../motorpool/motorpool_admin.php"><i class="fas fa-wrench icon-size"></i> Motorpool</a></li>
            <li><a href="../transpo.php"><i class="fas fa-truck icon-size"></i> Transportation</a></li>
            <li><a href="../../create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="../../logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="content">
<div id="system-status" class="alert alert-info" style="<?php echo isset($_GET['error']) ? 'display: block;' : 'display: none;'; ?> margin-bottom: 20px;">
    <i class="fas fa-info-circle"></i>
    <span id="system-status-message"><?php
        if (isset($_GET['error'])) {
            switch ($_GET['error']) {
                case 'duplicate':
                    echo 'A duplicate entry was found. Tent installation already exists for this name, contact number, date, and location.';
                    break;
                case 'invalid_contact':
                    echo 'Invalid contact number format. Please enter a number starting with +639 followed by exactly 9 digits (e.g., +639123456789).';
                    break;
                case 'tent_no exceeds available inventory.':
                    echo 'The requested quantity exceeds the 300-tent inventory limit.';
                    break;
                case 'duration must not exceed 365 days.':
                    echo 'Tent duration must be between 1 and 365 days.';
                    break;
                case 'datepicker must not be in the past.':
                    echo 'Installation date cannot be in the past.';
                    break;
                case 'insert_failed':
                    echo 'Failed to add tent record. Please try again.';
                    break;
                default:
                    echo 'Please review the required fields and try creating the deployment request again.';
                    break;
            }
        } elseif (isset($_GET['success'])) {
            echo 'Tent record added successfully!';
            $alertClass = str_replace('alert-info', 'alert-success', $alertClass ?? '');
        }
    ?></span>
</div>

        <header class="tracking-header">
            <div>
                <p class="tracking-eyebrow">General Services Office</p>
                <h1>Tent Tracking</h1>
                <p>Monitor deployments, retrieval schedules, and available tent inventory in one workspace.</p>
            </div>
            <div class="tracking-date" aria-label="Today's date">
                <i class="far fa-calendar-alt" aria-hidden="true"></i>
                <span><?php echo e($todayLabel); ?></span>
            </div>
        </header>

        <?php if ($overdueCount > 0): ?>
            <div class="tracking-alert" id="overdueAlert" role="alert">
                <div class="tracking-alert-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <div>
                    <strong><?php echo $overdueCount; ?> deployment<?php echo $overdueCount === 1 ? '' : 's'; ?> overdue for retrieval</strong>
                    <span>Review the overdue records and coordinate retrieval as soon as possible.</span>
                </div>
                <button type="button" class="tracking-alert-close" id="dismissOverdueAlert" aria-label="Dismiss overdue alert">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
        <?php endif; ?>

        <section class="tracking-stats" aria-label="Tent tracking summary">
            <article class="tracking-stat-card">
                <div class="tracking-stat-icon is-primary"><i class="fas fa-campground" aria-hidden="true"></i></div>
                <div><span>Total Deployed</span><strong><?php echo $deployedCount; ?></strong><small>Currently installed</small></div>
            </article>
            <article class="tracking-stat-card">
                <div class="tracking-stat-icon is-warning"><i class="far fa-clock" aria-hidden="true"></i></div>
                <div><span>Pending</span><strong><?php echo $pendingCount; ?></strong><small>Awaiting installation</small></div>
            </article>
            <article class="tracking-stat-card">
                <div class="tracking-stat-icon is-info"><i class="fas fa-truck-loading" aria-hidden="true"></i></div>
                <div><span>For Retrieval</span><strong><?php echo $dueTodayCount; ?></strong><small>Due today</small></div>
            </article>
            <article class="tracking-stat-card">
                <div class="tracking-stat-icon is-danger"><i class="fas fa-exclamation-circle" aria-hidden="true"></i></div>
                <div><span>Overdue</span><strong><?php echo $overdueCount; ?></strong><small>Past retrieval date</small></div>
            </article>
        </section>

        <section class="tracking-panel" aria-labelledby="trackingRecordsTitle">
            <div class="tracking-panel-header">
                <div>
                    <h2 id="trackingRecordsTitle">Deployment Records</h2>
                    <p>Search, review, and update tent requests.</p>
                </div>
                <button class="btn tracking-primary-action" id="addButton" type="button" data-bs-toggle="modal" data-bs-target="#detailsModal">
                    <i class="fas fa-plus" aria-hidden="true"></i> Install Tent
                </button>
            </div>

            <div class="tracking-toolbar">
                <div class="tracking-filter-chips" role="group" aria-label="Filter records by status">
                    <button type="button" class="tracking-filter is-active" data-filter="all" aria-pressed="true">All</button>
                    <button type="button" class="tracking-filter" data-filter="installed" aria-pressed="false">Installed</button>
                    <button type="button" class="tracking-filter" data-filter="pending" aria-pressed="false">Pending</button>
                    <button type="button" class="tracking-filter" data-filter="overdue" aria-pressed="false">Overdue</button>
                </div>
                <div class="tracking-actions">
                    <div class="tracking-date-range" role="group" aria-labelledby="trackingDateRangeLabel">
                        <span class="tracking-date-range-label" id="trackingDateRangeLabel">
                            <i class="far fa-calendar-alt" aria-hidden="true"></i>
                            Installation date
                        </span>
                        <label for="date-filter-from">
                            <span>From</span>
                            <input type="date" id="date-filter-from">
                        </label>
                        <span class="tracking-date-range-separator" aria-hidden="true">to</span>
                        <label for="date-filter-to">
                            <span>To</span>
                            <input type="date" id="date-filter-to">
                        </label>
                        <button type="button" class="tracking-date-clear" id="clear-date-filter" disabled>Clear</button>
                        <small class="tracking-date-range-error" id="date-filter-error" aria-live="polite"></small>
                    </div>
                    <label class="tracking-search" for="search-input">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" id="search-input" placeholder="Search records" autocomplete="off">
                    </label>
                    <button class="btn tracking-secondary-action" id="editStatusButton" type="button" data-bs-toggle="modal" data-bs-target="#editStatusModal"><i class="fas fa-edit" aria-hidden="true"></i> Edit Status</button>
                    <button class="btn tracking-secondary-action" id="printButton" type="button" data-bs-toggle="modal" data-bs-target="#printModal"><i class="fas fa-print" aria-hidden="true"></i> Print</button>
                    <a class="btn tracking-secondary-action" href="../export_tents_csv.php"><i class="fas fa-download" aria-hidden="true"></i> Export CSV</a>
                    <button class="btn tracking-secondary-action" id="retrievedDataButton" type="button" data-bs-toggle="modal" data-bs-target="#retrievedDataModal"><i class="fas fa-archive" aria-hidden="true"></i> Archive</button>
                </div>
            </div>

            <div class="table-responsive tracking-table-wrap">
                <table id="table_tent" class="table tracking-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Tent No.</th>
                            <th scope="col">Requestor</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Location</th>
                            <th scope="col">Purpose</th>
                            <th scope="col">Installed</th>
                            <th scope="col">Retrieval</th>
                            <th scope="col">Days on Field</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $status = trim((string) ($row['status'] ?? 'Pending')) ?: 'Pending';
                            $installedDate = (string) ($row['date'] ?? '');
                            $retrievalDate = (string) ($row['retrieval_date'] ?? '');
                            $isOverdue = $status === 'For Retrieval' && $retrievalDate !== '' && $retrievalDate < $today;
                            $isDueToday = $status === 'For Retrieval' && $retrievalDate === $today;
                            $filterStatus = $isOverdue ? 'overdue' : strtolower(str_replace(' ', '-', $status));
                            $nameParts = preg_split('/\s+/', trim((string) ($row['name'] ?? ''))) ?: [];
                            $initials = strtoupper(substr((string) ($nameParts[0] ?? '?'), 0, 1) . substr((string) ($nameParts[1] ?? ''), 0, 1));
                            $daysOnField = 0;
                            if ($installedDate !== '' && $status !== 'Pending') {
                                try {
                                    $startDate = new DateTimeImmutable($installedDate);
                                    $endDate = $status === 'Retrieved' && $retrievalDate !== '' ? new DateTimeImmutable($retrievalDate) : new DateTimeImmutable($today);
                                    $daysOnField = max(0, (int) $startDate->diff($endDate)->format('%r%a'));
                                } catch (Throwable $error) {
                                    $daysOnField = 0;
                                }
                            }
                        ?>
                            <tr
                                data-status="<?php echo e($filterStatus); ?>"
                                data-record-id="<?php echo (int) $row['id']; ?>"
                                data-tent-numbers="<?php echo e($row['tent_no'] ?? ''); ?>"
                                data-installed-date="<?php echo e($installedDate); ?>"
                                data-retrieval-date="<?php echo e($retrievalDate); ?>"
                            >
                                <td data-label="Tent No."><div class="tracking-cell-value"><strong class="tracking-tent-number"><?php echo e($row['tent_no'] ?: 'Unassigned'); ?></strong><small><?php echo e($row['no_of_tents']); ?> tent(s)</small></div></td>
                                <td data-label="Requestor"><div class="tracking-cell-value"><div class="tracking-person"><span class="tracking-avatar"><?php echo e($initials); ?></span><strong><?php echo e($row['name']); ?></strong></div></div></td>
                                <td data-label="Contact"><div class="tracking-cell-value"><a class="tracking-contact" href="tel:<?php echo e($row['Contact_no']); ?>"><?php echo e($row['Contact_no']); ?></a></div></td>
                                <td data-label="Location"><div class="tracking-cell-value"><?php echo e($row['location']); ?></div></td>
                                <td data-label="Purpose"><div class="tracking-cell-value"><span class="tracking-purpose"><?php echo e($row['purpose']); ?></span></div></td>
                                <td data-label="Installed" class="date"><div class="tracking-cell-value"><?php echo e($installedDate ?: 'Not set'); ?></div></td>
                                <td data-label="Retrieval" class="retrieval-date <?php echo $isOverdue ? 'is-overdue' : ($isDueToday ? 'is-due' : ''); ?>" data-retrieval-date="<?php echo e($retrievalDate); ?>" title="<?php echo $isOverdue ? 'Retrieval is overdue' : ($isDueToday ? 'Retrieval is due today' : 'Scheduled retrieval date'); ?>"><div class="tracking-cell-value"><?php echo e($retrievalDate ?: 'Not set'); ?>
                                    <?php if ($isOverdue): ?><small>Overdue</small><?php elseif ($isDueToday): ?><small>Due today</small><?php endif; ?></div></td>
                                <td data-label="Days on Field"><div class="tracking-cell-value"><strong><?php echo $daysOnField; ?></strong> day<?php echo $daysOnField === 1 ? '' : 's'; ?></div></td>
                                <td data-label="Status"><div class="tracking-cell-value"><span class="tracking-status status-<?php echo e($filterStatus); ?>"><?php echo e($isOverdue ? 'Overdue' : $status); ?></span>
                                    <select class="form-select status-dropdown tracking-status-select" name="status" aria-label="Change status for <?php echo e($row['name']); ?>">
                                        <option value="Pending" data-id="<?php echo (int) $row['id']; ?>" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Installed" data-id="<?php echo (int) $row['id']; ?>" <?php echo $status === 'Installed' ? 'selected' : ''; ?>>Installed</option>
                                        <option value="For Retrieval" data-id="<?php echo (int) $row['id']; ?>" <?php echo $status === 'For Retrieval' ? 'selected' : ''; ?>>For Retrieval</option>
                                        <option value="Retrieved" data-id="<?php echo (int) $row['id']; ?>" <?php echo $status === 'Retrieved' ? 'selected' : ''; ?>>Retrieved</option>
                                        <option value="Long Term" data-id="<?php echo (int) $row['id']; ?>" <?php echo $status === 'Long Term' ? 'selected' : ''; ?>>Long Term</option>
                                    </select></div></td>
                                <td data-label="Actions"><div class="tracking-cell-value"><div class="tracking-row-actions">
                                        <button class="btn viewButton" data-id="<?php echo (int) $row['id']; ?>" type="button" title="Edit record" aria-label="Edit <?php echo e($row['name']); ?>"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                        <?php if ($status !== 'Retrieved'): ?>
                                            <button class="btn mark-retrieved-button" data-id="<?php echo (int) $row['id']; ?>" type="button" title="Mark retrieved" aria-label="Mark <?php echo e($row['name']); ?> as retrieved"><i class="fas fa-check" aria-hidden="true"></i></button>
                                        <?php endif; ?>
                                        <button class="btn deleteButton" data-id="<?php echo (int) $row['id']; ?>" type="button" title="Delete record" aria-label="Delete <?php echo e($row['name']); ?>"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                    </div></div></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="tracking-empty-state" id="trackingEmptyState" hidden>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <strong>No matching records</strong>
                    <span>Try another search, status, or installation date range.</span>
                </div>
            </div>
            <footer class="tracking-pagination">
                <span id="trackingPaginationSummary">Showing records</span>
                <div id="trackingPaginationButtons" aria-label="Table pagination"></div>
            </footer>
        </section>
    </div>

    <div class="modal fade" id="viewEditModal" tabindex="-1" aria-labelledby="viewEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl edit-record-dialog">
            <div class="modal-content edit-record-content">
                <div class="modal-header edit-record-header">
                    <div>
                        <span class="edit-record-eyebrow">Tent Deployment Record</span>
                        <h5 class="modal-title" id="viewEditModalLabel">Edit Tent Request</h5>
                        <p>Review the request details and update assigned tents.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="viewEditForm" autocomplete="off" novalidate>
                    <input type="hidden" id="id" name="id">
                    <div class="modal-body edit-record-body">
                        <section class="edit-record-section edit-tent-section" aria-labelledby="editTentSelectionTitle">
                            <div class="edit-section-heading">
                                <div>
                                    <h6 id="editTentSelectionTitle">Assigned Tents</h6>
                                    <p>Select available tents up to the requested quantity.</p>
                                </div>
                                <div class="edit-selection-count" aria-live="polite">
                                    <strong id="editSelectedTentCount">0</strong>
                                    <span id="editSelectedTentLabel">selected</span>
                                </div>
                            </div>
                            <div class="edit-tent-toolbar">
                                <label class="edit-tent-search" for="editTentSearch">
                                    <i class="fas fa-search" aria-hidden="true"></i>
                                    <input type="search" id="editTentSearch" placeholder="Find tent number" inputmode="numeric">
                                </label>
                                <div class="edit-tent-legend" aria-label="Tent status legend">
                                    <span><i class="legend-swatch is-available"></i>Available</span>
                                    <span><i class="legend-swatch is-installed"></i>Installed</span>
                                    <span><i class="legend-swatch is-retrieval"></i>For retrieval</span>
                                    <span><i class="legend-swatch is-long-term"></i>Long term</span>
                                    <span><i class="legend-swatch is-selected"></i>Selected</span>
                                </div>
                            </div>
                            <div class="boxContainer edit-tent-container" tabindex="0" aria-label="Tent number selection">
                                <div class="boxes edit-tent-grid">
                                <?php for ($i = 1; $i <= 300; $i++): ?>
                                        <button class="box" data-box="<?php echo $i; ?>" type="button" aria-label="Tent <?php echo $i; ?>"><?php echo $i; ?></button>
                                <?php endfor; ?>
                                </div>
                            </div>
                            <div class="edit-assignment-summary">
                                <div>
                                    <label for="viewEditTentNo" class="form-label">Selected Tent Numbers</label>
                                    <input type="text" class="form-control" id="viewEditTentNo" name="tent_no" readonly placeholder="No tents assigned">
                                </div>
                                <div>
                                    <label for="viewEditNoOfTents" class="form-label">Requested Quantity</label>
                                    <input type="number" class="form-control" id="viewEditNoOfTents" name="no_of_tents" min="1" max="300" required>
                                    <div class="invalid-feedback">Enter a quantity from 1 to 300.</div>
                                </div>
                            </div>
                            <div class="edit-form-message" id="editTentSelectionMessage" role="status" aria-live="polite"></div>
                        </section>

                        <div class="edit-record-columns">
                            <section class="edit-record-section" aria-labelledby="editScheduleTitle">
                                <div class="edit-section-heading">
                                    <div>
                                        <h6 id="editScheduleTitle">Schedule &amp; Status</h6>
                                        <p>Deployment timing and current request state.</p>
                                    </div>
                                </div>
                                <div class="edit-field-grid">
                                    <div>
                                        <label for="viewEditDate" class="form-label">Installation Date</label>
                                        <input type="date" class="form-control" id="viewEditDate" name="date" required>
                                        <div class="invalid-feedback">Choose an installation date.</div>
                                    </div>
                                    <div>
                                        <label for="viewEditRetrievalDate" class="form-label">Retrieval Date</label>
                                        <input type="date" class="form-control" id="viewEditRetrievalDate" name="retrieval_date" required>
                                        <div class="invalid-feedback">Choose a retrieval date.</div>
                                    </div>
                                    <div class="edit-field-full">
                                        <label for="viewEditStatus" class="form-label">Status</label>
                                        <select class="form-select" id="viewEditStatus" name="status" required>
                                            <option value="">Select Status</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Installed">Installed</option>
                                            <option value="For Retrieval">For Retrieval</option>
                                            <option value="Retrieved">Retrieved</option>
                                            <option value="Long Term">Long Term</option>
                                        </select>
                                        <div class="invalid-feedback">Select the current request status.</div>
                                    </div>
                                </div>
                            </section>

                            <section class="edit-record-section" aria-labelledby="editRequesterTitle">
                                <div class="edit-section-heading">
                                    <div>
                                        <h6 id="editRequesterTitle">Requester Details</h6>
                                        <p>Contact information for the request.</p>
                                    </div>
                                </div>
                                <div class="edit-field-grid">
                                    <div>
                                        <label for="viewEditName" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="viewEditName" name="name" maxlength="255" required>
                                        <div class="invalid-feedback">Enter the requester name.</div>
                                    </div>
                                    <div>
                                        <label for="viewEditContactNo" class="form-label">Contact Number</label>
                                        <input type="tel" class="form-control" id="viewEditContactNo" name="contact_no" value="+639" inputmode="tel" maxlength="13" pattern="\+639[0-9]{9}" required>
                                        <div class="invalid-feedback">Use the format +639 followed by 9 digits.</div>
                                    </div>
                                    <div class="edit-field-full">
                                        <label for="viewEditAddress" class="form-label">Address</label>
                                        <textarea class="form-control" id="viewEditAddress" name="address" rows="2" maxlength="500" required></textarea>
                                        <div class="invalid-feedback">Enter the requester address.</div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <section class="edit-record-section" aria-labelledby="editEventTitle">
                            <div class="edit-section-heading">
                                <div>
                                    <h6 id="editEventTitle">Deployment Details</h6>
                                    <p>Where and why the tents are being deployed.</p>
                                </div>
                            </div>
                            <div class="edit-field-grid">
                                <div>
                                    <label for="viewEditPurpose" class="form-label">Purpose</label>
                                    <input type="text" class="form-control" id="viewEditPurpose" name="purpose" maxlength="255" required>
                                    <div class="invalid-feedback">Enter the deployment purpose.</div>
                                </div>
                                <div>
                                    <label for="viewEditLocation" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="viewEditLocation" name="location" maxlength="255" required>
                                    <div class="invalid-feedback">Enter the deployment location.</div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="modal-footer edit-record-footer">
                        <span id="editFormStatus" class="edit-form-status" aria-live="polite"></span>
                        <div>
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" id="viewEditSubmit">
                                <i class="fas fa-save" aria-hidden="true"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Install Tent Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg install-modal-dialog">
            <div class="modal-content install-modal-content">
                <div class="modal-header install-modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Install Tent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="installTentForm" action="tracking.php" method="POST" autocomplete="off">
                    <?php echo asset_csrf_input(); ?>
                    <input type="hidden" name="save_data" value="1">
                    <div class="modal-body install-modal-body">
                        <div class="row install-simple-grid">
                            <div class="col-md-6">
                                <label for="tent_no" class="form-label">No. of Tents</label>
                                <input type="number" class="form-control" id="tent_no" name="tent_no" min="1" max="300" step="1" required>
                            </div>
                            <div class="col-md-6">
                                <label for="datepicker" class="form-label">Date</label>
                                <input type="date" class="form-control" id="datepicker" name="datepicker" min="<?php echo e($today); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" maxlength="255" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact" class="form-label">Contact no.</label>
                                <input type="tel" class="form-control" id="contact" name="contact" value="+639" inputmode="tel" maxlength="13" pattern="\+639[0-9]{9}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" maxlength="500" required>
                            </div>
                            <div class="col-md-6">
                                <label for="Location" class="form-label">Barangay</label>
                                <select class="form-select" id="Location" name="Location" required>
                                    <option value="">Select Location</option>
                                    <option value="Bool">Bool</option><option value="Booy">Booy</option><option value="Cabawan">Cabawan</option>
                                    <option value="Cogon">Cogon</option><option value="Dao">Dao</option><option value="Dampas">Dampas</option>
                                    <option value="Manga">Manga</option><option value="Mansasa">Mansasa</option><option value="Poblacion I">Poblacion I</option>
                                    <option value="Poblacion II">Poblacion II</option><option value="Poblacion III">Poblacion III</option><option value="San Isidro">San Isidro</option>
                                    <option value="Taloto">Taloto</option><option value="Tiptip">Tiptip</option><option value="Ubujan">Ubujan</option>
                                    <option value="Outside Tagbilaran">Outside Tagbilaran</option><option value="Other">Other location</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="No_tents" class="form-label">Purpose</label>
                                <select class="form-select" id="No_tents" name="No_tents" required>
                                    <option value="">Select Purpose</option>
                                    <option value="Wake">Wake</option><option value="Fiesta">Fiesta</option><option value="Birthday">Birthday</option>
                                    <option value="Wedding">Wedding</option><option value="Baptism">Baptism</option><option value="Personal">Personal</option>
                                    <option value="Private">Private</option><option value="Church">Church</option><option value="School">School</option>
                                    <option value="City Government">City Government</option><option value="LGU">LGU</option><option value="Municipalities">Municipalities</option>
                                    <option value="Province">Province</option><option value="Burial">Burial</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tent_duration" class="form-label">Tent Duration</label>
                                <input type="number" class="form-control" id="tent_duration" name="duration" min="1" max="365" required>
                            </div>
                            <div class="col-12" id="otherLocationField" hidden>
                                <label for="other" class="form-label">Specify Location</label>
                                <input type="text" class="form-control" id="other" name="other" maxlength="255">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer install-modal-footer">
                        <button type="submit" class="btn btn-success" id="installTentSubmit">Submit</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printModalLabel">Print Tent Records</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column align-items-center gap-3">
                    <button type="button" class="btn btn-primary w-100" id="printPendingBtn">Print Pending Requests</button>
                    <button type="button" class="btn btn-warning w-100" id="printRetrievalBtn">Print For Retrieval</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Tent Status Modal -->
    <div class="modal fade" id="editStatusModal" tabindex="-1" aria-labelledby="editStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStatusModalLabel">Edit Tent Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="editStatusSearch" placeholder="Search tents...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="editStatusTable">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><input type="checkbox" id="selectAllCheckbox"></th>
                                    <th scope="col">Tent No.</th>
                                    <th scope="col">Current Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody id="editStatusTableBody">
                                <!-- Data will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="applyBulkStatus">Apply Status to Selected</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Retrieved Data Modal -->
    <div class="modal fade" id="retrievedDataModal" tabindex="-1" aria-labelledby="retrievedDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="retrievedDataModalLabel">Retrieved Tents History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="retrievedDataSearch" placeholder="Search in table...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="retrievedDataTable">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">Tent No.</th>
                                    <th scope="col">Retrieval Date</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Purpose</th>
                                    <th scope="col">Barangay</th>
                                    <th scope="col">Address</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody id="retrievedDataTableBody">
                                <!-- Data will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="tracking.js"></script>
    <script src="assets/tracking-redesign.js"></script>
    <script>
        // Store retrieved data globally for searching
        let retrievedTableData = [];

        // Handle Retrieved Data Modal
        $('#retrievedDataModal').on('show.bs.modal', function() {
            const tbody = $('#retrievedDataTableBody');
            // Show loading state
            tbody.html('<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>');

            $.ajax({
                url: '../fetch_retrieved_data.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    tbody.empty();

                    if (response.error) {
                        console.error('Server error:', response.error);
                        tbody.html(`<tr><td colspan="5" class="text-center text-danger">Error: ${response.error}</td></tr>`);
                        return;
                    }

                    const data = response.data || [];

                    if (data.length === 0) {
                        tbody.html('<tr><td colspan="5" class="text-center">No retrieved tents found</td></tr>');
                        return;
                    }

                    data.forEach(function(item) {
                        const row = `
                            <tr>
                                <td>${item.tent_no || 'N/A'}</td>
                                <td>${item.retrieval_date || 'N/A'}</td>
                                <td>${item.name || 'N/A'}</td>
                                <td>${item.purpose || 'N/A'}</td>
                                <td>${item.location || 'N/A'}</td>
                                 <td>${item.address || 'N/A'}</td>
                                <td>
                                    <button class="btn btn-warning btn-sm undoButton" data-tent-no="${item.id}" title="Undo Retrieved Status">
                                        <i class="fas fa-undo"></i> Undo
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.append(row);
                    });

                    // Store the data for searching
                    retrievedTableData = data;
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);
                    tbody.html('<tr><td colspan="5" class="text-center text-danger">Error loading data. Please try again.</td></tr>');
                }
            });
        });

        // Handle search functionality
        // Handle undo button clicks
        $(document).on('click', '.undoButton', function() {
            const tentid = $(this).data('tent-no');
            const button = $(this);
            if (confirm('Are you sure you want to undo the retrieved status for tent ' + tentid + '?')) {
                $.ajax({
                    url: '../undo_retrieved_status.php',
                    type: 'POST',
                    data: {
                        tent_Id: tentid
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Remove the row from the table
                            const row = button.closest('tr');
                            row.fadeOut(400, function() {
                                row.remove();
                                // Update the retrievedTableData array
                                retrievedTableData = retrievedTableData.filter(item => item.id !== tentid);
                                // Show success message
                                alert('Status successfully updated to For Retrieval');
                            });
                        } else {
                            alert('Error: ' + (response.message || 'Could not update status'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('Error occurred while processing your request');
                    }
                });
            }
        });

        $('#retrievedDataSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            const tbody = $('#retrievedDataTableBody');

            if (!retrievedTableData || retrievedTableData.length === 0) {
                return;
            }

            if (searchTerm === '') {
                // If search is empty, show all data
                displayData(retrievedTableData);
                return;
            }

            // Filter the data
            const filteredData = retrievedTableData.filter(item =>
                (item.tent_no?.toString().toLowerCase().includes(searchTerm) ||
                    item.retrieval_date?.toLowerCase().includes(searchTerm) ||
                    item.name?.toLowerCase().includes(searchTerm) ||
                    item.purpose?.toLowerCase().includes(searchTerm) ||
                    item.location?.toLowerCase().includes(searchTerm))
            );

            displayData(filteredData);
        });

        // Function to display data in the table
        function displayData(data) {
            const tbody = $('#retrievedDataTableBody');
            tbody.empty();

            if (data.length === 0) {
                tbody.html('<tr><td colspan="5" class="text-center">No matching records found</td></tr>');
                return;
            }

            data.forEach(function(item) {
                const row = `
                <tr>
                    <td>${item.tent_no || 'N/A'}</td>
                    <td>${item.retrieval_date || 'N/A'}</td>
                    <td>${item.name || 'N/A'}</td>
                    <td>${item.purpose || 'N/A'}</td>
                    <td>${item.location || 'N/A'}</td>
                    <td>${item.address || 'N/A'}</td>
                    <td>
                        <button class="btn btn-warning btn-sm undoButton" data-tent-no="${item.tent_id}" title="Undo Retrieved Status">
                            <i class="fas fa-undo"></i> Undo
                        </button>
                    </td>
                </tr>
            `;
                tbody.append(row);
            });
        };
    </script>
    <script>
        // Store edit status data globally
        let editStatusData = [];

        // Handle Edit Status Modal
        $('#editStatusModal').on('show.bs.modal', function() {
            const tbody = $('#editStatusTableBody');
            // Show loading state
            tbody.html('<tr><td colspan="4" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>');

            $.ajax({
                url: '../get_all_tent_status.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    tbody.empty();

                    if (!response.success || !Array.isArray(response.data)) {
                        tbody.html('<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>');
                        return;
                    }

                    const data = response.data;

                    if (data.length === 0) {
                        tbody.html('<tr><td colspan="4" class="text-center">No tent data found</td></tr>');
                        return;
                    }

                    displayEditStatusData(data);
                    editStatusData = data; // Store for reference
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    tbody.html('<tr><td colspan="4" class="text-center text-danger">Error loading data. Please try again.</td></tr>');
                }
            });
        });

        // Function to display edit status data
        function displayEditStatusData(data) {
            const tbody = $('#editStatusTableBody');
            tbody.empty();

            data.forEach(function(item) {
                const statusClass = item.Status ? item.Status.toLowerCase().replace(/\s+/g, '-') : '';
                const row = `
                <tr>
                    <td><input type="checkbox" class="tent-checkbox" value="${item.id}"></td>
                    <td>${item.id || 'N/A'}</td>
                    <td>
                        <select class="form-select status-select" data-id="${item.id}">
                            <option value="null" ${item.Status === 'null' ? 'selected' : ''}>Null</option>
                            <option value="Installed" ${item.Status === 'Installed' ? 'selected' : ''}>Installed</option>
                            <option value="For Retrieval" ${item.Status === 'For Retrieval' ? 'selected' : ''}>For Retrieval</option>
                            <option value="Retrieved" ${item.Status === 'Retrieved' ? 'selected' : ''}>Retrieved</option>
                            <option value="Long Term" ${item.Status === 'Long Term' ? 'selected' : ''}>Long Term</option>
                        </select>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm update-single-btn" data-id="${item.id}" title="Update Status">
                            <i class="fas fa-save"></i>
                        </button>
                    </td>
                </tr>
            `;
                tbody.append(row);
            });

            // Reset select all checkbox
            $('#selectAllCheckbox').prop('checked', false);
        }

        // Handle select all checkbox
        $('#selectAllCheckbox').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.tent-checkbox').prop('checked', isChecked);
        });

        // Handle individual checkbox changes
        $(document).on('change', '.tent-checkbox', function() {
            const totalCheckboxes = $('.tent-checkbox').length;
            const checkedCheckboxes = $('.tent-checkbox:checked').length;
            $('#selectAllCheckbox').prop('checked', checkedCheckboxes === totalCheckboxes);
        });

        // Handle single status update
        $(document).on('click', '.update-single-btn', function() {
            const tentId = $(this).data('id');
            const statusSelect = $(this).closest('tr').find('.status-select');
            const newStatus = statusSelect.val();

            if (!newStatus) {
                alert('Please select a status');
                return;
            }

            updateTentStatus(tentId, newStatus, $(this));
        });

        // Handle bulk status update
        $('#applyBulkStatus').on('click', function() {
            const selectedIds = $('.tent-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedIds.length === 0) {
                alert('Please select at least one tent');
                return;
            }

            // For bulk update, use the status from the first selected row as example
            const firstCheckedRow = $('.tent-checkbox:checked').first().closest('tr');
            const statusSelect = firstCheckedRow.find('.status-select');
            const newStatus = statusSelect.val();

            if (!newStatus) {
                alert('Please ensure the selected tents have a valid status');
                return;
            }

            if (confirm(`Update status to "${newStatus}" for ${selectedIds.length} selected tent(s)?`)) {
                updateBulkTentStatus(selectedIds, newStatus);
            }
        });

        // Update single tent status
        function updateTentStatus(tentId, newStatus, button) {
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: '../update_bulk_tent_status.php',
                type: 'POST',
                data: {
                    tent_ids: [tentId],
                    new_status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(`Status updated to "${newStatus}" successfully`);
                        // Update the stored data
                        editStatusData = editStatusData.map(item => {
                            if (item.id == tentId) {
                                item.status = newStatus;
                            }
                            return item;
                        });
                    } else {
                        alert('Error: ' + (response.message || 'Failed to update status'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error occurred while updating status');
                },
                complete: function() {
                    button.prop('disabled', false).html('<i class="fas fa-save"></i>');
                }
            });
        }

        // Handle search in edit status modal
        $('#editStatusSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();

            if (searchTerm === '') {
                displayEditStatusData(editStatusData);
                return;
            }

            const filteredData = editStatusData.filter(item =>
                (item.id?.toString().toLowerCase().includes(searchTerm) ||
                    item.name?.toLowerCase().includes(searchTerm) ||
                    item.Status?.toLowerCase().includes(searchTerm) ||
                    item.location?.toLowerCase().includes(searchTerm))
            );

            displayEditStatusData(filteredData);
        });

        // Update bulk tent status
        function updateBulkTentStatus(tentIds, newStatus) {
            $('#applyBulkStatus').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

            $.ajax({
                url: '../update_bulk_tent_status.php',
                type: 'POST',
                data: {
                    tent_ids: tentIds,
                    new_status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(`Successfully updated ${response.updated_count || tentIds.length} tent(s) to "${newStatus}"`);
                        // Refresh the data
                        displayEditStatusData(editStatusData.map(item => {
                            if (tentIds.includes(item.id.toString())) {
                                item.status = newStatus;
                            }
                            return item;
                        }));
                        // Clear selections
                        $('.tent-checkbox').prop('checked', false);
                        $('#selectAllCheckbox').prop('checked', false);
                    } else {
                        alert('Error: ' + (response.message || 'Failed to update statuses'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error occurred while updating statuses');
                },
                complete: function() {
                    $('#applyBulkStatus').prop('disabled', false).text('Apply Status to Selected');
                }
            });
        }

        $(document).ready(function() {
            $('#printPendingBtn').on('click', function() {
                window.open('../print_tent_pending.php', '_blank');
            });
            $('#printRetrievalBtn').on('click', function() {
                window.open('../print_tent_for_retrieval.php', '_blank');
            });
        });
    </script>
</body>

</html>
