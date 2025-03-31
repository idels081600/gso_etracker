<?php
require_once 'db.php';

$conditions = array();
$params = array();
$types = "";

if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
    $conditions[] = "Date BETWEEN ? AND ?";
    $params[] = $_POST['start_date'];
    $params[] = $_POST['end_date'];
    $types .= "ss";
}

if (!empty($_POST['supplier'])) {
    $conditions[] = "Supplier = ?";
    $params[] = $_POST['supplier'];
    $types .= "s";
}

$query = "SELECT * FROM sir_bayong";
if (count($conditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $query);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total_filtered_amount = 0;
$html_output = "";
while ($row = mysqli_fetch_assoc($result)) {
    $total_filtered_amount += $row['Amount'];
    $html_output .= "<tr>";
    $html_output .= "<td>" . htmlspecialchars($row["SR_DR"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["Date"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["Supplier"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["Quantity"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["Description"] ?? '') . "</td>";
    $html_output .= "<td>₱" . number_format($row["Amount"] ?? 0, 2) . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["Office"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["Vehicle"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["Remarks"] ?? '') . "</td>";
    $html_output .= "<td>";
    $html_output .= "<button class='btn btn-warning btn-sm edit-btn' data-id='" . $row['id'] . "'><i class='fas fa-edit'></i></button> ";
    $html_output .= "<button class='btn btn-danger btn-sm delete-btn' data-id='" . $row['id'] . "'><i class='fas fa-trash'></i></button>";
    $html_output .= "</td>";
    $html_output .= "</tr>";
}

$response = [
    'html' => $html_output,
    'total' => $total_filtered_amount
];

echo json_encode($response);