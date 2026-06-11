<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <title>Retrieve Transactions</title>
</head>
<body>

<div class="container mt-5">
    <?php
    // Retrieve the barcode value from the GET parameter
    $barcodeValue = isset($_GET['barcode']) ? $_GET['barcode'] : '';

    // Check if the barcode value is not empty
    if (!empty($barcodeValue)) {
        // Include your database connection code (similar to your existing code)
        include 'config.php';

        // Escape the barcode value to prevent SQL injection
        $escapedBarcode = mysqli_real_escape_string($conn, $barcodeValue);

        // Define an array of all branch tables
        $allBranchTables = array(
            'imallantipolo',
            'imallcanlubang',
            'imallcamarin',
            'imallfamy',
            'cogeotownplaza',
            'antipolomarket',
            'apmcommercial',
            'citicentre'
        );

        // Initialize an empty result array
        $allResults = array();

        // Iterate through all branch tables and retrieve transactions
        foreach ($allBranchTables as $targetTable) {
            $query = "SELECT * FROM $targetTable WHERE barcode = '$escapedBarcode'";
            $result = mysqli_query($conn, $query);

            // Check if there are results
            if ($result && mysqli_num_rows($result) > 0) {
                while ($transactionDetails = mysqli_fetch_assoc($result)) {
                    // Add each transaction to the result array
                    $allResults[] = $transactionDetails;
                }
            }
        }

        // Check if any results were found
        if (!empty($allResults)) {
            // Use Bootstrap grid system to display transactions horizontally
            foreach ($allResults as $transactionDetails) {
                
                echo '<div id="responsiveContainer" class="row mb-3">';
        
                echo '<div class="col-md-4">';
                echo "<p><strong>Transaction#:</strong> {$transactionDetails['transaction_id']}</p>";
                echo "<p><strong>Date:</strong> " . date("F j Y", strtotime($transactionDetails['date'])) . "</p>";
                echo "<p><strong>Branch:</strong> {$transactionDetails['branch']}</p>";
                echo "<p><strong>Company:</strong> {$transactionDetails['company']}</p>";
                echo '</div>';

                echo '<div class="col-md-4">';
                echo "<p><strong>Contract:</strong> {$transactionDetails['contract']}</p>";
                echo "<p><strong>Stall:</strong> {$transactionDetails['stall']}</p>";
                echo "<p><strong>Month:</strong> {$transactionDetails['displayedMonth']}</p>";
                echo "<p><strong>Particulars:</strong> {$transactionDetails['displayedCharges']}</p>";
                echo '</div>';

                echo '<div class="col-md-4">';
                echo "<p><strong>Amount:</strong> {$transactionDetails['displayedAmount']}</p>";
                echo "<p><strong>Total:</strong> {$transactionDetails['total']}</p>";
                echo "<p><strong>Payment:</strong> {$transactionDetails['payment']}</p>";
                echo "<p><strong>Paid By:</strong> {$transactionDetails['paidby']}</p>";
                echo '</div>';

                echo '</div>'; // Close the row
                echo "<hr>"; // Add a separator between transactions
            }
        } else {
            echo "<p class='alert alert-warning'>Transaction not found.</p>";
        }
    } else {
        echo "<p class='alert alert-danger'>Invalid barcode value.</p>";
    }
    ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
<style>
        /* Responsive styles for different screen sizes */
            /* Larger screens */
            @media (min-width: 1024px) {
                #responsiveContainer {
                    margin-left: 220px;
                }
            }

            /* Even larger screens */
            @media (min-width: 1366px) {
                #responsiveContainer {
                     margin-left: 200px;
                }
            }

            /* Largest screens */
            @media (min-width: 1440px) {
                #responsiveContainer {
                    margin-left: 200px;
            }
        }
</style>