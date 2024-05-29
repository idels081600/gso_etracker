<?php
include 'db.php';


// Validate and sanitize input (not implemented for simplicity)

// Perform the search query based on the provided dates
$query = "SELECT * FROM Maam_mariecris ORDER BY `id` DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    // If there's an error in the query execution, display the error message
    echo "Error: " . mysqli_error($conn);
} else {
    // Initialize total amount variable
    $total = 0;

    // Display the search results in HTML format
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {

            // Output the rows as HTML table rows
            echo "<tr data-id='" . $row['id'] . "'>";
            echo "<td>" . $row["SR_DR"] . "</td>";
            echo "<td>" . $row["date"] . "</td>";
            echo "<td>" . $row["department"] . "</td>";
            echo "<td>" . $row["store"] . "</td>";
            echo "<td>" . $row["activity"] . "</td>";
            echo "<td>" . $row["no_of_pax"] . "</td>";
            echo "<td>" . '₱' . number_format($row["amount"], 2) . "</td>";
            echo "<td>" . '₱' . number_format($row["total"], 2) . "</td>";
            echo "</tr>";
        }
    } else {
        // If no results are found, display a message
        echo "<tr><td colspan='8'>No results found.</td></tr>";
    }
}
