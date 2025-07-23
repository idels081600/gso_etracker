<?php
session_start();


// Include database connection
require_once 'logi_db.php';


// Function to sanitize input
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}


// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {


    // Sanitize inputs
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password before hashing
    $remember_me = isset($_POST['rememberMe']) ? 1 : 0;


    // Validate inputs
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Username and password are required.";
        header("Location: Logi_login.php");
        exit();
    }


    try {
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, office_id, username, password, role, status FROM users WHERE username = ? AND status = 'active'");


        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }


        // Bind parameters
        $stmt->bind_param("s", $username);


        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Error executing query: " . $stmt->error);
        }


        // Get result
        $result = $stmt->get_result();


        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();


            // Verify password (assuming passwords are hashed with password_hash)
            if (password_verify($password, $user['password'])) {


                // Set session variables
                $_SESSION['user_id'] = $user['office_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();


                // Set remember me cookie if checked
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 days


                    // Store token in database (you'll need to add a remember_token column to users table)
                    $update_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $token, $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }


                // Log successful login
                $login_time = date('Y-m-d H:i:s');
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, login_time, ip_address, status) VALUES (?, ?, ?, ?, 'success')");
                $log_stmt->bind_param("isss", $user['id'], $user['username'], $login_time, $ip_address);
                $log_stmt->execute();
                $log_stmt->close();


                // Role-based redirect
                if ($user['role'] === 'ADMIN_SAP') {
                    // Redirect ADMIN_SAP to system dashboard
                    header("Location: Logi_Sys_Dashboard.php");
                } else {
                    // Redirect other roles to regular requests page
                    header("Location: Logi_my_req.php");
                }
                exit();
            } else {
                // Invalid password
                log_failed_attempt($conn, $username, 'Invalid password');
                $_SESSION['login_error'] = "Invalid username or password.";
                header("Location: Logi_login.php");
                exit();
            }
        } else {
            // User not found
            log_failed_attempt($conn, $username, 'User not found');
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: Logi_login.php");
            exit();
        }


        $stmt->close();
    } catch (Exception $e) {
        // Log error
        error_log("Login error: " . $e->getMessage());
        $_SESSION['login_error'] = "An error occurred. Please try again.";
        header("Location: Logi_login.php");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: Logi_login.php");
    exit();
}


// Function to log failed login attempts
function log_failed_attempt($conn, $username, $reason)
{
    try {
        $login_time = date('Y-m-d H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'];


        $log_stmt = $conn->prepare("INSERT INTO login_logs (username, login_time, ip_address, status, reason) VALUES (?, ?, ?, 'failed', ?)");
        $log_stmt->bind_param("ssss", $username, $login_time, $ip_address, $reason);
        $log_stmt->execute();
        $log_stmt->close();
    } catch (Exception $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}


$conn->close();