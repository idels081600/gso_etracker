<?php
include 'db.php'; // Include your database connection file

// Query to select initial data from the database table
$query = "SELECT * FROM sir_bayong  ORDER BY `id` DESC";

// Execute the query
$result = mysqli_query($conn, $query);

// Check if there are any rows returned
if (mysqli_num_rows($result) > 0) {
    // Output the rows as HTML table rows
    while ($row = mysqli_fetch_assoc($result)) {
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
    echo "<tr><td colspan='8'>No data found.</td></tr>";
}

// Close the database connection
mysqli_close($conn);
