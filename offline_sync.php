<?php
// Offline Transaction Sync Handler
// This file handles syncing offline transactions to the online database

header('Content-Type: application/json');
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please login again.']);
    exit();
}

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'sync_offline_transactions') {
        // Get the offline transactions from POST data
        $offlineTransactions = json_decode($_POST['transactions'] ?? '[]', true);
        
        if (empty($offlineTransactions)) {
            echo json_encode(['status' => 'error', 'message' => 'No offline transactions to sync.']);
            exit();
        }

        $syncedCount = 0;
        $failedCount = 0;
        $errors = [];
        $syncedTransactionIds = [];

        foreach ($offlineTransactions as $index => $transaction) {
            try {
                // Validate required fields
                $requiredFields = ['collector', 'branch', 'tenantcode', 'spacecode', 'tenantname', 'collected_date'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (empty($transaction[$field])) {
                        $missingFields[] = $field;
                    }
                }

                if (!empty($missingFields)) {
                    $failedCount++;
                    $errors[] = "Transaction #" . ($index + 1) . ": Missing fields - " . implode(', ', $missingFields);
                    continue;
                }

                // Extract transaction data with defaults
                $collector = $conn->real_escape_string($transaction['collector']);
                $branch = $conn->real_escape_string($transaction['branch']);
                $tenantcode = $conn->real_escape_string($transaction['tenantcode']);
                $spacecode = $conn->real_escape_string($transaction['spacecode']);
                $tenantname = $conn->real_escape_string($transaction['tenantname']);
                $collected_date = $conn->real_escape_string($transaction['collected_date']);
                $username = $conn->real_escape_string($_SESSION["username"]);

                // Numeric values with defaults
                $rent = floatval($transaction['rent'] ?? 0);
                $rentbal = floatval($transaction['rentbal'] ?? 0);
                $runningBal = floatval($transaction['runningbal'] ?? 0);
                $paidrent = floatval($transaction['paidrent'] ?? 0);
                $paidbal = floatval($transaction['paidbal'] ?? 0);
                $total = floatval($transaction['total'] ?? 0);
                $newbalance = floatval($transaction['newbalance'] ?? 0);
                $newrentbalance = floatval($transaction['newrentbalance'] ?? 0);
                $chargesString = $conn->real_escape_string($transaction['charges'] ?? '');

                // Determine the table based on branch
                $tableName = '';
                if ($branch === 'Sanko Market') {
                    $tableName = 'collected';
                } elseif ($branch === 'Nova Market') {
                    $tableName = 'collectednova';
                } elseif ($branch === 'APM') {
                    $tableName = 'collectedapm';
                } else {
                    $failedCount++;
                    $errors[] = "Transaction #" . ($index + 1) . ": Invalid branch - " . $branch;
                    continue;
                }

                // Get the latest transaction number for this branch
                $latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM $tableName");
                $latestTransactionRow = $latestTransactionQuery->fetch_assoc();
                $nextTransactionNumber = ($latestTransactionRow['max_transaction'] ?? 0) + 1;

                // Prepare the INSERT statement
                $query = "INSERT INTO $tableName (transaction_number, collector, branch, tenantcode, spacecode, tenantname, rent, rentbal, runningbal, paidrent, paidbal, total, newbalance, newrentbalance, username, collected_date" .
                    (!empty($chargesString) ? ", charges" : "") . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" .
                    (!empty($chargesString) ? ", ?" : "") . ")";

                $insertStmt = $conn->prepare($query);

                if (!$insertStmt) {
                    $failedCount++;
                    $errors[] = "Transaction #" . ($index + 1) . ": Database prepare error - " . $conn->error;
                    continue;
                }

                // Bind parameters
                if (!empty($chargesString)) {
                    $insertStmt->bind_param("isssssdddddddddss", $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, $username, $collected_date, $chargesString);
                } else {
                    $insertStmt->bind_param("isssssddddddddds", $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, $username, $collected_date);
                }

                // Execute the INSERT
                if ($insertStmt->execute()) {
                    // Update running balance and rent balance based on branch
                    $updateTable = ($branch === 'Sanko Market') ? 'sanko' : (($branch === 'Nova Market') ? 'nova' : 'apm');
                    $updateStmt = $conn->prepare("UPDATE $updateTable SET runningbal = ?, rentbal = ? WHERE spacecode = ?");
                    
                    if ($updateStmt) {
                        $updateStmt->bind_param("dds", $newbalance, $newrentbalance, $spacecode);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }

                    $syncedCount++;
                    $syncedTransactionIds[] = $transaction['localId'] ?? $index;
                } else {
                    $failedCount++;
                    $errors[] = "Transaction #" . ($index + 1) . ": Insert error - " . $insertStmt->error;
                }

                $insertStmt->close();

            } catch (Exception $e) {
                $failedCount++;
                $errors[] = "Transaction #" . ($index + 1) . ": Exception - " . $e->getMessage();
            }
        }

        // Prepare response
        $response = [
            'status' => $syncedCount > 0 ? 'success' : 'error',
            'synced_count' => $syncedCount,
            'failed_count' => $failedCount,
            'total_count' => count($offlineTransactions),
            'synced_ids' => $syncedTransactionIds
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if ($syncedCount > 0) {
            $response['message'] = "$syncedCount transaction(s) synced successfully" . 
                ($failedCount > 0 ? ", $failedCount failed." : ".");
        } else {
            $response['message'] = "Failed to sync transactions. Please try again.";
        }

        echo json_encode($response);

    } elseif ($action == 'check_connection') {
        // Simple endpoint to check if the server is reachable
        echo json_encode(['status' => 'success', 'message' => 'Connected to server.', 'timestamp' => time()]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Please use POST.']);
}

$conn->close();
?>

