<?php
require_once 'dbh.php';
$name = $_POST['name'];
$searchTerm = "%" . $name . "%"; // Add wildcard '%' to search for partial matches

$sql = "SELECT * FROM request WHERE name LIKE ? AND `Status` LIKE 'Done' ORDER BY `status1` ASC";
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
        $tableRows .= '<td>' . $row['status1'] . '</td>';
        $tableRows .= '<td>' . $row['confirmed_by'] . '</td>';
        $tableRows .= '<td>' . $row['remarks'] . '</td>';
        $tableRows .= '<td><a href="view_approve_data.php?id=' . $row['id'] . '" class="btn btn-info btn-sm">View</a></td>';
        $tableRows .= '</tr>';
    }

    // Output the table rows
    echo $tableRows;
} else {
    
    echo "Error in preparing the SQL statement: " . mysqli_error($conn);
}

// Close the prepared statement and the database connection
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
