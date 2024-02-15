<?php
// Include your database connection file (dbh.php) here
require_once 'dbh.php';

if (isset($_POST['search'])) {
    $searchTerm = $_POST['search'];

    // Perform a query to retrieve data based on the search term
    $sql = "SELECT * FROM request WHERE name LIKE ?"; // Assuming "items" is your table name
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        $searchTerm = '%' . $searchTerm . '%'; 
        mysqli_stmt_bind_param($stmt, "s", $searchTerm);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

       
        $searchResults = '';

        while ($row = mysqli_fetch_assoc($result)) {
            $searchResults .= '<tr>';
            $searchResults .= '<td><h5>' . $row['name'] . '</h5></td>';
            $searchResults .= '<td>' . $row['position'] . '</td>';
            $searchResults .= '<td>' . $row['destination'] . '</td>';
            $searchResults .= '<td>' . $row['typeofbusiness'] . '</td>';
            $searchResults .= '<td>' . $row['Status'] . '</td>';
            $searchResults .= '<td>' . $row['confirmed_by'] . '</td>';
            $searchResults .= '<td><a href="view_approve_data_desk.php?id=' . $row['id'] . '" class="btn btn-info btn-sm">View</a></td>';
            $searchResults .= '</tr>';
        }

        // Output the search results
        echo $searchResults;

        // Close the prepared statement
        mysqli_stmt_close($stmt);
    } else {
        echo "Error in preparing the SQL statement: " . mysqli_error($conn);
    }
}

// Close the database connection
mysqli_close($conn);
?>
