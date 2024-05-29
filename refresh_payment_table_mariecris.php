<?php
include 'db.php';
$query = "SELECT * FROM `Maam_mariecris_payments` ORDER BY `id` DESC"; // Modify this query according to your database structure
$result = mysqli_query($conn, $query);

// Check if the query was successful
if ($result) {
    // Start building the HTML table rows
    $html = '';
    while ($row = mysqli_fetch_assoc($result)) {
        $html .= '<tr class="clickable-row3" data-rfq-id="' . $row['id'] . '">';
        $html .= '<td>' . $row["name"] . '</td>';
        $html .= '<td>₱' . number_format($row["amount"], 2) . '</td>';
        $html .= '</tr>';
    }

    // Output the HTML table rows
    echo $html;
} else {
    // If the query failed, output an error message
    echo "Error fetching data from the database.";
}
