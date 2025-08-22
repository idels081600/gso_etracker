<?php
header('Content-Type: application/json');
require_once 'db_asset.php';

try {
    // Get today's date
    $today = date('Y-m-d');

    // Fetch Pending status for today only
    $query_pending = "SELECT t.id, t.name, t.contact_no, t.location, t.status, t.tent_no, t.date, t.no_of_tents
FROM tent t
WHERE t.status = 'Pending'
AND DATE(t.date) = '$today'
ORDER BY t.date DESC";
    $result_pending = mysqli_query($conn, $query_pending);

    // Fetch For Retrieval status for today only
    $query_for_retrieval = "SELECT t.id, t.name, t.contact_no, t.location, t.status, t.tent_no, t.retrieval_date, t.no_of_tents
           FROM tent t
           WHERE t.status = 'For Retrieval'
           AND DATE(t.retrieval_date) = '$today'
           ORDER BY t.retrieval_date DESC";
    $result_for_retrieval = mysqli_query($conn, $query_for_retrieval);

    // Note: Installed status records are excluded as per requirements

    $data = array();

    // Process For Retrieval records
    if ($result_for_retrieval) {
        while ($row = mysqli_fetch_assoc($result_for_retrieval)) {
            $todayClass = ($row['retrieval_date'] == $today) ? 'today-record' : '';
            $data[] = array(
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name'] ?? ''),
                'location' => htmlspecialchars($row['location'] ?? ''),
                'contact_no' => htmlspecialchars($row['contact_no'] ?? ''),
                'no_of_tents' => htmlspecialchars($row['no_of_tents'] ?? ''),
                'date' => htmlspecialchars($row['retrieval_date'] ?? ''),
                'status' => htmlspecialchars($row['status'] ?? ''),
                'tent_no' => htmlspecialchars($row['tent_no'] ?? ''),
                'row_class' => 'for-retrieval-row ' . $todayClass,
                'type' => 'for_retrieval'
            );
        }
    }

    // Note: Installed records processing removed as per requirements

    // Process Pending records
    if ($result_pending) {
        while ($row = mysqli_fetch_assoc($result_pending)) {
            $data[] = array(
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name'] ?? ''),
                'location' => htmlspecialchars($row['location'] ?? ''),
                'contact_no' => htmlspecialchars($row['contact_no'] ?? ''),
                'no_of_tents' => htmlspecialchars($row['no_of_tents'] ?? ''),
                'date' => htmlspecialchars($row['date'] ?? ''),
                'status' => htmlspecialchars($row['status'] ?? ''),
                'tent_no' => htmlspecialchars($row['tent_no'] ?? ''),
                'row_class' => 'pending-row',
                'type' => 'pending'
            );
        }
    }

    // Close database connection
    mysqli_close($conn);

    // Return JSON response
    echo json_encode(array(
        'success' => true,
        'data' => $data,
        'total_records' => count($data),
        'timestamp' => date('Y-m-d H:i:s')
    ));

} catch (Exception $e) {
    // Return error response
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ));
}
?>
