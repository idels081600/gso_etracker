<?php
// Include your database connection file (dbh.php) here
include('dbh.php');

if (isset($_POST['name'])) {
    $name = $_POST['name'];
    $searchTerm = "%" . $name . "%"; // Add wildcard '%' to search for partial matches

    // Perform a query to retrieve data based on the search term
    $sql = "SELECT * FROM `request` WHERE name LIKE ? AND `Status` LIKE 'Approved' ORDER BY `date` DESC";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $searchTerm);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        // Initialize an empty string to store the table rows
        $tableRows = '';

        while ($row = mysqli_fetch_assoc($result)) {
            $tableRows .= '<tr>';
            $tableRows .= '<td>' . $row['name'] . '</td>';
            $tableRows .= '<td>' . $row['position'] . '</td>';
            $tableRows .= '<td>' . $row['destination'] . '</td>';
            $tableRows .= '<td>' . $row['typeofbusiness'] . '</td>';
            $tableRows .= '<td>' . $row['Status'] . '</td>';
            $tableRows .= '<td>' . $row['confirmed_by'] . '</td>';
            $tableRows .= '<td><a href="view_approve_data_emp.php?id=' . $row['id'] . '" class="btn btn-info btn-sm">View</a></td>';
            $tableRows .= '</tr>';
        }

        // Output the table rows
        echo $tableRows;

        // Close the prepared statement
        mysqli_stmt_close($stmt);
    } else {
        echo "Error in preparing the SQL statement: " . mysqli_error($conn);
    }
}

// Close the database connection
mysqli_close($conn);
?>
