<?php
// Connect to the database
require_once 'leave_db.php'; // Include your database connection file

// Fetch data from the cto_reg table
$query = "SELECT title, dates, name FROM cto"; // Include the `name` column
$result = mysqli_query($conn, $query);

// Fetch the data and format it for JavaScript
$events = [];

while ($row = mysqli_fetch_assoc($result)) {
    $title = $row['title'];
    $name = $row['name']; // Fetch the name
    $dates = explode(',', $row['dates']); // Split the dates by comma

    foreach ($dates as $date) {
        // Trim any spaces around the date
        $date = trim($date);

        // Ensure the date is in a valid format before proceeding
        if ($date) {
            // Format date as year-month-day (assuming date format is 'YYYY-MM-DD')
            try {
                $dateObj = new DateTime($date);
                $year = $dateObj->format('Y'); // Year
                $month = $dateObj->format('F'); // Full month name
                $day = $dateObj->format('j'); // Day of the month without leading zeros

                // Add the event to the events array
                if (!isset($events[$year])) {
                    $events[$year] = [];
                }
                if (!isset($events[$year][$month])) {
                    $events[$year][$month] = [];
                }
                $events[$year][$month][] = [
                    'day' => $day,
                    'title' => $title,
                    'name' => $name // Add the name here
                ];
            } catch (Exception $e) {
                // Handle invalid date format gracefully (optional)
                continue;
            }
        }
    }
}

// Output the events array as JSON
header('Content-Type: application/json');
echo json_encode($events);
