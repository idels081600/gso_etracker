<?php
$serverKey = 'AAAAAMHM41c:APA91bG1QuKFE7FjwLt6g6wT2tGAoBCNl88T7URY255pr5iMH3UGkdIGCFN0Jov_ePI9x6aAZemoQRx2LtLH4-4UQU4IBmfHEbWeAzsInQZ4RfEYhq31BJvqwegOGohihvdcfmlZ2CQN'; // Replace with your FCM server key
$url = "https://fcm.googleapis.com/fcm/send";

// Include your database connection code here

// Replace these database connection details with your own
$servername = "localhost";
$username = "bryanmysql";
$password = "gsotagbilaran";
$dbname = "my_data";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Retrieve registration IDs from the database
$sql = "SELECT token FROM tokens";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $registration_ids = array();
    
    while ($row = $result->fetch_assoc()) {
        $registration_ids[] = $row['token'];
    }

    // Close the database connection
    $conn->close();

    $fields = array(
        "notification" => array(
            "body" => "New Request",
            "title" => "New Notification",
            "icon" => "logo.png",
            "click_action" => "https://8490-180-190-189-30.ngrok-free.app/my_site2/login_v2.php"
        ),
        "registration_ids" => $registration_ids,
    );

    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ),
        CURLOPT_POSTFIELDS => json_encode($fields)
    );

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $result = curl_exec($curl);

    if ($result === false) {
        die("cURL execution failed: " . curl_error($curl));
    }

    curl_close($curl);

    echo 'Test notification sent successfully to all devices.';
} else {
    $conn->close();
    echo 'No tokens found in the database.';
}
?>
