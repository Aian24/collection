<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();

error_reporting(0);
// Check if the user is logged in
if (!isset($_SESSION['admin_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}

// Set the default timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get the current default timezone
$timezone = date_default_timezone_get();


if (isset($_SESSION['admin_name']) && !isset($_SESSION['welcome_alert_shown'])) {
    $admin_name = $_SESSION['admin_name'];
    echo "<script>alert('Welcome, $admin_name!');</script>";
    $_SESSION['welcome_alert_shown'] = true; // Set the flag to true
}

// Get the date filter values
$startDate = isset($_POST['startDate']) ? date('Y-m-d', strtotime($_POST['startDate'])) : date("Y-m-d");
$endDate = isset($_POST['endDate']) ? date('Y-m-d', strtotime($_POST['endDate'])) : date("Y-m-d");
// Modify the date filter based on your requirement
if (empty($_POST['startDate']) && empty($_POST['endDate'])) {
    // If no specific date range is set, consider all transactions
    $dateFilter = "";
} else {
    $dateFilter = " AND DATE(`date`) BETWEEN '$startDate' AND '$endDate'";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link rel="icon" type="image/x-icon" href="logo.png">
    <title id="pageTitle"></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- DataTables CSS and Buttons Extension CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.0.1/css/buttons.dataTables.min.css">
    <!-- ========================================================= -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.print.min.js"></script>
    <!-- Your other CSS and JS files -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dateTime.min.css">
    <link rel="stylesheet" href="assets/css/select.dataTables.min.css">
    <link rel="stylesheet" href="assets/css/editor.dataTables.min.css">
    <!-- CSS for Icons -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">

</head>

<body>
    <section class="dashboard">
        <h1 class="shine" target="_blank"><i class="uil uil-file-alt"></i> REPORT</h1>
    </section>

    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="data_table" style="border: 2px solid blue; box-shadow: 5px 10px #888888;">

                    <form action="dailyreport_admin.php" method="post">
                        <center>
                            <label for="startDate"><b>From:</b></label>
                            <input class="date" type="date" name="startDate" id="startDate"
                                value="<?php echo date('m/d/Y', strtotime($startDate)); ?>">
                            <label for="endDate"><b>To:</b></label>
                            <input class="date" type="date" name="endDate" id="endDate"
                                value="<?php echo date('m/d/Y', strtotime($endDate)); ?>"><br>
                        </center>
                        <button class="btn btn-primary" id="btnAll" onclick="setTransactionType('all')">All
                            Transactions</button>
                        <input type="hidden" name="showTransactions" id="showTransactions" value="">
                        <button class="btn btn-primary" id="btnYesterday"
                            onclick="setTransactionType('yesterday')">Yesterday's Transactions</button>
                        <button class="btn btn-primary" id="btnToday" onclick="setTransactionType('today')">Today's
                            Transactions</button>
                    </form>
                    <table id="example" class="table  table-hover table-bordered">
                        <thead class="table-primary">
                            <tr>
                                <th>Select</th>
                                <th>Branch</th>
                                <th>Company</th>
                                <th>Contract</th>
                                <th>Stall</th>
                                <th>Date</th>
                                <th>MOP</th>
                                <th>PaidBy</th>
                                <th>Transact#</th>
                                <th>Month</th>
                                <th>Charges</th>
                                <th>Amount</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Initialize total amount variable
                            $totalAll = 0;
                            $totalYesterday = 0;
                            $totalToday = 0;

                            if (isset($_POST['showTransactions'])) {
                                $transactionType = $_POST['showTransactions'];


                                $tableNames = array('imallantipolo', 'imallcamarin', 'imallcanlubang', 'imallfamy', 'cogeotownplaza', 'apmcommercial', 'citicentre');

                                foreach ($tableNames as $tableName) {
                                    $query = "";

                                    switch ($transactionType) {
                                        case 'all':
                                            $query = "SELECT * FROM `$tableName` WHERE 1 $dateFilter ORDER BY `date`";
                                            break;

                                        case 'yesterday':
                                            // Get yesterday's date in the format "Y-m-d"
                                            $yesterday_date = date('Y-m-d', strtotime('-1 day'));
                                            $query = "SELECT * FROM `$tableName` WHERE DATE(`date`) = '$yesterday_date' $dateFilter ORDER BY `date`";
                                            break;

                                        case 'today':
                                            // Get today's date in the format "Y-m-d"
                                            $today_date = date('Y-m-d');
                                            $query = "SELECT * FROM `$tableName` WHERE DATE(`date`) = '$today_date' $dateFilter ORDER BY `date`";
                                            break;
                                    }




                                    $result = $conn->query($query);

                                    while ($row = $result->fetch_assoc()):
                                        // Display transactions here
                            
                                        // Remove commas and convert to float
                                        $amount = floatval(str_replace(',', '', $row['total']));

                                        // Sum up the amounts for each transaction type
                                        $totalAll += $amount;

                                        // Check if the date matches yesterday (without considering the time)
                                        $isYesterday = date('Y-m-d', strtotime($row['date'])) == date('Y-m-d', strtotime('-1 day'));

                                        // Check if the date matches today (without considering the time)
                                        $isToday = date('Y-m-d', strtotime($row['date'])) == date('Y-m-d');

                                        $totalYesterday += $isYesterday ? $amount : 0;
                                        $totalToday += $isToday ? $amount : 0;
                                        ?>

                                        <tr>
                                            <td></td>
                                            <td>
                                                <?php echo $row['branch']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['company']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['contract']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['stall']; ?>
                                            </td>
                                            <td>
                                                <?php echo date('F d, Y', strtotime($row['date'])); ?>
                                            </td>
                                            <td>
                                                <?php echo $row['payment']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['paidby']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['transaction_id']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['displayedMonth'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['displayedCharges'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['displayedAmount'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['total'] ?>
                                            </td>
                                        </tr>

                                    <?php endwhile;
                                }
                            }
                            ?>
                        <tfoot>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td style="text-align:right;"> <strong>Total:</strong></td>
                                <td id="totalAmount" style="text-align: left;"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/datatables.min.js"></script>
    <script src="assets/js/pdfmake.min.js"></script>
    <script src="assets/js/vfs_fonts.js"></script>
    <script src="assets/js/select.js"></script>
    <script src="assets/js/dataTables.editor.min.js"></script>
    <script>
        $(document).ready(function () {
            var table = $('#example').DataTable({
                columnDefs: [
                    {
                        className: 'select-checkbox',
                        targets: 0
                    }
                ],
                select: {
                    style: 'multi+shift',
                    selector: 'td:first-child'
                },
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                pageLength: 10,
                dom: 'lBfrtip<"footer">',
                buttons: [
                    { extend: 'copy', footer: true, exportOptions: { columns: ':not(:first-child)' } },
                    { extend: 'excel', footer: true, exportOptions: { columns: ':not(:first-child)' } },
                    { extend: 'csv', footer: true, exportOptions: { columns: ':not(:first-child)' } },
                    { extend: 'pdf', footer: true, exportOptions: { columns: ':not(:first-child)' } },
                    {
                        extend: 'selectAll',
                        text: 'Select All',
                        className: 'btn-primary',
                    },
                    {
                        extend: 'selectNone',
                        text: 'Select None',
                        className: 'btn-danger',
                    },
                ],
                language: {
                    buttons: {
                        selectAll: "Select all items",
                        selectNone: "Select none"
                    }
                },
                ordering: false,
                destroy: true,
                responsive: true,
            });

            $('#example tbody').on('mousedown', 'input[type="checkbox"]', function (e) {
                e.preventDefault();
                var tr = $(this).closest('tr');
                tr.toggleClass('selected');
                table.rows(tr).select();
                updateTotalAmount(table);
            });

            table.on('select', function () {
                updateTotalAmount(table);
            });

            table.on('deselect', function () {
                updateTotalAmount(table);
            });

            displayTotalAmount();
        });
        function setTransactionType(type) {
            document.getElementById('showTransactions').value = type;
            document.querySelector('form').submit();
        }

        function updateTotalAmount(table) {
            var selectedRows = table.rows('.selected').data();
            var totalAmount = 0;

            // Calculate the total amount for selected rows
            for (var i = 0; i < selectedRows.length; i++) {
                totalAmount += parseFloat(selectedRows[i][12].replace(',', ''));
            }

            // Use toLocaleString for formatting with commas
            var formattedTotal = totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('totalAmount').innerHTML = formattedTotal;
        }


        // Display the total amount based on the selected transaction type
        function displayTotalAmount() {
            var totalAmount = 0;
            var transactionType = "<?php echo $transactionType; ?>";

            if (transactionType === 'all') {
                totalAmount = <?php echo $totalAll; ?>;
            } else if (transactionType === 'yesterday') {
                totalAmount = <?php echo $totalYesterday; ?>;
            } else if (transactionType === 'today') {
                totalAmount = <?php echo $totalToday; ?>;
            }

            // Format the total amount with commas and two decimal places
            totalAmount = totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            document.getElementById('totalAmount').innerHTML = totalAmount;
        }

        // Call the function to display the total amount
        displayTotalAmount();


    </script>



</body>

</html>