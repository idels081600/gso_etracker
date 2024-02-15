<?php
session_start();

// Unset specific session variables
unset($_SESSION['username']);
unset($_SESSION['role']);

// Destroy the session
session_destroy();

// Redirect to the login page or any other page after logout
header("Location: login_v2.php");
exit();
?>