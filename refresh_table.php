<?php
include 'db.php';
$query = "SELECT * FROM `sir_bayong_print` ORDER BY `id` DESC"; // Modify this query according to your database structure
$result = mysqli_query($conn, $query);

// Check if the query was successful
if ($result) {
    // Start building the HTML table rows
    $html = '';
    while ($row = mysqli_fetch_assoc($result)) {
        $html .= '<tr class="clickable-row3" data-rfq-id="' . $row['id'] . '">';
        $html .= '<td>' . $row["SR_DR"] . '</td>';
        $html .= '<td>' . $row["Date"] . '</td>';
        $html .= '<td>' . $row["Supplier"] . '</td>';
        $html .= '<td>' . $row["Description"] . '</td>';
        $html .= '<td>' . $row["Quantity"] . '</td>';
        $html .= '<td>₱' . number_format($row["Amount"], 2) . '</td>';
        $html .= '<td>' . $row["Office"] . '</td>';
        $html .= '<td>' . $row["Vehicle"] . '</td>';
        $html .= '<td>' . $row["Plate"] . '</td>';
        $html .= '</tr>';
    }

    // Output the HTML table rows
    echo $html;
} else {
    // If the query failed, output an error message
    echo "Error fetching data from the database.";
}
