<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

// Get user details from session
$username = $_SESSION["username"];
$lname = $_SESSION["lname"];
$branch = $_SESSION["branch"];

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get the current date and time in the Philippines timezone
$currentDateTime = date('Y-m-d\TH:i:s');

// Determine the latest transaction number based on branch
$latestTransactionNumber = 0; // Default transaction number
$tableName = '';

// Check if the branch is Sanko Market or Nova Market
if ($branch === 'Sanko Market') {
    $tableName = 'collected';
    $latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM collected");
} elseif ($branch === 'Nova Market') {
    $tableName = 'collectednova';
    $latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM collectednova");
} elseif ($branch === 'APM') {
    $tableName = 'collectedapm';
    $latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM collectedapm");
} else {
    echo "Invalid branch selection";
    exit();
}

// Fetch the latest transaction number
$latestTransactionRow = $latestTransactionQuery->fetch_assoc();
$latestTransactionNumber = $latestTransactionRow['max_transaction'] ?? 0; // Use 0 as default if no transaction found

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $collector = $_POST['collector'];
    $tenantcode = $_POST['tenantcode'];
    $spacecode = $_POST['spacecode'];
    $tenantname = $_POST['tenantname'];
    $collected_date = $_POST['collected_date']; // Capture the collected date

    // Remove commas and format numeric values properly
    $rent = !empty($_POST['rent']) ? floatval(str_replace(',', '', $_POST['rent'])) : 0;
    $rentbal = !empty($_POST['rentbal']) ? floatval(str_replace(',', '', $_POST['rentbal'])) : 0;
    $runningBal = !empty($_POST['runningbal']) ? floatval(str_replace(',', '', $_POST['runningbal'])) : 0;
    $paidrent = !empty($_POST['paidrent']) ? floatval(str_replace(',', '', $_POST['paidrent'])) : 0;
    $paidbal = !empty($_POST['paidbal']) ? floatval(str_replace(',', '', $_POST['paidbal'])) : 0;
    $total = !empty($_POST['total']) ? floatval(str_replace(',', '', $_POST['total'])) : 0;
    $newbalance = !empty($_POST['newbalance']) ? floatval(str_replace(',', '', $_POST['newbalance'])) : 0;
    $newrentbalance = !empty($_POST['newrentbalance']) ? floatval(str_replace(',', '', $_POST['newrentbalance'])) : 0;

    // Use the latest transaction number to generate the next one
    $nextTransactionNumber = $latestTransactionNumber + 1;

    // Accumulate charges, if provided
    $charges = [];
    if (!empty($_POST['chargecusa'])) {
        $charges[] = "Cusa: " . floatval(str_replace(',', '', $_POST['chargecusa']));
    }
    if (!empty($_POST['chargeac'])) {
        $charges[] = "Aircon: " . floatval(str_replace(',', '', $_POST['chargeac']));
    }
    if (!empty($_POST['chargeelec'])) {
        $charges[] = "Electricity: " . floatval(str_replace(',', '', $_POST['chargeelec']));
    }
    if (!empty($_POST['chargewater'])) {
        $charges[] = "Water: " . floatval(str_replace(',', '', $_POST['chargewater']));
    }

    // Handle multiple 'chargeothers' and 'otheramount' entries
    if (isset($_POST['chargeothers']) && isset($_POST['otheramount'])) {
        foreach ($_POST['chargeothers'] as $index => $chargeType) {
            $amount = isset($_POST['otheramount'][$index]) ? floatval(str_replace(',', '', $_POST['otheramount'][$index])) : 0;
            if ($amount > 0) {
                $charges[] = "$chargeType: " . $amount;
            }
        }
    }

    // Join all charges into a single string, or use an empty string if no charges
    $chargesString = implode(', ', $charges);

    // Prepare the INSERT statement for the main table
    $query = "INSERT INTO $tableName (transaction_number, collector, branch, tenantcode, spacecode, tenantname, rent, rentbal, runningbal, paidrent, paidbal, total, newbalance, newrentbalance, username, collected_date" .
        (!empty($chargesString) ? ", charges" : "") . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" .
        (!empty($chargesString) ? ", ?" : "") . ")";

    $insertStmt = $conn->prepare($query);

    // Bind parameters for insertion
    if (!empty($chargesString)) {
        $insertStmt->bind_param("dssssssddddssssss", $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, $username, $collected_date, $chargesString);
    } else {
        $insertStmt->bind_param("dssssssddddsssss", $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, $username, $collected_date);
    }

    // Execute the INSERT statement
    if ($insertStmt->execute()) {
        // Update running balance and rent balance based on branch
        $updateTable = ($branch === 'Sanko Market') ? 'sanko' : 'nova';
        $updateStmt = $conn->prepare("UPDATE $updateTable SET runningbal = ?, rentbal = ? WHERE spacecode = ?");
        $updateStmt->bind_param("dds", $newbalance, $newrentbalance, $spacecode);

        if ($updateStmt->execute()) {
            include 'modalsuccess.php'; // Include modalsuccess.php to display the success modal
        } else {
            echo "Error updating running and rent balance: " . $conn->error;
        }

        $updateStmt->close();

        // If branch is APM, fetch from the apm table and insert into collectedapm
        if ($branch === 'APM') {
            $apmQuery = $conn->query("SELECT * FROM apm WHERE tenantcode = '$tenantcode' AND spacecode = '$spacecode'");
            while ($apmRow = $apmQuery->fetch_assoc()) {
                // Prepare the INSERT statement for collectedapm
                $apmInsertQuery = "INSERT INTO collectedapm (transaction_number, collector, branch, tenantcode, spacecode, tenantname, rent, rentbal, collected_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $apmInsertStmt = $conn->prepare($apmInsertQuery);
                $apmRent = $apmRow['rent'] ?? 0;
                $apmRentBal = $apmRow['rentbal'] ?? 0;
                $apmInsertStmt->bind_param("dssssssss", $nextTransactionNumber, $collector, $branch, $apmRow['tenantcode'], $apmRow['spacecode'], $apmRow['tenantname'], $apmRent, $apmRentBal, $collected_date);
                $apmInsertStmt->execute();
                $apmInsertStmt->close();
            }
        }
    } else {
        echo "Error inserting collection: " . $conn->error;
    }

    $insertStmt->close();
}

$conn->close();
?>








<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Collection POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Google Fonts for fancy styling -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <!-- jQuery first -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.css">
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.js"></script>
    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.6/css/responsive.bootstrap4.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        :root {
            --primary:   #2563eb;
            --primary-dark: #1d4ed8;
            --accent:    #f59e0b;
            --danger:    #ef4444;
            --success:   #22c55e;
            --surface:   #ffffff;
            --surface2:  #f1f5f9;
            --border:    #e2e8f0;
            --text:      #1e293b;
            --text-muted:#64748b;
            --nav-h:     56px;
            --bot-h:     68px;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--surface2);
            color: var(--text);
            margin: 0;
            padding: 0;
            padding-top: var(--nav-h);
            padding-bottom: calc(var(--bot-h) + env(safe-area-inset-bottom, 0px));
            min-height: 100vh;
        }

        /* â”€â”€ TOP NAV â”€â”€ */
        .top-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--nav-h);
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 14px;
            box-shadow: 0 2px 12px rgba(37,99,235,.35);
            z-index: 100;
        }
        .top-bar .brand {
            display: flex; align-items: center; gap: 8px; color: #fff;
        }
        .top-bar .brand i { font-size: 20px; opacity:.9; }
        .top-bar .brand-name { font-size: 16px; font-weight: 700; letter-spacing: .3px; }
        .top-bar .brand-sub  { font-size: 10px; font-weight: 500; opacity:.75; margin-top:-2px; }
        .top-bar .user-pill {
            display: flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.15);
            border-radius: 20px; padding: 4px 10px 4px 6px;
            cursor: pointer; border: none; color: #fff;
            font-family: inherit;
        }
        .top-bar .user-pill i { font-size: 18px; }
        .top-bar .user-pill span { font-size: 12px; font-weight: 600; }

        /* â”€â”€ BOTTOM NAV â”€â”€ */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: var(--bot-h);
            background: #fff;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: stretch;
            box-shadow: 0 -4px 20px rgba(0,0,0,.08);
            z-index: 100;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .bot-btn {
            flex: 1;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 3px;
            background: none; border: none;
            font-family: inherit; cursor: pointer;
            color: var(--text-muted);
            transition: color .2s, background .2s;
            text-decoration: none;
            padding: 0 2px;
            font-size: 10px; font-weight: 600;
            letter-spacing: .2px;
            position: relative;
        }
        .bot-btn i { font-size: 19px; transition: transform .2s; }
        .bot-btn:active i { transform: scale(0.9); }
        .bot-btn { font-size: 9px; gap: 2px; padding: 0 1px; }
        
        /* Icon Colors */
        .bot-btn.tx-btn i { color: #3b82f6; }
        .bot-btn.sum-btn i { color: #8b5cf6; }
        .bot-btn.dup-btn i { color: #f43f5e; }
        .bot-btn.new-btn i { color: #10b981; }
        .bot-btn.mon-btn i { color: #f59e0b; }
        .bot-btn.void-btn i { color: #ef4444; }
        .bot-btn.print-btn i { color: #0ea5e9; }
        .bot-btn.stop-btn i { color: #64748b; }

        .bot-btn.active { border-bottom: 3px solid var(--primary); }
        .bot-btn.active.tx-btn { border-color: #3b82f6; }
        .bot-btn.active.mon-btn { border-color: #f59e0b; }
        .bot-btn.active.void-btn { border-color: #ef4444; }
        .bot-btn.active.new-btn { border-color: #10b981; }
        .bot-btn.active.print-btn { border-color: #0ea5e9; }

        .badge {
            position: absolute; top: 4px; left: 50%; margin-left: 8px;
            background: #ef4444; color: #fff; font-size: 10px; font-weight: 800;
            min-width: 17px; height: 17px; border-radius: 999px; padding: 0 4px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #fff;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* STATUS BUBBLE */
        .status-bubble {
            position: relative;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all .2s;
        }
        .status-bubble::after {
            content: '';
            position: absolute;
            top: -6px;
            left: 12px;
            border-width: 0 6px 6px 6px;
            border-style: solid;
            border-color: transparent transparent currentColor transparent;
            opacity: 0.15;
        }
        .status-paid { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .status-unpaid { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        /* â”€â”€ FORM CARD â”€â”€ */
        .pos-card {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: 0 1px 8px rgba(0,0,0,.07);
            overflow: hidden;
        }

        /* â”€â”€ INPUTS â”€â”€ */
        .pos-input {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 13px 14px;
            font-size: 16px;
            font-family: inherit;
            background: var(--surface);
            color: var(--text);
            transition: border-color .2s, box-shadow .2s;
            outline: none;
            -webkit-appearance: none;
        }
        .pos-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.15);
        }
        .pos-input-small {
            padding: 7px 10px !important;
            font-size: 14px !important;
            height: 38px !important;
        }

        .pos-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-muted);
            margin-bottom: 6px;
            display: flex; align-items: center; gap: 5px;
        }
        .pos-label i { color: var(--primary); }

        /* â”€â”€ SECTION DIVIDER â”€â”€ */
        .section-divider {
            display: flex; align-items: center;
            margin: 18px 0 12px;
        }
        .section-divider::before, .section-divider::after {
            content: ''; flex: 1;
            border-bottom: 2px solid #60a5fa; /* More visible blue line */
        }
        .section-divider-label {
            padding: 0 14px;
            font-size: 12px; font-weight: 800;
            color: var(--primary);
            text-transform: uppercase; letter-spacing: 1px;
        }

        /* â”€â”€ SUMMARY BOXES â”€â”€ */
        .info-box {
            border-radius: 12px;
            padding: 14px;
            background: var(--surface);
            box-shadow: 0 1px 6px rgba(0,0,0,.07);
            display: flex; align-items: center; gap: 12px;
        }
        .info-box .ib-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .info-label { font-size: 11px; color: var(--text-muted); font-weight: 600; }
        .info-value {
            font-size: 20px; font-weight: 800;
            color: var(--text); line-height: 1.1;
        }
        .info-content { flex: 1; }

        /* â”€â”€ COLLECTION DATE BAR â”€â”€ */
        .date-bar {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; color: #fff;
        }
        .date-bar label {
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            opacity: .8; display: block; margin-bottom: 2px;
        }
        .date-bar input {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 8px; color: #fff;
            font-size: 14px; font-weight: 600;
            padding: 6px 9px;
            font-family: inherit; outline: none;
            width: 100%;
        }
        .date-bar input:focus { border-color: rgba(255,255,255,.6); }

        /* â”€â”€ SUBMIT BUTTON â”€â”€ */
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: #fff;
            border: none; border-radius: 14px;
            padding: 16px;
            font-size: 17px; font-weight: 700;
            font-family: inherit; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 14px rgba(37,99,235,.4);
            transition: transform .15s, box-shadow .15s;
            letter-spacing: .3px;
        }
        .submit-btn:active { transform: scale(.97); box-shadow: 0 2px 6px rgba(37,99,235,.3); }

        /* â”€â”€ ADD CHARGE BUTTON â”€â”€ */
        .pos-button {
            border-radius: 10px;
            transition: all .2s;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }
        .pos-button:active { transform: scale(.97); }

        /* â”€â”€ MOBILE MODAL â”€â”€ */
        .mobile-modal {
            max-height: 92vh;
            overflow-y: auto;
            border-radius: 20px;
            animation: slideUp .25s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(40px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }

        /* â”€â”€ SIDE DRAWER â”€â”€ */
        .side-menu {
            background: linear-gradient(180deg, #1e3a8a 0%, #2563eb 100%);
        }

        /* â”€â”€ ANIMATIONS â”€â”€ */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn .4s ease-out; }

        /* â”€â”€ TOGGLE â”€â”€ */
        .toggle-switch { position:relative; display:inline-block; width:50px; height:24px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider {
            position:absolute; cursor:pointer;
            top:0;left:0;right:0;bottom:0;
            background:#ccc; transition:.4s; border-radius:24px;
        }
        .toggle-slider:before {
            position:absolute; content:"";
            height:16px; width:16px; left:4px; bottom:4px;
            background:#fff; transition:.4s; border-radius:50%;
        }
        input:checked + .toggle-slider { background:var(--primary); }
        input:checked + .toggle-slider:before { transform:translateX(26px); }

        /* â”€â”€ DATATABLE FIX â”€â”€ */
        .dataTables_wrapper .dataTables_filter input {
            border:1px solid var(--border); border-radius:8px; padding:6px 10px;
        }

        /* â”€â”€ SPACECODE SUGGESTIONS â”€â”€ */
        #spacecode-suggestions .cursor-pointer {
            padding: 12px 16px;
            font-size: 15px;
            border-bottom: 1px solid var(--border);
        }
        #spacecode-suggestions .cursor-pointer:last-child { border-bottom: none; }
        #spacecode-suggestions .cursor-pointer:active { background: #dbeafe; }

        /* â”€â”€ MISC â”€â”€ */
        .collection-date-label { display:none; }
        .collection-date-center { display:block; }
    </style>

<link rel="stylesheet" href="modern-bottom-nav.css">
</head>
<body>

    <!-- TOP BAR -->
    <nav class="top-bar">
        <div class="brand">
            <i class="fas fa-cash-register"></i>
            <div>
                <div class="brand-name">
                    <?php
                    if ($branch === 'Sanko Market') echo 'Sanko Market';
                    else echo htmlspecialchars($branch);
                    ?>
                </div>
                <div class="brand-sub">Collection POS</div>
            </div>
        </div>
        <button class="user-pill" id="burger-menu-btn">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($username); ?></span>
            <i class="fas fa-chevron-down" style="font-size:10px;opacity:.7"></i>
        </button>
    </nav>

    <!-- SIDE DRAWER -->
    <div id="side-nav" class="hidden fixed inset-0 z-50">
        <div class="flex h-full">
            <div id="side-nav-backdrop" class="bg-black opacity-50 flex-1 h-full"></div>
            <div class="side-menu w-72 h-full transform transition-transform duration-300 ease-in-out translate-x-full ml-auto">
                <div class="flex justify-between items-center p-4 border-b border-blue-800">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-user-circle text-white text-3xl"></i>
                        <div>
                            <div class="text-white font-bold"><?php echo htmlspecialchars($username); ?></div>
                            <div class="text-blue-200 text-xs"><?php echo htmlspecialchars($branch); ?></div>
                        </div>
                    </div>
                    <button id="close-btn" class="text-white p-2 hover:bg-blue-800 rounded-full">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="flex flex-col mt-4 px-3 gap-1">
                    <a href="user.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-plus-circle w-5 text-center text-blue-300"></i> New Collection
                    </a>
                    <a href="transactions.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-list-ul w-5 text-center text-blue-300"></i> Transactions
                    </a>
                    <a href="reprint.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-print w-5 text-center text-blue-300"></i> Reprint Receipt
                    </a>
                    <a href="void_transaction.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-ban w-5 text-center text-yellow-300"></i> Void Transaction
                    </a>
                    <a href="downloads/lclopez-collection.apk" download class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-download w-5 text-center text-green-300"></i> Download App
                    </a>
                </div>
                <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-blue-800">
                    <a href="index.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-red-700 transition">
                        <i class="fas fa-sign-out-alt w-5 text-center text-red-300"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="px-3 pt-3">
    <?php include 'modern_bottom_nav.php'; ?>

        <form id="collectionForm" method="post" action="user.php" class="mx-auto animate-fadeIn" style="max-width:520px">
            <input readonly hidden type="text" name="collector" id="collector" value="<?php echo htmlspecialchars($lname); ?>">
            <input name="branch" id="branch" type="hidden" value="<?php echo htmlspecialchars($branch); ?>">

            <!-- DATE BAR -->
            <div class="date-bar mb-4">
                <div style="flex:1">
                    <label>Collection Date</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="text" id="collected_date_part"
                            value="<?php echo date('Y-m-d'); ?>"
                            onchange="updateDateTime()" style="padding-right:30px; cursor:pointer;" readonly>
                        <i class="far fa-calendar" style="position:absolute; right:10px; color:#fff; pointer-events:none;"></i>
                    </div>
                </div>
                <div style="flex:1">
                    <label>Time</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="text" id="collected_time_part"
                            value="<?php echo date('H:i:s'); ?>" step="1"
                            onchange="updateDateTime()" style="padding-right:30px; cursor:pointer;" readonly>
                        <i class="far fa-clock" style="position:absolute; right:10px; color:#fff; pointer-events:none;"></i>
                    </div>
                </div>
                <input type="hidden" id="collected_date" name="collected_date"
                    value="<?php echo date('Y-m-d\TH:i:s'); ?>">
            </div>

            <!-- Tenant Info Section -->
            <div class="section-divider">
                <div class="section-divider-label"><i class="fas fa-user-tag mr-2"></i>Tenant Information</div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="pos-label" for="spacecode">
                        <i class="fas fa-map-marker-alt"></i>Space Code
                    </label>
                    <div class="relative">
                        <input required autocomplete="off"
                            class="pos-input w-full"
                            id="spacecode-input" type="text" name="spacecode" placeholder="Space Code"
                            oninput="suggestSpaceCode(this.value)">
                        <span id="spacecode-error" class="text-red-500 text-sm"></span>
                        <div id="payment-status-container" class="hidden">
                            <i id="payment-status-icon" class="fas"></i>
                            <span id="payment-status-text"></span>
                        </div>
                        <div id="spacecode-suggestions" class="hidden absolute z-10 bg-white rounded-lg mt-1 shadow-lg w-full"></div>
                    </div>
                </div>
                <div>
                    <label class="pos-label" for="tenantcode">
                        <i class="fas fa-id-card"></i>Tenant Code
                    </label>
                    <input required class="pos-input w-full" id="tenantcode" type="text" name="tenantcode" placeholder="Tenant Code">
                </div>
            </div>

            <div class="mb-3">
                <label class="pos-label" for="tenantname">
                    <i class="fas fa-user"></i>Tenant Name
                </label>
                <input required class="pos-input w-full" id="tenantname" type="text" name="tenantname" placeholder="Tenant Name">
            </div>

            <!-- Rent Details Section -->
            <div class="section-divider">
                <div class="section-divider-label"><i class="fas fa-coins mr-2"></i>Rent Details</div>
            </div>

            <div class="mb-3">
                <label class="pos-label" for="rent">
                    <i class="fas fa-money-bill"></i>Daily Rent
                </label>
                <input class="pos-input w-full" id="rent" type="text" name="rent" placeholder="Daily Rent">
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="pos-label" for="rentbal">
                        <i class="fas fa-balance-scale"></i>Rent Balance
                    </label>
                    <input class="pos-input w-full" id="rentbal" type="text" name="rentbal" placeholder="Rent Balance" oninput="calculateNewBalance()">
                </div>
                <div>
                    <label class="pos-label" for="runningbal">
                        <i class="fas fa-exclamation-circle"></i>Arrear Balance
                    </label>
                    <input class="pos-input w-full" id="runningbal" type="text" name="runningbal" placeholder="Arrear Balance">
                </div>
            </div>

            <!-- Payment Section -->
            <div class="section-divider">
                <div class="section-divider-label"><i class="fas fa-hand-holding-usd mr-2"></i>Payment</div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="pos-label" for="paidrent">
                        <i class="fas fa-money-bill-wave"></i>Payment (Rent)
                    </label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                        class="pos-input w-full" id="paidrent" type="text" name="paidrent" placeholder="0.00" inputmode="decimal">
                </div>
                <div>
                    <label class="pos-label" for="paidbal">
                        <i class="fas fa-money-check-alt"></i>Payment (Arrear)
                    </label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                        class="pos-input w-full" id="paidbal" type="text" name="paidbal" placeholder="0.00" inputmode="decimal">
                </div>
            </div>

            <!-- Charges Section -->
            <div class="section-divider">
                <div class="section-divider-label"><i class="fas fa-file-invoice mr-2"></i>Additional Charges</div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="pos-label" for="chargecusa"><i class="fas fa-landmark"></i>Cusa</label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input w-full" id="chargecusa" type="text" name="chargecusa" placeholder="0.00" inputmode="decimal">
                </div>
                <div>
                    <label class="pos-label" for="chargeac"><i class="fas fa-wind"></i>Aircon</label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input w-full" id="chargeac" type="text" name="chargeac" placeholder="0.00" inputmode="decimal">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="pos-label" for="chargeelec"><i class="fas fa-bolt"></i>Electricity</label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input w-full" id="chargeelec" type="text" name="chargeelec" placeholder="0.00" inputmode="decimal">
                </div>
                <div>
                    <label class="pos-label" for="chargewater"><i class="fas fa-tint"></i>Water</label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input w-full" id="chargewater" type="text" name="chargewater" placeholder="0.00" inputmode="decimal">
                </div>
            </div>

            <div class="mb-4 flex flex-col gap-3">
                <div class="grid grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="pos-label" for="chargeothers"><i class="fas fa-plus-circle"></i>Others</label>
                    <div class="relative custom-select" id="initialChargeSelect">
                        <input type="text" class="pos-input w-full cursor-pointer bg-white border-blue-200 focus:border-blue-500" placeholder="Select a type" readonly onclick="openCustomSelect(this)">
                        <input type="hidden" id="chargeothers" name="chargeothers[]">
                        <div class="absolute z-50 w-full bg-white border border-blue-200 rounded-lg shadow-xl mt-1 hidden dropdown-menu flex-col overflow-hidden">
                            <div class="p-2 border-b border-blue-100 bg-blue-50">
                                <input type="text" class="w-full bg-white border border-blue-200 rounded px-2 py-1.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400" placeholder="Search charges...">
                            </div>
                            <div class="max-h-48 overflow-y-auto dropdown-options"></div>
                        </div>
                    </div>
                </div>
                <div>
                        <label class="pos-label" for="otheramount"><i class="fas fa-money-bill-alt"></i>Amount</label>
                        <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input w-full" id="otheramount" type="text" name="otheramount[]" placeholder="0.00" inputmode="decimal">
                    </div>
                </div>
                <div id="additionalChargesContainer" class="flex flex-col gap-2"></div>
                <div class="flex justify-center">
                    <button type="button" id="addChargeButton" class="pos-button bg-blue-500 hover:bg-blue-600 text-white py-1.5 px-4 text-sm font-semibold border-none shadow transition-all rounded-lg">
                        <i class="fas fa-plus mr-1.5"></i>Add Charges
                    </button>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="section-divider">
                <div class="section-divider-label"><i class="fas fa-calculator mr-2"></i>Summary</div>
            </div>

            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="info-box" style="flex-direction:column;align-items:flex-start;padding:12px">
                    <div class="info-label" style="margin-bottom:4px">Total</div>
                    <div class="info-value" style="font-size:16px;color:#2563eb">
                        &#x20B1;<span id="total-display">0.00</span>
                    </div>
                    <input readonly hidden id="total" type="text" name="total" value="0">
                </div>
                <div class="info-box" style="flex-direction:column;align-items:flex-start;padding:12px">
                    <div class="info-label" style="margin-bottom:4px">Arrear Bal</div>
                    <div class="info-value" style="font-size:16px;color:#7c3aed">
                        &#x20B1;<span id="newbalance-display">0.00</span>
                    </div>
                    <input readonly hidden id="newbalance" type="text" name="newbalance" value="0">
                </div>
                <div class="info-box" style="flex-direction:column;align-items:flex-start;padding:12px">
                    <div class="info-label" style="margin-bottom:4px">Rent Bal</div>
                    <div class="info-value" style="font-size:16px;color:#16a34a">
                        &#x20B1;<span id="newrentbalance-display">0.00</span>
                    </div>
                    <input readonly hidden id="newrentbalance" type="text" name="newrentbalance" value="0">
                </div>
            </div>

            <button class="submit-btn mb-2" type="submit" id="submitButton">
                <i class="fas fa-paper-plane"></i> Submit Payment
            </button>
        </form>
    </div>


    <!-- Welcome Modal -->

    <div id="welcomeModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>&#8203;
            <div class="mobile-modal inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 sm:mx-0 sm:h-14 sm:w-14">
                            <!-- Welcome icon -->
                            <i class="fas fa-handshake text-blue-600 text-2xl"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Welcome, <?php echo htmlspecialchars($username); ?>!</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Ready to process collections for <?php echo htmlspecialchars($branch); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="document.getElementById('welcomeModal').classList.add('hidden')" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Duplicated Transactions Modal -->
    <div id="duplicatedTransactionsModal" class="fixed z-50 inset-0 hidden" style="background:rgba(0,0,0,0.55)">
        <div class="flex items-start justify-center min-h-screen pt-14 px-3 pb-24">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full overflow-hidden" style="max-width:480px">
                <div class="flex justify-between items-center px-4 py-3" style="background:linear-gradient(90deg,#dc2626,#b91c1c)">
                    <h3 class="text-white font-bold text-base flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i> Duplicated Transactions
                    </h3>
                    <button onclick="closeModal()" class="text-white p-1 hover:opacity-75">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="p-3 overflow-y-auto" style="max-height:70vh">
                    <div id="dupCardsContainerUser" class="flex flex-col gap-2">
                        <div class="text-center py-6 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="mobile-modal bg-white rounded-lg p-6 max-w-lg w-full mx-4">
            <div class="text-center mb-4">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                    <i class="fas fa-receipt text-blue-600 text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Payment Summary</h2>
                <p class="text-gray-600">Please double check the amount before confirming</p>
            </div>
            
            <div id="confirmationSummary" class="mb-6 bg-gray-50 p-4 rounded-lg">
                <!-- Dynamic content will be inserted here -->
            </div>
            
            <div class="flex justify-between gap-4">
                <button id="cancelButton" class="pos-button bg-gray-500 hover:bg-gray-600 text-white py-3 px-6 rounded-lg flex-1 flex items-center justify-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button id="confirmButton" class="pos-button bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-lg flex-1 flex items-center justify-center">
                    <i class="fas fa-check mr-2"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Logout Modern Modal -->
    <div id="logoutConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-[60] animate-fadeIn">
        <div class="mobile-modal bg-white rounded-3xl p-8 max-w-[340px] w-full mx-4 shadow-2xl relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-red-50 rounded-full opacity-50"></div>
            <div class="text-center relative z-10">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-2xl bg-red-500 mb-6 shadow-xl shadow-red-100">
                    <i class="fas fa-sign-out-alt text-white text-3xl"></i>
                </div>
                <h3 class="text-2xl font-black text-gray-900 mb-2">Going so soon?</h3>
                <p class="text-gray-500 mb-8 px-2 font-medium">Are you sure you want to exit and end your collection session?</p>
                
                <div class="flex flex-col gap-3">
                    <a href="index.php" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 px-6 rounded-2xl shadow-lg shadow-red-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-door-open mr-1"></i> YES, EXIT
                    </a>
                    <button onclick="hideLogoutModal()" class="w-full bg-gray-50 hover:bg-gray-100 text-gray-500 font-bold py-4 px-6 rounded-2xl transition-all active:scale-95">
                        KEEP WORKING
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="function.js"> </script>

    <script src="nav_badge.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.2.6/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.6/js/responsive.bootstrap4.min.js"></script>

    <script>
        // Function to open transactions modal
        function openModal() {
            const vtModal = document.getElementById('viewTransactionsModal');
            if (vtModal) {
                vtModal.classList.remove('hidden');
                fetchAndLoadTransactions();
            }
        }
        
        // Function to close modals
        function closeModal() {
            const vtModal = document.getElementById('viewTransactionsModal');
            if (vtModal) vtModal.classList.add('hidden');
            
            const dtModal = document.getElementById('duplicatedTransactionsModal');
            if (dtModal) dtModal.classList.add('hidden');
        }

        // Duplicate card builder
        function fmtDate(d) {
            const dt = new Date(d);
            if (isNaN(dt)) return d;
            return dt.toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'})
                + ' ' + dt.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit'});
        }
        function nFmt(v) {
            return parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
        }
        function getChargesTotal(chStr) {
            if (!chStr || chStr === 'null') return 0;
            let total = 0;
            const parts = chStr.split(',');
            for (let p of parts) {
                const match = p.match(/:\s*([\d,.]+)/);
                if (match) total += parseFloat(match[1].replace(/,/g, '')) || 0;
            }
            return total;
        }
        function buildDupCardUser(t) {
            const cTotal = getChargesTotal(t.charges);
            const total = parseFloat(t.paidrent||0) + parseFloat(t.paidbal||0) + cTotal;
            const charges = t.charges && t.charges !== 'null' ? t.charges : '';
            return `<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #fee2e2;border-left:4px solid #dc2626;margin-bottom:0;overflow:hidden">
                <div style="background:linear-gradient(135deg,#fff5f5,#fee2e2);padding:10px 14px 8px;border-bottom:1px solid #fecaca;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:15px;font-weight:700;color:#dc2626">#${t.transaction_number}</div>
                        <div style="font-size:11px;color:#64748b">${fmtDate(t.collected_date)}</div>
                    </div>
                    <div style="font-size:14px;font-weight:800;color:#16a34a">&#x20B1;${nFmt(total)}</div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;padding:10px 14px 12px">
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Space</div><div style="font-size:13px;font-weight:600;color:#374151">${t.spacecode||'—'}</div></div>
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Code</div><div style="font-size:13px;font-weight:600;color:#374151">${t.tenantcode||'—'}</div></div>
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Paid Rent</div><div style="font-size:13px;font-weight:700;color:#16a34a">&#x20B1;${nFmt(t.paidrent)}</div></div>
                    ${charges ? `<div style="padding:4px;grid-column:1/-1"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Charges</div><div style="font-size:12px;color:#64748b">${charges}</div></div>` : ''}
                    <div style="padding:4px;grid-column:1/-1"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Tenant</div><div style="font-size:14px;font-weight:600;color:#1e293b">${t.tenantname||'—'}</div></div>
                </div>
            </div>`;
        }

        // Wire the Dups nav button
        document.addEventListener('DOMContentLoaded', function() {
            const dupBtn = document.getElementById('notificationButton');
            if (dupBtn) {
                dupBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modal = document.getElementById('duplicatedTransactionsModal');
                    const cont  = document.getElementById('dupCardsContainerUser');
                    modal.classList.remove('hidden');
                    cont.innerHTML = '<div style="text-align:center;padding:24px;color:#94a3b8"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>';
                    fetch('fetch_duplicated_transactions.php')
                        .then(r => r.json())
                        .then(data => {
                            cont.innerHTML = data.length
                                ? data.map(buildDupCardUser).join('')
                                : '<div style="text-align:center;padding:32px;color:#22c55e"><i class="fas fa-check-circle" style="font-size:32px;display:block;margin-bottom:8px"></i>No duplicates found!</div>';
                        })
                        .catch(() => { cont.innerHTML = '<div style="text-align:center;padding:24px;color:#ef4444">Error loading data</div>'; });
                });
            }
        });

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.remove('hidden');
        }
        function hideLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.add('hidden');
        }
        
        // (Legacy transaction table loader removed - table replaced with modern card modal)

        // Function to update the combined datetime field
        function updateDateTime() {
            const datePart = document.getElementById('collected_date_part').value;
            const timePart = document.getElementById('collected_time_part').value;
            
            if (datePart && timePart) {
                const combinedValue = `${datePart}T${timePart}`;
                document.getElementById('collected_date').value = combinedValue;
                
                // Re-fetch tenant details if spacecode is already filled
                var spacecode = document.getElementById("spacecode-input").value;
                if (spacecode && spacecode !== "") {
                    fetchTenantDetails(spacecode);
                }
            }
        }
        
        // Function to update time with seconds in real-time
        function updateTimeWithSeconds() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeValue = `${hours}:${minutes}:${seconds}`;
            document.getElementById('collected_time_part').value = timeValue;
            
            // Update the combined field
            updateDateTime();
        }
        
        // Set interval to update time every second
        let timeUpdateInterval;
        
        // Function to format numbers with commas
        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        // Function to calculate new running balance and new rent balance
        function calculateNewBalance() {
            // Get the values from input fields and default to 0 if empty
            var dailyRent = parseFloat(document.getElementById("rent").value.replace(/,/g, '')) || 0;
            var paidRent = parseFloat(document.getElementById("paidrent").value.replace(/,/g, '')) || 0;
            var paidBalance = parseFloat(document.getElementById("paidbal").value.replace(/,/g, '')) || 0;
            var rentBalance = parseFloat(document.getElementById("rentbal").value.replace(/,/g, '')) || 0;
            var runningBalance = parseFloat(document.getElementById("runningbal").value.replace(/,/g, '')) || 0;

            // Get the charges values from predefined inputs
            var chargecusa = parseFloat(document.getElementById("chargecusa").value.replace(/,/g, '')) || 0;
            var chargeac = parseFloat(document.getElementById("chargeac").value.replace(/,/g, '')) || 0;
            var chargeelec = parseFloat(document.getElementById("chargeelec").value.replace(/,/g, '')) || 0;
            var chargewater = parseFloat(document.getElementById("chargewater").value.replace(/,/g, '')) || 0;

            // Calculate total charges from predefined inputs
            var totalCharges = chargecusa + chargeac + chargeelec + chargewater;

            // Get the value from the predefined 'Others' inputs
            var otherAmount = parseFloat(document.getElementById("otheramount").value.replace(/,/g, '')) || 0;

            // Check if there is a valid other type selected and amount is greater than 0
            var otherType = document.getElementById("chargeothers").value;
            if (otherType && otherAmount > 0) {
                totalCharges += otherAmount;
            }

            // Get dynamically added charges
            var additionalCharges = document.querySelectorAll('#additionalChargesContainer input[type="text"]');
            additionalCharges.forEach(function (input) {
                var amount = parseFloat(input.value.replace(/,/g, '')) || 0;
                totalCharges += amount;
            });

            // Calculate total amount paid
            var totalAmountPaid = paidRent + paidBalance;
            
            // Calculate new balances according to business rules:
            // 1. Handle overpayment in rent balance first
            // 2. Apply payments to respective balances independently
            // 3. Preserve negative balances as overpayments
            
            // Calculate new rent balance
            // When rentBalance is negative (overpayment), applying a payment should reduce the overpayment
            // The overpayment amount is (paidRent - dailyRent) when paidRent > dailyRent
            // New rent balance = Current rent balance - (Paid Rent - Daily Rent)
            var overpayment = paidRent - dailyRent;
            var newRentBalance = rentBalance - overpayment;
            
            // Calculate new arrear balance
            // New arrear balance = Current arrear balance - Amount paid toward arrear
            var newRunningBalance = runningBalance - paidBalance;

            // Calculate total payment (amounts paid + charges)
            var total = totalAmountPaid + totalCharges;

            // Format the values with commas for display (keep exact decimal precision)
            var formattedNewRentBalance = parseFloat(newRentBalance.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            var formattedNewRunningBalance = parseFloat(newRunningBalance.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            var formattedTotal = parseFloat(total.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Update both the display elements and hidden input fields
            document.getElementById("newrentbalance").value = formattedNewRentBalance;
            document.getElementById("newbalance").value = formattedNewRunningBalance;
            document.getElementById("total").value = formattedTotal;
            
            // Update the display spans
            document.getElementById("newrentbalance-display").textContent = formattedNewRentBalance;
            document.getElementById("newbalance-display").textContent = formattedNewRunningBalance;
            document.getElementById("total-display").textContent = formattedTotal;
        }

        // Function to format numbers with commas and trigger calculation
        function formatNumberAndCalculateNewBalance(input) {
            // Remove non-numeric characters and commas
            let cleanValue = input.value.replace(/[^\d.]/g, '');

            // Format the number with commas
            let formattedValue = cleanValue.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Set the formatted value back to the input
            input.value = formattedValue;

            // Now you can use this value for further calculations
            calculateNewBalance();
        }

        // For add button other charges
        document.getElementById('addChargeButton').addEventListener('click', function () {
            var container = document.getElementById('additionalChargesContainer');

            // Create a new entry with dropdown and amount input
            var newEntry = document.createElement('div');
            newEntry.classList.add('grid', 'grid-cols-2', 'gap-4', 'items-end', 'mb-2');
            newEntry.innerHTML = `
                <div class="relative custom-select">
                    <input type="text" class="pos-input w-full cursor-pointer bg-white border-blue-200 focus:border-blue-500" placeholder="Select a type" readonly onclick="openCustomSelect(this)">
                    <input type="hidden" name="chargeothers[]">
                    <div class="absolute z-50 w-full bg-white border border-blue-200 rounded-lg shadow-xl mt-1 hidden dropdown-menu flex-col overflow-hidden">
                        <div class="p-2 border-b border-blue-100 bg-blue-50">
                            <input type="text" class="w-full bg-white border border-blue-200 rounded px-2 py-1.5 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400" placeholder="Search charges...">
                        </div>
                        <div class="max-h-48 overflow-y-auto dropdown-options"></div>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="relative flex-1">
            <input oninput="formatNumberAndCalculateNewBalance(this)" 
                            class="pos-input w-full"
                            type="text" name="otheramount[]" placeholder="0.00" inputmode="decimal">
                    </div>
                    <button type="button" class="remove-charge ml-2 bg-red-50 hover:bg-red-100 text-red-500 rounded-full p-2.5 transition-colors">
                        <i class="fas fa-trash-alt"></i>
                    </button>
        </div>
    `;

            // Add event listener for remove button
            newEntry.querySelector('.remove-charge').addEventListener('click', function() {
                container.removeChild(newEntry);
                calculateNewBalance(); // Recalculate after removing
            });

            container.appendChild(newEntry);
        });

        // For Modal Payment Summary
        // Function to format the numbers as currency
        function formatCurrency(value) {
            return '&#x20B1;' + numberWithCommas(parseFloat(value).toFixed(2));
        }

        // Function to gather the values and show the modal
        function showConfirmationModal(event) {
            event.preventDefault(); // Prevent the form from submitting immediately

            // Retrieve the form values
            const rent = parseFloat(document.getElementById('rent').value.replace(',', '')) || 0;
            const rentbal = parseFloat(document.getElementById('rentbal').value.replace(',', '')) || 0;
            const runningbal = parseFloat(document.getElementById('runningbal').value.replace(',', '')) || 0;
            const paidrent = parseFloat(document.getElementById('paidrent').value.replace(',', '')) || 0;
            const paidbal = parseFloat(document.getElementById('paidbal').value.replace(',', '')) || 0;
            const total = parseFloat(document.getElementById('total').value.replace(',', '')) || 0;
            const newbalance = parseFloat(document.getElementById('newbalance').value.replace(',', '')) || 0;
            const newrentbalance = parseFloat(document.getElementById('newrentbalance').value.replace(',', '')) || 0;

            // Get all charges (Cusa, Aircon, Electricity, Water, Other charges from dropdown)
            const charges = [];
            
            const chargeNames = [
                { label: 'Cusa', id: 'chargecusa' },
                { label: 'Aircon', id: 'chargeac' },
                { label: 'Electricity', id: 'chargeelec' },
                { label: 'Water', id: 'chargewater' }
            ];
            
            chargeNames.forEach(charge => {
                const chargeAmount = parseFloat(document.getElementById(charge.id).value.replace(',', '')) || 0;
                if (chargeAmount > 0) {
                    charges.push({ name: charge.label, amount: chargeAmount });
                }
            });

            // Handle dynamic "Other Charges" from custom dropdowns (both static and dynamically added)
            const otherSelects = document.querySelectorAll('input[name="chargeothers[]"]');
            const otherAmounts = document.querySelectorAll('input[name="otheramount[]"]');
            
            for (let i = 0; i < otherSelects.length; i++) {
                const selectedChargeType = otherSelects[i].value;
                const otherAmount = parseFloat(otherAmounts[i].value.replace(/,/g, '')) || 0;
                if (selectedChargeType && otherAmount > 0) {
                    charges.push({ name: selectedChargeType, amount: otherAmount });
                }
            }

            // Generate the charges summary HTML
            let chargesHtml = '';
            charges.forEach(charge => {
                chargesHtml += `<p><strong>${charge.name}:</strong> ${formatCurrency(charge.amount)}</p>`;
            });

            // Update the Amount Paid (Arrear Balance) to include the charges as well
            const totalPaidArrearBalance = paidbal + charges.reduce((acc, charge) => acc + charge.amount, 0);

            // Display the payment summary in the modal
            const summaryHtml = `
                <p><strong>Amount Paid (Daily Rent):</strong> ${formatCurrency(paidrent)}</p>
                <p><strong>Amount Paid (Arrear Balance):</strong> ${formatCurrency(paidbal)}</p>
                ${chargesHtml}  <!-- Display charges here -->
                <p><strong>Total Paid (including Charges):</strong> ${formatCurrency(total)}</p>
            `;
            document.getElementById('confirmationSummary').innerHTML = summaryHtml;

            // Show the confirmation modal
            document.getElementById('confirmationModal').classList.remove('hidden');
        }

        // Event listener for the confirm button
        const confBtn = document.getElementById('confirmButton');
        if (confBtn) {
            confBtn.addEventListener('click', function() {
                // Close the modal
                document.getElementById('confirmationModal').classList.add('hidden');

                // Submit the form programmatically
                document.getElementById('collectionForm').submit();
            });
        }

        // Event listener for the cancel button
        const canBtn = document.getElementById('cancelButton');
        if (canBtn) {
            canBtn.addEventListener('click', function() {
                window.location.href = "user.php";
            });
        }

        // Event listener for the form submit button
        const colForm = document.getElementById('collectionForm');
        if (colForm) {
            colForm.addEventListener('submit', showConfirmationModal);
        }

        // Show welcome modal on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if the welcome modal should be displayed
            var welcomeModalClosed = localStorage.getItem("welcomeModalClosed");
            if (!welcomeModalClosed) {
                document.getElementById("welcomeModal").classList.remove("hidden");
                setTimeout(function() {
                    document.getElementById("welcomeModal").classList.add("hidden");
                    localStorage.setItem("welcomeModalClosed", "true");
                }, 2000);
            }
            
            // Initialize date/time inputs
            const datePart = document.getElementById('collected_date_part');
            const timePart = document.getElementById('collected_time_part');
            
            // Initialize with default values if not already set
            if (!datePart.value) {
                datePart.value = new Date().toISOString().split('T')[0];
            }
            
            // Start the real-time clock update
            updateTimeWithSeconds(); // Update immediately
            timeUpdateInterval = setInterval(updateTimeWithSeconds, 1000); // Update every second
            
            // Set the hidden combined field
            updateDateTime();
            
            // Initial manual calc
            calculateNewBalance();
            
            // Handle pre-fill from monitoring page
            const prefill = localStorage.getItem('prefill_spacecode');
            if (prefill) {
                document.getElementById('spacecode-input').value = prefill;
                fetchTenantDetails(prefill);
                localStorage.removeItem('prefill_spacecode');
            }
        });

        // â”€â”€ RIGHT-SIDE DRAWER â”€â”€
        document.getElementById('burger-menu-btn').addEventListener('click', function() {
            const sideNav = document.getElementById('side-nav');
            sideNav.classList.remove('hidden');
            setTimeout(function() {
                const sideMenu = sideNav.querySelector('.side-menu');
                sideMenu.classList.add('translate-x-0');
                sideMenu.classList.remove('translate-x-full');
            }, 20);
        });

        document.getElementById('close-btn').addEventListener('click', closeMenu);
        document.getElementById('side-nav-backdrop').addEventListener('click', closeMenu);

        function closeMenu() {
            const sideNav = document.getElementById('side-nav');
            const sideMenu = sideNav.querySelector('.side-menu');
            sideMenu.classList.add('translate-x-full');
            sideMenu.classList.remove('translate-x-0');
            setTimeout(function() { sideNav.classList.add('hidden'); }, 300);
        }

        // â”€â”€ BADGE FOR DUPLICATES â”€â”€
        function checkDuplicateBadge() {
            fetch('fetch_duplicated_transactions.php')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('notificationBadge');
                    if (data.length > 0) {
                        badge.textContent = data.length;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }).catch(() => {});
        }
        // Monitoring stats updates take care of the badge now
        // checkDuplicateBadge();

        // Inside the event listener for the notification button
        const notifBtn = document.getElementById('notificationButton');
        if (notifBtn) {
            notifBtn.addEventListener('click', function (event) {
                event.preventDefault(); // Prevent the default form submission
            
            // Show loading indicator
            document.getElementById('duplicatedTransactionsTableBody').innerHTML = 
                '<tr><td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Loading data...</td></tr>';
            
            // Show the modal first
            document.getElementById('duplicatedTransactionsModal').classList.remove('hidden');
            
            fetch('fetch_duplicated_transactions.php')
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('duplicatedTransactionsTableBody');
                    tableBody.innerHTML = ''; // Clear existing rows

                    if (data.length > 0) {
                        data.forEach(transaction => {
                            const charges = parseCharges(transaction.charges); // Parse charges from string

                            // Create a row with hover effect
                            const row = document.createElement('tr');
                            row.className = 'hover:bg-red-50 transition-colors';
                            
                            // Format the date
                            const date = new Date(transaction.collected_date);
                            const formattedDate = date.toLocaleString('en-US', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            // Create the table cells
                            row.innerHTML = `
                                <td class="px-4 py-3 whitespace-nowrap">${formattedDate}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${transaction.transaction_number}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${transaction.spacecode}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${transaction.tenantcode}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(transaction.paidrent).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(transaction.paidbal).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.aircon || 0).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.cusa || 0).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.electricity || 0).toFixed(2))}</td>
                                <td class="px-4 py-3 whitespace-nowrap">&#x20B1;${numberWithCommas(parseFloat(charges.water || 0).toFixed(2))}</td>
                            `;
                            
                            tableBody.appendChild(row);
                        });

                        // Initialize DataTable with better styling
                        try {
                            if ($.fn.DataTable.isDataTable('#duplicatedTransactionsTable')) {
                                $('#duplicatedTransactionsTable').DataTable().destroy();
                            }
                            
                            $('#duplicatedTransactionsTable').DataTable({
                                responsive: true,
                                paging: true,
                                searching: true,
                                dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
                                language: {
                                    search: "<i class='fas fa-search mr-2'></i>",
                                    lengthMenu: "<i class='fas fa-list mr-2'></i> _MENU_ rows"
                                }
                            });
                        } catch (e) {
                            console.error("Error initializing DataTable:", e);
                        }
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-gray-500">No duplicated transactions found</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching duplicated transactions:', error);
                    document.getElementById('duplicatedTransactionsTableBody').innerHTML = 
                        '<tr><td colspan="10" class="text-center py-4 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> Error loading duplicated transactions</td></tr>';
                });
            });
        }

        // Function to parse charges from the string
        function parseCharges(chargesString) {
            if (!chargesString) return {};
            
            const charges = {
                aircon: 0,
                cusa: 0,
                electricity: 0,
                water: 0,
                table_tennis: 0,
                pay_toilet: 0,
                pay_parking: 0,
                ice_water: 0,
                ulam_vendor: 0,
                gas: 0,
                famylihan: 0,
                garbage_haul: 0,
                photocopy: 0,
                tenant_id: 0,
                function_room: 0,
                tables_chairs: 0,
                overnight_works: 0,
                vendo_sale: 0,
                zumba: 0,
                secdep: 0,
                meterdep: 0,
                utilitydep: 0,
                miscellaneous: 0
            };

            try {
                const chargeArray = chargesString.split(', ');
                chargeArray.forEach(charge => {
                    const match = charge.match(/([^:]+):\s*(\d+\.?\d*)?/);
                    if (match) {
                        // Convert the charge type to a normalized key
                        let key = match[1].trim().toLowerCase()
                            .replace(/\s+/g, '_')       // Replace spaces with underscore
                            .replace(/[&']/g, '')       // Remove special chars
                            .replace(/ice_&_water/i, 'ice_water');  // Special case
                        
                        charges[key] = parseFloat(match[2]) || 0;
                    }
                });
            } catch (e) {
                console.error("Error parsing charges:", e);
            }

            return charges;
        }

        // Suggest Space Code and Auto Complete
        function suggestSpaceCode(value) {
            var branch = document.getElementById("branch").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "suggest_spacecode.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var suggestions = JSON.parse(xhr.responseText);
                    var suggestionsContainer = document.getElementById("spacecode-suggestions");
                    var errorSpan = document.getElementById("spacecode-error");
                    suggestionsContainer.innerHTML = "";
                    
                    if (suggestions.length > 0) {
                        suggestions.forEach(function(suggestion) {
                            var option = document.createElement("div");
                            option.classList.add("cursor-pointer", "py-2", "px-4", "hover:bg-blue-50");
                            option.textContent = suggestion;
                            option.onclick = function() {
                                document.getElementById("spacecode-input").value = this.textContent;
                                suggestionsContainer.classList.add("hidden");
                                fetchTenantDetails(this.textContent);
                            };
                            suggestionsContainer.appendChild(option);
                        });
                        
                        suggestionsContainer.classList.remove("hidden");
                        errorSpan.textContent = "";
                    } else {
                        suggestionsContainer.classList.add("hidden");
                        errorSpan.textContent = "No suggestions found";
                    }

                    // Check if typed value matches any suggestion
                    if (suggestions.includes(value)) {
                        fetchTenantDetails(value);
                    } else {
                        clearTenantDetails();
                    }
                }
            };
            xhr.send("search=" + encodeURIComponent(value) + "&branch=" + encodeURIComponent(branch));
        }

        // Close suggestions when clicking outside
        document.addEventListener("click", function(event) {
            var suggestionsContainer = document.getElementById("spacecode-suggestions");
            if (event.target !== suggestionsContainer && !suggestionsContainer.contains(event.target)) {
                suggestionsContainer.classList.add("hidden");
            }
        });

        // Function to fetch tenant details
        function fetchTenantDetails(spacecode) {
            var branch = document.getElementById("branch").value;
            var selectedDate = document.getElementById("collected_date_part").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "get_tenant_details.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        document.getElementById("tenantcode").value = response.tenantcode;
                        document.getElementById("tenantname").value = response.tenantname;
                        document.getElementById("rent").value = response.dailyRent;
                        document.getElementById("rentbal").value = response.rentbal;
                        document.getElementById("runningbal").value = response.runningbal;

                        // Set readonly attribute based on editable flag
                        var isAmbulant = response.editable;
                        document.getElementById("tenantcode").readOnly = !isAmbulant;
                        document.getElementById("tenantname").readOnly = !isAmbulant;
                        document.getElementById("rent").readOnly = !isAmbulant;
                        document.getElementById("rentbal").readOnly = !isAmbulant;
                        document.getElementById("runningbal").readOnly = !isAmbulant;
                        
                        // Update payment status reminder
                        var statusContainer = document.getElementById("payment-status-container");
                        var statusText = document.getElementById("payment-status-text");
                        var statusIcon = document.getElementById("payment-status-icon");
                        
                        var todayStr = new Date().toISOString().split('T')[0];
                        var dateLabel = selectedDate === todayStr ? "TODAY" : selectedDate;
                        
                        statusContainer.classList.remove("hidden");
                        if (response.paidToday) {
                            statusContainer.className = "status-bubble status-paid";
                            statusText.textContent = "ALREADY PAID ON " + dateLabel;
                            statusIcon.className = "fas fa-check-circle text-[10px]";
                            document.getElementById("submitButton").disabled = false;
                        } else {
                            statusContainer.className = "status-bubble status-unpaid";
                            statusText.textContent = "NOT YET PAID ON " + dateLabel;
                            statusIcon.className = "fas fa-exclamation-circle text-[10px]";
                        }
                        
                        // Calculate the balance immediately
                        calculateNewBalance();
                    } else {
                        clearTenantDetails();
                    }
                }
            };
            xhr.send("spacecode=" + encodeURIComponent(spacecode) + "&branch=" + encodeURIComponent(branch) + "&date=" + encodeURIComponent(selectedDate));
        }

        // Function to clear tenant details
        function clearTenantDetails() {
            document.getElementById("tenantcode").value = "";
            document.getElementById("tenantname").value = "";
            document.getElementById("rent").value = "";
            document.getElementById("rentbal").value = "";
            document.getElementById("runningbal").value = "";
            
            document.getElementById("tenantcode").readOnly = false;
            document.getElementById("tenantname").readOnly = false;
            document.getElementById("rent").readOnly = false;
            document.getElementById("rentbal").readOnly = false;
            document.getElementById("runningbal").readOnly = false;
            
            // Reset the balance displays
            document.getElementById("total-display").textContent = "0.00";
            document.getElementById("newbalance-display").textContent = "0.00";
            document.getElementById("newrentbalance-display").textContent = "0.00";
            
            // Hide and reset status container
            var statusContainer = document.getElementById("payment-status-container");
            if (statusContainer) statusContainer.classList.add("hidden");
        }

        // Listen for input on space code field
        document.getElementById("spacecode-input").addEventListener("input", function(event) {
            var value = event.target.value.trim();
            if (value === "") {
                clearTenantDetails();
            } else {
                suggestSpaceCode(value);
            }
        });

        // Custom Select Logic for Charges
        const CHARGE_OPTIONS = [
            "Table Tennis", "Pay Toilet", "Pay Parking", "Ice & Water", "Ulam Vendor",
            "Gas", "Famylihan", "Garbage Haul", "Photocopy", "Tenant ID",
            "Function Room", "Tables & Chairs", "Overnight Works", "Vendo Sale",
            "Zumba", "Sec Dep", "Meter Dep", "Utility Dep", "Miscellaneous", "Forfeited Items"
        ];

        window.openCustomSelect = function(inputEl) {
            // Close all others first
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
            
            const wrapper = inputEl.closest('.custom-select');
            const menu = wrapper.querySelector('.dropdown-menu');
            const search = wrapper.querySelector('.dropdown-menu input');
            
            menu.classList.remove('hidden');
            search.value = '';
            window.renderCustomSelectOptions(wrapper, '');
            search.focus();
        };

        window.renderCustomSelectOptions = function(wrapper, filterText) {
            const optionsContainer = wrapper.querySelector('.dropdown-options');
            const input = wrapper.querySelector('input[type="text"][readonly]');
            const hidden = wrapper.querySelector('input[type="hidden"]');
            const menu = wrapper.querySelector('.dropdown-menu');
            
            optionsContainer.innerHTML = '';
            
            // Add a Clear Option at the very top
            const clearDiv = document.createElement('div');
            clearDiv.className = 'px-3 py-2 cursor-pointer hover:bg-red-50 text-red-500 font-bold text-sm border-b border-gray-100 transition-colors flex justify-between items-center';
            clearDiv.innerHTML = '<span><i class="fas fa-times-circle mr-1"></i> Clear Selection</span>';
            clearDiv.onclick = (e) => {
                e.stopPropagation();
                input.value = '';
                hidden.value = '';
                menu.classList.add('hidden');
                
                // Clear the adjacent amount input as well
                const amountInput = wrapper.parentElement.nextElementSibling?.querySelector('input[name="otheramount[]"]');
                if (amountInput) amountInput.value = '';
                
                if(typeof calculateNewBalance === 'function') calculateNewBalance();
            };
            optionsContainer.appendChild(clearDiv);
            
            const filtered = CHARGE_OPTIONS.filter(opt => opt.toLowerCase().includes(filterText.toLowerCase()));
            
            filtered.forEach(opt => {
                const div = document.createElement('div');
                div.className = 'px-3 py-2 cursor-pointer hover:bg-blue-50 text-gray-700 text-sm border-b border-gray-50 last:border-0 transition-colors';
                div.textContent = opt;
                div.onclick = (e) => {
                    e.stopPropagation();
                    input.value = opt;
                    hidden.value = opt;
                    menu.classList.add('hidden');
                    if(typeof calculateNewBalance === 'function') calculateNewBalance();
                };
                optionsContainer.appendChild(div);
            });
            
            if (filtered.length === 0) {
                optionsContainer.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">No results found</div>';
            }
        };

        // Attach event delegation for the search input
        document.addEventListener('input', function(e) {
            if (e.target.matches('.dropdown-menu input')) {
                const wrapper = e.target.closest('.custom-select');
                window.renderCustomSelectOptions(wrapper, e.target.value);
            }
        });

        // Allow backspace/delete to clear the custom select
        document.addEventListener('keydown', function(e) {
            if (e.target.matches('.custom-select input[readonly]')) {
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    e.preventDefault();
                    e.target.value = '';
                    const hidden = e.target.closest('.custom-select').querySelector('input[type="hidden"]');
                    if (hidden) hidden.value = '';
                    if (typeof calculateNewBalance === 'function') calculateNewBalance();
                }
            }
        });

        // Prevent clicks inside menu from closing it
        document.addEventListener('click', function(e) {
            if (e.target.closest('.dropdown-menu')) {
                // Clicking inside the menu (like on the search box) should not close it
                return;
            }
            if (e.target.matches('.custom-select input[readonly]')) {
                // Handled by openCustomSelect
                return;
            }
            // Clicking anywhere else closes all menus
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        });
    </script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#collected_date_part", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "m/d/Y",
                disableMobile: true,
                onChange: function(selectedDates, dateStr, instance) {
                    updateDateTime();
                }
            });
            flatpickr("#collected_time_part", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i:S",
                altInput: true,
                altFormat: "h:i:S K",
                disableMobile: true,
                enableSeconds: true,
                onChange: function(selectedDates, dateStr, instance) {
                    updateDateTime();
                }
            });
        });
    </script>
</body>

</html>


