<?php
include 'db.php';
$query = "SELECT * FROM `Maam_mariecris_print` ORDER BY `id` DESC"; // Modify this query according to your database structure
$result = mysqli_query($conn, $query);

// Check if the query was successful
if ($result) {
    // Start building the HTML table rows
    $html = '';
    while ($row = mysqli_fetch_assoc($result)) {
        $html .= '<tr class="clickable-row3" data-rfq-id="' . $row['id'] . '">';
        $html .= '<td>' . $row["SR_DR"] . '</td>';
        $html .= '<td>' . $row["date"] . '</td>';
        $html .= '<td>' . $row["department"] . '</td>';
        $html .= '<td>' . $row["store"] . '</td>';
        $html .= '<td>' . $row["activity"] . '</td>';
        $html .= '<td>' . $row["no_of_pax"] . '</td>';
        $html .= '<td>₱' . number_format($row["amount"], 2) . '</td>';
        $html .= '<td>₱' . number_format($row["total"], 2) . '</td>';
        $html .= '</tr>';
    }

    // Output the HTML table rows
    echo $html;
} else {
    // If the query failed, output an error message
    echo "Error fetching data from the database.";
}
