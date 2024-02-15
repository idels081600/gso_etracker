<?php
require_once 'dbh.php';
require_once "fpdf/fpdf.php";
session_start();
if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee') {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Desk Clerk') {
    header("location:login_v2.php");
}
$result = "SELECT * FROM request WHERE Status = 'Done' AND `Role` = 'TCWS Employee' ORDER BY id";
$sql = $conn->query($result);

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', '', 12);
        $this->Cell(10, 10, 'ID', 1);
        $this->Cell(60, 10, 'Name', 1);
        $this->Cell(30, 10, 'Destination', 1);
        $this->Cell(70, 10, 'Purpose', 1);
        $this->Cell(21, 10, 'TimeDept', 1);
        $this->Cell(20, 10, 'EstTime', 1);
        $this->Cell(39, 10, 'TypeofBusiness', 1);
        $this->Cell(20, 10, 'TimeRet', 1);
        $this->Cell(55, 10, 'Confirmed By', 1);
        $this->Cell(50, 10, 'Remarks', 1);
        $this->Ln();
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage('L', array(215.9, 400)); // Landscape orientation
$h = 15;
$w = 39;
$x = $pdf->GetX();
$y = $pdf->GetY();
while ($row = $sql->fetch_object()) {
    $id = $row->id;
    $name = $row->name;
    $destination = $row->dest2;
    $purpose = $row->purpose;
    $timedept = date("h:i A", strtotime($row->timedept)); // Format the time for TimeDept
    $esttime = date("h:i A", strtotime($row->esttime)); // Format the time for EstTime
    $typeofbusiness = $row->typeofbusiness;
    $time_returned = date("h:i A", strtotime($row->time_returned));
    $confirmed_by = $row->confirmed_by;
    $remarks = $row->remarks;

    $pdf->Cell(10, 15, $id, 1);
    $pdf->Cell(60, $h, $name, 1);
    $pdf->Cell(30, $h, $destination, 1);
    $pdf->Cell(70, $h, $purpose, 1);
    $pdf->Cell(21, $h, $timedept, 1);
    $pdf->Cell(20, $h, $esttime, 1);
    $pdf->Cell($w, $h, $typeofbusiness, 1);
    $pdf->Cell(20, $h, $time_returned, 1);
    $pdf->Cell(55, $h, $confirmed_by, 1);

    // Use MultiCell for the Remarks column to allow text wrapping
    $pdf->SetFont('Arial', '', 12); // Set a smaller font size for Remarks
    $pdf->MultiCell(50, 8, $remarks, 1);
    $pdf->SetFont('Arial', '', 11); // Reset font size to the original value

    $pdf->Ln(0); // Start a new line for the next row
}
$filename = 'pass_slip_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'I');
?>
