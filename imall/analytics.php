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

// Update: Fetch distinct branches from the new tables
$newBranches = ['imallantipolo', 'imallcamarin', 'imallcanlubang', 'imallfamy','cogeotownplaza','apmcommercial','citicentre'];
$branches = array_map(function ($branch) {
    return ['branch' => $branch];
}, $newBranches);

// Function to calculate the totals for a specific branch with date filtering
function calculateBranchTotalSalesWithDateFilter($conn, $branch, $fromDate, $toDate, &$dailyTotal, &$weeklyTotal, &$monthlyTotal, &$yearlyTotal)
{
    $branchTotal = 0;

    // Update: Fetch records from the new table for the specific branch with date filtering
    $query = "SELECT displayedAmount, date FROM $branch WHERE date >= '$fromDate' AND date <= '$toDate 23:59:59'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $totalRecords = $result->fetch_all(MYSQLI_ASSOC);

        $startOfWeek = strtotime('last sunday', strtotime($fromDate));
        $startOfMonth = strtotime('first day of ' . date('F Y', strtotime($fromDate)));
        $startOfYear = strtotime('first day of January ' . date('Y', strtotime($fromDate)));

        foreach ($totalRecords as $record) {
            $total = $record['displayedAmount'];
            $saleDate = strtotime($record['date']);

            // Remove non-numeric characters and convert to an integer
            $total = intval(preg_replace('/[^0-9]/', '', $total));

            // Calculate daily total
            if ($saleDate >= strtotime($fromDate) && $saleDate <= strtotime($toDate . ' 23:59:59')) {
                $dailyTotal += $total;
            }

            // Calculate weekly total
            if ($saleDate >= $startOfWeek && $saleDate <= strtotime($toDate . ' 23:59:59')) {
                $weeklyTotal += $total;
            }

            // Calculate monthly total
            if ($saleDate >= $startOfMonth && $saleDate <= strtotime($toDate . ' 23:59:59')) {
                $monthlyTotal += $total;
            }

            // Calculate yearly total
            if ($saleDate >= $startOfYear && $saleDate <= strtotime($toDate . ' 23:59:59')) {
                $yearlyTotal += $total;
            }

            $branchTotal += $total;
        }
    }

    // Return the calculated branch total
    return $branchTotal;
}



// Get the selected branch from the dropdown
$selectedBranch = isset($_GET['branch']) ? $_GET['branch'] : '';
$fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : '';
$toDate = isset($_GET['toDate']) ? $_GET['toDate'] : '';

// Check if filter parameters are empty, set them to the current week (Monday to Sunday)
if (empty($fromDate)) {
    $fromDate = date("Y-m-d", strtotime('first day of January'));
}


if (empty($toDate)) {
    $toDate = date("Y-m-d", strtotime('last day of December'));
}



// Initialize variables to store the calculated totals
$dailyTotal = 0;
$weeklyTotal = 0;
$monthlyTotal = 0;
$yearlyTotal = 0;
$branchTotal = 0;  // Initialize branchTotal

// Call the function to calculate the totals for the selected branch or all branches with date filtering
if ($selectedBranch === '') {
    $branchTotal = 0;  // Reset branchTotal for "All Branches"
    foreach ($newBranches as $branch) {
        $branchTotal += calculateBranchTotalSalesWithDateFilter($conn, $branch, $fromDate, $toDate, $dailyTotal, $weeklyTotal, $monthlyTotal, $yearlyTotal);
    }
} else {
    $branchTotal = calculateBranchTotalSalesWithDateFilter($conn, $selectedBranch, $fromDate, $toDate, $dailyTotal, $weeklyTotal, $monthlyTotal, $yearlyTotal);
}

// Fetch company summary data based on the selected branch with date filtering
$companyData = [];
$colors = [];

// Update: Adjust date filtering for today
if ($selectedBranch === '') {
    // If 'All Branches' is selected, fetch data from all branches with date filtering
    foreach ($newBranches as $branch) {
        $sql = "SELECT company, COUNT(*) as count FROM $branch WHERE company IS NOT NULL AND TRIM(company) <> '' AND date BETWEEN '$fromDate' AND '$toDate 23:59:59' GROUP BY company ORDER BY count DESC";
        fetchCompanyData($conn, $sql, $companyData, $colors);
    }
} else {
    // Fetch data for the selected branch only with date filtering
    $sql = "SELECT company, COUNT(*) as count FROM $selectedBranch WHERE company IS NOT NULL AND TRIM(company) <> '' AND date BETWEEN '$fromDate' AND '$toDate 23:59:59' GROUP BY company ORDER BY count DESC";
    fetchCompanyData($conn, $sql, $companyData, $colors);
}

function fetchCompanyData($conn, $sql, &$companyData, &$colors)
{
    $result = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $companyName = $row['company'];
        $recordCount = $row['count'];

        // Generate a random color for each company
        $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
        $colors[$companyName] = $color;

        if (!isset($companyData[$companyName])) {
            $companyData[$companyName] = 0;
        }

        $companyData[$companyName] += $recordCount;
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics</title>
    <!-- Include Chart.js library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSS -->
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets//Responsive/css/responsive.bootsrap.css">
    <link rel="stylesheet" href="modalHistory.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.lordicon.com/lordicon-1.3.0.js"></script>
    <!-- Iconscout CSS -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <script src="https://cdn.lordicon.com/lordicon-1.1.0.js"></script>
</head>

<body>
    <section class="dashboard">
        <h1 class="shine" target="_blank"> <i class="uil uil-chart-line"></i>ANALYTICS </h1>
        <br><br><br><br>
        <!-- Add the dropdown filter in your HTML form -->
        <form method="get" action="analytics.php">
    <label for="branch"><strong>Select Branch:</strong></label>
    <select  class="date" name="branch" id="branchanalytics">
        <option value="" <?php echo ($selectedBranch === '') ? 'selected' : ''; ?>>All Branches</option>
        <?php
        foreach ($branches as $branchOption) {
            $branchName = $branchOption['branch'];
            echo "<option value='$branchName' " . (($selectedBranch === $branchName) ? 'selected' : '') . ">$branchName</option>";
        }
        ?>
    </select>

    <!-- Add date inputs -->
    <label for="fromDate"><strong>From:</strong></label>
    <input class="date" type="date" name="fromDate" value="<?php echo $_GET['fromDate'] ?? ''; ?>">

    <label for="toDate"><strong>To:</strong></label>
    <input class="date" type="date" name="toDate" value="<?php echo $_GET['toDate'] ?? ''; ?>">

    <input class="btn btn-primary" type="submit" value="Apply Filter">
</form>


        <div class="dash-content">
            <div class="overview">
                <div class="title">
                    <div class="boxes">
                        <div class="box box" style="margin-right: 10px;">
                            <lord-icon src="https://cdn.lordicon.com/lxizbtuq.json" trigger="hover"
                                style="width:50px;height:50px">
                            </lord-icon>
                            <span class="text">Daily Total Sales </span>
                            <span class="total">
                                <?= number_format($dailyTotal) ?>
                            </span>
                        </div>

                        <div class="box box2" style="margin-right: 10px;">
                            <lord-icon src="https://cdn.lordicon.com/jtiihjyw.json" trigger="hover"
                                style="width:50px;height:50px">
                            </lord-icon>
                            <span class="text">Weekly Total Sales </span>
                            <span class="total">
                                <?= number_format($weeklyTotal) ?>
                            </span>
                        </div>

                        <div class="box box3" style="margin-right: 10px;">
                            <lord-icon src="https://cdn.lordicon.com/qainaehu.json" trigger="hover"
                                style="width:50px;height:50px">
                            </lord-icon>
                            <span class="text">Monthly Total Sales </span>
                            <span class="total">
                                <?= number_format($monthlyTotal) ?>
                            </span>
                        </div>

                        <div class="box box4" style="margin-right: 10px;">
                            <lord-icon src="https://cdn.lordicon.com/ffvsbfvt.json" trigger="hover"
                                style="width:50px;height:50px">
                            </lord-icon>
                            <span class="text">Yearly Total Sales </span>
                            <span class="total">
                                <?= number_format($yearlyTotal) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="graph">
            <canvas id="dailyBarChart"></canvas>
            <div>
                <div class="pie" style=" margin-top:-48%;  margin-left:-85%;">
                    <div class="comContainer">
                        <div style="width: 500px; height: 500px;">
                            <canvas id="myPieChart"></canvas>
                        </div>
                    </div>
                    <center>
                        <br><br> <br>
                        <div class="container">
                            <div class="row">
                                <div class="col-12">
                                    <div class="datatable p-3" style="border: 2px solid blue; box-shadow: 5px 10px #888888;">
                                        <table id="example" class="table  table-hover table-bordered" >
                                            <thead class="table-primary">
                                                <h1 style="color: blue;">Company Summary</h1>

                                                <tr>
                                                    <th>Company Name</th>
                                                    <th>Record Count</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                foreach ($companyData as $companyName => $recordCount) {
                                                    echo '<tr>';
                                                    echo '<td style="text-align:center;">' . $companyName . '</td>';
                                                    echo '<td style="text-align:center;">' . $recordCount . '</td>';
                                                    echo '</tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                </div>
                </center>
                <script>
                    window.onload = function () {
                        // Data for the bar graphs
                        // Data for the bar graphs
                        var dailyData = <?= json_encode([$dailyTotal, $weeklyTotal, $monthlyTotal, $yearlyTotal]) ?>;


                        // Get canvas elements
                        var dailyBarChart = document.getElementById('dailyBarChart').getContext('2d');

                        // Define data for the bar graphs
                        var barChartData = {
                            labels: ['Daily', 'Weekly', 'Monthly', 'Yearly'],
                            datasets: [{
                                label: 'Total Sales',
                                backgroundColor: ['#CCCCFF', '#FFBF00', '#4da3ff', '#ffe6ac'],
                                data: dailyData
                            }]
                        };

                        // Create the bar graph for daily totals
                        var options = {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        };

                        new Chart(dailyBarChart, {
                            type: 'bar',
                            data: barChartData,
                            options: options
                        });
                    };
                </script>

                <script>
                    // Extract company names and record counts for chart data
                    var companyNames = <?php echo json_encode(array_keys($companyData)); ?>;
                    var recordCounts = <?php echo json_encode(array_values($companyData)); ?>;
                    var colors = <?php echo json_encode(array_values($colors)); ?>;

                    // Create a pie chart using Chart.js
                    var ctx = document.getElementById('myPieChart').getContext('2d');
                    var myPieChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: companyNames,
                            datasets: [{
                                data: recordCounts,
                                backgroundColor: colors,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                        }
                    });
                </script>

                <script src="https://code.jquery.com/jquery-3.7.0.js"> </script>
                <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"> </script>
                <script src=" https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
                <script src=" assets/Responsive/css/dataTables.responsive.js"></script>
                <script src="assets/js/app.js"></script>
                <script src="script.js"></script>
</body>

</html>