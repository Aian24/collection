<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();

error_reporting(0);
// Check if the user is logged in
if (!isset($_SESSION['user_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}

// Get the branch information of the logged-in user
$userBranch = $_SESSION['branch'];

// Get the date filter values
$startDate = isset($_POST['startDate']) ? date('Y-m-d', strtotime($_POST['startDate'])) : date("Y-m-d");
$endDate = isset($_POST['endDate']) ? date('Y-m-d', strtotime($_POST['endDate'])) : date("Y-m-d");


if (isset($_POST['showTransactions'])) {
    $transactionType = $_POST['showTransactions'];
    $branchFilter = "";

    // Modify the branch filter based on your requirement
    if ($userBranch != 'all') {
        $branchFilter = " AND branch = '$userBranch'";
    }

    // Modify the date filter based on your requirement
    if (empty($_POST['startDate']) && empty($_POST['endDate'])) {
        // If no specific date range is set, consider all transactions
        $dateFilter = "";
    } else {
        $dateFilter = " AND DATE(`date`) BETWEEN '$startDate' AND '$endDate'";
    }



    // Switch statement to determine the target table based on the user's branch
    switch ($userBranch) {
        case 'iMall Antipolo':
            $branchTable = 'imallantipolo';
            break;
        case 'iMall Canlubang':
            $branchTable = 'imallcanlubang';
            break;
        case 'iMall Camarin':
            $branchTable = 'imallcamarin';
            break;
        case 'iMall Famy':
            $branchTable = 'imallfamy';
            break;
        case 'Cogeo Town Plaza':
            $branchTable = 'cogeotownplaza';
            break;
        case 'Antipolo Market':
            $branchTable = 'antipolomarket';
            break;
        case 'APM Commercial':
            $branchTable = 'apmcommercial';
            break;
        case 'CITI Centre':
            $branchTable = 'citicentre';
            break;

        default:
            // Handle cases for other branches or set a default table
            break;
    }
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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dateTime.min.css">
    <link rel="stylesheet" href="assets/css/select.dataTables.min.css">


</head>

<body>


    <!-- nav -->
    <nav class="topnav" id="myTopnav">
        <a href="" style="font-weight: 600; font-size: 26px; padding: 5px; margin-left: 10px;">IMALL</a>
        <?php if (isset($_SESSION['user_name'])): ?>
            <a href="index.php?logout" style="float: right;">Log Out</a>
        <?php endif; ?>
        <a href="contactus.php" style="float: right;">Contact Us </a>
        <a href="CreateAR.php" style="float: right;">Create AR </a>
        <a href="user_page.php" style="float: right;">Transaction Report</a>
        <a href="javascript:void(0);" class="icon" onclick="myFunction()">
            <i class="fa fa-bars"></i>
        </a>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="data_table" style="border: 2px solid blue; box-shadow: 5px 10px #888888;">

                    <form action="user_page.php" method="post">
                        <center>
                            <label for="startDate">From:</label>
                            <input type="date" name="startDate" id="startDate"
                                value="<?php echo date('m/d/Y', strtotime($startDate)); ?>">
                            <label for="endDate">To:</label>
                            <input type="date" name="endDate" id="endDate"
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
                            // Initialize total amount variables
                            $totalAll = $totalYesterday = $totalToday = 0;

                            if (isset($_POST['showTransactions'])) {
                                $transactionType = $_POST['showTransactions'];
                                $branchFilter = " AND branch = '$userBranch'";

                                if ($transactionType === 'all') {
                                    // Query to fetch all transactions for the logged-in user's branch
                                    $all_query = $conn->query("SELECT * FROM `$branchTable` WHERE 1 $branchFilter $dateFilter ORDER BY `date` ASC");
                                    while ($row = $all_query->fetch_assoc()):
                                        // Display all transactions here
                                        $totalAll += floatval(str_replace(',', '', $row['total']));
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
                                } elseif ($transactionType === 'yesterday') {
                                    // Query to fetch yesterday's transactions for the logged-in user's branch
                                    $yesterday_query = $conn->query("SELECT * FROM `$branchTable` WHERE DATE(`date`) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) $branchFilter $dateFilter ORDER BY `date` ASC");
                                    while ($row = $yesterday_query->fetch_assoc()):
                                        // Display yesterday's transactions here
                                        $totalYesterday += floatval(str_replace(',', '', $row['total']));
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
                                } elseif ($transactionType === 'today') {
                                    // Query to fetch today's transactions for the logged-in user's branch
                                    $today_query = $conn->query("SELECT * FROM `$branchTable` WHERE DATE(`date`) = CURDATE() $branchFilter $dateFilter ORDER BY `date` ASC");
                                    while ($row = $today_query->fetch_assoc()):
                                        // Display today's transactions here
                                        $totalToday += floatval(str_replace(',', '', $row['total']));
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

                        </tbody>
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
                                <td id="total" style="text-align: left;"></td>
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
                updateTotal(table);
            });

            table.on('select', function () {
                updateTotal(table);
            });

            table.on('deselect', function () {
                updateTotal(table);
            });

            displayTotal();
        });


        function setTransactionType(type) {
            document.getElementById('showTransactions').value = type;
            document.querySelector('form').submit();
        }

        function updateTotal(table) {
            var selectedRows = table.rows('.selected').data();
            var total = 0;

            // Calculate the total amount for selected rows
            for (var i = 0; i < selectedRows.length; i++) {
                total += parseFloat(selectedRows[i][12]);
            }

            // Use toLocaleString for formatting with commas
            var formattedTotal = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('total').innerHTML = formattedTotal;
        }

        // Display the total amount based on the selected transaction type
        function displayTotal() {
            var total = 0;
            var transactionType = "<?php echo $transactionType; ?>";

            // Assign the PHP values directly to JavaScript variables
            var totalAll = <?php echo json_encode(floatval($totalAll)); ?>;
            var totalYesterday = <?php echo json_encode(floatval($totalYesterday)); ?>;
            var totalToday = <?php echo json_encode(floatval($totalToday)); ?>;


            if (transactionType === 'all') {
                total = totalAll;
            } else if (transactionType === 'yesterday') {
                total = totalYesterday;
            } else if (transactionType === 'today') {
                total = totalToday;
            }

            // Use toLocaleString for formatting with commas
            var formattedTotal = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('total').innerHTML = formattedTotal;

        }

        var options = { year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('pageTitle').innerText = new Date().toLocaleDateString('en-US', options);
    </script>


</body>

</html>