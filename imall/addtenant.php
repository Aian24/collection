<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get data from the form
    $company = isset($_POST['company']) ? $_POST['company'] : '';
    $contract = isset($_POST['contract']) ? $_POST['contract'] : '';
    $stall = isset($_POST['stall']) ? $_POST['stall'] : '';
    $trade = isset($_POST['trade']) ? $_POST['trade'] : '';
    $location = isset($_POST['location']) ? $_POST['location'] : '';

    // Determine the target table based on the branch
    switch ($location) {
        case 'iMall Antipolo':
            $targetTable = 'imallantipolotenants';
            break;
        case 'iMall Canlubang':
            $targetTable = 'imallcanlubangtenants';
            break;
        case 'iMall Camarin':
            $targetTable = 'imallcamarintenants';
            break;
        case 'iMall Famy':
            $targetTable = 'imallfamytenats';
            break;
        case 'Cogeo Town Plaza':
            $targetTable = 'cogeotownplazatenants';
            break;
        case 'APM Commercial':
            $targetTable = 'apmcommercialtenats';
            break;
        case 'CITI Centre':
            $targetTable = 'citicentretenats';
            break;
        // Add more cases for other branches if needed
        default:
            // Handle the case when the branch doesn't match any specific table
            echo "Invalid branch selected.";
            return;
    }


    // Insert data into the determined target table
    $sql = "INSERT INTO $targetTable (company, contract, stall, trade) VALUES ('$company', '$contract', '$stall', '$trade')";

    if ($conn->query($sql) === TRUE) {
        echo '<script>alert("Inserting New successful");</script>';
        echo '<script>window.location.href = "update_tenants.php";</script>';
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    // Close the database connection
    $conn->close();
}
?>

<h1 class="shinedash" style="margin-left:7%;" target="_blank"> <i class="uil uil-plus-circle"></i> ADD TENANT</h1>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert New Tenant</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
</head>

<body>
    <center>
        <div class="form-container">
            <form action="addtenant.php" method="post">
                <h3>Insert New Tenant</h3>
                <label for="location">Location:</label>
                <select id="location" name="location" required>
                    <option value="" disabled selected>Select Branch</option>
                    <option value="iMall Antipolo">iMall Antipolo</option>
                    <option value="iMall Canlubang">iMall Canlubang</option>
                    <option value="iMall Camarin">iMall Camarin</option>
                    <option value="iMall Famy">iMall Famy</option>
                    <option value="Cogeo Town Plaza">Cogeo Town Plaza</option>
                    <option value="APM Commercial">APM Commercial</option>
                    <option value="CITI Centre">CITI Centre</option>

                </select><br>
                <label for="company">Company:</label>
                <input type="text" id="company" name="company" required><br>

                <label for="contract">Contract:</label>
                <input type="text" id="contract" name="contract" required><br>

                <label for="stall">Stall:</label>
                <input type="text" id="stall" name="stall" required><br>

                <label for="trade">Trade:</label>
                <input type="text" id="trade" name="trade" required><br>

                <input class="form-btn" type="submit" value="Insert Data">
            </form>
        </div>
    </center>
</body>

</html>