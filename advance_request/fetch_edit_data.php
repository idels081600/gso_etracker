<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Unauthorized access';
    exit;
}
require_once 'advance_po_db.php';

// First get the search term from GET parameter before closing connection
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Now query the data
$query = "SELECT id, store, date, invoice_number, description, pcs, unit_price, amount, status FROM advancePo WHERE delete_status = 0 ORDER BY id DESC";

$result = mysqli_query($conn, $query);

$records = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}

mysqli_close($conn);

$filteredRecords = $records;

if (!empty($searchTerm)) {
    $filteredRecords = array_filter($records, function($row) use ($searchTerm) {
        $searchLower = strtolower($searchTerm);
        return strpos(strtolower($row['store']), $searchLower) !== false ||
               strpos(strtolower($row['date']), $searchLower) !== false ||
               strpos(strtolower($row['invoice_number']), $searchLower) !== false ||
               strpos(strtolower($row['description']), $searchLower) !== false ||
               strpos(strtolower($row['pcs']), $searchLower) !== false ||
               strpos(strtolower($row['unit_price']), $searchLower) !== false ||
               strpos(strtolower($row['amount']), $searchLower) !== false ||
               strpos(strtolower($row['status']), $searchLower) !== false;
    });
}

if (count($filteredRecords) > 0) {
    $count = 1;
    foreach ($filteredRecords as $row) {
        echo "<tr>
                <th scope='row'>{$count}</th>
                <td>{$row['store']}</td>
                <td>{$row['date']}</td>
                <td>{$row['invoice_number']}</td>
                <td>{$row['description']}</td>
                <td>{$row['pcs']}</td>
                <td>₱" . number_format($row['unit_price'], 2) . "</td>
                <td>₱" . number_format($row['amount'], 2) . "</td>
                <td>{$row['status']}</td>
                <td class='text-center'>
                    <button class='btn btn-sm btn-outline-primary edit-btn' data-id='{$row['id']}' data-bs-toggle='modal' data-bs-target='#editRowModal' title='Edit'>
                        <i class='fas fa-edit'></i>
                    </button>
                    <button class='btn btn-sm btn-outline-danger delete-btn ms-1' data-id='{$row['id']}' title='Delete'>
                        <i class='fas fa-trash-alt'></i>
                    </button>
                </td>
              </tr>";
        $count++;
    }
} else {
    echo "<tr><td colspan='10' class='text-center'>No records found</td></tr>";
}
?>
