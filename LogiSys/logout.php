<?php
session_start();

// Destroy all session data
session_destroy();

// Clear any cookies
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login page
header("Location: Logi_login.php");
exit();
?> 