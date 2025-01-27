<?php
// Connect to the database
require_once 'leave_db.php'; // Include your database connection file

// Get the search term from the query string
$searchTerm = isset($_GET['searchTerm']) ? $_GET['searchTerm'] : '';

// Initialize the events array
$events = [];

// Fetch data from the cto table if search term is provided
if (!empty($searchTerm)) {
    // Search in the cto table
    $query = "SELECT id, title, dates, name FROM cto WHERE title LIKE ? OR name LIKE ?";
    $stmt = mysqli_prepare($conn, $query);
    $searchTerm = "%" . $searchTerm . "%"; // Wildcard search
    mysqli_stmt_bind_param($stmt, 'ss', $searchTerm, $searchTerm);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $title = $row['title'];
        $name = $row['name'];
        $dates = $row['dates']; // Keep the date as is, no splitting

        // Directly use the dates as stored in the database
        try {
            // Format date as year-month-day (assuming date format is 'YYYY-MM-DD')
            $dateObj = new DateTime($dates);
            $year = $dateObj->format('Y');
            $month = $dateObj->format('F');
            $day = $dateObj->format('j');

            if (!isset($events[$year])) {
                $events[$year] = [];
            }
            if (!isset($events[$year][$month])) {
                $events[$year][$month] = [];
            }
            $events[$year][$month][] = [
                'id' => $id,
                'day' => $day,
                'title' => $title,
                'name' => $name
            ];
        } catch (Exception $e) {
            continue;
        }
    }

    // Fetch data from the leave_reg table if search term is provided
    $query = "SELECT id, title, dates, name FROM leave_reg WHERE title LIKE ? OR name LIKE ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ss', $searchTerm, $searchTerm);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $title = $row['title'];
        $name = $row['name'];
        $dates = $row['dates']; // Keep the date as is, no splitting

        // Directly use the dates as stored in the database
        try {
            // Format date as year-month-day (assuming date format is 'YYYY-MM-DD')
            $dateObj = new DateTime($dates);
            $year = $dateObj->format('Y');
            $month = $dateObj->format('F');
            $day = $dateObj->format('j');

            if (!isset($events[$year])) {
                $events[$year] = [];
            }
            if (!isset($events[$year][$month])) {
                $events[$year][$month] = [];
            }
            $events[$year][$month][] = [
                'id' => $id,
                'day' => $day,
                'title' => $title,
                'name' => $name
            ];
        } catch (Exception $e) {
            continue;
        }
    }
}

// Output the events array as JSON
header('Content-Type: application/json');
echo json_encode($events);
