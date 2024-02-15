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
$result = "SELECT * FROM request WHERE Status = 'Done' ORDER BY id";
$sql = $conn->query($result);

class PDF extends FPDF
{
    var $printedHeader = false; // Flag to track if the header has been printed

    function Header()
    {
        if (!$this->printedHeader) {
            $this->SetFont('Arial', '', 9);
            $this->Cell(10, 10, 'ID', 1);
            $this->Cell(45, 10, 'Name', 1);
            $this->Cell(60, 10, 'Destination', 1);
            $this->Cell(80, 10, 'Purpose', 1);
            $this->Cell(21, 10, 'TimeDept', 1);
            $this->Cell(20, 10, 'EstTime', 1);
            $this->Cell(28, 10, 'TypeofBusiness', 1);
            $this->Cell(20, 10, 'TimeRet', 1);
            $this->Cell(40, 10, 'Confirmed By', 1);
            $this->Cell(50, 10, 'Remarks', 1);
            $this->Ln();
            $this->printedHeader = true; // Set printedHeader to true after printing the header
        }
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

    // Set font size for all data except remarks
    $pdf->SetFont('Arial', '', 10); // Set font size to 10 points for all data except remarks

    $pdf->Cell(10, 15, $id, 1, 0, 'C'); // Adjusted cell width and centered alignment
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(45, 15, $name, 1); // Adjusted cell height
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(60, 15, $destination, 1); // Adjusted cell height
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(80, 15, $purpose, 1); // Adjusted cell height
    $pdf->Cell(21, 15, $timedept, 1); // Adjusted cell height
    $pdf->Cell(20, 15, $esttime, 1); // Adjusted cell height
    $pdf->Cell(28, 15, $typeofbusiness, 1); // Adjusted cell height
    $pdf->Cell(20, 15, $time_returned, 1); // Adjusted cell height
    $pdf->Cell(40, 15, $confirmed_by, 1); // Adjusted cell height

    // Set font size for remarks
    $pdf->SetFont('Arial', '', 8); // Set font size to 8 points for remarks
    // Use MultiCell for the Remarks column to allow text wrapping
    $pdf->MultiCell(50, 15, $remarks, 1, 'L'); // Adjusted cell width and left alignment
}

$filename = 'pass_slip_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'I');
?>
