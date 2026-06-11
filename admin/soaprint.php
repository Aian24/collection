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
            align-items: flex-start;
        }
        
        .half-section {
            width: 50%;
            padding: 0;
            box-sizing: border-box;
            margin: 0;
        }
        
        .table-section {
            width: 70%;
        }
        
        .balances-section {
            width: 30%;
            margin-top: 5px;
        }
        
        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0 0 0;
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
            
            .rent-balances-remarks {
                margin-top: -5px;
                margin-bottom: 8px;
            }
            
            .rent-balances-remarks input.remarks-input {
                font-style: italic;
                color: #333 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .signature-section input {
                border: none !important;
            }
            
            .signature-block div {
                border-top: 1px solid #000 !important;
            }
            
            .client-info-item {
                margin: 0 !important;
                line-height: 1.2 !important;
            }
            
            .client-info-container {
                padding: 5px !important;
            }
            
            .signature-section {
                margin-top: 60px !important;
            }
            
            .signature-block p {
                margin-top: 5px !important;
            }
            
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
        }
        
        /* Modified style for Rent Balances section */
        .rent-balances-section {
            border: 1px solid #e0e0e0;
            padding: 10px;
            border-radius: 0;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin: 0;
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
        
        .rent-balances-remarks {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            margin-left: 0px;
            margin-top: -3px;
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
        
        .rent-balances-remarks input.remarks-input {
            flex-grow: 1;
            font-style: italic;
            color: #555;
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
                        <h2 class="statement-title">MONTHLY RECORD</h2>
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
                        <p class="client-info-item"><strong>Date:</strong> <span id="modalDateRange"></span></p>
                    </div>
                </div>

                <!-- Separator line -->
                <div class="separator-line"></div>

                <!-- Main content -->
                <div class="container-half-half">
                    <div class="half-section table-section">
                        <table>
                            <thead>
                                <tr>
                                    <th>Trans#</th>
                                    <th>Date</th>
                                    <th>Paid Rent</th>
                                    <th>Paid Bal</th>
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
                            <div class="rent-balances-remarks">
                                <input type="text" class="balance-input remarks-input" placeholder="Remarks" id="rentBalanceRemarks">
                            </div>
                            
                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Arrears:" readonly>
                                <input type="text" class="balance-input value-input" id="arrearsBalance">
                            </div>
                            <div class="rent-balances-remarks">
                                <input type="text" class="balance-input remarks-input" placeholder="Remarks" id="arrearsBalanceRemarks">
                            </div>
                            
                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Electricity:" readonly>
                                <input type="text" class="balance-input value-input" id="electricityBalance">
                            </div>
                            <div class="rent-balances-remarks">
                                <input type="text" class="balance-input remarks-input" placeholder="Remarks" id="electricityBalanceRemarks">
                            </div>
                            
                            <div class="rent-balances-field">
                                <input type="text" class="balance-input label-input" value="Water:" readonly>
                                <input type="text" class="balance-input value-input" id="waterBalance">
                            </div>
                            <div class="rent-balances-remarks">
                                <input type="text" class="balance-input remarks-input" placeholder="Remarks" id="waterBalanceRemarks">
                            </div>
                            
                            <div class="rent-balances-field" id="editCustomFieldContainer">
                                <input type="text" class="balance-input label-input" id="editCustomFieldLabel" value="Label:">
                                <input type="text" class="balance-input value-input" id="editCustomField" value="0">
                            </div>
                            <div class="rent-balances-remarks" id="editCustomFieldRemarksContainer">
                                <input type="text" class="balance-input remarks-input" placeholder="Remarks" id="editCustomFieldRemarks">
                                <button onclick="removeCustomFieldGroup(this)" class="delete-btn no-print">×</button>
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

                <!-- Signature section -->
                <div class="signature-section" style="display: flex; justify-content: space-around; margin: 100px 0 20px 0;">
                    <div class="signature-block" style="text-align: center; width: 40%;">
                        <div style="position: relative; margin-bottom: 5px;">
                            <div style="border-top: 1px solid #000; width: 100%; margin: 0 auto;"></div>
                            <input type="text" class="name-input" id="issuerName" placeholder="Enter Your Name" style="width: 100%; border: none; text-align: center; position: absolute; bottom: 0; left: 0; transform: translateY(-50%); background: transparent;">
                        </div>
                        <p style="margin-top: 5px; font-weight: bold;">Issued By</p>
                    </div>
                    <div class="signature-block" style="text-align: center; width: 40%;">
                        <div style="position: relative; margin-bottom: 5px;">
                            <div style="border-top: 1px solid #000; width: 100%; margin: 0 auto;"></div>
                            <input type="text" class="name-input" id="receiverName" placeholder="Received By" style="width: 100%; border: none; text-align: center; position: absolute; bottom: 0; left: 0; transform: translateY(-50%); background: transparent;">
                        </div>
                        <p style="margin-top: 5px; font-weight: bold;">Received By</p>
                    </div>
                </div>

                <div class="no-print" style="text-align: center; margin-top: 15px;">
                    <button onclick="printStatement()" class="action-btn">Print Statement</button>
                    <button onclick="location.href='soa.php'" class="action-btn back-btn" style="margin-left: 10px;">Back to Form</button>
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

            // Handle signature input fields - make them blank if empty
            const issuerName = document.getElementById('issuerName');
            const receiverName = document.getElementById('receiverName');
            
            // Store original placeholder values
            const issuerPlaceholder = issuerName.placeholder;
            const receiverPlaceholder = receiverName.placeholder;
            
            // If empty, remove placeholder text for printing
            if (!issuerName.value.trim()) {
                issuerName.placeholder = '';
            }
            
            if (!receiverName.value.trim()) {
                receiverName.placeholder = '';
            }
            
            // Hide empty remarks fields
            const remarksInputs = document.querySelectorAll('.remarks-input');
            remarksInputs.forEach(input => {
                const remarksDiv = input.closest('.rent-balances-remarks');
                if (!input.value.trim() && remarksDiv) {
                    remarksDiv.classList.add('no-print');
                }
            });
            
            window.print();
            
            // Restore placeholders after printing
            setTimeout(() => {
                issuerName.placeholder = issuerPlaceholder;
                receiverName.placeholder = receiverPlaceholder;
                
                // Show remarks fields again
                remarksInputs.forEach(input => {
                    const remarksDiv = input.closest('.rent-balances-remarks');
                    if (remarksDiv) {
                        remarksDiv.classList.remove('no-print');
                    }
                });
            }, 500);
        }

        function removeCustomField(button) {
            // This function should not be used anymore, redirect to removeCustomFieldGroup
            removeCustomFieldGroup(button);
        }
        
        // Function to remove both the field and its remarks
        function removeCustomFieldGroup(button) {
            const remarksDiv = button.closest('.rent-balances-remarks');
            
            if (remarksDiv.id === 'editCustomFieldRemarksContainer') {
                // Special handling for the edit custom field
                document.getElementById('editCustomFieldContainer').style.display = 'none';
                document.getElementById('editCustomFieldRemarksContainer').style.display = 'none';
                editCustomFieldShown = false;
            } else {
                // For regular custom fields
                const fieldId = remarksDiv.dataset.fieldId;
                const fieldDiv = document.querySelector(`.rent-balances-field[data-remarks-id="${fieldId}"]`);
                
                if (fieldDiv) fieldDiv.remove();
                remarksDiv.remove();
            }
            
            calculateTotal(); // Recalculate total after removing
        }

        function addCustomField() {
            const container = document.getElementById('customFieldsContainer');
            const totalBalanceField = document.querySelector('.rent-balances-field:has(#totalBalance)');

            if (!editCustomFieldShown) {
                // Show and make the edit custom field editable
                document.getElementById('editCustomFieldContainer').style.display = 'flex';
                document.getElementById('editCustomFieldRemarksContainer').style.display = 'flex';
                document.getElementById('editCustomFieldLabel').removeAttribute('readonly');
                const editCustomFieldValueInput = document.getElementById('editCustomField');
                editCustomFieldValueInput.removeAttribute('readonly');
                document.getElementById('editCustomFieldRemarks').removeAttribute('readonly');
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
                
                // Create the field div
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'rent-balances-field custom-field';
                fieldDiv.innerHTML = `
                    <input type="text" class="balance-input label-input" placeholder="Label">
                    <input type="text" class="balance-input value-input" value="0.00">
                `;
                
                // Create the remarks div
                const remarksDiv = document.createElement('div');
                remarksDiv.className = 'rent-balances-remarks custom-field-remarks';
                remarksDiv.innerHTML = `
                    <input type="text" class="balance-input remarks-input" placeholder="Remarks">
                    <button onclick="removeCustomFieldGroup(this)" class="delete-btn no-print">×</button>
                `;
                
                // Store a reference between the two divs
                fieldDiv.dataset.remarksId = fieldId;
                remarksDiv.dataset.fieldId = fieldId;

                // Insert before the Total Balance row
                totalBalanceField.parentNode.insertBefore(remarksDiv, totalBalanceField);
                totalBalanceField.parentNode.insertBefore(fieldDiv, remarksDiv);

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
            // Set current month as default
            const now = new Date();
            const month = now.toLocaleString('default', { month: 'long' });
            const year = now.getFullYear();

            // Set initial values for all editable fields and format them
            const rentBalanceInput = document.getElementById('rentBalance');
            const arrearsBalanceInput = document.getElementById('arrearsBalance');
            const electricityInput = document.getElementById('electricityBalance');
            const waterInput = document.getElementById('waterBalance');
            const editCustomFieldInput = document.getElementById('editCustomField');
            
            // Initialize remarks fields
            const rentBalanceRemarks = document.getElementById('rentBalanceRemarks');
            const arrearsBalanceRemarks = document.getElementById('arrearsBalanceRemarks');
            const electricityBalanceRemarks = document.getElementById('electricityBalanceRemarks');
            const waterBalanceRemarks = document.getElementById('waterBalanceRemarks');
            const editCustomFieldRemarks = document.getElementById('editCustomFieldRemarks');

            // Don't reset the rentBalanceInput value if it already has a value
            if (!rentBalanceInput.value || rentBalanceInput.value === "0.00") {
                rentBalanceInput.value = formatNumber(0);
            }
            
            arrearsBalanceInput.value = formatNumber(parseNumber(arrearsBalanceInput.value) || 0);
            electricityInput.value = formatNumber(parseNumber(electricityInput.value) || 0);
            waterInput.value = formatNumber(parseNumber(waterInput.value) || 0);
            
            // Initial formatting for editCustomField if it's shown
            if(editCustomFieldShown) {
                editCustomFieldInput.value = formatNumber(parseNumber(editCustomFieldInput.value) || 0);
            }

            // Add blur event listeners for formatting (excluding rentBalance since it's readonly)
            arrearsBalanceInput.addEventListener('blur', function() {
                this.value = formatNumber(parseNumber(this.value) || 0);
            });
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
            // Remove rentBalance event listener since it's readonly
            document.getElementById('arrearsBalance').addEventListener('input', calculateTotal);
            document.getElementById('electricityBalance').addEventListener('input', calculateTotal);
            document.getElementById('waterBalance').addEventListener('input', calculateTotal);
            // The editCustomField listener is added in addCustomField when it's shown

            // Initialize the edit custom field as readonly initially
            document.getElementById('editCustomFieldLabel').setAttribute('readonly', 'readonly');
            document.getElementById('editCustomField').setAttribute('readonly', 'readonly');
            document.getElementById('editCustomFieldRemarks').setAttribute('readonly', 'readonly');
            
            // Hide the edit custom field containers initially
            document.getElementById('editCustomFieldContainer').style.display = 'none';
            document.getElementById('editCustomFieldRemarksContainer').style.display = 'none';

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

    // Extract month name from the date range
    $monthName = '';
    if ($fromDate) {
        $date = new DateTime($fromDate);
        $monthName = $date->format('F Y'); // "F" gives full month name, "Y" gives 4-digit year
    }
    
    // Prepare date range display with month name
    $dateRangeDisplay = $monthName . ' (' . $formattedFromDate . ' to ' . $formattedToDate . ')';

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
    $newRentBalance = 0;
    $newBalanceArrears = 0;

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

    // Reset totals
    $totalCharges = 0;
    $totalPaidRent = 0;
    $totalPaidBalance = 0;

    // Create an array to store all transactions
    $allTransactions = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $chargesValue = $row['charges'];
            $chargesTotal = 0;
            
            // Parse charges to sum up numeric values
            if (!empty($chargesValue)) {
                $chargesArray = explode(',', $chargesValue);
                foreach ($chargesArray as $charge) {
                    if (preg_match('/:\s*([\d.]+)/', $charge, $matches)) {
                        $chargesTotal += (float)$matches[1];
                    } else if (is_numeric(trim($charge))) {
                        $chargesTotal += (float)trim($charge);
                    }
                }
            }

            // Only add to rent totals if rent was actually paid
            if (floatval($row["paidrent"]) > 0) {
                // Add charges to total only if rent is paid
                $totalCharges += $chargesTotal;
                $totalPaidRent += $row["paidrent"];
                
                $currentChargesDisplay = !empty($chargesValue) ? $chargesValue : '0';
                $status = "Paid";
                $statusColor = "text-green-600";
            } else {
                $currentChargesDisplay = "0";
                $status = "Unpaid";
                $statusColor = "text-red-600";
            }
            $totalPaidBalance += $row["paidbal"];
            // Always update new balances with the current row's values
            $newRentBalance = $row["newrentbalance"];
            $newBalanceArrears = $row["newbalance"];

            $transactionDate = date("Y-m-d", strtotime($row["collected_date"]));
            $formattedTransactionDate = date("m/d/y - D", strtotime($row["collected_date"]));
            
            $recordedDays[] = $transactionDate;
            
            // Check if this day actually has rent paid
            if (floatval($row["paidrent"]) > 0) {
                // Add this day to the paid days array only if rent was actually paid
                $paidDays[] = $transactionDate;
                
                // Add to transactions array as paid
                $allTransactions[$transactionDate] = array(
                    'type' => 'paid',
                    'transaction_number' => $row["transaction_number"],
                    'date' => $formattedTransactionDate,
                    'paid_rent' => number_format($row["paidrent"], 2),
                    'paid_balance' => number_format($row["paidbal"], 2),
                    'charges' => $currentChargesDisplay
                );
            } else {
                // This day has a transaction but no rent paid - mark as unpaid
                $allTransactions[$transactionDate] = array(
                    'type' => 'unpaid',
                    'transaction_number' => $row["transaction_number"],
                    'date' => $formattedTransactionDate . " (Unpaid)",
                    'paid_rent' => number_format($dailyRent, 2) . " (Daily Rent)",
                    'paid_balance' => number_format($row["paidbal"], 2),
                    'charges' => $currentChargesDisplay
                );
            }
        }
    }
    
    // Calculate unpaid days - include both days with no transactions AND days with transactions but no rent paid
    // We use recordedDays here to only get days that are completely missing from the database
    $unpaidDays = array_diff($allDays, $recordedDays);
    
    // Also count days that have transactions but no rent paid
    $daysWithNoRentPaid = 0;
    foreach ($allTransactions as $date => $transaction) {
        if ($transaction['type'] == 'unpaid') {
            $daysWithNoRentPaid++;
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
    
    // Add unpaid transactions to the array (only for days with no transactions)
    foreach ($unpaidDays as $unpaidDay) {
        $dateObj = new DateTime($unpaidDay);
        $formattedUnpaidDate = $dateObj->format('m/d/y - D');
        
        $allTransactions[$unpaidDay] = array(
            'type' => 'unpaid',
            'transaction_number' => '-',
            'date' => $formattedUnpaidDate . " (Unpaid)",
            'paid_rent' => number_format($dailyRent, 2) . " (Daily Rent)",
            'paid_balance' => '0.00',
            'charges' => '0.00'
        );
    }
    
    // Sort transactions by date
    ksort($allTransactions);
    
    // Generate the HTML for the transactions table
    $transactions = "";
    foreach ($allTransactions as $date => $transaction) {
        if ($transaction['type'] == 'paid') {
            $transactions .= "
                <tr>
                    <td>" . $transaction['transaction_number'] . "</td>
                    <td>" . $transaction['date'] . "</td>
                    <td>" . $transaction['paid_rent'] . "</td>
                    <td>" . $transaction['paid_balance'] . "</td>
                    <td>" . htmlspecialchars($transaction['charges']) . "</td>
                </tr>
            ";
        } else {
            $transactions .= "
                <tr style='background-color: #ffeeee;'>
                    <td style='color: red;'>" . $transaction['transaction_number'] . "</td>
                    <td style='color: red;'>" . $transaction['date'] . "</td>
                    <td style='color: red;'>" . $transaction['paid_rent'] . "</td>
                    <td style='color: red;'>" . $transaction['paid_balance'] . "</td>
                    <td style='color: red;'>" . $transaction['charges'] . "</td>
                </tr>
            ";
        }
    }
    
    $conn->close();
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($result->num_rows > 0 || !empty($selectedTenant)): ?>
                document.getElementById('modalBranchName').textContent = '<?php echo htmlspecialchars($selectedBranch); ?>';
                document.getElementById('modalTenantName').textContent = '<?php echo htmlspecialchars($selectedTenant); ?>';
                document.getElementById('modalSpacecode').textContent = '<?php echo htmlspecialchars($selectedSpacecode); ?>';
                document.getElementById('modalDateRange').textContent = '<?php echo htmlspecialchars($dateRangeDisplay); ?>';
                document.getElementById('modalTransactions').innerHTML = `<?php echo $transactions; ?>`;
                // Format the totals from PHP with commas
                document.getElementById('modalTotalCharges').textContent = '<?php echo number_format($totalCharges, 2); ?>';
                document.getElementById('modalTotalPaidRent').textContent = '<?php echo number_format($totalPaidRent, 2); ?>';
                document.getElementById('modalTotalPaidBalance').textContent = '<?php echo number_format($totalPaidBalance, 2); ?>';
                
                // Only set these if the elements exist (they may have been removed in HTML updates)
                let prevRentBalanceEl = document.getElementById('modalPreviousRentBalance');
                let prevBalanceArrearsEl = document.getElementById('modalPreviousBalanceArrears');
                let newRentBalanceEl = document.getElementById('modalNewRentBalance');
                let newBalanceArrearsEl = document.getElementById('modalNewBalanceArrears');
                
                if (prevRentBalanceEl) prevRentBalanceEl.textContent = '<?php echo number_format($previousRentBalance, 2); ?>';
                if (prevBalanceArrearsEl) prevBalanceArrearsEl.textContent = '<?php echo number_format($previousBalanceArrears, 2); ?>';
                if (newRentBalanceEl) newRentBalanceEl.textContent = '<?php echo number_format($newRentBalance, 2); ?>';
                if (newBalanceArrearsEl) newBalanceArrearsEl.textContent = '<?php echo number_format($newBalanceArrears, 2); ?>';

                // Update rent balances form with values formatted with commas
                // Set the calculated unpaid amount to Rent Balance
                document.getElementById('rentBalance').value = formatNumber(<?php echo $rentBalanceForJS; ?>);
                
                // Add calculation breakdown to rentBalanceRemarks
                <?php if ($totalUnpaidDays > 0): ?>
                document.getElementById('rentBalanceRemarks').value = '';
                <?php endif; ?>
                
                // This function is called here after the spans are populated
                updateRentBalances();
            <?php else: ?>
                document.getElementById('modalBranchName').textContent = '<?php echo htmlspecialchars($selectedBranch); ?>';
                document.getElementById('modalTenantName').textContent = '<?php echo htmlspecialchars($selectedTenant); ?>';
                document.getElementById('modalSpacecode').textContent = '<?php echo htmlspecialchars($selectedSpacecode); ?>';
                document.getElementById('modalDateRange').textContent = '<?php echo htmlspecialchars($dateRangeDisplay); ?>';
                document.getElementById('modalTransactions').innerHTML = `<?php echo $transactions; ?>`;
                document.getElementById('modalTotalCharges').textContent = '<?php echo number_format(0, 2); ?>';
                document.getElementById('modalTotalPaidRent').textContent = '0.00';
                document.getElementById('modalTotalPaidBalance').textContent = '0.00';
                
                // Only set these if the elements exist
                let prevRentBalanceEl2 = document.getElementById('modalPreviousRentBalance');
                let prevBalanceArrearsEl2 = document.getElementById('modalPreviousBalanceArrears');
                let newRentBalanceEl2 = document.getElementById('modalNewRentBalance');
                let newBalanceArrearsEl2 = document.getElementById('modalNewBalanceArrears');
                
                if (prevRentBalanceEl2) prevRentBalanceEl2.textContent = '0.00';
                if (prevBalanceArrearsEl2) prevBalanceArrearsEl2.textContent = '0.00';
                if (newRentBalanceEl2) newRentBalanceEl2.textContent = '0.00';
                if (newBalanceArrearsEl2) newBalanceArrearsEl2.textContent = '0.00';

                // Initialize the rent balances form with unpaid days
                document.getElementById('rentBalance').value = formatNumber(<?php echo $rentBalanceForJS ?? 0; ?>);
                
                // Add calculation breakdown to rentBalanceRemarks
                <?php if ($totalUnpaidDays > 0): ?>
                document.getElementById('rentBalanceRemarks').value = '';
                <?php endif; ?>
                
                document.getElementById('arrearsBalance').value = formatNumber(0);
                document.getElementById('electricityBalance').value = formatNumber(0);
                document.getElementById('waterBalance').value = formatNumber(0);
                document.getElementById('totalBalance').value = formatNumber(<?php echo $rentBalanceForJS ?? 0; ?>);
                
                // Initialize remarks fields
                document.getElementById('arrearsBalanceRemarks').value = '';
                document.getElementById('electricityBalanceRemarks').value = '';
                document.getElementById('waterBalanceRemarks').value = '';
                
                // Hide the edit custom field containers
                document.getElementById('editCustomFieldContainer').style.display = 'none';
                document.getElementById('editCustomFieldRemarksContainer').style.display = 'none';
            <?php endif; ?>
        });
    </script>
</body>
</html>