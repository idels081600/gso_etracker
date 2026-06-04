<?php
require_once '../dbh.php';
require_once '../functions.php';

$result = display_request();

while ($row = mysqli_fetch_assoc($result)) {
  echo '<tr>';
  echo '<td>' . $row["name"] . '</td>';
  echo '<td>' . $row["position"] . '</td>';
  echo '<td>' . $row["destination"] . '</td>';
  echo '<td>' . $row["typeofbusiness"] . '</td>';
  echo '<td>' . $row["Status"] . '</td>';
  echo '<td>
          <button type="button" class="btn btn-info btn-sm" data-view-request="' . $row['id'] . '">View</button>
          <input type="checkbox" name="selected[]" value="' . $row['id'] . '" class="form-check-input ml-2">
        </td>';
  echo '</tr>';
}
?>
