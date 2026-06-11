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

    $branch = $_POST["editBranch"] ?? "";
    
    // Define allowed tables
    $allowedTables = ['apm', 'nova', 'sanko'];
    
    // Validate branch selection
    if (!in_array($branch, $allowedTables)) {
        $_SESSION['errorMessage'] = "Invalid branch selection!";
        header("Location: tenantsacc.php?branch=" . urlencode($branch));
        exit();
    }
    
    // Set table name (already validated)
    $tableName = $conn->real_escape_string($branch);

    // First, get the current tenant data for history tracking
    $selectQuery = "SELECT * FROM $tableName WHERE id = ?";
    $selectStmt = $conn->prepare($selectQuery);
    $selectStmt->bind_param("i", $id);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $oldTenantData = $result->fetch_assoc();
    $selectStmt->close();

    // Check for duplicate tenant code and space code (excluding current tenant)
    // Allow duplicate tenant names but prevent duplicate codes
    $duplicateFields = [];
    
    // Check for duplicate tenant code
    $checkCodeQuery = "SELECT id, tenantcode FROM $tableName WHERE LOWER(tenantcode) = LOWER(?) AND id != ?";
    $checkCodeStmt = $conn->prepare($checkCodeQuery);
    $checkCodeStmt->bind_param("si", $tenantCode, $id);
    $checkCodeStmt->execute();
    $codeResult = $checkCodeStmt->get_result();
    if ($codeResult->num_rows > 0) {
        $duplicateFields[] = "Tenant Code '" . htmlspecialchars($tenantCode) . "'";
    }
    $checkCodeStmt->close();
    
    // Check for duplicate space code
    $checkSpaceQuery = "SELECT id, spacecode FROM $tableName WHERE LOWER(spacecode) = LOWER(?) AND id != ?";
    $checkSpaceStmt = $conn->prepare($checkSpaceQuery);
    $checkSpaceStmt->bind_param("si", $spaceCode, $id);
    $checkSpaceStmt->execute();
    $spaceResult = $checkSpaceStmt->get_result();
    if ($spaceResult->num_rows > 0) {
        $duplicateFields[] = "Space Code '" . htmlspecialchars($spaceCode) . "'";
    }
    $checkSpaceStmt->close();
    
    // If any duplicates found, show error (only for codes, not names)
    if (!empty($duplicateFields)) {
        $duplicateMessage = implode(", ", $duplicateFields);
        $_SESSION['errorMessage'] = "Cannot update tenant. " . $duplicateMessage . " already exists in " . strtoupper($branch) . " branch!";
        $conn->close();
        header("Location: tenantsacc.php?branch=" . urlencode($branch));
        exit();
    }

    // Prepare the update query
    $updateQuery = "UPDATE $tableName 
                    SET tenantname=?, tenantcode=?, spacecode=?, daily=?, rentbal=?, runningbal=?
                    WHERE id=?";
    $stmt = $conn->prepare($updateQuery);
    
    // Bind the parameters to the query
    if ($stmt) {
        $stmt->bind_param("sssdddi", $tenantName, $tenantCode, $spaceCode, $dailyRent, $rentBal, $runningBal, $id);

        // Execute the query and check for success
        if ($stmt->execute()) {
            // Log the tenant update history
            $newTenantData = [
                'id' => $id,
                'tenantname' => $tenantName,
                'tenantcode' => $tenantCode,
                'spacecode' => $spaceCode,
                'daily' => $dailyRent,
                'rentbal' => $rentBal,
                'runningbal' => $runningBal
            ];
            
            $changesMade = getTenantChanges($oldTenantData, $newTenantData);
            
            // Get user info from session
            $userEmail = $_SESSION['email'] ?? 'unknown';
            $userName = $_SESSION['username'] ?? 'unknown';
            
            logTenantHistory($conn, 'updated', $newTenantData, $userEmail, $userName, $branch, $changesMade);

            // Set success message in session
            $_SESSION['successMessage'] = "Tenant updated successfully!";
            header("Location: tenantsacc.php?branch=" . urlencode($branch));
            exit();
        } else {
            // Set error message in session
            $_SESSION['errorMessage'] = "Error updating tenant: " . mysqli_error($conn);
            header("Location: tenantsacc.php?branch=" . urlencode($branch));
            exit();
        }
    } else {
        // Set error message in session
        $_SESSION['errorMessage'] = "Error preparing statement: " . mysqli_error($conn);
        header("Location: tenantsacc.php?branch=" . urlencode($branch));
        exit();
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}
?>