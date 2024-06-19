<?php
// Assuming you have a database connection established
include('db_asset.php');

// Perform select query to fetch updated data
$sql = "SELECT * FROM tent";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    // Output data as table rows
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row["tent_no"] . "</td>";
        echo "<td>" . $row["date"] . "</td>";
        echo "<td>" . $row["name"] . "</td>";
        echo "<td>" . $row["Contact_no"] . "</td>";
        echo "<td>" . $row["no_of_tents"] . "</td>";
        echo "<td>" . $row["purpose"] . "</td>";
        echo "<td>" . $row["location"] . "</td>";
        echo "<td>";
        echo "<button class='button-4 viewButton' data-id='" . $row['id'] . "' role='button'>Edit</button>";
        echo "<button class='button-5 deleteButton' data-id='" . $row['id'] . "' role='button'>Delete</button>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8'>No records found</td></tr>";
}

// Close database connection
mysqli_close($conn);
?>
