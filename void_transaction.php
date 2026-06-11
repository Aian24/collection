<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Debug: Log page load
error_log("Void transaction page loaded");

// Initialize variables
$message = '';
$message_type = ''; // 'success' or 'error'

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

// Set the timezone to Manila
date_default_timezone_set('Asia/Manila');

// Get the current date and time
$void_date = date('Y-m-d H:i:s');

// Redirect after form submission to avoid resubmission
if (isset($_POST['void_submit'])) {
    // Debug: Log form submission
    error_log("Form submitted - void_submit detected");
    error_log("POST data: " . print_r($_POST, true));
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed");
        $_SESSION['void_transaction_message'] = 'Invalid form submission. Please try again.';
        $_SESSION['void_transaction_message_type'] = 'error';
        header("Location: void_transaction.php");
        exit();
    }
    
    $transaction_number = $_POST['transaction_number'];
    $branch = $_POST['branch'];
    $note = $_POST['note'];
    
    // Debug: Log the received values
    error_log("Transaction Number: " . $transaction_number);
    error_log("Branch: " . $branch);
    error_log("Note: " . $note);

    // Validate branch selection and set table name dynamically
    $valid_branches = ['nova', 'sanko', 'apm'];
    if (!in_array($branch, $valid_branches)) {
        error_log("Invalid branch selected: " . $branch);
        $message = 'Invalid branch selected.';
        $message_type = 'error';
    } else {
        error_log("Branch validation passed: " . $branch);
        
        // Dynamically create the correct table name based on the branch selected
        if ($branch === 'sanko') {
            $branch_table = 'collected'; // Use the same "collected" table for "sanko" branch
        } else {
            $branch_table = 'collected' . $branch;
        }
        
        error_log("Using table: " . $branch_table);

        // Start the transaction
        $conn->begin_transaction();

        try {
            // First, check if the charges column exists in the table
            $checkColumnQuery = "SHOW COLUMNS FROM $branch_table LIKE 'charges'";
            $columnResult = $conn->query($checkColumnQuery);
            $chargesColumnExists = $columnResult && $columnResult->num_rows > 0;
            
            // Build the SELECT query based on whether charges column exists
            if ($chargesColumnExists) {
                $selectQuery = "SELECT rentbal, runningbal, tenantcode, spacecode, rent, paidrent, paidbal, charges, collector, tenantname FROM $branch_table WHERE transaction_number = ?";
            } else {
                $selectQuery = "SELECT rentbal, runningbal, tenantcode, spacecode, rent, paidrent, paidbal, collector, tenantname FROM $branch_table WHERE transaction_number = ?";
            }
            
            // Fetch the original values from the respective branch table (collectednova, collectedapm, or collected for sanko)
            $stmt = $conn->prepare($selectQuery);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('s', $transaction_number);
            $stmt->execute();
            $result = $stmt->get_result();
            $transaction = $result->fetch_assoc();

            error_log("Query executed: " . $selectQuery . " with transaction_number: " . $transaction_number);
            error_log("Transaction found: " . ($transaction ? 'Yes' : 'No'));

            if (!$transaction) {
                // Transaction not found
                error_log("Transaction not found in table: " . $branch_table);
                throw new Exception("Transaction not found.");
            }

            // Extract values
            $original_rentbal = $transaction['rentbal'];
            $original_runningbal = $transaction['runningbal'];
            $tenantcode = $transaction['tenantcode'];
            $spacecode = $transaction['spacecode'];
            $rent = $transaction['rent'];
            $paidrent = $transaction['paidrent'];
            $paidbal = $transaction['paidbal'];
            $charges = $chargesColumnExists ? ($transaction['charges'] ?? '') : '';
            $collector = $transaction['collector'];
            $tenantname = $transaction['tenantname'];

            // Debug: Log the charges value
            error_log("Charges column exists: " . ($chargesColumnExists ? 'Yes' : 'No'));
            error_log("Charges value from database: " . $charges);
            
            // Ensure charges is not null and convert to proper format
            if ($charges === null) {
                $charges = '';
            }
            // Don't convert to float since charges might be a string (comma-separated values)
            $charges = trim($charges);

            // Debug: Log the processed charges value
            error_log("Processed charges value: " . $charges);

            // Rollback in the collected table (collectednova, collectedapm, or collected for sanko)
            $stmt = $conn->prepare("UPDATE $branch_table SET rentbal = ?, runningbal = ? WHERE transaction_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('dds', $original_rentbal, $original_runningbal, $transaction_number);
            $stmt->execute();

            // Rollback in the selected branch table (collectednova, collectedsanko, collectedapm)
            $stmt = $conn->prepare("UPDATE $branch_table SET rentbal = ?, runningbal = ? WHERE tenantcode = ? AND spacecode = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('ddss', $original_rentbal, $original_runningbal, $tenantcode, $spacecode);
            $stmt->execute();

            // Prepare to insert into void table
            $branch_name = ucfirst($branch) . ' Branch';
            
            // Debug: Log all values before insertion
            error_log("Inserting into void table:");
            error_log("Transaction Number: " . $transaction_number);
            error_log("Branch: " . $branch_name);
            error_log("Note: " . $note);
            error_log("Rent: " . $rent);
            error_log("Rentbal: " . $original_rentbal);
            error_log("Runningbal: " . $original_runningbal);
            error_log("Paidrent: " . $paidrent);
            error_log("Paidbal: " . $paidbal);
            error_log("Charges: " . $charges);
            error_log("Collector: " . $collector);
            error_log("Tenantname: " . $tenantname);
            error_log("Spacecode: " . $spacecode);
            error_log("Void Date: " . $void_date);
            
            $stmt = $conn->prepare("INSERT INTO void (transaction_number, branch, note, rent, rentbal, runningbal, paidrent, paidbal, charges, collector, tenantname, spacecode, void_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Bind parameters with correct types
            $stmt->bind_param('sssdddddsssss', $transaction_number, $branch_name, $note, $rent, $original_rentbal, $original_runningbal, $paidrent, $paidbal, $charges, $collector, $tenantname, $spacecode, $void_date);
            
            // Debug: Log the bind_param string and charges value
            error_log("Bind param string: sssdddddsssss");
            error_log("Charges value being bound: " . $charges);
            
            // Debug: Log all parameter values and types
            error_log("Parameter values:");
            error_log("1. transaction_number: " . $transaction_number . " (type: " . gettype($transaction_number) . ")");
            error_log("2. branch_name: " . $branch_name . " (type: " . gettype($branch_name) . ")");
            error_log("3. note: " . $note . " (type: " . gettype($note) . ")");
            error_log("4. rent: " . $rent . " (type: " . gettype($rent) . ")");
            error_log("5. original_rentbal: " . $original_rentbal . " (type: " . gettype($original_rentbal) . ")");
            error_log("6. original_runningbal: " . $original_runningbal . " (type: " . gettype($original_runningbal) . ")");
            error_log("7. paidrent: " . $paidrent . " (type: " . gettype($paidrent) . ")");
            error_log("8. paidbal: " . $paidbal . " (type: " . gettype($paidbal) . ")");
            error_log("9. charges: " . $charges . " (type: " . gettype($charges) . ")");
            error_log("10. collector: " . $collector . " (type: " . gettype($collector) . ")");
            error_log("11. tenantname: " . $tenantname . " (type: " . gettype($tenantname) . ")");
            error_log("12. spacecode: " . $spacecode . " (type: " . gettype($spacecode) . ")");
            error_log("13. void_date: " . $void_date . " (type: " . gettype($void_date) . ")");
            
            // Debug: Check if the insert was successful
            if (!$stmt->execute()) {
                error_log("Insert failed: " . $stmt->error);
                throw new Exception("Insert failed: " . $stmt->error);
            } else {
                error_log("Insert successful. Affected rows: " . $stmt->affected_rows);
            }

            // Delete the transaction record from the respective collected table (collectednova, collectedsanko, collectedapm)
            $stmt = $conn->prepare("DELETE FROM $branch_table WHERE transaction_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('s', $transaction_number);
            $stmt->execute();

            // Commit the transaction
            $conn->commit();
            $_SESSION['void_transaction_message'] = 'Transaction voided successfully.';
            $_SESSION['void_transaction_message_type'] = 'success';

            // Regenerate CSRF token to prevent replay attacks
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // TEMPORARY DEBUG: Show the user what values were processed
            $_SESSION['debug_info'] = [
                'transaction_number' => $transaction_number,
                'branch_table' => $branch_table,
                'all_values' => [
                    'rent' => $rent,
                    'rentbal' => $original_rentbal,
                    'runningbal' => $original_runningbal,
                    'paidrent' => $paidrent,
                    'paidbal' => $paidbal,
                    'charges' => $charges,
                    'collector' => $collector,
                    'tenantname' => $tenantname,
                    'spacecode' => $spacecode
                ]
            ];

            // Redirect to prevent form resubmission
            header("Location: void_transaction.php");
            exit();

        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $_SESSION['void_transaction_message'] = $e->getMessage();
            $_SESSION['void_transaction_message_type'] = 'error';
            
            // Regenerate CSRF token to prevent replay attacks
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Redirect to prevent form resubmission even on error
            header("Location: void_transaction.php");
            exit();
        }
    }
}

mysqli_close($conn);
ob_end_flush(); // End output buffering and flush output
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Void Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Google Fonts for fancy styling -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <!-- jQuery first -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root {
            --primary:      #2563eb;
            --primary-dark: #1d4ed8;
            --danger:       #ef4444;
            --success:      #22c55e;
            --warning:      #f59e0b;
            --surface:      #ffffff;
            --surface2:     #f1f5f9;
            --border:       #e2e8f0;
            --text:         #1e293b;
            --text-muted:   #64748b;
            --nav-h:        56px;
            --bot-h:        68px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--surface2);
            color: var(--text);
            margin: 0; padding: 0;
            padding-top: var(--nav-h);
            padding-bottom: var(--bot-h);
            min-height: 100vh;
        }
        /* TOP BAR */
        .top-bar {
            position: fixed; top: 0; left: 0; right: 0;
            height: var(--nav-h);
            background: linear-gradient(90deg,#2563eb,#1d4ed8);
            color: #fff;
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 0 14px;
            z-index: 40;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }
        .brand { display:flex; align-items:center; gap:10px; }
        .brand i { font-size:22px; opacity:.9; }
        .brand-name { font-size:16px; font-weight:700; letter-spacing:.3px; }
        .brand-sub  { font-size:10px; font-weight:500; opacity:.75; margin-top:-2px; }
        .user-pill {
            display:flex; align-items:center; gap:6px;
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.25);
            border-radius:999px;
            padding:6px 12px; color:#fff; cursor:pointer;
            font-size:13px; font-weight:600;
            transition: background .2s;
        }
        .user-pill:hover { background:rgba(255,255,255,.25); }
        /* BOTTOM NAV */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: var(--bot-h);
            background: #fff;
            display: flex; align-items: stretch;
            border-top: 1px solid var(--border);
            box-shadow: 0 -2px 12px rgba(0,0,0,.08);
            z-index: 40;
        }
        .bot-btn {
            flex: 1; display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            gap:2px; font-size:10px; font-weight:600;
            color: var(--text-muted); background:none; border:none;
            cursor:pointer; text-decoration:none;
            padding: 6px 2px;
            transition: color .2s, background .2s;
            position: relative;
        }
        .bot-btn i { font-size:20px; }
        .bot-btn i { font-size: 20px; transition: transform .2s; }
        .bot-btn:active i { transform: scale(0.9); }
        
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
        /* SIDE DRAWER */
        .side-menu {
            background: linear-gradient(180deg,#2563eb,#1d4ed8);
        }
        /* PAGE CARD */
        .page-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            padding: 24px 20px;
            max-width: 520px;
            margin: 20px auto;
        }
        .page-title {
            font-size: 18px; font-weight: 700;
            color: var(--text); margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        /* FORM ELEMENTS */
        .pos-input {
            border-radius: 10px;
            border: 1.5px solid var(--border);
            padding: 12px 14px;
            font-size: 15px;
            width: 100%;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            display: block;
        }
        .pos-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.15);
        }
        .pos-label {
            font-weight: 600; font-size: 13px;
            color: var(--text-muted);
            display: flex; align-items: center; gap: 6px;
            margin-bottom: 6px;
        }
        .pos-label i { color: var(--primary); }
        .pos-button {
            border-radius: 10px;
            transition: all 0.2s ease;
            font-weight: 600;
            border: none; cursor: pointer;
        }
        .pos-button:hover { transform: translateY(-1px); }
        .pos-button:active { transform: translateY(0); }
        .submit-btn {
            width: 100%; padding: 14px;
            background: linear-gradient(90deg,#2563eb,#1d4ed8);
            color: #fff; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            gap: 8px; cursor: pointer;
            box-shadow: 0 4px 14px rgba(37,99,235,.35);
            transition: all .2s;
        }
        .submit-btn:hover { box-shadow: 0 6px 18px rgba(37,99,235,.45); transform:translateY(-1px); }
        .section-divider {
            display: flex; align-items: center; margin: 18px 0 14px;
        }
        .section-divider::before, .section-divider::after {
            content:''; flex:1; border-bottom: 1.5px solid var(--border);
        }
        .section-divider-label {
            padding: 0 12px; font-weight: 700; font-size: 11px;
            color: var(--primary); text-transform: uppercase; letter-spacing: 1px;
        }
        @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
        .animate-fadeIn { animation: fadeIn .35s ease; }

    </style>
</head>
<body>
    <!-- TOP BAR -->
    <nav class="top-bar">
        <div class="brand">
            <i class="fas fa-cash-register"></i>
            <div>
                <div class="brand-name"><?php
                    $db = isset($_SESSION['branch']) ? $_SESSION['branch'] : '';
                    echo ($db==='Sanko Market') ? 'Sanko Market' : htmlspecialchars($db);
                    ?></div>
                <div class="brand-sub">Collection POS</div>
            </div>
        </div>
        <button class="user-pill" id="burger-menu-btn">
            <i class="fas fa-user-circle"></i>
            <span><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?></span>
            <i class="fas fa-chevron-down" style="font-size:10px;opacity:.7"></i>
        </button>
    </nav>
    <input type="hidden" id="branch" value="<?php echo htmlspecialchars($_SESSION['branch'] ?? ''); ?>">

    <!-- SIDE DRAWER -->
    <div id="side-nav" class="hidden fixed inset-0 z-50">
        <div class="flex h-full">
            <div id="side-nav-backdrop" class="bg-black opacity-50 flex-1 h-full"></div>
            <div class="side-menu w-72 h-full transform transition-transform duration-300 ease-in-out translate-x-full ml-auto">
                <div class="flex justify-between items-center p-4 border-b border-blue-800">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-user-circle text-white text-3xl"></i>
                        <div>
                            <div class="text-white font-bold"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?></div>
                            <div class="text-blue-200 text-xs"><?php
                    $db = isset($_SESSION['branch']) ? $_SESSION['branch'] : '';
                    echo ($db==='Sanko Market') ? 'Sanko Market' : htmlspecialchars($db);
                    ?></div>
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
                </div>
                <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-blue-800">
                    <a href="index.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-red-700 transition">
                        <i class="fas fa-sign-out-alt w-5 text-center text-red-300"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- BOTTOM NAV -->
    <nav class="bottom-nav">
        <a href="transactions.php" class="bot-btn">
            <i class="fas fa-list-ul"></i>
            Transactions
        </a>
        <a href="monitoring.php" class="bot-btn">
            <i class="fas fa-chart-line"></i>
            <span id="notificationBadge" class="badge" style="display:none"></span>
            Monitoring
        </a>
        <a href="void_transaction.php" class="bot-btn active warn">
            <i class="fas fa-ban"></i>
            Void
        </a>
        <a href="user.php" class="bot-btn success-btn">
            <i class="fas fa-plus-circle"></i>
            New
        </a>
        <a href="index.php" class="bot-btn danger">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </nav>
            </div>
        </div>
    </div>

    <div class="px-3">
        <div class="page-card animate-fadeIn">
            <h1 class="section-title">Void a Transaction</h1>
            
            <!-- Debug Information -->
            <?php /* if (isset($_POST) && !empty($_POST)): ?>
                <div class="mb-6 p-4 rounded-lg bg-yellow-100 text-yellow-700">
                    <h3 class="font-bold mb-2">Debug: Form Submitted</h3>
                    <div class="text-sm">
                        <p><strong>POST Data:</strong></p>
                        <pre><?php print_r($_POST); ?></pre>
                        <p><strong>CSRF Token Valid:</strong> <?php echo (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) ? 'Yes' : 'No'; ?></p>
                    </div>
                </div>
            <?php endif; */ ?>
            
            <?php if (isset($_SESSION['void_transaction_message'])): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $_SESSION['void_transaction_message_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <p class="flex items-center">
                        <?php if ($_SESSION['void_transaction_message_type'] === 'success'): ?>
                            <i class="fas fa-check-circle mr-2"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($_SESSION['void_transaction_message']); ?>
                    </p>
                </div>
                <?php 
                // Clear the message after displaying it
                unset($_SESSION['void_transaction_message']); 
                unset($_SESSION['void_transaction_message_type']);
                ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['debug_info'])): ?>
                <div class="mb-6 p-4 rounded-lg bg-blue-100 text-blue-700">
                    <h3 class="font-bold mb-2">Details:</h3>
                    <div class="text-sm">
                        <p><strong>Transaction Number:</strong> <?php echo htmlspecialchars($_SESSION['debug_info']['transaction_number']); ?></p>
                        <p><strong>Branch Table:</strong> <?php echo htmlspecialchars($_SESSION['debug_info']['branch_table']); ?></p>
                        <p><strong>All Values:</strong></p>
                        <ul class="ml-4">
                            <?php foreach ($_SESSION['debug_info']['all_values'] as $key => $value): ?>
                                <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php 
                // Clear the debug info after displaying it
                unset($_SESSION['debug_info']);
                ?>
            <?php endif; ?>
            
            <form action="void_transaction.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="section-divider">
                    <div class="section-divider-label"><i class="fas fa-info-circle mr-2"></i>Transaction Details</div>
                </div>
                
                <div class="mb-4">
                    <label class="pos-label" for="transaction_number">
                        <i class="fas fa-hashtag"></i>Transaction Number
                    </label>
                    <input type="text" id="transaction_number" name="transaction_number" 
                           class="pos-input w-full" required>
                </div>
                
                <div class="mb-4">
                    <label class="pos-label" for="branch">
                        <i class="fas fa-building"></i>Branch
                    </label>
                    <?php 
                    // Get user's branch from session and convert to form format
                    $userBranch = isset($_SESSION["branch"]) ? $_SESSION["branch"] : "";
                    $formBranch = '';
                    
                    // Convert branch name to form format
                    if ($userBranch === 'Sanko Market') {
                        $formBranch = 'sanko';
                    } elseif ($userBranch === 'Nova Market') {
                        $formBranch = 'nova';
                    } elseif ($userBranch === 'APM') {
                        $formBranch = 'apm';
                    }
                    ?>
                    <select id="branch" name="branch" class="pos-input w-full" required disabled>
                        <option value="sanko" <?php echo ($formBranch === 'sanko') ? 'selected' : ''; ?>>Sanko Branch</option>
                        <option value="nova" <?php echo ($formBranch === 'nova') ? 'selected' : ''; ?>>Nova Branch</option>
                        <option value="apm" <?php echo ($formBranch === 'apm') ? 'selected' : ''; ?>>APM Branch</option>
                    </select>
                    <input type="hidden" name="branch" value="<?php echo htmlspecialchars($formBranch); ?>">
                    <p class="text-sm text-gray-600 mt-1">Branch automatically selected based on your login</p>
                </div>
                
                <div class="mb-6">
                    <label class="pos-label" for="note">
                        <i class="fas fa-sticky-note"></i>Reason for Voiding
                    </label>
                    <textarea id="note" name="note" rows="3" 
                              class="pos-input w-full" required></textarea>
                </div>
                
                <div class="flex justify-center">
                    <button type="submit" name="void_submit" id="submitBtn"
                            class="submit-btn" style="background:linear-gradient(90deg,#dc2626,#b91c1c);box-shadow:0 4px 14px rgba(220,38,38,.35)">
                        <i class="fas fa-ban mr-2"></i>Void Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="transactions.php" class="bot-btn tx-btn">
            <i class="fas fa-list-ul"></i>
            TXS
        </a>
        <a href="printsummary.php" class="bot-btn sum-btn">
            <i class="fas fa-database"></i>
            Summ
        </a>
        <a href="#" onclick="openDupModal(event)" id="notificationButton" class="bot-btn dup-btn">
            <i class="fas fa-exclamation-circle"></i>
            <span id="notificationCountBadge" class="badge" style="display:none">0</span>
            Dups
        </a>
        <a href="user.php" class="bot-btn new-btn">
            <i class="fas fa-plus-circle"></i>
            New
        </a>
        <a href="monitoring.php" class="bot-btn mon-btn">
            <i class="fas fa-chart-line"></i>
            <span id="notificationBadge" class="badge" style="display:none"></span>
            Mon
        </a>
        <a href="void_transaction.php" class="bot-btn void-btn active">
            <i class="fas fa-ban"></i>
            Void
        </a>
        <a href="reprint.php" class="bot-btn print-btn">
            <i class="fas fa-print"></i>
            Print
        </a>
        <button type="button" onclick="showLogoutModal()" class="bot-btn stop-btn">
            <i class="fas fa-sign-out-alt"></i>
            Exit
        </button>
    </nav>

    <!-- Logout Modern Modal -->
    <div id="logoutConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-[60] animate-fadeIn">
        <div class="mobile-modal bg-white rounded-3xl p-8 max-w-[340px] w-full mx-4 shadow-2xl relative overflow-hidden text-left">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-red-50 rounded-full opacity-50"></div>
            <div class="text-center relative z-10">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-2xl bg-red-500 mb-6 shadow-xl shadow-red-100">
                    <i class="fas fa-sign-out-alt text-white text-3xl"></i>
                </div>
                <h3 class="text-2xl font-black text-gray-900 mb-2 font-sans">Going so soon?</h3>
                <p class="text-gray-500 mb-8 px-2 font-medium font-sans">Are you sure you want to exit and end your collection session?</p>
                
                <div class="flex flex-col gap-3">
                    <a href="index.php" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 px-6 rounded-2xl shadow-lg shadow-red-200 transition-all active:scale-95 flex items-center justify-center gap-2 font-sans">
                        <i class="fas fa-door-open mr-1"></i> YES, EXIT
                    </a>
                    <button onclick="hideLogoutModal()" class="w-full bg-gray-50 hover:bg-gray-100 text-gray-500 font-bold py-4 px-6 rounded-2xl transition-all active:scale-95 font-sans">
                        KEEP WORKING
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
                    <button onclick="closeDupModal()" class="text-white p-1 hover:opacity-75">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="p-3 overflow-y-auto" style="max-height:70vh">
                    <div id="dupCardsContainer" class="flex flex-col gap-2">
                        <div class="text-center py-6 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="branch" value="<?php echo htmlspecialchars($formBranch); ?>">
    <script src="nav_badge.js"></script>
    <script>
        function showLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.remove('hidden');
        }
        function hideLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.add('hidden');
        }

        // Duplicate Card Modal Logic
        function closeDupModal() {
            document.getElementById('duplicatedTransactionsModal').classList.add('hidden');
        }

        function fmtDate(d) {
            const dt = new Date(d);
            return isNaN(dt) ? d : dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})+' '+dt.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
        }
        function nFmt(v) { return parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
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
        function openDupModal(e) {
            if(e) e.preventDefault();
            const m = document.getElementById('duplicatedTransactionsModal'), c = document.getElementById('dupCardsContainer');
            if(!m || !c) return;
            m.classList.remove('hidden');
            c.innerHTML = '<div style="text-align:center;padding:24px;color:#94a3b8"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>';
            fetch('fetch_duplicated_transactions.php').then(r=>r.json()).then(data=>{
                c.innerHTML = data.length ? data.map(buildDupCardUser).join('') : '<div style="text-align:center;padding:32px;color:#22c55e"><i class="fas fa-check-circle" style="font-size:32px;display:block;margin-bottom:8px"></i>No duplicates found!</div>';
            }).catch(()=>c.innerHTML='<div style="text-align:center;padding:24px;color:#ef4444">Error loading</div>');
        }
    </script>
</body>
</html>
