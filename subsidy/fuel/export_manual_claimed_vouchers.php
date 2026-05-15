<?php
session_start();
require_once 'db_fuel.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

$station_id = 0;
$station_name = 'Unknown Station';

if (isset($_POST['station_id']) && !empty($_POST['station_id'])) {
    $station_id = (int)$_POST['station_id'];
    $station_sql = "SELECT station_name FROM gas_stations WHERE id = $station_id";
    $station_result = mysqli_query($conn, $station_sql);

    if (mysqli_num_rows($station_result) > 0) {
        $station_data = mysqli_fetch_assoc($station_result);
        $station_name = $station_data['station_name'];
    } else {
        die('Invalid station selected.');
    }
} elseif (isset($_SESSION['station_id']) && !empty($_SESSION['station_id'])) {
    $station_id = (int)$_SESSION['station_id'];
    $station_name = isset($_SESSION['station_name']) ? $_SESSION['station_name'] : $station_name;
} else {
    header("Location: select_station.php");
    exit();
}

$selectedEntriesRaw = isset($_POST['selected_entries']) ? $_POST['selected_entries'] : '';
$selectedEntries = json_decode($selectedEntriesRaw, true);

if (!is_array($selectedEntries) || empty($selectedEntries)) {
    $selectedRaw = isset($_POST['selected_tricycles']) ? $_POST['selected_tricycles'] : '';
    $selectedTricycles = json_decode($selectedRaw, true);
    $selectedEntries = [];

    if (is_array($selectedTricycles)) {
        foreach ($selectedTricycles as $tricycleNo) {
            $selectedEntries[] = [
                'tricycle_no' => $tricycleNo,
                'voucher_number' => ''
            ];
        }
    }
}

$orderedSelections = [];
$selectionKeys = [];
foreach ($selectedEntries as $entry) {
    $tricycleNo = is_array($entry) && isset($entry['tricycle_no']) ? trim((string)$entry['tricycle_no']) : trim((string)$entry);
    $voucherNumber = is_array($entry) && isset($entry['voucher_number']) ? trim((string)$entry['voucher_number']) : '';
    $voucherNumber = $voucherNumber !== '' ? (int)ltrim($voucherNumber, '0') : '';
    $key = $tricycleNo . '|' . $voucherNumber;

    if ($tricycleNo !== '' && !isset($selectionKeys[$key])) {
        $fuelType = is_array($entry) && isset($entry['fuel_type']) ? trim((string)$entry['fuel_type']) : 'Silver';
        $liters = is_array($entry) && isset($entry['liters']) ? (float)$entry['liters'] : 0;
        $entryPumpPrice = is_array($entry) && isset($entry['pump_price']) ? (float)$entry['pump_price'] : 0;
        $driverName = is_array($entry) && isset($entry['driver_name']) ? trim((string)$entry['driver_name']) : 'Manual entry';
        $claimDate = is_array($entry) && !empty($entry['claim_date']) ? $entry['claim_date'] : date('Y-m-d');
        $validFuelTypes = ['Silver', 'Platinum', 'Diesel'];
        if (!in_array($fuelType, $validFuelTypes, true)) {
            $fuelType = 'Silver';
        }

        $selectionKeys[$key] = true;
        $orderedSelections[] = [
            'tricycle_no' => $tricycleNo,
            'voucher_number' => $voucherNumber,
            'driver_name' => $driverName,
            'claim_date' => $claimDate,
            'fuel_type' => $fuelType,
            'liters' => $liters,
            'pump_price' => $entryPumpPrice
        ];
    }
}

if (empty($orderedSelections)) {
    die('Please select at least one tricycle.');
}

$pumpPrice = isset($_POST['pump_price']) ? (float)$_POST['pump_price'] : 0;

$dateFilter = '';
$dateRangeText = 'All Dates';

if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
    $startDate = mysqli_real_escape_string($conn, $_POST['start_date']);
    $dateFilter .= " AND DATE(vc.claim_date) >= '$startDate' ";
    $dateRangeText = 'From ' . date('F j, Y', strtotime($startDate));
}

if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
    $endDate = mysqli_real_escape_string($conn, $_POST['end_date']);
    $dateFilter .= " AND DATE(vc.claim_date) <= '$endDate' ";
    $dateRangeText = isset($startDate)
        ? $dateRangeText . ' to ' . date('F j, Y', strtotime($endDate))
        : 'Until ' . date('F j, Y', strtotime($endDate));
}

require_once '../../fpdf/fpdf.php';

function formatRoundedAmount($amount) {
    return number_format(round((float)$amount), 0);
}

function formatVoucherRange($vouchers) {
    $numbers = [];
    foreach ($vouchers as $voucher) {
        $number = (int)$voucher;
        if ($number > 0) {
            $numbers[$number] = $number;
        }
    }

    if (empty($numbers)) {
        return '';
    }

    sort($numbers, SORT_NUMERIC);
    $ranges = [];
    $start = $numbers[0];
    $previous = $numbers[0];

    for ($i = 1; $i < count($numbers); $i++) {
        $current = $numbers[$i];
        if ($current === $previous + 1) {
            $previous = $current;
            continue;
        }

        $ranges[] = $start === $previous
            ? str_pad((string)$start, 3, '0', STR_PAD_LEFT)
            : str_pad((string)$start, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string)$previous, 3, '0', STR_PAD_LEFT);
        $start = $current;
        $previous = $current;
    }

    $ranges[] = $start === $previous
        ? str_pad((string)$start, 3, '0', STR_PAD_LEFT)
        : str_pad((string)$start, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string)$previous, 3, '0', STR_PAD_LEFT);

    return implode(', ', $ranges);
}

function findClaimForSelection($conn, $stationId, $tricycleNo, $voucherNumber) {
    $safeTricycleNo = mysqli_real_escape_string($conn, $tricycleNo);
    $voucherFilter = $voucherNumber !== '' ? ' AND vc.voucher_number = ' . (int)$voucherNumber . ' ' : '';
    $stationOrder = (int)$stationId;

    $sql = "SELECT tr.driver_name, vc.claim_date, vc.e_signature
            FROM voucher_claims vc
            INNER JOIN tricycle_records tr ON vc.tricycle_id = tr.id
            WHERE tr.tricycle_no = '$safeTricycleNo'
            $voucherFilter
            AND vc.e_signature IS NOT NULL AND vc.e_signature != ''
            ORDER BY CASE WHEN vc.station_id = $stationOrder THEN 0 ELSE 1 END, vc.claim_date DESC
            LIMIT 1";

    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }

    return null;
}

$currentDate = date('F j, Y');
$reportDateText = $currentDate;

if (!empty($endDate)) {
    $reportDateText = date('F j, Y', strtotime($endDate));
} elseif (!empty($startDate)) {
    $reportDateText = date('F j, Y', strtotime($startDate));
}

$groupedData = [];
$totalVouchers = 0;
$boatVouchers = 0;
$boatFueledPrice = 0;
$boatLiters = 0;
$boatCount = 0;
$tricycleVouchers = 0;
$tricycleFueledPrice = 0;
$tricycleLiters = 0;
$tricycleCount = 0;
$trackedVehicles = [];
$countedAmountGroups = [];
$fuelBreakdown = [
    'Silver' => ['liters' => 0, 'amount' => 0],
    'Platinum' => ['liters' => 0, 'amount' => 0],
    'Diesel' => ['liters' => 0, 'amount' => 0]
];

foreach ($orderedSelections as $orderIndex => $selection) {
    $tricycleNo = $selection['tricycle_no'];
    $voucherNumber = $selection['voucher_number'];
    $fuelType = $selection['fuel_type'];
    $liters = (float)$selection['liters'];
    $entryPumpPrice = (float)$selection['pump_price'];
    $entryAmount = $liters * $entryPumpPrice;
    $safeTricycleNo = mysqli_real_escape_string($conn, $tricycleNo);
    $voucherFilter = $voucherNumber !== '' ? ' AND vc.voucher_number = ' . (int)$voucherNumber . ' ' : '';
    $matchedRows = 0;
    $sql = "SELECT tr.driver_name, tr.tricycle_no, vc.voucher_number, vc.claim_date, vc.e_signature, gs.station_name
            FROM voucher_claims vc
            INNER JOIN tricycle_records tr ON vc.tricycle_id = tr.id
            LEFT JOIN gas_stations gs ON vc.station_id = gs.id
            WHERE vc.e_signature IS NOT NULL AND vc.e_signature != ''
            AND vc.station_id = $station_id
            AND tr.tricycle_no = '$safeTricycleNo'
            $voucherFilter
            $dateFilter
            ORDER BY vc.claim_date, vc.voucher_number";

    $result = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $matchedRows++;
        $key = $row['tricycle_no'] . '_' . $fuelType . '_' . $entryPumpPrice . '_' . $liters;

        if (!isset($groupedData[$key])) {
            $isBoat = strlen($row['tricycle_no']) > 4;
            $groupedData[$key] = [
                'driver_name' => $row['driver_name'],
                'tricycle_no' => $row['tricycle_no'],
                'vouchers' => [],
                'claim_date' => $row['claim_date'],
                'e_signature' => $row['e_signature'],
                'is_boat' => $isBoat,
                'fuel_type' => $fuelType,
                'liters' => $liters,
                'pump_price' => $entryPumpPrice,
                'amount' => $entryAmount,
                'counted_totals' => false
            ];

            if (!isset($trackedVehicles[$key])) {
                $trackedVehicles[$key] = true;
                if ($isBoat) {
                    $boatCount++;
                } else {
                    $tricycleCount++;
                }
            }
        }

        if (empty($groupedData[$key]['e_signature']) && !empty($row['e_signature'])) {
            $groupedData[$key]['e_signature'] = $row['e_signature'];
        }
        $groupedData[$key]['vouchers'][] = $row['voucher_number'];
        $totalVouchers++;

        $totalGroupKey = strtolower($row['tricycle_no']);
        if (!isset($countedAmountGroups[$totalGroupKey])) {
            if (strlen($row['tricycle_no']) > 4) {
                $boatFueledPrice += $entryAmount;
                $boatLiters += $liters;
            } else {
                $tricycleFueledPrice += $entryAmount;
                $tricycleLiters += $liters;
            }
            $countedAmountGroups[$totalGroupKey] = true;
            if (!isset($fuelBreakdown[$fuelType])) {
                $fuelBreakdown[$fuelType] = ['liters' => 0, 'amount' => 0];
            }
            $fuelBreakdown[$fuelType]['liters'] += $liters;
            $fuelBreakdown[$fuelType]['amount'] += $entryAmount;
            $groupedData[$key]['counted_totals'] = true;
        }

        if (strlen($row['tricycle_no']) > 4) {
            $boatVouchers++;
        } else {
            $tricycleVouchers++;
        }
    }

    if ($matchedRows === 0) {
        $matchedClaim = findClaimForSelection($conn, $station_id, $tricycleNo, $voucherNumber);
        $fallbackDate = !empty($matchedClaim['claim_date'])
            ? date('Y-m-d', strtotime($matchedClaim['claim_date']))
            : date('Y-m-d', strtotime($selection['claim_date']));
        $fallbackDriver = !empty($matchedClaim['driver_name']) ? $matchedClaim['driver_name'] : $selection['driver_name'];
        $fallbackSignature = !empty($matchedClaim['e_signature']) ? $matchedClaim['e_signature'] : '';
        $key = $tricycleNo . '_' . $fuelType . '_' . $entryPumpPrice . '_' . $liters;

        if (!isset($groupedData[$key])) {
            $isBoat = strlen($tricycleNo) > 4;
            $groupedData[$key] = [
                'driver_name' => $fallbackDriver,
                'tricycle_no' => $tricycleNo,
                'vouchers' => [],
                'claim_date' => $fallbackDate,
                'e_signature' => $fallbackSignature,
                'is_boat' => $isBoat,
                'fuel_type' => $fuelType,
                'liters' => $liters,
                'pump_price' => $entryPumpPrice,
                'amount' => $entryAmount,
                'counted_totals' => false
            ];

            if (!isset($trackedVehicles[$key])) {
                $trackedVehicles[$key] = true;
                if ($isBoat) {
                    $boatCount++;
                } else {
                    $tricycleCount++;
                }
            }
        }

        if (empty($groupedData[$key]['e_signature']) && $fallbackSignature !== '') {
            $groupedData[$key]['e_signature'] = $fallbackSignature;
        }
        if ($voucherNumber !== '') {
            $groupedData[$key]['vouchers'][] = $voucherNumber;
            $totalVouchers++;
        }

        $totalGroupKey = strtolower($tricycleNo);
        if (!isset($countedAmountGroups[$totalGroupKey])) {
            if (strlen($tricycleNo) > 4) {
                $boatFueledPrice += $entryAmount;
                $boatLiters += $liters;
                $boatVouchers++;
            } else {
                $tricycleFueledPrice += $entryAmount;
                $tricycleLiters += $liters;
                $tricycleVouchers++;
            }
            $countedAmountGroups[$totalGroupKey] = true;
            if (!isset($fuelBreakdown[$fuelType])) {
                $fuelBreakdown[$fuelType] = ['liters' => 0, 'amount' => 0];
            }
            $fuelBreakdown[$fuelType]['liters'] += $liters;
            $fuelBreakdown[$fuelType]['amount'] += $entryAmount;
            $groupedData[$key]['counted_totals'] = true;
        }
    }
}

$totalFueledPrice = $tricycleFueledPrice + $boatFueledPrice;
$totalLiters = $tricycleLiters + $boatLiters;

define('COL_NAME', 40);
define('COL_TRIC', 24);
define('COL_VOUCH', 34);
define('COL_DATE', 28);
define('COL_TYPE', 24);
define('COL_LITERS', 22);
define('COL_PRICE', 26);
define('COL_AMOUNT', 28);
define('COL_SIG', 44);
define('LINE_HEIGHT', 5);
define('MIN_ROW_H', 20);
define('LEFT_MARGIN', 10);

class PDF extends FPDF
{
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'CLAIMED VOUCHERS AS OF ' . $GLOBALS['reportDateText'], 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 6, $GLOBALS['dateRangeText'], 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function FixedCell($x, $y, $w, $h, $txt, $lineH = LINE_HEIGHT) {
        $this->Rect($x, $y, $w, $h);

        if ($txt === '') return;

        $innerW = $w - 6;
        $lines = $this->wordWrapText($txt, $innerW);
        $blockH = count($lines) * $lineH;
        $startY = $y + ($h - $blockH) / 2;

        foreach ($lines as $i => $line) {
            $lineY = $startY + $i * $lineH;
            $this->SetXY($x, $lineY);
            $this->Cell($w, $lineH, $line, 0, 0, 'C');
        }
    }

    function wordWrapText($txt, $maxW) {
        $words = explode(' ', $txt);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if ($this->GetStringWidth($test) <= $maxW) {
                $current = $test;
            } else {
                if ($current !== '') $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') $lines[] = $current;

        return $lines;
    }

    function calcRowHeight($txt, $colW, $lineH = LINE_HEIGHT, $minH = MIN_ROW_H) {
        $innerW = $colW - 6;
        $lines = $this->wordWrapText($txt, $innerW);
        $needed = count($lines) * $lineH + 10;
        return max($minH, $needed);
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(LEFT_MARGIN, 10, 10);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$pdf->SetFillColor(200, 200, 200);
$pdf->SetFont('Arial', 'B', 10);
$headerH = 10;
$hx = LEFT_MARGIN;
$hy = $pdf->GetY();

$pdf->SetXY($hx, $hy);
$pdf->Cell(COL_NAME, $headerH, 'Name', 1, 0, 'C', true);
$pdf->Cell(COL_TRIC, $headerH, 'Tricycle No.', 1, 0, 'C', true);
$pdf->Cell(COL_VOUCH, $headerH, 'Voucher No. Claimed', 1, 0, 'C', true);
$pdf->Cell(COL_DATE, $headerH, 'Date Claimed', 1, 0, 'C', true);
$pdf->Cell(COL_TYPE, $headerH, 'Type', 1, 0, 'C', true);
$pdf->Cell(COL_LITERS, $headerH, 'Liters', 1, 0, 'C', true);
$pdf->Cell(COL_PRICE, $headerH, 'Pump Price', 1, 0, 'C', true);
$pdf->Cell(COL_AMOUNT, $headerH, 'Amount', 1, 0, 'C', true);
$pdf->Cell(COL_SIG, $headerH, 'E-Signature', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

if (empty($groupedData)) {
    $pdf->Cell(COL_NAME + COL_TRIC + COL_VOUCH + COL_DATE + COL_TYPE + COL_LITERS + COL_PRICE + COL_AMOUNT + COL_SIG, 10, 'No claimed vouchers found for the selected tricycles.', 1, 1, 'C');
} else {
    foreach ($groupedData as $data) {
        $voucherRange = formatVoucherRange($data['vouchers']);
        $voucherStr = $voucherRange !== '' ? $data['tricycle_no'] . ' ' . $voucherRange : $data['tricycle_no'];
        $litersStr = number_format((float)$data['liters'], 2);
        $priceStr = number_format((float)$data['pump_price'], 2);
        $amountStr = formatRoundedAmount($data['amount']);

        $h1 = $pdf->calcRowHeight($data['driver_name'], COL_NAME);
        $h2 = $pdf->calcRowHeight($data['tricycle_no'], COL_TRIC);
        $h3 = $pdf->calcRowHeight($voucherStr, COL_VOUCH);
        $h4 = $pdf->calcRowHeight(date('M j, Y', strtotime($data['claim_date'])), COL_DATE);
        $h5 = $pdf->calcRowHeight($data['fuel_type'], COL_TYPE);
        $rowH = max($h1, $h2, $h3, $h4, $h5, MIN_ROW_H);

        if ($pdf->GetY() + $rowH > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
            $pdf->SetFillColor(200, 200, 200);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(COL_NAME, $headerH, 'Name', 1, 0, 'C', true);
            $pdf->Cell(COL_TRIC, $headerH, 'Tricycle No.', 1, 0, 'C', true);
            $pdf->Cell(COL_VOUCH, $headerH, 'Voucher No. Claimed', 1, 0, 'C', true);
            $pdf->Cell(COL_DATE, $headerH, 'Date Claimed', 1, 0, 'C', true);
            $pdf->Cell(COL_TYPE, $headerH, 'Type', 1, 0, 'C', true);
            $pdf->Cell(COL_LITERS, $headerH, 'Liters', 1, 0, 'C', true);
            $pdf->Cell(COL_PRICE, $headerH, 'Pump Price', 1, 0, 'C', true);
            $pdf->Cell(COL_AMOUNT, $headerH, 'Amount', 1, 0, 'C', true);
            $pdf->Cell(COL_SIG, $headerH, 'E-Signature', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
        }

        $rowX = LEFT_MARGIN;
        $rowY = $pdf->GetY();

        $xName = $rowX;
        $xTric = $xName + COL_NAME;
        $xVouch = $xTric + COL_TRIC;
        $xDate = $xVouch + COL_VOUCH;
        $xType = $xDate + COL_DATE;
        $xLiters = $xType + COL_TYPE;
        $xPrice = $xLiters + COL_LITERS;
        $xAmount = $xPrice + COL_PRICE;
        $xSig = $xAmount + COL_AMOUNT;

        $pdf->FixedCell($xName, $rowY, COL_NAME, $rowH, $data['driver_name']);
        $pdf->FixedCell($xTric, $rowY, COL_TRIC, $rowH, $data['tricycle_no']);
        $pdf->FixedCell($xVouch, $rowY, COL_VOUCH, $rowH, $voucherStr);
        $pdf->FixedCell($xDate, $rowY, COL_DATE, $rowH, date('M j, Y', strtotime($data['claim_date'])));
        $pdf->FixedCell($xType, $rowY, COL_TYPE, $rowH, $data['fuel_type']);
        $pdf->FixedCell($xLiters, $rowY, COL_LITERS, $rowH, $litersStr);
        $pdf->FixedCell($xPrice, $rowY, COL_PRICE, $rowH, $priceStr);
        $pdf->FixedCell($xAmount, $rowY, COL_AMOUNT, $rowH, $amountStr);
        $pdf->Rect($xSig, $rowY, COL_SIG, $rowH);

        if (!empty($data['e_signature']) && strpos($data['e_signature'], 'data:image') === 0) {
            preg_match('/data:image\/(\w+);base64,/', $data['e_signature'], $typeMatch);
            $imageType = isset($typeMatch[1]) ? strtolower($typeMatch[1]) : 'png';
            $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $data['e_signature']);
            $imageData = base64_decode($base64String);

            $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.' . $imageType;
            file_put_contents($tempFile, $imageData);

            $imgW = 36;
            $imgH = min(16, $rowH - 4);
            $imgX = $xSig + (COL_SIG - $imgW) / 2;
            $imgY = $rowY + ($rowH - $imgH) / 2;

            $pdf->Image($tempFile, $imgX, $imgY, $imgW, $imgH);
            unlink($tempFile);
        }

        $pdf->SetXY($rowX, $rowY + $rowH);
    }
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'SUMMARY TOTALS', 0, 1, 'C');
$pdf->Ln(2);

$colWidth = 60;
$rowHeight = 8;

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell($colWidth, $rowHeight, 'SUMMARY', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'TRICYCLE', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'BOAT', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'TOTAL', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell($colWidth, $rowHeight, 'Total Amount (PHP)', 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, formatRoundedAmount($tricycleFueledPrice), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, formatRoundedAmount($boatFueledPrice), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, formatRoundedAmount($totalFueledPrice), 1, 1, 'C');

$pdf->Cell($colWidth, $rowHeight, 'Total Liters', 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($tricycleLiters, 2), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($boatLiters, 2), 1, 0, 'C');
$pdf->Cell($colWidth, $rowHeight, number_format($totalLiters, 2), 1, 1, 'C');

$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell($colWidth, $rowHeight, 'FUEL TYPE', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'LITERS', 1, 0, 'C', true);
$pdf->Cell($colWidth, $rowHeight, 'AMOUNT (PHP)', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
foreach (['Silver', 'Platinum', 'Diesel'] as $fuelType) {
    $pdf->Cell($colWidth, $rowHeight, strtoupper($fuelType), 1, 0, 'C');
    $pdf->Cell($colWidth, $rowHeight, number_format($fuelBreakdown[$fuelType]['liters'], 2), 1, 0, 'C');
    $pdf->Cell($colWidth, $rowHeight, formatRoundedAmount($fuelBreakdown[$fuelType]['amount']), 1, 1, 'C');
}

$pdf->SetY(-50);
$pdf->SetX(-80);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(70, 0, '', 'B', 1, 'C');


$pdf->Output('I', 'Manual_Claimed_Vouchers_' . date('Y-m-d') . '.pdf');
?>
