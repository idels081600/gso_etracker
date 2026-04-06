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
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_duration = isset($_GET['filter_duration']) ? $_GET['filter_duration'] : '0';

// Duration filter condition (greater than 1 hour = 3600 seconds)
$duration_condition = ($filter_duration == '1') ? "AND duration_seconds > 3600" : "";

if ($range == 'today') {
    $date_condition = "AND DATE(date) = CURDATE()";
} elseif ($range == 'first15') {
    $month_year = explode('-', $selected_month);
    $year = $month_year[0];
    $month = $month_year[1];
    $date_condition = "AND DAY(date) BETWEEN 1 AND 15 AND MONTH(date) = $month AND YEAR(date) = $year";
} elseif ($range == 'second15') {
    $month_year = explode('-', $selected_month);
    $year = $month_year[0];
    $month = $month_year[1];
    $date_condition = "AND DAY(date) BETWEEN 16 AND 31 AND MONTH(date) = $month AND YEAR(date) = $year";
} else {
    $date_condition = "AND DATE(date) = CURDATE()";
}
 
// Query for Official Business (sorted alphabetically by name)
$result_official = "SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Official Business' AND (confirmed_by = 'CAGULADA RENE ART' OR confirmed_by = 'Pahang Dave') AND role = 'Employee' $date_condition $duration_condition ORDER BY name";
$sql_official = $conn->query($result_official);

// Query for Personal Business (sorted alphabetically by name)
$result_personal = "SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Personal' AND (confirmed_by = 'CAGULADA RENE ART' OR confirmed_by = 'Pahang Dave') AND role = 'Employee' $date_condition $duration_condition ORDER BY name";
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
        $this->SetFont('Arial', 'B', 10);

        // Define header cells with green background - includes Date column
        $this->Cell(45, 10, 'Name', 1, 0, 'C', true);
        $this->Cell(60, 10, 'Destination', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Date', 1, 0, 'C', true);
        $this->Cell(30, 10, 'TimeDept', 1, 0, 'C', true);
        $this->Cell(30, 10, 'TimeRet', 1, 0, 'C', true);
        $this->Cell(45, 10, 'Duration Outside Office', 1, 0, 'C', true);
        $this->Ln();

        // Reset text color to black for table content
        $this->SetTextColor(0, 0, 0);
    }

    function printTableRows($sql)
    {
        $this->SetFont('Arial', '', 10);
        while ($row = $sql->fetch_object()) {
            $name = $row->name;
            $destination = $row->dest2;
            $date = date("m/d/Y", strtotime($row->date));
            $timedept = date("h:i A", strtotime($row->timedept));
            $time_returned = date("h:i A", strtotime($row->time_returned));
            
            // Convert duration_seconds to time format
            $duration_seconds = $row->duration_seconds;
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration_str = $hours . ' hours ' . $minutes . ' minutes';

            // Columns with Date included
            $this->Cell(45, 12, $name, 1);
            $this->Cell(60, 12, $destination, 1);
            $this->Cell(30, 12, $date, 1, 0, 'C');
            $this->Cell(30, 12, $timedept, 1, 0, 'C');
            $this->Cell(30, 12, $time_returned, 1, 0, 'C');
            $this->Cell(45, 12, $duration_str, 1, 0, 'C');
            $this->Ln();
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
    // For first15 and second15 with duration filter - show detailed records
    if ($filter_duration == '1') {
        if ($range == 'first15') {
            $month_name = date('F Y', strtotime($selected_month));
            $title = '1st 15 Days - ' . $month_name . ' (Duration > 1 Hour)';
        } elseif ($range == 'second15') {
            $month_name = date('F Y', strtotime($selected_month));
            $title = '2nd 15 Days - ' . $month_name . ' (Duration > 1 Hour)';
        } else {
            $title = 'Request Summary (Duration > 1 Hour)';
        }

        $pdf = new PDF($title);
        $pdf->AddPage('L', array(215.9, 400)); // Landscape orientation

        // Official Business Section with detailed records
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Official Business - Records > 1 Hour', 0, 1, 'L');
        $pdf->printTableHeader();
        $pdf->printTableRows($sql_official);

        $pdf->Ln(10);

        // Personal Business Section with detailed records
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Personal Business - Records > 1 Hour', 0, 1, 'L');
        $pdf->printTableHeader();
        $pdf->printTableRows($sql_personal);
    } else {
        // Summary for first15 and second15 (without duration filter)
        $totals = ['Official Business' => [], 'Personal' => []];

        // Process Official Business
        while ($row = $sql_official->fetch_object()) {
            $duration_seconds = $row->duration_seconds;
            if (!isset($totals['Official Business'][$row->name])) {
                $totals['Official Business'][$row->name] = 0;
            }
            $totals['Official Business'][$row->name] += $duration_seconds;
        }

        // Process Personal Business
        while ($row = $sql_personal->fetch_object()) {
            $duration_seconds = $row->duration_seconds;
            if (!isset($totals['Personal'][$row->name])) {
                $totals['Personal'][$row->name] = 0;
            }
            $totals['Personal'][$row->name] += $duration_seconds;
        }

        if ($range == 'first15') {
            $month_name = date('F Y', strtotime($selected_month));
            $title = '1st 15 Days - ' . $month_name;
        } elseif ($range == 'second15') {
            $month_name = date('F Y', strtotime($selected_month));
            $title = '2nd 15 Days - ' . $month_name;
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

            // Sort names alphabetically
            ksort($totals[$type]);
            
            foreach ($totals[$type] as $name => $total_seconds) {
                $hours = floor($total_seconds / 3600);
                $mins = floor(($total_seconds % 3600) / 60);
                $time_str = $hours . ' hours ' . $mins . ' minutes';
                $pdf->Cell(100, 15, $name, 1);
                $pdf->Cell(100, 15, $time_str, 1);
                $pdf->Ln();
            }

            $pdf->Ln(10); // Add space between sections
        }
    }
}

// Output the PDF
$month_suffix = ($range == 'today') ? '' : '_' . str_replace('-', '', $selected_month);
$filename = 'pass_slip_' . $range . $month_suffix . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');
