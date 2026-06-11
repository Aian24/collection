<?php
/**
 * Auto Update Rent Balance System
 * This script automatically updates rent balances for tenants without transactions
 * and moves unpaid rent to arrears when a new month starts
 */

// Only include config if not already included
if (!isset($conn)) {
    include 'config.php';
}

// Set timezone if not already set
@date_default_timezone_set('Asia/Manila');

// Log file for tracking updates
$log_file = 'auto_update_log.txt';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s T'); // Include timezone in timestamp
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Get the last transaction date for a tenant
 */
function getLastTransactionDate($conn, $spacecode, $branch) {
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
            return null;
    }
    
    $stmt = $conn->prepare("SELECT MAX(collected_date) as last_date FROM $table WHERE spacecode = ? AND branch = ?");
    $stmt->bind_param("ss", $spacecode, $branch);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['last_date'];
}

/**
 * Calculate days in previous month
 */
function getDaysInPreviousMonth() {
    $firstDayCurrentMonth = date('Y-m-01');
    $lastDayPreviousMonth = date('Y-m-d', strtotime($firstDayCurrentMonth . ' -1 day'));
    $firstDayPreviousMonth = date('Y-m-01', strtotime($lastDayPreviousMonth));
    
    return date('t', strtotime($firstDayPreviousMonth)); // t gives number of days in month
}

/**
 * Get first and last day of previous month
 */
function getPreviousMonthRange() {
    $firstDayCurrentMonth = date('Y-m-01');
    $lastDayPreviousMonth = date('Y-m-d', strtotime($firstDayCurrentMonth . ' -1 day'));
    $firstDayPreviousMonth = date('Y-m-01', strtotime($lastDayPreviousMonth));
    
    return [
        'first' => $firstDayPreviousMonth,
        'last' => $lastDayPreviousMonth
    ];
}

/**
 * Check if tenant had transaction in previous month
 */
function hadTransactionInPreviousMonth($conn, $spacecode, $branch) {
    $prevMonth = getPreviousMonthRange();
    
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
            return false;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table 
                            WHERE spacecode = ? 
                            AND branch = ? 
                            AND DATE(collected_date) BETWEEN ? AND ?");
    $stmt->bind_param("ssss", $spacecode, $branch, $prevMonth['first'], $prevMonth['last']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
}

/**
 * Update rent balance for tenants without transactions
 */
function updateRentBalances($conn, $branch) {
    logMessage("=== Starting rent balance update for $branch ===");
    
    // Determine tenant table
    $tenantTable = '';
    switch ($branch) {
        case 'Sanko Market':
            $tenantTable = 'sanko';
            break;
        case 'Nova Market':
            $tenantTable = 'nova';
            break;
        case 'APM':
            $tenantTable = 'apm';
            break;
        default:
            return 0;
    }
    
    // Get all tenants
    $query = "SELECT id, tenantcode, tenantname, spacecode, daily, rentbal, runningbal FROM $tenantTable 
              WHERE spacecode != 'Ambulant' AND spacecode != ''";
    $result = $conn->query($query);
    
    if (!$result) {
        logMessage("Error fetching tenants from $tenantTable: " . $conn->error);
        return 0;
    }
    
    $updateCount = 0;
    $daysInPrevMonth = getDaysInPreviousMonth();
    
    while ($tenant = $result->fetch_assoc()) {
        $spacecode = $tenant['spacecode'];
        $daily = floatval($tenant['daily']);
        $currentRentBal = floatval($tenant['rentbal']);
        $currentRunningBal = floatval($tenant['runningbal']);
        
        // Check if tenant had transaction in previous month
        $hadTransaction = hadTransactionInPreviousMonth($conn, $spacecode, $branch);
        
        if (!$hadTransaction) {
            // Calculate rent for previous month
            // APM uses fixed 30-day calculation, others use actual days in month
            if ($branch === 'APM') {
                $previousMonthRent = $daily * 30; // Fixed 30 days for APM
            } else {
                $previousMonthRent = $daily * $daysInPrevMonth;
            }
            
            // Move current unpaid rentbal to arrears (runningbal)
            // This happens when a new month starts and previous month's rent wasn't paid
            $newRunningBal = $currentRunningBal + $currentRentBal;
            
            // Set new rentbal to previous month's rent
            $newRentBal = $previousMonthRent;
            
            // Update database
            $updateStmt = $conn->prepare("UPDATE $tenantTable SET rentbal = ?, runningbal = ? WHERE id = ?");
            $updateStmt->bind_param("ddi", $newRentBal, $newRunningBal, $tenant['id']);
            
            if ($updateStmt->execute()) {
                $updateCount++;
                $calculationMethod = ($branch === 'APM') ? "(Fixed 30 days)" : "($daysInPrevMonth days)";
                logMessage("Updated {$tenant['tenantname']} ({$spacecode}) $calculationMethod: RentBal: {$currentRentBal} -> {$newRentBal}, RunningBal: {$currentRunningBal} -> {$newRunningBal}");
            } else {
                logMessage("Error updating {$tenant['tenantname']} ({$spacecode}): " . $updateStmt->error);
            }
            
            $updateStmt->close();
        } else {
            logMessage("Skipped {$tenant['tenantname']} ({$spacecode}): Had transaction in previous month");
        }
    }
    
    $result->free();
    logMessage("=== Completed $branch: Updated $updateCount tenants ===\n");
    
    return $updateCount;
}

/**
 * Main execution
 */
function runAutoUpdate() {
    global $conn;
    
    logMessage("========================================");
    logMessage("Starting Auto Update Rent Balance");
    logMessage("Time: " . date('Y-m-d H:i:s T'));
    logMessage("Timezone: Asia/Manila");
    logMessage("========================================");
    
    // Check if update has already been run for this month
    if (isUpdateAlreadyRunAuto($conn)) {
        $currentMonth = date('F Y');
        logMessage("ERROR: Update has already been run for $currentMonth. Skipping to prevent duplicates.");
        return 0;
    }
    
    $branches = ['Sanko Market', 'Nova Market', 'APM'];
    $totalUpdated = 0;
    
    foreach ($branches as $branch) {
        $count = updateRentBalances($conn, $branch);
        $totalUpdated += $count;
    }
    
    logMessage("========================================");
    logMessage("Total tenants updated: $totalUpdated");
    logMessage("Update completed at: " . date('Y-m-d H:i:s T'));
    logMessage("========================================\n\n");
    
    return $totalUpdated;
}

/**
 * Check if update has already been run for this month
 * Note: This function is also defined in manual_update_rentbal.php
 * This version is used when auto_update_rentbal.php is called directly
 */
function isUpdateAlreadyRunAuto($conn) {
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

// Execute the update if called directly
if (php_sapi_name() === 'cli' || (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === 'auto_update_rentbal.php')) {
    $updated = runAutoUpdate();
    echo "Auto update completed. $updated tenants updated.\n";
    echo "Check auto_update_log.txt for details.\n";
    $conn->close();
}
?>

