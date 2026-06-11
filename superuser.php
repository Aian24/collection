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

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get the current date and time in the Philippines timezone
$currentDateTime = date('Y-m-d\TH:i:s');

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $branch = $_POST['branch']; // Get the branch from the form
    $_SESSION["branch"] = $branch; // Store branch in session
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

    // Determine the appropriate table based on the selected branch
    $tableName = ($branch === 'Sanko Market') ? 'collected' : (($branch === 'APM') ? 'collectedapm' : 'collectednova');

    // Get the latest transaction number
    $latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM $tableName");
    $latestTransactionRow = $latestTransactionQuery->fetch_assoc();
    $latestTransactionNumber = $latestTransactionRow['max_transaction'] ?? 0; // Use 0 as default if no transaction found

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

    // Prepare the INSERT statement
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
        $updateTable = ($branch === 'Sanko Market') ? 'sanko' : (($branch === 'APM') ? 'apm' : 'nova');
        $updateStmt = $conn->prepare("UPDATE $updateTable SET runningbal = ?, rentbal = ? WHERE spacecode = ?");
        $updateStmt->bind_param("dds", $newbalance, $newrentbalance, $spacecode);

        if ($updateStmt->execute()) {
            include 'supermodalsuccess.php'; // Include modalsuccess.php to display the success modal
        } else {
            echo "Error updating running and rent balance: " . $conn->error;
        }

        $updateStmt->close();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User page</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.js"></script>
    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.6/css/responsive.bootstrap4.min.css">
    <style>
        /* Custom styling for pagination */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: red;
            /* Change pagination button color to red */
        }

        /* Custom styling for search box border */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid black;
            /* Change search box border color to black */
        }
    </style>
</head>



<body class="bg-white" onload="calculateNewBalance()">

    <!-- Navigation Bar -->
    <nav class="bg-gray-800 p-4 mb-4 sm:mb-0 w-full">

        <div class="flex justify-between items-center w-full mx-auto">
            <!-- Transaction Button -->
            <div>
                <!-- Button to Open Modal -->
                <button type="button" onclick="openModal()"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Transactions
                </button>
            </div>
            <!-- Burger menu icon for small screens -->
            <div class="sm:hidden">
                <button id="burger-menu-btn" class="text-white focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
            </div>
            <!-- Navigation links -->
            <div class="hidden sm:flex sm:items-center sm:space-x-4">
                <a href="superuservoid.php" class="text-white">Void Transaction</a>
                <a href="superuser.php" class="text-white">Create Collection</a>
                <a href="index.php" class="text-white">Logout</a>


            </div>
        </div>
    </nav>

    <!-- Side navigation for small screens -->
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
            <a href="superuservoid.php" class="text-white">Void Transaction</a>
            <a href="index.php" class="text-white">Logout</a>
        </div>
    </div>

    <!-- Your Form -->
    <div class="w-full mt-5">
        <form id="collectionForm" method="post" action="superuser.php"
            class="sm:max-w-lg mx-auto sm:bg-white sm:shadow-md sm:rounded px-8 pt-6 pb-8 mb-4 sm:bg-transparent sm:w-full">
            <!-- Your form inputs go here -->

            <!-- Display the latest transaction in gray -->
            <div class="justify-center mb-4 hidden">
                <p class="text-gray-500">Transaction Number: <?php echo $latestTransactionNumber; ?></p>
            </div>

            <!-- Displaying last name using an input field with name="collector" -->
            <input readonly hidden
                class="shadow appearance-none border rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="collector" type="text" name="collector" value="<?php echo $lname; ?>">

            <div class="mb-4">
                <input readonly hidden
                    class="shadow appearance-none border rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="collected_date" type="text" name="collected_date" value="<?php echo date('Y-m-d H:i:s'); ?>"
                    placeholder="Collected Date and Time">
            </div>

            <div class="mb-4">
                <input readonly hidden
                    class="shadow appearance-none border rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="collected_date" type="text" name="collected_date" value="<?php echo date('Y-m-d H:i:s'); ?>"
                    placeholder="Collected Date and Time">
            </div>


            <div class="mb-4 text-center">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="branch">
                    Branch
                </label>
                <select name="branch" id="branch"
                    class="shadow appearance-none border border-gray-300 rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    style="text-align:center;">
                    <option value="" disabled selected>Select Branch</option>
                    <option value="Sanko Market">Sanko Market</option>
                    <option value="Nova Market">Nova Market</option>
                    <option value="APM">APM</option>
                </select>
            </div>






            <div class="mb-4 flex gap-4">
                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="spacecode">
                        Space Code
                    </label>
                    <input required autocomplete="off"
                        class="shadow appearance-none border border-black  rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="spacecode-input" type="text" name="spacecode" placeholder="Space Code"
                        oninput="suggestSpaceCode(this.value)">
                    <!-- Error message placeholder -->
                    <span id="spacecode-error" class="text-red-500"></span>
                    <div id="spacecode-suggestions" class="hidden absolute z-10 bg-white rounded mt-1 shadow-lg">
                        <!-- Suggestions will be displayed here -->
                    </div>
                </div>
                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="tenantcode">
                        Tenant Code
                    </label>
                    <input required
                        class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="tenantcode" type="text" name="tenantcode" placeholder="Tenant Code">
                </div>
            </div>







            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="tenant">
                    Tenant Name
                </label>
                <input required
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                    id="tenantname" type="text" name="tenantname" placeholder="Tenant Name">
            </div>


            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="rent">
                    Daily Rent
                </label>
                <input
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                    id="rent" type="text" name="rent" placeholder="Daily Rent">
            </div>


            <div class="mb-4 flex gap-4">
                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="rentbal">
                        Rent Balance
                    </label>
                    <input
                        class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="rentbal" type="text" name="rentbal" placeholder="Rent Balance"
                        oninput="calculateNewBalance()">
                </div>

                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="runningbal">
                        Arrear Balance
                    </label>
                    <input
                        class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="runningbal" type="text" name="runningbal" placeholder="Arrear Balance">
                </div>
            </div>


            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="paidrent">
                    Amount Paid (Daily Rent)
                </label>
                <input oninput="formatNumberAndCalculateNewBalance(this)"
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                    id="paidrent" type="text" name="paidrent" placeholder="Enter amount paid rent" inputmode="decimal">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="paidbal">
                    Amount Paid (Arrear Balance)
                </label>
                <input oninput="formatNumberAndCalculateNewBalance(this)"
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                    id="paidbal" type="text" name="paidbal" placeholder="Enter amount paid balance" inputmode="decimal">
            </div>




            <div class="flex items-center">
                <div class="flex-1 border-t border-gray-500"></div>
                <div class="px-4 uppercase text-gray-600 text-lg font-bold tracking-wide">Charges</div>
                <div class="flex-1 border-t border-gray-500"></div>
            </div>



            <div class="mb-4 flex gap-4">

                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="chargecusa">
                        Cusa
                    </label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                        class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="chargecusa" type="text" name="chargecusa" placeholder="Enter amount" inputmode="decimal">
                </div>

                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="chargecusa">
                        Aircon
                    </label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                        class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="chargeac" type="text" name="chargeac" placeholder="Enter amount" inputmode="decimal">
                </div>
            </div>


            <div class="mb-4 flex gap-4">
                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="chargeelec">
                        Electricity
                    </label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                        class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="chargeelec" type="text" name="chargeelec" placeholder="Enter amount" inputmode="decimal">
                </div>
                <div class="flex-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="chargewater">
                        Water
                    </label>
                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                        class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                        id="chargewater" type="text" name="chargewater" placeholder="Enter amount " inputmode="decimal">

                </div>
            </div>




            <div class="mb-4 flex flex-col gap-4">
                <div class="flex gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="chargeothers">
                            Others
                        </label>
                        <select id="chargeothers" name="chargeothers[]"
                            class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500">
                            <option value="" selected>Select a type</option>
                            <option value="Table Tennis">Table Tennis</option>
                            <option value="Pay Toilet">Pay Toilet</option>
                            <option value="Pay Parking">Pay Parking</option>
                            <option value="Ice & Water">Ice & Water</option>
                            <option value="Ulam Vendor">Ulam Vendor</option>
                            <option value="Gas">Gas</option>
                            <option value="Famylihan">Famylihan</option>
                            <option value="Garbage Haul">Garbage Haul</option>
                            <option value="Photocopy">Photocopy</option>
                            <option value="Tenant ID">Tenant ID</option>
                            <option value="Function Room">Function Room</option>
                            <option value="Tables & Chairs">Tables & Chairs</option>
                            <option value="Overnight Works">Overnight Works</option>
                            <option value="Vendo Sale">Vendo Sale</option>
                            <option value="Zumba">Zumba</option>
                            <option value="Sec Dep">Sec Dep</option>
                        </select>
                    </div>

                    <div class="flex-1">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="otheramount">
                            Amount for Others
                        </label>
                        <input oninput="formatNumberAndCalculateNewBalance(this)"
                            class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                            id="otheramount" type="text" name="otheramount[]" placeholder="Enter amount"
                            inputmode="decimal">
                    </div>
                </div>

                <div id="additionalChargesContainer" class="flex flex-col gap-2">
                    <!-- Placeholder for additional charges -->
                </div>

                <div class="flex justify-center">
                    <button type="button" id="addChargeButton"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        + Add
                    </button>
                </div>
            </div>




            <div class="flex items-center mb-4">
                <div class="flex-1 border-t border-gray-500"></div>
            </div>


            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="total">
                    Total
                </label>
                <input readonly
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                    id="total" type="text" name="total" placeholder="Total">
            </div>


            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="balance">
                    New Arrear Balance
                </label>
                <input readonly
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                    id="newbalance" type="text" name="newbalance" placeholder="New Balance">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="newrentbalance">
                    New Rent Balance
                </label>
                <input readonly
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                    id="newrentbalance" type="text" name="newrentbalance" placeholder="New Rent Balance">
            </div>






            <div class="flex items-center justify-center">
                <button
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-6 mt-4 rounded focus:outline-none focus:shadow-outline w-full sm:w-auto"
                    type="submit">
                    Submit
                </button>
            </div>


            <!-- Welcome Modal -->
            <div id="welcomeModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>&#8203;
                    <div
                        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <!-- Handshake icon -->
                                    <svg class="h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 21v-4m0 0V9m0 4h3m-3 4h3m8-10v4m0 0v8m0-8h-3m3 0h3m-11-9c-1.333 0-4 1.333-4 4v5c0 2.667 2.667 4 4 4h2.5c2.25 0 4.5 1.25 5.5 3l1.5 3v-15m-7 0c1.333 0 4-1.333 4-4m-4 4h3">
                                        </path>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Welcome!</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            have a great day!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- View Transactions Modal -->
            <div id="viewTransactionsModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>&#8203;
                    <div
                        class="inline-block align-bottom bg-gray-400 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <!-- Modal Header -->
                        <div class="bg-gray-50 px-4 py-5 sm:px-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 text-center">Transactions Today
                            </h3>

                        </div>

                        <!-- Modal Body -->
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">

                            <div class="mb-4">
                                <a href="superprintsum.php"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-2 rounded"
                                    style="text-decoration: none;">
                                    Summary Report
                                </a>
                            </div>


                            <!-- Table Container -->
                            <div class="overflow-x-auto">
                                <table id="transactionsTable" class="table table-striped table-hover">

                                    <!-- Table Header -->
                                    <thead class="table-primary">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Date</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Transaction#</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Space Code</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Collector</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Branch</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Tenant Code</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Tenant Name</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Paid Rent</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Paid Balance</th>
                                        </tr>
                                    </thead>
                                    <!-- Table Body - This will be populated dynamically -->
                                    <tbody id="transactionsTableBody">
                                        <!-- Data will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Modal Footer - Pagination and Close Button -->
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button"
                                class="inline-flex justify-center w-full rounded-md border border-transparent px-4 py-2 bg-gray-800 text-base font-medium text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="closeModal()">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>



    <script src="function.js"> </script>

    <!-- jQuery -->
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
        // Function to open the modal
        function openModal() {
            document.getElementById('viewTransactionsModal').classList.remove('hidden');
            // Call function to fetch and load transactions
            fetchAndLoadTransactions();
        }

        // Function to close the modal and refresh the page
        function closeModal() {
            document.getElementById('viewTransactionsModal').classList.add('hidden');
            location.reload(); // Reload the page
        }


        // Function to fetch and load transactions
        function fetchAndLoadTransactions() {
            // Fetch transactions for today using fetch API
            fetch('fetch_transactions.php')
                .then(response => response.json())
                .then(data => {
                    // Populate table rows with fetched data
                    const tableBody = document.getElementById('transactionsTableBody');
                    tableBody.innerHTML = ''; // Clear existing rows
                    data.forEach(transaction => {
                        const row = `
                        <tr>
                            <td>${transaction.collected_date}</td>
                            <td>${transaction.transaction_number}</td>
                            <td>${transaction.spacecode}</td>
                            <td>${transaction.collector}</td>
                            <td>${transaction.branch}</td>
                            <td>${transaction.tenantcode}</td>
                            <td>${transaction.tenantname}</td>
                            <td>${transaction.paidrent}</td>
                            <td>${transaction.paidbal}</td>
                        </tr>
                    `;
                        tableBody.innerHTML += row;
                    });
                    // Initialize DataTables (responsive and other functionalities)
                    $('#transactionsTable').DataTable({
                        responsive: true,
                        paging: true,
                        searching: true,

                    });
                })
                .catch(error => {
                    console.error('Error fetching transactions:', error);
                });
        }


        // Function to close the welcome modal
        function closeWelcomeModal() {
            document.getElementById("welcomeModal").classList.add("hidden");
            // Store a flag in localStorage indicating that the modal has been closed
            localStorage.setItem("welcomeModalClosed", "true");
        }

        // Function to check if the welcome modal should be displayed
        function checkWelcomeModal() {
            // Check if the modal should be displayed based on whether it has been closed before
            var welcomeModalClosed = localStorage.getItem("welcomeModalClosed");
            if (!welcomeModalClosed) {
                // If the modal has not been closed before, display it
                document.getElementById("welcomeModal").classList.remove("hidden");
                // Set a timeout to close the modal after 3 seconds
                setTimeout(closeWelcomeModal, 2000);
            }
        }

        // Call the function to check if the welcome modal should be displayed when the page loads
        window.onload = checkWelcomeModal;


        // Suggest Space Code and Auto Complete
        function suggestSpaceCode(value) {
            var branch = document.getElementById("branch").value; // Get selected branch value
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "suggest_spacecode.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var suggestions = JSON.parse(xhr.responseText);
                    var suggestionsContainer = document.getElementById("spacecode-suggestions");
                    var errorSpan = document.getElementById("spacecode-error"); // Get error message span
                    suggestionsContainer.innerHTML = "";
                    if (suggestions.length > 0) {
                        for (var i = 0; i < suggestions.length; i++) {
                            var option = document.createElement("div");
                            option.classList.add("cursor-pointer", "py-1", "px-4", "hover:bg-gray-200");
                            option.innerText = suggestions[i];
                            option.onclick = function () {
                                document.getElementById("spacecode-input").value = this.innerText;
                                suggestionsContainer.classList.add("hidden");
                                fetchTenantDetails(this.innerText); // Fetch tenant details when suggestion is clicked
                            };
                            suggestionsContainer.appendChild(option);
                        }
                        suggestionsContainer.classList.remove("hidden");
                        errorSpan.textContent = ""; // Clear error message
                    } else {
                        suggestionsContainer.classList.add("hidden");
                        errorSpan.textContent = "No suggestions found"; // Display message for no suggestions found
                    }

                    // Check if the typed value matches any suggestion
                    if (suggestions.includes(value)) {
                        fetchTenantDetails(value); // Fetch tenant details if the typed value matches a suggestion
                    } else {
                        clearTenantDetails(); // Clear tenant details if the typed value does not match any suggestion
                    }
                }
            };
            xhr.send("search=" + encodeURIComponent(value) + "&branch=" + encodeURIComponent(branch)); // Pass branch value
        }

        document.addEventListener("click", function (event) {
            var suggestionsContainer = document.getElementById("spacecode-suggestions");
            if (event.target !== suggestionsContainer && !suggestionsContainer.contains(event.target)) {
                suggestionsContainer.classList.add("hidden");
            }
        });

        // Function to fetch tenant details based on the selected space code and branch
        function fetchTenantDetails(spacecode) {
            var branch = document.getElementById("branch").value; // Get selected branch value
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
                        document.getElementById("rentbal").value = response.rentbal; // Update rentbal field
                        document.getElementById("runningbal").value = response.runningbal;

                        // Check if the spacecode is "Ambulant"
                        var isAmbulant = response.editable;

                        // Set readonly attribute for input fields based on the condition
                        document.getElementById("tenantcode").readOnly = !isAmbulant;
                        document.getElementById("tenantname").readOnly = !isAmbulant;
                        document.getElementById("rent").readOnly = !isAmbulant;
                        document.getElementById("rentbal").readOnly = !isAmbulant; // Update rentbal readonly
                        document.getElementById("runningbal").readOnly = !isAmbulant;
                    } else {
                        // Handle error or clear fields
                        clearTenantDetails();
                    }
                }
            };
            xhr.send("spacecode=" + encodeURIComponent(spacecode) + "&branch=" + encodeURIComponent(branch)); // Pass branch value
        }


        // Function to clear tenant details
        function clearTenantDetails() {
            document.getElementById("tenantname").value = "";
            document.getElementById("rent").value = "";
            document.getElementById("rentbal").value = "";
            document.getElementById("runningbal").value = "";
            // Clear readonly attributes
            document.getElementById("tenantname").readOnly = false;
            document.getElementById("rent").readOnly = false;
            document.getElementById("rentbal").readOnly = false;
            document.getElementById("runningbal").readOnly = false;
        }

        // Listen for input event on the space code input field
        document.getElementById("spacecode-input").addEventListener("input", function (event) {
            var value = event.target.value.trim();
            if (value === "") {
                clearTenantDetails(); // Clear tenant details if the input field becomes empty
            } else {
                suggestSpaceCode(value); // Suggest space code if the input field is not empty
            }
        });












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
            var additionalCharges = document.querySelectorAll('#additionalChargesContainer .flex-1 input');
            additionalCharges.forEach(function (input) {
                var amount = parseFloat(input.value.replace(/,/g, '')) || 0;
                totalCharges += amount;
            });

            // Calculate total amount paid
            var totalAmountPaid = paidRent + paidBalance;

            // Calculate rent balance based on paid rent
            var rentBalanceRemaining = dailyRent - paidRent; // Remaining daily rent after payment
            var newRentBalance = rentBalance + rentBalanceRemaining; // Add to existing rent balance

            // Format the new rent balance with commas and two decimal places
            var formattedNewRentBalance = newRentBalance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Update the value of the new rent balance input field
            document.getElementById("newrentbalance").value = formattedNewRentBalance;

            // Calculate new running balance: running balance - paid balance
            var newRunningBalance = runningBalance - paidBalance;

            // Format the new running balance with commas and two decimal places
            var formattedNewRunningBalance = newRunningBalance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Update the value of the new running balance input field
            document.getElementById("newbalance").value = formattedNewRunningBalance;

            // Update the total input field
            var total = totalAmountPaid + totalCharges;
            var formattedTotal = total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById("total").value = formattedTotal;
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
            newEntry.classList.add('mb-4', 'flex', 'gap-4', 'items-end');
            newEntry.innerHTML = `
        <div class="flex-1">
            <select name="chargeothers[]" 
                    class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500">
                    <option value="" selected>Select a type</option>
                    <option value="Table Tennis">Table Tennis</option>
                    <option value="Pay Toilet">Pay Toilet</option>
                    <option value="Pay Parking">Pay Parking</option>
                    <option value="Ice & Water">Ice & Water</option>
                    <option value="Ulam Vendor">Ulam Vendor</option>
                    <option value="Gas">Gas</option>
                    <option value="Famylihan">Famylihan</option>
                    <option value="Garbage Haul">Garbage Haul</option>
                    <option value="Photocopy">Photocopy</option>
                    <option value="Tenant ID">Tenant ID</option>
                    <option value="Function Room">Function Room</option>
                    <option value="Tables & Chairs">Tables & Chairs</option>
                    <option value="Overnight Works">Overnight Works</option>
                    <option value="Vendo Sale">Vendo Sale</option>
                    <option value="Zumba">Zumba</option>
                    <option value="Sec Dep">Sec Dep</option>
            </select>
        </div>
        <div class="flex-1">
            <input oninput="formatNumberAndCalculateNewBalance(this)" 
                   class="shadow appearance-none border border-black rounded w-full py-3 px-4 text-lg text-gray-700 leading-tight focus:outline-none focus:shadow-outline hover:border-blue-500"
                   type="text" name="otheramount[]" placeholder="Enter amount" inputmode="decimal">
        </div>
    `;

            container.appendChild(newEntry);
        });



    </script>


</body>

</html>