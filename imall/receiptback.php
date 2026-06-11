<?php
session_start();
include 'config.php';

// Check if the transaction_id parameter is set
if (isset($_GET['transaction_id'])) {
    $transaction_id = $_GET['transaction_id'];

    // Query the database to retrieve the receipt details
    $query = "SELECT * FROM ar WHERE transaction_id = $transaction_id";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Extract data from the database query result
        $company = $row['company'];
        $contract = $row['contract'];
        $stall = $row['stall'];
        $date = $row['date'];
        $checknumber = $row['checknumber'];
        $payment = $row['payment'];
        $paidby = $row['paidby'];
        $total = $row['total'];
        $displayedMonth1 = $row['displayedMonth1'];
        $displayedCharges1 = $row['displayedCharges1'];
        $displayedAmount1 = $row['displayedAmount1'];
        $displayedMonth2 = $row['displayedMonth2'];
        $displayedCharges2 = $row['displayedCharges2'];
        $displayedAmount2 = $row['displayedAmount2'];
        $displayedMonth3 = $row['displayedMonth3'];
        $displayedCharges3 = $row['displayedCharges3'];
        $displayedAmount3 = $row['displayedAmount3'];
    } else {
        echo "Transaction not found.";
        exit();
    }
} else {
    echo "Transaction ID not provided.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="receipt.css" media="print">
<link rel="stylesheet" href="style.css">
</head>

<body>
<center>
    <h1>iMall</h1>
    <h3>Canlubang</h3>

    <div class="receipt">
    <p>Tenant: <br> <span class="indented-text"> <?php echo $company; ?> </span> </p>
    <p>Contract #:   <?php echo $contract; ?> </span> </p>
    <p>Stall #:<?php echo $stall; ?> </span> </p>
    </div>
    <p>Date:<?php echo $date; ?></p>
        Payment Type:
        <span id="paymentType"><?php echo $payment; ?></span>
    </p>
    <div id="checkNumberFieldCash">
        <p>Check Number: <?php echo $checknumber; ?></p>
    </div>
    <p>Paid By: <?php echo $paidby; ?></p>
    <p>Transaction ID: <?php echo $transaction_id; ?></p>
    <script>
        // JavaScript to hide the check number field when payment type is Cash
        window.onload = function() {
            var paymentType = document.getElementById("paymentType").textContent.trim();
            var checkNumberFieldCash = document.getElementById("checkNumberFieldCash");

            if (paymentType === "Cash") {
                checkNumberFieldCash.style.display = "none";
            }
        }
    </script>
    

    <h3>PARTICULARS</h3>

    <table style="text-align:center;">
        <tr>
            <td>Month</td>
            <td>Charges</td>
            <td>Amount</td>
        </tr>
        <tr>
            <td><?php echo $displayedMonth1; ?></td>
            <td><?php echo $displayedCharges1; ?></td>
            <td><?php echo $displayedAmount1; ?></td>
        </tr>
        <tr>
            <td><?php echo $displayedMonth2; ?></td>
            <td><?php echo $displayedCharges2; ?></td>
            <td><?php echo $displayedAmount2; ?></td>
        </tr>
        <tr>
            <td><?php echo $displayedMonth3; ?></td>
            <td><?php echo $displayedCharges3; ?></td>
            <td><?php echo $displayedAmount3; ?></td>
        </tr>
    </table>

    <p>Total Amount:<?php echo $total; ?></p>

    <p style="text-align:center;">Cash Payment Received By: <?php echo $_SESSION['user_name']; ?></p>
    <p style="text-align:center;">Mall Accounting Assistant</p>
    <p style="text-align:center;">Note: Receipts will be issued 1-2 days after receiving payment. When claiming receipts, please present this receiving copy. Thank you.</p>
    <button class="print" onclick="window.print()">PRINT RECEIPT</button>
    <br><br>
    <button class="create"> <a class="create" style="text-decoration:none;" href="CreateAR.php"> CREATE NEW AR</a> </button>
    </center>
</body>
</html>
