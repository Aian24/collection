<?php
/**
 * Manual Trigger for Auto Update Rent Balance
 * This page allows admin to manually trigger the rent balance update
 */

// Handle AJAX requests FIRST (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'run_update') {
    // Start clean
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Suppress all errors from output
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Set longer timeout for updates
    set_time_limit(300); // 5 minutes
    
    try {
        session_start();
        
        // Check login immediately
        if (!isset($_SESSION["username"])) {
            throw new Exception("Not logged in");
        }
        
        // Include config
        require_once 'config.php';
        
        if (!isset($conn) || $conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        // Set timezone
        date_default_timezone_set('Asia/Manila');
        
        // Include auto update
        require_once 'auto_update_rentbal.php';
        
        // Check if function exists
        if (!function_exists('runAutoUpdate')) {
            throw new Exception("Update function not found");
        }
        
        // Check if update has already been run for this month
        if (isUpdateAlreadyRun($conn)) {
            $currentMonth = date('F Y');
            throw new Exception("Rent balance update has already been run for $currentMonth. The system will allow updates again next month.");
        }
        
        // Run update
        $updated = runAutoUpdate();
        
        // Validate result
        if (!is_numeric($updated)) {
            $updated = 0;
        }
        
        // Success response
        $result = [
            'success' => true,
            'message' => 'Update completed successfully!',
            'tenants_updated' => intval($updated),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        error_log("Manual Update Error: " . $e->getMessage());
        $result = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    } catch (Error $e) {
        error_log("Manual Update Fatal Error: " . $e->getMessage());
        $result = [
            'success' => false,
            'message' => 'System error: ' . $e->getMessage()
        ];
    }
    
    // Clean all buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send response
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Content-Length: ' . strlen(json_encode($result)));
    
    echo json_encode($result);
    
    // Close connection if exists
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    
    exit(0);
    } elseif ($_POST['action'] === 'get_transaction_free_days') {
        // Handle request for transaction-free days data
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        try {
            session_start();
            
            // Check login immediately
            if (!isset($_SESSION["username"])) {
                throw new Exception("Not logged in");
            }
            
            // Include config
            require_once 'config.php';
            
            if (!isset($conn) || $conn->connect_error) {
                throw new Exception("Database connection failed");
            }
            
            // Set timezone
            date_default_timezone_set('Asia/Manila');
            
            // Get no transactions data
            $data = getNoTransactionsData($conn);
            
            $result = [
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Transaction Free Days Error: " . $e->getMessage());
            $result = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (Error $e) {
            error_log("Transaction Free Days Fatal Error: " . $e->getMessage());
            $result = [
                'success' => false,
                'message' => 'System error: ' . $e->getMessage()
            ];
        }
        
        // Clean all buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send response
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Length: ' . strlen(json_encode($result)));
        
        echo json_encode($result);
        
        // Close connection if exists
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
        
        exit(0);
    }
}

// Normal page load
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Include the auto update script
include 'auto_update_rentbal.php';

/**
 * Get no transactions data for each tenant in the previous month
 */
function getNoTransactionsData($conn) {
    $data = [];
    
    // Get previous month range
    $firstDayCurrentMonth = date('Y-m-01');
    $lastDayPreviousMonth = date('Y-m-d', strtotime($firstDayCurrentMonth . ' -1 day'));
    $firstDayPreviousMonth = date('Y-m-01', strtotime($lastDayPreviousMonth));
    
    $branches = [
        'Sanko Market' => 'sanko',
        'Nova Market' => 'nova',
        'APM' => 'apm'
    ];
    
    foreach ($branches as $branchName => $tableName) {
        $data[$branchName] = [
            'branch' => $branchName,
            'tenants' => [],
            'summary' => [
                'total_tenants' => 0,
                'tenants_without_transactions' => 0,
                'total_days_without_transactions' => 0
            ]
        ];
        
        // Get all tenants from this branch
        $result = $conn->query("SELECT id, tenantcode, tenantname, spacecode, daily, rentbal, runningbal 
                               FROM $tableName 
                               WHERE spacecode != 'Ambulant' AND spacecode != ''");
        
        if (!$result) continue;
        
        $totalTenants = 0;
        $tenantsWithoutTransactions = 0;
        $totalDaysWithoutTransactions = 0;
        
        while ($tenant = $result->fetch_assoc()) {
            $totalTenants++;
            $spacecode = $tenant['spacecode'];
            
            // Check if tenant had any transactions in previous month
            $transactionDays = getTransactionDaysInMonth($conn, $spacecode, $branchName, $firstDayPreviousMonth, $lastDayPreviousMonth);
            $daysInMonth = date('t', strtotime($firstDayPreviousMonth));
            $daysWithoutTransactions = $daysInMonth - count($transactionDays);
            
            // Check if this tenant was already updated for previous month
            $isUpdatedAlready = isTenantUpdatedForPreviousMonth($conn, $tenant['id'], $tableName, $firstDayPreviousMonth);
            
            $tenantData = [
                'id' => $tenant['id'],
                'tenantcode' => $tenant['tenantcode'],
                'tenantname' => $tenant['tenantname'],
                'spacecode' => $spacecode,
                'daily_rent' => floatval($tenant['daily']),
                'current_rentbal' => floatval($tenant['rentbal']),
                'current_runningbal' => floatval($tenant['runningbal']),
                'days_in_previous_month' => $daysInMonth,
                'days_with_transactions' => count($transactionDays),
                'days_without_transactions' => $daysWithoutTransactions,
                'transaction_dates' => $transactionDays,
                'has_transactions' => count($transactionDays) > 0,
                'estimated_unpaid_rent' => $daysWithoutTransactions * floatval($tenant['daily']),
                'is_updated_already' => $isUpdatedAlready,
                'update_status' => $isUpdatedAlready ? 'Updated Already' : ($daysWithoutTransactions > 0 ? 'Needs Update' : 'Up to Date')
            ];
            
            $data[$branchName]['tenants'][] = $tenantData;
            
            if ($daysWithoutTransactions > 0) {
                $tenantsWithoutTransactions++;
                $totalDaysWithoutTransactions += $daysWithoutTransactions;
            }
        }
        
        // Count tenants by update status
        $updatedAlready = 0;
        $needsUpdate = 0;
        $upToDate = 0;
        
        foreach ($data[$branchName]['tenants'] as $tenant) {
            if ($tenant['is_updated_already']) {
                $updatedAlready++;
            } elseif ($tenant['days_without_transactions'] > 0) {
                $needsUpdate++;
            } else {
                $upToDate++;
            }
        }
        
        $data[$branchName]['summary'] = [
            'total_tenants' => $totalTenants,
            'tenants_without_transactions' => $tenantsWithoutTransactions,
            'total_days_without_transactions' => $totalDaysWithoutTransactions,
            'updated_already' => $updatedAlready,
            'needs_update' => $needsUpdate,
            'up_to_date' => $upToDate,
            'previous_month' => date('F Y', strtotime($firstDayPreviousMonth)),
            'month_range' => $firstDayPreviousMonth . ' to ' . $lastDayPreviousMonth
        ];
        
        $result->free();
    }
    
    return $data;
}

/**
 * Get specific days when tenant had transactions in a given month
 */
function getTransactionDaysInMonth($conn, $spacecode, $branch, $startDate, $endDate) {
    $table = '';
    switch ($branch) {
        case 'Sanko Market':
            $table = 'collected';
            break;
        case 'Nova Market':
            $table = 'collectednova';
            break;
        case 'APM':
            $table = 'collectedapm';
            break;
        default:
            return [];
    }
    
    $stmt = $conn->prepare("SELECT DISTINCT DATE(collected_date) as transaction_date 
                           FROM $table 
                           WHERE spacecode = ? 
                           AND branch = ? 
                           AND DATE(collected_date) BETWEEN ? AND ?
                           ORDER BY transaction_date");
    $stmt->bind_param("ssss", $spacecode, $branch, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactionDays = [];
    while ($row = $result->fetch_assoc()) {
        $transactionDays[] = $row['transaction_date'];
    }
    $stmt->close();
    
    return $transactionDays;
}

/**
 * Check if update has already been run for this month
 */
function isUpdateAlreadyRun($conn) {
    // Check if there's a log entry for current month's update
    $currentMonth = date('Y-m');
    $logFile = 'auto_update_log.txt';
    
    if (!file_exists($logFile)) {
        return false;
    }
    
    $logContent = file_get_contents($logFile);
    
    // Look for current month in the log with "Total tenants updated" to ensure it's a complete run
    $pattern = '/' . preg_quote($currentMonth, '/') . '.*Total tenants updated/s';
    return preg_match($pattern, $logContent);
}

/**
 * Check if a specific tenant was already updated for the previous month
 */
function isTenantUpdatedForPreviousMonth($conn, $tenantId, $tableName, $previousMonthFirstDay) {
    // Check if there's evidence that this tenant was updated for the previous month
    // This can be determined by checking if the rent balance was updated based on previous month's rent
    
    // Get the tenant's current data
    $result = $conn->query("SELECT daily, rentbal, runningbal FROM $tableName WHERE id = $tenantId");
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    
    $tenant = $result->fetch_assoc();
    $daily = floatval($tenant['daily']);
    $currentRentBal = floatval($tenant['rentbal']);
    $currentRunningBal = floatval($tenant['runningbal']);
    
    // Calculate what the rent balance should be for the previous month
    $daysInPreviousMonth = date('t', strtotime($previousMonthFirstDay));
    $expectedRentBal = $daily * $daysInPreviousMonth;
    
    // If the current rent balance matches the expected previous month's rent,
    // it's likely this tenant was already updated
    $tolerance = 0.01; // Small tolerance for floating point comparison
    $isUpdated = abs($currentRentBal - $expectedRentBal) < $tolerance;
    
    // Also check if there's a log entry indicating this tenant was updated
    $logFile = 'auto_update_log.txt';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $previousMonthName = date('F Y', strtotime($previousMonthFirstDay));
        
        // Look for log entries that mention updating this tenant in the context of previous month
        $tenantNamePattern = '/Updated.*' . preg_quote($tenant['tenantname'], '/') . '/i';
        $monthPattern = '/' . preg_quote($previousMonthName, '/') . '/';
        
        if (preg_match($tenantNamePattern, $logContent) && preg_match($monthPattern, $logContent)) {
            $isUpdated = true;
        }
    }
    
    return $isUpdated;
}

// Get statistics for display
function getTenantStatistics($conn) {
    $stats = [];
    
    $branches = [
        'Sanko Market' => 'sanko',
        'Nova Market' => 'nova',
        'APM' => 'apm'
    ];
    
    foreach ($branches as $branchName => $tableName) {
        // Get total tenants
        $result = $conn->query("SELECT COUNT(*) as total FROM $tableName WHERE spacecode != 'Ambulant' AND spacecode != ''");
        $row = $result->fetch_assoc();
        $total = $row['total'];
        
        // Get tenants with positive rent balance
        $result = $conn->query("SELECT COUNT(*) as with_balance FROM $tableName WHERE CAST(rentbal AS DECIMAL(10,2)) > 0");
        $row = $result->fetch_assoc();
        $withBalance = $row['with_balance'];
        
        // Get tenants with arrears
        $result = $conn->query("SELECT COUNT(*) as with_arrears FROM $tableName WHERE CAST(runningbal AS DECIMAL(10,2)) > 0");
        $row = $result->fetch_assoc();
        $withArrears = $row['with_arrears'];
        
        // Get total rent balance
        $result = $conn->query("SELECT SUM(CAST(rentbal AS DECIMAL(10,2))) as total_rentbal FROM $tableName");
        $row = $result->fetch_assoc();
        $totalRentBal = $row['total_rentbal'] ?? 0;
        
        // Get total arrears
        $result = $conn->query("SELECT SUM(CAST(runningbal AS DECIMAL(10,2))) as total_arrears FROM $tableName");
        $row = $result->fetch_assoc();
        $totalArrears = $row['total_arrears'] ?? 0;
        
        $stats[$branchName] = [
            'total' => $total,
            'with_balance' => $withBalance,
            'with_arrears' => $withArrears,
            'total_rentbal' => $totalRentBal,
            'total_arrears' => $totalArrears
        ];
    }
    
    return $stats;
}

$statistics = getTenantStatistics($conn);

// Read last 20 lines from log file
$log_content = '';
if (file_exists('auto_update_log.txt')) {
    $lines = file('auto_update_log.txt');
    $last_lines = array_slice($lines, -50);
    $log_content = implode('', $last_lines);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Update Rent Balance</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .log-container {
            background-color: #1a1a1a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            animation: slideDown 0.3s ease;
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
            border-radius: 16px 16px 0 0;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-radius: 0 0 16px 16px;
            background-color: #f9fafb;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideDown {
            from { 
                transform: translateY(-50px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 16px 0;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            border-radius: 4px;
            transition: width 0.3s ease;
            animation: progressAnimation 1.5s ease-in-out infinite;
        }
        
        @keyframes progressAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Spinner */
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Success Animation */
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }
        
        .success-checkmark .check-icon {
            width: 80px;
            height: 80px;
            position: relative;
            border-radius: 50%;
            box-sizing: content-box;
            border: 4px solid #10b981;
        }
        
        .success-checkmark .check-icon::before {
            top: 3px;
            left: -2px;
            width: 30px;
            transform-origin: 100% 50%;
            border-radius: 100px 0 0 100px;
        }
        
        .success-checkmark .check-icon::after {
            top: 0;
            left: 30px;
            width: 60px;
            transform-origin: 0 50%;
            border-radius: 0 100px 100px 0;
            animation: rotate-circle 4.25s ease-in;
        }
        
        .success-checkmark .icon-line {
            height: 5px;
            background-color: #10b981;
            display: block;
            border-radius: 2px;
            position: absolute;
            z-index: 10;
        }
        
        .success-checkmark .icon-line.line-tip {
            top: 46px;
            left: 14px;
            width: 25px;
            transform: rotate(45deg);
            animation: icon-line-tip 0.75s;
        }
        
        .success-checkmark .icon-line.line-long {
            top: 38px;
            right: 8px;
            width: 47px;
            transform: rotate(-45deg);
            animation: icon-line-long 0.75s;
        }
        
        @keyframes icon-line-tip {
            0% { width: 0; left: 1px; top: 19px; }
            54% { width: 0; left: 1px; top: 19px; }
            70% { width: 50px; left: -8px; top: 37px; }
            84% { width: 17px; left: 21px; top: 48px; }
            100% { width: 25px; left: 14px; top: 45px; }
        }
        
        @keyframes icon-line-long {
            0% { width: 0; right: 46px; top: 54px; }
            65% { width: 0; right: 46px; top: 54px; }
            84% { width: 55px; right: 0px; top: 35px; }
            100% { width: 47px; right: 8px; top: 38px; }
        }
        
        /* Error Animation */
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            border: 4px solid #ef4444;
            border-radius: 50%;
            position: relative;
        }
        
        .error-icon::before,
        .error-icon::after {
            content: '';
            position: absolute;
            width: 4px;
            height: 40px;
            background-color: #ef4444;
            border-radius: 2px;
            top: 50%;
            left: 50%;
        }
        
        .error-icon::before {
            transform: translate(-50%, -50%) rotate(45deg);
            animation: errorLine 0.5s ease;
        }
        
        .error-icon::after {
            transform: translate(-50%, -50%) rotate(-45deg);
            animation: errorLine 0.5s ease 0.1s;
        }
        
        @keyframes errorLine {
            0% { height: 0; }
            100% { height: 40px; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-sync-alt mr-2"></i>Auto Update Rent Balance
            </h1>
            <p class="text-gray-600">
                Automatically update rent balances for tenants without transactions and move unpaid rent to arrears.
            </p>
            <div class="flex gap-4 mt-4">
                <a href="preview_update_rentbal.php" class="inline-block px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-eye mr-2"></i>Preview Updates
                </a>
                <a href="admin/admin.php" class="inline-block px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Admin Panel
                </a>
            </div>
        </div>


        <!-- Manual Trigger Card -->
        <div class="card bg-white p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-hand-pointer mr-2 text-blue-600"></i>Manual Trigger
            </h2>
            <p class="text-gray-600 mb-4">
                Click the button below to manually run the rent balance update for all branches. 
                This will check all tenants without transactions in the previous month and update their balances accordingly.
            </p>
            <?php 
            $currentMonth = date('F Y');
            $nextMonth = date('F Y', strtotime('+1 month'));
            if (isUpdateAlreadyRun($conn)): 
            ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Update Already Run for <?php echo $currentMonth; ?></strong><br>
                            The rent balance update has already been completed this month. 
                            You can run updates again starting <?php echo $nextMonth; ?>.
                        </p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">
                            <strong>Update Available for <?php echo $currentMonth; ?></strong><br>
                            You can run the rent balance update now. This will process all tenants without transactions in the previous month.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="flex gap-3 flex-wrap">
                <button onclick="showConfirmModal()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i>Run Update Now
                </button>
                <button onclick="loadNoTransactionsData()" 
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-calendar-times mr-2"></i>View No Transactions Data
                </button>
                <button onclick="testAjax()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-vial mr-2"></i>Test Connection
                </button>
            </div>
            <div id="testResult" class="mt-4 hidden">
                <div class="bg-gray-100 p-4 rounded-lg">
                    <p class="font-bold text-sm text-gray-700 mb-2">Test Result:</p>
                    <pre id="testOutput" class="text-xs text-gray-800 overflow-auto max-h-40"></pre>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <?php foreach ($statistics as $branchName => $stats): ?>
            <div class="card bg-white p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-store mr-2 text-purple-600"></i><?php echo $branchName; ?>
                </h3>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Tenants:</span>
                        <span class="font-bold text-gray-800"><?php echo number_format($stats['total']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">With Rent Balance:</span>
                        <span class="font-bold text-orange-600"><?php echo number_format($stats['with_balance']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">With Arrears:</span>
                        <span class="font-bold text-red-600"><?php echo number_format($stats['with_arrears']); ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Rent Bal:</span>
                        <span class="font-bold text-orange-600">₱<?php echo number_format($stats['total_rentbal'], 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Arrears:</span>
                        <span class="font-bold text-red-600">₱<?php echo number_format($stats['total_arrears'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- No Transactions Data Section -->
        <div id="noTransactionsSection" class="card bg-white p-6 mb-6 hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center justify-between">
                <span>
                    <i class="fas fa-calendar-times mr-2 text-red-600"></i>Previous Month - No Transactions Analysis
                </span>
                <button onclick="hideNoTransactionsData()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </h2>
            <div id="noTransactionsContent">
                <div class="text-center py-8">
                    <div class="spinner mb-4"></div>
                    <p class="text-gray-600">Loading previous month data...</p>
                </div>
            </div>
        </div>

        <!-- How It Works -->
        <div class="card bg-blue-50 p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-info-circle mr-2 text-blue-600"></i>How It Works
            </h2>
            <div class="space-y-3 text-gray-700">
                <div class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                    <p><strong>Step 1:</strong> System analyzes previous month data to identify tenants with no transactions.</p>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                    <p><strong>Step 2:</strong> For tenants with no transactions, current unpaid rent balance is moved to arrears (Running Balance).</p>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                    <p><strong>Step 3:</strong> New rent balance is calculated based on daily rent × days in previous month.</p>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                    <p><strong>Step 4:</strong> Tenants already updated show "Updated Already" status to prevent duplicate processing.</p>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                    <p><strong>Step 5:</strong> This process repeats monthly, accumulating unpaid rent as arrears.</p>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card bg-white p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-history mr-2 text-gray-600"></i>Recent Activity Log
            </h2>
            <div class="log-container p-4 rounded">
                <?php if ($log_content): ?>
                    <pre class="text-xs"><?php echo htmlspecialchars($log_content); ?></pre>
                <?php else: ?>
                    <p class="text-gray-400">No activity log available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-exclamation-circle text-yellow-500 text-2xl mr-3"></i>
                    Confirm Update
                </h3>
            </div>
            <div class="modal-body">
                <p class="text-gray-700 mb-4">
                    Are you sure you want to run the rent balance update?
                </p>
                <p class="text-sm text-gray-600">
                    This will update all tenants without transactions in the previous month. 
                    The process may take a few moments to complete.
                </p>
                <p class="text-xs text-blue-600 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Note: Updates can only be run once per month. If already run this month, you can run updates again next month.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="closeConfirmModal()" 
                        class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button onclick="runUpdate()" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                    <i class="fas fa-check mr-2"></i>Yes, Proceed
                </button>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div id="progressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-sync-alt fa-spin text-blue-600 text-2xl mr-3"></i>
                    Update in Progress
                </h3>
            </div>
            <div class="modal-body text-center">
                <div class="spinner mb-4"></div>
                <p class="text-gray-700 font-semibold mb-2">Processing Update...</p>
                <p class="text-sm text-gray-600 mb-4">
                    Please wait while we update the rent balances. This may take a moment.
                </p>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: 100%;"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>Do not close this window
                </p>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header bg-green-50">
                <h3 class="text-xl font-bold text-green-800 flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                    Update Successful!
                </h3>
            </div>
            <div class="modal-body text-center">
                <div class="success-checkmark mb-4">
                    <div class="check-icon">
                        <span class="icon-line line-tip"></span>
                        <span class="icon-line line-long"></span>
                    </div>
                </div>
                <h4 class="text-2xl font-bold text-gray-800 mb-2">All Done!</h4>
                <p class="text-gray-700 mb-4">
                    The rent balance update has been completed successfully.
                </p>
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600">Tenants Updated:</span>
                        <span id="tenantsUpdatedCount" class="text-2xl font-bold text-blue-600">0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Completed At:</span>
                        <span id="updateTimestamp" class="text-sm font-semibold text-gray-800">-</span>
                    </div>
                </div>
                <p class="text-sm text-gray-600">
                    <i class="fas fa-file-alt mr-1"></i>Check the activity log below for detailed information.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="closeSuccessModal()" 
                        class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                    <i class="fas fa-check mr-2"></i>OK, Got it!
                </button>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header bg-red-50">
                <h3 class="text-xl font-bold text-red-800 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-3"></i>
                    Update Failed
                </h3>
            </div>
            <div class="modal-body text-center">
                <div class="error-icon mb-4"></div>
                <h4 class="text-2xl font-bold text-gray-800 mb-2">Oops! Something went wrong</h4>
                <p class="text-gray-700 mb-4">
                    We encountered an error while processing the update.
                </p>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 text-left">
                    <p class="text-sm font-semibold text-red-800 mb-1">Error Details:</p>
                    <p id="errorMessage" class="text-sm text-red-700">Unknown error occurred</p>
                </div>
                <p class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>Please check the activity log or contact support.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="closeErrorModal()" 
                        class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Test AJAX Connection
        function testAjax() {
            const resultDiv = document.getElementById('testResult');
            const outputPre = document.getElementById('testOutput');
            
            resultDiv.classList.remove('hidden');
            outputPre.textContent = 'Testing connection...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=run_update'
            })
            .then(response => {
                outputPre.textContent = 'Response Status: ' + response.status + '\n';
                outputPre.textContent += 'Response OK: ' + response.ok + '\n\n';
                return response.text();
            })
            .then(text => {
                outputPre.textContent += 'Raw Response:\n' + text + '\n\n';
                try {
                    const json = JSON.parse(text);
                    outputPre.textContent += 'Parsed JSON:\n' + JSON.stringify(json, null, 2);
                } catch (e) {
                    outputPre.textContent += 'JSON Parse Error: ' + e.message;
                }
            })
            .catch(error => {
                outputPre.textContent += '\nFetch Error: ' + error.message;
            });
        }
        
        // Modal Functions
        function showConfirmModal() {
            document.getElementById('confirmModal').classList.add('show');
        }
        
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }
        
        function showProgressModal() {
            document.getElementById('progressModal').classList.add('show');
        }
        
        function closeProgressModal() {
            document.getElementById('progressModal').classList.remove('show');
        }
        
        function showSuccessModal(data) {
            document.getElementById('tenantsUpdatedCount').textContent = data.tenants_updated;
            document.getElementById('updateTimestamp').textContent = data.timestamp;
            document.getElementById('successModal').classList.add('show');
        }
        
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('show');
            // Reload page to refresh statistics and log
            setTimeout(() => {
                location.reload();
            }, 500);
        }
        
        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').classList.add('show');
        }
        
        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('show');
        }
        
        // Run Update Function
        function runUpdate() {
            // Close confirmation modal
            closeConfirmModal();
            
            // Show progress modal
            showProgressModal();
            
            // Make AJAX request
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=run_update'
            })
            .then(response => response.json())
            .then(data => {
                // Close progress modal
                closeProgressModal();
                
                if (data.success) {
                    // Show success modal
                    showSuccessModal(data);
                } else {
                    // Show error modal
                    showErrorModal(data.message);
                }
            })
            .catch(error => {
                // Close progress modal
                closeProgressModal();
                
                // Show error modal
                showErrorModal('Network error: ' + error.message);
            });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }
        
        // Auto-scroll log to bottom
        const logContainer = document.querySelector('.log-container');
        if (logContainer) {
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // No Transactions Data Functions
        function loadNoTransactionsData() {
            const section = document.getElementById('noTransactionsSection');
            const content = document.getElementById('noTransactionsContent');
            
            // Show section with loading state
            section.classList.remove('hidden');
            content.innerHTML = `
                <div class="text-center py-8">
                    <div class="spinner mb-4"></div>
                    <p class="text-gray-600">Loading previous month data...</p>
                </div>
            `;
            
            // Fetch data
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_transaction_free_days'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNoTransactionsData(data.data);
                } else {
                    content.innerHTML = `
                        <div class="bg-red-50 border-l-4 border-red-500 p-4">
                            <p class="text-red-800 font-semibold">Error loading data:</p>
                            <p class="text-red-700">${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 p-4">
                        <p class="text-red-800 font-semibold">Network Error:</p>
                        <p class="text-red-700">${error.message}</p>
                    </div>
                `;
            });
        }
        
        function hideNoTransactionsData() {
            document.getElementById('noTransactionsSection').classList.add('hidden');
        }
        
        function displayNoTransactionsData(data) {
            const content = document.getElementById('noTransactionsContent');
            let html = '';
            
            // Summary section
            html += '<div class="mb-6 p-4 bg-blue-50 rounded-lg">';
            html += '<h3 class="text-lg font-bold text-blue-800 mb-3">Previous Month Summary</h3>';
            html += '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
            
            for (const branchName in data) {
                const branch = data[branchName];
                html += `
                    <div class="bg-white p-4 rounded-lg border">
                        <h4 class="font-bold text-gray-800 mb-2">${branch.branch}</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Total Tenants:</span>
                                <span class="font-semibold">${branch.summary.total_tenants}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>No Transactions:</span>
                                <span class="font-semibold text-red-600">${branch.summary.tenants_without_transactions}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Updated Already:</span>
                                <span class="font-semibold text-blue-600">${branch.summary.updated_already || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Needs Update:</span>
                                <span class="font-semibold text-orange-600">${branch.summary.needs_update || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Up to Date:</span>
                                <span class="font-semibold text-green-600">${branch.summary.up_to_date || 0}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">
                                ${branch.summary.previous_month}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            html += '</div></div>';
            
            // Detailed tenant breakdown
            html += '<div class="space-y-4">';
            html += '<h3 class="text-lg font-bold text-gray-800 mb-4">Detailed Breakdown by Branch</h3>';
            
            for (const branchName in data) {
                const branch = data[branchName];
                html += `
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b">
                            <h4 class="font-bold text-gray-800">${branch.branch}</h4>
                            <p class="text-sm text-gray-600">${branch.summary.month_range}</p>
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Tenant</th>
                                        <th class="px-3 py-2 text-left">Space</th>
                                        <th class="px-3 py-2 text-center">No Transactions</th>
                                        <th class="px-3 py-2 text-center">Est. Unpaid</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                branch.tenants.forEach(tenant => {
                    let statusClass = '';
                    let statusText = tenant.update_status;
                    
                    if (tenant.is_updated_already) {
                        statusClass = 'bg-blue-100 text-blue-800';
                    } else if (tenant.days_without_transactions > 0) {
                        statusClass = 'bg-red-100 text-red-800';
                    } else {
                        statusClass = 'bg-green-100 text-green-800';
                    }
                    
                    html += `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-3 py-2">${tenant.tenantname}</td>
                            <td class="px-3 py-2 font-mono">${tenant.spacecode}</td>
                            <td class="px-3 py-2 text-center">${tenant.days_without_transactions}</td>
                            <td class="px-3 py-2 text-center">₱${tenant.estimated_unpaid_rent.toFixed(2)}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-1 rounded-full text-xs ${statusClass}">
                                    ${statusText}
                                </span>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div></div>';
            }
            
            html += '</div>';
            
            content.innerHTML = html;
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>

