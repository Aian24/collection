<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>Statement of Account - Print</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        #statementModal {
            font-size: 12px;
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }
        
        #statementModal > div {
            width: 100%;
            background-color: white;
            padding: 5px;
        }
        
        #statementModal h2 {
            font-size: 1.2em;
            color: #2c3e50;
        }
        
        #statementModal h3 {
            font-size: 1.1em;
            color: #2c3e50;
        }
        
        #statementModal p, #statementModal table, #statementModal th, #statementModal td {
            font-size: 0.9em;
        }
        
        /* Header styling */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
        }
        
        .logo-container {
            flex: 0 0 auto;
        }
        
        .title-container {
            flex: 1;
            text-align: center;
        }
        
        .statement-title {
            font-size: 1.4em;
            font-weight: bold;
            margin: 0 auto;
            color: #2c3e50;
            letter-spacing: 1px;
        }
        
        /* Client info section */
        .client-info-container {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        .client-info-left {
            text-align: left;
            flex: 1;
        }
        
        .client-info-right {
            text-align: right;
            flex: 1;
        }
        
        .client-info-item {
            margin: 3px 0;
            font-weight: 500;
        }
        
        /* Separator line */
        .separator-line {
            border-top: 1px solid #ddd;
            margin: 10px 0;
            width: 100%;
        }
        
        /* Balances summary */
        .balances-summary {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px;
            background-color: #f1f5f9;
            border-radius: 4px;
        }
        
        /* Half-half layout */
        .container-half-half {
            display: flex;
            width: 100%;
            margin-top: 5px;
            gap: 15px;
        }
        
        .half-section {
            width: 50%;
            padding: 0 5px;
            box-sizing: border-box;
        }
        
        .table-section {
            width: 60%;
        }
        
        .balances-section {
            width: 40%;
        }
        
        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        th, td {
            padding: 4px;
            border: 1px solid #e0e0e0;
            text-align: left;
        }
        
        th {
            background-color: #2c3e50;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 0.5px;
            border: 1px solid #2c3e50;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        /* Input fields styling */
        .balance-input {
            border: none;
            border-bottom: 1px solid #ddd;
            padding: 3px 0;
            margin: 2px 0;
            background-color: transparent;
            width: 100%;
        }
        
        .balance-input:focus {
            outline: none;
            border-bottom: 2px solid #2c3e50;
        }
        
        /* Custom fields container */
        .custom-fields {
            margin-top: 10px;
        }
        
        .custom-field {
            margin-bottom: 5px;
        }
        
        .add-field-btn {
            background-color: #f0f0f0;
            border: 1px dashed #999;
            padding: 5px;
            text-align: center;
            cursor: pointer;
            margin-top: 10px;
            border-radius: 4px;
            font-size: 0.8em;
        }
        
        .add-field-btn:hover {
            background-color: #e0e0e0;
        }
        
        /* Print-specific styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
                font-size: 10px;
            }
            
            #statementModal {
                padding: 0;
                margin: 0;
                width: 100%;
                height: 100%;
            }
            
            #statementModal > div {
                padding: 5mm;
                width: 100%;
                height: 100%;
                box-shadow: none;
            }
            
            table {
                font-size: 8px;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            th, td {
                padding: 3px;
                border: 1px solid #ddd;
            }
            
            th {
                background-color: #2c3e50 !important;
                color: white !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border: 1px solid #2c3e50 !important;
            }
            
            tr:nth-child(even) {
                background-color: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .no-print {
                display: none !important;
            }
            
            .add-field-btn {
                display: none;
            }
            
            .separator-line {
                border-top: 1px solid #000;
            }
            
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
        }
        
        /* Modified style for Rent Balances section */
        .rent-balances-section {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
        
        .rent-balances-section h3 {
            font-weight: bold;
            text-align: center;
            margin: 0 0 10px 0;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .rent-balances-field {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .rent-balances-field input.label-input {
            width: 120px;
            margin-right: 10px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .rent-balances-field input.value-input {
            flex-grow: 1;
        }
        
        .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: red;
            margin-left: 5px;
            width: 20px;
        }
        
        #editCustomFieldContainer {
            display: none;
        }
        
        /* Totals row styling */
        tfoot tr {
            background-color: #e9ecef !important;
            font-weight: bold;
        }
        
        /* Button styling */
        .action-btn {
            background-color: #2c3e50;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #1a252f;
        }
        
        .back-btn {
            background-color: #6c757d;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div id="statementModal">
        <div>
            <div style="text-align: center; margin-bottom: 5px;">
                <div class="header-container">
                    <div class="logo-container">
                        <img src="../images/lc.png" alt="Company Logo" style="height: 80px; width: auto;">
                    </div>
                    <div class="title-container">
                        <h2 class="statement-title">STATEMENT OF ACCOUNT</h2>
                    </div>
                    <div class="logo-container" style="visibility: hidden;">
                        <img src="../images/lc.png" alt="Company Logo" style="height: 60px; width: auto;">
                    </div>
                </div>

                <!-- Client information in two columns -->
                <div class="client-info-container">
                    <div class="client-info-left">
                        <p class="client-info-item"><strong>Branch:</strong> <span id="modalBranchName"></span></p>
                        <p class="client-info-item"><strong>Tenant:</strong> <span id="modalTenantName"></span></p>
                    </div>
                    <div class="client-info-right">
                        <p class="client-info-item"><strong>Spacecode:</strong> <span id="modalSpacecode"></span></p>
                        <p class="client-info-item"><strong>Date Range:</strong> <span id="modalDateRange"></span></p>
                    </div>
                </div>

                <!-- Separator line -->
                <div class="separator-line"></div>

                <!-- Balances summary -->
                <div class="balances-summary">
                    <div style="text-align: left;">
                        <p style="margin: 2px 0;"><strong>Previous Rent Balance:</strong> <span id="modalPreviousRentBalance"></span></p>
                        <p style="margin: 2px 0;"><strong>Previous Arrears:</strong> <span id="modalPreviousBalanceArrears"></span></p>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 2px 0;"><strong>Current Rent Balance:</strong> <span id="modalNewRentBalance"></span></p>
                        <p style="margin: 2px 0;"><strong>Current Arrears:</strong> <span id="modalNewBalanceArrears"></span></p>
                    </div>
                </div>

                <!-- Main content -->
                <div class="container-half-half">
                    <div class="half-section table-section">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction#</th>
                                    <th>Date</th>
                                    <th>Paid Rent</th>
                                    <th>Paid Balance</th>
                                    <th>Charges</th>
                                </tr>
                            </thead>
                            <tbody id="modalTransactions">
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" style="text-align: right;"><strong>Totals:</strong></td>
                                    <td><strong><span id="modalTotalPaidRent"></span></strong></td>
                                    <td><strong><span id="modalTotalPaidBalance"></span></strong></td>
                                    <td><strong><span id="modalTotalCharges"></span></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="half-section balances-section">
                        <div class="rent-balances-section">
                           
                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Rent Balance:" readonly>
                                <input type="text" class="balance-input value-input" id="rentBalance" readonly>
                            </div>
                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Arrears:" readonly>
                                <input type="text" class="balance-input value-input" id="arrearsBalance" readonly>
                            </div>
                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Electricity:" readonly>
                                <input type="text" class="balance-input value-input" id="electricityBalance">
                            </div>
                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Water:" readonly>
                                <input type="text" class="balance-input value-input" id="waterBalance">
                            </div>
                            <div class="rent-balances-field" id="editCustomFieldContainer">
                                <input type="text" class="balance-input label-input" id="editCustomFieldLabel" value="Label:">
                                <input type="text" class="balance-input value-input" id="editCustomField" value="0">
                                <button onclick="removeCustomField(this)" class="delete-btn no-print">×</button>
                            </div>

                            <div id="customFieldsContainer"></div>

                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Total:" readonly>
                                <input type="text" class="balance-input value-input" id="totalBalance" readonly>
                            </div>

                            <div class="add-field-btn no-print" onclick="addCustomField()">
                                + Add Custom Field
                            </div>
                        </div>
                    </div>
                </div>

                <div class="no-print" style="text-align: center; margin-top: 15px;">
                    <button onclick="printStatement()" class="action-btn">Print Statement</button>
                    <button onclick="location.href='soaapm.php'" class="action-btn back-btn" style="margin-left: 10px;">Back to Form</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let editCustomFieldShown = false;

        function formatNumber(number) {
            // Formats a number with commas and two decimal places
            return parseFloat(number).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function parseNumber(formattedString) {
            // Removes commas and parses a string into a float
            return parseFloat(formattedString.replace(/,/g, ''));
        }

        function printStatement() {
            // Hide input field borders for printing
            const inputs = document.querySelectorAll('.balance-input');
            inputs.forEach(input => {
                input.style.borderBottom = '1px solid #000';
            });

            window.print();
        }

        function removeCustomField(button) {
            button.parentNode.remove();
            calculateTotal(); // Recalculate total after removing
        }

        function addCustomField() {
            const container = document.getElementById('customFieldsContainer');
            const totalBalanceField = document.querySelector('.rent-balances-field:has(#totalBalance)');

            if (!editCustomFieldShown) {
                // Show and make the edit custom field editable
                document.getElementById('editCustomFieldContainer').style.display = 'flex';
                document.getElementById('editCustomFieldLabel').removeAttribute('readonly');
                const editCustomFieldValueInput = document.getElementById('editCustomField');
                editCustomFieldValueInput.removeAttribute('readonly');
                editCustomFieldShown = true;

                // Add input event listener to the edit custom field
                editCustomFieldValueInput.addEventListener('input', calculateTotal);
                // Add blur event listener for formatting
                editCustomFieldValueInput.addEventListener('blur', function() {
                    this.value = formatNumber(parseNumber(this.value) || 0);
                });
                // Format initial value
                editCustomFieldValueInput.value = formatNumber(parseNumber(editCustomFieldValueInput.value) || 0);

            } else {
                // Add a new custom field before the Total Balance
                const fieldId = 'customField_' + Date.now();
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'rent-balances-field custom-field';
                fieldDiv.innerHTML = `
                    <input type="text" class="balance-input label-input" placeholder="Label">
                    <input type="text" class="balance-input value-input" value="0.00">
                    <button onclick="removeCustomField(this)" class="delete-btn no-print">×</button>
                `;

                // Insert before the Total Balance row
                totalBalanceField.parentNode.insertBefore(fieldDiv, totalBalanceField);

                // Add event listeners to the new field's value input
                const newValueInput = fieldDiv.querySelector('.value-input');
                newValueInput.addEventListener('input', calculateTotal);
                // Add blur event listener for formatting
                newValueInput.addEventListener('blur', function() {
                    this.value = formatNumber(parseNumber(this.value) || 0);
                });

                // Format initial value of the new field
                newValueInput.value = formatNumber(parseNumber(newValueInput.value) || 0);
            }

            // Recalculate total after adding a field
            calculateTotal();
        }

        function updateRentBalances() {
            // Get current values from header spans (need to remove commas for parsing)
            const rentBalance = parseNumber(document.getElementById('modalNewRentBalance').textContent) || 0;
            const arrearsBalance = parseNumber(document.getElementById('modalNewBalanceArrears').textContent) || 0;

            // Set the values in the form, formatted with commas
            document.getElementById('rentBalance').value = formatNumber(rentBalance);
            document.getElementById('arrearsBalance').value = formatNumber(arrearsBalance);

            // Set current month as default
            const now = new Date();
            const month = now.toLocaleString('default', { month: 'long' });
            const year = now.getFullYear();

            // Set initial values for electricity and water and format them
            const electricityInput = document.getElementById('electricityBalance');
            const waterInput = document.getElementById('waterBalance');
            const editCustomFieldInput = document.getElementById('editCustomField');

            electricityInput.value = formatNumber(parseNumber(electricityInput.value) || 0);
            waterInput.value = formatNumber(parseNumber(waterInput.value) || 0);
            // Initial formatting for editCustomField if it's shown
            if(editCustomFieldShown) {
                editCustomFieldInput.value = formatNumber(parseNumber(editCustomFieldInput.value) || 0);
            }

            // Add blur event listeners for formatting
            electricityInput.addEventListener('blur', function() {
                this.value = formatNumber(parseNumber(this.value) || 0);
            });
            waterInput.addEventListener('blur', function() {
                this.value = formatNumber(parseNumber(this.value) || 0);
            });

            // Calculate initial total
            calculateTotal();
        }

        // Add input event listeners to calculate total when inputs change
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('electricityBalance').addEventListener('input', calculateTotal);
            document.getElementById('waterBalance').addEventListener('input', calculateTotal);
            // The editCustomField listener is added in addCustomField when it's shown

            // Initialize the edit custom field as readonly initially
            document.getElementById('editCustomFieldLabel').setAttribute('readonly', 'readonly');
            document.getElementById('editCustomField').setAttribute('readonly', 'readonly');

            // Call updateRentBalances to populate the form and calculate initial total
            updateRentBalances();
        });

        function calculateTotal() {
            // Parse all numeric input values (remove commas before parsing)
            const rentBalance = parseNumber(document.getElementById('rentBalance').value) || 0;
            const arrearsBalance = parseNumber(document.getElementById('arrearsBalance').value) || 0;
            const electricity = parseNumber(document.getElementById('electricityBalance').value) || 0;
            const water = parseNumber(document.getElementById('waterBalance').value) || 0;
            const editCustomFieldValue = parseNumber(document.getElementById('editCustomField').value) || 0;

            // Calculate sum of all custom fields
            let customFieldsSum = 0;
            document.querySelectorAll('.custom-field .value-input').forEach(input => {
                customFieldsSum += parseNumber(input.value) || 0;
            });

            const total = rentBalance + arrearsBalance + electricity + water + editCustomFieldValue + customFieldsSum;
            // Set the total, formatted with commas
            document.getElementById('totalBalance').value = formatNumber(total);
        }
    </script>

    <?php
    include '../config.php';

    $selectedBranch = isset($_POST['branchFilter']) ? $_POST['branchFilter'] : "";
    $selectedTenant = isset($_POST['tenantFilter']) ? $_POST['tenantFilter'] : "";
    $selectedSpacecode = isset($_POST['spacecodeFilter']) ? $_POST['spacecodeFilter'] : "";
    $fromDate = isset($_POST['fromDateFilter']) ? $_POST['fromDateFilter'] : "";
    $toDate = isset($_POST['toDateFilter']) ? $_POST['toDateFilter'] : "";

    function formatDateToMMDDYY($dateString) {
        if ($dateString) {
            $date = new DateTime($dateString);
            return $date->format('m/d/y');
        }
        return '';
    }

    $formattedFromDate = formatDateToMMDDYY($fromDate);
    $formattedToDate = formatDateToMMDDYY($toDate);

    $table = '';
    if ($selectedBranch == 'Sanko Market') {
        $table = 'collected';
    } elseif ($selectedBranch == 'Nova Market') {
        $table = 'collectednova';
    } elseif ($selectedBranch == 'APM') {
        $table = 'collectedapm';
    } else {
        // Default table if branch is not specified or is something else
        $table = 'collected';
    }

    $dateCondition = "AND DATE(collected_date) BETWEEN '$fromDate' AND '$toDate'";

    $spacecodeCondition = '';
    if (!empty($selectedSpacecode)) {
        $spacecodeCondition = "AND spacecode = '$selectedSpacecode'";
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
        $spacecodeCondition
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
    $newRentBalance = 0; // Will store the last new rent balance
    $newBalanceArrears = 0; // Will store the last new balance (arrears)

    // Get daily rent from tenant table
    $dailyRent = 0;
    $tenantTableName = "";
    
    if ($selectedBranch == 'Sanko Market') {
        $tenantTableName = 'sanko';
    } elseif ($selectedBranch == 'Nova Market') {
        $tenantTableName = 'nova';
    } elseif ($selectedBranch == 'APM') {
        $tenantTableName = 'apm';
    }
    
    if (!empty($tenantTableName) && !empty($selectedSpacecode)) {
        $tenantQuery = "SELECT daily FROM $tenantTableName WHERE tenantname = '$selectedTenant' AND spacecode = '$selectedSpacecode'";
        $tenantResult = $conn->query($tenantQuery);
        if ($tenantResult && $tenantResult->num_rows > 0) {
            $tenantRow = $tenantResult->fetch_assoc();
            $dailyRent = floatval($tenantRow['daily']);
        }
    }

    // Get all days in the date range
    $allDays = array();
    $recordedDays = array();
    $paidDays = array();
    
    // Convert string dates to DateTime objects
    $startDate = new DateTime($fromDate);
    $endDate = new DateTime($toDate);
    $endDate->setTime(23, 59, 59); // Include the end date
    
    // Create array with all days in the range
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $allDays[] = $currentDate->format('Y-m-d');
        $currentDate->modify('+1 day');
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $chargesValue = $row['charges'];
            $chargesTotal = 0;
            $hasAmount = false;
            // Parse charges to sum up numeric values
            if (!empty($chargesValue)) {
                $chargesArray = explode(',', $chargesValue);
                foreach ($chargesArray as $charge) {
                    // Extract number from "label:amount" format
                    if (preg_match('/:\s*([\d.]+)/', $charge, $matches)) {
                        $chargesTotal += (float)$matches[1];
                        $hasAmount = true;
                    } else if (is_numeric(trim($charge))) { // Handle cases where it might just be a number
                        $chargesTotal += (float)trim($charge);
                        $hasAmount = true;
                    }
                }
            }

            // Only add to rent totals if rent was actually paid
            if (floatval($row["paidrent"]) > 0) {
                $totalCharges += $chargesTotal;
                $totalPaidRent += $row["paidrent"];
            }
            $totalPaidBalance += $row["paidbal"];
            // Update previous balances only on the first row
            if ($transactions == "") {
                $previousRentBalance = $row["rentbal"];
                $previousBalanceArrears = $row["runningbal"];
            }
            // Always update new balances with the current row's values
            $newRentBalance = $row["newrentbalance"];
            $newBalanceArrears = $row["newbalance"];

            $phpDate = strtotime($row["collected_date"]);
            $formattedTransactionDate = date("m/d/y - D", $phpDate);
            
            $transactionDate = date("Y-m-d", $phpDate);
            $recordedDays[] = $transactionDate;
            
            // Check if this day actually has rent paid
            if (floatval($row["paidrent"]) > 0) {
                // Add this day to the paid days array only if rent was actually paid
                $paidDays[] = $transactionDate;
                
                // Display original charges string in the table
                $displayedCharges = !empty($chargesValue) ? $chargesValue : '0';

                $transactions .= "
                    <tr>
                        <td>" . $row["transaction_number"] . "</td>
                        <td>" . $formattedTransactionDate . "</td>
                        <td>" . number_format($row["paidrent"], 2) . "</td>
                        <td>" . number_format($row["paidbal"], 2) . "</td>
                        <td>" . htmlspecialchars($displayedCharges) . "</td>
                    </tr>
                ";
            } else {
                // This day has a transaction but no rent paid - mark as unpaid
                $displayedCharges = '0';
                
                $transactions .= "
                    <tr style='background-color: #ffeeee;'>
                        <td style='color: red;'>" . $row["transaction_number"] . "</td>
                        <td style='color: red;'>" . $formattedTransactionDate . " (Unpaid)</td>
                        <td style='color: red;'>" . number_format($dailyRent, 2) . " (Daily Rent)</td>
                        <td style='color: red;'>" . number_format($row["paidbal"], 2) . "</td>
                        <td style='color: red;'>" . htmlspecialchars($displayedCharges) . "</td>
                    </tr>
                ";
            }
        }
    }
    
    // Calculate unpaid days - include both days with no transactions AND days with transactions but no rent paid
    // We use recordedDays here to only get days that are completely missing from the database
    $unpaidDays = array_diff($allDays, $recordedDays);
    
    // Count days that have transactions but no rent paid (we need to track this separately)
    $daysWithNoRentPaid = 0;
    if ($result->num_rows > 0) {
        // Reset result pointer to beginning
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            if (floatval($row["paidrent"]) == 0) {
                $daysWithNoRentPaid++;
            }
        }
    }
    
    $totalUnpaidDays = count($unpaidDays) + $daysWithNoRentPaid;
    $unpaidAmount = $dailyRent * $totalUnpaidDays;
    
    // Calculate net rent balance: unpaid amount minus total paid balance
    $netRentBalance = $unpaidAmount - $totalPaidBalance;
    // Ensure balance is not negative
    $netRentBalance = max(0, $netRentBalance);
    
    // Set the corrected net rent balance
    $rentBalanceForJS = $netRentBalance;
    
    // Create a string of unpaid days for display in remarks
    $unpaidDaysFormatted = array();
    foreach ($unpaidDays as $unpaidDay) {
        $dateObj = new DateTime($unpaidDay);
        $unpaidDaysFormatted[] = $dateObj->format('m/d/y - D');
    }
    $unpaidDaysString = implode(", ", $unpaidDaysFormatted);
    
    // Add unpaid days to the transactions table with red marker
    // Sort unpaid days by date first
    sort($unpaidDays);
    foreach ($unpaidDays as $unpaidDay) {
        $dateObj = new DateTime($unpaidDay);
        $formattedUnpaidDate = $dateObj->format('m/d/y - D');
        
        $transactions .= "
            <tr style='background-color: #ffeeee;'>
                <td style='color: red;'>-</td>
                <td style='color: red;'>" . $formattedUnpaidDate . " (Unpaid)</td>
                <td style='color: red;'>" . number_format($dailyRent, 2) . " (Daily Rent)</td>
                <td style='color: red;'>0.00</td>
                <td style='color: red;'>0.00</td>
            </tr>
        ";
    }
    
    $conn->close();
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($result->num_rows > 0 || !empty($selectedTenant)): ?>
                document.getElementById('modalBranchName').textContent = '<?php echo htmlspecialchars($selectedBranch); ?>';
                document.getElementById('modalTenantName').textContent = '<?php echo htmlspecialchars($selectedTenant); ?>';
                document.getElementById('modalSpacecode').textContent = '<?php echo htmlspecialchars($selectedSpacecode); ?>';
                document.getElementById('modalDateRange').textContent = '<?php echo htmlspecialchars($formattedFromDate . " to " . $formattedToDate); ?>';
                document.getElementById('modalTransactions').innerHTML = `<?php echo $transactions; ?>`;
                // Format the totals from PHP with commas
                document.getElementById('modalTotalCharges').textContent = '<?php echo number_format($totalCharges, 2); ?>';
                document.getElementById('modalTotalPaidRent').textContent = '<?php echo number_format($totalPaidRent, 2); ?>';
                document.getElementById('modalTotalPaidBalance').textContent = '<?php echo number_format($totalPaidBalance, 2); ?>';
                // Format the previous/new balances from PHP with commas
                document.getElementById('modalPreviousRentBalance').textContent = '<?php echo number_format($previousRentBalance, 2); ?>';
                document.getElementById('modalPreviousBalanceArrears').textContent = '<?php echo number_format($previousBalanceArrears, 2); ?>';
                document.getElementById('modalNewRentBalance').textContent = '<?php echo number_format($newRentBalance, 2); ?>';
                document.getElementById('modalNewBalanceArrears').textContent = '<?php echo number_format($newBalanceArrears, 2); ?>';

                // Update rent balances form with values formatted with commas
                // Set the calculated unpaid amount to Rent Balance
                document.getElementById('rentBalance').value = formatNumber(<?php echo $rentBalanceForJS; ?>);
                
                // Add calculation breakdown to rentBalanceRemarks
                <?php if ($totalUnpaidDays > 0): ?>
                document.getElementById('rentBalanceRemarks').value = 'Calculation: <?php echo number_format($dailyRent, 2); ?> x <?php echo $totalUnpaidDays; ?> day(s) = <?php echo number_format($rentBalanceForJS, 2); ?>';
                <?php endif; ?>
                
                // This function is called here after the spans are populated
                updateRentBalances();
            <?php else: ?>
                // Handle case where no data is found for the selected filters
                document.getElementById('modalBranchName').textContent = '<?php echo htmlspecialchars($selectedBranch); ?>';
                document.getElementById('modalTenantName').textContent = '<?php echo htmlspecialchars($selectedTenant); ?>';
                document.getElementById('modalSpacecode').textContent = '<?php echo htmlspecialchars($selectedSpacecode); ?>';
                document.getElementById('modalDateRange').textContent = '<?php echo htmlspecialchars($formattedFromDate . " to " . $formattedToDate); ?>';
                document.getElementById('modalTransactions').innerHTML = `<?php echo $transactions; ?>`;
                document.getElementById('modalTotalCharges').textContent = '<?php echo number_format(0, 2); ?>';
                document.getElementById('modalTotalPaidRent').textContent = '0.00';
                document.getElementById('modalTotalPaidBalance').textContent = '0.00';
                document.getElementById('modalPreviousRentBalance').textContent = '0.00';
                document.getElementById('modalPreviousBalanceArrears').textContent = '0.00';
                document.getElementById('modalNewRentBalance').textContent = '0.00';
                document.getElementById('modalNewBalanceArrears').textContent = '0.00';

                // Initialize the rent balances form with unpaid days
                document.getElementById('rentBalance').value = formatNumber(<?php echo $rentBalanceForJS ?? 0; ?>);
                
                // Add calculation breakdown to rentBalanceRemarks
                <?php if ($totalUnpaidDays > 0): ?>
                document.getElementById('rentBalanceRemarks').value = 'Calculation: <?php echo number_format($dailyRent, 2); ?> x <?php echo $totalUnpaidDays; ?> day(s) = <?php echo number_format($rentBalanceForJS, 2); ?>';
                <?php endif; ?>
                
                document.getElementById('arrearsBalance').value = formatNumber(0);
                document.getElementById('electricityBalance').value = formatNumber(0);
                document.getElementById('waterBalance').value = formatNumber(0);
                document.getElementById('totalBalance').value = formatNumber(<?php echo $rentBalanceForJS ?? 0; ?>);
                
                // Initialize remarks fields
                document.getElementById('arrearsBalanceRemarks').value = '';
                document.getElementById('electricityBalanceRemarks').value = '';
                document.getElementById('waterBalanceRemarks').value = '';
            <?php endif; ?>
        });
    </script>
</body>
</html>