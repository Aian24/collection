<?php
include '../config.php';
include 'tenant_history_logger.php';

// Start session to get user info
session_start();

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Initialize a message variable
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get posted data
    $id = $_POST["editTenantId"] ?? "";
    $tenantName = $_POST["editTenantName"] ?? "";
    $tenantCode = $_POST["editTenantCode"] ?? "";
    $spaceCode = $_POST["editSpaceCode"] ?? "";
    $dailyRent = str_replace(',', '', $_POST["editDailyRent"]) ?? ""; // Sanitize daily rent input (remove commas)
    $rentBal = str_replace(',', '', $_POST["editRentBal"]) ?? "";
    $runningBal = str_replace(',', '', $_POST["editRunningBal"]) ?? "";

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
        $returnUrl = $_POST['returnUrl'] ?? 'tenants.php';
        $_SESSION['errorMessage'] = "Invalid branch selection!";
        header("Location: " . $returnUrl);
        exit();
    }
    
    // Set table name (already validated)
    $tableName = $conn->real_escape_string($branch);
    
    $returnUrl = $_POST['returnUrl'] ?? 'tenants.php';
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
                    SET tenantname=?, tenantcode=?, spacecode=?, daily=?, rentbal=?, runningbal=?
                    WHERE id=?";
    $stmt = $conn->prepare($updateQuery);
    
    // Bind the parameters to the query
    if ($stmt) {
        $stmt->bind_param("ssssddi", $tenantName, $tenantCode, $spaceCode, $dailyRent, $rentBal, $runningBal, $id);

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
                'runningbal' => $runningBal
            ];
            
            // Get user information
            $userEmail = $_SESSION['username'] ?? 'unknown';
            $userName = ($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? 'Unknown User');
            
            // Get changes made
            $changesMade = getTenantChanges($oldTenantData, $newTenantData);
            
            // Log tenant update
            logTenantHistory($conn, 'updated', $newTenantData, $userEmail, $userName, $branch, $changesMade);
            
            // Set success message in session
            $_SESSION['successMessage'] = "Tenant updated successfully!";
            
            header("Location: " . $redirectUrl);
            exit();
        } else {
            // Set error message in session
            $_SESSION['errorMessage'] = "Error updating tenant: " . mysqli_error($conn);
            header("Location: " . $redirectUrl);
            exit();
        }
    } else {
        // Set error message in session
        $_SESSION['errorMessage'] = "Error preparing statement: " . mysqli_error($conn);
        header("Location: " . $redirectUrl);
        exit();
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
    exit();
}

// If there's a success message, show it with a redirect
if ($successMessage) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Success</title>
        <script>
            // Redirect after 3 seconds
            setTimeout(function() {
                var returnUrl = '<?php echo isset($_POST["returnUrl"]) ? htmlspecialchars($_POST["returnUrl"]) : "tenants.php"; ?>';
                window.location.href = returnUrl;
            }, 3000);
        </script>
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background-color: #f5f5f5;
                font-family: Arial, sans-serif;
                overflow: hidden;
            }
            .message {
                padding: 30px 20px;
                background: linear-gradient(90deg, #4CAF50, #3e8e41);
                color: white;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
                transform: scale(0);
                animation: scaleIn 0.5s forwards, fadeOut 0.5s forwards 2.5s;
            }
            @keyframes scaleIn {
                to {
                    transform: scale(1);
                }
            }
            @keyframes fadeOut {
                to {
                    opacity: 0;
                    transform: translateY(-20px);
                }
            }
            .loader {
                display: inline-block;
                width: 50px;
                height: 50px;
                border: 5px solid rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                border-top: 5px solid white;
                animation: spin 1s linear infinite;
                margin-top: 15px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="message">
            <h2><?php echo $successMessage; ?></h2>
            <div class="loader"></div>
            <p>Please wait...</p>
        </div>
    </body>
    </html>
    <?php
    exit(); // Ensure no further output
}
?>