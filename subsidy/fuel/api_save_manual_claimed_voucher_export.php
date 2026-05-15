<?php
session_start();
require_once 'db_fuel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request']);
    exit();
}

$username = mysqli_real_escape_string($conn, $_SESSION['username']);
$station_id = isset($_SESSION['station_id']) ? (int)$_SESSION['station_id'] : 0;
$station_name = isset($_SESSION['station_name']) ? $_SESSION['station_name'] : 'Unknown Station';

if (isset($input['station_id']) && (int)$input['station_id'] > 0) {
    $station_id = (int)$input['station_id'];
    $station_sql = "SELECT station_name FROM gas_stations WHERE id = $station_id";
    $station_result = mysqli_query($conn, $station_sql);
    if ($station_result && mysqli_num_rows($station_result) > 0) {
        $station_row = mysqli_fetch_assoc($station_result);
        $station_name = $station_row['station_name'];
    }
}

if ($station_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No station selected.']);
    exit();
}

$selectedEntries = isset($input['selected_entries']) ? $input['selected_entries'] : [];
if (!is_array($selectedEntries) || empty($selectedEntries)) {
    echo json_encode(['success' => false, 'message' => 'No selected entries to save.']);
    exit();
}

$orderedSelections = [];
$selectionKeys = [];
foreach ($selectedEntries as $entry) {
    $tricycleNo = is_array($entry) && isset($entry['tricycle_no']) ? trim((string)$entry['tricycle_no']) : '';
    $voucherNumber = is_array($entry) && isset($entry['voucher_number']) ? trim((string)$entry['voucher_number']) : '';
    $voucherNumber = $voucherNumber !== '' ? (int)ltrim($voucherNumber, '0') : '';
    $key = $tricycleNo . '|' . $voucherNumber;

    if ($tricycleNo !== '' && !isset($selectionKeys[$key])) {
        $fuelType = is_array($entry) && isset($entry['fuel_type']) ? trim((string)$entry['fuel_type']) : 'Silver';
        $validFuelTypes = ['Silver', 'Platinum', 'Diesel'];
        if (!in_array($fuelType, $validFuelTypes, true)) {
            $fuelType = 'Silver';
        }

        $selectionKeys[$key] = true;
        $orderedSelections[] = [
            'tricycle_no' => $tricycleNo,
            'voucher_number' => $voucherNumber,
            'driver_name' => is_array($entry) && isset($entry['driver_name']) ? trim((string)$entry['driver_name']) : 'Manual entry',
            'claim_date' => is_array($entry) && !empty($entry['claim_date']) ? date('Y-m-d', strtotime($entry['claim_date'])) : date('Y-m-d'),
            'fuel_type' => $fuelType,
            'liters' => is_array($entry) && isset($entry['liters']) ? (float)$entry['liters'] : 0,
            'pump_price' => is_array($entry) && isset($entry['pump_price']) ? (float)$entry['pump_price'] : 0
        ];
    }
}

if (empty($orderedSelections)) {
    echo json_encode(['success' => false, 'message' => 'No valid selected entries to save.']);
    exit();
}

$dateFilter = '';
$dateRangeText = 'All Dates';
$startDateValue = 'NULL';
$endDateValue = 'NULL';

if (!empty($input['start_date'])) {
    $startDate = mysqli_real_escape_string($conn, $input['start_date']);
    $dateFilter .= " AND DATE(vc.claim_date) >= '$startDate' ";
    $dateRangeText = 'From ' . date('F j, Y', strtotime($startDate));
    $startDateValue = "'$startDate'";
}

if (!empty($input['end_date'])) {
    $endDate = mysqli_real_escape_string($conn, $input['end_date']);
    $dateFilter .= " AND DATE(vc.claim_date) <= '$endDate' ";
    $dateRangeText = isset($startDate)
        ? $dateRangeText . ' to ' . date('F j, Y', strtotime($endDate))
        : 'Until ' . date('F j, Y', strtotime($endDate));
    $endDateValue = "'$endDate'";
}

$groupedData = [];
$totalVouchers = 0;
$boatAmount = 0;
$boatLiters = 0;
$tricycleAmount = 0;
$tricycleLiters = 0;
$countedAmountGroups = [];

foreach ($orderedSelections as $selection) {
    $tricycleNo = $selection['tricycle_no'];
    $voucherNumber = $selection['voucher_number'];
    $fuelType = $selection['fuel_type'];
    $liters = (float)$selection['liters'];
    $pumpPrice = (float)$selection['pump_price'];
    $amount = $liters * $pumpPrice;
    $safeTricycleNo = mysqli_real_escape_string($conn, $tricycleNo);
    $voucherFilter = $voucherNumber !== '' ? ' AND vc.voucher_number = ' . (int)$voucherNumber . ' ' : '';

    $sql = "SELECT tr.driver_name, tr.tricycle_no, vc.voucher_number, vc.claim_date, vc.e_signature
            FROM voucher_claims vc
            INNER JOIN tricycle_records tr ON vc.tricycle_id = tr.id
            WHERE vc.e_signature IS NOT NULL AND vc.e_signature != ''
            AND vc.station_id = $station_id
            AND tr.tricycle_no = '$safeTricycleNo'
            $voucherFilter
            $dateFilter
            ORDER BY vc.claim_date, vc.voucher_number";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error reading voucher claims: ' . mysqli_error($conn)]);
        exit();
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $claimDate = date('Y-m-d', strtotime($row['claim_date']));
        $key = $row['tricycle_no'] . '_' . $fuelType . '_' . $pumpPrice . '_' . $liters . '_' . $claimDate;

        if (!isset($groupedData[$key])) {
            $isBoat = strlen($row['tricycle_no']) > 4;
            $groupedData[$key] = [
                'driver_name' => $row['driver_name'],
                'tricycle_no' => $row['tricycle_no'],
                'vouchers' => [],
                'claim_date' => $claimDate,
                'e_signature' => $row['e_signature'],
                'is_boat' => $isBoat,
                'fuel_type' => $fuelType,
                'liters' => $liters,
                'pump_price' => $pumpPrice,
                'amount' => $amount,
                'counted_totals' => false
            ];
        }

        $groupedData[$key]['vouchers'][] = $row['voucher_number'];
        $totalVouchers++;

        $totalGroupKey = strtolower($row['tricycle_no']);
        if (!isset($countedAmountGroups[$totalGroupKey])) {
            if ($groupedData[$key]['is_boat']) {
                $boatAmount += $amount;
                $boatLiters += $liters;
            } else {
                $tricycleAmount += $amount;
                $tricycleLiters += $liters;
            }
            $countedAmountGroups[$totalGroupKey] = true;
            $groupedData[$key]['counted_totals'] = true;
        }
    }
}

if (empty($groupedData)) {
    foreach ($orderedSelections as $selection) {
        $claimDate = $selection['claim_date'];

        $tricycleNo = $selection['tricycle_no'];
        $fuelType = $selection['fuel_type'];
        $liters = (float)$selection['liters'];
        $pumpPrice = (float)$selection['pump_price'];
        $amount = $liters * $pumpPrice;
        $key = $tricycleNo . '_' . $fuelType . '_' . $pumpPrice . '_' . $liters . '_' . $claimDate;

        if (!isset($groupedData[$key])) {
            $isBoat = strlen($tricycleNo) > 4;
            $groupedData[$key] = [
                'driver_name' => $selection['driver_name'],
                'tricycle_no' => $tricycleNo,
                'vouchers' => [],
                'claim_date' => $claimDate,
                'e_signature' => '',
                'is_boat' => $isBoat,
                'fuel_type' => $fuelType,
                'liters' => $liters,
                'pump_price' => $pumpPrice,
                'amount' => $amount,
                'counted_totals' => false
            ];
        }

        if ($selection['voucher_number'] !== '') {
            $groupedData[$key]['vouchers'][] = $selection['voucher_number'];
            $totalVouchers++;
        }

        $totalGroupKey = strtolower($tricycleNo);
        if (!isset($countedAmountGroups[$totalGroupKey])) {
            if ($groupedData[$key]['is_boat']) {
                $boatAmount += $amount;
                $boatLiters += $liters;
            } else {
                $tricycleAmount += $amount;
                $tricycleLiters += $liters;
            }
            $countedAmountGroups[$totalGroupKey] = true;
            $groupedData[$key]['counted_totals'] = true;
        }
    }
}

if (empty($groupedData)) {
    echo json_encode(['success' => false, 'message' => 'No selected rows found to save.']);
    exit();
}

$totalAmount = $tricycleAmount + $boatAmount;
$totalLiters = $tricycleLiters + $boatLiters;
$stationNameSql = mysqli_real_escape_string($conn, $station_name);
$dateRangeSql = mysqli_real_escape_string($conn, $dateRangeText);
$selectedEntriesJson = mysqli_real_escape_string($conn, json_encode($selectedEntries));
$exportCode = 'MCV-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

mysqli_begin_transaction($conn);

try {
    $headerSql = "INSERT INTO manual_claimed_voucher_exports
        (export_code, username, station_id, station_name, date_range_text, start_date, end_date,
         tricycle_amount, boat_amount, total_amount, tricycle_liters, boat_liters, total_liters,
         total_vouchers, selected_entries_json)
        VALUES
        ('$exportCode', '$username', $station_id, '$stationNameSql', '$dateRangeSql', $startDateValue, $endDateValue,
         $tricycleAmount, $boatAmount, $totalAmount, $tricycleLiters, $boatLiters, $totalLiters,
         $totalVouchers, '$selectedEntriesJson')";

    if (!mysqli_query($conn, $headerSql)) {
        throw new Exception(mysqli_error($conn));
    }

    $exportId = mysqli_insert_id($conn);
    $rowOrder = 1;

    foreach ($groupedData as $data) {
        $vouchers = [];
        foreach ($data['vouchers'] as $voucherNumber) {
            $vouchers[] = $data['tricycle_no'] . '-' . str_pad((string)$voucherNumber, 3, '0', STR_PAD_LEFT);
        }

        $driverName = mysqli_real_escape_string($conn, $data['driver_name']);
        $tricycleNo = mysqli_real_escape_string($conn, $data['tricycle_no']);
        $voucherNumbers = mysqli_real_escape_string($conn, implode(', ', $vouchers));
        $fuelType = mysqli_real_escape_string($conn, $data['fuel_type']);
        $claimDate = mysqli_real_escape_string($conn, $data['claim_date']);
        $eSignature = mysqli_real_escape_string($conn, $data['e_signature']);
        $isBoat = $data['is_boat'] ? 1 : 0;
        $liters = (float)$data['liters'];
        $pumpPrice = (float)$data['pump_price'];
        $amount = (float)$data['amount'];

        $itemSql = "INSERT INTO manual_claimed_voucher_export_items
            (export_id, row_order, driver_name, tricycle_no, voucher_numbers, claim_date,
             fuel_type, liters, pump_price, amount, is_boat, e_signature)
            VALUES
            ($exportId, $rowOrder, '$driverName', '$tricycleNo', '$voucherNumbers', '$claimDate',
             '$fuelType', $liters, $pumpPrice, $amount, $isBoat, '$eSignature')";

        if (!mysqli_query($conn, $itemSql)) {
            throw new Exception(mysqli_error($conn));
        }

        $rowOrder++;
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Export saved.',
        'export_id' => $exportId,
        'export_code' => $exportCode,
        'rows_saved' => count($groupedData),
        'total_amount' => $totalAmount,
        'total_liters' => $totalLiters
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error saving export: ' . $e->getMessage()]);
}
?>
