<?php
require_once 'dbh.php';
require_once 'functions.php';

$result = display_request();

while ($row = mysqli_fetch_assoc($result)) {
  echo '<tr>';
  echo '<td>' . $row["name"] . '</td>';
  echo '<td>' . $row["position"] . '</td>';
  echo '<td>' . $row["destination"] . '</td>';
  echo '<td>' . $row["typeofbusiness"] . '</td>';
  echo '<td>' . $row["Status"] . '</td>';
  echo '<td>
          <a href="view_desk.php?id=' . $row['id'] . '" class="btn btn-info btn-sm">View</a>
          <input type="checkbox" name="selected[]" value="' . $row['id'] . '" class="form-check-input ml-2">
        </td>';
  echo '</tr>';
}
?>
