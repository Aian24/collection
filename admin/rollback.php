<?php
ob_start();
include '../config.php';
include '../nav.php';
include 'tenant_history_logger.php'; // Include tenant history logger
session_start();

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

// Get all branch tables
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);
$allowedTables = [];

// Filter tables to get only branch tables (excluding collected tables)
while ($table = $tables_result->fetch_array()) {
    $table_name = $table[0];
    // Only include tables that are not collection tables
    if (!str_starts_with($table_name, 'collected') && 
        (in_array($table_name, ['apm', 'nova', 'sanko']) || 
        $conn->query("SHOW TABLES LIKE 'collected$table_name'")->num_rows > 0)) {
        $allowedTables[] = $table_name;
    }
}

$branch = '';
// Set default rollback date to yesterday in Manila timezone
$rollbackDate = date('Y-m-d', strtotime('-1 day'));
$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rollback'])) {
    $branch = $_POST['branch'];
    $rollbackDate = $_POST['rollback_date'];
    
    if (empty($branch)) {
        $errorMessage = "Please select a branch.";
    } elseif (empty($rollbackDate)) {
        $errorMessage = "Please select a rollback date.";
    } else {
        // Perform rollback
        $successMessage = performRollback($conn, $branch, $rollbackDate);
    }
}

// Function to perform rollback
function performRollback($conn, $branch, $rollbackDate) {
    // Validate branch
    $tables_query = "SHOW TABLES";
    $tables_result = $conn->query($tables_query);
    $allowedTables = [];
    
    while ($table = $tables_result->fetch_array()) {
        $table_name = $table[0];
        if (!str_starts_with($table_name, 'collected') && 
            (in_array($table_name, ['apm', 'nova', 'sanko']) || 
            $conn->query("SHOW TABLES LIKE 'collected$table_name'")->num_rows > 0)) {
            $allowedTables[] = $table_name;
        }
    }
    
    if (!in_array($branch, $allowedTables)) {
        return "Invalid branch selected.";
    }
    
    // Get tenant history records before the rollback date for this branch
    $historyQuery = "SELECT * FROM tenant_history 
                     WHERE branch = ? AND DATE(timestamp) <= ? 
                     ORDER BY timestamp ASC";
    $stmt = $conn->prepare($historyQuery);
    $stmt->bind_param("ss", $branch, $rollbackDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        return "No history records found for the selected date and branch.";
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First, delete all current tenants in the branch
        $deleteQuery = "DELETE FROM $branch";
        $conn->query($deleteQuery);
        
        // Track processed tenants to avoid duplicates
        $processedTenants = [];
        
        // Reconstruct the database state by applying history records
        while ($row = $result->fetch_assoc()) {
            $tenantId = $row['tenant_id'];
            $action = $row['action'];
            
            // Skip if we've already processed this tenant in this batch
            if ($tenantId && in_array($tenantId, $processedTenants)) {
                continue;
            }
            
            if ($tenantId) {
                $processedTenants[] = $tenantId;
            }
            
            if ($action == 'created' || $action == 'updated') {
                // Insert tenant record
                $insertQuery = "INSERT INTO $branch 
                               (id, tenantname, tenantcode, spacecode, daily, rentbal, runningbal, started_date) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE 
                               tenantname = VALUES(tenantname),
                               tenantcode = VALUES(tenantcode),
                               spacecode = VALUES(spacecode),
                               daily = VALUES(daily),
                               rentbal = VALUES(rentbal),
                               runningbal = VALUES(runningbal),
                               started_date = VALUES(started_date)";
                
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("isssssss",
                    $row['tenant_id'],
                    $row['tenant_name'],
                    $row['tenant_code'],
                    $row['space_code'],
                    $row['daily_rent'],
                    $row['rent_balance'],
                    $row['running_balance'],
                    $row['started_date'] ?? date('Y-m-d') // Use stored started_date or current date as default
                );
                
                $insertStmt->execute();
                $insertStmt->close();
            }
            // Note: We don't need to handle 'deleted' action here since we're rebuilding the state
        }
        
        // Commit transaction
        $conn->commit();
        $stmt->close();
        return "Database successfully rolled back to state as of $rollbackDate for branch $branch.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $stmt->close();
        return "Error during rollback: " . $e->getMessage();
    }
}

// Get distinct dates from tenant history for dropdown
$datesQuery = "SELECT DISTINCT DATE(timestamp) as rollback_date 
               FROM tenant_history 
               WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               ORDER BY timestamp DESC";
$datesResult = $conn->query($datesQuery);
$availableDates = [];
if ($datesResult) {
    while ($dateRow = $datesResult->fetch_assoc()) {
        $availableDates[] = $dateRow['rollback_date'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>LCLopez Resources - Rollback Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f3f4f6;
            font-family: sans-serif;
        }
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        .card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <div class="card">
            <div class="card-header">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-undo me-2"></i>Database Rollback
                </h1>
                <p class="text-gray-600 mt-2">
                    Restore your tenant database to a previous state using the tenant history records.
                </p>
            </div>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="post" class="mb-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="branch" class="form-label">Select Branch:</label>
                        <select name="branch" id="branch" class="form-select" required>
                            <option value="" <?= empty($branch) ? 'selected' : '' ?>>Please Select Branch</option>
                            <?php
                            foreach ($allowedTables as $table_name) {
                                $selected = ($branch == $table_name) ? 'selected' : '';
                                echo "<option value=\"$table_name\" $selected>" . strtoupper($table_name) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="rollback_date" class="form-label">Rollback Date:</label>
                        <select name="rollback_date" id="rollback_date" class="form-select" required>
                            <option value="">Select Date</option>
                            <?php
                            foreach ($availableDates as $date) {
                                // Select yesterday's date by default
                                $selected = ($date == date('Y-m-d', strtotime('-1 day'))) ? 'selected' : '';
                                if (empty($rollbackDate) && $date == date('Y-m-d', strtotime('-1 day'))) {
                                    $selected = 'selected';
                                } elseif ($rollbackDate == $date) {
                                    $selected = 'selected';
                                }
                                echo "<option value=\"$date\" $selected>$date</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">Available rollback dates from the past 30 days. Default date is set to yesterday (Manila timezone).</small>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Important Notice:</h6>
                    <hr>
                    <ul class="mb-0">
                        <li>This action will restore the database to the state as of the selected date</li>
                        <li>All changes made after this date will be permanently lost</li>
                        <li>This operation cannot be undone</li>
                        <li>Please ensure you have a backup before proceeding</li>
                    </ul>
                </div>
                
                <button type="submit" name="rollback" class="btn btn-danger" onclick="return confirm('Are you sure you want to rollback the database? This action cannot be undone.')">
                    <i class="fas fa-undo me-2"></i>Rollback Database
                </button>
                
                <a href="tenants.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Tenants
                </a>
            </form>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>How Rollback Works</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Select the branch you want to rollback</li>
                        <li>Choose a date from the available rollback dates (based on tenant history)</li>
                        <li>Click "Rollback Database" to restore the database to that date</li>
                        <li>The system will:
                            <ul>
                                <li>Delete all current tenant records in the selected branch</li>
                                <li>Recreate the database state using tenant history records up to the selected date</li>
                                <li>Preserve tenant IDs to maintain referential integrity</li>
                            </ul>
                        </li>
                    </ol>
                    <p class="mt-3"><strong>Note:</strong> This feature relies on the tenant history tracking system. Make sure tenant history logging is enabled for accurate rollbacks.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>