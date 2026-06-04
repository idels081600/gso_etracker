<?php
require_once '../dbh.php';
require_once '../functions.php';

$result = display_request();

while ($row = mysqli_fetch_assoc($result)) {
  echo '<tr>';
  echo '<td>' . htmlspecialchars($row["name"]) . '</td>';
  echo '<td>' . htmlspecialchars($row["position"]) . '</td>';
  echo '<td>' . htmlspecialchars($row["destination"]) . '</td>';
  echo '<td>' . htmlspecialchars($row["typeofbusiness"]) . '</td>';
  echo '<td>' . htmlspecialchars($row["Status"]) . '</td>';
  echo '<td>
          <button type="button" class="btn btn-info btn-sm" data-view-request="' . (int) $row['id'] . '">View</button>
          <input type="checkbox" name="selected[]" value="' . (int) $row['id'] . '" class="form-check-input ml-2">
        </td>';
  echo '</tr>';
}
?>
