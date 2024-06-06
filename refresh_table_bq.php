<?php
include 'db.php';
$query = "SELECT * FROM `bq_print` ORDER BY `id` DESC"; // Modify this query according to your database structure
$result = mysqli_query($conn, $query);

// Check if the query was successful
if ($result) {
    // Start building the HTML table rows
    $html = '';
    while ($row = mysqli_fetch_assoc($result)) {
        $html .= '<tr class="clickable-row3" data-rfq-id="' . $row['id'] . '">';
        $html .= '<td>' . $row["SR_DR"] . '</td>';
        $html .= '<td>' . $row["date"] . '</td>';
        $html .= '<td>' . $row["supplier"] . '</td>';
        $html .= '<td>' . $row["requestor"] . '</td>';
        $html .= '<td>' . $row["activity"] . '</td>';
        $html .= '<td>' . $row["description"] . '</td>';
        $html .= '<td>' . $row["quantity"] . '</td>';
        $html .= '<td>₱' . number_format($row["amount"], 2) . '</td>';
        $html .= '</tr>';
    }

    // Output the HTML table rows
    echo $html;
} else {
    // If the query failed, output an error message
    echo "Error fetching data from the database.";
}
