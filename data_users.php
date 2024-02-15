<?php
require_once 'dbh.php';


$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM `logindb`";
$result = $conn->query($sql);
?>
<tr>
    <th scope="col">Id</th>
    <th scope="col">Username</th>
    <th scope="col">Password</th>
    <th scope="col">Name</th>
    <th scope="col">Role</th>
    <th scope="col">Action</th>
</tr>
<tr>
    <?php
    while ($row = mysqli_fetch_assoc($result)) {
        ?>
        <td>
            <?php echo $row["Id"]; ?>
        </td>
        <td>
            <?php echo $row["username"]; ?>
        </td>
        <td>
            <?php echo $row["password"]; ?>
        </td>
        <td>
            <?php echo $row["name"]; ?>
        </td>
        <td>
            <?php echo $row["role"]; ?>
        </td>
        <form action="code.php" method="post">
            <input type="hidden" name="id" value="<?php echo $row['Id'] ?>">
            <td><input type="submit" name="delete" class="btn btn-danger" value="delete"></td>
        </form>
    </tr>

    <?php
    }

    $conn->close();
    ?>