<?php
ob_start();
include '../config.php';
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

$lname = isset($_SESSION["lname"]) ? $_SESSION["lname"] : "Admin";
date_default_timezone_set('Asia/Manila');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$selectedBranch = "APM";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Statement of Account</title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/modern-dashboard.css" rel="stylesheet">
    
    <style>
        .soa-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            border: 1px solid #e5e7eb;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
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
        
        /* Custom Dropdown list for tenants */
        #tenantDropdown {
            position: absolute;
            width: 100%;
            z-index: 1000;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-hover);
            max-height: 250px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        }
        
        #tenantDropdown option {
            padding: 10px 16px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 0.9rem;
            color: #334155;
            display: block;
        }
        #tenantDropdown option:hover {
            background-color: #f8fafc;
            color: var(--accent-indigo);
            font-weight: 500;
        }
        #tenantDropdown option[style*="display: none"] { display: none; }
        
        /* Input group with icon */
        .input-icon-wrapper {
            position: relative;
        }
        .input-icon-wrapper .icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        .input-icon-wrapper .form-control-modern {
            padding-left: 2.5rem !important;
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        
                <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

            <a class="sidebar-brand d-flex align-items-center" href="adminapm.php">
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
                <a class="nav-link" href="adminapm.php">
                    <i class="fas fa-fw fa-crown dashboard-icon"></i>
                    <span class="dashboard-text">Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Menu
            </div>

            <li class="nav-item">
                <a class="nav-link" href="collectionapm.php">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Collection</span>
                </a>
            </li>

            <li class="nav-item active">
                <a class="nav-link" href="soaapm.php">
                    <i class="fas fa-fw fa-file-invoice"></i>
                    <span>SOA</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="tenantsapm.php">
                    <i class="fas fa-fw fa-user-tie"></i>
                    <span>Tenants</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="voidedtransactionsapm.php">
                    <i class="fas fa-fw fa-ban"></i>
                    <span>Voided Transactions</span>
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
                    
                    <div class="soa-card">
                        <div class="text-center mb-5">
                            <h2 class="h3 font-weight-bold text-gray-800" style="letter-spacing: -0.5px;">Generate Statement of Account</h2>
                            <p class="text-muted text-sm mt-2">Select the branch, tenant, space code, and date range to generate the Statement of Account.</p>
                        </div>
                        
                        <form method="POST" action="soaprint.php">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-6 mb-4">
                                    <div class="form-group mb-4">
                                        <label for="branchFilter" class="font-weight-bold text-gray-700 small text-uppercase">Select Branch</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-store icon"></i>
                                            <select class="form-control form-control-modern w-100" disabled style="background-color: #e2e8f0; cursor: not-allowed;">
                                                <option value="APM" selected>APM</option>
                                            </select>
                                            <input type="hidden" id="branchFilter" name="branchFilter" value="APM">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group mb-4 position-relative">
                                        <label for="tenantFilter" class="font-weight-bold text-gray-700 small text-uppercase">Select Tenant</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-user icon"></i>
                                            <input type="text" id="tenantFilter" name="tenantFilter" placeholder="Type to search tenants..." class="form-control form-control-modern w-100" value="<?php echo isset($_POST['tenantFilter']) ? htmlspecialchars($_POST['tenantFilter']) : ''; ?>" autocomplete="off">
                                        </div>
                                        <div id="tenantDropdown"></div>
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="spacecodeFilter" class="font-weight-bold text-gray-700 small text-uppercase">Select Space Code</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-key icon"></i>
                                            <select id="spacecodeFilter" name="spacecodeFilter" class="form-control form-control-modern w-100">
                                                <option value="">Select Space Code</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Column -->
                                <div class="col-md-6 mb-4">
                                    <div class="form-group mb-4">
                                        <label for="fromDateFilter" class="font-weight-bold text-gray-700 small text-uppercase">From Date</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-calendar-alt icon"></i>
                                            <input type="date" id="fromDateFilter" name="fromDateFilter" class="form-control form-control-modern w-100" value="<?php echo isset($_POST['fromDateFilter']) ? htmlspecialchars($_POST['fromDateFilter']) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group mb-4">
                                        <label for="toDateFilter" class="font-weight-bold text-gray-700 small text-uppercase">To Date</label>
                                        <div class="input-icon-wrapper">
                                            <i class="fas fa-calendar-alt icon"></i>
                                            <input type="date" id="toDateFilter" name="toDateFilter" class="form-control form-control-modern w-100" value="<?php echo isset($_POST['toDateFilter']) ? htmlspecialchars($_POST['toDateFilter']) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="mt-2 mb-4 border-gray-200">
                            
                            <button type="submit" class="btn btn-primary btn-block font-weight-bold py-3 shadow-sm d-flex justify-content-center align-items-center" style="background-color: var(--accent-indigo); border-color: var(--accent-indigo); border-radius: var(--radius-md); font-size: 1.05rem; transition: transform 0.2s;">
                                <i class="fas fa-file-invoice mr-2"></i> Generate Statement
                            </button>
                        </form>
                    </div>

                </div>
            </div>
            
            <!-- Footer -->
            <footer class="sticky-footer bg-white mt-4 shadow-sm border-top">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto text-gray-500 font-weight-500">
                        <span>Copyright &copy; IT Department <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    

    <!-- MODALS (Logout, Profile, Settings) -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="../logout.php">Logout</a> </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Edit Profile</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="mb-4">
                                    <img id="profilePreview" class="img-profile rounded-circle" 
                                        src="<?php echo isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo']) ? 
                                            htmlspecialchars($_SESSION['profile_photo']) : 
                                            'img/undraw_profile.svg'; ?>" 
                                        style="width: 200px; height: 200px; object-fit: cover;">
                                    <div class="mt-3">
                                        <label class="btn btn-primary btn-sm">
                                            <i class="fas fa-upload"></i> Upload Photo
                                            <input type="file" name="profile_photo" id="profilePhotoInput" 
                                                accept="image/*" style="display: none;" onchange="previewImage(this)">
                                        </label>
                                    </div>
                                </div>
                                <div class="text-muted small">
                                    <p>Recommended: Square image<br>Max size: 5MB</p>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">
                                            <i class="fas fa-user"></i> Basic Info
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                                            <i class="fas fa-lock"></i> Security
                                        </a>
                                    </li>
                                </ul>
                                <div class="tab-content mt-3" id="profileTabContent">
                                    <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>First Name</label>
                                                    <input type="text" class="form-control" name="fname" 
                                                        value="<?php echo isset($_SESSION['fname']) ? htmlspecialchars($_SESSION['fname']) : ''; ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Last Name</label>
                                                    <input type="text" class="form-control" name="lname" 
                                                        value="<?php echo isset($_SESSION['lname']) ? htmlspecialchars($_SESSION['lname']) : ''; ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Username</label>
                                            <input type="text" class="form-control" name="username" 
                                                value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Branch</label>
                                            <input type="text" class="form-control" name="branch" 
                                                value="<?php echo isset($_SESSION['branch']) ? htmlspecialchars($_SESSION['branch']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="security" role="tabpanel">
                                        <div class="form-group">
                                            <label>Current Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="current_password">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>New Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="new_password">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">
                                                Password must be at least 8 characters long and contain uppercase, lowercase, and numbers
                                            </small>
                                        </div>
                                        <div class="form-group">
                                            <label>Confirm New Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="confirm_password">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">Settings</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="settings-sections">
                        <!-- Appearance Section -->
                        <h6 class="settings-section-title mb-3">
                            <i class="fas fa-paint-brush mr-2"></i> Appearance
                        </h6>
                        <div class="card mb-4">
                            <div class="card-body">
                                <!-- Dark Mode Toggle -->
                                <div class="form-group mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="darkModeToggle">
                                        <label class="custom-control-label" for="darkModeToggle">Dark Mode</label>
                                    </div>
                                    <small class="form-text text-muted">Switch between light and dark theme</small>
                                </div>
                                
                                <!-- Font Size Setting -->
                                <div class="form-group mb-3">
                                    <label for="fontSize">Font Size</label>
                                    <select class="form-control" id="fontSize">
                                        <option value="small">Small</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="large">Large</option>
                                    </select>
                                    <small class="form-text text-muted">Change the text size across the application</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notification Settings -->
                        <h6 class="settings-section-title mb-3">
                            <i class="fas fa-bell mr-2"></i> Notifications
                        </h6>
                        <div class="card">
                            <div class="card-body">
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="enableNotifications" checked>
                                        <label class="custom-control-label" for="enableNotifications">Enable Notifications</label>
                                    </div>
                                    <small class="form-text text-muted">Receive alerts about new transactions</small>
                                </div>
                                
                                <div class="form-group mb-0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="notificationSound" checked>
                                        <label class="custom-control-label" for="notificationSound">Notification Sound</label>
                                    </div>
                                    <small class="form-text text-muted">Play sound for new notifications</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveSettings">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this before the closing body tag -->
    <script>
    // Add window load event handler to control page loader
    window.addEventListener('load', function() {
        // Hide the page loader when everything is fully loaded
        setTimeout(function() {
            .page-loader, #loader-overlay.fadeOut();
        }, 500);
    });
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            // Check file size
            if (input.files[0].size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                input.value = '';
                return;
            }

            // Check file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(input.files[0].type)) {
                alert('Only JPG, PNG & GIF files are allowed');
                input.value = '';
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function togglePassword(button) {
        const input = button.closest('.input-group').querySelector('input');
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
    </script>
    <!-- END MODALS -->

<!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>

    <script>
        $(document).ready(function () {
            // Function to fetch tenants based on the selected branch
            function fetchTenants(branch) {
                if (!branch) {
                     $('#tenantDropdown').html(''); 
                     $('#tenantDropdown').hide();
                     $('#tenantFilter').val(''); 
                     $('#spacecodeFilter').html('<option value="">Select Space Code</option>');
                    return;
                }
                $.ajax({
                    url: '../fetch_tenants.php',
                    method: 'GET',
                    data: { branch: branch },
                    success: function (data) {
                        $('#tenantDropdown').html(data);
                        if (data.trim() !== '') {
                             $('#tenantDropdown').show();
                        } else {
                             $('#tenantDropdown').hide();
                        }
                    },
                    error: function () {
                         $('#tenantDropdown').html('<option value="">Error loading tenants</option>');
                         $('#tenantDropdown').show();
                    }
                });
            }

            // Function to fetch space codes based on the selected branch and tenant
            function fetchSpaceCodes(branch, tenant) {
                 if (!branch || !tenant) {
                     $('#spacecodeFilter').html('<option value="">Select Space Code</option>');
                     return;
                 }
                $.ajax({
                    url: '../fetch_spacecodes.php', 
                    method: 'GET',
                    data: { branch: branch, tenant: tenant },
                    success: function (data) {
                        $('#spacecodeFilter').html('<option value="">Select Space Code</option>' + data);
                    },
                    error: function () {
                         $('#spacecodeFilter').html('<option value="">Error loading space codes</option>');
                    }
                });
            }

            // Event listener for Branch filter change
            $('#branchFilter').on('change', function () {
                const branch = $(this).val();
                $('#tenantFilter').val(''); 
                $('#tenantDropdown').hide(); 
                $('#spacecodeFilter').html('<option value="">Select Space Code</option>'); 
                if (branch) {
                    fetchTenants(branch);
                }
            });

            // Event listener for Tenant input focus
             $('#tenantFilter').on('focus', function () {
                 const branch = $('#branchFilter').val();
                 if (branch) {
                     const currentDropdownContent = $('#tenantDropdown').html().trim();
                     if (currentDropdownContent !== '' && $('#tenantDropdown option').length > 0) {
                          $('#tenantDropdown').show();
                     } else {
                         fetchTenants(branch); 
                     }
                 } else {
                      $('#tenantDropdown').html('<option value="">Select branch first</option>');
                      $('#tenantDropdown').show();
                 }
             });

            // Event listener for Tenant input typing (filtering dropdown)
            $('#tenantFilter').on('input', function () {
                const searchTerm = $(this).val().toLowerCase();
                $('#tenantDropdown option').each(function () {
                    const tenantName = $(this).text().toLowerCase();
                    if (tenantName.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
                 const branch = $('#branchFilter').val();
                if (branch && searchTerm.length === 0) {
                     fetchTenants(branch);
                } else if (searchTerm.length > 0) {
                     $('#tenantDropdown').show(); 
                }
            });

            // Event listener for clicking an option in the Tenant dropdown
             $('#tenantDropdown').on('click', 'option', function () {
                 const selectedTenant = $(this).text();
                 $('#tenantFilter').val(selectedTenant);
                 $('#tenantDropdown').hide(); 
                 const branch = $('#branchFilter').val();
                 if (branch && selectedTenant) {
                     fetchSpaceCodes(branch, selectedTenant);
                 } else {
                      $('#spacecodeFilter').html('<option value="">Select branch and tenant first</option>');
                 }
             });

            // Event listener to hide dropdown when clicking outside
            $(document).on('click', function (e) {
                 if (!$(e.target).closest('#tenantDropdown').length && !$(e.target).is('#tenantFilter')) {
                     $('#tenantDropdown').hide();
                 }
             });

             const initialBranch = $('#branchFilter').val();
             const initialTenant = $('#tenantFilter').val();
             const initialSpaceCode = "<?php echo isset($_POST['spacecodeFilter']) ? htmlspecialchars($_POST['spacecodeFilter']) : ''; ?>"; 

             if (initialBranch) {
                 fetchTenants(initialBranch); 
             }

             function setInitialSelections() {
                  const branchSelected = $('#branchFilter').val();
                  const tenantInputVal = $('#tenantFilter').val();

                  if (initialBranch && !$('#tenantDropdown option').length) {
                       setTimeout(setInitialSelections, 50); 
                       return;
                  }

                 if (initialTenant && branchSelected === initialBranch) {
                      const tenantOption = $('#tenantDropdown option').filter(function() {
                           return $(this).text() === initialTenant;
                      });

                      if (tenantOption.length > 0) {
                          tenantOption.first().trigger('click');
                      } else {
                          $('#tenantFilter').val('');
                          $('#spacecodeFilter').html('<option value="">Select Space Code</option>');
                      }
                 }

                 if (initialSpaceCode) {
                     const checkSpaceCodeInterval = setInterval(function() {
                         if ($('#spacecodeFilter option[value="' + initialSpaceCode + '"]').length > 0) {
                             $('#spacecodeFilter').val(initialSpaceCode);
                             clearInterval(checkSpaceCodeInterval); 
                         }
                     }, 100); 
                 }
             }

             setInitialSelections();
             
             // Simple button hover effect
             $('button[type="submit"]').hover(
                 function() { $(this).css('transform', 'translateY(-2px)'); $(this).addClass('shadow-lg'); },
                 function() { $(this).css('transform', 'translateY(0)'); $(this).removeClass('shadow-lg'); }
             );

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
