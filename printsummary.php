<?php
ob_start();
include 'config.php';
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION["username"];
$branch = $_SESSION["branch"]; // This will help determine the table
date_default_timezone_set('Asia/Manila');
$current_date_time = date('Y-m-d g:i A');
$total_rent = $total_balance = $total_charges = $total = 0;

$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the selected date or default to today
$selected_date = isset($_POST['selected_date']) ? $_POST['selected_date'] : date('Y-m-d');

// Determine the correct table based on the user's branch
$table = ($branch === 'Sanko Market') ? 'collected' : (($branch === 'Nova Market') ? 'collectednova' : 'collectedapm');

// Fetch collector's last name and branch first so we can use it in the queries
$collector_query = "SELECT lname, branch FROM users WHERE username = '$username'";
$collector_result = $conn->query($collector_query);
$lname = '';
if ($collector_result->num_rows > 0) {
    $collector_row = $collector_result->fetch_assoc();
    $lname = $collector_row['lname'];
    $branch = $collector_row['branch'];
}

// Fetch totals for the selected date and collector
$query = "SELECT SUM(paidrent) AS total_rent, SUM(paidbal) AS total_balance, SUM(total) AS db_grand_total
          FROM $table
          WHERE DATE(collected_date) = '$selected_date' AND collector = '$lname'";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_rent = (float)($row['total_rent'] ?? 0);
    $total_balance = (float)($row['total_balance'] ?? 0);
    $db_grand_total = (float)($row['db_grand_total'] ?? 0);
}

// Fetch charges and group by type
$charges_query = "SELECT charges FROM $table WHERE DATE(collected_date) = '$selected_date' AND collector = '$lname'";
$charges_result = $conn->query($charges_query);

// Initialize array to store charge totals by type
$charge_totals = array();
$total_charges = 0;

if ($charges_result->num_rows > 0) {
    while ($charge_row = $charges_result->fetch_assoc()) {
        // Match charges with the format "Electricity: 123", "Ice & Water: 123", etc.
        preg_match_all('/([^:,]+):\s*([\d,]+(\.\d{1,2})?)/', $charge_row['charges'], $matches);

        // If matches are found
        if (count($matches[0]) > 0) {
            foreach ($matches[1] as $index => $charge_type) {
                $charge_value = (float) str_replace(',', '', $matches[2][$index]);
                $charge_type = trim($charge_type);
                
                // Add to total charges
                $total_charges += $charge_value;
                
                // Group by charge type
                if (!isset($charge_totals[$charge_type])) {
                    $charge_totals[$charge_type] = 0;
                }
                $charge_totals[$charge_type] += $charge_value;
            }
        }
    }
}


// Round all totals
$total_rent = round($total_rent, 2);
$total_balance = round($total_balance, 2);
$total_charges = round($total_charges, 2);

// Use the database's true grand total column to match the exact POS transaction totals
$total = round($db_grand_total, 2);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../images/lc.png">
    <title>Print Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #f72585;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
        }
        
        /* Print styles - match print.php UI */
        @media print {
            body, p, h1, div, span {
                color: black !important;
                background: white !important;
                font-size: 12px;
                line-height: 1.2;
                margin: 0;
                padding: 0;
                overflow: visible !important;
            }
            .print-logo {
                width: 100%;
                height: auto;
                margin-bottom: 4%;
            }
            .print-button, .modern-ui, .nav, #side-nav, #date-filter, .print-btn {
                display: none !important;
            }
            .print-only {
                display: block !important;
                width: 58mm; /* Standard Thermal Size */
                margin: 0 auto;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .print-only * {
                visibility: visible !important;
            }
            .print-container {
                text-align: center;
                padding: 0;
            }
            .receipt-row {
                display: flex;
                justify-content: space-between;
                margin: 2px 0;
            }
            .receipt-label {
                font-weight: 600; /* font-semibold */
                text-transform: uppercase;
            }
            .receipt-value {
                font-weight: 900; /* font-black */
                text-align: right;
            }
            @page {
                margin: 0;
                size: 58mm auto;
            }
        }
        
        /* Hide print-only content on screen naturally */
        .print-only {
            display: none;
        }
        /* Hide logo on screen, show only on print */
        .screen-hide {
            display: none;
        }
        
        /* Modern UI styles */
        .modern-ui {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            height: 100vh;
            overflow-y: auto;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
        }
        
        .summary-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            animation: slideInUp 0.6s ease-out;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .stat-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .stat-item:hover {
            transform: scale(1.05);
        }
        
        .stat-item.rent {
            background: linear-gradient(135deg, #4cc9f0 0%, #4361ee 100%);
        }
        
        .stat-item.balance {
            background: linear-gradient(135deg, #f72585 0%, #7209b7 100%);
        }
        
        .stat-item.charges {
            background: linear-gradient(135deg, #f8961e 0%, #f3722c 100%);
        }
        
        .stat-item.total {
            background: linear-gradient(135deg, #06ffa5 0%, #00d4aa 100%);
        }
        
        .charge-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid var(--primary-color);
            animation: slideInRight 0.6s ease-out;
        }
        
        .date-picker {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .custom-input {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .custom-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }
        
        .print-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.4);
        }
        
        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Top bar styles */
        .top-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .nav-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 8px 16px;
            color: white;
            transition: all 0.3s ease;
        }
        
        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }
        
        /* Side menu styles */
        .side-menu {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <!-- Print-only content (Matched print.php Style) -->
    <div class="print-only">
        <div class="print-container">
            <div class="mb-4">
                <img src="images/lc.png" alt="Logo" class="print-logo">
                <p class="mb-2 font-bold italic">Summary Report</p>
            </div>
            
            <div class="space-y-1 text-xs border-b border-black pb-2 mb-2">
                <div class="receipt-row">
                    <span class="receipt-label">Date:</span>
                    <span class="receipt-value"><?php echo $selected_date; ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Branch:</span>
                    <span class="receipt-value"><?php echo isset($branch) ? $branch : ''; ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Collector:</span>
                    <span class="receipt-value"><?php echo isset($lname) ? $lname : ''; ?></span>
                </div>
            </div>

            <div class="space-y-1 my-2">
                <?php if ($total_rent !== null): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Rent:</span>
                        <span class="receipt-value"><?php echo number_format($total_rent, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($total_balance !== null): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Arrear:</span>
                        <span class="receipt-value"><?php echo number_format($total_balance, 2); ?></span>
                    </div>
                <?php endif; ?>
                <!-- Subtotal row like print.php -->
                <div class="receipt-row" style="border-top: 1px dotted black; margin-top: 4px; padding-top: 4px;">
                    <span class="receipt-label">Rent & Arrear Total:</span>
                    <span class="receipt-value">(<?php echo number_format((float)$total_rent + (float)$total_balance, 2); ?>)</span>
                </div>
            </div>
            
            <?php if (!empty($charge_totals)): ?>
                <div class="space-y-1 my-2 pt-1 border-t-2 border-black">
                    <?php foreach ($charge_totals as $charge_type => $amount): ?>
                        <div class="receipt-row">
                            <span class="receipt-label"><?php echo htmlspecialchars($charge_type); ?>:</span>
                            <span class="receipt-value"><?php echo number_format($amount, 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <!-- Total Charges row like print.php -->
                    <div class="receipt-row" style="border-top: 1px dotted black; margin-top: 4px; padding-top: 4px;">
                        <span class="receipt-label">Total Charges:</span>
                        <span class="receipt-value">(<?php echo number_format($total_charges, 2); ?>)</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="border-t-2 border-black pt-2 mt-2">
                <div class="receipt-row" style="font-size: 16px;">
                    <span style="font-weight: 900;">Grand Total:</span>
                    <span style="font-weight: 900;">₱<?php echo number_format($total, 2); ?></span>
                </div>
            </div>

            <div style="margin-top: 15px; text-align: center; border-top: 1px dotted black; pt-2">
                <div class="mt-4 space-y-1">
                    <p style="font-size: 9px; font-weight: 600; font-style: italic; color: black; margin-top: 8px;">"Ensuring accurate collections for every transaction."</p>
                    <p style="font-size: 8px; font-weight: 500; margin-top: 4px;">Thank you for your hard work!</p>
                </div>

                <div class="mt-6 border-t border-dotted border-gray-400 pt-2">
                    <div class="text-[8px] uppercase tracking-[0.4em] font-black opacity-80">Official Summary Report</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern UI (hidden on print) -->
    <div class="modern-ui">
        <nav class="top-bar p-4 mb-4 w-full sticky top-0 z-40">
            <div class="flex justify-between items-center w-full mx-auto max-w-6xl">
                <div class="flex items-center">
                    <a href="user.php" class="nav-btn flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
                <div class="absolute left-1/2 transform -translate-x-1/2">
                </div>
                <div class="sm:hidden">
                    <button id="burger-menu-btn" class="nav-btn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <div class="hidden sm:flex sm:items-center sm:space-x-3">
                    <a href="reprint.php" class="nav-btn flex items-center">
                        <i class="fas fa-print mr-2"></i> Reprint
                    </a>
                    <a href="void_transaction.php" class="nav-btn flex items-center">
                        <i class="fas fa-ban mr-2"></i> Void
                    </a>
                    <a href="user.php" class="nav-btn flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i> New Collection
                    </a>
                    <a href="index.php" class="nav-btn flex items-center">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </nav>

        <!-- Side navigation for small screens -->
        <div id="side-nav" class="hidden fixed inset-0 z-50">
            <div class="flex h-full">
                <div id="side-nav-backdrop" class="bg-black opacity-50 w-1/4 sm:w-3/4 h-full"></div>
                <div class="side-menu w-3/4 sm:w-1/4 h-full transform transition-transform duration-300 ease-in-out -translate-x-full">
                    <div class="flex justify-between items-center p-4 border-b border-white/20">
                        <div class="flex items-center">
                            <i class="fas fa-user-circle text-white text-2xl mr-2"></i>
                            <span class="text-white font-bold"><?php echo htmlspecialchars($username); ?></span>
                        </div>
                        <button id="close-btn" class="text-white focus:outline-none hover:bg-white/20 p-2 rounded-full">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="flex flex-col items-start pl-8 mt-6 space-y-4">
                        <a href="reprint.php" class="text-white text-lg py-3 w-full flex items-center hover:bg-white/10 rounded-lg px-4">
                            <i class="fas fa-print mr-4 w-6 text-center"></i> Reprint Receipt
                        </a>
                        <a href="void_transaction.php" class="text-white text-lg py-3 w-full flex items-center hover:bg-white/10 rounded-lg px-4">
                            <i class="fas fa-ban mr-4 w-6 text-center"></i> Void Transaction
                        </a>
                        <a href="user.php" class="text-white text-lg py-3 w-full flex items-center hover:bg-white/10 rounded-lg px-4">
                            <i class="fas fa-plus-circle mr-4 w-6 text-center"></i> Create Collection
                        </a>
                        <a href="index.php" class="text-white text-lg py-3 w-full flex items-center hover:bg-white/10 rounded-lg px-4">
                            <i class="fas fa-sign-out-alt mr-4 w-6 text-center"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-4 max-w-6xl">
            <!-- Header with Logo (hidden on screen, visible on print) -->
            <div class="text-center mb-4 screen-hide">
                <div class="glass-card inline-block p-6">
                    <img src="images/lc.png" alt="Logo" class="h-24 mx-auto mb-3">
                    <p class="text-white text-xl font-semibold">Summary Report</p>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="date-picker mb-6">
                <form id="dateForm" method="post" class="flex items-center justify-center space-x-4">
                    <label for="selected_date" class="font-bold text-gray-700">Select Date:</label>
                    <input type="date" name="selected_date" id="selected_date" value="<?php echo $selected_date; ?>" 
                           onchange="this.form.submit()" class="custom-input">
                </form>
            </div>

            <!-- Main Summary Card -->
            <div class="summary-card p-6 mb-6">
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="stat-item rent">
                        <div class="text-center">
                            <i class="fas fa-home text-2xl mb-1"></i>
                            <h3 class="font-bold text-sm">Total Rent</h3>
                            <p class="text-xl font-bold">₱<?php echo number_format($total_rent, 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-item balance">
                        <div class="text-center">
                            <i class="fas fa-wallet text-2xl mb-1"></i>
                            <h3 class="font-bold text-sm">Total Balance</h3>
                            <p class="text-xl font-bold">₱<?php echo number_format($total_balance, 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-item charges">
                        <div class="text-center">
                            <i class="fas fa-bolt text-2xl mb-1"></i>
                            <h3 class="font-bold text-sm">Total Charges</h3>
                            <p class="text-xl font-bold">₱<?php echo number_format($total_charges, 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-item total">
                        <div class="text-center">
                            <i class="fas fa-calculator text-2xl mb-1"></i>
                            <h3 class="font-bold text-sm">Grand Total</h3>
                            <p class="text-xl font-bold">₱<?php echo number_format($total, 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Report Details & Charges in one row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Report Details -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                            Report Details
                        </h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-700">Date:</span>
                                <span class="text-gray-900"><?php echo $selected_date; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-700">Branch:</span>
                                <span class="text-gray-900"><?php echo isset($branch) ? $branch : ''; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-700">Collector:</span>
                                <span class="text-gray-900"><?php echo isset($lname) ? $lname : ''; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Charges Breakdown -->
                    <?php if (!empty($charge_totals)): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-list-ul mr-2 text-blue-600"></i>
                                Charges Breakdown
                            </h3>
                            <div class="space-y-2">
                                <?php foreach ($charge_totals as $charge_type => $amount): ?>
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-gray-700">
                                            <?php echo htmlspecialchars($charge_type); ?>:
                                        </span>
                                        <span class="font-bold text-green-600">
                                            ₱<?php echo number_format($amount, 2); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-list-ul mr-2 text-blue-600"></i>
                                Charges Breakdown
                            </h3>
                            <p class="text-gray-500 text-center">No charges for this date</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Print Button -->
                <div class="text-center mt-6 pt-6 border-t border-gray-200">
                    <button onclick="window.print()" class="print-btn">
                        <i class="fas fa-print mr-2"></i>
                        Print Summary
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Burger menu logic
        document.getElementById('burger-menu-btn').addEventListener('click', function() {
            const sideNav = document.getElementById('side-nav');
            sideNav.classList.remove('hidden');
            setTimeout(function() {
                const sideMenu = sideNav.querySelector('.transform');
                sideMenu.classList.add('translate-x-0');
                sideMenu.classList.remove('-translate-x-full');
            }, 50);
        });
        
        document.getElementById('close-btn').addEventListener('click', closeMenu);
        document.getElementById('side-nav-backdrop').addEventListener('click', closeMenu);
        
        function closeMenu() {
            const sideNav = document.getElementById('side-nav');
            const sideMenu = sideNav.querySelector('.transform');
            sideMenu.classList.add('-translate-x-full');
            sideMenu.classList.remove('translate-x-0');
            setTimeout(function() {
                sideNav.classList.add('hidden');
            }, 300);
        }
        
        // Add animation delay to stat items
        document.addEventListener('DOMContentLoaded', function() {
            const statItems = document.querySelectorAll('.stat-item');
            statItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
    
    <!-- App Print Integration -->
    <script src="app_print_integration.js"></script>
</body>
</html>
