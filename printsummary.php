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
$query = "SELECT SUM(paidrent) AS total_rent, SUM(paidbal) AS total_balance, (SUM(paidelec) + SUM(IFNULL(paidelecarrear,0))) AS total_elec, (SUM(paidwater) + SUM(IFNULL(paidwaterarrear,0))) AS total_water, SUM(total) AS db_grand_total
          FROM $table
          WHERE DATE(collected_date) = '$selected_date' AND collector = '$lname'";
$result = $conn->query($query);

$total_rent = 0;
$total_balance = 0;
$total_elec = 0;
$total_water = 0;
$db_grand_total = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_rent = (float)($row['total_rent'] ?? 0);
    $total_balance = (float)($row['total_balance'] ?? 0);
    $total_elec = (float)($row['total_elec'] ?? 0);
    $total_water = (float)($row['total_water'] ?? 0);
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
        // Match charges with the format "Cusa: 123", etc.
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
$total_elec = round($total_elec, 2);
$total_water = round($total_water, 2);
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Print styles - match print.php UI */
        @media print {
            body, p, h1, div, span {
                color: black !important;
                background: white !important;
                font-family: sans-serif;
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
            .modern-app, .modern-bottom-nav {
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

        /* Flatpickr Customization */
        .flatpickr-calendar {
            font-family: 'Inter', sans-serif !important;
            border-radius: 16px !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1) !important;
            border: none !important;
            padding: 10px !important;
        }
        .flatpickr-day.selected {
            background: #2563eb !important;
            border-color: #2563eb !important;
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
                <?php if ($total_rent > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Rent:</span>
                        <span class="receipt-value"><?php echo number_format($total_rent, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($total_balance > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Arrear:</span>
                        <span class="receipt-value"><?php echo number_format($total_balance, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($total_elec > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Elec:</span>
                        <span class="receipt-value"><?php echo number_format($total_elec, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($total_water > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Water:</span>
                        <span class="receipt-value"><?php echo number_format($total_water, 2); ?></span>
                    </div>
                <?php endif; ?>
                <!-- Subtotal row like print.php -->
                <div class="receipt-row" style="border-top: 1px dotted black; margin-top: 4px; padding-top: 4px;">
                    <span class="receipt-label">Payments Subtotal:</span>
                    <span class="receipt-value">(<?php echo number_format((float)$total_rent + (float)$total_balance + (float)$total_elec + (float)$total_water, 2); ?>)</span>
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

    <!-- Modern App Layout -->
    <div class="modern-app min-h-screen bg-[#f3f4f6] pb-32">
        <!-- Bottom Nav Include -->
        <?php include 'modern_bottom_nav.php'; ?>

        <!-- Topbar -->
        <div class="bg-white px-6 pt-8 pb-4 shadow-sm sticky top-0 z-30">
            <div class="flex justify-between items-center w-full lg:max-w-full max-w-lg mx-auto lg:px-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Summary</h1>
                    <p class="text-sm font-medium text-gray-500"><?php echo isset($branch) ? $branch : 'Branch'; ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>

        <div class="px-5 pt-6 pb-6 space-y-5 w-full lg:max-w-full max-w-lg mx-auto lg:px-12">
            
            <!-- Date Selector -->
            <form id="dateForm" method="post">
                <div class="bg-white rounded-2xl p-2 shadow-sm border border-gray-100 flex items-center relative transition-all active:scale-[0.98]">
                    <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center text-blue-500 mr-3">
                        <i class="fas fa-calendar-alt text-lg"></i>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">Collection Date</p>
                        <input type="text" name="selected_date" id="selected_date" value="<?php echo $selected_date; ?>" 
                               class="bg-transparent text-gray-900 font-bold text-base outline-none cursor-pointer w-full" readonly>
                    </div>
                    <div class="px-4 text-gray-300">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </form>

            <!-- Grand Total Card -->
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-3xl p-6 shadow-xl shadow-blue-600/20 text-white relative overflow-hidden">
                <!-- Decorative background shapes -->
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-white opacity-5 rounded-full blur-2xl"></div>
                <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-white opacity-10 rounded-full blur-xl"></div>
                
                <div class="relative z-10">
                    <p class="text-blue-100 font-medium text-sm mb-1">Grand Total Collected</p>
                    <h2 class="text-4xl font-black tracking-tight mb-5">₱<?php echo number_format($total, 2); ?></h2>
                    <div class="flex items-center gap-2 text-sm font-medium bg-white/10 w-max px-3 py-1.5 rounded-full border border-white/20">
                        <i class="fas fa-user-circle text-blue-200"></i>
                        <span><?php echo isset($lname) ? $lname : ''; ?></span>
                    </div>
                </div>
            </div>

            <!-- Breakdown: Rent, Arrear, Elec, Water -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                        <i class="fas fa-home text-xs"></i>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">Total Rent</p>
                    <p class="text-lg font-bold text-gray-900">₱<?php echo number_format($total_rent, 2); ?></p>
                </div>
                <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                    <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-2">
                        <i class="fas fa-history text-xs"></i>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">Total Arrear</p>
                    <p class="text-lg font-bold text-gray-900">₱<?php echo number_format($total_balance, 2); ?></p>
                </div>
                <?php if ($total_elec > 0): ?>
                <div class="bg-white rounded-2xl p-4 shadow-sm border border-yellow-100 bg-yellow-50/20">
                    <div class="w-8 h-8 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center mb-2">
                        <i class="fas fa-bolt text-xs"></i>
                    </div>
                    <p class="text-[10px] font-bold text-yellow-700 uppercase tracking-wider mb-0.5">Total Elec</p>
                    <p class="text-lg font-bold text-yellow-800">₱<?php echo number_format($total_elec, 2); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($total_water > 0): ?>
                <div class="bg-white rounded-2xl p-4 shadow-sm border border-blue-100 bg-blue-50/20">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-2">
                        <i class="fas fa-tint text-xs"></i>
                    </div>
                    <p class="text-[10px] font-bold text-blue-700 uppercase tracking-wider mb-0.5">Total Water</p>
                    <p class="text-lg font-bold text-blue-800">₱<?php echo number_format($total_water, 2); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Total Charges -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 flex items-center justify-between border-b border-gray-50 bg-gray-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900">Total Charges</h3>
                            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Itemized Breakdown</p>
                        </div>
                    </div>
                    <p class="font-bold text-xl text-gray-900">₱<?php echo number_format($total_charges, 2); ?></p>
                </div>
                
                <div class="p-3">
                    <?php if (!empty($charge_totals)): ?>
                        <div class="space-y-1">
                            <?php foreach ($charge_totals as $charge_type => $amount): ?>
                                <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-gray-50 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                                        <span class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($charge_type); ?></span>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900">₱<?php echo number_format($amount, 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="py-6 text-center">
                            <p class="text-sm font-medium text-gray-400">No extra charges today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Print Button (Inline) -->
            <button onclick="window.print()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-600/20 flex items-center justify-center gap-3 mt-4 active:scale-[0.98] transition-all">
                <i class="fas fa-print"></i>
                <span class="text-lg">Print Receipt</span>
            </button>
        </div>
    </div>
    
    <!-- App Print Integration -->
    <script src="app_print_integration.js"></script>

    <!-- Flatpickr for Modern Date Picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#selected_date", {
                dateFormat: "Y-m-d",
                disableMobile: true,
                onChange: function(selectedDates, dateStr, instance) {
                    if (dateStr) {
                        document.getElementById("dateForm").submit();
                    }
                }
            });
        });
    </script>
</body>
</html>
