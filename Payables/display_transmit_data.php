<?php
require_once "transmit_db.php";

function display_transmittal_bac_data() {
    global $conn;
    $sql = "SELECT * FROM transmittal_bac WHERE delete_status=0 ORDER BY id DESC";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['ib_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['project_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['date_received']) . '</td>';
            echo '<td>' . htmlspecialchars($row['office']) . '</td>';
            echo '<td>' . htmlspecialchars($row['received_by']) . '</td>';
            echo '<td>' . htmlspecialchars($row['winning_bidders']) . '</td>';
            echo '<td>' . htmlspecialchars($row['NOA_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['COA_date']) . '</td>';
            echo '<td>' . htmlspecialchars($row['notice_proceed']) . '</td>';
            echo '<td>' . htmlspecialchars($row['deadline']) . '</td>';
            echo '<td class="text-center">';
            echo '<button class="btn btn-sm btn-primary edit-btn" data-id="' . $row['id'] . '" title="Edit"><i class="fas fa-edit"></i></button> ';
            echo '<button class="btn btn-sm btn-danger delete-btn" data-id="' . $row['id'] . '" title="Delete"><i class="fas fa-trash-alt"></i></button>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="12" class="text-center">No data found.</td></tr>';
    }
}
?>