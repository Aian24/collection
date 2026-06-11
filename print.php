<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

$username = $_SESSION["username"];

// Fetch the last name from the users table
$sql_user = "SELECT * FROM users WHERE username = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("s", $username);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows > 0) {
    $row_user = $result_user->fetch_assoc();
    $lname = $row_user["lname"]; // Assign the last name to $lname variable
} else {
    $lname = "Unknown";
}

// Close the statement and result set for users table
$stmt_user->close();

// Determine the correct table to fetch the latest transaction
$branch = $_SESSION["branch"];
$tableName = ($branch === 'Nova Market') ? 'collectednova' : (($branch === 'APM') ? 'collectedapm' : 'collected');

// Fetch the latest transaction for the current user
$sql = "SELECT * FROM $tableName WHERE username = ? ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Output data of the latest transaction for the current user
    $row = $result->fetch_assoc();
    $transaction_number = $row["transaction_number"];
    $collected_date = $row["collected_date"];
    $tenantcode = $row["tenantcode"];
    $spacecode = $row["spacecode"];
    $tenantname = $row["tenantname"];
    $dailyRent = $row["rent"];
    $runningBalance = $row["runningbal"];
    $rentbal = $row["rentbal"];
    $paidrent = $row["paidrent"];
    $paidbal = $row["paidbal"];
    $total = $row["total"];
    $newbalance = $row["newbalance"];
    $newrentbalance = $row["newrentbalance"];

    // Close the statement for latest transaction fetch
    $stmt->close();

    // Fetch all charges with the given transaction number to get all charges
    $sql_charges = "SELECT * FROM $tableName WHERE transaction_number = ?";
    $stmt_charges = $conn->prepare($sql_charges);
    $stmt_charges->bind_param("d", $transaction_number); // Use "d" for transaction_number if it's numeric
    $stmt_charges->execute();
    $result_charges = $stmt_charges->get_result();

    // Start HTML output
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/png" href="../images/lc.png">
        <title>Receipt</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <style>
            @media print {
                @page {
                    size: 58mm auto;
                    margin: 0;
                }

                body,
                p,
                h1 {
                    font-size: 12px;
                    margin: 0;
                }

                .container {
                    width: 58mm;
                    padding: 0;
                }

                .print-logo {
                    width: 100%;
                    height: auto;
                    margin-bottom: 4%;
                }

                .print-button {
                    display: none;
                }

                nav,
                #side-nav {
                    display: none;
                }

                .charges-section {
                    width: 100%;
                }

                .charges-section div {
                    display: flex;
                    justify-content: center;
                }
            }
        </style>
    </head>

    <body>

        <!-- Navigation Bar -->
        <nav class="bg-gray-800 p-4 mb-2 sm:mb-0 w-full">
            <div class="flex justify-between items-center w-full mx-auto">
                <div class="sm:hidden">
                    <button id="burger-menu-btn" class="text-white focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                        </svg>
                    </button>
                </div>
                <div style="text-align: right;">
                    <a href="user.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Back</a>
                </div>
                <div class="hidden sm:flex sm:items-center sm:space-x-4">
                    <a href="index.php" class="text-white">Logout</a>
                    <a href="user.php" class="text-white">Create Collection</a>
                </div>
            </div>
        </nav>

        <div id="side-nav" class="hidden bg-gray-800 fixed inset-0 z-50">
            <div class="flex justify-end p-4">
                <button id="close-btn" class="text-white focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="flex flex-col items-center mt-10 space-y-4">
                <a href="user.php" class="text-white">Create Collection</a>
                <a href="index.php" class="text-white">Logout</a>
            </div>
        </div>

        <div class="container mx-auto mt-8">
            <div class="max-w-lg mx-auto text-center">
                <img src="images/lc.png" alt="Logo" class="print-logo mb-2">
                <p class="mb-2 font-bold">Slip Receipt</p>
            </div>

            <div class="space-y-1 text-xs border-b border-black pb-2 mb-2">
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Transaction #:</span>
                    <span class="font-black text-black"><?php echo isset($transaction_number) ? $transaction_number : ''; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Date & Time:</span>
                    <span class="font-black text-black"><?php echo isset($collected_date) ? date('m/d/y, g:i A', strtotime($collected_date)) : ''; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Collector:</span>
                    <span class="font-black text-black"><?php echo isset($lname) ? $lname : ''; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Branch:</span>
                    <span class="font-black text-black"><?php echo isset($branch) ? $branch : ''; ?></span>
                </div>
            </div>

            <div class="space-y-1 mt-2">
                <div class="flex justify-between text-xs border-b border-gray-100 pb-1">
                    <span class="text-black uppercase font-semibold">Space Code:</span>
                    <span class="font-black text-black"><?php echo isset($spacecode) ? $spacecode : ''; ?></span>
                </div>
                <div class="flex justify-between text-xs border-b border-gray-100 pb-1">
                    <span class="text-black uppercase font-semibold">Tenant:</span>
                    <span class="font-black text-black text-right"><?php echo isset($tenantname) ? $tenantname : ''; ?></span>
                </div>
            </div>

            <div class="space-y-1 my-2 text-[10px]">
                <div class="flex justify-between border-b border-dotted border-gray-200">
                    <span class="text-black uppercase font-semibold">Code:</span>
                    <span class="font-black text-black"><?php echo isset($tenantcode) ? $tenantcode : ''; ?></span>
                </div>
                <div class="flex justify-between border-b border-dotted border-gray-200">
                    <span class="text-black uppercase font-semibold">Daily Rent:</span>
                    <span class="font-black text-black"><?php echo isset($dailyRent) ? number_format((float)$dailyRent, 2) : ''; ?></span>
                </div>
                <div class="flex justify-between border-b border-dotted border-gray-200">
                    <span class="text-black uppercase font-semibold">Arrear Bal:</span>
                    <span class="font-black text-black"><?php echo isset($runningBalance) ? number_format((float)$runningBalance, 2) : ''; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Rent Bal:</span>
                    <span class="font-black text-black"><?php echo isset($rentbal) ? number_format((float)$rentbal, 2) : ''; ?></span>
                </div>
            </div>

            <div class="border-t-2 border-black my-2"></div>

            <!-- Charges Section -->
            <?php
            function parseAndFormatCharges($chargesString) {
                if (empty($chargesString)) return [];
                $parts = explode(',', $chargesString);
                $formatted = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (strpos($part, ':') !== false) {
                        list($name, $val) = explode(':', $part, 2);
                        $formatted[] = [
                            'name' => trim($name),
                            'amount' => number_format((float)trim(str_replace(',', '', $val)), 2)
                        ];
                    }
                }
                return $formatted;
            }

            $hasCharges = false;
            $allCharges = [];
            if ($result_charges->num_rows > 0) {
                $result_charges->data_seek(0);
                while ($row_charges = $result_charges->fetch_assoc()) {
                    if (!empty($row_charges['charges'])) {
                        $parsed = parseAndFormatCharges($row_charges['charges']);
                        $allCharges = array_merge($allCharges, $parsed);
                        $hasCharges = true;
                    }
                }
            }

            echo '<div class="space-y-1 mb-2">';
            echo '<div class="text-[9px] font-black text-center border-b border-gray-200 pb-1 mb-1 tracking-widest uppercase">Payment Itemization</div>';
            
            $subTotalCharges = 0;
            
            // Rent line
            if (isset($paidrent) && $paidrent > 0) {
                echo '<div class="flex justify-between text-xs">';
                echo '<span>Daily Rent Payment</span>';
                echo '<span class="font-bold">' . number_format((float)$paidrent, 2) . '</span>';
                echo '</div>';
            }

            // Balance line
            if (isset($paidbal) && $paidbal > 0) {
                echo '<div class="flex justify-between text-xs">';
                echo '<span>Arrear Balance Payment</span>';
                echo '<span class="font-bold">' . number_format((float)$paidbal, 2) . '</span>';
                echo '</div>';
            }

            // Rent/Arrear Subtotal line
            $paidTotal = (isset($paidrent) ? (float)$paidrent : 0) + (isset($paidbal) ? (float)$paidbal : 0);
            if ($paidTotal > 0) {
                echo '<div class="flex justify-between text-[10px] pt-1 border-t border-dotted border-gray-300 mb-2">';
                echo '<span class="text-black font-bold uppercase">Rent & Arrear Total:</span>';
                echo '<span class="font-black text-black">(' . number_format($paidTotal, 2) . ')</span>';
                echo '</div>';
            }

            // Individual Charges
            if ($hasCharges) {
                foreach ($allCharges as $c) {
                    $cleanAmount = (float)str_replace(',', '', $c['amount']);
                    $subTotalCharges += $cleanAmount;
                    echo '<div class="flex justify-between text-xs">';
                    echo '<span>' . htmlspecialchars($c['name']) . '</span>';
                    echo '<span class="font-bold">' . $c['amount'] . '</span>';
                    echo '</div>';
                }
                // Show Sub-total for Charges
                echo '<div class="flex justify-between text-[10px] pt-1 border-t border-dotted border-gray-300">';
                echo '<span class="text-black font-bold uppercase">Total Charges:</span>';
                echo '<span class="font-black text-black">(' . number_format($subTotalCharges, 2) . ')</span>';
                echo '</div>';
            }
            echo '</div>';
            ?>

            <div class="border-t-2 border-black pt-2 mt-2">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-black uppercase">Grand Total</span>
                    <span class="text-xl font-black">&#x20B1;<?php echo isset($total) ? number_format((float)$total, 2) : '0.00'; ?></span>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-2 mt-2 space-y-1 bg-gray-50 p-2 rounded">
                <div class="flex justify-between text-[10px] font-bold">
                    <span class="text-black uppercase font-semibold">Remaining Arrear:</span>
                    <span class="text-black font-black"><?php echo isset($newbalance) ? number_format((float)$newbalance, 2) : '0.00'; ?></span>
                </div>
                <div class="flex justify-between text-[10px] font-bold">
                    <span class="text-black uppercase font-semibold">Remaining Rent Bal:</span>
                    <span class="text-black font-black"><?php echo isset($newrentbalance) ? number_format((float)$newrentbalance, 2) : '0.00'; ?></span>
                </div>
            </div>

            <!-- Note section -->
            <div class="mt-6 text-center">
                <p class="text-[10px] leading-tight text-gray-600 font-medium">Thank you for your payment.<br>Keep this receipt for your records.</p>
                <div class="mt-4 border-t border-dotted border-gray-300 pt-1">
                    <p class="text-[8px] text-gray-400 font-black uppercase tracking-[0.3em]">Official Receipt Copy</p>
                </div>
            </div>
        </div>

        <div class="mt-4 mb-2 print-button text-center">
            <a href="my.bluetoothprint.scheme://https://lclopezresources.com/Collection/src/print.php" onclick="window.print()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Print</a>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.js"></script>
        <script>
            document.getElementById('burger-menu-btn').addEventListener('click', function () {
                document.getElementById('side-nav').classList.toggle('hidden');
            });

            document.getElementById('close-btn').addEventListener('click', function () {
                document.getElementById('side-nav').classList.add('hidden');
            });

            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 1000); // Adjust the delay (in milliseconds) if needed
            });
        </script>
        
        <!-- App Print Integration -->
        <script src="app_print_integration.js"></script>
    </body>

    </html>

    <?php
} else {
    echo "No transaction found for the current user";
}

// Close the statement for charges fetch
$stmt_charges->close();

// Close database connection
$conn->close();
?>
