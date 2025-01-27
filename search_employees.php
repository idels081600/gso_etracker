<?php
// search_employees.php
require_once 'dbh.php'; // Include your database connection

if (isset($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';

    // Prepare the query to search for name or credits (we'll only return name and credits)
    $query = "SELECT id, name, credits FROM logindb WHERE name LIKE ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $searchTerm); // Bind the search term to the name column
    $stmt->execute();

    // Get the result and return it as JSON
    $result = $stmt->get_result();
    $employees = [];

    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode($employees); // Return the result as JSON
} else {
    echo json_encode([]); // Return empty array if no search term
}
