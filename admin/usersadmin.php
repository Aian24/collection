<?php
ob_start(); // Ensure output buffering is on
include '../config.php'; // Assuming this connects to your database ($conn)
session_start();
$lname = isset($_SESSION["lname"]) ? $_SESSION["lname"] : "Admin";

// Check if user is logged in - Redirect if not
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php"); // Adjust redirect path if needed
    exit();
}

// Initialize message variables (will be populated from $_SESSION after redirect)
$success_message = '';
$error_message = '';

// Fetch branches for dropdowns
$branches_query = "SELECT branch_name, branch_code FROM branches ORDER BY branch_name";
$branches_result = $conn->query($branches_query);
$branches = [];
if ($branches_result) {
    while ($branch = $branches_result->fetch_assoc()) {
        $branches[] = $branch;
    }
}

// Fetch roles for dropdowns
$roles_query = "SELECT role_name, role_code FROM user_roles ORDER BY role_name";
$roles_result = $conn->query($roles_query);
$roles = [];
if ($roles_result) {
    while ($role = $roles_result->fetch_assoc()) {
        $roles[] = $role;
    }
}

// --- Handle POST Requests (Create, Edit, Delete) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- CREATE USER ---
    if (isset($_POST['create'])) {
        $username = trim($_POST['username']);
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $branch = trim($_POST['branch']);
        $user_type = trim($_POST['user_type']);
        $password = $_POST['password']; // Get plain password

        // Basic validation
        if (empty($username) || empty($fname) || empty($lname) || empty($email) || empty($branch) || empty($user_type) || empty($password)) {
            $_SESSION['error_message'] = "All fields are required!";
        } else {
            // Check if the username or email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $_SESSION['error_message'] = "Username or Email already exists!";
            } else {
                // Proceed with the user creation
                // Using prepared statements with plain password (INSECURE)
                $insert_stmt = $conn->prepare("INSERT INTO users (user_type, username, fname, lname, email, branch, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                // 'sssssss' indicates all parameters are strings
                // --- SECURITY RISK: STORING PLAIN TEXT PASSWORD ---
                $insert_stmt->bind_param("sssssss", $user_type, $username, $fname, $lname, $email, $branch, $password); // Changed to use $password directly

                if ($insert_stmt->execute()) {
                    $_SESSION['success_message'] = "User created successfully!";
                } else {
                    $_SESSION['error_message'] = "Error creating user: " . $conn->error; // Consider logging detailed error, showing generic to user
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        } // End basic validation

        // Redirect to prevent form resubmission on refresh
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // --- EDIT USER ---
    if (isset($_POST['edit'])) {
        $id = trim($_POST['id']);
        $username = trim($_POST['username']);
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $branch = trim($_POST['branch']);
        $user_type = trim($_POST['user_type']);
        $password = $_POST['password']; // New password (can be empty)

        // Basic validation (adjust as needed, ID must exist)
        if (empty($id) || empty($username) || empty($fname) || empty($lname) || empty($email) || empty($branch) || empty($user_type)) {
            $_SESSION['error_message'] = "All fields except password are required!";
        } else {
            // Check if the username or email already exists for other users
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check_stmt->bind_param("ssi", $username, $email, $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $_SESSION['error_message'] = "Username or Email already exists for another user!";
                $check_stmt->close();
            } else {
                $check_stmt->close();
                // Start building the query dynamically
            $query = "UPDATE users SET username=?, fname=?, lname=?, email=?, branch=?, user_type=?";
            $params = [$username, $fname, $lname, $email, $branch, $user_type];
            $param_types = "ssssss";

            // Only update password if a new one is provided (INSECURE)
            if (!empty($password)) {
                 // --- SECURITY RISK: STORING PLAIN TEXT PASSWORD ---
                $query .= ", password=?";
                $params[] = $password; // Changed to use $password directly
                $param_types .= "s";
            }

            // Add the WHERE clause
            $query .= " WHERE id=?";
            $params[] = $id;
            $param_types .= "i"; // 'i' for integer type (assuming id is INT)

            $stmt = $conn->prepare($query);
            // Use ... to unpack array into bind_param arguments
            $stmt->bind_param($param_types, ...$params);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "User updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating user: " . $conn->error; // Log error
            }
            $stmt->close();
            } // End duplicate check
        } // End basic validation

        // Redirect after POST
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // --- DELETE USER ---
    if (isset($_POST['delete'])) {
        $id = trim($_POST['id']); // Sanitize input

        // Basic validation
        if (empty($id)) {
            $_SESSION['error_message'] = "User ID is missing for deletion!";
        } else {
            // Check if user exists before deletion
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                $_SESSION['error_message'] = "User not found!";
            } else {
                $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_stmt->bind_param("i", $id);

                if ($delete_stmt->execute()) {
                    $_SESSION['success_message'] = "User deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting user: " . $conn->error;
                }
                $delete_stmt->close();
            }
            $check_stmt->close();
        }

        // Redirect after POST
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// --- Display Session Messages ---
// Check for session messages populated by the POST handler
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


// --- Fetch users for the table ---
// Using prepared statement for fetching (though not strictly necessary here, good practice)
$sql = "SELECT id, user_type, username, fname, lname, email, branch FROM users"; // Do NOT select the password!
$result = $conn->query($sql);

// IMPORTANT: Close the database connection when done (assuming $conn is from config.php)
// $conn->close(); // Uncomment if your config.php doesn't close it automatically or you prefer closing here

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>LCLopez Resources - User Management <?php echo !empty($branch) ? '- ' . strtoupper($branch) : ''; ?></title>
    
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
            <li class="nav-item">
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
            <li class="nav-item active">
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
            <div class="card-header py-2 bg-white" style="border-bottom: 1px solid #e5e7eb;">
                <h1 class="h6 font-weight-bold text-gray-800 mb-0" style="margin-top: 4px; margin-bottom: 4px !important;"><i class="fas fa-users mr-2"></i> User Management</h1>
            </div>

            <div class="card-body">


        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-times-circle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap mb-4" style="gap: 12px;">
            <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-indigo); border-color: var(--accent-indigo); font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);" data-toggle="modal" data-target="#createModal">
                <i class="fas fa-user-plus mr-2"></i> Add New User
            </button>
            <a href="branches.php" class="btn btn-success shadow-sm" style="background-color: #10b981; border-color: #10b981; font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);">
                <i class="fas fa-building mr-2"></i> Manage Branches
            </a>
            <a href="roles.php" class="btn btn-info shadow-sm text-white" style="background-color: #0ea5e9; border-color: #0ea5e9; font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);">
                <i class="fas fa-user-tag mr-2"></i> Manage Roles
            </a>
        </div>

        <div class="table-responsive">
             <table id="userTable" class="table table-striped table-bordered dt-responsive nowrap" style="width: 100%;">
                 <thead class="bg-blue-600 text-white" style="background-color: #1e293b !important;">
                     <tr>
                         <th>Username</th>
                         <th>First Name</th>
                         <th>Last Name</th>
                         <th>Email</th>
                         <th>Branch</th>
                         <th>User Type</th>
                         <th class="text-center">Actions</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php
                     if ($result && $result->num_rows > 0) {
                         while ($row = $result->fetch_assoc()):
                     ?>
                           <tr>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['fname']) ?></td>
                                <td><?= htmlspecialchars($row['lname']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['branch']) ?></td>
                                <td><?= htmlspecialchars($row['user_type']) ?></td>
                                <td class="text-center action-buttons">
                                    <button class="btn btn-warning btn-sm edit-btn"
                                             data-id="<?= $row['id'] ?>"
                                             data-username="<?= htmlspecialchars($row['username']) ?>"
                                             data-fname="<?= htmlspecialchars($row['fname']) ?>"
                                             data-lname="<?= htmlspecialchars($row['lname']) ?>"
                                             data-email="<?= htmlspecialchars($row['email']) ?>"
                                             data-branch="<?= htmlspecialchars($row['branch']) ?>"
                                             data-user-type="<?= htmlspecialchars($row['user_type']) ?>"
                                             data-toggle="modal" data-target="#editModal"
                                             title="Edit User">
                                         <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-btn"
                                             data-id="<?= htmlspecialchars($row['id']) ?>"
                                             data-username="<?= htmlspecialchars($row['username']) ?>"
                                             data-toggle="modal" 
                                             data-target="#deleteModal"
                                             title="Delete User">
                                         <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                           </tr>
                     <?php
                         endwhile;
                     } else {
                         // Display a message if no users found
                         echo '<tr><td colspan="7" class="text-center">No users found.</td></tr>';
                     }
                     ?>
                 </tbody>
             </table>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createModalLabel"><i class="fas fa-user-plus mr-2"></i>Create New User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="create_username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" id="create_username" class="form-control form-control-modern" required>
                                </div>
                                <div class="mb-3">
                                    <label for="create_fname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fname" id="create_fname" class="form-control form-control-modern" required>
                                </div>
                                <div class="mb-3">
                                    <label for="create_lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="lname" id="create_lname" class="form-control form-control-modern" required>
                                </div>
                                <div class="mb-3">
                                    <label for="create_email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="create_email" class="form-control form-control-modern" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="create_branch" class="form-label">Branch <span class="text-danger">*</span></label>
                                    <select name="branch" id="create_branch" class="form-control form-control-modern" required>
                                        <option value="" disabled selected>Select Branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?= htmlspecialchars($branch['branch_code']) ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="create_user_type" class="form-label">User Type <span class="text-danger">*</span></label>
                                    <select name="user_type" id="create_user_type" class="form-control form-control-modern" required>
                                        <option value="" disabled selected>Select User Type</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= htmlspecialchars($role['role_code']) ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="create_password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" name="password" id="create_password" class="form-control form-control-modern" required style="border-right: none;">
                                        <div class="input-group-append">
                                            <span class="input-group-text toggle-password" data-target="#create_password" style="background: white; border-left: none; cursor: pointer; border-color: #e5e7eb; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; height: 100%;">
                                                <i class="fas fa-eye text-secondary"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cancel</button>
                        <button type="submit" name="create" class="btn btn-primary shadow-sm" style="background-color: var(--accent-indigo); border-color: var(--accent-indigo); font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);"><i class="fas fa-save mr-1"></i> Create User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit mr-2"></i>Edit User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                                        <div class="modal-body">
                        <input type="hidden" name="id" id="editUserId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editUsername" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" id="editUsername" class="form-control form-control-modern" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editFname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fname" id="editFname" class="form-control form-control-modern" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editLname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="lname" id="editLname" class="form-control form-control-modern" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="editEmail" class="form-control form-control-modern" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editBranch" class="form-label">Branch <span class="text-danger">*</span></label>
                                    <select name="branch" id="editBranch" class="form-control form-control-modern" required>
                                        <option value="" disabled>Select Branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?= htmlspecialchars($branch['branch_code']) ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="editUserType" class="form-label">User Type <span class="text-danger">*</span></label>
                                    <select name="user_type" id="editUserType" class="form-control form-control-modern" required>
                                        <option value="" disabled>Select User Type</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= htmlspecialchars($role['role_code']) ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="editPassword" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" name="password" id="editPassword" class="form-control form-control-modern" style="border-right: none;">
                                        <div class="input-group-append">
                                            <span class="input-group-text toggle-password" data-target="#editPassword" style="background: white; border-left: none; cursor: pointer; border-color: #e5e7eb; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; height: 100%;">
                                                <i class="fas fa-eye text-secondary"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Leave blank to keep the existing password.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cancel</button>
                        <button type="submit" name="edit" class="btn btn-primary shadow-sm" style="background-color: var(--accent-indigo); border-color: var(--accent-indigo); font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);"><i class="fas fa-save mr-1"></i> Update User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="deleteUserForm">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-trash-alt mr-2"></i>Delete User</h5>
                        <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="deleteUserId">
                        <p>Are you sure you want to delete this user: <strong><span id="deleteUserName"></span></strong>?</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                        <button type="submit" name="delete" class="btn btn-danger">
                            <i class="fas fa-trash-alt mr-1"></i> Delete User
                        </button>
                    </div>
                </div>
            </form>
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

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    
    <!-- DataTables & Plugins -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#userTable').DataTable({
                "pagingType": "full_numbers",
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Filter records:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)"
                },
            
                "dom": '<"d-flex flex-wrap align-items-center justify-content-between mb-3"l f>rt<"d-flex flex-wrap align-items-center justify-content-between mt-3"i p>'
            });

            // Hide page loader if any
            $("#loader-overlay").hide();

            // Toggle Password Visibility
            $('.toggle-password').click(function() {
                var targetId = $(this).data('target');
                var $input = $(targetId);
                var $icon = $(this).find('i');
                
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            // Store current edit data globally
            var currentEditData = {};

            // Handle Edit Button Click
            $(document).on('click', '.edit-btn', function() {
                var $btn = $(this);
                
                // Store the data globally
                currentEditData = {
                    id: $btn.data('id'),
                    username: $btn.data('username'),
                    fname: $btn.data('fname'),
                    lname: $btn.data('lname'),
                    email: $btn.data('email'),
                    branch: $btn.data('branch'),
                    userType: $btn.data('user-type')
                };
                
                console.log('Edit User Data stored:', currentEditData);
            });

            // Handle modal shown event - populate form when modal is fully rendered
            $('#editModal').on('shown.bs.modal', function() {
                console.log('Modal shown event triggered');
                
                if (Object.keys(currentEditData).length === 0) return;
                
                // Populate all fields
                $('#editUserId').val(currentEditData.id);
                $('#editUsername').val(currentEditData.username);
                $('#editFname').val(currentEditData.fname);
                $('#editLname').val(currentEditData.lname);
                $('#editEmail').val(currentEditData.email);
                $('#editPassword').val(''); // Clear password field
                
                // Normalize branch value: convert to lowercase and replace spaces with underscores
                var normalizedBranch = currentEditData.branch.toLowerCase().replace(/ /g, '_');
                
                // Try to set the branch with normalized value first
                $('#editBranch').val(normalizedBranch);
                
                // If that didn't work (value is empty), try the original value
                if ($('#editBranch').val() === null || $('#editBranch').val() === '') {
                    $('#editBranch').val(currentEditData.branch);
                }
                
                // Normalize user type as well
                var normalizedUserType = currentEditData.userType.toLowerCase().replace(/ /g, '_');
                $('#editUserType').val(normalizedUserType);
                
                // If that didn't work, try the original value
                if ($('#editUserType').val() === null || $('#editUserType').val() === '') {
                    $('#editUserType').val(currentEditData.userType);
                }
                
                // Verify the values were set
                console.log('Form values set:', {
                    id: $('#editUserId').val(),
                    username: $('#editUsername').val(),
                    fname: $('#editFname').val(),
                    lname: $('#editLname').val(),
                    email: $('#editEmail').val(),
                    branch: $('#editBranch').val(),
                    originalBranch: currentEditData.branch,
                    normalizedBranch: normalizedBranch,
                    userType: $('#editUserType').val()
                });
                
                // Log available branch options
                console.log('Available branch options:', $('#editBranch option').map(function() {
                    return $(this).val() + ': ' + $(this).text();
                }).get());
            });

            // Handle Delete Button Click
            $(document).on('click', '.delete-btn', function() {
                var userId = $(this).data('id');
                var username = $(this).data('username');
                console.log('Delete User ID:', userId); // Add logging for debugging
                $('#deleteUserId').val(userId);
                $('#deleteUserName').text(username);
                $('#deleteModal').modal('show');
            });

            // Add form submission handling for edit
            $('#editModal form').on('submit', function(e) {
                var userId = $('#editUserId').val();
                var username = $('#editUsername').val();
                var fname = $('#editFname').val();
                var lname = $('#editLname').val();
                var email = $('#editEmail').val();
                var branch = $('#editBranch').val();
                var userType = $('#editUserType').val();
                
                console.log('Edit form submission data:', {
                    id: userId,
                    username: username,
                    fname: fname,
                    lname: lname,
                    email: email,
                    branch: branch,
                    userType: userType
                });
                
                if (!userId || !username || !fname || !lname || !email || !branch || !userType) {
                    e.preventDefault();
                    alert('Error: All fields except password are required. Please fill in all required fields.');
                    return false;
                }
            });

            // Add form submission handling for delete
            $('#deleteUserForm').on('submit', function(e) {
                if (!$('#deleteUserId').val()) {
                    e.preventDefault();
                    alert('Error: User ID is missing. Please try again.');
                }
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
<?php include 'notification_script.php'; ?>
</body>
</html>