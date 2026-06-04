<?php

require_once '../dbh.php';
require_once '../functions.php';

$result = display_emp_status_r();

while ($row = mysqli_fetch_assoc($result)) {
  echo '<tr>';
  echo '<td>' . $row["name"] . '</td>';
  echo '<td>' . $row["destination"] . '</td>';
  echo '<td>' . $row["status1"] . '</td>';
  echo '<td>' . $row["typeofbusiness"] . '</td>';
  echo '<td>' . $row["remarks"] . '</td>';
  echo '<td><button type="button" class="btn btn-info btn-sm" data-track-view="' . $row['id'] . '">View</button></td>';
  echo '</tr>';
}
