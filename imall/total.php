<?php
   // Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Initialize variables to store the calculated totals
    $dailyTotal = 0;
    $weeklyTotal = 0;
    $monthlyTotal = 0;
    $yearlyTotal = 0;

    // Function to calculate the totals
    function calculateTotalSales($conn, &$dailyTotal, &$weeklyTotal, &$monthlyTotal, &$yearlyTotal) {
        // Fetch all records from the "ar" table
        $query = "SELECT total, date FROM ar";
        $result = $conn->query($query);

        if ($result->num_rows > 0) {
            $totalRecords = $result->fetch_all(MYSQLI_ASSOC);

            $today = strtotime('today');
            $startOfWeek = strtotime('last sunday');
            $startOfMonth = strtotime('first day of this month');
            $startOfYear = strtotime('first day of January this year');

            foreach ($totalRecords as $record) {
                $total = $record['total'];
                $saleDate = strtotime($record['date']);

                // Remove non-numeric characters and convert to an integer
                $total = intval(preg_replace('/[^0-9]/', '', $total));

                // Calculate daily total
                if ($saleDate >= $today) {
                    $dailyTotal += $total;
                }

                // Calculate weekly total
                if ($saleDate >= $startOfWeek) {
                    $weeklyTotal += $total;
                }

                // Calculate monthly total
                if ($saleDate >= $startOfMonth) {
                    $monthlyTotal += $total;
                }

                // Calculate yearly total
                if ($saleDate >= $startOfYear) {
                    $yearlyTotal += $total;
                }
            }
        } else {
            echo "No data found in the 'ar' table.";
        }
    }

    // Call the function to calculate the totals
    calculateTotalSales($conn, $dailyTotal, $weeklyTotal, $monthlyTotal, $yearlyTotal);

    // Close the database connection
    $conn->close();
    ?>
    
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Total Sales</title>

         <!----======== CSS ======== -->
    <link rel="stylesheet" href="admin.css">
    <!----===== Iconscout CSS ===== -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
</head>
<body>


<div class="boxes">
<div class="box box2">
    <h2>Daily Total Sales</h2>
    <span class="total"><?= number_format($dailyTotal) ?></span>
    </div>
    <div class="box box2">
    <h2>Weekly Total Sales</h2>
    <span class="total"><?= number_format($weeklyTotal) ?></span>
    </div>
    <div class="box box2">
    <h2>Monthly Total Sales </h2>
    <span class="total"><?= number_format($monthlyTotal) ?></span>
    </div>
    <div class="box box2">
    <h2>Yearly Total Sales </h2>
    <span class="total"><?= number_format($yearlyTotal) ?></span>
    </div>
    </div>

            
</body>
</html>
<script src="script.js"></script>