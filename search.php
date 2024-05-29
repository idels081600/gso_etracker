<?php
include 'db.php';

$startDate = $_GET['startDate'];
$endDate = $_GET['endDate'];

// Validate and sanitize input (not implemented for simplicity)

// Perform the search query based on the provided dates
$query = "SELECT * FROM sir_bayong WHERE Date BETWEEN '$startDate' AND '$endDate'";
$result = mysqli_query($conn, $query);

if (!$result) {
    // If there's an error in the query execution, display the error message
    echo "Error: " . mysqli_error($conn);
} else {
    // Display the search results in HTML format
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Output the rows as HTML table rows
            echo "<tr data-id='" . $row['id'] . "'>";
            echo "<td>" . $row["SR_DR"] . "</td>";
            echo "<td>" . $row["Date"] . "</td>";
            echo "<td>" . $row["Quantity"] . "</td>";
            echo "<td>" . $row["Description"] . "</td>";
            echo "<td>" . '₱' . number_format($row["Amount"], 2) . "</td>";
            echo "<td>" . $row["Office"] . "</td>";
            echo "<td>" . $row["Vehicle"] . "</td>";
            echo "<td>" . $row["Plate"] . "</td>";
            echo "</tr>";
        }
    } else {
        // If no results are found, display a message
        echo "<tr><td colspan='8'>No results found.</td></tr>";
    }
}
