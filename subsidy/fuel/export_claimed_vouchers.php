<?php
session_start();
require_once 'db_fuel.php';

// Security check - redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

// Check if station_id is provided via GET parameter (for export modal selection)
if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
    $station_id = (int)$_GET['station_id'];
    
    // Get station name from database
    $station_sql = "SELECT station_name FROM gas_stations WHERE id = $station_id";
    $station_result = mysqli_query($conn, $station_sql);
    
    if (mysqli_num_rows($station_result) > 0) {
        $station_data = mysqli_fetch_assoc($station_result);
        $station_name = $station_data['station_name'];
    } else {
        die('Invalid station selected.');
    }
} else {
    // Fallback to session station_id if no parameter provided
    if (!isset($_SESSION['station_id']) || empty($_SESSION['station_id'])) {
        // Check if user has station in database
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $check_sql = "SELECT us.station_id, gs.station_name 
                      FROM user_stations us 
                      JOIN gas_stations gs ON us.station_id = gs.id 
                      WHERE us.username = '$username'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Set session
            $station_data = mysqli_fetch_assoc($check_result);
            $_SESSION['station_id'] = $station_data['station_id'];
            $_SESSION['station_name'] = $station_data['station_name'];
            $station_id = (int)$_SESSION['station_id'];
            $station_name = $_SESSION['station_name'];
        } else {
            // Redirect to station selection
            header("Location: select_station.php");
            exit();
        }
    } else {
        $station_id = (int)$_SESSION['station_id'];
        $station_name = isset($_SESSION['station_name']) ? $_SESSION['station_name'] : 'Unknown Station';
    }
}

require_once '../../fpdf/fpdf.php';

// Get current date for title
$currentDate = date('F j, Y');

// Fetch claimed vouchers with driver info filtered by station
$sql = "SELECT tr.driver_name, tr.tricycle_no, vc.voucher_number, vc.claim_date, vc.e_signature, gs.station_name
        FROM voucher_claims vc
        INNER JOIN tricycle_records tr ON vc.tricycle_id = tr.id
        LEFT JOIN gas_stations gs ON vc.station_id = gs.id
        WHERE vc.e_signature IS NOT NULL AND vc.e_signature != ''
        AND vc.station_id = $station_id
        ORDER BY tr.tricycle_no, vc.claim_date, vc.voucher_number";

$result = mysqli_query($conn, $sql);

// Group data by tricycle and date to handle multiple vouchers with single signature
$groupedData = [];
while ($row = mysqli_fetch_assoc($result)) {
    $key = $row['tricycle_no'] . '_' . date('Y-m-d', strtotime($row['claim_date']));
    
    if (!isset($groupedData[$key])) {
        $groupedData[$key] = [
            'driver_name' => $row['driver_name'],
            'tricycle_no' => $row['tricycle_no'],
            'vouchers' => [],
            'claim_date' => $row['claim_date'],
            'e_signature' => $row['e_signature']
        ];
    }
    $groupedData[$key]['vouchers'][] = $row['voucher_number'];
}

// Create PDF
class PDF extends FPDF {
    function Header() {
        // Logo (optional - uncomment if you have a logo)
        // $this->Image('../../logo.png', 10, 10, 30);
        
        // Station Name
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $GLOBALS['station_name'], 0, 1, 'C');
        
        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'CLAIMED VOUCHERS AS OF ' . $GLOBALS['currentDate'], 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape orientation
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Table header
$pdf->SetFillColor(200, 200, 200);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 10, 'Name', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Tricycle No.', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Voucher No. Claimed', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Date Claimed', 1, 0, 'C', true);
$pdf->Cell(100, 10, 'E-Signature', 1, 1, 'C', true);

// Table data
$pdf->SetFont('Arial', '', 9);

if (empty($groupedData)) {
    $pdf->Cell(270, 10, 'No claimed vouchers found.', 1, 1, 'C');
} else {
    foreach ($groupedData as $data) {
        // Calculate row height based on signature
        $rowHeight = 20;
        
        // Name
        $pdf->Cell(50, $rowHeight, $data['driver_name'], 1, 0, 'C');
        
        // Tricycle Number
        $pdf->Cell(30, $rowHeight, $data['tricycle_no'], 1, 0, 'C');
        
        // Voucher numbers claimed (format: tricycle_no-voucher_number)
        $formattedVouchers = [];
        foreach ($data['vouchers'] as $vnum) {
            $formattedVouchers[] = $data['tricycle_no'] . '-' . $vnum;
        }
        $voucherStr = implode(', ', $formattedVouchers);
        $pdf->Cell(50, $rowHeight, $voucherStr, 1, 0, 'C');
        
        // Date Claimed
        $claimDateFormatted = date('M j, Y', strtotime($data['claim_date']));
        $pdf->Cell(40, $rowHeight, $claimDateFormatted, 1, 0, 'C');
        
        // E-Signature (as image)
        $sigX = $pdf->GetX();
        $sigY = $pdf->GetY();
        $pdf->Rect($sigX, $sigY, 100, $rowHeight);
        
        // Add signature image if it's base64
        if (!empty($data['e_signature'])) {
            // Check if it's base64 data
            if (strpos($data['e_signature'], 'data:image') === 0) {
                // Extract the image type from the data URI
                preg_match('/data:image\/(\w+);base64,/', $data['e_signature'], $typeMatch);
                $imageType = isset($typeMatch[1]) ? strtolower($typeMatch[1]) : 'png';
                
                $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $data['e_signature']);
                $imageData = base64_decode($base64String);
                
                // Create temp file for image with proper extension
                $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.' . $imageType;
                file_put_contents($tempFile, $imageData);
                
                // Add image to PDF (centered in cell)
                $pdf->Image($tempFile, $sigX + 20, $sigY + 2, 60, 16);
                
                // Delete temp file
                unlink($tempFile);
            }
        }
        
        $pdf->SetXY($sigX + 100, $sigY);
        $pdf->Ln($rowHeight);
    }
}

// Add signature line at the bottom right of the last page
$pdf->SetY(-50);
$pdf->SetX(-80);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(70, 0, '', 'B', 1, 'C');
$pdf->SetX(-80);
$pdf->Cell(70, 8, 'Signature:', 0, 1, 'C');

// Output PDF
$pdf->Output('D', 'Claimed_Vouchers_' . date('Y-m-d') . '.pdf');
?>
