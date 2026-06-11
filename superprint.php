<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: index.php"); // Redirect if not logged in
    exit();
}

$username = $_SESSION["username"];

// Fetch user details
$sql_user = "SELECT * FROM users WHERE username = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("s", $username);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

$lname = $result_user->num_rows > 0 ? $result_user->fetch_assoc()["lname"] : "Unknown"; // Get last name or default to "Unknown"
$stmt_user->close();

// Determine the correct table based on branch
$branch = $_SESSION["branch"];
$tableName = ($branch === 'Nova Market') ? 'collectednova' : (($branch === 'APM') ? 'collectedapm' : 'collected');

// Fetch the latest transaction for the current user
$sql = "SELECT * FROM $tableName WHERE username = ? ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Get the latest transaction details
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

    // Close the statement for the latest transaction fetch
    $stmt->close();

    // Fetch charges for the transaction
    $sql_charges = "SELECT * FROM $tableName WHERE transaction_number = ?";
    $stmt_charges = $conn->prepare($sql_charges);
    $stmt_charges->bind_param("d", $transaction_number);
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

                .print-button {
                    display: none;
                }

                nav,
                #side-nav {
                    display: none;
                }
            }
        </style>
    </head>

    <body>

        <!-- Navigation Bar -->
        <nav class="bg-gray-800 p-4 mb-2">
            <div class="flex justify-between items-center w-full mx-auto">
                <div class="sm:hidden">
                    <button id="burger-menu-btn" class="text-white focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16m-7 6h7"></path>
                        </svg>
                    </button>
                </div>
                <div>
                    <a href="superuser.php"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Back</a>
                </div>
                <div class="hidden sm:flex sm:items-center sm:space-x-4">
                    <a href="index.php" class="text-white">Logout</a>
                    <a href="superuser.php" class="text-white">Create Collection</a>
                </div>
            </div>
        </nav>

        <div id="side-nav" class="hidden bg-gray-800 fixed inset-0 z-50">
            <div class="flex justify-end p-4">
                <button id="close-btn" class="text-white focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div class="flex flex-col items-center mt-10 space-y-4">
                <a href="superuser.php" class="text-white">Create Collection</a>
                <a href="index.php" class="text-white">Logout</a>
            </div>
        </div>

        <div class="container mx-auto mt-8">
            <div class="max-w-lg mx-auto text-center">
                <img src="images/lc.png" alt="Logo" class="print-logo mb-2">
                <p class="mb-2 font-bold">Acknowledgement Receipt</p>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Transaction#: <?php echo $transaction_number; ?></p>
                </div>
                <div>
                    <p class="font-semibold text-left"><?php echo date('m/d/y, g:i A', strtotime($collected_date)); ?></p>
                </div>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Collector: <?php echo $lname; ?></p>
                </div>
                <div>
                    <p class="font-semibold">Branch: <?php echo $branch; ?></p>
                </div>
            </div>

            <hr class="w-full my-2 border-0 border-t-2 border-solid border-black">

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Space Code:</p>
                </div>
                <div>
                    <p class="font-semibold"><?php echo $spacecode; ?></p>
                </div>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Tenant Name:</p>
                </div>
                <div>
                    <p class="font-semibold"><?php echo $tenantname; ?></p>
                </div>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Tenant Code: <?php echo $tenantcode; ?></p>
                </div>
                <div>
                    <p class="font-semibold">Daily Rent: <?php echo number_format($dailyRent, 2); ?></p>
                </div>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Arrear Balance: <?php echo number_format($runningBalance, 2); ?></p>
                </div>
                <div>
                    <p class="font-semibold">Rent Balance: <?php echo number_format($rentbal, 2); ?></p>
                </div>
            </div>

            <hr class="w-full my-2 border-0 border-t-2 border-solid border-black">

            <!-- Charges Section -->
            <div class="charges-section mb-1">
                <?php
                function formatCharge($charge)
                {
                    if (preg_match('/\d/', $charge)) {
                        preg_match_all('/([0-9]*)([^0-9]*)/', $charge, $matches, PREG_SET_ORDER);
                        $formatted_parts = [];
                        foreach ($matches as $match) {
                            if (!empty($match[1])) {
                                $formatted_parts[] = number_format((float) $match[1]);
                            }
                            if (!empty($match[2])) {
                                $formatted_parts[] = $match[2];
                            }
                        }
                        return implode('', $formatted_parts);
                    }
                    return $charge;
                }

                if ($result_charges->num_rows > 0) {
                    $charges_list = [];
                    while ($row_charges = $result_charges->fetch_assoc()) {
                        if (!empty($row_charges['charges'])) {
                            $charges_list[] = formatCharge($row_charges['charges']);
                        }
                    }
                    if (!empty($charges_list)) {
                        echo "<div class='text-center mb-1'>";
                        echo "<p class='font-bold text-xs'>Charges</p>";
                        echo "</div>";
                        echo "<div>";
                        echo "<p class='font-semibold text-xs'>" . implode(", ", $charges_list) . "</p>";
                        echo "</div>";
                    }
                }
                ?>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <?php if ($paidrent > 0) { ?>
                    <div>
                        <p class="font-semibold">Amount Paid (Rent):</p>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo number_format($paidrent, 2); ?></p>
                    </div>
                <?php } ?>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <?php if ($paidbal > 0) { ?>
                    <div>
                        <p class="font-semibold">Amount Paid (Balance):</p>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo number_format($paidbal, 2); ?></p>
                    </div>
                <?php } ?>
            </div>

            <hr class="w-full my-2 border-0 border-t-2 border-solid border-black">

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Total:</p>
                </div>
                <div>
                    <p class="font-semibold"><?php echo number_format($total, 2); ?></p>
                </div>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Running Arrear Balance:</p>
                </div>
                <div>
                    <p class="font-semibold"><?php echo number_format($newbalance, 2); ?></p>
                </div>
            </div>

            <div class="mb-1 flex justify-between text-xs">
                <div>
                    <p class="font-semibold">Running Rent Balance:</p>
                </div>
                <div>
                    <p class="font-semibold"><?php echo number_format($newrentbalance, 2); ?></p>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-gray-600 text-center text-xs">Thank you for your payment. Please keep this receipt for your
                    records.</p>
            </div>
        </div>

        <div class="mt-4 mb-2 print-button text-center">
            <a href="my.bluetoothprint.scheme://https://lclopezresources.com/Collection/src/print.php"
                onclick="window.print()"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Print</a>
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
                }, 1000); // Adjust the delay if needed
            });
        </script>
    </body>

    </html>

    <?php
} else {
    echo "No transaction found for the current user.";
}

// Close statement for charges fetch and database connection
$stmt_charges->close();
$conn->close();
?>