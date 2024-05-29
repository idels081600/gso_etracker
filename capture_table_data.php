<?php
session_start();

if(isset($_POST['tableData'])) {
    $_SESSION['tableData'] = $_POST['tableData'];
    echo "Table data captured successfully.";
} else {
    echo "No table data received.";
}
?>
