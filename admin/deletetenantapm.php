<?php
ob_start(); // Start output buffering
include '../config.php';
include 'tenant_history_logger.php';

// Start session to get user info
session_start();

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get posted data
    $tenantId = $_POST["deleteTenantId"] ?? "";

    if ($tenantId) {
        // Default to the "apm" table
        $tableName = 'apm';
        $branch = 'apm';

        // First, get the current tenant data for history tracking
        $selectQuery = "SELECT * FROM $tableName WHERE id = ?";
        $selectStmt = $conn->prepare($selectQuery);
        $selectStmt->bind_param("i", $tenantId);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $tenantData = $result->fetch_assoc();
        $selectStmt->close();

        // Prepare the delete query
        $deleteQuery = "DELETE FROM $tableName WHERE id=?";
        $stmt = $conn->prepare($deleteQuery);
        if ($stmt) {
            // Bind the parameter and execute the query
            $stmt->bind_param('i', $tenantId);
            if ($stmt->execute()) {
                // Log the tenant deletion history
                if ($tenantData) {
                    // Get user info from session
                    $userEmail = $_SESSION['email'] ?? 'unknown';
                    $userName = $_SESSION['username'] ?? 'unknown';
                    
                    logTenantHistory($conn, 'deleted', $tenantData, $userEmail, $userName, $branch);
                }

                // Set success message in session
                $_SESSION['successMessage'] = "Tenant deleted successfully!";
                header("Location: tenantsapm.php");
                exit();
            } else {
                // Set error message in session
                $_SESSION['errorMessage'] = "Error deleting tenant: " . $stmt->error;
                header("Location: tenantsapm.php");
                exit();
            }
        } else {
            // Set error message in session
            $_SESSION['errorMessage'] = "Error preparing statement: " . mysqli_error($conn);
            header("Location: tenantsapm.php");
            exit();
        }

        // Close the statement
        $stmt->close();
    } else {
        // Set error message in session
        $_SESSION['errorMessage'] = "Invalid data - missing tenant ID";
        header("Location: tenantsapm.php");
        exit();
    }

    // Close the database connection
    $conn->close();
}
?>