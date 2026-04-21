<?php
require_once '../dbh.php';
require_once "../../fpdf/fpdf.php";
session_start();
// if (!isset($_SESSION['username'])) {
//     header("location:login_v2.php");
// } else if ($_SESSION['role'] == 'Employee') {
//     header("location:login_v2.php");
// } else if ($_SESSION['role'] == 'Desk Clerk') {
//     header("location:login_v2.php");
// }

$range = isset($_GET['range']) ? $_GET['range'] : 'today';

if ($range == 'today') {
    $date_condition = "AND DATE(date) = CURDATE()";
} elseif ($range == 'first15') {
    $date_condition = "AND DAY(date) BETWEEN 1 AND 15 AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())";
} elseif ($range == 'second15') {
    $date_condition = "AND DAY(date) BETWEEN 16 AND 31 AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())";
} else {
    $date_condition = "AND DATE(date) = CURDATE()";
}
 
// Query for Official Business
$result_official = "SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Official Business' AND (confirmed_by = 'CAGULADA RENE ART' OR confirmed_by = 'Pahang Dave') AND role IN ('Employee', 'TCWS Employee') $date_condition ORDER BY id";
$sql_official = $conn->query($result_official);

// Query for Personal Business
$result_personal = "SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Personal' AND (confirmed_by = 'CAGULADA RENE ART' OR confirmed_by = 'Pahang Dave') AND role IN ('Employee', 'TCWS Employee') $date_condition ORDER BY id";
$sql_personal = $conn->query($result_personal);

class PDF extends FPDF
{
    var $printedHeader = false;
    var $title = 'Request Summary';

    function __construct($title = 'Request Summary') {
        parent::__construct();
        $this->title = $title;
    }

    function Header()
    {
        if (!$this->printedHeader) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, $this->title, 0, 1, 'C');
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
        // $this->Cell(10, 10, 'ID', 1, 0, 'C', true);
        $this->Cell(45, 10, 'Name', 1, 0, 'C', true);
        $this->Cell(60, 10, 'Destination', 1, 0, 'C', true);
        $this->Cell(80, 10, 'Purpose', 1, 0, 'C', true);
        $this->Cell(21, 10, 'TimeDept', 1, 0, 'C', true);
        $this->Cell(20, 10, 'EstTime', 1, 0, 'C', true);
        $this->Cell(28, 10, 'Time Allotted', 1, 0, 'C', true);
        $this->Cell(28, 10, 'Date', 1, 0, 'C', true);
        $this->Cell(20, 10, 'TimeRet', 1, 0, 'C', true);
        $this->Cell(40, 10, 'Confirmed By', 1, 0, 'C', true);
        $this->Cell(50, 10, 'Duration Outside Office', 1, 0, 'C', true);
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
            $time_alloted = $row->time_allotted;
            $purpose = $row->purpose;
            $timedept = date("h:i A", strtotime($row->timedept)); // Format the time for TimeDept
            $esttime = date("h:i A", strtotime($row->esttime)); // Format the time for EstTime
            $date = $row->date;
            $time_returned = date("h:i A", strtotime($row->time_returned));
            $confirmed_by = $row->confirmed_by;
            // Calculate time difference for remarks
            $timedept_dt = new DateTime($row->timedept);
            $timeret_dt = new DateTime($row->time_returned);
            $interval = $timedept_dt->diff($timeret_dt);
            $remarks = $interval->format('%h hours %i minutes');

            // $this->Cell(10, 15, $id, 1, 0, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(45, 15, $name, 1);
            $this->SetFont('Arial', '', 8);
            $this->Cell(60, 15, $destination, 1);
            $this->Cell(80, 15, $purpose, 1);
            $this->Cell(21, 15, $timedept, 1);
            $this->Cell(20, 15, $esttime, 1);
            $this->Cell(28, 15, $time_alloted, 1);
            $this->Cell(28, 15, $date, 1);
            $this->Cell(20, 15, $time_returned, 1);
            $this->Cell(40, 15, $confirmed_by, 1);
            $this->SetFont('Arial', '', 8); // Set font size for remarks
            $this->MultiCell(50, 15, $remarks, 1, 'L'); // Use MultiCell for the Remarks column to allow text wrapping
        }
    }
}

if ($range == 'today') {
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
} else {
    // Summary for first15 and second15
    $totals = ['Official Business' => [], 'Personal' => []];

    // Process Official Business
    while ($row = $sql_official->fetch_object()) {
        $timedept_dt = new DateTime($row->timedept);
        $timeret_dt = new DateTime($row->time_returned);
        $interval = $timedept_dt->diff($timeret_dt);
        $minutes = $interval->h * 60 + $interval->i;
        if (!isset($totals['Official Business'][$row->name])) {
            $totals['Official Business'][$row->name] = 0;
        }
        $totals['Official Business'][$row->name] += $minutes;
    }

    // Process Personal Business
    while ($row = $sql_personal->fetch_object()) {
        $timedept_dt = new DateTime($row->timedept);
        $timeret_dt = new DateTime($row->time_returned);
        $interval = $timedept_dt->diff($timeret_dt);
        $minutes = $interval->h * 60 + $interval->i;
        if (!isset($totals['Personal'][$row->name])) {
            $totals['Personal'][$row->name] = 0;
        }
        $totals['Personal'][$row->name] += $minutes;
    }

    if ($range == 'first15') {
        $title = '1st Kinsena';
    } elseif ($range == 'second15') {
        $title = '2nd Kinsena';
    } else {
        $title = 'Request Summary';
    }

    $pdf = new PDF($title);
    $pdf->AddPage('L', array(215.9, 400)); // Landscape orientation

    // For each type
    foreach (['Official Business', 'Personal'] as $type) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, $type . ' Summary', 0, 1, 'L'); // Section Title

        // Summary table header
        $pdf->SetFillColor(0, 128, 0);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(100, 10, 'Name', 1, 0, 'C', true);
        $pdf->Cell(100, 10, 'Total Time Outside the Office', 1, 0, 'C', true);
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 9);

        foreach ($totals[$type] as $name => $total_minutes) {
            $hours = floor($total_minutes / 60);
            $mins = $total_minutes % 60;
            $time_str = $hours . ' hours ' . $mins . ' minutes';
            $pdf->Cell(100, 15, $name, 1);
            $pdf->Cell(100, 15, $time_str, 1);
            $pdf->Ln();
        }

        $pdf->Ln(10); // Add space between sections
    }
}

// Output the PDF
$filename = 'pass_slip_' . $range . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');
