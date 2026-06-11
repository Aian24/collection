<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
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
    $lname = $row_user["lname"];
} else {
    $lname = "Unknown";
}
$stmt_user->close();

// Fetch parameters
$transaction_number = $_POST['transaction_number'] ?? '';
$branch = $_POST['branch'] ?? '';

// Determine table
$table = ($branch === 'Nova Market') ? 'collectednova' : 
         (($branch === 'APM Branch' || $branch === 'APM') ? 'collectedapm' : 'collected');

// Fetch transaction
$sql = "SELECT * FROM $table WHERE transaction_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $transaction_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
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
    $stmt->close();

    // Fetch all charges
    $sql_charges = "SELECT * FROM $table WHERE transaction_number = ?";
    $stmt_charges = $conn->prepare($sql_charges);
    $stmt_charges->bind_param("s", $transaction_number);
    $stmt_charges->execute();
    $result_charges = $stmt_charges->get_result();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/png" href="../images/lc.png">
        <title>Reprint Receipt</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <style>
            body { background: #f3f4f6; }
            .receipt-card {
                background: white;
                padding: 1rem;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                max-width: 32rem; /* max-w-lg */
                border-radius: 1rem;
                margin-top: 2rem;
            }
            @media print {
                @page { size: 58mm auto; margin: 0; }
                body, p, h1 { font-size: 12px; margin: 0; }
                body { background: white !important; margin: 0; padding: 0; }
                .receipt-card { 
                    width: 58mm; 
                    padding: 0; 
                    margin: 0 auto; 
                    box-shadow: none !important;
                    border-radius: 0 !important;
                    max-width: none !important;
                    background: white !important;
                    margin-top: 0 !important;
                }
                .print-logo { width: 100%; height: auto; margin-bottom: 4%; }
                .no-print { display: none !important; }
            }
            .no-print { display: block; }
        </style>
    </head>
    <body class="bg-gray-100">
        <nav class="bg-gray-800 p-4 mb-2 sm:mb-0 w-full no-print">
            <div class="flex justify-between items-center w-full mx-auto max-w-lg">
                <a href="reprint.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-xl transition duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back
                </a>
                <div class="text-white font-bold opacity-50 uppercase text-xs tracking-widest">Reprinting Mode</div>
            </div>
        </nav>

        <div class="container mx-auto receipt-card">
            <div class="max-w-lg mx-auto text-center">
                <img src="images/lc.png" alt="Logo" class="print-logo mb-2 mx-auto">
                <p class="mb-2 font-bold italic">Slip Receipt (Reprint)</p>
            </div>

            <div class="space-y-1 text-xs border-b border-black pb-2 mb-2">
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Transaction #:</span>
                    <span class="font-black text-black"><?php echo htmlspecialchars($transaction_number); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Date & Time:</span>
                    <span class="font-black text-black"><?php echo date('m/d/y, g:i A', strtotime($collected_date)); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Collector:</span>
                    <span class="font-black text-black"><?php echo htmlspecialchars($lname); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Branch:</span>
                    <span class="font-black text-black"><?php echo htmlspecialchars($branch); ?></span>
                </div>
            </div>

            <div class="space-y-1 mt-2">
                <div class="flex justify-between text-xs border-b border-gray-100 pb-1">
                    <span class="text-black uppercase font-semibold">Space Code:</span>
                    <span class="font-black text-black"><?php echo htmlspecialchars($spacecode); ?></span>
                </div>
                <div class="flex justify-between text-xs border-b border-gray-100 pb-1">
                    <span class="text-black uppercase font-semibold">Tenant:</span>
                    <span class="font-black text-black text-right"><?php echo htmlspecialchars($tenantname); ?></span>
                </div>
            </div>

            <div class="space-y-1 my-2 text-[10px]">
                <div class="flex justify-between border-b border-dotted border-gray-200">
                    <span class="text-black uppercase font-semibold">Code:</span>
                    <span class="font-black text-black"><?php echo htmlspecialchars($tenantcode); ?></span>
                </div>
                <div class="flex justify-between border-b border-dotted border-gray-200">
                    <span class="text-black uppercase font-semibold">Daily Rent:</span>
                    <span class="font-black text-black"><?php echo number_format((float)$dailyRent, 2); ?></span>
                </div>
                <div class="flex justify-between border-b border-dotted border-gray-200">
                    <span class="text-black uppercase font-semibold">Arrear Bal:</span>
                    <span class="font-black text-black"><?php echo number_format((float)$runningBalance, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-black uppercase font-semibold">Rent Bal:</span>
                    <span class="font-black text-black"><?php echo number_format((float)$rentbal, 2); ?></span>
                </div>
            </div>

            <div class="border-t-2 border-black my-2"></div>

            <div class="space-y-1 mb-2">
                <div class="text-[9px] font-black text-center border-b border-gray-200 pb-1 mb-1 tracking-widest uppercase">Payment Itemization</div>
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

                $subTotalCharges = 0;
                if (isset($paidrent) && $paidrent > 0) {
                    echo '<div class="flex justify-between text-xs"><span>Daily Rent Payment</span><span class="font-bold">' . number_format((float)$paidrent, 2) . '</span></div>';
                }
                if (isset($paidbal) && $paidbal > 0) {
                    echo '<div class="flex justify-between text-xs"><span>Arrear Balance Payment</span><span class="font-bold">' . number_format((float)$paidbal, 2) . '</span></div>';
                }
                $paidTotal = (isset($paidrent) ? (float)$paidrent : 0) + (isset($paidbal) ? (float)$paidbal : 0);
                if ($paidTotal > 0) {
                    echo '<div class="flex justify-between text-[10px] pt-1 border-t border-dotted border-gray-300 mb-2"><span class="text-black font-bold uppercase">Rent & Arrear Total:</span><span class="font-black text-black">(' . number_format($paidTotal, 2) . ')</span></div>';
                }

                if ($result_charges->num_rows > 0) {
                    $result_charges->data_seek(0);
                    while ($row_c = $result_charges->fetch_assoc()) {
                        if (!empty($row_c['charges'])) {
                            $parsed = parseAndFormatCharges($row_c['charges']);
                            foreach ($parsed as $c) {
                                $subTotalCharges += (float)str_replace(',', '', $c['amount']);
                                echo '<div class="flex justify-between text-xs"><span>' . htmlspecialchars($c['name']) . '</span><span class="font-bold">' . $c['amount'] . '</span></div>';
                            }
                        }
                    }
                }
                if ($subTotalCharges > 0) {
                    echo '<div class="flex justify-between text-[10px] pt-1 border-t border-dotted border-gray-300"><span class="text-black font-bold uppercase">Total Charges:</span><span class="font-black text-black">(' . number_format($subTotalCharges, 2) . ')</span></div>';
                }
                ?>
            </div>

            <div class="border-t-2 border-black pt-2 mt-2">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-black uppercase">Grand Total</span>
                    <span class="text-xl font-black">₱<?php echo number_format((float)$total, 2); ?></span>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-2 mt-2 space-y-1 bg-gray-50 p-2 rounded">
                <div class="flex justify-between text-[10px] font-bold">
                    <span class="text-black uppercase font-semibold">Remaining Arrear:</span>
                    <span class="text-black font-black"><?php echo number_format((float)$newbalance, 2); ?></span>
                </div>
                <div class="flex justify-between text-[10px] font-bold">
                    <span class="text-black uppercase font-semibold">Remaining Rent Bal:</span>
                    <span class="font-black text-black"><?php echo number_format((float)$newrentbalance, 2); ?></span>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-[10px] leading-tight text-gray-600 font-medium">Thank you for your payment.<br>Keep this receipt for your records.</p>
                <div class="mt-4 border-t border-dotted border-gray-300 pt-1">
                    <p class="text-[8px] text-gray-400 font-black uppercase tracking-[0.3em]">Reprinted Receipt Copy</p>
                </div>
            </div>
        </div>

        <div class="mt-8 mb-8 no-print text-center">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-black py-4 px-12 rounded-2xl shadow-xl transform active:scale-95 transition-all flex items-center gap-2 mx-auto">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                CONFIRM REPRINT
            </button>
        </div>

        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 1000);
            });
        </script>
        <script src="app_print_integration.js"></script>
    </body>
    </html>
    <?php
} else {
    echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h2 style='color:red;'>Transaction Not Found</h2>
            <p>We couldn't find transaction #$transaction_number in the $branch branch.</p>
            <a href='reprint.php' style='display:inline-block; padding:10px 20px; background:#2563eb; color:white; border-radius:8px; text-decoration:none;'>Go Back</a>
          </div>";
}
$conn->close();
ob_end_flush();
?>