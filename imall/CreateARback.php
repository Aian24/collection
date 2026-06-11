<?php
session_start();
include 'config.php';

date_default_timezone_set('Asia/Manila');

// Check if the user is logged in
if (!isset($_SESSION['user_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}

if (isset($_SESSION['user_name'])) {
    if (!isset($_SESSION['welcome_alert_shown'])) {
        $user_name = $_SESSION['user_name'];
        echo "<script>alert('Welcome, $user_name!');</script>";
        $_SESSION['welcome_alert_shown'] = true; // Set the flag to true
    }
}

function sanitize_input($data)
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    } else {
        return htmlspecialchars(trim($data));
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    processForm();
}

function processForm()
{
    global $conn;
    // Get and sanitize the input values
    $branch = isset($_POST["branch"]) ? $_POST["branch"] : '';
    $company = isset($_POST["company"]) ? $_POST["company"] : '';
    $contract = isset($_POST["contract"]) ? $_POST["contract"] : '';
    $stall = isset($_POST["stall"]) ? $_POST["stall"] : '';
    $date = isset($_POST["date"]) ? $_POST["date"] : '';
    $checknumber = isset($_POST["checknumber"]) ? $_POST["checknumber"] : '';
    $total = isset($_POST["total"]) ? $_POST["total"] : '';
    $payment = isset($_POST["payment"]) ? $_POST["payment"] : '';
    $paidby = isset($_POST["paidby"]) ? $_POST["paidby"] : '';
    $displayedMonth = isset($_POST["displayedMonth"]) ? $_POST["displayedMonth"] : '';
    $displayedCharges = isset($_POST["displayedCharges"]) ? $_POST["displayedCharges"] : '';
    $displayedAmount = isset($_POST["displayedAmount"]) ? $_POST["displayedAmount"] : '';
    $branch = $_SESSION['branch'];

    if ($checknumber === null) {
        $checknumber = ''; // You can change this to another default value if needed
    }

    $datetime = new DateTime("now", new DateTimeZone('Asia/Manila'));
    $date = $datetime->format('Y-m-d H:i:s');

    // Use prepared statement
    $insert = "INSERT INTO ar (branch,company, contract, stall, date, checknumber, payment, total, paidby, displayedMonth, displayedCharges, displayedAmount) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)";

    $stmt = mysqli_prepare($conn, $insert);

    if ($stmt) {
        // Bind parameters outside the loops
        mysqli_stmt_bind_param($stmt, "ssssssssssss", $sanitized_branch, $sanitized_company, $sanitized_contract, $sanitized_stall, $sanitized_date, $sanitized_checknumber, $sanitized_payment, $sanitized_total, $sanitized_paidby, $sanitized_displayedMonth, $sanitized_displayedCharges, $sanitized_displayedAmount);

        // Loop through the arrays
        if (is_array($displayedMonth)) {
            $countDisplayedMonth = count($displayedMonth);

            for ($i = 0; $i < $countDisplayedMonth; $i++) {
                // Sanitize individual elements in the arrays
                $sanitized_branch = sanitize_input($branch);
                $sanitized_company = sanitize_input($company);
                $sanitized_contract = sanitize_input($contract);
                $sanitized_stall = sanitize_input($stall);
                $sanitized_date = sanitize_input($date);
                $sanitized_checknumber = sanitize_input($checknumber);
                $sanitized_payment = sanitize_input($payment);
                $sanitized_total = sanitize_input($total);
                $sanitized_paidby = sanitize_input($paidby);
                $sanitized_displayedMonth = sanitize_input($displayedMonth[$i]);
                $sanitized_displayedCharges = sanitize_input($displayedCharges[$i]);

                // Check if displayedCharges is "OTHERS" and otherOption is set
                if ($sanitized_displayedCharges === 'OTHERS' && isset($_POST['otherOption'][$i])) {
                    // Use the sanitized otherOption value
                    $sanitized_displayedCharges = sanitize_input($_POST['otherOption'][$i]);
                }

                $sanitized_displayedAmount = sanitize_input($displayedAmount[$i]);

                // Execute the statement
                if (!mysqli_stmt_execute($stmt)) {
                    echo "Error: " . mysqli_error($conn);
                    // Handle the error here, if necessary
                }
            }
        }
        // Fetch the last inserted ID
        $transaction_id = mysqli_insert_id($conn);

        // Redirect to the preview page
        header("Location: receipt.php?transaction_id=$transaction_id");
        exit();
    } else {
        echo "Error in preparing the statement: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <title>Create Ackowledgement Report</title>
    <!-- custom css file link  -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets//Responsive/css/responsive.bootsrap.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="modaltenantlist.css">
    <link rel="stylesheet" href="userNav.css">
    <link rel="stylesheet" href="print.css" media="print">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="loader.js"></script>
       <!-- (A) AUTOCOMPLETE JS -->
       <script src="4a-autocomplete.js"></script>

</head>

<body>

    <div class="page-loader">
        <div class="spinnerContainer">
            <div class="spinner"></div>
            <div class="loader">
                <div class="words">
                    <span class="word">iMall Canlubang</span>
                    <span class="word">Rusty Lopez</span>
                    <span class="word">iMall Canlubang</span>
                    <span class="word">Rusty Lopez</span>
                    <span class="word">iMall Canlubang</span>
                    <span class="word">Rusty Lopez</span>
                </div>
            </div>
        </div>
    </div>

    <!-- nav -->
    <nav class="topnav" id="myTopnav">
        <a href="" style="font-weight: 600; font-size: 26px; padding: 5px; margin-left: 10px;">IMALL</a>
        <a href="logout.php" style="float: right;">Log Out</a>
        <a href="CreateAR.php" style="float: right;">Create AR </a>
        <a href="user_page.php" style="float: right;">Transaction Report</a>
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
    <div class="containerCon" id="containerCon">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post" autocomplete="off"
            onsubmit="showSubmitSuccess();">

            <div>
                <h3 class="pay">PAYMENT ACKNOWLEDGEMENT</h3>
            </div>
            <div class="con1">
                <table id="myForm" class="myForm">
                    <tr>
                        <td class="logoAdd">
                            <img class="logoAdd" src="logo.png">
                        </td>
                    </tr>
         
                    <div class="container">
                    <tr>
                        <td>
                            <label for="branch">BRANCH:
                                <?php echo $_SESSION['branch'] ?>
                            </label><br>

                    </tr>
                    

                    <tr>
                        <td>
                            <label for="contract">CONTRACT NO. :</label><br>
                            <input type="text" class="input" id="contract" placeholder="Enter Contract" name="contract"
                                required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="stall">STALL NO. :</label><br>
                            <input type="text" class="input" id="stall" placeholder="Enter Stall" name="stall">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="company">TENANT:</label><br>
                            <input type="text" id="company" class="input" placeholder="Enter Tenant" name="company"
                                required>
                        </td>
                    </tr>
                    <script>
                        ac.attach({
                            target: document.getElementById("contract"),
                            data: "contractsearch.php"
                        });
                    </script>
                    <script>
                        ac.attach({
                            target: document.getElementById("stall"),
                            data: "stallsearch.php"
                        });
                    </script>
                     <script>
                        ac.attach({
                            target: document.getElementById("company"),
                            data: "companysearch.php"
                        });
                    </script>
                </table>
            </div>

            <div class="con2" id="con2" class="con2">
                <table class="table-AR" id="tableAR">
                    <tbody></tbody>
                    <tr>
                        <th style="font-size: 12px; color: gray; border: none;">TRANSACTION #</th>
                        <td>
                            <?php
                            $transaction_id_query = "SELECT MAX(transaction_id) AS max_transaction_id FROM ar";
                            $result = mysqli_query($conn, $transaction_id_query);
                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $max_transaction_id = $row['max_transaction_id'];
                                // Increment the max_transaction_id by 1 to get the next receipt number
                                $receipt_number = $max_transaction_id + 1;
                                echo $receipt_number;
                            } else {
                                echo "Error: " . mysqli_error($conn);
                            }
                            ?>

                        </td>
                    </tr>
                    <tr>
                        <th class="th" colspan="2">DATE</th>
                        <td class="td" colspan="2">
                            <span id="currentDateTime"></span>
                            <input type="hidden" name="date" id="date">
                        </td>
                    </tr>
                    <tr>
                        <th class="th" colspan="2" style="width: 500px;">MODE OF PAYMENT </th>
                        <td colspan="2">
                            <select name="payment" id="payment" required onchange="showPaymentInput(this.value)">
                                <option value="">Payment Type</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                            </select>
                            <div id="checkInputRow">
                                <!-- Input field for Check -->
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="4" class="particular"> PARTICULARS</th>
                    </tr>
                    <tr class="border-bottom text-center">
                        <th>Month</th>
                        <th>Charges</th>
                        <th>Amount</th>
                        <th>
                            <button type="button" class="addentry" onclick="addFields()">
                                <span class="addentry__text">Add Item</span>
                                <span class="addentry__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                        viewBox="0 0 24 24" stroke-width="2" stroke-linejoin="round"
                                        stroke-linecap="round" stroke="currentColor" height="24" fill="none"
                                        class="svg">
                                        <line y2="19" y1="5" x2="12" x1="12"></line>
                                        <line y2="12" y1="12" x2="19" x1="5"></line>
                                    </svg></span>
                            </button>
                        </th>
                    </tr>
                    <tr>
                        <div id="additionalFields"></div>
                        <th colspan="2" style="text-align: center;">Total</th>
                        <th colspan="2">
                            <input style="width: 190px;" class="total" id="totalAmount" type="text" placeholder="Total"
                                name="total" value="0" required readonly>
                        </th>
                    </tr>
                    <tr>
                        <th colspan="2">PAID BY:</th>
                        <td><input name="paidby" type="text" class="input" placeholder="Enter Payer Name" required
                                oninput="validateAlphabeticInput(this)"></td>
                    </tr>
                    <tr>
                        <th>RECEIVED BY:</th>
                        <td style="text-align:center;">
                            <?php echo $_SESSION['fullname'] ?> <br>
                            <p class="mb-0 text-center" style="text-align:center;">
                                <?php echo $_SESSION['position'] ?>
                            </p>
                        </td>

                        <td class="td border-bottom" colspan="4">
                            <button class="btn btn-primary float-right mr-2" type="submit" name="submit"
                                onclick="return confirmSubmission()">Submit</button>
                        </td>
                    </tr>

                </table>
            </div>
        </form>
    </div>

        <!-- Modal -->

                      <div class="interior">
    <a class="modalbutton btn btn-primary" href="#open-modal"><span>Tenant List</span> </a>
  </div>
</div>
<div id="open-modal" class="modal-window" >
  <div>
    <a href="#" title="Close" class="modal-close">Close</a>
    <h1>Tenant List</h1>
  
    <table id="example" class="table  table-hover table-bordered">
                                <thead class="table-primary">
                                    <tr> <!-- TABLE HEADER-->
                                        <th>Company</th>
                                        <th>Contract</th>
                                        <th>Stall</th>
                                        <th>Trade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $qry = $conn->query("SELECT * FROM `tenants` ORDER BY `contract` DESC");
                                    while ($row = $qry->fetch_assoc()):
                                        ?>
                                        <!-- LIST OF TRANSACTION-->
                                        <tr class="active-row" data-contract="<?php echo $row['contract']; ?>">
                                            <td>
                                                <?php echo $row['company']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['contract']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['stall']; ?>
                                            </td>
                                            <td>
                                                <?php echo $row['trade']; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>

</div>
</div>
    <script>

        function updateContainerHeight() {
            var containerCon = document.getElementById("containerCon");
            var con2 = document.getElementById("tableAR");
            var con2Height = con2.offsetHeight; // Get the height of con2

            // Set the height of containerCon to con2Height
            containerCon.style.height = con2Height + "px";
        }

        // ADD - REMOVE ENTRY
        var entryCount = 1;

        function addFields() {
            var table = document.getElementById("tableAR");
            var newRow = table.insertRow(5);
            var cell1 = newRow.insertCell(0);
            var cell2 = newRow.insertCell(1);
            var cell3 = newRow.insertCell(2);
            var cell4 = newRow.insertCell(3);

            var containerId = "additionalFields" + entryCount;
            entryCount++;

            newRow.id = containerId; // Set a unique id for the row

            cell1.innerHTML = `
        <select style="margin-left:10%; text-align:center;" name="displayedMonth[]" required>
            <option disabled selected>SELECT MONTH</option>
            <option value="JANUARY">JANUARY</option>
            <option value="FEBRUARY">FEBRUARY</option>
            <option value="MARCH">MARCH</option>
            <option value="APRIL">APRIL</option>
            <option value="MAY">MAY</option>
            <option value="JUNE">JUNE</option>
            <option value="JULY">JULY</option>
            <option value="AUGUST">AUGUST</option>
            <option value="SEPTEMBER">SEPTEMBER</option>
            <option value="OCTOBER">OCTOBER</option>
            <option value="NOVEMBER">NOVEMBER</option>
            <option value="DECEMBER">DECEMBER</option>
        </select>`;
            cell2.innerHTML = `
        <select style="margin-left:10%; text-align:center;"  name="displayedCharges[]" required onchange="showInput(this);">
            <option disabled selected>SELECT CHARGES</option>
            <option value="BALANCE">BALANCE</option>
            <option value="RENT">RENT</option>
            <option value="CUSA">CUSA</option>
            <option value="AIRCON">AIRCON</option>
            <option value="ELECTRICITY">ELECTRICITY</option>
            <option value="WATER">WATER</option>
            <option value="BILL BOARD">BILL BOARD</option>
            <option value="FEST CONTROL">FEST CONTROL</option>
            <option value="BIO AUGMENTATION">BIO AUGMENTATION</option>
            <option value="OTHERS">OTHERS</option>
        </select>
        
        <div id="${containerId}OtherInput" style="display: none;">
            <input type="text" class="input" id="otherOption" name="otherOption[]" placeholder="Enter Other Charge">
        </div>`;

            cell3.innerHTML = `<input style="text-align:center;" type="text" class="input" name="displayedAmount[]" placeholder="Enter Amount" oninput="formatAmountInput(this); calculateTotal();">`;
            cell4.innerHTML = `<button type="button" class="delete-button" onclick="removeFields('${containerId}'); updateContainerHeight();">  <svg class="delete-svgIcon" viewBox="0 0 448 512">
                    <path d="M135.2 17.7L128 32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320l-7.2-14.3C307.4 6.8 296.3 0 284.2 0H163.8c-12.1 0-23.2 6.8-28.6 17.7zM416 128H32L53.2 467c1.6 25.3 22.6 45 47.9 45H346.9c25.3 0 46.3-19.7 47.9-45L416 128z"></path>
                  </svg> </button>`;

            // Get the reference node (row for month, charges, and amount)
            var referenceNode = table.rows[4];

            // Insert the new row after the reference node
            table.insertBefore(newRow, referenceNode.nextSibling);
            updateContainerHeight();
        }

        function removeFields(containerId) {
            var row = document.getElementById(containerId);
            var table = document.getElementById("tableAR");

            table.deleteRow(row.rowIndex);
            calculateTotal();
            updateContainerHeight();
        }
        // END OF ADD - REMOVE ENTRY

        function showInput(selectElement) {
            var containerId = selectElement.parentNode.parentNode.id;
            var otherInput = document.getElementById(containerId + 'OtherInput');

            if (selectElement.value === 'OTHERS') {
                otherInput.style.display = 'block';
            } else {
                otherInput.style.display = 'none';
            }
        }

        // CALCULATE THE INPUT AMOUNT
        function calculateTotal() {
            var total = 0;
            var displayedAmountFields = document.getElementsByName("displayedAmount[]");

            displayedAmountFields.forEach(function (field) {
                // Parse the value as a float (ignoring commas) and add it to the total
                total += parseFloat(field.value.replace(/,/g, '') || 0);
            });

            // Format the total with commas
            var formattedTotal = total.toLocaleString('en-US');

            // Display the formatted total in the totalAmount field
            document.getElementById("totalAmount").value = formattedTotal;
        }
    </script>

    <script>
        function showAmountInput() {
            var monthDropdown = document.getElementById('displayedMonth');
            var chargesDropdown = document.getElementById('displayedCharges');
            var amountInput = document.getElementById('displayedAmount');

            // Check if both month and charges are selected
            if (monthDropdown.value !== 'Select Month' && chargesDropdown.value !== 'Select Charges') {
                amountInput.style.display = 'block'; // Show the input field
            } else {
                amountInput.style.display = 'none'; // Hide the input field
            }
        }

        function formatAmountInput(input) {
            // You can add formatting logic here if needed
        }
    </script>


    <script>
        //button for  CANCEL
        function confirmCancellation() {
            // Display a confirmation prompt
            var confirmation = confirm("Are you sure you want to cancel? Any unsaved data will be lost.");

            // If the user clicks "OK" in the confirmation prompt, redirect to index.php
            if (confirmation) {
                window.location.href = "index.php";
            }
            // If the user clicks "Cancel" in the confirmation prompt, do nothing
        }

        //button for SUBMIT
        function confirmSubmission() {
            // Get the form element
            var form = document.forms[0];

            // Check if the form is valid
            if (form.checkValidity()) {
                // Display a confirmation prompt
                var confirmation = confirm("Are you sure you want to submit the form? Double-check your entries.");

                // If the user clicks "OK" in the confirmation prompt, submit the form
                if (confirmation) {
                    // Remove the showSubmitSuccess function
                    // alert("Submit successful");
                    form.submit();
                }
                // If the user clicks "Cancel" in the confirmation prompt, do nothing (form submission is canceled)
            } else {
                // If the form is not valid, do something (e.g., display an error message)
                alert("Please fill out the form correctly before submitting.");
            }
        }


        //For Date JS -->
        function updateDateTime() {
            var dateElement = document.getElementById("date");
            var currentDateTimeElement = document.getElementById("currentDateTime");
            var currentDate = new Date();

            // Use toLocaleString to format the date and time
            var formattedDateTime = currentDate.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });

            // Update the content of the span element
            currentDateTimeElement.textContent = formattedDateTime;

            // Update the value of the hidden date field (if needed)
            dateElement.value = currentDate.toISOString(); // Use ISO format for hidden input
        }

        // Call the updateDateTime function when the page loads to initially set the date and time
        updateDateTime();

        // Optionally, update the date and time every second (1,000 milliseconds)
        setInterval(updateDateTime, 1000);


        // For comma amoount -->
        function showPaymentInput(selectedValue) {
            var checkInputRow = document.getElementById("checkInputRow");

            // Hide the Check input row initially
            checkInputRow.style.display = "none";

            if (selectedValue === "Cash") {
                // If "Cash" is selected, hide the Check input field
                checkInputRow.style.display = "none";
            } else if (selectedValue === "Check") {
                // If "Check" is selected, generate the Check input field
                checkInputRow.style.display = "block";
                checkInputRow.innerHTML = '<input type="text" style="margin-top:2%; width:54%;"  class="input" name="checknumber" id="checknumber" placeholder="Enter check number">';
            }
        }

        function formatAmountInput(input) {
            // Remove any non-digit characters and keep the decimal point
            var formattedValue = input.value.replace(/[^0-9.]/g, '');

            // Split the value into integer and fractional parts
            var parts = formattedValue.split('.');

            // Format the integer part with commas
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Limit the fractional part to two decimal places
            if (parts[1] !== undefined) {
                parts[1] = parts[1].slice(0, 2);
            }

            // Reconstruct the formatted value
            formattedValue = parts.join('.');

            // Update the input field with the formatted value
            input.value = formattedValue;
        }

        // Char Only
        function validateAlphabeticInput(input) {
            // Remove any non-alphabetic characters from the input value
            var cleanedValue = input.value.replace(/[^A-Za-z ]/g, '');

            // Update the input field with the cleaned value
            input.value = cleanedValue;
        }


        //input numbers olny -->
        document.addEventListener("DOMContentLoaded", function () {
            var chargesDropdown = document.getElementById("chargesDropdown");
            var amountInput = document.getElementById("amountInput");

            chargesDropdown.addEventListener("change", function () {
                // Create an input field
                var inputField = document.createElement("input");
                inputField.type = "text";
                inputField.name = "amount";
                inputField.placeholder = "Enter Amount";

                // Clear previous content and append the input field
                amountInput.innerHTML = "";
                amountInput.appendChild(inputField);
            });
        });
    </script>

</body>
<script src="https://code.jquery.com/jquery-3.7.0.js"> </script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"> </script>
    <script src=" https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src=" assets/Responsive/css/dataTables.responsive.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="script.js"></script>
</html>

