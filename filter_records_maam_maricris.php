<?php
require_once 'db.php';

$conditions = array();
$params = array();
$types = "";

if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
    $conditions[] = "date BETWEEN ? AND ?";
    $params[] = $_POST['start_date'];
    $params[] = $_POST['end_date'];
    $types .= "ss";
}

if (!empty($_POST['supplier'])) {
    $conditions[] = "store = ?";
    $params[] = $_POST['supplier'];
    $types .= "s";
}

$query = "SELECT * FROM Maam_mariecris";
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
    $total_filtered_amount += $row['total'];
    $html_output .= "<tr>";
    $html_output .= "<td>" . htmlspecialchars($row["SR_DR"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["date"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["department"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["store"] ?? '') . "</td>";
    $html_output .= "<td style='max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;'>" . htmlspecialchars($row["activity"] ?? '') . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["no_of_pax"] ?? '') . "</td>";
    $html_output .= "<td>₱" . number_format($row["amount"] ?? 0, 2) . "</td>";
    $html_output .= "<td>₱" . number_format($row["total"] ?? 0, 2) . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["PO_no"] ?? '') . "</td>";
    $html_output .= "<td>₱" . number_format($row["PO_amount"] ?? 0, 2) . "</td>";
    $html_output .= "<td>" . htmlspecialchars($row["remarks"] ?? '') . "</td>";
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
