<?php
require_once '../dbh.php';
require_once "../../fpdf/fpdf.php";
session_start();

$range = isset($_GET['range']) ? $_GET['range'] : 'today';
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_duration = isset($_GET['filter_duration']) ? $_GET['filter_duration'] : '0';
$duration_condition = ($filter_duration == '1') ? "AND duration_seconds > 3600" : "";

if ($range == 'today') {
    $date_condition = "AND DATE(date) = CURDATE()";
} else {
    $month_year = explode('-', $selected_month);
    $year = $month_year[0]; $month = $month_year[1];
    $date_condition = ($range == 'first15') 
        ? "AND DAY(date) BETWEEN 1 AND 15 AND MONTH(date) = $month AND YEAR(date) = $year"
        : "AND DAY(date) BETWEEN 16 AND 31 AND MONTH(date) = $month AND YEAR(date) = $year";
}

// Query for TCWS Employees ONLY
$sql_official = $conn->query("SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Official Business' AND role = 'TCWS Employee' $date_condition $duration_condition ORDER BY name");
$sql_personal = $conn->query("SELECT * FROM request WHERE Status = 'Done' AND TypeofBusiness = 'Personal' AND role = 'TCWS Employee' $date_condition $duration_condition ORDER BY name");

class PDF extends FPDF {
    var $headerTitle;
    function __construct($orientation='L', $unit='mm', $size=array(215.9, 400), $title='') {
        parent::__construct($orientation, $unit, $size);
        $this->headerTitle = $title;
    }
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 15, $this->headerTitle, 0, 1, 'C');
        $this->Ln(5);
    }
    function printTableHeader() {
        $this->SetFillColor(0, 128, 0); $this->SetTextColor(255, 255, 255); $this->SetFont('Arial', 'B', 10);
        $this->Cell(45, 10, 'Name', 1, 0, 'C', true);
        $this->Cell(60, 10, 'Purpose', 1, 0, 'C', true);
        $this->Cell(70, 10, 'Destination', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Date', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Time Dept', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Time Ret', 1, 0, 'C', true);
        $this->Cell(45, 10, 'Duration', 1, 0, 'C', true);
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
    }
    function printRows($sql) {
        $this->SetFont('Arial', '', 10);
        while ($row = $sql->fetch_object()) {
            $h = floor($row->duration_seconds / 3600); $m = floor(($row->duration_seconds % 3600) / 60);
            $this->Cell(45, 12, $row->name, 1);
            $this->Cell(60, 12, $row->purpose, 1);
            $this->Cell(70, 12, $row->dest2, 1);
            $this->Cell(30, 12, date("m/d/Y", strtotime($row->date)), 1, 0, 'C');
            $this->Cell(30, 12, date("h:i A", strtotime($row->timedept)), 1, 0, 'C');
            $this->Cell(30, 12, date("h:i A", strtotime($row->time_returned)), 1, 0, 'C');
            $this->Cell(45, 12, $h.'h '.$m.'m', 1, 0, 'C');
            $this->Ln();
        }
    }
}

$pdf = new PDF('L', 'mm', array(215.9, 400), "TCWS EMPLOYEE SUMMARY - ".date('F Y', strtotime($selected_month)));
$pdf->AddPage();

if ($range == 'today' || $filter_duration == '1') {
    $pdf->Cell(0, 10, "Official Business (TCWS)", 0, 1); $pdf->printTableHeader(); $pdf->printRows($sql_official);
    $pdf->Ln(10);
    $pdf->Cell(0, 10, "Personal Business (TCWS)", 0, 1); $pdf->printTableHeader(); $pdf->printRows($sql_personal);
} else {
    // Summary logic (Grouped by Name)
    foreach (['Official Business' => $sql_official, 'Personal' => $sql_personal] as $label => $sql) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, $label . " Summary", 0, 1);
        $totals = [];
        while ($row = $sql->fetch_object()) {
            if (!isset($totals[$row->name])) $totals[$row->name] = 0;
            $totals[$row->name] += $row->duration_seconds;
        }
        ksort($totals);
        $pdf->SetFillColor(0, 128, 0); $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(100, 10, 'Name', 1, 0, 'C', true);
        $pdf->Cell(100, 10, 'Total Time Outside', 1, 0, 'C', true);
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0); $pdf->SetFont('Arial', '', 10);
        foreach ($totals as $name => $sec) {
            $time_str = floor($sec / 3600) . 'h ' . floor(($sec % 3600) / 60) . 'm';
            $pdf->Cell(100, 10, $name, 1); $pdf->Cell(100, 10, $time_str, 1); $pdf->Ln();
        }
        $pdf->Ln(10);
    }
}
$pdf->Output('TCWS_Employees_'.date('Y-m-d').'.pdf', 'D');
?>