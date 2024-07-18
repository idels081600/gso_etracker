<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee') {
    header("location:login_v2.php");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="sidebar.css">
</head>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Chris John Rener Torralba</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="sir_bayong.php">Sir Bayong</a></li>
                    <li><a href="maam_maricris.php">Maam Maricris</a></li>
                    <li><a href="BQ.php">BQ</a></li>

                </ul>
            </li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="#" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all dropdown list items
        var dropdowns = document.querySelectorAll('.dropdown');

        // Loop through each dropdown list item
        dropdowns.forEach(function(dropdown) {
            // Add click event listener to toggle the dropdown menu
            dropdown.addEventListener('click', function(event) {
                // Toggle the 'active' class on the dropdown menu
                this.querySelector('.dropdown-menu').classList.toggle('active');
                this.classList.toggle('open'); // Toggle 'open' class on the dropdown item
            });
        });

        // Close dropdown menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                var activeDropdowns = document.querySelectorAll('.dropdown-menu.active');
                activeDropdowns.forEach(function(activeDropdown) {
                    activeDropdown.classList.remove('active');
                    activeDropdown.closest('.dropdown').classList.remove('open'); // Remove 'open' class
                });
            }
        });
    });
</script>

</html>