<?php
ob_start(); // Start output buffering
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
    $tenantId = $_POST["deleteTenantId"] ?? "";
    $branch = $_POST["deleteBranch"] ?? "";

    if ($tenantId && $branch) {
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
        
        // Fetch tenant data before deletion for logging
        $tenantQuery = "SELECT * FROM $tableName WHERE id=?";
        $tenantStmt = $conn->prepare($tenantQuery);
        $tenantStmt->bind_param('i', $tenantId);
        $tenantStmt->execute();
        $tenantResult = $tenantStmt->get_result();
        $tenantData = $tenantResult->fetch_assoc();
        $tenantStmt->close();

        // Prepare the delete query
        $deleteQuery = "DELETE FROM $tableName WHERE id=?";
        $stmt = $conn->prepare($deleteQuery);
        if ($stmt) {
            // Bind the parameter and execute the query
            $stmt->bind_param('i', $tenantId);
            if ($stmt->execute()) {
                // Get user information
                $userEmail = $_SESSION['username'] ?? 'unknown';
                $userName = ($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? 'Unknown User');
                
                // Log tenant deletion
                logTenantHistory($conn, 'deleted', $tenantData, $userEmail, $userName, $branch);
                
                // Set success message in session
                $_SESSION['successMessage'] = "Tenant deleted successfully!";
                
                header("Location: " . $redirectUrl);
                exit();
            } else {
                // Set error message in session
                $_SESSION['errorMessage'] = "Error deleting tenant: " . $stmt->error;
                header("Location: " . $redirectUrl);
                exit();
            }
        } else {
            // Set error message in session
            $_SESSION['errorMessage'] = "Error preparing statement: " . mysqli_error($conn);
            header("Location: " . $redirectUrl);
            exit();
        }

        // Close the statement
        $stmt->close();
    } else {
        $returnUrl = $_POST['returnUrl'] ?? 'tenants.php';
        // Set error message in session
        $_SESSION['errorMessage'] = "Invalid data - missing tenant ID or branch";
        header("Location: " . $returnUrl);
        exit();
    }

    // Close the database connection
    $conn->close();
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
    exit(); // Ensure no further output is sent after the redirect
}
?>
