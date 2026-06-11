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
            <li class="nav-item active">
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
                <h1 class="h6 font-weight-bold text-gray-800 mb-0" style="margin-top: 4px; margin-bottom: 4px !important;"><i class="fas fa-undo-alt mr-2"></i> Void Transaction Log</h1>
            </div>

            <div class="card-body">
                 <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                     <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorMessage">
                         <?= htmlspecialchars($errorMessage) ?>
                         <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                             <span aria-hidden="true">&times;</span>
                         </button>
                     </div>
                 <?php endif; ?>

                <!-- Filters -->
                <div class="d-flex flex-wrap align-items-end mb-4" style="gap: 12px; background-color: #f8fafc; padding: 16px; border-radius: var(--radius-lg); border: 1px solid #e2e8f0;">
                    <div style="min-width: 150px;">
                        <label for="from" class="form-label text-gray-700 font-weight-bold small mb-1"><i class="fas fa-calendar-alt"></i> From Date:</label>
                        <input type="date" id="from" class="form-control form-control-modern" />
                    </div>
                    <div style="min-width: 150px;">
                        <label for="to" class="form-label text-gray-700 font-weight-bold small mb-1"><i class="fas fa-calendar-alt"></i> To Date:</label>
                        <input type="date" id="to" class="form-control form-control-modern" />
                    </div>
                    <div style="min-width: 200px;">
                        <label for="branch" class="form-label text-gray-700 font-weight-bold small mb-1"><i class="fas fa-building"></i> Branch:</label>
                        <select id="branch" class="form-control form-control-modern">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars(strtoupper($b)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button id="applyFilters" class="btn btn-primary shadow-sm" style="border-radius: var(--radius-sm); padding: 7px 16px; font-size: 0.875rem; font-weight: 600; background-color: var(--accent-indigo); border-color: var(--accent-indigo);">
                            <i class="fas fa-filter mr-2"></i> Apply
                        </button>
                    </div>
                    <div>
                        <button id="resetFilters" class="btn btn-light shadow-sm text-secondary" style="border-radius: var(--radius-sm); padding: 7px 16px; font-size: 0.875rem; border: 1px solid #e5e7eb; font-weight: 600;">
                            <i class="fas fa-redo mr-2"></i> Reset
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

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script>
        $(document).ready(function () {
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
                    { "data": "transaction_number" },
                    { "data": "branch", "render": function(data) { return data ? data.toUpperCase() : ''; } },
                    { "data": "spacecode" },
                    { "data": "note" },
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
            });

            $('#resetFilters').on('click', function () {
                const today = new Date().toISOString().split('T')[0];
                $('#from').val(today);
                $('#to').val(today);
                $('#branch').val('');
                table.ajax.reload(null, false);
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
</body>
</html>