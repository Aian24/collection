<?php

// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['admin_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ackowledgement Report</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- ========================================================= -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <!----======== CSS ======== -->
    <link rel="stylesheet" href="admin.css">
    <script src="https://cdn.lordicon.com/lordicon-1.1.0.js"></script>

    <!----===== Iconscout CSS ===== -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">

</head>

<body>

    <section class="dashboard">
        <h1 class="shine" target="_blank"> <i class="uil uil-file-graph"></i>ALL AR</h1>

        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="datatable1">
                        <form action="AllAR.php" method="post">
                            <table id="example" class="table  table-hover table-bordered">
                        </form>
                        <thead class="table-primary ">
                            <tr>
                                <th>Company</th>
                                <th>Contract</th>
                                <th>Stall</th>
                                <th>Date</th>
                                <th>Check#</th>
                                <th>MOP</th>
                                <th>Transact#</th>
                                <th>PaidBy</th>
                                <th>Month</th>
                                <th>Charges</th>
                                <th>Amount</th>
                                <th>Total</th>


                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Modify the SQL query to include LIMIT and OFFSET
                            $qry = $conn->query("SELECT * FROM `ar` ORDER BY `transaction_id`");

                            while ($row = $qry->fetch_assoc()):
                                ?>
                                <tr>
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
                                        <?php echo $row['date']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['checknumber']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['payment']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['transaction_id']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['paidby']; ?>
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
                                        <?php echo $row['total']; ?>
                                    </td>


                                </tr>
                            <?php endwhile; ?>
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
        <script src="assets/js/custom.js"></script>
        <script>
            function setTransactionType(type) {
                document.getElementById('showTransactions').value = type;
                document.querySelector('form').submit();
            }
        </script>



</body>

</html>