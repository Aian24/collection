<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Initialize variables
$message = '';
$message_type = ''; // 'success' or 'error'

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
if (isset($_POST['submit'])) {
    $transaction_number = $_POST['transaction_number'];
    $branch = $_POST['branch'];
    $note = $_POST['note'];

    // Determine the branch table
    $branch_table = ($branch === 'nova') ? 'nova' : (($branch === 'sanko') ? 'sanko' : 'apm');
    if ($branch_table !== 'nova' && $branch_table !== 'sanko' && $branch_table !== 'apm') {
        $message = 'Invalid branch selected.';
        $message_type = 'error';
    } else {
        // Start the transaction
        $conn->begin_transaction();

        try {
            // Fetch the original values from collected table
            $stmt = $conn->prepare("SELECT rentbal, runningbal, tenantcode, spacecode, rent, paidrent, paidbal, charges, collector, tenantname FROM collected WHERE transaction_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('s', $transaction_number);
            $stmt->execute();
            $result = $stmt->get_result();
            $transaction = $result->fetch_assoc();

            if (!$transaction) {
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
            $charges = $transaction['charges'];
            $collector = $transaction['collector'];
            $tenantname = $transaction['tenantname'];

            // Rollback in the collected table
            $stmt = $conn->prepare("UPDATE collected SET rentbal = ?, runningbal = ? WHERE transaction_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('dds', $original_rentbal, $original_runningbal, $transaction_number);
            $stmt->execute();

            // Rollback in the selected branch table
            $stmt = $conn->prepare("UPDATE $branch_table SET rentbal = ?, runningbal = ? WHERE tenantcode = ? AND spacecode = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('ddss', $original_rentbal, $original_runningbal, $tenantcode, $spacecode);
            $stmt->execute();

            // Rollback in the apm and collectedapm tables
            $stmt = $conn->prepare("UPDATE apm SET rentbal = ?, runningbal = ? WHERE tenantcode = ? AND spacecode = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('ddss', $original_rentbal, $original_runningbal, $tenantcode, $spacecode);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE collectedapm SET rentbal = ?, runningbal = ? WHERE tenantcode = ? AND spacecode = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('ddss', $original_rentbal, $original_runningbal, $tenantcode, $spacecode);
            $stmt->execute();

            // Prepare to insert into void table
            $branch_name = ($branch === 'nova') ? 'Nova Branch' : (($branch === 'sanko') ? 'Sanko Branch' : 'APM Branch');
            $stmt = $conn->prepare("INSERT INTO void (transaction_number, branch, note, rent, rentbal, runningbal, paidrent, paidbal, charges, collector, tenantname, spacecode, void_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Bind parameters
            $stmt->bind_param('sssddddddssss', $transaction_number, $branch_name, $note, $rent, $original_rentbal, $original_runningbal, $paidrent, $paidbal, $charges, $collector, $tenantname, $spacecode, $void_date);
            $stmt->execute();

            // Delete the transaction record from collected table
            $stmt = $conn->prepare("DELETE FROM collected WHERE transaction_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('s', $transaction_number);
            $stmt->execute();

            // Commit the transaction
            $conn->commit();
            $_SESSION['void_transaction_message'] = 'Transaction voided successfully.';
            $_SESSION['void_transaction_message_type'] = 'success';

        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $_SESSION['void_transaction_message'] = $e->getMessage();
            $_SESSION['void_transaction_message_type'] = 'error';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Void Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.6/css/responsive.bootstrap4.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal.active {
            display: flex;
        }

        .message-success {
            color: green;
        }

        .message-error {
            color: red;
        }


        nav {
            padding: 10px;
        }
    </style>
</head>

<body class="bg-gray-100 p-0">
    <!-- Navigation Bar -->
    <nav class="bg-gray-800">
        <div class="flex justify-between items-center w-full mx-auto">
            <!-- Transaction Button -->
            <div>
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
                <a href="superuser.php" class="text-white">Create Collection</a>
                <a href="superuservoid.php" class="text-white">Void Transaction</a>
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

    <!-- Main Content -->
    <div class="max-w-lg mx-auto bg-white p-8 rounded-lg shadow-md mt-10">
        <h1 class="text-xl font-semibold mb-4 text-center">Void a Transaction</h1>
        <form action="void_transaction.php" method="post">
            <div class="mb-4">
                <label for="transaction_number" class="block text-gray-700">Transaction Number:</label>
                <input type="text" id="transaction_number" name="transaction_number"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    required>
            </div>
            <div class="mb-4">
                <label for="branch" class="block text-gray-700">Select Branch:</label>
                <select id="branch" name="branch"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    required>
                    <option value="" disabled selected>Select Branch</option>
                    <option value="nova">Nova Branch</option>
                    <option value="sanko">Sanko Branch</option>
                    <option value="apm">APM Branch</option> <!-- Added APM branch -->
                </select>
            </div>
            <div class="mb-4">
                <label for="note" class="block text-gray-700">Note:</label>
                <textarea required id="note" name="note"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
            </div>


            <input type="hidden" id="void_date" name="void_date" value="<?php echo date('Y-m-d H:i:s'); ?>">


            <button type="submit" name="submit"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Void Transaction</button>
        </form>
    </div>

    <!-- View Transactions Modal -->
    <div id="viewTransactionsModal" class="modal">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-lg w-full">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">View Transactions</h3>
                <button type="button" onclick="closeModal()"
                    class="text-gray-500 hover:text-gray-700 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div class="mt-4">
                <table id="transactionsTable" class="min-w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Transaction Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Space Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Collector</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Branch</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tenant Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tenant Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Paid Rent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Paid Balance</th>
                        </tr>
                    </thead>
                    <tbody id="transactionsTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Data will be inserted here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div id="resultModal" class="modal">
        <div class="bg-gray-400 p-6 rounded-lg shadow-lg max-w-sm w-full">
            <div class="bg-gray-50 px-4 py-5 sm:px-6">
                <h3 id="resultMessage" class="text-lg leading-6 font-medium text-gray-900 text-center"></h3>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.6/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.6/js/responsive.bootstrap4.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Check if there is a message to show
            const message = "<?php echo isset($_SESSION['void_transaction_message']) ? $_SESSION['void_transaction_message'] : ''; ?>";
            const messageType = "<?php echo isset($_SESSION['void_transaction_message_type']) ? $_SESSION['void_transaction_message_type'] : ''; ?>";

            if (message) {
                const resultModal = document.getElementById('resultModal');
                const resultMessage = document.getElementById('resultMessage');

                resultMessage.textContent = message;
                resultModal.classList.add('active');

                // Clear the session message after showing it
                <?php unset($_SESSION['void_transaction_message']); ?>
                <?php unset($_SESSION['void_transaction_message_type']); ?>

                // Redirect after 2 seconds
                setTimeout(function () {
                    resultModal.classList.remove('active');
                    window.location.href = 'void_transaction.php';
                }, 2000);
            }
        });

        function openModal() {
            document.getElementById('viewTransactionsModal').classList.add('active');
            fetchTransactions();
        }

        function closeModal() {
            document.getElementById('viewTransactionsModal').classList.remove('active');
            $('#transactionsTable').DataTable().destroy(); // Destroy the previous DataTable instance
        }

        function fetchTransactions() {
            fetch('fetch_transactions.php')
                .then(response => response.json())
                .then(data => {
                    let tableBody = document.getElementById('transactionsTableBody');
                    tableBody.innerHTML = ''; // Clear previous data
                    data.forEach(transaction => {
                        let row = document.createElement('tr');
                        row.innerHTML = `
                        <td>${transaction.collected_date}</td>
                            <td>${transaction.transaction_number}</td>
                            <td>${transaction.spacecode}</td>
                            <td>${transaction.collector}</td>
                            <td>${transaction.branch}</td>
                            <td>${transaction.tenantcode}</td>
                            <td>${transaction.tenantname}</td>
                            <td>${transaction.paidrent}</td>
                            <td>${transaction.paidbal}</td>
                        `;
                        tableBody.appendChild(row);
                    });
                    $('#transactionsTable').DataTable({
                        responsive: true,
                        destroy: true // Destroy any existing instance of DataTable
                    });
                })
                .catch(error => console.error('Error fetching transactions:', error));
        }

        // Handle burger menu toggle
        document.getElementById('burger-menu-btn').addEventListener('click', () => {
            document.getElementById('side-nav').classList.toggle('hidden');
        });

        // Handle side nav close button
        document.getElementById('close-btn').addEventListener('click', () => {
            document.getElementById('side-nav').classList.add('hidden');
        });
    </script>
</body>

</html>