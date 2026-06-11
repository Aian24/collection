<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();
error_reporting(0);

// Check if the user is logged in
if (!isset($_SESSION['user_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}



// Call address
$address = $_SESSION['address'];
// Determine the target table based on the branch
$branch = $_SESSION['branch'];

switch ($branch) {
    case 'iMall Antipolo':
        $targetTable = 'imallantipolo';
        break;
    case 'iMall Canlubang':
        $targetTable = 'imallcanlubang';
        break;
    case 'iMall Camarin':
        $targetTable = 'imallcamarin';
        break;
    case 'iMall Famy':
        $targetTable = 'imallfamy';
        break;
    case 'Cogeo Town Plaza':
        $targetTable = 'cogeotownplaza';
        break;
    case 'Antipolo Market':
        $targetTable = 'antipolomarket';
        break;
    case 'APM Commercial':
        $targetTable = 'apmcommercial';
        break;
    case 'CITI Centre':
        $targetTable = 'citicentre';
        break;

    default:
        // Handle the case when the branch doesn't match any specific table
        echo "Invalid branch selected.";
        return;
}

date_default_timezone_set('Asia/Manila');

// Function to convert currency-formatted strings to integers
function convertToInteger($amount)
{
    // Remove commas and convert to integer
    return intval(str_replace(',', '', $amount));
}

function convertToFloat($amount)
{
    // Remove commas and convert to float
    return floatval(str_replace(',', '', $amount));
}

// Retrieve the email of the logged-in user
$email = $_SESSION['email'];

// Query the database to retrieve the latest receipt details by transaction_id for the specific user's email
$query = "SELECT * FROM `$targetTable` WHERE email = '$email' ORDER BY `transaction_id` DESC LIMIT 1";
$result = mysqli_query($conn, $query);

// Check if there are any transactions
if ($result) {
    if (mysqli_num_rows($result) > 0) {
        // Fetch the latest transaction
        $latestTransaction = mysqli_fetch_assoc($result);

        // Get the transaction_id of the latest transaction
        $latestTransactionId = $latestTransaction['transaction_id'];

        // Query the database to retrieve all entries with the same transaction_id
        $entryQuery = "SELECT * FROM $targetTable WHERE transaction_id = '$latestTransactionId'";
        $entryResult = mysqli_query($conn, $entryQuery);

        // Check if there are any entries with the same transaction_id
        if ($entryResult) {
            if (mysqli_num_rows($entryResult) > 0) {
                // Fetch all entries with the same transaction_id
                $entries = mysqli_fetch_all($entryResult, MYSQLI_ASSOC);

                // Convert 'displayedAmount' values to integers using the custom function and then calculate the total
                $total = number_format(array_sum(array_map('convertToFloat', array_column($entries, 'displayedAmount'))), 2, '.', '');
            } else {
                echo "No entries found for the latest transaction.";
                exit();
            }
        } else {
            echo "Error: " . mysqli_error($conn);
            exit();
        }
    } else {
        echo "No transactions found.";
        exit();
    }
} else {
    echo "Error: " . mysqli_error($conn);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>
    <link rel="stylesheet" href="print.css">

</head>

<body>
    <nav class="topnav" id="myTopnav">
        <a href="" style="font-weight: 500; font-size: 24px; padding: 5px; margin-left: 10px;">
            <?php if (isset($_SESSION['user_name'])): ?>
                PAYMENT ACKNOWLEDGEMENT
            </a>
        <?php endif; ?>
        <a href="contactus.php" style="float: right;">Contact Us </a>
        <a href="user_page.php" style="float: right;">Transaction Report</a>
        <a href="CreateAR.php" style="float: right;">Create AR </a>
        <a onclick="window.print()" style="float: right; cursor: pointer;">Print Receipt</a>
        <a href="javascript:void(0);" class="icon" onclick="myFunction()">
            <i class="fa fa-bars"></i>
        </a>
    </nav>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Your JavaScript code here

            function myFunction() {
                var x = document.getElementById("myTopnav");
                if (x.className === "topnav") {
                    x.className += " responsive";
                } else {
                    x.className = "topnav";
                }
            }

        });
    </script>
    <center>

        <div class="receiptCon" style="border: 1px solid #0082e6;">
            <div class="receiptHeader" style="border-radius: 17px 17px 0 0;">
                <h1 style="font-size:1.5em; font-weight: 600;">
                    <?php echo $latestTransaction['branch']; ?>
                </h1>
                <label for="address">
                    <?php echo $_SESSION['address'] ?>
                </label><br>
            </div><br>
            <!-- Display entries for the latest transaction date -->
            <?php if (!empty($entries)): ?>
                <div class="align">
                    <p>Transaction#:
                        <?php echo $latestTransaction['transaction_id']; ?>
                    </p>
                    <p>Date:
                        <?php echo date('F j, Y g:i:s A', strtotime($latestTransaction['date'])); ?>
                    </p>

                    <p>Contract #:
                        <?php echo $latestTransaction['contract']; ?>
                    </p>
                    <p>Tenant :
                        <?php echo $latestTransaction['company']; ?>
                    </p>
                    <p>Stall #:
                        <?php echo $latestTransaction['stall']; ?>
                    </p>
                    <p>Paid By:
                        <?php echo $latestTransaction['paidby']; ?>
                    </p>

                    <p class="pa"><strong>PAYMENT ACKNOWLEDGEMENT</strong></p>
                </div>
                <table class="table">
                    <tr>

                        <td style="font-weight: bold;">Month</td>
                        <td style="font-weight: bold;">Particulars</td>
                        <td style="font-weight: bold;">Amount</td>
                        <td style="font-weight: bold;">MOP</td>

                    </tr>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td>
                                <?php echo $entry['displayedMonth']; ?>
                            </td>
                            <td>
                                <?php echo $entry['displayedCharges'];
                                // Check if "OTHERS" was selected and if otherOption is set
                                if ($entry['displayedCharges'] === 'OTHERS' && isset($entry['otherOption'])) {
                                    echo ' ' . $entry['otherOption'];
                                }
                                ?>
                            </td>
                            <td>
                                <?php echo number_format((float) str_replace(',', '', $entry['displayedAmount']), 2, '.', ','); ?>
                            </td>
                            <td>
                                <?php if ($entry['payment'] !== 'Cash'): ?>
                                    <?php if (strpos($entry['payment'], 'Check') !== false): ?>
                                        <!-- Display check information if payment contains 'Check' -->
                                        <p>
                                            <?php echo wordwrap($entry['payment'], 8, "<br>", true); ?>
                                        </p>
                                    <?php elseif (strpos($entry['payment'], 'Bank') !== false): ?>
                                        <!-- Display bank information if payment contains 'Bank Transfer' -->
                                        <p>
                                            <?php echo wordwrap($entry['payment'], 8, "<br>", true); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo $entry['payment']; ?><br>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td></td>
                        <td style="text-align:right;">Total:</td>
                        <td>
                            <?php echo number_format($total, 2, '.', ','); ?>
                        </td>

                    </tr>
                </table>
            <?php else: ?>
                <p>No transactions found for the latest transaction date.</p>
            <?php endif; ?>

            <p style="text-align:center;" class="pe">Payment Received By:
                <?php echo $_SESSION['fullname']; ?>
            </p>
            <p style="text-align:center;" class="pos">
                <?php echo $_SESSION['position'] ?>
            </p><br>
            <div class="receiptFooter" style=" border-radius: 0 0 17px 17px;">
                <p style="text-align:center;">Note: Receipts will be issued 1-2 days after receiving payment. When
                    claiming receipts, please present this receiving copy. Thank you.</p>

            </div>
    </center>
    </div>
    <script>
        function myFunction() {
            var x = document.getElementById("myTopnav");
            if (x.className === "topnav") {
                x.className += " responsive";
            } else {
                x.className = "topnav";
            }
        }

        // New JavaScript function for printing the receipt
        function printReceipt() {
            // Add your printing logic here
            // For example, you can use the window.print() function to open the print dialog
            window.print();
        }
    </script>

   
</body>

</html>
