<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Image Update</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div id="icons">
        <img id="icon" src="pending.png" alt="Logo" class="check-img">
    </div>

    <script>
        function updateImage() {
            $.ajax({
                url: 'update.php',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    if (data.success) {
                        $('#icon').attr('src', data.image); // Corrected ID here
                    } else {
                        console.error(data.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                }
            });
        }

        // Initial update
        updateImage();

        // Set interval for periodic updates (every 5 seconds)
        setInterval(updateImage, 5000);
    </script>
</body>

</html>
