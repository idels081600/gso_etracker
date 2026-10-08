<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: Logi_login.php');
    exit;
}

require_once __DIR__ . '/logi_db.php';

function stock_card_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stock_card_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
if (!$itemId) {
    http_response_code(400);
    exit('Select an inventory item to view its stock card.');
}

$stmt = $conn->prepare('SELECT id, item_no, item_name, rack_no, unit, current_balance, description, updated_at FROM inventory_items WHERE id = ?');
$stmt->bind_param('i', $itemId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
if (!$item) {
    http_response_code(404);
    exit('Inventory item not found.');
}

$from = stock_card_date(trim((string)($_GET['from'] ?? '')));
$to = stock_card_date(trim((string)($_GET['to'] ?? '')));
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}

$where = ['item_no = ?', 'item_name = ?'];
$types = 'ss';
$params = [$item['item_no'], $item['item_name']];
if ($from !== '') {
    $where[] = 'created_at >= ?';
    $types .= 's';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'created_at <= ?';
    $types .= 's';
    $params[] = $to;
}

$sql = 'SELECT id, transaction_type, quantity, previous_balance, new_balance, reason, requestor, reference_no, PO_no_IB_no, user_id, created_at, updated_at
        FROM inventory_transactions
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY created_at, updated_at, id';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$movements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($from !== '') {
    $openingStmt = $conn->prepare('SELECT new_balance FROM inventory_transactions WHERE item_no = ? AND item_name = ? AND created_at < ? ORDER BY created_at DESC, updated_at DESC, id DESC LIMIT 1');
    $openingStmt->bind_param('sss', $item['item_no'], $item['item_name'], $from);
    $openingStmt->execute();
    $openingRow = $openingStmt->get_result()->fetch_assoc();
    $opening = $openingRow ? (int)$openingRow['new_balance'] : ($movements ? (int)$movements[0]['previous_balance'] : (int)$item['current_balance']);
} else {
    $opening = $movements ? (int)$movements[0]['previous_balance'] : (int)$item['current_balance'];
}

$entries = [];
$running = $opening;
$totalReceipts = 0;
$totalIssues = 0;
$varianceCount = 0;

foreach ($movements as $movement) {
    $previous = (int)$movement['previous_balance'];
    if ($previous !== $running) {
        $variance = $previous - $running;
        $entries[] = [
            'date' => $movement['created_at'],
            'reference' => 'LEGACY-VARIANCE',
            'receipt' => $variance > 0 ? $variance : null,
            'issue' => $variance < 0 ? abs($variance) : null,
            'balance' => $previous,
            'office' => '',
            'particulars' => 'Balance discontinuity before transaction #' . $movement['id'] . '; supporting movement is missing.',
            'variance' => true,
        ];
        if ($variance > 0) {
            $totalReceipts += $variance;
        } else {
            $totalIssues += abs($variance);
        }
        $running = $previous;
        $varianceCount++;
    }

    $delta = (int)$movement['new_balance'] - (int)$movement['previous_balance'];
    $receipt = $delta > 0 ? $delta : null;
    $issue = $delta < 0 ? abs($delta) : null;
    if ($receipt !== null) {
        $totalReceipts += $receipt;
    }
    if ($issue !== null) {
        $totalIssues += $issue;
    }

    $referenceParts = array_filter([
        trim((string)$movement['reference_no']),
        trim((string)$movement['PO_no_IB_no']),
        '#' . $movement['id'],
    ]);
    $entries[] = [
        'date' => $movement['created_at'],
        'reference' => implode(' / ', array_unique($referenceParts)),
        'receipt' => $receipt,
        'issue' => $issue,
        'balance' => (int)$movement['new_balance'],
        'office' => trim((string)$movement['requestor']),
        'particulars' => trim((string)$movement['reason']) ?: ucwords(strtolower(str_replace('_', ' ', $movement['transaction_type']))),
        'variance' => false,
    ];
    $running = (int)$movement['new_balance'];
}

$excludedStmt = $conn->prepare('SELECT COUNT(*) AS total FROM inventory_transactions WHERE item_no = ? AND item_name <> ?');
$excludedStmt->bind_param('ss', $item['item_no'], $item['item_name']);
$excludedStmt->execute();
$excludedCount = (int)$excludedStmt->get_result()->fetch_assoc()['total'];

$periodLabel = $from || $to
    ? (($from ?: 'Beginning') . ' to ' . ($to ?: 'Present'))
    : 'Complete recorded history';
$closingLabel = $to !== '' ? 'Period closing balance' : 'Recorded balance';
$balanceDifference = $to === '' ? ((int)$item['current_balance'] - $running) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Card - <?= stock_card_h($item['item_name']) ?></title>
    <link rel="stylesheet" href="Logi_stock_card.css">
</head>
<body>
    <header class="screen-header">
        <a class="brand" href="Logi_Sys_Dashboard.php">
            <img src="tagbi_seal.png" alt="">
            <span><strong>LogiSys</strong><small>General Services Office</small></span>
        </a>
        <nav>
            <a href="Logi_inventory.php">← Inventory</a>
            <button type="button" onclick="window.print()">Print stock card</button>
        </nav>
    </header>

    <main>
        <section class="page-intro screen-only">
            <div>
                <p class="eyebrow">INVENTORY RECORD</p>
                <h1><?= stock_card_h($item['item_name']) ?></h1>
                <p>Review the receipts, issues, and running balance recorded for this item.</p>
            </div>
            <span class="balance-chip"><?= number_format((int)$item['current_balance']) ?> <?= stock_card_h($item['unit']) ?></span>
        </section>

        <form class="period-filter screen-only" method="get">
            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
            <label>From<input type="date" name="from" value="<?= stock_card_h($from) ?>"></label>
            <label>To<input type="date" name="to" value="<?= stock_card_h($to) ?>"></label>
            <button type="submit">Apply period</button>
            <?php if ($from !== '' || $to !== ''): ?><a href="?item_id=<?= (int)$item['id'] ?>">Show all</a><?php endif; ?>
        </form>

        <?php if ($excludedCount > 0): ?>
            <div class="data-note screen-only">
                <strong>Item-number reuse detected.</strong>
                <?= number_format($excludedCount) ?> transaction<?= $excludedCount === 1 ? '' : 's' ?> for other item names also use item number <?= stock_card_h($item['item_no']) ?> and were excluded.
            </div>
        <?php endif; ?>
        <?php if ($varianceCount > 0): ?>
            <div class="data-note warning">
                <strong>Legacy balance evidence is incomplete.</strong>
                <?= number_format($varianceCount) ?> balance discontinuity was retained as a highlighted variance row instead of being treated as a normal receipt.
            </div>
        <?php endif; ?>

        <article class="stock-card">
            <div class="document-heading">
                <div class="government">
                    <strong>Republic of the Philippines</strong>
                    <span>City Government of Tagbilaran</span>
                    <span>General Services Office</span>
                </div>
                <div class="document-title">
                    <p>WORKING COPY</p>
                    <h2>STOCK CARD</h2>
                </div>
            </div>

            <dl class="item-facts">
                <div><dt>Item description</dt><dd><?= stock_card_h($item['item_name']) ?></dd></div>
                <div><dt>Stock number</dt><dd><?= stock_card_h($item['item_no']) ?></dd></div>
                <div><dt>Unit of measure</dt><dd><?= stock_card_h($item['unit']) ?></dd></div>
                <div><dt>Rack number</dt><dd><?= stock_card_h($item['rack_no']) ?></dd></div>
                <div class="period"><dt>Reporting period</dt><dd><?= stock_card_h($periodLabel) ?></dd></div>
            </dl>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">Date</th>
                            <th rowspan="2">Reference</th>
                            <th colspan="2">Quantity</th>
                            <th rowspan="2">Balance</th>
                            <th rowspan="2">Office / recipient</th>
                            <th rowspan="2">Particulars</th>
                        </tr>
                        <tr>
                            <th>IN</th>
                            <th>OUT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="opening-row">
                            <td><?= stock_card_h($from ?: ($movements[0]['created_at'] ?? date('Y-m-d'))) ?></td>
                            <td>OPENING</td>
                            <td></td>
                            <td></td>
                            <td class="number"><?= number_format($opening) ?></td>
                            <td></td>
                            <td>Opening balance for the displayed period</td>
                        </tr>
                        <?php foreach ($entries as $entry): ?>
                            <tr class="<?= $entry['variance'] ? 'variance-row' : '' ?>">
                                <td><?= stock_card_h(date('M j, Y', strtotime($entry['date']))) ?></td>
                                <td><?= stock_card_h($entry['reference']) ?></td>
                                <td class="number"><?= $entry['receipt'] === null ? '' : number_format($entry['receipt']) ?></td>
                                <td class="number"><?= $entry['issue'] === null ? '' : number_format($entry['issue']) ?></td>
                                <td class="number"><?= number_format($entry['balance']) ?></td>
                                <td><?= stock_card_h($entry['office']) ?></td>
                                <td><?= stock_card_h($entry['particulars']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$entries): ?>
                            <tr><td colspan="7" class="empty">No movements were recorded for the selected period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">Period totals</th>
                            <th class="number"><?= number_format($totalReceipts) ?></th>
                            <th class="number"><?= number_format($totalIssues) ?></th>
                            <th class="number"><?= number_format($running) ?></th>
                            <th colspan="2"><?= stock_card_h($closingLabel) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card-footer">
                <span>Generated <?= stock_card_h(date('M j, Y g:i A')) ?></span>
                <span><?= number_format(count($movements)) ?> source movement<?= count($movements) === 1 ? '' : 's' ?></span>
                <?php if ($balanceDifference !== 0): ?><strong>Current inventory variance: <?= number_format($balanceDifference) ?></strong><?php endif; ?>
            </div>
        </article>
    </main>
</body>
</html>
