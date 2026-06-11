<?php
ob_start();
include '../config.php';

session_start();
$lname = isset($_SESSION["lname"]) ? $_SESSION["lname"] : "Admin";

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

// Initialize message variables
$success_message = '';
$error_message = '';

// Handle POST Requests (Create, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CREATE ROLE
    if (isset($_POST['create'])) {
        $role_name = trim($_POST['role_name']);
        $role_code = trim($_POST['role_code']);
        $description = trim($_POST['description']);

        if (empty($role_name) || empty($role_code)) {
            $_SESSION['error_message'] = "Role name and code are required!";
        } else {
            // Check if role already exists
            $check_stmt = $conn->prepare("SELECT id FROM user_roles WHERE role_name = ? OR role_code = ?");
            $check_stmt->bind_param("ss", $role_name, $role_code);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $_SESSION['error_message'] = "Role name or code already exists!";
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO user_roles (role_name, role_code, description) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("sss", $role_name, $role_code, $description);

                if ($insert_stmt->execute()) {
                    $_SESSION['success_message'] = "Role created successfully!";
                } else {
                    $_SESSION['error_message'] = "Error creating role: " . $conn->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // EDIT ROLE
    if (isset($_POST['edit'])) {
        $id = trim($_POST['id']);
        $role_name = trim($_POST['role_name']);
        $role_code = trim($_POST['role_code']);
        $description = trim($_POST['description']);

        if (empty($id) || empty($role_name) || empty($role_code)) {
            $_SESSION['error_message'] = "Role name and code are required!";
        } else {
            $update_stmt = $conn->prepare("UPDATE user_roles SET role_name=?, role_code=?, description=? WHERE id=?");
            $update_stmt->bind_param("sssi", $role_name, $role_code, $description, $id);

            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Role updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating role: " . $conn->error;
            }
            $update_stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // DELETE ROLE
    if (isset($_POST['delete'])) {
        $id = trim($_POST['id']);

        if (empty($id)) {
            $_SESSION['error_message'] = "Role ID is missing for deletion!";
        } else {
            $delete_stmt = $conn->prepare("DELETE FROM user_roles WHERE id=?");
            $delete_stmt->bind_param("i", $id);

            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Role deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting role: " . $conn->error;
            }
            $delete_stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fetch roles for the table
$sql = "SELECT id, role_name, role_code, description, created_at FROM user_roles ORDER BY role_name";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>LCLopez Resources - Role Management <?php echo !empty($branch) ? '- ' . strtoupper($branch) : ''; ?></title>
    
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
            <div class="card-header py-2 bg-white" style="border-bottom: 1px solid #e5e7eb;">
                <h1 class="h6 font-weight-bold text-gray-800 mb-0" style="margin-top: 4px; margin-bottom: 4px !important;"><i class="fas fa-user-tag mr-2"></i> Role Management</h1>
            </div>
            <div class="card-body">


        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-times-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap mb-4" style="gap: 12px;">
            <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-indigo); border-color: var(--accent-indigo); font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);" data-toggle="modal" data-target="#createModal">
                <i class="fas fa-plus-circle mr-2"></i>Add New Role
            </button>
            <a href="usersadmin.php" class="btn btn-success shadow-sm" style="background-color: #10b981; border-color: #10b981; font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);">
                <i class="fas fa-user-plus mr-2"></i>Create User
            </a>
        </div>

        <div class="table-responsive">
            <table id="rolesTable" class="table table-striped table-bordered dt-responsive nowrap" style="width: 100%;">
                <thead class="bg-blue-600 text-white" style="background-color: #1e293b !important;">
                    <tr>
                        <th>Role Name</th>
                        <th>Role Code</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['role_name']) ?></td>
                            <td><?= htmlspecialchars($row['role_code']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm edit-btn"
                                        data-id="<?= $row['id'] ?>"
                                        data-role-name="<?= htmlspecialchars($row['role_name']) ?>"
                                        data-role-code="<?= htmlspecialchars($row['role_code']) ?>"
                                        data-description="<?= htmlspecialchars($row['description']) ?>"
                                        data-toggle="modal" data-target="#editModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm delete-btn"
                                        data-id="<?= $row['id'] ?>"
                                        data-toggle="modal" 
                                        data-target="#deleteModal">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    } else {
                        echo '<tr><td colspan="5" class="text-center">No roles found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createModalLabel">
                            <i class="fas fa-plus-circle mr-2"></i>Create New Role
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create_role_name" class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="role_name" id="create_role_name" class="form-control form-control-modern" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_role_code" class="form-label">Role Code <span class="text-danger">*</span></label>
                            <input type="text" name="role_code" id="create_role_code" class="form-control form-control-modern" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_description" class="form-label">Description</label>
                            <textarea name="description" id="create_description" class="form-control form-control-modern" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" name="create" class="btn btn-primary shadow-sm" style="background-color: var(--accent-indigo); border-color: var(--accent-indigo); font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);">
                            <i class="fas fa-save mr-2"></i>Create Role
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">
                            <i class="fas fa-edit mr-2"></i>Edit Role
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_role_name" class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="role_name" id="edit_role_name" class="form-control form-control-modern" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role_code" class="form-label">Role Code <span class="text-danger">*</span></label>
                            <input type="text" name="role_code" id="edit_role_code" class="form-control form-control-modern" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control form-control-modern" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" name="edit" class="btn btn-primary shadow-sm" style="background-color: var(--accent-indigo); border-color: var(--accent-indigo); font-weight: 600; padding: 8px 16px; border-radius: var(--radius-sm);">
                            <i class="fas fa-save mr-2"></i>Update Role
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel">
                            <i class="fas fa-trash-alt mr-2"></i>Delete Role
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="delete_id">
                        <p>Are you sure you want to delete this role? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" name="delete" class="btn btn-danger">
                            <i class="fas fa-trash-alt mr-2"></i>Delete Role
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
            $('#rolesTable').DataTable({
                "pagingType": "full_numbers",
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "order": [[0, "asc"]],
                "dom": '<"d-flex flex-wrap align-items-center justify-content-between mb-3"l f>rt<"d-flex flex-wrap align-items-center justify-content-between mt-3"i p>'
            });

            // Handle Edit Button Click
            $('.edit-btn').click(function() {
                $('#edit_id').val($(this).data('id'));
                $('#edit_role_name').val($(this).data('role-name'));
                $('#edit_role_code').val($(this).data('role-code'));
                $('#edit_description').val($(this).data('description'));
            });

            // Handle Delete Button Click
            $('.delete-btn').click(function() {
                var roleId = $(this).data('id');
                $('#delete_id').val(roleId);
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