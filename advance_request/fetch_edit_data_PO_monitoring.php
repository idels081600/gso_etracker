<?php
session_start();

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: text/html');
header('Access-Control-Allow-Origin: *');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'advance_po_db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// First get the search term from GET parameter before closing connection
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Now query the data
$query = "SELECT id, supplier, po_date, po_number, description, office, price, destination, "
       . "preAuditchecklist_cb, obr_cb, dv_cb, billing_request_cb, certWarranty_cb, omnibus_cb, ris_cb, acceptance_cb, rfq_cb, recommending_cb, PR_cb, PO_cb, receipts_cb, delegation_cb, mayorsPermit_cb, jetsCert_cb, "
       . "status FROM poMonitoring WHERE delete_status = 0 ORDER BY id DESC";

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
        return strpos(strtolower($row['supplier']), $searchLower) !== false ||
               strpos(strtolower($row['po_date']), $searchLower) !== false ||
               strpos(strtolower($row['po_number']), $searchLower) !== false ||
               strpos(strtolower($row['description']), $searchLower) !== false ||
               strpos(strtolower($row['office']), $searchLower) !== false ||
               strpos(strtolower($row['price']), $searchLower) !== false ||
               strpos(strtolower($row['destination']), $searchLower) !== false ||
               strpos(strtolower($row['status']), $searchLower) !== false;
    });
}

if (count($filteredRecords) > 0) {
    $count = 1;
    foreach ($filteredRecords as $row) {
        // Calculate lacking (count of unchecked checkboxes)
        $totalCheckboxes = 20; // Count of checkboxes in the form
        $checkedCount = 0;
        $checkboxes = ['preAuditchecklist_cb', 'obr_cb', 'dv_cb', 'billing_request_cb', 'certWarranty_cb', 'omnibus_cb', 'ris_cb', 'acceptance_cb', 'rfq_cb', 'recommending_cb', 'PR_cb', 'PO_cb', 'receipts_cb', 'delegation_cb', 'mayorsPermit_cb', 'jetsCert_cb'];

        foreach ($checkboxes as $cb) {
            if ($row[$cb] == '1' || $row[$cb] == 'on' || $row[$cb] == true) {
                $checkedCount++;
            }
        }
        $lacking = $totalCheckboxes - $checkedCount;

        echo "<tr>
                <th scope='row'>{$count}</th>
                <td>{$row['supplier']}</td>
                <td>{$row['po_date']}</td>
                <td>{$row['po_number']}</td>
                <td>{$row['description']}</td>
                <td>{$row['office']}</td>
                <td>₱" . number_format($row['price'], 2) . "</td>
                <td>{$row['destination']}</td>
                <td>{$lacking}</td>
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
    echo "<tr><td colspan='11' class='text-center'>No records found</td></tr>";
}
?>
