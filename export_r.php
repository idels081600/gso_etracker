<?php
require_once 'dbh.php';
require_once "fpdf/fpdf.php";
// session_start();
// if (!isset($_SESSION['username'])) {
//     header("location:login_v2.php");
// } else if ($_SESSION['role'] == 'Employee') {
//     header("location:login_v2.php");
// } else if ($_SESSION['role'] == 'Desk Clerk') {
//     header("location:login_v2.php");
// }

// Query for Official Business
$result_official = "SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Official Business' AND Role = 'Employee' ORDER BY id";
$sql_official = $conn->query($result_official);

// Query for Personal Business
$result_personal = "SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Personal' AND Role = 'Employee' ORDER BY id";
$sql_personal = $conn->query($result_personal);

class PDF extends FPDF
{
    var $printedHeader = false;

    function Header()
    {
        if (!$this->printedHeader) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'Request Summary', 0, 1, 'C');
            $this->Ln(10);
            $this->printedHeader = true;
        }
    }

    function printTableHeader()
    {
        // Set fill color to green (RGB: 0, 128, 0)
        $this->SetFillColor(0, 128, 0);
        $this->SetTextColor(255, 255, 255); // Set text color to white

        // Set font for table header
        $this->SetFont('Arial', 'B', 9);

        // Define header cells with green background
        $this->Cell(10, 10, 'ID', 1, 0, 'C', true);
        $this->Cell(45, 10, 'Name', 1, 0, 'C', true);
        $this->Cell(60, 10, 'Destination', 1, 0, 'C', true);
        $this->Cell(80, 10, 'Purpose', 1, 0, 'C', true);
        $this->Cell(21, 10, 'TimeDept', 1, 0, 'C', true);
        $this->Cell(20, 10, 'EstTime', 1, 0, 'C', true);
        $this->Cell(28, 10, 'Date', 1, 0, 'C', true);
        $this->Cell(20, 10, 'TimeRet', 1, 0, 'C', true);
        $this->Cell(40, 10, 'Confirmed By', 1, 0, 'C', true);
        $this->Cell(50, 10, 'Remarks', 1, 0, 'C', true);
        $this->Ln();

        // Reset text color to black for table content
        $this->SetTextColor(0, 0, 0);
    }

    function printTableRows($sql)
    {
        $this->SetFont('Arial', '', 10); // Set font size for all data except remarks
        while ($row = $sql->fetch_object()) {
            $id = $row->id;
            $name = $row->name;
            $destination = $row->dest2;
            $purpose = $row->purpose;
            $timedept = date("h:i A", strtotime($row->timedept)); // Format the time for TimeDept
            $esttime = date("h:i A", strtotime($row->esttime)); // Format the time for EstTime
            $date = $row->date;
            $time_returned = date("h:i A", strtotime($row->time_returned));
            $confirmed_by = $row->confirmed_by;
            $remarks = $row->remarks;

            $this->Cell(10, 15, $id, 1, 0, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(45, 15, $name, 1);
            $this->SetFont('Arial', '', 8);
            $this->Cell(60, 15, $destination, 1);
            $this->Cell(80, 15, $purpose, 1);
            $this->Cell(21, 15, $timedept, 1);
            $this->Cell(20, 15, $esttime, 1);
            $this->Cell(28, 15, $date, 1);
            $this->Cell(20, 15, $time_returned, 1);
            $this->Cell(40, 15, $confirmed_by, 1);

            $this->SetFont('Arial', '', 8); // Set font size for remarks
            $this->MultiCell(50, 15, $remarks, 1, 'L'); // Use MultiCell for the Remarks column to allow text wrapping
        }
    }
}

$pdf = new PDF();
$pdf->AddPage('L', array(215.9, 400)); // Landscape orientation

// Official Business Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Official Business', 0, 1, 'L'); // Section Title
$pdf->printTableHeader(); // Table header
$pdf->printTableRows($sql_official); // Print rows for Official Business

$pdf->Ln(10); // Add some space between sections

// Personal Business Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Personal Business', 0, 1, 'L'); // Section Title
$pdf->printTableHeader(); // Table header
$pdf->printTableRows($sql_personal); // Print rows for Personal Business

// Output the PDF
$filename = 'pass_slip_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');
