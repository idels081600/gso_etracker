<?php
require_once 'dbh.php';
session_start();
$username1 = $_SESSION['username'];
$servername = "localhost";
$username = "bryanmysql";
$password = "gsotagbilaran";
$dbname = "my_data";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM `request` WHERE `name` = '$username1' ORDER BY `id` DESC LIMIT 1";
$result = $conn->query($sql);
?>

<?php
while ($row = mysqli_fetch_assoc($result)) {
    $estTime = !empty($row["esttime"]) ? date("h:i A", strtotime($row["esttime"])) : "00:00";  // Check if esttime is empty
    echo $row["Status"];
    echo '<br>';

    if ($row["Status"] !== "Done") {
        echo "YOU NEED TO RETURN BY: ";
        echo '<br>' . ($estTime !== "12:00 AM" ? $estTime : "00:00");
    }
}

$conn->close();
?>
