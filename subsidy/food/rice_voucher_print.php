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

$filter = isset($_GET['filter']) ? strtolower(trim($_GET['filter'])) : 'unclaimed';
$allowed_filters = ['all', 'unclaimed', 'claimed'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'unclaimed';
}

$sort = isset($_GET['sort']) ? strtolower(trim($_GET['sort'])) : 'address';
$allowed_sorts = ['address', 'code', 'name'];
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'address';
}

$barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';

$where_clauses = ["status = 'Active'"];
if ($filter === 'unclaimed') {
    $where_clauses[] = "is_claimed = 0";
} elseif ($filter === 'claimed') {
    $where_clauses[] = "is_claimed = 1";
}

$types = '';
$params = [];
if ($barangay !== '') {
    $where_clauses[] = "address = ?";
    $types .= 's';
    $params[] = $barangay;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

$natural_code_order = "ORDER BY TRIM(SUBSTRING_INDEX(household_code, '-', 1)) ASC, CAST(TRIM(SUBSTRING_INDEX(household_code, '-', -1)) AS UNSIGNED) ASC, household_code ASC";
$order_sql = $natural_code_order;
if ($sort === 'address') {
    $order_sql = "ORDER BY (address IS NULL OR TRIM(address) = ''), address ASC, TRIM(SUBSTRING_INDEX(household_code, '-', 1)) ASC, CAST(TRIM(SUBSTRING_INDEX(household_code, '-', -1)) AS UNSIGNED) ASC, household_code ASC";
} elseif ($sort === 'name') {
    $order_sql = "ORDER BY household_name ASC, household_code ASC";
}

$sql = "SELECT household_code, household_name, address, is_claimed
        FROM rice_households
        $where_sql
        $order_sql";

$stmt = mysqli_prepare($conn, $sql);
$result = false;
if ($stmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}
$records = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}

$filter_title = [
    'all' => 'All Active Households',
    'unclaimed' => 'Unclaimed Vouchers',
    'claimed' => 'Claimed Vouchers'
];

$sort_title = [
    'address' => 'Sorted by Address',
    'code' => 'Sorted by Code',
    'name' => 'Sorted by Name'
];

function riceVoucherNameClass($name)
{
    $normalized = preg_replace('/\s+/', '', (string)$name);
    $length = strlen($normalized);

    if ($length >= 22) {
        return ' voucher-name--xlong';
    }

    if ($length >= 18) {
        return ' voucher-name--long';
    }

    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rice Voucher Print Sheet</title>
    <style>
        :root {
            --sheet-bg: #f3f4f6;
            --card-bg: #ffffff;
            --ink-soft: #7a7a7a;
            --ink-strong: #515151;
            --accent-line: #6b7280;
            --claimed: #0f766e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--sheet-bg);
            color: #111827;
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

        .toolbar .badge {
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

        .page {
            width: 297mm;
            min-height: 210mm;
            margin: 12px auto;
            padding: 4mm;
            background: #fff;
            box-shadow: 0 14px 38px rgba(15, 23, 42, 0.12);
            break-after: page;
            page-break-after: always;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2.5mm;
        }

        .voucher {
            position: relative;
            aspect-ratio: 8 / 3;
            border: 0.4mm solid #dadde2;
            background: var(--card-bg);
            overflow: hidden;
        }

        .voucher-template {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            pointer-events: none;
        }

        .voucher-overlay {
            position: absolute;
            inset: 0;
            height: 100%;
            z-index: 1;
        }

        .voucher-meta {
            position: absolute;
            left: 28.5%;
            right: 9.3%;
            bottom: 24.6%;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 21mm;
            column-gap: 4.8mm;
            align-items: end;
        }

        .voucher-name,
        .voucher-code {
            color: #b0002a;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
            white-space: nowrap;
        }

        .voucher-name {
            font-size: 2.55mm;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: left;
            padding: 0 0.6mm;
        }

        .voucher-code {
            font-size: 2.55mm;
            font-weight: 800;
            text-align: center;
        }

        .voucher-name--long {
            font-size: 2.22mm;
        }

        .voucher-name--xlong {
            font-size: 1.95mm;
        }

        .voucher.claimed {
            border-color: #0f766e;
        }

        .voucher.claimed .voucher-name,
        .voucher.claimed .voucher-code {
            color: var(--claimed);
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
            <span class="badge"><?php echo htmlspecialchars($filter_title[$filter]); ?></span>
            <?php if ($barangay !== ''): ?>
                <span class="badge"><?php echo htmlspecialchars($barangay); ?></span>
            <?php endif; ?>
            <span class="badge"><?php echo htmlspecialchars($sort_title[$sort]); ?></span>
            <span><?php echo number_format(count($records)); ?> voucher<?php echo count($records) === 1 ? '' : 's'; ?></span>
        </div>
        <button type="button" onclick="window.print()">Print Vouchers</button>
    </div>

    <?php if (count($records) === 0): ?>
        <div class="page">
            <div class="empty-state">No rice vouchers available for this filter.</div>
        </div>
    <?php else: ?>
        <?php
        $chunks = array_chunk($records, 15);
        foreach ($chunks as $pageItems):
        ?>
            <div class="page">
                <div class="voucher-grid">
                    <?php foreach ($pageItems as $record): ?>
                        <article class="voucher<?php echo ((int)$record['is_claimed'] === 1) ? ' claimed' : ''; ?>">
                            <img class="voucher-template" src="rice voucher.png" alt="" aria-hidden="true">
                            <div class="voucher-overlay">
                                <div class="voucher-meta">
                                    <div class="voucher-name<?php echo riceVoucherNameClass($record['household_name']); ?>"><?php echo htmlspecialchars($record['household_name']); ?></div>
                                    <div class="voucher-code"><?php echo htmlspecialchars($record['household_code']); ?></div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
