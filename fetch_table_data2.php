<?php
// Include your database connection file if needed
include 'db_asset.php';

// Query to fetch data for Table 1
$result1 = mysqli_query($conn,  "SELECT * FROM RFQ where Status = 'Office Clerk'");

// HTML content for Table 1
$output = '';
while ($row = mysqli_fetch_assoc($result1)) {
    $output .= '<tr class="clickable-row1" data-rfq-id="' . $row["id"] . '">';
    $output .= '<td>' . $row["rfq_no"] . '</td>';
    $output .= '<td>' . $row["pr_no"] . '</td>';
    $output .= '<td>' . $row["rfq_name"] . '</td>';
    $output .= '<td>' . $row["date"] . '</td>';
    $output .= '<td>' . $row["amount"] . '</td>';
    $output .= '<td>' . $row["requestor"] . '</td>';
    $output .= '<td>' . $row["supplier"] . '</td>';
    // Add other table columns as needed
    $output .= '</tr>';
}
echo $output;
?>
