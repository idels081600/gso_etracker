
<?php
 require_once 'dbh.php';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

$sql = "SELECT * FROM `request` ORDER BY `id` DESC";
$result = $conn->query($sql);
?>
 <tr>
                      <th scope="col">Name</th>
                      <th scope="col">Destination</th>
                      <th scope="col">Status</th>
                      <th scope = "col">Confirmed By</th>
                      <th scope="col">Remarks</th>
                      <th scope="col">Action</th>
                      
                  </tr>
<?php
while($row = mysqli_fetch_assoc($result))
{
  ?>
  
                  <td><?php echo $row["name"]; ?></td>
                  <td><?php echo $row["destination"]; ?></td>
                  <td><?php echo $row["status1"]; ?></td>
                  <td><?php echo $row["confirmed_by"]; ?></td>
                  <td><?php echo $row["remarks"]; ?></td>
                  
                  <td> <a href="view_track_emp.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">View</a></td>
</tr>

  <?php
}

$conn->close();
?>