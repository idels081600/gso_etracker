<?php
require_once 'transmit_db.php';
require_once '../fpdf/fpdf.php'; // Adjust path as needed

$type = isset($_GET['type']) ? $_GET['type'] : '';
$start = isset($_GET['start']) ? $_GET['start'] : '';
$end = isset($_GET['end']) ? $_GET['end'] : '';

$where = [];
$params = [];
$types = '';
if ($type) {
    $where[] = "transmittal_type = ?";
    $params[] = $type;
    $types .= 's';
}
if ($start && $end) {
    $where[] = "date_received BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
    $types .= 'ss';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT * FROM transmittal_bac $where_sql ORDER BY date_received DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// FPDF setup
$pdf = new FPDF('L', 'mm', array(356, 216)); // Legal size, landscape (width, height in mm)
$pdf->SetMargins(10, 10, 10); // left, top, right margins
$pdf->SetAutoPageBreak(true, 10); // bottom margin
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Transmittal Report' . ($type ? " - $type" : ''), 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
if ($start && $end) {
    $pdf->Cell(0, 8, "Date Range: $start to $end", 0, 1, 'C');
}
$pdf->Ln(4);

// Table header
$pdf->SetFillColor(32, 128, 69);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial', 'B', 8);
$headers = [
    'IB no.', 'Project Name', 'Date Received', 'Office', 'Received by',
    'Winning Bidders', 'NOA no.', 'COA Date', 'Notice to Proceed', 'Deadline'
];
$widths = [25, 90, 25, 25, 25, 45, 20, 25, 25, 25, 16]; // sum = 316, leaves 20mm for spacing
foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 10, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Table body
$pdf->SetFont('Arial', '', 6);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(233, 245, 238);
$fill = true;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell($widths[0], 8, $row['ib_no'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], 8, $row['project_name'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[2], 8, date('Y-m-d', strtotime($row['date_received'])), 1, 0, 'C', $fill);
        $pdf->Cell($widths[3], 8, $row['office'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[4], 8, $row['received_by'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[5], 8, $row['winning_bidders'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[6], 8, $row['NOA_no'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[7], 8, $row['COA_date'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[8], 8, $row['notice_proceed'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[9], 8, $row['deadline'], 1, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
} else {
    $pdf->Cell(array_sum($widths), 8, 'No records found.', 1, 1, 'C');
}

$pdf->Output('I', 'Transmittal_Report.pdf');
$stmt->close();
?>