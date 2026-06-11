<?php
session_start(); // Start session FIRST before any output
ob_start(); // Start output buffering
include '../config.php'; // Include your database configuration
include '../navacc.php'; // Include your navigation bar
include 'tenant_history_logger.php'; // Include tenant history logger

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

// Set the selected branch from POST, GET, or default to empty
// Prioritize POST (from select change or modal form), then GET (if redirected back), then empty
$branch = '';
if (isset($_GET['branch']) && !empty($_GET['branch'])) {
    $branch = $_GET['branch'];
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
        $allowedTables = ['apm', 'nova', 'sanko']; // Define your allowed table names
        if (in_array($tableName, $allowedTables)) {
             // Check for duplicate tenant name, tenant code, or space code
             // Check each field separately to provide more specific error messages
             $duplicateFields = [];
             
             // Check for duplicate tenant name
             $checkNameQuery = "SELECT id FROM $tableName WHERE LOWER(tenantname) = LOWER(?)";
             $checkNameStmt = $conn->prepare($checkNameQuery);
             $checkNameStmt->bind_param("s", $tenantname);
             $checkNameStmt->execute();
             $nameResult = $checkNameStmt->get_result();
             if ($nameResult->num_rows > 0) {
                 $duplicateFields[] = "Tenant Name '" . htmlspecialchars($tenantname) . "'";
             }
             $checkNameStmt->close();
             
             // Check for duplicate tenant code
             $checkCodeQuery = "SELECT id FROM $tableName WHERE LOWER(tenantcode) = LOWER(?)";
             $checkCodeStmt = $conn->prepare($checkCodeQuery);
             $checkCodeStmt->bind_param("s", $tenantcode);
             $checkCodeStmt->execute();
             $codeResult = $checkCodeStmt->get_result();
             if ($codeResult->num_rows > 0) {
                 $duplicateFields[] = "Tenant Code '" . htmlspecialchars($tenantcode) . "'";
             }
             $checkCodeStmt->close();
             
             // Check for duplicate space code
             $checkSpaceQuery = "SELECT id FROM $tableName WHERE LOWER(spacecode) = LOWER(?)";
             $checkSpaceStmt = $conn->prepare($checkSpaceQuery);
             $checkSpaceStmt->bind_param("s", $spacecode);
             $checkSpaceStmt->execute();
             $spaceResult = $checkSpaceStmt->get_result();
             if ($spaceResult->num_rows > 0) {
                 $duplicateFields[] = "Space Code '" . htmlspecialchars($spacecode) . "'";
             }
             $checkSpaceStmt->close();
             
             // If any duplicates found, show error
             if (!empty($duplicateFields)) {
                 $duplicateMessage = implode(", ", $duplicateFields);
                 $_SESSION['errorMessage'] = "Cannot add tenant. " . $duplicateMessage . " already exists in " . strtoupper($branch) . " branch!";
                 header("Location: ".$_SERVER['PHP_SELF']."?branch=" . urlencode($branch));
                 exit();
             }
             
             $insertQuery = "INSERT INTO $tableName (tenantname, tenantcode, spacecode, daily, rentbal, runningbal, started_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
             $stmt = $conn->prepare($insertQuery);
             // Use "d" for decimal/double for float/double types in bind_param
             $stmt->bind_param("ssssdds", $tenantname, $tenantcode, $spacecode, $daily, $rentbal, $runningbal, $started_date);

            if ($stmt->execute()) {
                // Log tenant creation history
                $tenantData = [
                    'id' => $conn->insert_id, // Get the inserted ID
                    'tenantname' => $tenantname,
                    'tenantcode' => $tenantcode,
                    'spacecode' => $spacecode,
                    'daily' => $daily,
                    'rentbal' => $rentbal,
                    'runningbal' => $runningbal
                ];
                
                // Get user info from session
                $userEmail = $_SESSION['email'] ?? 'unknown';
                $userName = $_SESSION['username'] ?? 'unknown';
                
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
$allowedTables = ['apm', 'nova', 'sanko']; // Define your allowed table names
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
    <title>Tenant Data - <?php echo !empty($branch) ? strtoupper($branch) : 'Select Branch'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* Custom styles or overrides */
        body {
            background-color: #f3f4f6;
            font-family: sans-serif;
            font-size: 0.875rem; /* Reduced base font size */
        }
        .container-custom {
             max-width: 1400px;
             margin: 0 auto;
             padding: 1rem; /* Reduced padding */
        }
        .card {
             background-color: #ffffff;
             border-radius: 0.5rem;
             box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08); /* Reduced shadow */
             padding: 1rem; /* Reduced padding */
             margin-bottom: 1rem;
        }
         .card-header {
             border-bottom: 1px solid #e5e7eb;
             padding-bottom: 0.75rem; /* Reduced padding */
             margin-bottom: 0.75rem;
         }
         .card-header h1 {
             font-size: 1.5rem; /* Reduced heading size */
             margin: 0;
         }
        .dataTables_wrapper .dataTables_filter input {
             border-radius: 0.25rem;
             border: 1px solid #d1d5db;
             padding: 0.375rem 0.625rem; /* Reduced padding */
             box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
             font-size: 0.875rem;
         }
        .dataTables_wrapper .dataTables_length select {
            width: 60px !important;
            border-radius: 0.25rem;
             border: 1px solid #d1d5db;
             padding: 0.375rem 0.625rem;
             box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
             font-size: 0.875rem;
        }
         .dataTables_wrapper .dataTables_paginate .paginate_button.current,
         .dataTables_wrapper .dataTables_paginate .paginate_button:active {
            background: none !important;
            color: #ffffff !important;
            border-color: #0d6efd !important;
            background-color: #0d6efd !important;
            border-radius: 0.25rem;
            font-size: 0.875rem;
         }
         .dataTables_wrapper .dataTables_paginate .paginate_button {
             border-radius: 0.25rem;
             margin: 0 2px;
             border: 1px solid transparent;
             font-size: 0.875rem;
         }
         .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
             background-color: #e5e7eb !important;
             border-color: #d1d5db !important;
         }
         .dataTables_wrapper .dataTables_info,
         .dataTables_wrapper .dataTables_length label {
             font-size: 0.875rem;
         }

         /* Table styling */
         table.dataTable {
             font-size: 0.813rem;
         }
         table.dataTable thead th {
             font-size: 0.875rem;
             padding: 0.5rem;
         }
         table.dataTable tbody td {
             padding: 0.5rem;
         }

         /* Button styling */
         .btn {
             font-size: 0.813rem;
             padding: 0.375rem 0.75rem;
         }
         .btn-sm {
             font-size: 0.75rem;
             padding: 0.25rem 0.5rem;
         }

         /* Modal styling */
         .modal-title {
             font-size: 1.125rem;
         }
         .modal-body {
             font-size: 0.875rem;
         }
         .form-label {
             font-size: 0.875rem;
             margin-bottom: 0.375rem;
         }
         .form-control, .form-select {
             font-size: 0.875rem;
             padding: 0.375rem 0.75rem;
         }

         /* Style for the totals section */
        .totals-section {
            background-color: #e0f2f7;
            border-radius: 0.5rem;
            padding: 0.75rem; /* Reduced padding */
            margin-top: 1rem;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .total-item {
            text-align: center;
        }
        .total-label {
            font-weight: bold;
            color: #0369a1;
            font-size: 0.813rem;
        }
        .total-value {
            font-size: 1.125rem; /* Reduced value size */
            color: #075985;
            font-weight: 600;
        }

        /* Custom styles for Bootstrap alerts to match theme */
         .alert {
             border-radius: 0.5rem;
             padding: 0.75rem;
             margin-bottom: 1rem;
             font-size: 0.875rem;
         }
         .alert-success {
             background-color: #d1fae5;
             color: #065f46;
             border-color: #a7f3d0;
         }
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
         .alert-warning {
             background-color: #fffbeb;
             color: #92400e;
             border-color: #fde68a;
         }

         /* SweetAlert2 custom styling */
         .swal2-popup {
             font-size: 0.875rem;
         }
         .swal2-title {
             font-size: 1.25rem;
         }
         .swal2-html-container {
             font-size: 0.875rem;
         }

    </style>
</head>

<body class="bg-gray-100">
    <div class="container-custom mt-3">
        <div id="loader" class="hidden fixed inset-0 flex items-center justify-center bg-gray-700 bg-opacity-50 z-50">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header flex flex-col md:flex-row justify-between items-center">
                <h1 class="text-xl font-bold text-gray-800 mb-2 md:mb-0">Tenants Information</h1>
                 <div class="flex flex-col md:flex-row items-center gap-2">
                     <form method="get" class="flex-grow">
                         <select name="branch" onchange="this.form.submit()" class="form-select block w-full">
                             <option value="" <?= $branch == '' ? 'selected' : '' ?> disabled>Please Select Branch</option>
                             <option value="apm" <?= $branch == 'apm' ? 'selected' : '' ?>>APM</option>
                             <option value="nova" <?= $branch == 'nova' ? 'selected' : '' ?>>NOVA</option>
                             <option value="sanko" <?= $branch == 'sanko' ? 'selected' : '' ?>>SANKO</option>
                         </select>
                     </form>
                     <button class="btn btn-primary w-full md:w-auto" data-bs-toggle="modal" data-bs-target="#createModal" <?= empty($branch) ? 'disabled' : '' ?>>
                          <i class="fas fa-plus-circle me-2"></i> Add Tenant
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
                     <div class="table-responsive" style="overflow-x: auto; min-width: 1000px;">
                         <table id="tenantTable" class="table table-striped table-bordered dt-responsive nowrap" style="width: 100%; min-width: 1200px;">
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
                                             <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                                 data-id="<?= htmlspecialchars($row['id'] ?? '') ?>"
                                                 data-tenantname="<?= htmlspecialchars($row['tenantname'] ?? '') ?>"
                                                 data-tenantcode="<?= htmlspecialchars($row['tenantcode'] ?? '') ?>"
                                                 data-spacecode="<?= htmlspecialchars($row['spacecode'] ?? '') ?>"
                                                 data-daily="<?= htmlspecialchars($row['daily'] ?? '') ?>"
                                                 data-rentbal="<?= htmlspecialchars($row['rentbal'] ?? '') ?>"
                                                 data-runningbal="<?= htmlspecialchars($row['runningbal'] ?? '') ?>"
                                                 data-branch="<?= htmlspecialchars($branch) ?>"
                                                 title="Edit Tenant"><i class="fas fa-edit"></i></button>

                                             <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal"
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
            <form method="post" action="edittenantacc.php" id="editForm">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit me-2"></i> Edit Tenant</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
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
                                    <input type="text" name="editRentBal" id="editRentBal" class="form-control decimal" required readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editRunningBal" class="form-label">Arrear Balance:</label>
                                    <input type="text" name="editRunningBal" id="editRunningBal" class="form-control decimal" required readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                        <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-trash-alt me-2"></i> Delete Tenant</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="deleteTenantId" id="deleteTenantId">
                        <input type="hidden" name="deleteBranch" id="deleteBranch">
                        <p>Are you sure you want to delete this tenant?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                        <h5 class="modal-title" id="createModalLabel"><i class="fas fa-user-plus me-2"></i> Add Tenant</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Tenant</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>
        $(document).ready(function () {
            // Show success message with SweetAlert2 - immediate display
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

            // Show error message with SweetAlert2 - immediate display
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
                    scrollX: true, // Enable horizontal scrolling
                    responsive: false, // Disable responsive to prevent hiding columns
                    // Ensure Bootstrap 5 styling is applied correctly
                     "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
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

        });
    </script>
</body>
</html>
<?php
ob_end_flush(); // Flush the output buffer
?>