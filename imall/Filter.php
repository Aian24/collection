
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
    <title>Filter</title>
   <!-- Google Fonts -->
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="admin.css">
    <!-- CSS for Icons -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">


</head>
<body>

    <section class="dashboard">
       
    <h1 class="shine" target="_blank" > <i class="uil uil-filter"></i> FILTER</h1>
    <div class="container">
    
                        <br><br><br><br>
                        <form action="Filter.php" method="GET" style="margin-left:15%;">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" name="from_date" value="<?php if(isset($_GET['from_date'])){ echo $_GET['from_date']; } ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" name="to_date" value="<?php if(isset($_GET['to_date'])){ echo $_GET['to_date']; } ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group"><br>
                                      <button type="submit" style="margin-left:20%;">Click to Filter</button>
                                    </div>
                                </div>
                            </div>
                        </form>
               <br><br><br>
            <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="datatable">
                <table id="example" class="table  table-hover table-bordered">
        <thead class="table-primary">
             
                                <tr>
                                    <th>Company</th>
                                    <th>Contract</th>
                                    <th>Stall</th>
                                    <th>Date</th>
                                    <th>Check Number</th>
                                    <th>Total</th>
                                    <th>MOP</th>
                                    <th>Transaction #</th>
                                    <th>Paid By</th>
                                    <th>Month</th>
                                    <th>Charges</th>
                                    <th>Amount</th>
                                
                                </tr>
                            </thead>
                            <tbody>
                            
                            <?php 
                                $con = mysqli_connect("localhost","root","","imall");

                                if(isset($_GET['from_date']) && isset($_GET['to_date']))
                                {
                                    $from_date = $_GET['from_date'];
                                    $to_date = $_GET['to_date'];

                                    $query = "SELECT * FROM imallantipolo WHERE DATE_FORMAT(date, '%Y-%m-%d') BETWEEN '$from_date' AND '$to_date'";
                                    $query_run = mysqli_query($con, $query);

                                    if(mysqli_num_rows($query_run) > 0)
                                    {
                                        foreach($query_run as $row)
                                        {
                                            ?>
                                            <tr>
                                                <td><?= $row['company']; ?></td>
                                                <td><?= $row['contract']; ?></td>
                                                <td><?= $row['stall']; ?></td>
                                                <td><?php echo date('F d, Y', strtotime($row['date'])); ?><td>
                                                <td><?= $row['checknumber']; ?></td>
                                                <td><?= $row['total']; ?></td>
                                                <td><?= $row['payment']; ?></td>
                                                <td><?= $row['transaction_id']; ?></td>
                                                <td><?= $row['paidby']; ?></td>
                                                <td><?= $row['displayedMonth']; ?></td>
                                                <td><?= $row['displayedCharges']; ?></td>
                                                <td><?= $row['displayedAmount']; ?></td>
                                            
                                            <?php
                                        }
                                    }
                                  
                                }
                            ?>
                            </tbody>
                        </table>
                  
                </div>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div>
   
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/datatables.min.js"></script>
    <script src="assets/js/pdfmake.min.js"></script>
    <script src="assets/js/vfs_fonts.js"></script>
    <script src="assets/js/custom.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>