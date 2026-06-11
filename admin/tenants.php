<?php
ob_start(); // Start output buffering
include '../config.php'; // Include your database configuration
include 'tenant_history_logger.php'; // Include tenant history logger
session_start();

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}
$lname = isset($_SESSION["lname"]) ? $_SESSION["lname"] : "Admin";

$successMessage = ''; // Variable to hold success message

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
        // For custom branch tables, check if their collection table exists
        $conn->query("SHOW TABLES LIKE 'collected$table_name'")->num_rows > 0)) {
        $allowedTables[] = $table_name;
    }
}

// Set the selected branch from POST, GET, or default to empty
// Prioritize POST (from select change or modal form), then GET (if redirected back), then empty
$branch = '';
if (isset($_POST['branch']) && !empty($_POST['branch'])) {
    $branch = $_POST['branch'];
} elseif (isset($_GET['branch']) && !empty($_GET['branch'])) {
     $branch = $_GET['branch'];
} elseif (isset($_POST['create']) && isset($_POST['branch'])) { // Check if create form was submitted
     $branch = $_POST['branch'];
}


// Handle Create operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    // Check if branch is selected before attempting insert
    if (!empty($_POST['branch'])) {
        $tenantname = $_POST['tenantname'];
        $tenantcode = $_POST['tenantcode'];
        $spacecode = $_POST['spacecode'];
        // Ensure numerical fields are treated correctly, prevent issues with currency symbols etc.
        $daily = filter_var($_POST['daily'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $rentbal = filter_var($_POST['rentbal'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $runningbal = filter_var($_POST['runningbal'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $started_date = $_POST['started_date'];
        $branch = $_POST['branch']; // Get the selected branch from the modal/form

        // Dynamic table name based on selected branch (already validated if !empty($branch))
        $tableName = $conn->real_escape_string($branch); // Sanitize table name just in case

        // Validate table name against expected values to prevent SQL injection
        if (in_array($tableName, $allowedTables)) {
             // Check for duplicate tenant name, tenant code, or space code
             $checkQuery = "SELECT * FROM $tableName WHERE tenantname = ? OR tenantcode = ? OR spacecode = ?";
             $checkStmt = $conn->prepare($checkQuery);
             $checkStmt->bind_param("sss", $tenantname, $tenantcode, $spacecode);
             $checkStmt->execute();
             $checkResult = $checkStmt->get_result();
             
             if ($checkResult->num_rows > 0) {
                 // Duplicate found - determine which field is duplicate
                 $duplicate = $checkResult->fetch_assoc();
                 $duplicateFields = [];
                 
                 if (strtolower($duplicate['tenantname']) === strtolower($tenantname)) {
                     $duplicateFields[] = "Tenant Name '" . htmlspecialchars($tenantname) . "'";
                 }
                 if (strtolower($duplicate['tenantcode']) === strtolower($tenantcode)) {
                     $duplicateFields[] = "Tenant Code '" . htmlspecialchars($tenantcode) . "'";
                 }
                 if (strtolower($duplicate['spacecode']) === strtolower($spacecode)) {
                     $duplicateFields[] = "Space Code '" . htmlspecialchars($spacecode) . "'";
                 }
                 
                 $duplicateMessage = implode(", ", $duplicateFields);
                 $_SESSION['errorMessage'] = "Cannot add tenant. " . $duplicateMessage . " already exists in " . strtoupper($branch) . " branch!";
                 $checkStmt->close();
                 header("Location: ".$_SERVER['PHP_SELF']."?branch=" . urlencode($branch));
                 exit();
             }
             $checkStmt->close();
             
             $insertQuery = "INSERT INTO $tableName (tenantname, tenantcode, spacecode, daily, rentbal, runningbal, started_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
             $stmt = $conn->prepare($insertQuery);
             // Use "d" for decimal/double for float/double types in bind_param
             $stmt->bind_param("ssssdds", $tenantname, $tenantcode, $spacecode, $daily, $rentbal, $runningbal, $started_date);

            if ($stmt->execute()) {
                // Get the inserted tenant ID
                $insertedId = $conn->insert_id;
                
                // Prepare tenant data for logging
                $tenantData = [
                    'id' => $insertedId,
                    'tenantname' => $tenantname,
                    'tenantcode' => $tenantcode,
                    'spacecode' => $spacecode,
                    'daily' => $daily,
                    'rentbal' => $rentbal,
                    'runningbal' => $runningbal
                ];
                
                // Get user information
                $userEmail = $_SESSION['username'] ?? 'unknown';
                $userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'] ?? 'Unknown User';
                
                // Log tenant creation
                logTenantHistory($conn, 'created', $tenantData, $userEmail, $userName, $branch);
                
                $_SESSION['successMessage'] = "Tenant added successfully to " . strtoupper($branch) . "!"; // Store success message in session
                // Redirect back to the same branch view
                header("Location: ".$_SERVER['PHP_SELF']."?branch=" . urlencode($branch));
                exit();
            } else {
                // Handle insertion error (optional)
                 $_SESSION['errorMessage'] = "Error adding tenant: " . $conn->error;
                 header("Location: ".$_SERVER['PHP_SELF']."?branch=" . urlencode($branch));
                 exit();
            }
             $stmt->close();
        } else {
            // Handle invalid branch submission (optional)
             $_SESSION['errorMessage'] = "Invalid branch selected!";
             header("Location: ".$_SERVER['PHP_SELF']);
             exit();
        }
    } else {
         $_SESSION['errorMessage'] = "Please select a branch before adding a tenant.";
         header("Location: ".$_SERVER['PHP_SELF']);
         exit();
    }
}


// Fetch updated data if a branch is selected and is valid
$data = [];
if (!empty($branch) && in_array($branch, $allowedTables)) {
     $tableName = $conn->real_escape_string($branch);
    $query = "SELECT * FROM $tableName"; // Use the selected branch table
    $result = $conn->query($query);
    if ($result) {
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $result->free(); // Free result set
    } else {
        // Handle query error (optional)
        $_SESSION['errorMessage'] = "Error fetching data: " . $conn->error;
         // Optionally unset branch to show the "Please select branch" message again
         // $branch = '';
    }
}

// Calculate totals (only if data is fetched)
$totalDaily = array_sum(array_column($data, 'daily'));
$totalRentBal = array_sum(array_column($data, 'rentbal'));
$totalRunningBal = array_sum(array_column($data, 'runningbal'));

// Get messages from session
$successMessage = isset($_SESSION['successMessage']) ? $_SESSION['successMessage'] : '';
$errorMessage = isset($_SESSION['errorMessage']) ? $_SESSION['errorMessage'] : '';

// Unset session messages after retrieving them
unset($_SESSION['successMessage']);
unset($_SESSION['errorMessage']);

$conn->close(); // Close the database connection

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>LCLopez Resources - Tenant Management <?php echo !empty($branch) ? '- ' . strtoupper($branch) : ''; ?></title>
    
    <!-- SB Admin 2 / Modern Dashboard Styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/modern-dashboard.css?v=<?= time() ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* DataTables Custom Overrides */
        .table-container {
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            background-color: var(--card-bg);
            border: 1px solid #e5e7eb;
            margin-top: 1.5rem;
        }

        .table thead th {
            background-color: #1e293b !important;
            color: #fff !important;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            border-bottom: none !important;
            padding: 12px 30px 12px 15px !important;
        }

        #tenantTable tbody td {
            font-size: 0.85rem;
            vertical-align: middle;
            color: var(--text-secondary);
        }

        .table tbody tr:hover {
            background-color: rgba(99, 102, 241, 0.04);
        }

        /* Specific Button Colors for Tenants */
        .btn-success { background-color: #10b981; border-color: #10b981; }
        .btn-primary { background-color: var(--accent-indigo); border-color: var(--accent-indigo); }
        .btn-info { background-color: #0ea5e9; border-color: #0ea5e9; }
        
        .dataTables_length select { 
            border-radius: var(--radius-sm) !important; 
            border: 1px solid #e2e8f0 !important; 
            padding: 4px 28px 4px 12px !important; 
            margin: 0 6px !important;
            outline: none !important;
            width: auto !important;
            display: inline-block !important;
        }
        .dataTables_filter input { 
            border-radius: var(--radius-sm) !important; 
            border: 1px solid #e2e8f0 !important; 
            padding: 4px 12px !important; 
            margin-left: 8px !important;
            outline: none !important;
            display: inline-block !important;
            width: auto !important;
        }
        
        /* Modern Select overrides */
        .form-control-modern {
            border-radius: var(--radius-md);
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            color: #1e293b;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .form-control-modern:focus {
            border-color: var(--accent-indigo);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
            outline: none;
        }

        .totals-section {
            background-color: #f8fafc;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: var(--shadow-sm);
        }
        .total-item {
            text-align: center;
        }
        .total-label {
            font-weight: 600;
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .total-value {
            font-size: 1.25rem;
            color: var(--accent-indigo);
            font-weight: 700;
        }

        /* SweetAlert2 custom styling */
        .swal2-popup { font-size: 0.875rem; }
        .swal2-title { font-size: 1.25rem; }
        .swal2-html-container { font-size: 0.875rem; }
    </style>
</head>

<body id="page-top">
    <!-- Modern Page Loader -->
    <div id="loader-overlay" style="display:none;">
        <div class="modern-spinner">
            <div></div><div></div><div></div><div></div>
        </div>
        <div class="loader-text">Loading Data...</div>
    </div>

    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center" href="admin.php">
                <div class="sidebar-brand-icon">
                    <img src="../images/lc.png" alt="logo" class="brand-image">
                </div>
                <div class="sidebar-brand-text mx-3">
                    <span class="brand-text-main">LCLopez</span>
                    <span class="brand-text-sub">Resources</span>
                </div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item">
                <a class="nav-link" href="admin.php">
                    <i class="fas fa-fw fa-crown dashboard-icon"></i>
                    <span class="dashboard-text">Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Menu</div>
            <li class="nav-item">
                <a class="nav-link" href="collection.php">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Collection</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="soa.php">
                    <i class="fas fa-fw fa-file-invoice"></i>
                    <span>SOA</span>
                </a>
            </li>
            <li class="nav-item active">
                <a class="nav-link" href="tenants.php">
                    <i class="fas fa-fw fa-user-tie"></i>
                    <span>Tenants</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="voidedtransactions.php">
                    <i class="fas fa-fw fa-ban"></i>
                    <span>Voided Transactions</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="usersadmin.php">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-fw fa-sign-in-alt"></i>
                    <span>Login Page</span>
                </a>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
        </ul>
        <!-- End of Sidebar -->

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        
                        <!-- Auto Update Toggle -->
                        <li class="nav-item dropdown no-arrow mx-1 d-flex align-items-center">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="dashboardAutoUpdate" checked>
                                <label class="custom-control-label text-gray-600" for="dashboardAutoUpdate">Auto Update</label>
                                <span class="update-status ml-2"></span>
                            </div>
                        </li>

                        <?php include 'notification_bell.php'; ?>
                        <div class="topbar-divider d-none d-sm-block"></div>
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($lname); ?></span>
                                <img class="img-profile rounded-circle" src="<?php echo isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo']) ? htmlspecialchars($_SESSION['profile_photo']) : 'img/undraw_profile.svg'; ?>">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profileModal">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profile
                                </a>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#settingsModal">
                                    <i class="fas fa-cog fa-sm fa-fw mr-2 text-gray-400"></i> Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <div class="container-fluid dashboard-container">

        <div class="card shadow mb-4" style="border:none;">
            <div class="card-header py-3 bg-white d-flex flex-column flex-md-row justify-content-between align-items-center" style="border-bottom: 1px solid #e5e7eb;">
                <h1 class="h4 font-weight-bold text-gray-800 mb-3 mb-md-0" style="margin:0;">Tenants Information</h1>
                 <div class="d-flex flex-column flex-md-row align-items-center mt-3 mt-md-0">
                     <form method="post" class="mr-md-2 mb-2 mb-md-0" style="min-width: 220px;">
                         <select name="branch" onchange="this.form.submit()" class="form-control form-control-modern w-100">
                             <option value="" <?= $branch == '' ? 'selected' : '' ?> disabled>Please Select Branch</option>
                             <?php
                             // Display branch options
                             foreach ($allowedTables as $table_name) {
                                 $selected = ($branch == $table_name) ? 'selected' : '';
                                 echo "<option value=\"$table_name\" $selected>" . strtoupper($table_name) . "</option>";
                             }
                             ?>
                         </select>
                     </form>
                     <button class="btn btn-success w-100 w-md-auto mr-md-2 mb-2 mb-md-0" data-toggle="modal" data-target="#createBranchModal" style="white-space: nowrap;">
                          <i class="fas fa-plus-circle mr-2"></i> Create Branch
                     </button>
                     <button class="btn btn-primary w-100 w-md-auto mr-md-2 mb-2 mb-md-0" data-toggle="modal" data-target="#createModal" <?= empty($branch) ? 'disabled' : '' ?> style="white-space: nowrap;">
                          <i class="fas fa-plus-circle mr-2"></i> Add Tenant
                     </button>
                     <button class="btn btn-info w-100 w-md-auto" data-toggle="modal" data-target="#bulkUpdateModal" <?= empty($branch) ? 'disabled' : '' ?> style="white-space: nowrap;">
                          <i class="fas fa-upload mr-2"></i> Update Data
                     </button>
                 </div>
            </div>

            <div class="card-body">
                 <?php if (empty($branch)): ?>
                     <div class="alert alert-warning text-center" role="alert">
                         <i class="fas fa-info-circle me-2"></i> Please select a branch from the dropdown above to view tenant data and add new tenants.
                     </div>
                 <?php elseif (!in_array($branch, $allowedTables)): ?>
                     <div class="alert alert-danger text-center" role="alert">
                          <i class="fas fa-exclamation-triangle me-2"></i> Invalid branch selected. Please choose from the list.
                     </div>
                 <?php elseif (empty($data)): ?>
                      <div class="alert alert-info text-center" role="alert">
                           <i class="fas fa-info-circle me-2"></i> No tenant data found for the selected branch (<?= strtoupper($branch) ?>).
                      </div>
                 <?php else: ?>
                     <div class="table-responsive">
                         <table id="tenantTable" class="table table-striped table-bordered dt-responsive nowrap" style="width: 100%;">
                             <thead class="bg-blue-600 text-white"> <tr>
                                     <th>Tenant Name</th>
                                     <th>Tenant Code</th>
                                     <th>Space Code</th>
                                     <th>Daily Rent</th>
                                     <th>Rent Balance</th>
                                     <th>Arrear Balance</th>
                                      <th>Started Date</th>
                                     <th>Actions</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($data as $row): ?>
                                     <tr>
                                         <td><?= htmlspecialchars($row['tenantname'] ?? '') ?></td>
                                         <td><?= htmlspecialchars($row['tenantcode'] ?? '') ?></td>
                                         <td><?= htmlspecialchars($row['spacecode'] ?? '') ?></td>
                                         <td><?= number_format((float) ($row['daily'] ?? 0), 2) ?></td>
                                         <td><?= number_format((float) ($row['rentbal'] ?? 0), 2) ?></td>
                                         <td><?= number_format((float) ($row['runningbal'] ?? 0), 2) ?></td>
                                        <td><?= htmlspecialchars($row['started_date'] ?? '') ?></td>
                                         <td>
                                             <button class="btn btn-warning btn-sm me-1" data-toggle="modal" data-target="#editModal"
                                                 data-id="<?= htmlspecialchars($row['id'] ?? '') ?>"
                                                 data-tenantname="<?= htmlspecialchars($row['tenantname'] ?? '') ?>"
                                                 data-tenantcode="<?= htmlspecialchars($row['tenantcode'] ?? '') ?>"
                                                 data-spacecode="<?= htmlspecialchars($row['spacecode'] ?? '') ?>"
                                                 data-daily="<?= htmlspecialchars($row['daily'] ?? '') ?>"
                                                 data-rentbal="<?= htmlspecialchars($row['rentbal'] ?? '') ?>"
                                                 data-runningbal="<?= htmlspecialchars($row['runningbal'] ?? '') ?>"
                                                 data-branch="<?= htmlspecialchars($branch) ?>"
                                                 title="Edit Tenant"><i class="fas fa-edit"></i></button>

                                             <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal"
                                                 data-id="<?= htmlspecialchars($row['id'] ?? '') ?>"
                                                 data-branch="<?= htmlspecialchars($branch) ?>"
                                                 title="Delete Tenant"><i class="fas fa-trash-alt"></i></button>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>
                     </div>

                     <div class="totals-section">
                         <div class="total-item">
                             <div class="total-label">Total Daily Rent:</div>
                             <div class="total-value"><?= number_format($totalDaily, 2) ?></div>
                         </div>
                          <div class="total-item">
                             <div class="total-label">Total Rent Balance:</div>
                             <div class="total-value"><?= number_format($totalRentBal, 2) ?></div>
                         </div>
                          <div class="total-item">
                             <div class="total-label">Total Arrear Balance:</div>
                             <div class="total-value"><?= number_format($totalRunningBal, 2) ?></div>
                         </div>
                     </div>
                 <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="post" action="edittenant.php" id="editForm">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit mr-2"></i> Edit Tenant</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="returnUrl" value="<?= basename($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="editTenantId" id="editTenantId">
                        <input type="hidden" name="editBranch" id="editBranch">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editTenantName" class="form-label">Tenant Name:</label>
                                    <input type="text" name="editTenantName" id="editTenantName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editTenantCode" class="form-label">Tenant Code:</label>
                                    <input type="text" name="editTenantCode" id="editTenantCode" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editSpaceCode" class="form-label">Space Code:</label>
                                    <input type="text" name="editSpaceCode" id="editSpaceCode" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editDailyRent" class="form-label">Daily Rent:</label>
                                    <input type="text" name="editDailyRent" id="editDailyRent" class="form-control decimal" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editRentBal" class="form-label">Rent Balance:</label>
                                    <input type="text" name="editRentBal" id="editRentBal" class="form-control decimal" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editRunningBal" class="form-label">Arrear Balance:</label>
                                    <input type="text" name="editRunningBal" id="editRunningBal" class="form-control decimal" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="deletetenant.php" id="deleteForm">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-trash-alt mr-2"></i> Delete Tenant</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="returnUrl" value="<?= basename($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="deleteTenantId" id="deleteTenantId">
                        <input type="hidden" name="deleteBranch" id="deleteBranch">
                        <p>Are you sure you want to delete this tenant?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="post" id="createForm">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="createModalLabel"><i class="fas fa-user-plus mr-2"></i> Add Tenant</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tenantname" class="form-label">Tenant Name:</label>
                                    <input type="text" name="tenantname" id="tenantname" placeholder="Enter Tenant Name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tenantcode" class="form-label">Tenant Code:</label>
                                    <input type="text" name="tenantcode" id="tenantcode" placeholder="Enter Tenant Code" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="spacecode" class="form-label">Space Code:</label>
                                    <input type="text" name="spacecode" id="spacecode" placeholder="Enter Space Code" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="daily" class="form-label">Daily Rent:</label>
                                    <input type="text" name="daily" id="daily" placeholder="Enter Daily Rent" class="form-control decimal" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rentbal" class="form-label">Rent Balance:</label>
                                    <input type="text" name="rentbal" id="rentbal" placeholder="Enter Rent Balance" class="form-control decimal" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="runningbal" class="form-label">Arrear Balance:</label>
                                    <input type="text" name="runningbal" id="runningbal" placeholder="Enter Arrear Balance" class="form-control decimal" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="started_date" class="form-label">Started Date:</label>
                                    <input type="date" name="started_date" id="started_date" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="branch" value="<?= htmlspecialchars($branch) ?>">
                        <input type="hidden" name="create" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Tenant</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>
        $(document).ready(function () {
            // Debug: Check for messages
            console.log('Success Message: <?= !empty($successMessage) ? addslashes($successMessage) : "none" ?>');
            console.log('Error Message: <?= !empty($errorMessage) ? addslashes($errorMessage) : "none" ?>');

            // Show success message with SweetAlert2 - moved to top for immediate display
            <?php if (!empty($successMessage)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?= addslashes($successMessage) ?>',
                    showConfirmButton: true,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#198754',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
            <?php endif; ?>

            // Show error message with SweetAlert2 - moved to top for immediate display
            <?php if (!empty($errorMessage)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?= addslashes($errorMessage) ?>',
                    showConfirmButton: true,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
            <?php endif; ?>

            // Initialize DataTable if the table exists (i.e., a branch is selected and has data)
            if ($('#tenantTable').length && $('#tenantTable tbody tr').length > 0) {
                 const tenantTable = $('#tenantTable').DataTable({
                    searching: true,
                    paging: true,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, "All"]
                    ],
                    responsive: true, // Use DataTables responsive plugin
                    // Ensure modern flexbox styling is applied correctly
                    "dom": '<"d-flex flex-wrap align-items-center justify-content-between mb-3"l f>rt<"d-flex flex-wrap align-items-center justify-content-between mt-3"i p>',
                    "language": {
                         "search": "Search:", // Customize search label
                         "lengthMenu": "Show _MENU_ entries", // Customize length menu label
                         "info": "Showing _START_ to _END_ of _TOTAL_ entries", // Customize info label
                         "infoEmpty": "Showing 0 to 0 of 0 entries",
                         "infoFiltered": "(filtered from _MAX_ total entries)",
                         "paginate": {
                             "first": "First",
                             "last": "Last",
                             "next": "Next",
                             "previous": "Previous"
                         }
                     },
                     // Prevent the last column (Actions) from being hidden or collapsed
                     "columnDefs": [
                        { "targets": -1, "orderable": false, "width": "140px" }
                     ]
                 });
            }


            // Handle Edit Modal Data Population
            $('#editModal').on('show.bs.modal', function (e) {
                const button = $(e.relatedTarget);
                const id = button.data('id');
                const tenantname = button.data('tenantname');
                const tenantcode = button.data('tenantcode');
                const spacecode = button.data('spacecode');
                const daily = button.data('daily');
                const rentbal = button.data('rentbal');
                const runningbal = button.data('runningbal');
                const branch = button.data('branch');

                $(this).find('#editTenantId').val(id);
                $(this).find('#editTenantName').val(tenantname);
                $(this).find('#editTenantCode').val(tenantcode);
                $(this).find('#editSpaceCode').val(spacecode);
                $(this).find('#editDailyRent').val(daily);
                $(this).find('#editRentBal').val(rentbal);
                $(this).find('#editRunningBal').val(runningbal);
                $(this).find('#editBranch').val(branch);
            });

            // Handle Delete Modal Data Population
            $('#deleteModal').on('show.bs.modal', function (e) {
                const button = $(e.relatedTarget);
                const id = button.data('id');
                const branch = button.data('branch');

                $(this).find('#deleteTenantId').val(id);
                $(this).find('#deleteBranch').val(branch);
            });

            // Edit form confirmation
            $('#editForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                
                Swal.fire({
                    title: 'Update Tenant?',
                    text: "Are you sure you want to save these changes?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            // Delete form confirmation
            $('#deleteForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                
                Swal.fire({
                    title: 'Delete Tenant?',
                    text: "This action cannot be undone!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            // Create form confirmation
            $('#createForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                
                Swal.fire({
                    title: 'Add New Tenant?',
                    text: "Do you want to add this tenant?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, add it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

             // Optional: Prevent non-numeric input for decimal fields
             $('.decimal').on('input', function() {
                 let value = $(this).val();
                 // Allow digits, a single decimal point, and handle potential leading decimal point
                 value = value.replace(/[^0-9.]/g, ''); // Remove anything that's not a digit or a dot
                 value = value.replace(/\.{2,}/g, '.'); // Replace multiple dots with a single dot
                 value = value.replace(/^\./, '0.'); // Add a leading zero if it starts with a dot
                  // Optional: Restrict to only two decimal places after the dot
                 if (value.includes('.')) {
                      const parts = value.split('.');
                      if (parts[1].length > 2) {
                          value = parts[0] + '.' + parts[1].substring(0, 2);
                      }
                 }
                 $(this).val(value);
             });

             // CSV file validation
             $('#csvFile').on('change', function() {
                 const file = this.files[0];
                 const fileType = file.type || 'application/octet-stream';
                 const fileSize = file.size;
                 
                 // Check file type
                 if (fileType !== 'text/csv' && !file.name.endsWith('.csv')) {
                     Swal.fire({
                         icon: 'error',
                         title: 'Invalid File Type',
                         text: 'Please upload a CSV file',
                         confirmButtonColor: '#dc3545'
                     });
                     this.value = '';
                     return;
                 }
                 
                 // Check file size (max 5MB)
                 if (fileSize > 5 * 1024 * 1024) {
                     Swal.fire({
                         icon: 'error',
                         title: 'File Too Large',
                         text: 'File size should not exceed 5MB',
                         confirmButtonColor: '#dc3545'
                     });
                     this.value = '';
                     return;
                 }
             });

             // Branch name validation
             $('#branchName').on('input', function() {
                 this.value = this.value.toLowerCase().replace(/[^a-z]/g, '');
             });

             // Form validation before submission with SweetAlert2 confirmation
             $('#createBranchModal form').on('submit', function(e) {
                 e.preventDefault();
                 const form = this;
                 const branchName = $('#branchName').val();
                 const csvFile = $('#csvFile').val();
                 
                 if (!branchName) {
                     Swal.fire({
                         icon: 'warning',
                         title: 'Missing Information',
                         text: 'Please enter a branch name',
                         confirmButtonColor: '#0d6efd'
                     });
                     return;
                 }
                 
                 if (!csvFile) {
                     Swal.fire({
                         icon: 'warning',
                         title: 'Missing File',
                         text: 'Please select a CSV file',
                         confirmButtonColor: '#0d6efd'
                     });
                     return;
                 }

                 // Confirmation dialog
                 Swal.fire({
                     title: 'Create New Branch?',
                     text: `Create branch "${branchName}" with the selected CSV data?`,
                     icon: 'question',
                     showCancelButton: true,
                     confirmButtonColor: '#198754',
                     cancelButtonColor: '#6c757d',
                     confirmButtonText: 'Yes, create it!',
                     cancelButtonText: 'Cancel'
                 }).then((result) => {
                     if (result.isConfirmed) {
                         form.submit();
                     }
                 });
             });

             // Bulk Update Modal Functionality
             let csvData = null;

                         // Handle file selection for bulk update
            $('#bulkUpdateCsvFile').on('change', function() {
                const file = this.files[0];
                if (!file) {
                    $('#previewBtn, #bulkUpdateBtn').prop('disabled', true);
                    return;
                }

                // Validate file type - now supports CSV, XLS, and XLSX
                const allowedExtensions = ['.csv', '.xls', '.xlsx'];
                const fileExtension = file.name.toLowerCase().substring(file.name.lastIndexOf('.'));
                if (!allowedExtensions.includes(fileExtension)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid File Type',
                        text: 'Please select a CSV, XLS, or XLSX file',
                        confirmButtonColor: '#dc3545'
                    });
                    this.value = '';
                    $('#previewBtn, #bulkUpdateBtn').prop('disabled', true);
                    return;
                }

                 // Validate file size (50MB - increased limit for large datasets)
                 if (file.size > 50 * 1024 * 1024) {
                     Swal.fire({
                         icon: 'error',
                         title: 'File Too Large',
                         text: 'File size should not exceed 50MB',
                         confirmButtonColor: '#dc3545'
                     });
                     this.value = '';
                     $('#previewBtn, #bulkUpdateBtn').prop('disabled', true);
                     return;
                 }

                                 // Handle file reading - only CSV files can be previewed on client side
                if (fileExtension === '.csv') {
                    // Read and parse CSV
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const csvContent = e.target.result;
                            const lines = csvContent.split('\n').filter(line => line.trim() !== '');
                            
                            csvData = lines.map(line => {
                                // Handle CSV with potential quotes and better parsing
                                const columns = [];
                                let current = '';
                                let inQuotes = false;
                                
                                for (let i = 0; i < line.length; i++) {
                                    const char = line[i];
                                    
                                    if (char === '"') {
                                        inQuotes = !inQuotes;
                                    } else if (char === ',' && !inQuotes) {
                                        columns.push(current.trim());
                                        current = '';
                                    } else {
                                        current += char;
                                    }
                                }
                                
                                // Add the last column
                                columns.push(current.trim());
                                
                                // Remove quotes from each column
                                const cleanColumns = columns.map(col => col.replace(/^"|"$/g, ''));
                                
                                return {
                                    tenantname: cleanColumns[0] || '',
                                    tenantcode: cleanColumns[1] || '',
                                    spacecode: cleanColumns[2] || '',
                                    daily: cleanColumns[3] || '',
                                    rentbal: cleanColumns[4] || '',
                                    runningbal: cleanColumns[5] || '',
                                    started_date: cleanColumns[6] || ''
                                };
                            });

                            // Enable buttons
                            $('#previewBtn, #bulkUpdateBtn').prop('disabled', false);
                            
                            // Show preview automatically
                            showPreview();
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'CSV Error',
                                text: 'Error reading CSV file: ' + error.message,
                                confirmButtonColor: '#dc3545'
                            });
                            $('#bulkUpdateCsvFile').val('');
                            $('#previewBtn, #bulkUpdateBtn').prop('disabled', true);
                        }
                    };
                    reader.readAsText(file);
                } else {
                    // For Excel files, we can't preview on client side
                    csvData = null; // Clear any previous CSV data
                    
                    // Enable buttons for Excel files (preview will show a message)
                    $('#previewBtn, #bulkUpdateBtn').prop('disabled', false);
                    
                    // Show Excel file info
                    showExcelPreview(file);
                }
             });

             // Preview functionality
             $('#previewBtn').on('click', function() {
                 showPreview();
             });

                         function showPreview() {
                if (!csvData || csvData.length === 0) {
                    $('#bulkUpdatePreview').addClass('d-none');
                    return;
                }

                const previewData = csvData.slice(0, 5); // Show first 5 rows
                const tbody = $('#previewTableBody');
                tbody.empty();

                previewData.forEach((row, index) => {
                    const tr = $('<tr>');
                    tr.append(`<td>${row.tenantname}</td>`);
                    tr.append(`<td>${row.tenantcode}</td>`);
                    tr.append(`<td><strong>${row.spacecode || '<span class="text-danger">EMPTY</span>'}</strong></td>`);
                    tr.append(`<td>${row.daily}</td>`);
                    tr.append(`<td>${row.rentbal}</td>`);
                    tr.append(`<td>${row.runningbal}</td>`);
                    tr.append(`<td>${row.started_date || 'Not provided'}</td>`);
                    tbody.append(tr);
                    
                    // Debug info in console
                    console.log(`Row ${index + 1}:`, row);
                });

                $('#bulkUpdatePreview').removeClass('d-none');
            }

            function showExcelPreview(file) {
                const tbody = $('#previewTableBody');
                tbody.empty();
                
                // Show Excel file information instead of data preview
                const tr = $('<tr>');
                tr.append(`<td colspan="7" class="text-center">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-file-excel me-2"></i>
                        <strong>Excel File Selected:</strong> ${file.name}<br>
                        <small>Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</small><br>
                        <small class="text-muted">Excel files cannot be previewed on the client side. Click "Process Update" to upload and process the file.</small>
                    </div>
                </td>`);
                tbody.append(tr);

                $('#bulkUpdatePreview').removeClass('d-none');
            }

                         // Handle bulk update form submission
            $('#bulkUpdateForm').on('submit', function(e) {
                e.preventDefault();
                const formElement = this;
                
                const fileInput = $('#bulkUpdateCsvFile')[0];
                if (!fileInput.files || fileInput.files.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing File',
                        text: 'Please select a file (CSV, XLS, or XLSX)',
                        confirmButtonColor: '#0d6efd'
                    });
                    return;
                }
                
                // For CSV files, we need csvData. For Excel files, we don't have client-side data
                const file = fileInput.files[0];
                const fileExtension = file.name.toLowerCase().substring(file.name.lastIndexOf('.'));
                
                if (fileExtension === '.csv' && (!csvData || csvData.length === 0)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid CSV',
                        text: 'Please select a valid CSV file',
                        confirmButtonColor: '#0d6efd'
                    });
                    return;
                }

                 const branch = $('#bulkUpdateBranch').val();
                 if (!branch) {
                     Swal.fire({
                         icon: 'warning',
                         title: 'Missing Branch',
                         text: 'Please select a branch',
                         confirmButtonColor: '#0d6efd'
                     });
                     return;
                 }

                 // Confirmation dialog for bulk update
                 Swal.fire({
                     title: 'Bulk Update Tenants?',
                     html: `Are you sure you want to update tenant data for <strong>${branch.toUpperCase()}</strong> branch?<br><small>This will update or insert tenants based on the file.</small>`,
                     icon: 'question',
                     showCancelButton: true,
                     confirmButtonColor: '#0dcaf0',
                     cancelButtonColor: '#6c757d',
                     confirmButtonText: 'Yes, proceed!',
                     cancelButtonText: 'Cancel'
                 }).then((result) => {
                     if (!result.isConfirmed) {
                         return;
                     }

                     // Show processing modal
                     $('#bulkUpdateModal').modal('hide');
                     $('#processingModal').modal('show');
                     
                     // Update processing message
                     $('#processingModal .modal-title').text('Processing CSV Update...');
                     $('#processingModal p').text('Please wait while we process your file. This may take a few minutes for large files.');

                     // Submit form via AJAX
                     const formData = new FormData(formElement);
                 
                 $.ajax({
                     url: 'bulk_update_tenants.php',
                     type: 'POST',
                     data: formData,
                     processData: false,
                     contentType: false,
                     timeout: 300000, // 5 minutes timeout for large files
                     success: function(response) {
                         $('#processingModal').modal('hide');
                         
                         try {
                             // Handle both string and object responses
                             let result;
                             if (typeof response === 'string') {
                                 result = JSON.parse(response);
                             } else {
                                 result = response;
                             }
                             
                             if (result.success) {
                                 let message = `<div class="text-center">`;
                                 message += `<h5 class="text-success mb-3"><i class="fas fa-check-circle me-2"></i>Bulk Update Completed Successfully!</h5>`;
                                 message += `<div class="row text-center mb-3">`;
                                 message += `<div class="col-6"><strong class="text-primary">${result.updated || 0}</strong><br><small>Updated</small></div>`;
                                 message += `<div class="col-6"><strong class="text-success">${result.inserted || 0}</strong><br><small>Inserted</small></div>`;
                                 message += `</div>`;
                                 message += `<div class="row text-center mb-3">`;
                                 message += `<div class="col-6"><strong class="text-warning">${result.skipped || 0}</strong><br><small>Skipped</small></div>`;
                                 message += `<div class="col-6"><strong class="text-info">${result.total_processed || 0}</strong><br><small>Total Processed</small></div>`;
                                 message += `</div>`;
                                 
                                 if (result.errors && result.errors.length > 0) {
                                     message += `<div class="alert alert-warning mt-3">`;
                                     message += `<strong>Errors encountered: ${result.errors.length}</strong>`;
                                     if (result.errors.length <= 3) {
                                         message += '<br>' + result.errors.join('<br>');
                                     } else {
                                         message += `<br>(Showing first 3 errors)<br>` + result.errors.slice(0, 3).join('<br>');
                                     }
                                     message += `</div>`;
                                 }
                                 message += `<div class="mt-3">`;
                                 message += `<button type="button" class="btn btn-outline-primary btn-sm" onclick="location.reload()">`;
                                 message += `<i class="fas fa-sync-alt me-2"></i>Refresh Page to See Updates`;
                                 message += `</button>`;
                                 message += `</div>`;
                                 message += `</div>`;
                                 
                                 showResultModal('Success', message, 'success');
                                 // Don't auto-reload, let user close manually
                             } else {
                                 let errorMessage = `<div class="text-center">`;
                                 errorMessage += `<h5 class="text-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Error Occurred</h5>`;
                                 if (result.message) {
                                     errorMessage += `<p class="text-danger">${result.message}</p>`;
                                 } else if (result.errors && result.errors.length > 0) {
                                     errorMessage += `<div class="alert alert-danger">`;
                                     errorMessage += result.errors.slice(0, 5).join('<br>');
                                     errorMessage += `</div>`;
                                 } else {
                                     errorMessage += `<p class="text-danger">Unknown error occurred</p>`;
                                 }
                                 errorMessage += `</div>`;
                                 
                                 showResultModal('Error', errorMessage, 'danger');
                             }
                         } catch (e) {
                             console.error('Response parsing error:', e);
                             console.error('Raw response:', response);
                             let errorMessage = `<div class="text-center">`;
                             errorMessage += `<h5 class="text-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Processing Error</h5>`;
                             errorMessage += `<p class="text-danger">Error processing response. Please check the console for details.</p>`;
                             errorMessage += `</div>`;
                             
                             showResultModal('Error', errorMessage, 'danger');
                         }
                     },
                     error: function(xhr, status, error) {
                         $('#processingModal').modal('hide');
                         console.error('AJAX Error:', {xhr, status, error});
                         
                         let errorMessage = `<div class="text-center">`;
                         errorMessage += `<h5 class="text-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Network Error</h5>`;
                         
                         if (xhr.responseText) {
                             try {
                                 const errorResponse = JSON.parse(xhr.responseText);
                                 errorMessage += `<p class="text-danger">${errorResponse.message || error}</p>`;
                             } catch (e) {
                                 errorMessage += `<p class="text-danger">${xhr.responseText || error}</p>`;
                             }
                         } else {
                             errorMessage += `<p class="text-danger">${error}</p>`;
                         }
                         
                         errorMessage += `</div>`;
                         
                         showResultModal('Network Error', errorMessage, 'danger');
                     }
                 });
                 }); // Close Swal confirmation then()
             });

             // Reset modal when closed
             $('#bulkUpdateModal').on('hidden.bs.modal', function() {
                 $('#bulkUpdateCsvFile').val('');
                 $('#previewBtn, #bulkUpdateBtn').prop('disabled', true);
                 $('#bulkUpdatePreview').addClass('d-none');
                 csvData = null;
             });

             // Function to show result modal
             function showResultModal(title, content, type) {
                 const modal = $('#resultModal');
                 const header = $('#resultModalHeader');
                 const titleElement = $('#resultModalLabel');
                 const contentElement = $('#resultModalContent');
                 
                 // Set header color based on type
                 header.removeClass('bg-success bg-danger bg-warning bg-info').addClass(`bg-${type} text-white`);
                 
                 // Set title with appropriate icon
                 let icon = 'fas fa-info-circle';
                 if (type === 'success') icon = 'fas fa-check-circle';
                 else if (type === 'danger') icon = 'fas fa-exclamation-triangle';
                 else if (type === 'warning') icon = 'fas fa-exclamation-circle';
                 
                 titleElement.html(`<i class="${icon} me-2"></i>${title}`);
                 
                 // Set content
                 contentElement.html(content);
                 
                 // Show modal
                 modal.modal('show');
             }

             // Handle OK button click in result modal
             $('#resultModalOkBtn').on('click', function() {
                 $('#resultModal').modal('hide');
                 location.reload();
             });

             // Handle X button click in result modal
             $('#resultModal .close').on('click', function() {
                 $('#resultModal').modal('hide');
                 location.reload();
             });

            // Auto Update Functions
            let autoUpdateInterval;
            let countdownInterval;
            let lastUpdateTime;
            const UPDATE_INTERVAL = 59000; // 59 seconds

            function updateTimeRemaining() {
                if ($('#dashboardAutoUpdate').is(':checked') && lastUpdateTime) {
                    const now = new Date().getTime();
                    const nextUpdate = lastUpdateTime + UPDATE_INTERVAL;
                    const remaining = Math.max(0, Math.ceil((nextUpdate - now) / 1000));
                    
                    if (remaining > 0) {
                        $('.update-status').html(`<span class="badge badge-info" style="font-size: 0.75rem; background-color: var(--accent-indigo); color: white;">Next update in ${remaining}s</span>`);
                    } else {
                        $('.update-status').empty();
                    }
                } else {
                    $('.update-status').empty();
                }
            }

            function performUpdate() {
                // Prevent update if a modal is open
                if ($('.modal.show').length > 0) {
                    lastUpdateTime = new Date().getTime(); // Reset timer without interrupting user
                    return;
                }
                
                lastUpdateTime = new Date().getTime();
                if (window.location.pathname.includes('collection.php')) {
                    if (typeof dataTable !== 'undefined') {
                        dataTable.ajax.reload(null, false);
                    }
                } else {
                    location.reload();
                }
            }

            $('#dashboardAutoUpdate').change(function() {
                if ($(this).is(':checked')) {
                    lastUpdateTime = new Date().getTime();
                    autoUpdateInterval = setInterval(performUpdate, UPDATE_INTERVAL);
                    countdownInterval = setInterval(updateTimeRemaining, 1000);
                    updateTimeRemaining();
                } else {
                    clearInterval(autoUpdateInterval);
                    clearInterval(countdownInterval);
                    $('.update-status').empty();
                }
            });

            // Initialize Auto Update if checked
            if ($('#dashboardAutoUpdate').is(':checked')) {
                lastUpdateTime = new Date().getTime();
                autoUpdateInterval = setInterval(performUpdate, UPDATE_INTERVAL);
                countdownInterval = setInterval(updateTimeRemaining, 1000);
                updateTimeRemaining();
            }


        });
    </script>

    <!-- Create Branch Modal -->
    <div class="modal fade" id="createBranchModal" tabindex="-1" aria-labelledby="createBranchModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="create_branch.php" enctype="multipart/form-data">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="createBranchModalLabel"><i class="fas fa-building mr-2"></i> Create New Branch</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="branchName" class="form-label">Branch Name:</label>
                            <input type="text" name="branchName" id="branchName" class="form-control" required 
                                   placeholder="Enter branch name (lowercase, no spaces)" 
                                   pattern="[a-z]+" 
                                   title="Please use lowercase letters only, no spaces or special characters">
                            <small class="text-muted">Example: apm, nova, sanko</small>
                        </div>
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Import Initial Data (CSV):</label>
                            <input type="file" name="csvFile" id="csvFile" class="form-control" accept=".csv" required>
                            <small class="text-muted">CSV should contain columns: tenantname, tenantcode, spacecode, daily, rentbal, runningbal (started_date is optional, defaults to today)</small>
                        </div>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Important Integration Steps:</h6>
                            <hr>
                            <p class="mb-2"><strong>After creating the branch, you need to:</strong></p>
                            <ol class="ps-3">
                               
                                <li class="mb-2">Update collection display files:
                                    <ul class="ps-3 mt-1">
                                        <li>Modify collection.php</li>
                                        <li>Modify collectionacc.php</li>
                                        <li>Add branch to branch filters</li>
                                    </ul>
                                </li>
                                <li class="mb-2">Update transaction processing:
                                    <ul class="ps-3 mt-1">
                                        <li>Update fetch_tenants.php</li>
                                        <li>Update fetch_spacecodes.php</li>
                                        <li>Modify transaction handling in collection forms</li>
                                    </ul>
                                </li>
                                <li class="mb-2">Update user transaction files:
                                    <ul class="ps-3 mt-1">
                                        <li>Modify user.php to include new branch in table selection:
                                            <code class="d-block bg-light p-2 mt-1">
                                                // In the table selection logic (around line 20-35)<br>
                                                } elseif ($branch === 'New Branch Name') {<br>
                                                &nbsp;&nbsp;$tableName = 'collected{branchname}';<br>
                                                &nbsp;&nbsp;$latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM collected{branchname}");<br>
                                                }
                                            </code>
                                        </li>
                                        <li>Update get_tenant_details.php:
                                            <code class="d-block bg-light p-2 mt-1">
                                                // Add new branch condition<br>
                                                } elseif ($branch === 'New Branch Name') {<br>
                                                &nbsp;&nbsp;$query = "SELECT * FROM {branchname} WHERE spacecode = ?";<br>
                                                }
                                            </code>
                                        </li>
                                        <li>Update print.php for receipts:
                                            <code class="d-block bg-light p-2 mt-1">
                                                // Add new branch table selection<br>
                                                } elseif ($branch === 'New Branch Name') {<br>
                                                &nbsp;&nbsp;$table = "collected{branchname}";<br>
                                                &nbsp;&nbsp;$tenant_table = "{branchname}";<br>
                                                }
                                            </code>
                                        </li>
                                        <li>Update summary.php for reports:
                                            <code class="d-block bg-light p-2 mt-1">
                                                // Add new branch to summary queries<br>
                                                UNION ALL<br>
                                                SELECT * FROM collected{branchname}<br>
                                                WHERE DATE(collected_date) = CURDATE()
                                            </code>
                                        </li>
                                    </ul>
                                </li>
                                <li class="mb-2">Update printing and reporting:
                                    <ul class="ps-3 mt-1">
                                        <li>Modify reprint.php:
                                            <code class="d-block bg-light p-2 mt-1">
                                                // Add new branch condition<br>
                                                } elseif ($branch === 'New Branch Name') {<br>
                                                &nbsp;&nbsp;$table = "collected{branchname}";<br>
                                                }
                                            </code>
                                        </li>
                                        <li>Update printsummary.php:
                                            <code class="d-block bg-light p-2 mt-1">
                                                // Add new branch to summary printing<br>
                                                } elseif ($selectedBranch === 'New Branch Name') {<br>
                                                &nbsp;&nbsp;$table = "collected{branchname}";<br>
                                                &nbsp;&nbsp;$query .= " UNION ALL SELECT * FROM $table";<br>
                                                }
                                            </code>
                                        </li>
                                    </ul>
                                </li>
                                <li class="mb-2">Update collection display:
                                    <ul class="ps-3 mt-1">
                                        <li>Add branch to collection.php table selection:
                                            <code class="d-block bg-light p-2 mt-1">
                                                } elseif ($selectedBranch == 'New Branch Name') {<br>
                                                &nbsp;&nbsp;$table = 'collected{branchname}';<br>
                                                }
                                            </code>
                                        </li>
                                        <li>Update branch filters in collection forms</li>
                                        <li>Update collection reports and summaries</li>
                                    </ul>
                                </li>
                            </ol>
                            <div class="alert alert-warning mt-3">
                                <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Important Files to Update:</h6>
                                <hr>
                                <p class="mb-2">The following files need to be modified for the new branch:</p>
                                <ul class="mb-0">
                                    <li>user.php - Main transaction interface and processing</li>
                                    <li>get_tenant_details.php - Tenant information lookup</li>
                                    <li>print.php - Receipt printing</li>
                                    <li>summary.php - Daily summary reports</li>
                                    <li>reprint.php - Receipt reprinting</li>
                                    <li>printsummary.php - Summary printing</li>
                                    <li>collection.php - Collection display and reporting</li>
                                    <li>modalsuccess.php - Transaction success handling</li>
                                </ul>
                                <p class="mt-2 mb-0">Each file needs to be updated to handle the new branch's table structure and naming convention.</p>
                            </div>
                            <div class="alert alert-info mt-3">
                                <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Testing Steps:</h6>
                                <hr>
                                <ol class="mb-0">
                                    <li>Test tenant lookup with the new branch</li>
                                    <li>Create a test transaction</li>
                                    <li>Verify balance updates</li>
                                    <li>Test receipt printing:
                                        <ul>
                                            <li>Print new transaction receipt</li>
                                            <li>Test receipt reprinting</li>
                                            <li>Verify all receipt details</li>
                                        </ul>
                                    </li>
                                    <li>Test summary reports:
                                        <ul>
                                            <li>Check daily summary</li>
                                            <li>Verify branch totals</li>
                                            <li>Test summary printing</li>
                                        </ul>
                                    </li>
                                    <li>Check collection reports</li>
                                    <li>Verify transaction success modal</li>
                                </ol>
                            </div>
                            <p class="mb-0 mt-3"><strong>Note:</strong> These changes require direct database access and code modifications. Please coordinate with your system administrator.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Create Branch</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Update Modal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="post" action="bulk_update_tenants.php" enctype="multipart/form-data" id="bulkUpdateForm">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="bulkUpdateModalLabel"><i class="fas fa-upload mr-2"></i> Bulk Update Tenants (CSV/Excel)</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle"></i> File Format Instructions:</h6>
                            <hr>
                            <p class="mb-2"><strong>CSV/Excel files should contain the following columns in order:</strong></p>
                            <ol class="mb-2">
                                <li>Tenant Name</li>
                                <li>Tenant Code</li>
                                <li>Space Code (unique identifier)</li>
                                <li>Daily Rent</li>
                                <li>Rent Balance</li>
                                <li>Arrear Balance</li>
                                <li>Started Date (optional, format: YYYY-MM-DD)</li>
                            </ol>
                            <p class="mb-0"><strong>CSV Example:</strong><br>
                            <code>Calipes, Celestino,1A001,195.77,0.00,2431.00,2024-01-01</code><br>
                            <small class="text-muted">Excel files should have the same column structure but in spreadsheet format.</small></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulkUpdateBranch" class="form-label">Select Branch:</label>
                            <select name="bulkUpdateBranch" id="bulkUpdateBranch" class="form-select" required <?= empty($branch) ? '' : 'disabled' ?>>
                                <option value="">Please Select Branch</option>
                                <?php
                                foreach ($allowedTables as $table_name) {
                                    $selected = ($branch == $table_name) ? 'selected' : '';
                                    echo "<option value=\"$table_name\" $selected>" . strtoupper($table_name) . "</option>";
                                }
                                ?>
                            </select>
                            <?php if (!empty($branch)): ?>
                                <input type="hidden" name="bulkUpdateBranch" value="<?= htmlspecialchars($branch) ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulkUpdateCsvFile" class="form-label">Upload File (CSV/Excel):</label>
                            <input type="file" name="bulkUpdateCsvFile" id="bulkUpdateCsvFile" class="form-control" accept=".csv,.xls,.xlsx" required>
                            <small class="text-muted">Supported formats: CSV, XLS, XLSX | Maximum file size: 50MB</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Matching Logic:</h6>
                            <hr>
                            <ul class="mb-0">
                                <li><strong>Primary Matching:</strong> Space Code only</li>
                                <li><strong>Secondary Matching:</strong> Tenant Name AND Space Code (for "others in space code")</li>
                                <li>If tenant doesn't exist (spacecode not found): Insert as new tenant</li>
                                <li>Empty values in CSV will not overwrite existing data</li>
                                <li>Started Date is optional - if not provided, current date will be used for new tenants</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Common Excel Issues:</h6>
                            <hr>
                            <ul class="mb-0">
                                <li><strong>Summary/Total Rows:</strong> Remove any rows that contain totals, subtotals, or summary calculations</li>
                                <li><strong>Empty Space Codes:</strong> Every data row must have a space code (3rd column)</li>
                                <li><strong>Headers:</strong> Remove or ensure header rows are properly formatted</li>
                                <li><strong>Merged Cells:</strong> Unmerge any merged cells before uploading</li>
                                <li><strong>Formulas:</strong> Convert calculated cells to values before uploading</li>
                            </ul>
                        </div>
                        
                        <div id="bulkUpdatePreview" class="d-none">
                            <h6><i class="fas fa-eye me-2"></i>Preview (First 5 rows):</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tenant Name</th>
                                            <th>Tenant Code</th>
                                            <th>Space Code</th>
                                            <th>Daily Rent</th>
                                            <th>Rent Balance</th>
                                            <th>Arrear Balance</th>
                                            <th>Started Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-info" id="previewBtn" disabled><i class="fas fa-eye me-2"></i>Preview</button>
                        <button type="submit" class="btn btn-primary" id="bulkUpdateBtn" disabled>
                            <i class="fas fa-upload me-2"></i>Process Update
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Processing Modal -->
    <div class="modal fade" id="processingModal" tabindex="-1" aria-labelledby="processingModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 200px;">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Processing...</span>
                    </div>
                    <h5 class="modal-title mb-2" id="processingModalLabel">Processing CSV Update</h5>
                    <p class="text-muted mb-3">Please wait while we process your file...</p>
                    <div class="progress mb-2" style="width: 100%;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                    </div>
                    <small class="text-muted">This may take a few moments depending on file size</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" id="resultModalHeader">
                    <h5 class="modal-title" id="resultModalLabel">
                        <i class="fas fa-info-circle mr-2"></i>Result
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 150px;">
                    <div id="resultModalContent">
                        <!-- Content will be dynamically inserted here -->
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary" id="resultModalOkBtn">OK</button>
                </div>
            </div>
        </div>
    </div>
                </div> <!-- Closes #content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white mt-4 shadow-sm border-top">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto text-gray-500 font-weight-500">
                        <span>Copyright &copy; IT Department <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
        </div> <!-- Closes #content-wrapper -->
    </div> <!-- Closes #wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
<?php include 'notification_script.php'; ?>
</body>
</html>
<?php
ob_end_flush(); // Flush the output buffer
?>
