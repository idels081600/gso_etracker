<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * Login Debug Tool - Simulates the exact check_login.php flow
 */

// Step 1: Start session like check_login.php does
require_once 'auth_security.php';
start_secure_session();

echo "<h1>Login Debug</h1>";

// Step 2: Simulate POST data
$input = 'GSO_Etracker';
$password = 'test123';

echo "<h2>1. Testing with credentials:</h2>";
echo "Username: " . htmlspecialchars($input) . "<br>";
echo "Password: " . htmlspecialchars($password) . "<br>";

// Step 3: Connect to database like check_login.php
require_once 'passlip/dbh.php';

if (!$conn) {
    die("<h2>❌ Connection failed</h2>");
}
echo "<h2>2. Database: Connected ✓</h2>";

// Step 4: Search for user (same logic as check_login.php)
if (is_numeric($input)) {
    $sql = "SELECT * FROM logindb WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $input);
} else {
    $sql = "SELECT * FROM logindb WHERE BINARY username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $input);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_array($result);

echo "<h2>3. User lookup:</h2>";
if ($row) {
    echo "✓ User found in database<br>";
    echo "Username: " . htmlspecialchars($row['username']) . "<br>";
    echo "Role: " . htmlspecialchars($row['role']) . "<br>";
    echo "Name: " . htmlspecialchars($row['name']) . "<br>";
    
    // Step 5: Test password verification
    echo "<h2>4. Password verification:</h2>";
    echo "Stored password column length: " . strlen($row['password']) . " chars<br>";
    echo "Hash starts with: '" . htmlspecialchars(substr($row['password'], 0, 20)) . "...'<br>";
    
    if (password_verify($password, $row['password'])) {
        echo "<strong style='color:green;font-size:18px;'>✓ PASSWORD MATCHES!</strong><br>";
    } else {
        echo "<strong style='color:red;font-size:18px;'>✗ PASSWORD DOES NOT MATCH</strong><br>";
        
        // Try to show if it's a different password
        $plain = $row['text_password'];
        if (!empty($plain)) {
            echo "text_password field contains: '" . htmlspecialchars($plain) . "'<br>";
            echo "Try login with that password instead.<br>";
        } else {
            echo "text_password field is EMPTY - password was likely set differently.<br>";
        }
    }
    
    // Step 6: Check destination
    echo "<h2>5. Redirect target:</h2>";
    echo "Role '{$row['role']}' maps to: ";
    switch ($row['role']) {
        case "ASSET2":
            echo "asset_tracker_dashboard/dashboard_asset_tracker.php<br>";
            break;
        case "ASSET":
            echo "dashboard_asset_tracker.php (which loads from asset_tracker_dashboard/)<br>";
            break;
        default:
            echo "Unknown role, check check_login.php switch statement<br>";
    }
} else {
    echo "<strong style='color:red;font-size:18px;'>✗ USER NOT FOUND</strong><br>";
    echo "No user with username '" . htmlspecialchars($input) . "' and role 'ASSET' found.<br>";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

echo "<hr>";
echo "<h2>Files involved:</h2>";
echo "check_login.php - CWD: " . getcwd() . "<br>";
echo "auth_security.php - exists: " . (file_exists('auth_security.php') ? 'YES' : 'NO') . "<br>";
echo "passlip/dbh.php - exists: " . (file_exists('passlip/dbh.php') ? 'YES' : 'NO') . "<br>";

echo "<hr>";
echo "<h2>Quick Fix Options:</h2>";
echo "<ol>";
echo "<li><a href='reset_asset_password.php?username=GSO_Etracker&password=GSO2026'>Reset GSO_Etracker to password: GSO2026</a></li>";
echo "<li><a href='reset_asset_password.php?username=admin_gso&password=admin123'>Reset admin_gso to password: admin123</a></li>";
echo "<li><a href='reset_asset_password.php?username=jjs&password=jjs123'>Reset jjs to password: jjs123</a></li>";
echo "</ol>";
?>
