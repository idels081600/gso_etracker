<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    header("Location: ../../login_v2.php");
    exit();
}

$barangay = isset($_GET['barangay']) ? trim((string)$_GET['barangay']) : '';
$sector_key = isset($_GET['sector']) ? strtolower(trim((string)$_GET['sector'])) : '';
$sector_options = [
    'pwd' => ['label' => 'PWD', 'address' => 'PWD'],
    'honest_drivers' => ['label' => 'HONEST DRIVERS', 'address' => 'HONEST DRIVERS'],
    'porter' => ['label' => 'PORTER', 'address' => 'PORTER'],
    'ind' => ['label' => 'IND', 'address' => 'IND'],
    'ind2' => ['label' => 'IND', 'address' => 'IND'],
    'indigents' => ['label' => 'IND', 'address' => 'IND'],
];
$address_sector_labels = [
    'PWD' => 'PWD',
    'HONEST DRIVERS' => 'HONEST DRIVERS',
    'PORTER' => 'PORTER',
    'IND' => 'IND',
    'IND2' => 'IND',
    'INDIGENTS' => 'IND',
];
$selected_sector = $sector_options[$sector_key] ?? null;
$selected_address = $selected_sector !== null ? $selected_sector['address'] : $barangay;
$selected_filter_label = $selected_sector !== null ? $selected_sector['label'] : $barangay;
$selected_sector_label = $selected_sector !== null
    ? $selected_sector['label']
    : ($address_sector_labels[strtoupper($barangay)] ?? '');
$records = [];

if ($selected_address !== '') {
    $is_ind_sector = $selected_sector !== null && $selected_sector['label'] === 'IND';
    $sql = "SELECT household_name, household_code
            FROM rice_claimed_households
            WHERE status = 'Active'
              AND " . ($is_ind_sector
                  ? "UPPER(TRIM(address)) IN ('IND', 'IND2', 'INDIGENTS')"
                  : "address = ?") . "
            ORDER BY household_name ASC, household_code_prefix ASC, household_code_number ASC, household_code ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!$is_ind_sector) {
            mysqli_stmt_bind_param($stmt, 's', $selected_address);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $row['sectoral_representation'] = $selected_sector_label;
                $records[] = $row;
            }
        }
    }
}
$rowsPerPage = 12;
$pages = !empty($records) ? array_chunk($records, $rowsPerPage) : [[]];
$today = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rice Attendance Sheet</title>
    <style>
        :root {
            --line: #222;
            --soft-line: #505050;
            --olive: #a9ce62;
            --olive-border: #90b549;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e5e7eb;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid #d1d5db;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar a,
        .toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            background: #0f766e;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        .toolbar a.alt {
            background: #e5e7eb;
            color: #111827;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #e5e7eb;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .page {
            width: 297mm;
            min-height: 210mm;
            margin: 12px auto;
            padding: 6mm;
            background: #fff;
            box-shadow: 0 14px 38px rgba(15, 23, 42, 0.12);
            break-after: page;
            page-break-after: always;
        }

        .sheet {
            width: 100%;
            border: 1px solid var(--line);
            display: block;
        }

        .sheet-header {
            padding: 5mm 5mm 3mm;
            border-bottom: 1px solid var(--line);
        }

        .sheet-header-top {
            display: grid;
            grid-template-columns: 20mm 1fr;
            gap: 4mm;
            align-items: start;
        }

        .seal {
            width: 18mm;
            height: 18mm;
            border: 1px solid var(--soft-line);
            border-radius: 50%;
            overflow: hidden;
            align-self: start;
        }

        .seal img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .title-block {
            display: flex;
            flex-direction: column;
            gap: 2mm;
        }

        .title-line {
            font-weight: 700;
            font-size: 4.2mm;
        }

        .meta-box {
            display: grid;
            grid-template-columns: 1fr 105mm;
            gap: 4mm;
            align-items: start;
            margin-top: 2mm;
        }

        .meta-fields {
            background: var(--olive);
            border-radius: 4mm;
            padding: 2.5mm 4mm;
            border: 1px solid var(--olive-border);
        }

        .meta-row {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: end;
            gap: 2mm;
            font-size: 3.3mm;
            font-weight: 700;
            margin-bottom: 1.2mm;
        }

        .meta-row:last-child {
            margin-bottom: 0;
        }

        .meta-label {
            white-space: nowrap;
        }

        .meta-value {
            min-height: 4.4mm;
            border-bottom: 1px solid var(--soft-line);
            display: flex;
            align-items: end;
            padding: 0 1mm 0.3mm;
            font-weight: 700;
        }

        .meta-note {
            font-size: 2.4mm;
            color: #4b5563;
            line-height: 1.35;
            padding-top: 2mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid var(--line);
            padding: 1.2mm 1.4mm;
            font-size: 2.85mm;
            vertical-align: middle;
        }

        th {
            font-weight: 700;
            text-align: center;
        }

        td {
            height: 9mm;
        }

        .col-no { text-align: center; }
        .col-name { text-align: left; }
        .col-sex-sm,
        .col-pwd-sm { text-align: center; padding-left: 0.4mm; padding-right: 0.4mm; }
        .col-age { text-align: center; }
        .col-sector { text-align: center; font-weight: 700; }

        .subhead {
            font-size: 2.2mm;
            font-weight: 700;
        }

        .name-cell {
            font-weight: 700;
            text-transform: uppercase;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sheet-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 6mm;
            padding: 2.5mm 4mm 3mm;
            border-top: 1px solid var(--line);
            font-size: 2.15mm;
            line-height: 1.35;
        }

        .page-mark {
            display: flex;
            align-items: center;
            gap: 2mm;
            font-size: 3.4mm;
            font-weight: 700;
            white-space: nowrap;
        }

        .page-mark-box {
            width: 10mm;
            height: 3.2mm;
            background: #d7e61e;
            display: inline-block;
        }

        .empty-state {
            padding: 30mm 12mm;
            text-align: center;
            color: #6b7280;
            font-size: 18px;
        }

        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        @media print {
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .toolbar {
                display: none;
            }

            .page {
                width: 100%;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
                break-after: page;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-left">
            <a class="alt" href="dashboard_rice.php">Back to Dashboard</a>
            <?php if ($selected_address !== ''): ?>
                <span class="badge"><?php echo htmlspecialchars($selected_filter_label); ?></span>
                <span><?php echo number_format(count($records)); ?> name<?php echo count($records) === 1 ? '' : 's'; ?></span>
            <?php else: ?>
                <span class="badge">No Filter Selected</span>
            <?php endif; ?>
        </div>
        <button type="button" onclick="window.print()">Print Attendance</button>
    </div>

    <?php if ($selected_address === '' || count($records) === 0): ?>
        <div class="page">
            <div class="empty-state">
                <?php echo $selected_address === '' ? 'Select a barangay or sector first to open the attendance sheet.' : 'No active household names found for this selection.'; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($pages as $pageIndex => $pageRecords): ?>
            <div class="page">
                <section class="sheet">
                    <header class="sheet-header">
                        <div class="sheet-header-top">
                            <div class="seal">
                                <img src="Tagbilaran-City-Seal-Logo.png" alt="Tagbilaran City Seal">
                            </div>
                            <div class="title-block">
                                <div class="title-line">CITY GOVERNMENT OF TAGBILARAN | REGISTRATION SHEET</div>
                                <div class="meta-box">
                                    <div class="meta-fields">
                                        <div class="meta-row">
                                            <span class="meta-label">ACTIVITY TITLE:</span>
                                            <span class="meta-value">RICE ASSISTANCE</span>
                                        </div>
                                        <div class="meta-row">
                                            <span class="meta-label">HOSTING DEPARTMENT/OFFICE/UNIT:</span>
                                            <span class="meta-value">&nbsp;</span>
                                        </div>
                                        <div class="meta-row" style="grid-template-columns: auto 1fr auto 34mm;">
                                            <span class="meta-label">VENUE:</span>
                                            <span class="meta-value">&nbsp;</span>
                                            <span class="meta-label">DATE:</span>
                                            <span class="meta-value">&nbsp;</span>
                                        </div>
                                    </div>
                                    <div class="meta-note">
                                        *Reproduce this page as much as needed for more than twenty (20) attendees.
                                        Supply corresponding page number/s below.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </header>

                    <div>
                        <table>
                            <colgroup>
                                <col style="width: 4%">
                                <col style="width: 23%">
                                <col style="width: 3.5%">
                                <col style="width: 3.5%">
                                <col style="width: 3.5%">
                                <col style="width: 3.5%">
                                <col style="width: 5%">
                                <col style="width: 10%">
                                <col style="width: 11%">
                                <col style="width: 12%">
                                <col style="width: 9%">
                                <col style="width: 12%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-no" rowspan="2">No</th>
                                    <th class="col-name" rowspan="2">Full Name (Last name, first name, MI)</th>
                                    <th colspan="2">Sex</th>
                                    <th colspan="2">PWD</th>
                                    <th class="col-age" rowspan="2">Age</th>
                                    <th class="col-office" rowspan="2">Office</th>
                                    <th class="col-designation" rowspan="2">Designation</th>
                                    <th class="col-sector" rowspan="2">Sectoral Representation</th>
                                    <th class="col-contact" rowspan="2">Contact Number</th>
                                    <th class="col-signature" rowspan="2">Signature</th>
                                </tr>
                                <tr>
                                    <th class="col-sex-sm subhead">M</th>
                                    <th class="col-sex-sm subhead">F</th>
                                    <th class="col-pwd-sm subhead">Yes</th>
                                    <th class="col-pwd-sm subhead">No</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($rowIndex = 0; $rowIndex < $rowsPerPage; $rowIndex++): ?>
                                    <?php $record = $pageRecords[$rowIndex] ?? null; ?>
                                    <tr>
                                        <td class="col-no"><?php echo $record ? (string)(($pageIndex * $rowsPerPage) + $rowIndex + 1) : ''; ?></td>
                                        <td class="name-cell"><?php echo $record ? htmlspecialchars($record['household_name']) : ''; ?></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td class="col-sector"><?php echo $record ? htmlspecialchars($record['sectoral_representation']) : ''; ?></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <footer class="sheet-footer">
                        <div>
                            <strong>Note:</strong>
                            By signing my name, I consent to the organizer's use of the information provided above in compliance with the Data Privacy Act of 2012.
                            Furthermore, I consent to the photo and video documentation of this activity for its intended purposes.
                        </div>
                        <div class="page-mark">
                            <span>PAGE</span>
                            <span class="page-mark-box"></span>
                            <span><?php echo $pageIndex + 1; ?></span>
                        </div>
                    </footer>
                </section>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
