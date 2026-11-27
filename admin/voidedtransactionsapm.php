<?php
ob_start(); // Start output buffering
session_start();
$lname = isset($_SESSION["lname"]) ? $_SESSION["lname"] : "Admin"; // Start session
date_default_timezone_set('Asia/Manila'); // Set timezone to Manila

// Include database configuration
include '../config.php';

// Check if user is logged in (optional, based on your nav.php and security setup)
if (!isset($_SESSION["username"])) {
    // Redirect to login page if not logged in
    // header("Location: ../index.php");
    // exit();
    // If login is not strictly required for this page, you can remove the above lines
    // but typically, admin/transaction pages require authentication.
}

// Fetch distinct branches for the filter dropdown
$branches = [];
$branchQuery = "SELECT DISTINCT branch FROM void ORDER BY branch ASC"; // Order branches alphabetically
$branchResult = mysqli_query($conn, $branchQuery);
if ($branchResult) {
    while ($branchRow = mysqli_fetch_assoc($branchResult)) {
        $branches[] = $branchRow['branch'];
    }
    mysqli_free_result($branchResult); // Free result set
} else {
     // Handle error fetching branches
     $errorMessage = isset($errorMessage) ? $errorMessage . " Error fetching branches: " . mysqli_error($conn) : "Error fetching branches: " . mysqli_error($conn);
}

// Don't fetch all data initially - will be loaded via AJAX
// $conn->close(); // Keep connection open for AJAX requests

// Removed the code that calculates $totalRent, $totalRentBal, etc.
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>LCLopez Resources - Void Transactions <?php echo !empty($branch) ? '- ' . strtoupper($branch) : ''; ?></title>
    
    <!-- SB Admin 2 / Modern Dashboard Styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/modern-dashboard.css?v=<?= time() ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <style>
        /* Datepicker Overrides (Matches Collection Page) */
        .ui-datepicker { z-index: 10000 !important; font-family: 'Inter', sans-serif; box-shadow: var(--shadow-hover); border-radius: var(--radius-md); border: 1px solid var(--border-color); }
        .ui-datepicker-header { background: var(--card-bg); border: none; border-bottom: 1px solid var(--border-color); font-weight: 600; }
        .ui-datepicker-title { color: var(--text-primary); }

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

            <li class="nav-item">
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

            <li class="nav-item active">
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
        <!-- Single-Line Hero + Inline KPI Pills -->
        <div class="page-hero anim-fade-up">
            <div class="page-hero-left">
                <div class="page-hero-icon" style="background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); box-shadow: 0 4px 14px -2px rgba(244,63,94,0.34);">
                    <i class="fas fa-ban"></i>
                </div>
                <div>
                    <h1 class="page-hero-title">Voided Transactions</h1>
                    <p class="page-hero-subtitle">Audit trail of APM voided receipts &amp; reversal notes.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                <div class="kpi-card" style="padding: 0.42rem 0.78rem; gap: 0.55rem; border-radius: 10px;">
                    <div class="kpi-icon-wrap kpi-icon-rose" style="width: 30px; height: 30px; font-size: 0.8rem;"><i class="fas fa-ban"></i></div>
                    <div class="kpi-body">
                        <div class="kpi-label" style="font-size: 0.58rem;">Voided</div>
                        <div class="kpi-value" id="voidKpiCount" style="font-size: 0.96rem;">0</div>
                    </div>
                </div>
                <div class="kpi-card" style="padding: 0.42rem 0.78rem; gap: 0.55rem; border-radius: 10px;">
                    <div class="kpi-icon-wrap kpi-icon-amber" style="width: 30px; height: 30px; font-size: 0.8rem;"><i class="fas fa-receipt"></i></div>
                    <div class="kpi-body">
                        <div class="kpi-label" style="font-size: 0.58rem;">Paid Rent</div>
                        <div class="kpi-value" id="voidKpiPaidRent" style="font-size: 0.96rem;">₱0.00</div>
                    </div>
                </div>
                <div class="kpi-card" style="padding: 0.42rem 0.78rem; gap: 0.55rem; border-radius: 10px;">
                    <div class="kpi-icon-wrap kpi-icon-indigo" style="width: 30px; height: 30px; font-size: 0.8rem;"><i class="fas fa-balance-scale-left"></i></div>
                    <div class="kpi-body">
                        <div class="kpi-label" style="font-size: 0.58rem;">Paid Bal</div>
                        <div class="kpi-value" id="voidKpiPaidBal" style="font-size: 0.96rem;">₱0.00</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4 interactive-card anim-fade-up" style="border:none; --delay: 60ms;">
            <div class="card-body p-3">
                 <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                     <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorMessage">
                         <?= htmlspecialchars($errorMessage) ?>
                         <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                             <span aria-hidden="true">&times;</span>
                         </button>
                     </div>
                 <?php endif; ?>

                <!-- Single-Line Filters + Quick Date Dropdown -->
                <div class="filter-bar mb-3">
                    <div class="filter-pill" onclick="document.getElementById('from').focus();" style="cursor:pointer;">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="d-flex flex-column">
                            <label for="from" class="mb-0 filter-label">From</label>
                            <input type="text" id="from" class="datepicker border-0 p-0" readonly style="width: 78px;" />
                        </div>
                    </div>
                    <div class="filter-pill" onclick="document.getElementById('to').focus();" style="cursor:pointer;">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="d-flex flex-column">
                            <label for="to" class="mb-0 filter-label">To</label>
                            <input type="text" id="to" class="datepicker border-0 p-0" readonly style="width: 78px;" />
                        </div>
                    </div>
                    <div class="filter-pill" style="background: #f1f5f9; opacity: 0.85; cursor: not-allowed;">
                        <i class="fas fa-building"></i>
                        <div class="d-flex flex-column">
                            <label for="branch" class="mb-0 filter-label">Branch</label>
                            <select id="branch" disabled style="cursor: not-allowed; pointer-events: none; background: transparent; border: none; font-weight: 700; color: var(--text-primary);">
                                <option value="APM" selected>APM</option>
                            </select>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-compact btn-soft-indigo dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bolt"></i> Quick Date
                        </button>
                        <div class="dropdown-menu shadow">
                            <a class="dropdown-item void-preset active" href="#" data-preset="today"><i class="fas fa-clock mr-2 text-primary"></i>Today</a>
                            <a class="dropdown-item void-preset" href="#" data-preset="last7"><i class="fas fa-calendar-week mr-2 text-primary"></i>Last 7 Days</a>
                            <a class="dropdown-item void-preset" href="#" data-preset="month"><i class="fas fa-calendar-alt mr-2 text-primary"></i>This Month</a>
                            <a class="dropdown-item void-preset" href="#" data-preset="lastmonth"><i class="fas fa-history mr-2 text-primary"></i>Last Month</a>
                        </div>
                    </div>
                    <div class="d-flex ml-auto" style="gap: 6px;">
                        <button id="applyFilters" class="btn btn-compact btn-modern-gradient">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <button id="resetFilters" class="btn btn-compact btn-light text-secondary" style="border: 1px solid var(--border-color);">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="voidTable" class="table table-striped table-bordered dt-responsive nowrap" style="width: 100%;">
                        <thead class="bg-blue-600 text-white">
                            <tr>
                                <th></th> <!-- Responsive control column -->
                                <th>Transaction #</th>
                                <th>Branch</th>
                                <th>Space Code</th>
                                <th>Note</th>
                                <th>Void Date</th>
                                <th>Rent</th>
                                <th>Rent Balance</th>
                                <th>Arrear Balance</th>
                                <th>Paid Rent</th>
                                <th>Paid Balance</th>
                                <th>Charges</th>
                                <th>Collector</th>
                                <th>Tenant Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
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
    
    <!-- External Scripts (Injected to fix missing jQuery and DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
    // Add window load event handler to control page loader
    window.addEventListener('load', function() {
        // Hide the page loader when everything is fully loaded
        setTimeout(function() {
            $('.page-loader, #loader-overlay').fadeOut();
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

    <script>
        $(document).ready(function () {
            // Initialize jQuery UI Datepickers (matches Collection page)
            $(".datepicker").datepicker({
                dateFormat: 'yy-mm-dd'
            });

            // Set default dates to today
            const today = new Date().toISOString().split('T')[0];
            $('#from').val(today);
            $('#to').val(today);
            
            // Initialize DataTable with server-side processing
            var table = $('#voidTable').DataTable({
                responsive: {
                    details: {
                        type: 'inline',
                        target: 0 // Responsive control in its own column
                    }
                },
                columnDefs: [
                    { orderable: false, className: 'dtr-control', targets: 0 } // Responsive control column
                ],
                order: [[5, 'desc']], // Order by the Void Date column (index 5) descending
                "dom": '<"d-flex flex-wrap align-items-center justify-content-between mb-3"l f>rt<"d-flex flex-wrap align-items-center justify-content-between mt-3"i p>',
                "language": {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    },
                    "loadingRecords": "Loading...",
                    "processing": "Processing..."
                },
                "pageLength": 10,
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "server_processing_void.php",
                    "type": "POST",
                    "data": function(d) {
                        d.from_date = $('#from').val();
                        d.to_date = $('#to').val();
                        d.branch = $('#branch').val();
                    }
                },
                "columns": [
                    { "data": null, "defaultContent": "" },
                    { "data": "transaction_number", "render": function(data, type) {
                        if (type === 'display') return '<span class="font-weight-bold text-primary">#' + $('<div>').text(data || '').html() + '</span>';
                        return data;
                    }},
                    { "data": "branch", "render": function(data, type) {
                        if (type === 'display') return data ? '<span class="badge-soft-indigo">' + $('<div>').text(data.toUpperCase()).html() + '</span>' : '';
                        return data ? data.toUpperCase() : '';
                    }},
                    { "data": "spacecode" },
                    { "data": "note", "render": function(data, type) {
                        if (type === 'display') return data ? '<span class="badge-soft-rose">' + $('<div>').text(data).html() + '</span>' : '<span class="text-muted">-</span>';
                        return data;
                    }},
                    { 
                        "data": "void_date",
                        "render": function(data, type, row) {
                            if (type === 'display') {
                                if (data) {
                                    var date = new Date(data);
                                    return date.toLocaleDateString('en-US', {
                                        year: 'numeric', month: 'short', day: 'numeric',
                                        hour: '2-digit', minute: '2-digit'
                                    });
                                }
                                return '';
                            }
                            return data;
                        }
                    },
                    { "data": "rent", "render": $.fn.dataTable.render.number(',', '.', 2) },
                    { "data": "rentbal", "render": $.fn.dataTable.render.number(',', '.', 2) },
                    { "data": "runningbal", "render": $.fn.dataTable.render.number(',', '.', 2) },
                    { "data": "paidrent", "render": $.fn.dataTable.render.number(',', '.', 2) },
                    { "data": "paidbal", "render": $.fn.dataTable.render.number(',', '.', 2) },
                    { 
                        "data": "charges",
                        "render": function(data, type, row) {
                            if (type === 'display') {
                                var chargesDisplay = '';
                                if (data) {
                                    try {
                                        var decoded = JSON.parse(data);
                                        if (Array.isArray(decoded)) {
                                            chargesDisplay = decoded.join(', ');
                                        } else if (typeof decoded === 'object' && decoded !== null) {
                                            var parts = [];
                                            for (var key in decoded) {
                                                parts.push(key + ': ' + decoded[key]);
                                            }
                                            chargesDisplay = parts.join(', ');
                                        } else {
                                            chargesDisplay = decoded.toString();
                                        }
                                    } catch (e) {
                                        chargesDisplay = data;
                                    }
                                }
                                return chargesDisplay;
                            }
                            return data;
                        }
                    },
                    { "data": "collector" },
                    { "data": "tenantname" }
                ]
            });

            $('#applyFilters').on('click', function () {
                table.ajax.reload(null, false);
                if (typeof ModernUI !== 'undefined') ModernUI.toast('info', 'Filters Applied', 'Refreshing voided transactions...');
            });

            $('#resetFilters').on('click', function () {
                const today = new Date().toISOString().split('T')[0];
                $('#from').val(today);
                $('#to').val(today);
                $('#branch').val('APM');
                $('.void-preset').removeClass('active').filter('[data-preset="today"]').addClass('active');
                table.ajax.reload(null, false);
                if (typeof ModernUI !== 'undefined') ModernUI.toast('info', 'Filters Reset', 'Showing today\'s voided transactions.');
            });

            // Quick Date Presets (dropdown)
            $('.void-preset').on('click', function (e) {
                e.preventDefault();
                if (typeof ModernUI === 'undefined') return;
                $('.void-preset').removeClass('active');
                $(this).addClass('active');
                const range = ModernUI.getDatePresetRange($(this).data('preset'));
                $('#from').val(range.start);
                $('#to').val(range.end);
                table.ajax.reload(null, false);
            });

            // Update Void KPI Strip on AJAX completion
            table.on('xhr.dt', function (e, settings, json) {
                if (!json) return;
                const count = parseInt(json.recordsFiltered || json.recordsTotal || 0, 10);
                let paidRentSum = 0;
                let paidBalSum = 0;
                if (Array.isArray(json.data)) {
                    json.data.forEach(function (row) {
                        paidRentSum += parseFloat(row.paidrent) || 0;
                        paidBalSum += parseFloat(row.paidbal) || 0;
                    });
                }
                if (typeof ModernUI !== 'undefined') {
                    ModernUI.animateNumber(document.getElementById('voidKpiCount'), 0, count, 600, '', '', 0);
                    ModernUI.animateNumber(document.getElementById('voidKpiPaidRent'), 0, paidRentSum, 600, '₱', '', 2);
                    ModernUI.animateNumber(document.getElementById('voidKpiPaidBal'), 0, paidBalSum, 600, '₱', '', 2);
                } else {
                    $('#voidKpiCount').text(count);
                }
            });

            setTimeout(function() {
                $('#errorMessage').fadeOut('slow', function() { $(this).remove(); });
            }, 5000);
            
            $('#loader-overlay').hide();

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
<script src="js/modern-ui.js?v=<?= time() ?>"></script>
</body>
</html>
