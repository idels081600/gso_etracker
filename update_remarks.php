<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firebase Cloud Messaging Example</title>
    <!-- Include Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.14.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.4.0/firebase-messaging-compat.js"></script>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- Your Firebase Config -->
    <script>
        var firebaseConfig = {
            apiKey: "AIzaSyBdJEBddNuHGPyYW_NQ3D8VFpeQdfXOS2M",
            authDomain: "push-notification-4469d.firebaseapp.com",
            databaseURL: "https://push-notification-4469d-default-rtdb.asia-southeast1.firebasedatabase.app",
            projectId: "push-notification-4469d",
            storageBucket: "push-notification-4469d.appspot.com",
            messagingSenderId: "3251430231",
            appId: "1:3251430231:web:aea52a61992765cf511412",
            measurementId: "G-V236DTMQ4E"
        };
        firebase.initializeApp(firebaseConfig);

        // Get FCM token
        firebase.messaging().getToken().then((token) => {
            console.log("FCM Token:", token);

            // Send the token to your server using jQuery AJAX
            $.ajax({
                url: 'store_token.php',
                type: 'POST',
                data: { token: token },
                success: function (response) {
                    console.log('Token stored successfully:', response);
                },
                error: function () {
                    console.error('Error storing token');
                }
            });
        }).catch((error) => {
            console.error("Error getting FCM token:", error);
        });
    </script>
</head>

<body>
    <h1>Firebase Cloud Messaging Example</h1>
    <!-- Your HTML content -->
</body>

</html>
