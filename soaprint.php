
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement of Account - Print</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <style>
        body {
            font-size: 14px; /* Increased base font size for body */
        }
        #statementModal {
            font-size: 14px; /* Increased display font size to 14px */
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Align items to the start (top) */
            min-height: 100vh;
            /* padding-top: 20px;  Removed padding-top to occupy top */
        }
        #statementModal > div {
            width: 80%; /* Further compressed width */
            max-width: 2xl; /* More compression, Tailwind max-w-2xl is 42rem or 672px - adjust if needed */
            background-color: white;
            padding: 20px;
            border-radius: 0.5rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
             margin-top: 5; /* Reset margin-top */
            padding-top: 5; /* Reset padding-top */
        }

        #statementModal h2 {
            font-size: 1.2em;
        }
        #statementModal h3 {
            font-size: 1.2em;
        }
        #statementModal p, #statementModal table, #statementModal th, #statementModal td {
            font-size: 0.9em;
        }

      @media print {
    /* Ensure only statementModal content is visible during print */
    body * {
        visibility: hidden;
    }
    #statementModal, #statementModal * {
        visibility: visible;
    }
    /* Force top-left positioning and full page */
    #statementModal {
        position: fixed !important; /* Change to fixed positioning */
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
        padding: 0 !important; /* Remove padding here, add to inner div if needed */
        box-sizing: border-box !important;
        transform: none !important;
        background-color: white !important;
        display: block !important; /* Override flex display for print */
        justify-content: flex-start !important; /* Reset to default */
        align-items: flex-start !important; /* Reset to default */
        min-height: auto !important; /* Reset min-height for print */
        font-size: 12px; /* Keep base font size for print if needed, already set in body */
    }
     #statementModal > div {
        box-shadow: none !important; /* Remove shadow in print */
        padding: 10mm; /* Keep padding for inner div in print, adjust as needed */
        max-width: none !important; /* Full width in print */
        width: 100% !important; /* Full width in print */
        margin-top: 0 !important; /* Reset margin-top for inner div in print */
        padding-top: 0 !important; /* Reset padding-top for inner div in print */
     }
    #statementModal h2 {
        font-size: 1.4em; /* Slightly larger heading in print if desired */
    }
    #statementModal h3 {
        font-size: 1.4em;
    }
    #statementModal p, #statementModal table, #statementModal th, #statementModal td {
        font-size: 1em; /* Slightly larger text in print */
    }

    /* Style adjustments for print tables */
    table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed;
        border-collapse: collapse;
        font-size: 0.7em;
    }
    th, td {
        white-space: normal !important;
        padding: 2px;
        font-weight: bold !important;
    }
    @page {
        margin: 0mm !important; /* Ensure no page margins in print settings */
        padding: 0mm !important; /* Ensure no page padding in print settings */
        size: letter;
    }
    .page-break {
        page-break-after: always;
    }
    .no-print {
        display: none !important;
    }
}
        /* Make table font bold for screen */
        th, td {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div id="statementModal">
        <div>
            <div class="mt-3 text-center">
                <div class="flex items-center justify-between mb-2 w-full">
                    <img src="../images/lc.png" alt="Company Logo" class="h-24 w-24 mr-4">
                    <h2 class="text-xl font-bold absolute left-1/2 transform -translate-x-1/2">LCLopez Resources INC.</h2>
                </div>

                <h3 class="text-lg font-semibold">Statement of Account</h3>

                <div class="mt-2">
                    <p><strong>Branch:</strong> <span id="modalBranchName"></span></p>
                    <p><strong>Tenant:</strong> <span id="modalTenantName"></span></p>
                    <p><strong>Date Range:</strong> <span id="modalDateRange"></span></p>
                </div>

                <div class="mt-2 flex justify-between">
                    <div class="text-left">
                        <p><strong>Previous Rent Balance:</strong> <span id="modalPreviousRentBalance" style="font-weight: bold;"></span></p>
                        <p><strong>Previous Arrears:</strong> <span id="modalPreviousBalanceArrears" style="font-weight: bold;"></span></p>
                    </div>
                    <div class="text-right">
                        <p><strong>Current Rent Balance:</strong> <span id="modalNewRentBalance" style="font-weight: bold;"></span></p>
                        <p><strong>Current Arrears:</strong> <span id="modalNewBalanceArrears" style="font-weight: bold;"></span></p>
                    </div>
                </div>

                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-300">
                        <thead>
                            <tr>
                                <th class="py-1 px-2 border-b">Transaction Number</th>
                                <th class="py-1 px-2 border-b">Date</th>
                                <th class="py-1 px-2 border-b">SCode</th>
                                <th class="py-1 px-2 border-b">Paid Rent</th>
                                <th class="py-1 px-2 border-b">Paid Balance</th>
                                <th class="py-1 px-2 border-b">Charges</th>
                            </tr>
                        </thead>
                        <tbody id="modalTransactions">
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 flex" style="display: flex; justify-content: space-between;">
                    <div style="text-align: left; width: 33%;">
                        <p><strong style="font-weight: bold;">Total Paid Rent:</strong> <span id="modalTotalPaidRent" style="display: block;"></span></p>
                    </div>
                    <div style="text-align: center; width: 33%;">
                        <p><strong style="font-weight: bold;">Total Paid Balance:</strong> <span id="modalTotalPaidBalance" style="display: block;"></span></p>
                    </div>
                    <div style="text-align: right; width: 33%;">
                        <p><strong style="font-weight: bold;">Total Charges:</strong> <span id="modalTotalCharges" style="display: block;"></span></p>
                    </div>
                </div>

                <div class="mt-4 no-print flex justify-center">  <button onclick="printStatement()" class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600" style="font-size: 0.9em;">Print</button>
                    <button onclick="location.href='soa.php'" class="bg-gray-500 text-white px-3 py-1 rounded-md hover:bg-gray-600 ml-2" style="font-size: 0.9em;">Back to Form</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function printStatement() {
            window.print();
        }
    </script>

    <?php
    include 'config.php';

    $selectedBranch = isset($_POST['branchFilter']) ? $_POST['branchFilter'] : "";
    $selectedTenant = isset($_POST['tenantFilter']) ? $_POST['tenantFilter'] : "";
    $fromDate = isset($_POST['fromDateFilter']) ? $_POST['fromDateFilter'] : "";
    $toDate = isset($_POST['toDateFilter']) ? $_POST['toDateFilter'] : "";

    $table = '';
    if ($selectedBranch == 'Sanko Market') {
        $table = 'collected';
    } elseif ($selectedBranch == 'Nova Market') {
        $table = 'collectednova';
    } elseif ($selectedBranch == 'APM') {
        $table = 'collectedapm';
    } else {
        $table = 'collected';
    }

    $dateCondition = '';
    if (!empty($fromDate) && !empty($toDate)) {
        $dateCondition = "AND collected_date BETWEEN '$fromDate' AND '$toDate'";
    }

    $sql = "
        SELECT
            transaction_number,
            collected_date,
            spacecode,
            rentbal,
            runningbal,
            paidrent,
            paidbal,
            newrentbalance,
            newbalance,
            charges
        FROM $table
        WHERE tenantname = '$selectedTenant'
        $dateCondition
        ORDER BY collected_date
    ";

    $result = $conn->query($sql);

    $transactions = "";
    $totalCharges = 0;
    $totalPaidRent = 0;
    $totalPaidBalance = 0;
    $previousRentBalance = 0;
    $previousBalanceArrears = 0;
    $newRentBalance = 0;
    $newBalanceArrears = 0;

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $chargesArray = explode(',', $row['charges']);
            $chargesTotal = 0;
            foreach ($chargesArray as $charge) {
                if (preg_match('/\d+/', $charge, $matches)) {
                    $chargesTotal += (float)$matches[0];
                }
            }
            $totalCharges += $chargesTotal;
            $totalPaidRent += $row["paidrent"];
            $totalPaidBalance += $row["paidbal"];
            $previousRentBalance = $row["rentbal"];
            $previousBalanceArrears = $row["runningbal"];
            $newRentBalance = $row["newrentbalance"];
            $newBalanceArrears = $row["newbalance"];

            $transactions .= "
                <tr>
                    <td class='py-1 px-2 border-b'>" . $row["transaction_number"] . "</td>
                    <td class='py-1 px-2 border-b'>" . date("m/d/y", strtotime($row["collected_date"])) . "</td>
                    <td class='py-1 px-2 border-b'>" . $row["spacecode"] . "</td>
                    <td class='py-1 px-2 border-b'>" . number_format($row["paidrent"], 2) . "</td>
                    <td class='py-1 px-2 border-b'>" . number_format($row["paidbal"], 2) . "</td>
                    <td class='py-1 px-2 border-b'>" . $row["charges"] . "</td>
                </tr>
            ";
        }
    }
    $conn->close();
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($result->num_rows > 0): ?>
                document.getElementById('modalBranchName').textContent = '<?php echo $selectedBranch; ?>';
                document.getElementById('modalTenantName').textContent = '<?php echo $selectedTenant; ?>';
                document.getElementById('modalDateRange').textContent = '<?php echo $fromDate . " to " . $toDate; ?>';
                document.getElementById('modalTransactions').innerHTML = `<?php echo $transactions; ?>`;
                document.getElementById('modalTotalCharges').textContent = '<?php echo number_format($totalCharges, 2); ?>';
                document.getElementById('modalTotalPaidRent').textContent = '<?php echo number_format($totalPaidRent, 2); ?>';
                document.getElementById('modalTotalPaidBalance').textContent = '<?php echo number_format($totalPaidBalance, 2); ?>';
                document.getElementById('modalPreviousRentBalance').textContent = '<?php echo number_format($previousRentBalance, 2); ?>';
                document.getElementById('modalPreviousBalanceArrears').textContent = '<?php echo number_format($previousBalanceArrears, 2); ?>';
                document.getElementById('modalNewRentBalance').textContent = '<?php echo number_format($newRentBalance, 2); ?>';
                document.getElementById('modalNewBalanceArrears').textContent = '<?php echo number_format($newBalanceArrears, 2); ?>';
            <?php endif; ?>
        });
    </script>
</body>
</html>