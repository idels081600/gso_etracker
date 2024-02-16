<?php
session_start();

$conn = mysqli_connect("localhost", "bryanmysql", 'gsotagbilaran', "my_data");
if(mysqli_connect_errno()){
    echo "failed to connect";
    exit();
}
else{
    echo "connected";
}

if (isset($_POST['save_data'])) {
    $name = $_POST['name'];
    $position = $_POST['position'];
    $date = date('Y-m-d', strtotime($_POST['date']));
    $destination = $_POST['destination'];
    $purpose = $_POST['purpose'];
    $timedept = $_POST['timedept'];
    $esttime = $_POST['esttime'];
    $typeofbusiness = $_POST['typeofbusiness'];
    
    $query = "INSERT INTO request(name, position, date, destination, purpose, timedept, esttime, typeofbusiness, Status) VALUES ('$name', '$position', '$date', '$destination', '$purpose', '$timedept', '$esttime', '$typeofbusiness','pending')";
    
    $query_run = mysqli_query($conn, $query);
   
}
if (isset($_POST['save_data2'])) {
    $name = $_POST['name'];
    $position = $_POST['position'];
    $date = date('Y-m-d', strtotime($_POST['date']));
    $destination = $_POST['destination'];
    $purpose = $_POST['purpose'];
    $timedept = $_POST['timedept'];
    $esttime = $_POST['esttime'];
    $typeofbusiness = $_POST['typeofbusiness'];
    
    $query = "INSERT INTO request(name, position, date, destination, purpose, timedept, esttime, typeofbusiness, Status) VALUES ('$name', '$position', '$date', '$destination', '$purpose', '$timedept', '$esttime', '$typeofbusiness','pending')";
    
    $query_run = mysqli_query($conn, $query);
    if($query_run)
    {
        
        header("Location: index_emp.php");
        exit(0);
    }
    else
    {
        header("Location: index_emp.php");
        exit(0);
    }
   
}
?>
