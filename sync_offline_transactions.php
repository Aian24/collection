<?php
/**
 * LC Lopez Collection - Offline Transaction Sync
 * Handles syncing offline transactions from the app to the database
 */

header('Content-Type: application/json');
ob_start();
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$username = $_SESSION["username"];
$branch = $_SESSION["branch"];

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['transactions']) || !is_array($data['transactions'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data format']);
    exit();
}

$transactions = $data['transactions'];
$synced = 0;
$failed = 0;
$errors = [];

// Determine table based on branch
if ($branch === 'Sanko Market') {
    $tableName = 'collected';
    $updateTable = 'sanko';
} elseif ($branch === 'Nova Market') {
    $tableName = 'collectednova';
    $updateTable = 'nova';
} elseif ($branch === 'APM') {
    $tableName = 'collectedapm';
    $updateTable = 'apm';
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid branch']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    foreach ($transactions as $index => $transaction) {
        // Validate required fields
        $required = ['collector', 'tenantcode', 'spacecode', 'tenantname', 'collected_date'];
        foreach ($required as $field) {
            if (!isset($transaction[$field]) || empty($transaction[$field])) {
                $errors[] = "Transaction #$index: Missing $field";
                $failed++;
                continue 2; // Skip to next transaction
            }
        }
        
        // Get transaction data
        $collector = $transaction['collector'];
        $tenantcode = $transaction['tenantcode'];
        $spacecode = $transaction['spacecode'];
        $tenantname = $transaction['tenantname'];
        $collected_date = $transaction['collected_date'];
        
        // Numeric values (default to 0 if not set)
        $rent = isset($transaction['rent']) ? floatval($transaction['rent']) : 0;
        $rentbal = isset($transaction['rentbal']) ? floatval($transaction['rentbal']) : 0;
        $runningBal = isset($transaction['runningbal']) ? floatval($transaction['runningbal']) : 0;
        $paidrent = isset($transaction['paidrent']) ? floatval($transaction['paidrent']) : 0;
        $paidbal = isset($transaction['paidbal']) ? floatval($transaction['paidbal']) : 0;
        $total = isset($transaction['total']) ? floatval($transaction['total']) : 0;
        $newbalance = isset($transaction['newbalance']) ? floatval($transaction['newbalance']) : 0;
        $newrentbalance = isset($transaction['newrentbalance']) ? floatval($transaction['newrentbalance']) : 0;
        $chargesString = isset($transaction['charges']) ? $transaction['charges'] : '';
        
        // Get latest transaction number
        $latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM $tableName");
        $latestTransactionRow = $latestTransactionQuery->fetch_assoc();
        $latestTransactionNumber = $latestTransactionRow['max_transaction'] ?? 0;
        $nextTransactionNumber = $latestTransactionNumber + 1;
        
        // Prepare INSERT statement
        $query = "INSERT INTO $tableName (transaction_number, collector, branch, tenantcode, spacecode, tenantname, rent, rentbal, runningbal, paidrent, paidbal, total, newbalance, newrentbalance, username, collected_date" .
            (!empty($chargesString) ? ", charges" : "") . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" .
            (!empty($chargesString) ? ", ?" : "") . ")";
        
        $insertStmt = $conn->prepare($query);
        
        // Bind parameters
        if (!empty($chargesString)) {
            $insertStmt->bind_param("isssssdddddddssss", $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, $username, $collected_date, $chargesString);
        } else {
            $insertStmt->bind_param("isssssdddddddsss", $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, $username, $collected_date);
        }
        
        // Execute INSERT
        if ($insertStmt->execute()) {
            // Update running balance and rent balance
            if ($branch !== 'APM') {
                $updateStmt = $conn->prepare("UPDATE $updateTable SET runningbal = ?, rentbal = ? WHERE spacecode = ?");
                $updateStmt->bind_param("dds", $newbalance, $newrentbalance, $spacecode);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            $synced++;
        } else {
            $errors[] = "Transaction #$index: " . $insertStmt->error;
            $failed++;
        }
        
        $insertStmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'synced' => $synced,
        'failed' => $failed,
        'total' => count($transactions),
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

