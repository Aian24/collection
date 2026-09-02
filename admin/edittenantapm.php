<?php
include '../config.php';
include 'tenant_history_logger.php';

// Start session to get user info
session_start();

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get posted data
    $id = $_POST["editTenantId"] ?? "";
    $tenantName = $_POST["editTenantName"] ?? "";
    $tenantCode = $_POST["editTenantCode"] ?? "";
    $spaceCode = $_POST["editSpaceCode"] ?? "";
    $dailyRent = str_replace(',', '', $_POST["editDailyRent"]) ?? ""; // Sanitize daily rent input (remove commas)
    $rentBal = str_replace(',', '', $_POST["editRentBal"] ?? "");
    $runningBal = str_replace(',', '', $_POST["editRunningBal"] ?? "");
    $elecBal = str_replace(',', '', $_POST["editElecBal"] ?? "0");
    $elecArrear = str_replace(',', '', $_POST["editElecArrear"] ?? "0");
    $waterBal = str_replace(',', '', $_POST["editWaterBal"] ?? "0");
    $waterArrear = str_replace(',', '', $_POST["editWaterArrear"] ?? "0");

    $branch = $_POST["editBranch"] ?? "";
    
    // Get all branch tables for validation
    $tables_query = "SHOW TABLES";
    $tables_result = $conn->query($tables_query);
    $allowedTables = [];
    
    // Filter tables to get only branch tables (excluding collected tables)
    while ($table = $tables_result->fetch_array()) {
        $table_name = $table[0];
        // Only include tables that are not collection tables
        if (!str_starts_with($table_name, 'collected') && 
            (in_array($table_name, ['apm', 'nova', 'sanko']) || 
            // For custom branch tables, check if their collection table exists
            $conn->query("SHOW TABLES LIKE 'collected$table_name'")->num_rows > 0)) {
            $allowedTables[] = $table_name;
        }
    }
    
    // Validate branch selection
    if (!in_array($branch, $allowedTables)) {
        $returnUrl = $_POST['returnUrl'] ?? 'tenantsapm.php';
        $_SESSION['errorMessage'] = "Invalid branch selection!";
        header("Location: " . $returnUrl);
        exit();
    }
    
    // Set table name (already validated)
    $tableName = $conn->real_escape_string($branch);
    
    $returnUrl = $_POST['returnUrl'] ?? 'tenantsapm.php';
    $redirectUrl = $returnUrl . "?branch=" . urlencode($branch);
    
    // Fetch old tenant data for comparison
    $oldDataQuery = "SELECT * FROM $tableName WHERE id = ?";
    $oldDataStmt = $conn->prepare($oldDataQuery);
    $oldDataStmt->bind_param('i', $id);
    $oldDataStmt->execute();
    $oldDataResult = $oldDataStmt->get_result();
    $oldTenantData = $oldDataResult->fetch_assoc();
    $oldDataStmt->close();

    // Check for duplicate tenant name, tenant code, or space code (excluding current tenant)
    $checkQuery = "SELECT * FROM $tableName WHERE (tenantname = ? OR tenantcode = ? OR spacecode = ?) AND id != ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("sssi", $tenantName, $tenantCode, $spaceCode, $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Duplicate found - determine which field is duplicate
        $duplicate = $checkResult->fetch_assoc();
        $duplicateFields = [];
        
        if (strtolower($duplicate['tenantname']) === strtolower($tenantName)) {
            $duplicateFields[] = "Tenant Name '" . htmlspecialchars($tenantName) . "'";
        }
        if (strtolower($duplicate['tenantcode']) === strtolower($tenantCode)) {
            $duplicateFields[] = "Tenant Code '" . htmlspecialchars($tenantCode) . "'";
        }
        if (strtolower($duplicate['spacecode']) === strtolower($spaceCode)) {
            $duplicateFields[] = "Space Code '" . htmlspecialchars($spaceCode) . "'";
        }
        
        $duplicateMessage = implode(", ", $duplicateFields);
        $_SESSION['errorMessage'] = "Cannot update tenant. " . $duplicateMessage . " already exists in " . strtoupper($branch) . " branch!";
        $checkStmt->close();
        $conn->close();
        header("Location: " . $redirectUrl);
        exit();
    }
    $checkStmt->close();

    // Prepare the update query
    $updateQuery = "UPDATE $tableName 
                    SET tenantname=?, tenantcode=?, spacecode=?, daily=?, rentbal=?, runningbal=?, elecbal=?, elecarrear=?, waterbal=?, waterarrear=?
                    WHERE id=?";
    $stmt = $conn->prepare($updateQuery);
    
    // Bind the parameters to the query
    if ($stmt) {
        $stmt->bind_param("ssssddddddi", $tenantName, $tenantCode, $spaceCode, $dailyRent, $rentBal, $runningBal, $elecBal, $elecArrear, $waterBal, $waterArrear, $id);

        // Execute the query and check for success
        if ($stmt->execute()) {
            // Prepare new tenant data for logging
            $newTenantData = [
                'id' => $id,
                'tenantname' => $tenantName,
                'tenantcode' => $tenantCode,
                'spacecode' => $spaceCode,
                'daily' => $dailyRent,
                'rentbal' => $rentBal,
                'runningbal' => $runningBal,
                'elecbal' => $elecBal,
                'elecarrear' => $elecArrear,
                'waterbal' => $waterBal,
                'waterarrear' => $waterArrear
            ];
            
            $changesMade = getTenantChanges($oldTenantData, $newTenantData);
            
            // Get user info from session
            $userEmail = $_SESSION['email'] ?? 'unknown';
            $userName = $_SESSION['username'] ?? 'unknown';
            
            logTenantHistory($conn, 'updated', $newTenantData, $userEmail, $userName, $branch, $changesMade);

            // Set success message in session
            $_SESSION['successMessage'] = "Tenant updated successfully!";
            header("Location: tenantsapm.php");
            exit();
        } else {
            // Set error message in session
            $_SESSION['errorMessage'] = "Error updating tenant: " . mysqli_error($conn);
            header("Location: tenantsapm.php");
            exit();
        }
    } else {
        // Set error message in session
        $_SESSION['errorMessage'] = "Error preparing statement: " . mysqli_error($conn);
        header("Location: tenantsapm.php");
        exit();
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}
?>