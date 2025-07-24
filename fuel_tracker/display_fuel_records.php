<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../db_asset.php';

try {
    // Query to get all records
    $sql = "SELECT * FROM fuel ORDER BY date DESC";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($result)) {
        // Format date
        $date = new DateTime($row['date']);
        $formattedDate = $date->format('Y-m-d');
        
        // Get time difference
        $now = new DateTime();
        $interval = $now->diff($date);
        $relativeDate = "";
        
        if ($interval->days == 0) {
            $relativeDate = "Today";
        } elseif ($interval->days == 1) {
            $relativeDate = "Yesterday";
        } elseif ($interval->days <= 7) {
            $relativeDate = $interval->days . " days ago";
        } elseif ($interval->days <= 30) {
            $relativeDate = ceil($interval->days/7) . " weeks ago";
        } else {
            $relativeDate = ceil($interval->days/30) . " months ago";
        }

        // Get badge class for fuel type
        $badgeClass = "bg-secondary";
        switch(strtolower($row['fuel_type'])) {
            case 'unleaded':
                $badgeClass = "bg-success";
                break;
            case 'diesel':
                $badgeClass = "bg-warning text-dark";
                break;
            case 'premium':
                $badgeClass = "bg-primary";
                break;
        }

        // Output table row
        echo "<tr>
                <td>
                    <input type='checkbox' class='form-check-input row-checkbox' value='{$row['id']}'>
                </td>
                <td>
                    <span class='fw-medium'>$formattedDate</span>
                    <small class='text-muted d-block'>$relativeDate</small>
                </td>
                <td>
                    <span class='badge bg-light text-dark'>{$row['office']}</span>
                </td>
                <td>{$row['vehicle']}</td>
                <td>
                    <span class='font-monospace'>{$row['plate_no']}</span>
                </td>
                <td>{$row['driver']}</td>
                <td>
                    <span class='text-truncate d-inline-block' style='max-width: 150px;' 
                          title='{$row['purpose']}'>
                        {$row['purpose']}
                    </span>
                </td>
                <td>
                    <span class='badge $badgeClass'>{$row['fuel_type']}</span>
                </td>
                <td>
                    <span class='fw-bold'>" . 
                    (empty($row['liters_issued']) ? "-" : number_format($row['liters_issued'], 2) . " L") .
                    "</span>
                </td>
                <td>
                    <span class='text-muted'>{$row['remarks']}</span>
                </td>
                <td>
                    <div class='btn-group btn-group-sm' role='group'>
                        <button type='button' class='btn btn-outline-primary action-view' title='View' onclick='window.viewRecord(\"{$row['id']}\")'>
                            <i class='fas fa-eye'></i>
                        </button>
                        <button type='button' class='btn btn-outline-warning action-edit' title='Edit' onclick='editRecord(\"{$row['id']}\", this.closest(\"tr\"))'>
                            <i class='fas fa-edit'></i>
                        </button>
                        <button type='button' class='btn btn-outline-danger action-delete' title='Delete' onclick='deleteRecord(\"{$row['id']}\", this.closest(\"tr\"))'>
                            <i class='fas fa-trash'></i>
                        </button>
                    </div>
                </td>
            </tr>";
    }

} catch (Exception $e) {
    echo "<tr><td colspan='11' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
}

// Close connection
mysqli_close($conn);
?>
