<?php
session_start();
include '../config.php';
include '../nav.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Fetch tenant history data
$query = "SELECT * FROM tenant_history ORDER BY date DESC";
$result = $conn->query($query);
$historyData = [];
if ($result) {
    $historyData = $result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>LCLopez Resources - Tenant History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <style>
        body {
            background: white;
            min-height: 100vh;
            padding-top: 80px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(13, 71, 161, 0.15);
            border: none;
            margin-bottom: 2rem;
            border: 1px solid #e1e8ed;
            animation: fadeIn 0.6s ease-out;
        }
        .card-header {
            background: linear-gradient(120deg, #0d47a1, #1976d2);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            padding: 1.2rem 1.5rem;
            border: none;
            animation: fadeIn 0.5s ease-in;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(13, 71, 161, 0.1);
            border: 1px solid #e1e8ed;
            animation: slideInUp 0.5s ease-out;
        }
        .dataTables_wrapper .dataTables_length select {
            width: 80px !important;
            border-radius: 8px;
            padding: 8px 12px;
            border: 1px solid #e1e8ed;
            background-color: #f8fafc;
            transition: all 0.3s ease;
        }
        
        .dataTables_wrapper .dataTables_length select:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.2);
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 8px;
            border: 1px solid #e1e8ed;
            padding: 8px 12px;
            background-color: #f8fafc;
            width: 250px;
            transition: all 0.3s ease;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.2);
        }
        
        .table th {
            font-weight: 600;
            color: #0d47a1;
            background-color: #f0f7ff;
            border-bottom: 2px solid #e1e8ed;
            white-space: nowrap; /* Prevent text wrapping in headers */
            word-wrap: normal;
            text-align: center;
            font-size: 0.75rem; /* Smaller font size for headers */
        }
        
        .table td {
            font-size: 0.75rem; /* Smaller font size for data cells */
            white-space: nowrap; /* Prevent text wrapping in data cells */
            text-align: center; /* Center data in cells */
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.4em 0.7em;
            transition: all 0.3s ease;
        }
        
        /* Reduce font size for Show entries dropdown and label */
        .dataTables_length label {
            font-size: 0.75rem !important;
        }

        .dataTables_length select {
            font-size: 0.75rem !important;
            padding: 0.2rem 1rem !important;
            height: 28px !important;
        }

        /* Reduce font size for search input and label */
        .dataTables_filter label {
            font-size: 0.75rem !important;
        }

        .dataTables_filter input {
            font-size: 0.75rem !important;
            padding: 0.2rem 1rem !important;
            height: 28px !important;
        }
        
        .badge:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(120deg, #0d47a1, #1976d2);
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        .dataTables_info {
            font-size: 0.75rem !important;
            color: #6c757d;
            padding-top: 0.75rem !important;
        }
        
        .pagination .page-link {
            border-radius: 8px !important;
            margin: 0 3px;
            border: 1px solid #e1e8ed;
            padding: 8px 12px;
            transition: all 0.3s ease;
            color: #1976d2;
            font-size: 0.75rem; /* Same font size as other elements */
            background-color: white; /* White background instead of blue */
        }
        
        .pagination .page-link:hover {
            background-color: #f0f7ff;
            border-color: #1976d2;
            transform: translateY(-2px);
        }
        
        .pagination .page-item.active .page-link {
            background: white; /* White background for active page */
            border-color: #0d47a1;
            color: #0d47a1; /* Blue text for active page */
            font-weight: bold;
        }
        
        .bg-success {
            background-color: #4caf50 !important;
        }
        
        .bg-warning {
            background-color: #ff9800 !important;
        }
        
        .bg-danger {
            background-color: #f44336 !important;
        }
        
        .table-hover > tbody > tr:hover {
            background-color: #f8fafc !important;
            box-shadow: 0 2px 5px rgba(13, 71, 161, 0.1);
            transition: all 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-history me-2"></i>Tenant History</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table id="tenantHistoryTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Tenant Name</th>
                                <th>Tenant Code</th>
                                <th>Space Code</th>
                                <th>Branch</th>
                                <th>User</th>
                                <th>Changes</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyData as $record): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $action = $record['action'];
                                    $badgeClass = '';
                                    switch ($action) {
                                        case 'created':
                                            $badgeClass = 'bg-success';
                                            break;
                                        case 'updated':
                                            $badgeClass = 'bg-warning text-dark';
                                            break;
                                        case 'deleted':
                                            $badgeClass = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($action); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($record['tenant_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['tenant_code'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['space_code'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['branch']); ?></td>
                                <td><?php echo htmlspecialchars($record['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['changes_made'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#tenantHistoryTable').DataTable({
                "pageLength": 25,
                "order": [[7, "desc"]], // Order by timestamp descending (now column 7)
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": [6] }, // Disable ordering on changes column (now column 6)
                    { "className": "text-nowrap text-center", "targets": "_all" } // Prevent text wrapping and center text in all cells
                ],
                "language": {
                    "lengthMenu": "Show _MENU_ entries",
                    "search": "Search:",
                    "searchPlaceholder": "Search records...",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                },
                "initComplete": function() {
                    // Style the show entries dropdown with smaller font
                    $('.dataTables_length select').css('width', '80px'); // Reduced width as per guidelines
                    $('.dataTables_length select').css('padding', '0.2rem 1rem');
                    $('.dataTables_length select').css('border-radius', '8px');
                    $('.dataTables_length select').css('border', '1px solid #e1e8ed');
                    $('.dataTables_length select').css('background-color', '#f8fafc');
                    $('.dataTables_length select').css('font-size', '0.75rem'); // Smaller font size
                    $('.dataTables_length select').css('height', '28px');
                    $('.dataTables_length select').addClass('form-select');
                    
                    // Style the search input with smaller font
                    $('.dataTables_filter input').css('font-size', '0.75rem'); // Smaller font size
                    $('.dataTables_filter input').css('padding', '0.2rem 1rem');
                    $('.dataTables_filter input').css('height', '28px');
                    $('.dataTables_filter input').css('width', '200px'); // Slightly reduced width
                    $('.dataTables_filter input').css('border-radius', '8px');
                    $('.dataTables_filter input').css('border', '1px solid #e1e8ed');
                    $('.dataTables_filter input').css('background-color', '#f8fafc');
                    
                    // Style the info text with smaller font
                    $('.dataTables_info').css('font-size', '0.75rem');
                    $('.dataTables_info').css('color', '#6c757d');
                    
                    // Style the pagination buttons with smaller font and white background
                    $('.dataTables_paginate .page-link').css('font-size', '0.75rem');
                    $('.dataTables_paginate .page-link').css('background-color', 'white');
                    $('.dataTables_paginate .page-item.active .page-link').css('background-color', 'white');
                    $('.dataTables_paginate .page-item.active .page-link').css('color', '#0d47a1');
                    $('.dataTables_paginate .page-item.active .page-link').css('font-weight', 'bold');
                    
                    // Add focus effects
                    $('.dataTables_length select').on('focus', function() {
                        $(this).css('box-shadow', '0 0 0 3px rgba(25, 118, 210, 0.2)');
                    });
                    
                    $('.dataTables_length select').on('blur', function() {
                        $(this).css('box-shadow', '');
                    });
                    
                    $('.dataTables_filter input').on('focus', function() {
                        $(this).css('box-shadow', '0 0 0 3px rgba(25, 118, 210, 0.2)');
                    });
                    
                    $('.dataTables_filter input').on('blur', function() {
                        $(this).css('box-shadow', '');
                    });
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
</body>
</html>