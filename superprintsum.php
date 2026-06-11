<?php
ob_start();
include 'config.php';
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION["username"];
date_default_timezone_set('Asia/Manila');
$current_date_time = date('Y-m-d g:i A');
$total_rent = $total_balance = $total_charges = $total = 0;

$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the selected date or default to today
$selected_date = isset($_POST['selected_date']) ? $_POST['selected_date'] : date('Y-m-d');
$selected_branch = isset($_POST['selected_branch']) ? $_POST['selected_branch'] : $_SESSION["branch"];

// Determine the correct table based on the selected branch
$table = ($selected_branch === 'Sanko Market') ? 'collected' : (($selected_branch === 'APM') ? 'collectedapm' : 'collectednova');

// Fetch totals for the selected date and branch
$query = "SELECT SUM(paidrent) AS total_rent, SUM(paidbal) AS total_balance, SUM(total) AS db_grand_total
          FROM $table
          WHERE DATE(collected_date) = '$selected_date' AND username = '$username'";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_rent = $row['total_rent'];
    $total_balance = $row['total_balance'];
    $db_grand_total = $row['db_grand_total'];
}

// Fetch collector's last name and branch
$collector_query = "SELECT lname, branch FROM users WHERE username = '$username'";
$collector_result = $conn->query($collector_query);
if ($collector_result->num_rows > 0) {
    $collector_row = $collector_result->fetch_assoc();
    $lname = $collector_row['lname'];
}

// Fetch charges
$charges_query = "SELECT charges FROM $table WHERE DATE(collected_date) = '$selected_date' AND username = '$username'";
$charges_result = $conn->query($charges_query);
if ($charges_result->num_rows > 0) {
    while ($charge_row = $charges_result->fetch_assoc()) {
        preg_match_all('/[\d,\.]+/', $charge_row['charges'], $matches);
        foreach ($matches[0] as $match) {
            // Trim trailing periods just in case
            $match = rtrim($match, '.');
            $total_charges += (float) str_replace(',', '', $match);
        }
    }
}

$total = round((float)$db_grand_total, 2);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../images/lc.png">
    <title>Print Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {

            body,
            p,
            h1 {
                font-size: 13px;
            }

            .print-logo {
                width: 12rem;
                margin-bottom: 6%;
            }

            .print-button {
                display: none;
            }

            nav,
            #side-nav,
            #date-filter {
                display: none;
            }
        }

        .print-button {
            text-align: center;
            margin-top: 20px;
        }

        .print-button button {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <nav class="bg-gray-800 p-4 mb-4">
        <div class="flex justify-between items-center">
            <a href="superuser.php"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Back</a>
            <div class="hidden sm:flex sm:items-center sm:space-x-4">
                <a href="index.php" class="text-white">Logout</a>
                <a href="superuser.php" class="text-white">Create Collection</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto mt-8">
        <div class="max-w-lg mx-auto text-center">
            <img src="images/lc.png" alt="Logo" class="print-logo h-auto mx-auto mb-6">
            <p class="mb-8 font-bold">Summary Report</p>
        </div>
        <div id="date-filter" class="text-center mb-4">
            <form id="dateForm" method="post">
                <div>
                    <label for="selected_branch" class="font-bold">Select Branch:</label>
                </div>
                <div>
                    <select name="selected_branch" id="selected_branch" onchange="this.form.submit()"
                        class="border border-gray-500">
                        <option value="Sanko Market" <?php echo $selected_branch === 'Sanko Market' ? 'selected' : ''; ?>>
                            Sanko Market</option>
                        <option value="Nova Market" <?php echo $selected_branch === 'Nova Market' ? 'selected' : ''; ?>>
                            Nova Market</option>
                        <option value="APM" <?php echo $selected_branch === 'APM' ? 'selected' : ''; ?>>
                            APM</option>
                    </select>

                </div>

                <div class="mt-4"> <!-- Add margin for spacing -->
                    <label for="selected_date" class="font-bold">Select Date:</label>
                </div>
                <div>
                    <input type="date" name="selected_date" id="selected_date" value="<?php echo $selected_date; ?>"
                        onchange="this.form.submit()" class="border border-gray-500">
                </div>
            </form>
        </div>


        <div class="summary-details text-center">
            <p>Date: <?php echo $selected_date; ?></p>
            <p>Branch: <?php echo isset($selected_branch) ? $selected_branch : ''; ?></p>
            <p>Collector: <?php echo isset($lname) ? $lname : ''; ?></p>
            <?php if ($total_rent !== null && $total_balance !== null): ?>
                <p>Total Paid (Rent): <?php echo number_format($total_rent, 2); ?></p>
                <p>Total Paid (Balance): <?php echo number_format($total_balance, 2); ?></p>
                <p>Total Charges: <?php echo number_format($total_charges, 2); ?></p>
                <p>Total: <?php echo number_format($total, 2); ?></p>
            <?php endif; ?>
        </div>

        <div class="print-button">
            <button onclick="window.print()">Print Summary</button>
        </div>
    </div>
</body>

</html>