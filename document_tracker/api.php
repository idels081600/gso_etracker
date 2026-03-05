<?php
// Document Tracking System API
// Turn off error reporting to prevent HTML errors in JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Security: Start session and check authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
    exit();
}

// Security: Regenerate session ID to prevent session fixation
session_regenerate_id(true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once('asset_db.php');

// Set the character set
$conn->set_charset("utf8mb4");

// Get the action from request
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Response array
$response = ['success' => false, 'message' => '', 'data' => null];

switch ($action) {
    case 'create':
        createDocument($conn);
        break;
    case 'read':
        readDocuments($conn);
        break;
    case 'read_one':
        readOneDocument($conn);
        break;
    case 'update':
        updateDocument($conn);
        break;
    case 'update_direction':
        updateDocumentDirection($conn);
        break;
    case 'update_status':
        updateDocumentStatus($conn);
        break;
    case 'delete':
        deleteDocument($conn);
        break;
    case 'track':
        trackDocument($conn);
        break;
    case 'check_barcode':
        checkBarcode($conn);
        break;
    case 'statistics':
        getStatistics($conn);
        break;
    default:
        $response['message'] = 'Invalid action';
        echo json_encode($response);
        break;
}

// Create new document
function createDocument($conn) {
    global $response;
    
    $tracking_no = generateTrackingNumber($conn, $_POST['doc_direction'] ?? 'incoming');
    $description = sanitizeInput($conn, $_POST['description'] ?? '');
    $doc_type = sanitizeInput($conn, $_POST['doc_type'] ?? '');
    $date_received = sanitizeInput($conn, $_POST['date_received'] ?? date('Y-m-d'));
    $status = sanitizeInput($conn, $_POST['status'] ?? 'Pending');
    $date_released = !empty($_POST['date_released']) ? sanitizeInput($conn, $_POST['date_released']) : NULL;
    $date_deadline = !empty($_POST['date_deadline']) ? sanitizeInput($conn, $_POST['date_deadline']) : NULL;
    $destination = sanitizeInput($conn, $_POST['destination'] ?? '');
    $barcode = sanitizeInput($conn, $_POST['barcode'] ?? '');
    $doc_direction = sanitizeInput($conn, $_POST['doc_direction'] ?? 'incoming');
    
    // Validation
    if (empty($description) || empty($doc_type)) {
        $response['message'] = 'Description and document type are required';
        echo json_encode($response);
        return;
    }
    
    // Check if barcode already exists
    if (!empty($barcode)) {
        $checkSql = "SELECT id FROM doc_tracker WHERE barcode = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $barcode);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $response['message'] = 'Barcode already exists';
            $response['barcode_exists'] = true;
            echo json_encode($response);
            return;
        }
    }
    
    $sql = "INSERT INTO doc_tracker (tracking_no, description, doc_type, date_received, status, date_released, date_deadline, destination, barcode, doc_direction) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssssss', $tracking_no, $description, $doc_type, $date_received, $status, $date_released, $date_deadline, $destination, $barcode, $doc_direction);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Document saved successfully';
        $response['data'] = [
            'id' => $stmt->insert_id,
            'tracking_no' => $tracking_no,
            'description' => $description,
            'doc_type' => $doc_type,
            'date_received' => $date_received,
            'status' => $status,
            'date_released' => $date_released,
            'date_deadline' => $date_deadline,
            'destination' => $destination,
            'barcode' => $barcode,
            'doc_direction' => $doc_direction
        ];
    } else {
        $response['message'] = 'Error saving document: ' . $conn->error;
    }
    
    echo json_encode($response);
}

// Read all documents
function readDocuments($conn) {
    global $response;
    
    $doc_direction = sanitizeInput($conn, $_GET['doc_direction'] ?? 'incoming');
    
    $sql = "SELECT * FROM doc_tracker WHERE doc_direction = ? ORDER BY date_received DESC, id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $doc_direction);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    $response['success'] = true;
    $response['data'] = $documents;
    echo json_encode($response);
}

// Read one document
function readOneDocument($conn) {
    global $response;
    
    $id = sanitizeInput($conn, $_GET['id'] ?? '');
    
    if (empty($id)) {
        $response['message'] = 'Document ID is required';
        echo json_encode($response);
        return;
    }
    
    $sql = "SELECT * FROM doc_tracker WHERE id = ? OR tracking_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $id, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
    } else {
        $response['message'] = 'Document not found';
    }
    
    echo json_encode($response);
}

// Update document
function updateDocument($conn) {
    global $response;
    
    $id = sanitizeInput($conn, $_POST['id'] ?? '');
    $description = sanitizeInput($conn, $_POST['description'] ?? '');
    $doc_type = sanitizeInput($conn, $_POST['doc_type'] ?? '');
    $date_received = sanitizeInput($conn, $_POST['date_received'] ?? '');
    $status = sanitizeInput($conn, $_POST['status'] ?? '');
    $date_released = !empty($_POST['date_released']) ? sanitizeInput($conn, $_POST['date_released']) : NULL;
    $date_deadline = !empty($_POST['date_deadline']) ? sanitizeInput($conn, $_POST['date_deadline']) : NULL;
    $destination = sanitizeInput($conn, $_POST['destination'] ?? '');
    
    if (empty($id)) {
        $response['message'] = 'Document ID is required';
        echo json_encode($response);
        return;
    }
    
    $sql = "UPDATE doc_tracker SET 
            description = ?, 
            doc_type = ?, 
            date_received = ?, 
            status = ?, 
            date_released = ?, 
            date_deadline = ?, 
            destination = ? 
            WHERE id = ? OR tracking_no = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssss', $description, $doc_type, $date_received, $status, $date_released, $date_deadline, $destination, $id, $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Document updated successfully';
    } else {
        $response['message'] = 'Error updating document: ' . $conn->error;
    }
    
    echo json_encode($response);
}

// Update document direction (mark as outgoing)
function updateDocumentDirection($conn) {
    global $response;
    
    $id = sanitizeInput($conn, $_POST['id'] ?? '');
    $description = sanitizeInput($conn, $_POST['description'] ?? '');
    $doc_type = sanitizeInput($conn, $_POST['doc_type'] ?? '');
    $date_received = sanitizeInput($conn, $_POST['date_received'] ?? date('Y-m-d'));
    $status = sanitizeInput($conn, $_POST['status'] ?? 'In Transit');
    $destination = sanitizeInput($conn, $_POST['destination'] ?? '');
    $date_deadline = !empty($_POST['date_deadline']) ? sanitizeInput($conn, $_POST['date_deadline']) : NULL;
    $doc_direction = sanitizeInput($conn, $_POST['doc_direction'] ?? 'outgoing');
    
    if (empty($id)) {
        $response['message'] = 'Document ID is required';
        echo json_encode($response);
        return;
    }
    
    if (empty($destination)) {
        $response['message'] = 'Destination is required';
        echo json_encode($response);
        return;
    }
    
    // Update the document with new direction and outgoing info
    $sql = "UPDATE doc_tracker SET 
            description = ?, 
            doc_type = ?, 
            date_received = ?, 
            status = ?, 
            destination = ?, 
            date_deadline = ?, 
            doc_direction = ? 
            WHERE id = ? OR tracking_no = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssss', $description, $doc_type, $date_received, $status, $destination, $date_deadline, $doc_direction, $id, $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Document marked as outgoing successfully';
    } else {
        $response['message'] = 'Error updating document: ' . $conn->error;
    }
    
    echo json_encode($response);
}

// Delete document
function deleteDocument($conn) {
    global $response;
    
    $id = sanitizeInput($conn, $_REQUEST['id'] ?? '');
    
    if (empty($id)) {
        $response['message'] = 'Document ID is required';
        echo json_encode($response);
        return;
    }
    
    $sql = "DELETE FROM doc_tracker WHERE id = ? OR tracking_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $id, $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Document deleted successfully';
    } else {
        $response['message'] = 'Error deleting document: ' . $conn->error;
    }
    
    echo json_encode($response);
}

// Update document status (mark as returned)
function updateDocumentStatus($conn) {
    global $response;
    
    $id = sanitizeInput($conn, $_POST['id'] ?? '');
    $status = sanitizeInput($conn, $_POST['status'] ?? 'Returned');
    $date_released = !empty($_POST['date_released']) ? sanitizeInput($conn, $_POST['date_released']) : date('Y-m-d');
    
    if (empty($id)) {
        $response['message'] = 'Document ID is required';
        echo json_encode($response);
        return;
    }
    
    $sql = "UPDATE doc_tracker SET status = ?, date_released = ? WHERE id = ? OR tracking_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $status, $date_released, $id, $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Document status updated successfully';
    } else {
        $response['message'] = 'Error updating document status: ' . $conn->error;
    }
    
    echo json_encode($response);
}

// Track document
function trackDocument($conn) {
    global $response;
    
    $search = sanitizeInput($conn, $_GET['search'] ?? '');
    
    if (empty($search)) {
        $response['message'] = 'Tracking number or barcode is required';
        echo json_encode($response);
        return;
    }
    
    $sql = "SELECT * FROM doc_tracker WHERE tracking_no = ? OR barcode = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $doc = $result->fetch_assoc();
        
        // Build history based on document direction and status
        $history = buildDocumentHistory($doc);
        
        $response['success'] = true;
        $response['data'] = $doc;
        $response['history'] = $history;
    } else {
        $response['message'] = 'Document not found';
    }
    
    echo json_encode($response);
}

// Check if barcode exists
function checkBarcode($conn) {
    global $response;
    
    $barcode = sanitizeInput($conn, $_GET['barcode'] ?? '');
    
    if (empty($barcode)) {
        $response['message'] = 'Barcode is required';
        echo json_encode($response);
        return;
    }
    
    $sql = "SELECT * FROM doc_tracker WHERE barcode = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $doc = $result->fetch_assoc();
        $history = buildDocumentHistory($doc);
        
        $response['success'] = true;
        $response['exists'] = true;
        $response['data'] = $doc;
        $response['history'] = $history;
    } else {
        $response['success'] = true;
        $response['exists'] = false;
    }
    
    echo json_encode($response);
}

// Get statistics
function getStatistics($conn) {
    global $response;
    
    // Count incoming documents
    $incomingSql = "SELECT COUNT(*) as count FROM doc_tracker WHERE doc_direction = 'incoming'";
    $incomingResult = $conn->query($incomingSql);
    $incomingCount = $incomingResult->fetch_assoc()['count'];
    
    // Count outgoing documents
    $outgoingSql = "SELECT COUNT(*) as count FROM doc_tracker WHERE doc_direction = 'outgoing'";
    $outgoingResult = $conn->query($outgoingSql);
    $outgoingCount = $outgoingResult->fetch_assoc()['count'];
    
    // Count pending documents
    $pendingSql = "SELECT COUNT(*) as count FROM doc_tracker WHERE status IN ('Pending', 'In Transit')";
    $pendingResult = $conn->query($pendingSql);
    $pendingCount = $pendingResult->fetch_assoc()['count'];
    
    // Count processed/completed documents
    $processedSql = "SELECT COUNT(*) as count FROM doc_tracker WHERE status IN ('Processed', 'Delivered', 'Received', 'Returned')";
    $processedResult = $conn->query($processedSql);
    $processedCount = $processedResult->fetch_assoc()['count'];
    
    $response['success'] = true;
    $response['data'] = [
        'incoming' => $incomingCount,
        'outgoing' => $outgoingCount,
        'pending' => $pendingCount,
        'processed' => $processedCount
    ];
    
    echo json_encode($response);
}

// Generate tracking number
function generateTrackingNumber($conn, $doc_direction) {
    $year = date('Y');
    $prefix = $doc_direction === 'incoming' ? 'INC' : 'OUT';
    
    // Get the count of documents for this year and direction
    $sql = "SELECT COUNT(*) as count FROM doc_tracker WHERE doc_direction = ? AND YEAR(date_received) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $doc_direction, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'] + 1;
    
    $number = str_pad($count, 4, '0', STR_PAD_LEFT);
    return "{$prefix}-{$year}-{$number}";
}

// Build document history for tracking
function buildDocumentHistory($doc) {
    $history = [];
    $doc_direction = $doc['doc_direction'];
    
    if ($doc_direction === 'incoming') {
        // Document Received
        $history[] = [
            'action' => 'Document Received',
            'date' => $doc['date_received'],
            'location' => 'ASSET OFFICE',
            'status' => 'completed'
        ];
        
        // Barcode Scanned
        if (!empty($doc['barcode'])) {
            $history[] = [
                'action' => 'Barcode Scanned: ' . $doc['barcode'],
                'date' => $doc['date_received'],
                'location' => 'ASSET OFFICE',
                'status' => 'completed'
            ];
        }
        
        // Status updates
        if ($doc['status'] === 'In Progress') {
            $history[] = [
                'action' => 'Document In Progress',
                'date' => $doc['date_received'],
                'location' => 'ASSET OFFICE',
                'status' => 'completed'
            ];
        }
        
        if ($doc['status'] === 'Processed') {
            $history[] = [
                'action' => 'Document Processed',
                'date' => $doc['date_released'] ?? $doc['date_received'],
                'location' => 'ASSET OFFICE',
                'status' => 'completed'
            ];
        }
    } else {
        // Outgoing document
        $history[] = [
            'action' => 'Document Sent',
            'date' => $doc['date_received'],
            'location' => 'ASSET OFFICE',
            'status' => $doc['status'] === 'Pending' ? 'pending' : 'completed'
        ];
        
        // Barcode Scanned
        if (!empty($doc['barcode'])) {
            $history[] = [
                'action' => 'Barcode Scanned: ' . $doc['barcode'],
                'date' => $doc['date_received'],
                'location' => 'ASSET OFFICE',
                'status' => 'completed'
            ];
        }
        
        // In Transit
        if (!empty($doc['destination'])) {
            $history[] = [
                'action' => 'In Transit to: ' . $doc['destination'],
                'date' => $doc['date_received'],
                'location' => $doc['destination'],
                'status' => in_array($doc['status'], ['In Transit', 'Delivered', 'Received', 'Returned']) ? 'completed' : 'pending'
            ];
            
            // Delivered
            $history[] = [
                'action' => 'Delivered to: ' . $doc['destination'],
                'date' => $doc['date_released'] ?? '-',
                'location' => $doc['destination'],
                'status' => in_array($doc['status'], ['Delivered', 'Received', 'Returned']) ? 'completed' : 'pending'
            ];
        }
        
        // Return deadline if set
        if (!empty($doc['date_deadline'])) {
            $isOverdue = strtotime($doc['date_deadline']) < time() && $doc['status'] !== 'Returned';
            $history[] = [
                'action' => 'Expected Return: ' . formatDate($doc['date_deadline']),
                'date' => $doc['date_deadline'],
                'location' => 'ASSET OFFICE',
                'status' => $isOverdue ? 'overdue' : 'pending'
            ];
        }
        
        // Returned
        if ($doc['status'] === 'Returned') {
            $history[] = [
                'action' => 'Document Returned',
                'date' => $doc['date_released'] ?? date('Y-m-d'),
                'location' => 'ASSET OFFICE',
                'status' => 'completed'
            ];
        }
    }
    
    return $history;
}

// Format date
function formatDate($dateString) {
    if (empty($dateString) || $dateString === '-') return '-';
    return date('M d, Y', strtotime($dateString));
}

// Sanitize input
function sanitizeInput($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

$conn->close();
?>