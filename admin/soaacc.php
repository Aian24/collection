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

$selectedBranch = isset($_POST['branchFilter']) ? $_POST['branchFilter'] : "";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/modern-dashboard.css?v=<?= time() ?>" rel="stylesheet">
    
    <style>
        .soa-workspace {
            display: grid;
            grid-template-columns: 1.55fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 992px) {
            .soa-workspace { grid-template-columns: 1fr; }
        }

        .soa-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
            padding: 2rem;
            width: 100%;
            margin: 0;
        }

        /* Step Progress Bar */
        .soa-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }
        @media (max-width: 576px) {
            .soa-steps { grid-template-columns: repeat(2, 1fr); }
        }
        .soa-step {
            background: var(--card-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.7rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: var(--transition);
        }
        .soa-step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--border-color);
            color: var(--text-secondary);
            font-size: 0.74rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
        }
        .soa-step-label {
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .soa-step.active {
            border-color: var(--accent-indigo);
            background: rgba(99, 102, 241, 0.08);
        }
        .soa-step.active .soa-step-num {
            background: var(--accent-indigo);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .soa-step.done {
            border-color: rgba(16, 185, 129, 0.45);
            background: rgba(16, 185, 129, 0.07);
        }
        .soa-step.done .soa-step-num {
            background: #10b981;
            color: #fff;
        }
        .soa-step.done .soa-step-label {
            color: var(--text-primary);
        }
        
        /* Custom Dropdown list for tenants */
        #tenantDropdown {
            position: absolute;
            width: 100%;
            z-index: 1000;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-hover);
            max-height: 250px;
            overflow-y: auto;
            display: none;
            margin-top: 6px;
            animation: fadeSlideUp 0.22s var(--ease-spring) both;
        }
        
        #tenantDropdown option {
            padding: 10px 16px;
            cursor: pointer;
            transition: background-color 0.15s ease, padding-left 0.15s ease;
            font-size: 0.88rem;
            color: var(--text-primary);
            display: block;
        }
        #tenantDropdown option:hover,
        #tenantDropdown option.highlighted {
            background-color: rgba(99, 102, 241, 0.08);
            color: var(--accent-indigo);
            font-weight: 600;
            padding-left: 20px;
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
            color: var(--accent-indigo);
            pointer-events: none;
            z-index: 2;
        }
        .input-icon-wrapper .form-control-modern {
            padding-left: 2.55rem !important;
        }

        /* Live Preview Card (Light Modern Glass) */
        .soa-preview-card {
            background: linear-gradient(145deg, #ffffff 0%, #f5f7ff 100%);
            color: var(--text-primary);
            border-radius: var(--radius-lg);
            padding: 1.35rem;
            border: 1px solid #c7d2fe;
            box-shadow: var(--shadow-card);
            position: sticky;
            top: 72px;
        }
        .soa-preview-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.55rem 0;
            border-bottom: 1px dashed var(--border-color);
            font-size: 0.8rem;
        }
        .soa-preview-row:last-of-type { border-bottom: none; }
        .soa-preview-label { color: var(--text-muted); font-weight: 600; }
        .soa-preview-val { color: var(--text-primary); font-weight: 700; text-align: right; max-width: 60%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

            <a class="sidebar-brand d-flex align-items-center" href="adminacc.php">
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
                <a class="nav-link" href="adminacc.php">
                    <i class="fas fa-fw fa-crown dashboard-icon"></i>
                    <span class="dashboard-text">Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Menu
            </div>

            <li class="nav-item">
                <a class="nav-link" href="collectionacc.php">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Collection</span>
                </a>
            </li>

            <li class="nav-item active">
                <a class="nav-link" href="soaacc.php">
                    <i class="fas fa-fw fa-file-invoice"></i>
                    <span>SOA</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="tenantsacc.php">
                    <i class="fas fa-fw fa-user-tie"></i>
                    <span>Tenants</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="voidedtransactionsacc.php">
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

                    <!-- Single-Line Page Hero + Quick Period Dropdown -->
                    <div class="page-hero anim-fade-up">
                        <div class="page-hero-left">
                            <div class="page-hero-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div>
                                <h1 class="page-hero-title">Statement of Account</h1>
                                <p class="page-hero-subtitle">Interactive SOA builder with live tenant search.</p>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button type="button" class="btn btn-compact btn-soft-indigo dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-calendar-alt"></i> Quick Period
                            </button>
                            <div class="dropdown-menu dropdown-menu-right shadow">
                                <a class="dropdown-item soa-preset" href="#" data-preset="month"><i class="fas fa-calendar-alt mr-2 text-primary"></i>This Month</a>
                                <a class="dropdown-item soa-preset" href="#" data-preset="lastmonth"><i class="fas fa-history mr-2 text-primary"></i>Last Month</a>
                                <a class="dropdown-item soa-preset" href="#" data-preset="last30"><i class="fas fa-clock mr-2 text-primary"></i>Last 30 Days</a>
                                <a class="dropdown-item soa-preset" href="#" data-preset="ytd"><i class="fas fa-chart-line mr-2 text-primary"></i>Year to Date</a>
                            </div>
                        </div>
                    </div>

                    <div class="soa-workspace">
                        <div class="soa-card interactive-card anim-fade-up" style="--delay: 60ms;">
                            <!-- Step Progress Bar -->
                            <div class="soa-steps">
                                <div class="soa-step active" id="stepBranch">
                                    <span class="soa-step-num">1</span>
                                    <span class="soa-step-label">Branch</span>
                                </div>
                                <div class="soa-step" id="stepTenant">
                                    <span class="soa-step-num">2</span>
                                    <span class="soa-step-label">Tenant</span>
                                </div>
                                <div class="soa-step" id="stepSpace">
                                    <span class="soa-step-num">3</span>
                                    <span class="soa-step-label">Space</span>
                                </div>
                                <div class="soa-step" id="stepPeriod">
                                    <span class="soa-step-num">4</span>
                                    <span class="soa-step-label">Period</span>
                                </div>
                            </div>

                            <form method="POST" action="soaprint.php" id="soaForm">
                                <div class="row">
                                    <!-- Left Column -->
                                    <div class="col-md-6 mb-2">
                                        <div class="form-group mb-4">
                                            <label for="branchFilter" class="font-weight-bold text-gray-700 small text-uppercase">Select Branch</label>
                                            <div class="input-icon-wrapper">
                                                <i class="fas fa-store icon"></i>
                                                <select id="branchFilter" name="branchFilter" class="form-control form-control-modern w-100">
                                                    <option value="">Select Branch</option>
                                                    <option value="Sanko Market" <?php echo ($selectedBranch == "Sanko Market") ? "selected" : ""; ?>>Sanko Market</option>
                                                    <option value="Nova Market" <?php echo ($selectedBranch == "Nova Market") ? "selected" : ""; ?>>Nova Market</option>
                                                    <option value="APM" <?php echo ($selectedBranch == "APM") ? "selected" : ""; ?>>APM</option>
                                                </select>
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
                                    <div class="col-md-6 mb-2">
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

                                        <div class="p-3 rounded" style="background: var(--card-subtle); border: 1px dashed var(--border-color);">
                                            <div class="small font-weight-bold text-primary mb-1"><i class="fas fa-magic mr-1"></i> Quick Tip</div>
                                            <div class="small text-muted">Pick a branch first, then search by tenant name to auto-load their assigned space codes.</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="mt-2 mb-4" style="border-color: var(--border-color);">
                                
                                <button type="submit" class="btn btn-modern-gradient btn-block font-weight-bold py-3 d-flex justify-content-center align-items-center" style="font-size: 1rem;">
                                    <i class="fas fa-file-invoice mr-2"></i> Generate Statement
                                </button>
                            </form>
                        </div>

                        <!-- Live Interactive Preview Summary -->
                        <div class="soa-preview-card interactive-card anim-fade-up" style="--delay: 140ms;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge-soft-indigo">LIVE PREVIEW</span>
                                <i class="fas fa-file-alt text-primary" style="font-size: 1rem;"></i>
                            </div>
                            <h6 class="font-weight-bold mb-1" style="color: var(--text-primary);">SOA Parameters</h6>
                            <p class="small mb-3 text-muted">Verify details before generating printable statement.</p>

                            <div class="soa-preview-row">
                                <span class="soa-preview-label"><i class="fas fa-store text-primary mr-2"></i>Branch</span>
                                <span class="soa-preview-val" id="previewBranch">Not selected</span>
                            </div>
                            <div class="soa-preview-row">
                                <span class="soa-preview-label"><i class="fas fa-user text-primary mr-2"></i>Tenant</span>
                                <span class="soa-preview-val" id="previewTenant">Not selected</span>
                            </div>
                            <div class="soa-preview-row">
                                <span class="soa-preview-label"><i class="fas fa-key text-primary mr-2"></i>Space Code</span>
                                <span class="soa-preview-val" id="previewSpace">Not selected</span>
                            </div>
                            <div class="soa-preview-row">
                                <span class="soa-preview-label"><i class="fas fa-calendar text-primary mr-2"></i>Period</span>
                                <span class="soa-preview-val" id="previewPeriod">Not selected</span>
                            </div>

                            <div class="mt-3 pt-2" style="border-top: 1px solid var(--border-color);">
                                <div class="d-flex align-items-center justify-content-between small text-muted">
                                    <span>Readiness</span>
                                    <span id="previewStatusBadge" class="badge badge-secondary">Incomplete</span>
                                </div>
                            </div>
                        </div>
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

            // Live SOA Preview & Step Tracker
            function syncSoaPreview() {
                const branch = $('#branchFilter').val();
                const tenant = $('#tenantFilter').val().trim();
                const space = $('#spacecodeFilter').val();
                const fromD = $('#fromDateFilter').val();
                const toD = $('#toDateFilter').val();

                $('#previewBranch').text(branch || 'Not selected');
                $('#previewTenant').text(tenant || 'Not selected');
                $('#previewSpace').text(space || 'Not selected');
                $('#previewPeriod').text((fromD && toD) ? `${fromD} → ${toD}` : 'Not selected');

                $('#stepBranch').toggleClass('done', !!branch).toggleClass('active', !branch);
                $('#stepTenant').toggleClass('done', !!tenant).toggleClass('active', !!branch && !tenant);
                $('#stepSpace').toggleClass('done', !!space).toggleClass('active', !!tenant && !space);
                $('#stepPeriod').toggleClass('done', !!(fromD && toD)).toggleClass('active', !!space && !(fromD && toD));

                const ready = !!(branch && tenant && space && fromD && toD);
                $('#previewStatusBadge')
                    .text(ready ? 'Ready to Generate' : 'Incomplete')
                    .removeClass('badge-secondary badge-success')
                    .addClass(ready ? 'badge-success' : 'badge-secondary');
            }

            $('#branchFilter, #tenantFilter, #spacecodeFilter, #fromDateFilter, #toDateFilter').on('change input', syncSoaPreview);
            $('#tenantDropdown').on('click', 'option', function() { setTimeout(syncSoaPreview, 80); });
            syncSoaPreview();

            // SOA Quick Date Presets (dropdown)
            $('.soa-preset').on('click', function(e) {
                e.preventDefault();
                if (typeof ModernUI === 'undefined') return;
                $('.soa-preset').removeClass('active');
                $(this).addClass('active');
                const range = ModernUI.getDatePresetRange($(this).data('preset'));
                $('#fromDateFilter').val(range.start);
                $('#toDateFilter').val(range.end);
                syncSoaPreview();
                ModernUI.toast('info', 'Period Selected', `${range.start} to ${range.end}`);
            });

        });
    </script>
<?php include 'includes/admin_modals.php'; ?>
<?php include 'notification_script.php'; ?>
<script src="js/modern-ui.js?v=<?= time() ?>"></script>
</body>
</html>