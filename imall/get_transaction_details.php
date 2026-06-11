<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["contract"])) {
    // Retrieve the contract parameter from the POST data
    $contract = $_POST["contract"];

    $tables = ['imallantipolo', 'imallcamarin', 'imallcanlubang', 'cogeotownplaza', 'antipolomarket'];
    
    // Create an array to store the results from each table
    $tableResults = array();

    // Iterate through each table in $tables array
    foreach ($tables as $table) {
        // Modify the SQL query to retrieve transaction details from the current table
        $sql = "SELECT * FROM $table WHERE contract = '" . mysqli_real_escape_string($conn, $contract) . "'";
        $result = $conn->query($sql);

        // Check if there are any transaction records
        if ($result->num_rows > 0) {
            // Add the result set to the $tableResults array
            $tableResults[$table] = $result;
        }
    }

    // Check if there are any results
    if (!empty($tableResults)) {
        // Add a scrollable container
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets//Responsive/css/responsive.bootsrap.css">
    <link rel="stylesheet" href="modalHistory.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.lordicon.com/lordicon-1.3.0.js"></script>
    <!----===== Iconscout CSS ===== -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">

</head>
<body>
<body>
<body>
            <div class="modalcontainer">
                <div class="row">
                    <div class="col-12">
                        <table id="example" class="table table-hover table-bordered" style="margin-top:1%; width:auto;">
                            <thead class="table-primary">
                                <tr>
                                    <!-- Table header columns -->
                                    <th>Transaction ID</th>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Month</th>
                                    <th>Charges</th>
                                    <th>Amount</th>
                                    <th>Total</th>
                                    <th>Paid By</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Iterate through each table result and display the rows
                                foreach ($tableResults as $table => $result) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                ?>
                                        <tr>
                                            <td class="box"><?php echo $row['transaction_id']; ?></td>
                                            <td class="box"><?php echo date('F d, Y', strtotime($row['date'])); ?></td>
                                            <td class="box"><?php echo $row['branch']; ?></td>
                                            <td class="box"><?php echo $row['displayedMonth']; ?></td>
                                            <td class="box"><?php echo $row['displayedCharges']; ?></td>
                                            <td class="box"><?php echo $row['displayedAmount']; ?></td>
                                            <td class="box"><?php echo $row['total']; ?></td>
                                            <td class="box"><?php echo $row['paidby']; ?></td>
                                            <td class="box"><?php echo $row['payment']; ?></td>
                                        </tr>
                                <?php
                                    }
                                    // Reset the result pointer to the beginning of the result set
                                    mysqli_data_seek($result, 0);
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add DataTables scripts and initialization here -->
            <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
            <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

            <script>
                $(document).ready(function () {
                    $('#example').DataTable({
                        "paging": true,
                        "lengthChange": true,
                        "searching": true,
                        "ordering": true,
                        "info": true,
                        "autoWidth": false,
                        // Add any additional configuration options here
                    });
                });
            </script>

            <!-- Include Bootstrap and other scripts -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js"></script>
            <script src="https://cdn.lordicon.com/lordicon-1.3.0.js"></script>
            <script src="https://unicons.iconscout.com/release/v4.0.0/js/line.js"></script>
        </body>

        </html>
<?php
    } else {
        echo 'No transaction history.';
    }
} else {
    // Handle the case when the contract parameter is not provided
    echo 'Enter a contract to search.';
}
?>