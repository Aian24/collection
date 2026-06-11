<?php
session_start();
@include 'config.php';
@include 'navadmin.php';
error_reporting(0);

// Check if the user is logged in
if (!isset($_SESSION['admin_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}

// Check if a branch is selected
if (isset($_GET['branch'])) {
    $selectedBranch = $_GET['branch'];

    // Switch statement to determine the target table based on the user's branch
    switch ($selectedBranch) {
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
            $branchTable = 'default_table';
            break;
    }

    $filterCondition = "WHERE `branch` = '$selectedBranch'";
} else {
    $filterCondition = ""; // Show all transactions if no branch is selected
}

$totalAll = 0;

// Check if 'from' and 'to' parameters are set in the URL
if (isset($_GET['from']) && isset($_GET['to'])) {
    $fromDate = $_GET['from'];
    $toDate = $_GET['to'];

    // Convert date format using STR_TO_DATE
    $fromDate = date('Y-m-d', strtotime($fromDate));
    $toDate = date('Y-m-d', strtotime($toDate));

    // Add the date range condition to the existing filter
    $filterCondition .= " AND STR_TO_DATE(`date`, '%Y-%m-%d') BETWEEN '$fromDate' AND '$toDate'";
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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="assets/css/dateTime.min.css">
    <link rel="stylesheet" href="assets/css/select.dataTables.min.css">


    <div class="container">
        <div class="row">
            <div class="col-12">
            <div class="data_table" style="border: 2px solid blue; box-shadow: 5px 10px #888888;">
                <center>
            <div class="date-filter">
                <label for="fromDate"><b>From:</b></label>
                <input class="date" type="date" id="fromDate" name="fromDate">

                <label for="toDate"><b>To:</b></label>
                <input class="date" type="date" id="toDate" name="toDate">

                <button class="btn btn-primary" onclick="applyDateFilter()">Filter</button>
            </div>
            </center>
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
                           $qry = $conn->query("SELECT * FROM `$branchTable` $filterCondition ORDER BY `date` DESC");

                            // Initialize $totalAll
                            $totalAll = 0;

                            while ($row = $qry->fetch_assoc()):
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
                                        <?php echo $row['displayedMonth']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['displayedCharges']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['displayedAmount']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['total']; ?>
                                    </td>
                                   
                                </tr>
                            <?php endwhile; ?>
                            <tfoot>
                            <tr>
                               <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>    
                                <td style="text-align:right;"> <strong>Total:</strong></td>
                                <td id="totalAmount" style="text-align: left;"></td>
                            </tr>
                        </tfoot>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- =======  Data-Table  = End  ===================== -->
    <!-- ============ Java Script Files  ================== -->


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

    // Add event listener for checkbox changes
    $('#example tbody').on('mousedown', 'input[type="checkbox"]', function (e) {
        // Prevent default behavior to avoid clearing the selection
        e.preventDefault();
        // Get the current row
        var tr = $(this).closest('tr');
        // Toggle the selected class on the row
        tr.toggleClass('selected');
        // Update the DataTables selection
        table.rows(tr).select();
        // Update the total amount
        updateTotalAmount(table);
    });

    table.on('select', function () {
        // Update the total amount when a row is selected
        updateTotalAmount(table);
    });

    table.on('deselect', function () {
        // Update the total amount when a row is deselected
        updateTotalAmount(table);
    });

    // Call the function to display the total amount
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

        <?php
        // Initialize totalAll
        $totalAll = 0;
        $qry = $conn->query("SELECT * FROM `$branchTable` $filterCondition ORDER BY `date` DESC");

        while ($row = $qry->fetch_assoc()):
            $totalAll += floatval(str_replace(',', '', $row['displayedAmount']));
        endwhile;
        ?>

        totalAmount = <?php echo $totalAll; ?>;

      // Format the total amount with commas and two decimal places
      totalAmount = totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        document.getElementById('totalAmount').innerHTML = totalAmount;
    }

    // Call the function to display the total amount after the page is loaded
    window.onload = function () {
        displayTotalAmount();
    };
</script>


<script>
    function applyDateFilter() {
        var fromDate = document.getElementById('fromDate').value;
        var toDate = document.getElementById('toDate').value;

        // Redirect to the same page with the selected date range as query parameters
        window.location.href = 'branch.php?branch=<?php echo urlencode($selectedBranch); ?>&from=' + fromDate + '&to=' + toDate;
    }
</script>


</body>

</html>