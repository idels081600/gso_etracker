<?php  
session_start();  
require_once 'logi_db.php';    

header('Content-Type: application/json');    

// Function to log errors  
function log_error($message) {          
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/error.log');  
}    

// Check if the request method is POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {          
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);          
    exit;  
}    

// Validate and sanitize the effective date  
if (empty($_POST['effectiveDate'])) {          
    echo json_encode(['success' => false, 'message' => 'Effective date is required']);          
    exit;  
}    

$effectiveDate = trim($_POST['effectiveDate']);  
$effectiveDate = htmlspecialchars($effectiveDate);   

// Check if the date is valid  
if (DateTime::createFromFormat('Y-m-d', $effectiveDate) == FALSE) {          
    echo json_encode(['success' => false, 'message' => 'Invalid effective date format. Use YYYY-MM-DD.']);          
    exit;  
}    

// Start transaction for data consistency 
mysqli_autocommit($conn, false);  

try {          
    // Prepare the SQL query to fetch approved items     
    $selectQuery = "SELECT item_id, item_name, approved_quantity AS quantity, office_name AS office FROM items_requested WHERE DATE(date_requested) = ? AND status = 'Approved'";     
    $selectStmt = mysqli_prepare($conn, $selectQuery);            
    
    if (!$selectStmt) {                  
        log_error('Select prepare failed: ' . mysqli_error($conn));                  
        throw new Exception('Failed to prepare select statement');          
    }            
    
    // Bind the effective date parameter          
    mysqli_stmt_bind_param($selectStmt, "s", $effectiveDate);            
    
    // Execute the query          
    if (!mysqli_stmt_execute($selectStmt)) {                  
        log_error('Select execute failed: ' . mysqli_stmt_error($selectStmt));                  
        throw new Exception('Failed to execute select query');          
    }      
    
    $result = mysqli_stmt_get_result($selectStmt);            
    
    // Fetch the data          
    $items = [];          
    while ($row = mysqli_fetch_assoc($result)) {                  
        $items[] = $row;          
    }            
    
    // Close select statement          
    mysqli_stmt_close($selectStmt);      
    
    // Log retrieved items for debugging
    log_error('Retrieved items from items_requested: ' . json_encode($items));
    
    // Check if any items were found          
    if (empty($items)) {                  
        echo json_encode(['success' => false, 'message' => 'No approved items found for the selected date']);
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        mysqli_close($conn);                  
        exit;     
    }      
    
    $currentDateTime = date('Y-m-d H:i:s');
    $processedItems = [];
    $insertedCount = 0;
    
    // Process each item for inventory deduction and transaction logging
    foreach ($items as $item) {
        // Get current balance from inventory_items - Use item_no column, search with item_id value
        $balanceQuery = "SELECT current_balance FROM inventory_items WHERE item_no = ?";
        $balanceStmt = mysqli_prepare($conn, $balanceQuery);
        
        if (!$balanceStmt) {
            log_error('Balance query prepare failed: ' . mysqli_error($conn));
            throw new Exception('Failed to prepare balance query');
        }
        
        mysqli_stmt_bind_param($balanceStmt, "s", $item['item_id']);
        
        if (!mysqli_stmt_execute($balanceStmt)) {
            log_error('Balance query execute failed: ' . mysqli_stmt_error($balanceStmt));
            throw new Exception('Failed to execute balance query for item: ' . $item['item_id']);
        }
        
        $balanceResult = mysqli_stmt_get_result($balanceStmt);
        $balanceRow = mysqli_fetch_assoc($balanceResult);
        mysqli_stmt_close($balanceStmt);
        
        // Check if item exists in inventory
        if (!$balanceRow) {
            throw new Exception('Item not found in inventory: ' . $item['item_id']);
        }
        
        $oldBalance = $balanceRow['current_balance'];
        $requestedQuantity = $item['quantity'];
        $newBalance = $oldBalance - $requestedQuantity;
        
        // Check if sufficient stock is available
        if ($newBalance < 0) {
            throw new Exception('Insufficient stock for item: ' . $item['item_name'] . '. Available: ' . $oldBalance . ', Requested: ' . $requestedQuantity);
        }
        
        // Update inventory_items with new balance - FIXED: Use item_id consistently
        $updateQuery = "UPDATE inventory_items SET current_balance = ? WHERE item_no = ?";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        
        if (!$updateStmt) {
            log_error('Update prepare failed: ' . mysqli_error($conn));
            throw new Exception('Failed to prepare update statement');
        }
        
        mysqli_stmt_bind_param($updateStmt, "is", $newBalance, $item['item_id']);
        
        if (!mysqli_stmt_execute($updateStmt)) {
            log_error('Update execute failed for item ' . $item['item_id'] . ': ' . mysqli_stmt_error($updateStmt));
            throw new Exception('Failed to update inventory for item: ' . $item['item_name']);
        }
        
        mysqli_stmt_close($updateStmt);
        
        // Insert transaction record - FIXED: Use item_id for item_no column
        $insertQuery = "INSERT INTO inventory_transactions (item_no, item_name, quantity, requestor, created_at, transaction_type, previous_balance, new_balance) VALUES (?, ?, ?, ?, ?, 'DEDUCTION', ?, ?)";
        $insertStmt = mysqli_prepare($conn, $insertQuery);
        
        if (!$insertStmt) {
            log_error('Insert prepare failed: ' . mysqli_error($conn));
            throw new Exception('Failed to prepare insert statement');
        }
        
        mysqli_stmt_bind_param($insertStmt, "ssissii", 
            $item['item_id'],  // FIXED: Use item_id instead of undefined item_no
            $item['item_name'], 
            $requestedQuantity, 
            $item['office'],   // FIXED: Use office instead of undefined requestor
            $currentDateTime,
            $oldBalance,
            $newBalance
        );
        
        if (mysqli_stmt_execute($insertStmt)) {
            $insertedCount++;
            $processedItems[] = [
                'item_no' => $item['item_id'],    // FIXED: Use item_id
                'item_name' => $item['item_name'],
                'quantity_deducted' => $requestedQuantity,
                'requestor' => $item['office'],   // FIXED: Use office
                'previous_balance' => $oldBalance,
                'new_balance' => $newBalance
            ];
        } else {
            log_error('Insert execute failed for item ' . $item['item_id'] . ': ' . mysqli_stmt_error($insertStmt));
            throw new Exception('Failed to insert transaction for item: ' . $item['item_name']);    
        }
        
        mysqli_stmt_close($insertStmt);
    }
    
    // Commit the transaction     
    mysqli_commit($conn);      
    
    echo json_encode([                  
        'success' => true,                  
        'message' => "Successfully processed {$insertedCount} items. Inventory updated and transactions recorded.",                  
        'items_processed' => $insertedCount,         
        'items' => $processedItems          
    ]);   
    
} catch (Exception $e) {          
    // Rollback transaction on error     
    mysqli_rollback($conn);     
    log_error('Transaction failed: ' . $e->getMessage());     
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);  
}    

// Restore autocommit and close connection 
mysqli_autocommit($conn, true); 
mysqli_close($conn);
?>