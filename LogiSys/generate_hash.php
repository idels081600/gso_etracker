<?php
// Generate hash for password "123"
$password = "GSO2025";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: " . $password . "\n";
echo "Hash: " . $hash . "\n";

// Verify the hash works
if (password_verify($password, $hash)) {
    echo "Hash verification: SUCCESS\n";
} else {
    echo "Hash verification: FAILED\n";
}
?> 