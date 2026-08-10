<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * ASSET USER PASSWORD RESET TOOL
 * 
 * Run from command line: php reset_asset_password.php
 * 
 * Commands:
 *   php reset_asset_password.php --list
 *   php reset_asset_password.php <username> <new_password>
 */

$isCLI = (php_sapi_name() === 'cli');

require_once 'passlip/dbh.php';

if (!$conn) {
    die("Connection failed\n");
}

if ($isCLI) {
    // --list command
    if (isset($argv[1]) && $argv[1] === '--list') {
        echo "ASSET/ASSET2 Users:\n\n";
        echo str_pad("ID", 6) . str_pad("Username", 20) . str_pad("Name", 25) . "Role\n";
        echo str_repeat("-", 70) . "\n";
        
        $result = mysqli_query($conn, "SELECT id, username, name, role FROM logindb WHERE role IN ('ASSET', 'ASSET2') ORDER BY id");
        while ($row = mysqli_fetch_assoc($result)) {
            echo str_pad($row['id'], 6) . str_pad($row['username'], 20) . str_pad($row['name'], 25) . $row['role'] . "\n";
        }
        echo "\nTo reset: php reset_asset_password.php <username> <new_password>\n";
        exit(0);
    }
    
    // Reset password command
    if ($argc < 3) {
        echo "Usage: php reset_asset_password.php <username> <new_password>\n";
        echo "Example: php reset_asset_password.php GSO_Etracker mynewpass123\n\n";
        echo "Or list all ASSET users:\n";
        echo "  php reset_asset_password.php --list\n";
        exit(1);
    }
    
    $username = $argv[1];
    $newPassword = $argv[2];
} else {
    $username = $_GET['username'] ?? '';
    $newPassword = $_GET['password'] ?? '';
}

if (empty($username) || empty($newPassword)) {
    if (!$isCLI) {
        echo "<h1>ASSET Password Reset</h1>";
        echo "<form method='GET'>";
        echo "Username: <input type='text' name='username'><br>";
        echo "New Password: <input type='password' name='password'><br>";
        echo "<input type='submit' value='Reset Password'>";
        echo "</form>";
        exit;
    }
    exit("Provide username and password\n");
}

// Find user
$stmt = mysqli_prepare($conn, "SELECT id, username, role FROM logindb WHERE BINARY username = ? AND role IN ('ASSET', 'ASSET2')");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User '{$username}' not found or not an ASSET role.\n");
}

// Generate new bcrypt hash
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

// Update password
$update = mysqli_prepare($conn, "UPDATE logindb SET password = ?, text_password = ? WHERE id = ?");
mysqli_stmt_bind_param($update, "ssi", $hash, $newPassword, $user['id']);

if (mysqli_stmt_execute($update)) {
    $msg = "✓ Password RESET successful!\n";
    $msg .= "  Username: {$user['username']}\n";
    $msg .= "  User ID: {$user['id']}\n";
    $msg .= "  Role: {$user['role']}\n";
    $msg .= "  New Password: {$newPassword}\n";
    $msg .= "  Bcrypt Hash: {$hash}\n\n";
    $msg .= "You can now login at: login_v2.php\n";
    
    if ($isCLI) {
        echo $msg;
    } else {
        echo "<pre>" . htmlspecialchars($msg) . "</pre>";
        echo "<a href='login_v2.php'>Go to Login</a>";
    }
} else {
    echo "✗ Failed to update password: " . mysqli_error($conn) . "\n";
}

mysqli_stmt_close($stmt);
mysqli_stmt_close($update);
mysqli_close($conn);
?>
