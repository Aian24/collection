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
    $bank = isset($_POST["bank"]) ? $_POST["bank"] : '';
    $total = isset($_POST["total"]) ? $_POST["total"] : '';
    $payment = isset($_POST["payment"]) ? $_POST["payment"] : '';
    $paidby = isset($_POST["paidby"]) ? $_POST["paidby"] : '';
    $displayedMonth = isset($_POST["displayedMonth"]) ? $_POST["displayedMonth"] : '';
    $displayedCharges = isset($_POST["displayedCharges"]) ? $_POST["displayedCharges"] : '';
    $displayedAmount = isset($_POST["displayedAmount"]) ? $_POST["displayedAmount"] : '';
    $branch = $_SESSION['branch'];

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

    // Generate the transaction_id before the loop
    $transaction_id_query = "SELECT MAX(transaction_id) AS max_transaction_id FROM $targetTable";
    $result = mysqli_query($conn, $transaction_id_query);

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $max_transaction_id = $row['max_transaction_id'];
        // Increment the max_transaction_id by 1 to get the next transaction_id
        $transaction_id = $max_transaction_id + 1;
    } else {
        echo "Error: " . mysqli_error($conn);
        return; // Exit the function if there's an error
    }

    if ($checknumber === null) {
        $checknumber = ''; // You can change this to another default value if needed
    }
    if ($bank === null) {
        $bank = ''; // You can change this to another default value if needed
    }

    $datetime = new DateTime("now", new DateTimeZone('Asia/Manila'));
    $date = $datetime->format('Y-m-d H:i:s');

    if (is_array($_POST['payment'])) {
        // Loop through the payment array
        foreach ($_POST['payment'] as $key => $paymentType) {
            // Get the corresponding values for each entry
            $displayedMonth = $_POST['displayedMonth'][$key];
            $displayedCharges = $_POST['displayedCharges'][$key];
            $displayedAmount = $_POST['displayedAmount'][$key];

            // Check if displayedCharges is "OTHERS" and otherOption is set
            if ($displayedCharges === 'OTHERS' && isset($_POST['otherOption'][$key])) {
                // Use the sanitized otherOption value
                $sanitized_displayedCharges = sanitize_input($_POST['otherOption'][$key]);
            } else {
                // Use the sanitized displayedCharges value
                $sanitized_displayedCharges = sanitize_input($displayedCharges);
            }

            // Rest of your code to insert into the determined targetTable
            $insert = "INSERT INTO $targetTable (transaction_id, branch, company, contract, stall, date, payment, total, paidby, displayedMonth, displayedCharges, displayedAmount) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $insert);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssssssssssss", $sanitized_transaction_id, $sanitized_branch, $sanitized_company, $sanitized_contract, $sanitized_stall, $sanitized_date, $sanitized_payment, $sanitized_total, $sanitized_paidby, $sanitized_displayedMonth, $sanitized_displayedCharges, $sanitized_displayedAmount);

                // Use the generated transaction_id for all entries
                $sanitized_transaction_id = $transaction_id;

                $sanitized_branch = sanitize_input($branch);
                $sanitized_company = sanitize_input($company);
                $sanitized_contract = sanitize_input($contract);
                $sanitized_stall = sanitize_input($stall);
                $sanitized_date = sanitize_input($date);

                // Check if the payment type is "Check" or "Bank Transfer" to include the relevant information
                if ($paymentType === 'Check') {
                    $sanitized_payment = "Check: " . sanitize_input($checknumber);
                } elseif ($paymentType === 'Bank Transfer') {
                    $sanitized_payment = "Bank: " . sanitize_input($bank);
                } else {
                    $sanitized_payment = sanitize_input($paymentType);
                }

                $sanitized_total = sanitize_input($total);
                $sanitized_paidby = sanitize_input($paidby);
                $sanitized_displayedMonth = sanitize_input($displayedMonth);
                $sanitized_displayedCharges = sanitize_input($displayedCharges);

                // Check if displayedCharges is "OTHERS" and otherOption is set
                if ($sanitized_displayedCharges === 'OTHERS' && isset($_POST['otherOption'][$key])) {
                    // Use the sanitized otherOption value
                    $sanitized_displayedAmount = sanitize_input($_POST['otherOption'][$key]);
                } else {
                    // Use the sanitized displayedAmount value
                    $sanitized_displayedAmount = sanitize_input($displayedAmount);
                }

                // Execute the statement
                if (!mysqli_stmt_execute($stmt)) {
                    echo "Error: " . mysqli_error($conn);
                    // Handle the error here, if necessary
                }
            }
        }
        // Fetch the last inserted ID based on the target table
        $last_id_query = "SELECT MAX(transaction_id) AS max_transaction_id FROM $targetTable";
        $result = mysqli_query($conn, $last_id_query);

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $transaction_id = $row['max_transaction_id'];
        } else {
            echo "Error: " . mysqli_error($conn);
            return; // Exit the function if there's an error
        }

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
    <title>Create OTHI Report</title>
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



    <!-- nav -->
    <nav class="topnav" id="myTopnav">
        <a href="" style="font-weight: 500; font-size: 24px; padding: 5px; margin-left: 10px;">Welcome,
            <?php echo $_SESSION['fullname']; ?>
        </a>
        <?php if (isset($_SESSION['user_name'])): ?>
            <a href="index.php?logout" style="float: right;">Log Out</a>
        <?php endif; ?>
        <a href="contactus.php" style="float: right;">Contact Us </a>
        <a href="CreateAR.php" style="float: right;">Create AR </a>
        <a href="user_page.php" style="float: right;">Transaction Report</a>
        <a href="javascript:void(0);" class="icon" onclick="myFunction()">
            <i class="fa fa-bars"></i>
        </a>
    </nav>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            function myFunction() {
                var x = document.getElementById("myTopnav");
                if (x.className === "topnav") {
                    x.className += " responsive";
                } else {
                    x.className = "topnav";
                }
            }

            // Attach the myFunction to the click event of the hamburger icon
            document.querySelector(".icon").addEventListener("click", myFunction);
        });
    </script>

    <div class="containerCon" id="containerCon">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post" autocomplete="off"
            onsubmit="return confirmSubmission();">

            <div>
                <h3 class="pay">OTHER INCOME</h3>
            </div>
            <div class="con1">
                <table id="myForm" class="myForm">

                    <div class="container" style="margin-top:-30%;">
                        <tr>
                            <td>
                                <img class="logoAdd" src="images/userimage.png"><br><br>
                            </td>
                        </tr>
                       
                        <tr>
                            <td>
                                <label for="branch">BRANCH:
                                    <?php echo $_SESSION['branch'] ?>
                                </label><br>

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
                        <th colspan="2" style="font-size: 12px; color: gray; border: none;">TRANSACTION NO.</th>
                        <td>
                            <?php
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
                            $transaction_id_query = "SELECT MAX(transaction_id) AS max_transaction_id FROM $targetTable";
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
                        <th class="th" colspan="2">DATE:</th>
                        <td class="td" colspan="2">
                            <span id="currentDateTime"></span>
                            <input type="hidden" name="date" id="date">
                        </td>
                    </tr>
                    <tr>
                        <th colspan="6" class="particular"></th>
                    </tr>
                    <tr class="border-bottom text-center">
                        <th>Payment</th>
                        <th>Month</th>
                        <th>Particulars</th>
                        <th>Amount</th>
                        <th>
                            <button type="button" class="button btn btn-success" onclick="addFields()">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                                        <path fill="none" d="M0 0h24v24H0z"></path>
                                        <path fill="currentColor" d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z"></path>
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th></th>
                    </tr>
                    <tr>
                        <div id="additionalFields"></div>
                        <th colspan="3" style="text-align: right;">Total</th>
                        <th>
                            <input style="width: 190px;" class="total text-center" id="totalAmount" type="text"
                                placeholder="Total" name="total" value="0" required readonly>
                        </th>
                    </tr>
                    <tr>
                        <th colspan="2">PAID BY:</th>
                        <td><input name="paidby" type="text" class="input" placeholder="Enter Payer Name" required
                                oninput="validateAlphabeticInput(this)"></td>
                    </tr>
                    <tr>
                        <th colspan="2">RECEIVED BY:</th>
                        <td style="text-align:center;">
                            <?php echo $_SESSION['fullname'] ?> <br>
                            <p class="mb-0 text-center" style="text-align:center;">
                                <?php echo $_SESSION['position'] ?>
                            </p>
                        </td>

                        <td class="td" colspan="4">
                            <button class="btn btn-primary text-center ml-5" type="submit" name="submit"
                                onclick="return confirmSubmission()">Submit</button>
                        </td>
                    </tr>

                </table>
            </div>
        </form>
    </div>

    <!-- Modal -->
    <div class="interior">
    </div>


    <!-- The Modal -->
    <div class="overlay" id="overlay" onclick="closeModal()"></div>
    <div class="modaltenant" id="modaltenant">
        <a href="#" title="Close" class="modal-close" onclick="closeModal()">Close</a>
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
                $location = $_SESSION['branch']; // Use the branch information from the session
                
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
                    case 'Antipolo Market':
                        $targetTable = 'antipolomarkettenants';
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

                // Execute the query based on the selected table
                $qry = $conn->query("SELECT * FROM `$targetTable` ORDER BY `contract` DESC");

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
            var newRow = table.insertRow(4);
            var cell1 = newRow.insertCell(0);
            var cell2 = newRow.insertCell(1);
            var cell3 = newRow.insertCell(2);
            var cell4 = newRow.insertCell(3);
            var cell5 = newRow.insertCell(4);

            var containerId = "additionalFields" + entryCount;
            entryCount++;

            newRow.id = containerId; // Set a unique id for the row
            cell1.innerHTML = `<tr>
            <td>
    <select class="entry" name="payment[]" id="payment_${containerId}" required onchange="showPaymentInput(this.value, '${containerId}')">
    <option value="">Payment Type</option>
    <option value="Cash">Cash</option>
    <option value="Check">Check</option>
    <option value="Bank Transfer">Bank Transfer</option>
</select>
    <div class="entry" name="payment" id="checkInputRow_${containerId}"></div>
    <div class="entry" name="payment" id="bankkInputRow_${containerId}"></div>
    </td>
</tr>`;

            cell2.innerHTML = `
        <select style="text-align:center;" name="displayedMonth[]" required>
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
            cell3.innerHTML = `
            <select required class="entry" style="text-align:center;" name="displayedCharges[]" onchange="showInput(this);" id="${containerId}ChargesDropdown">
            <option disabled selected>SELECT CHARGES</option>
            <option value="TABLE TENNIS">TABLE TENNIS</option>
            <option value="PAY TOILET">PAY TOILET</option>
            <option value="PAY PARKING">PAY PARKING</option>
            <option value="ICE & WATER">ICE & WATER</option>
            <option value="ULAM VENDOR">ULAM VENDOR</option>
            <option value="GAS">GAS</option>
            <option value="FAMYLIHAN">FAMYLIHAN</option>
            <option value="GARBAGE HAULING">GARBAGE HAULING</option>
            <option value="PHOTO COPY">PHOTO COPY</option>
            <option value="TENANT ID">TENANT ID</option>
            <option value="FUNCTION ROOM">FUNCTION ROOM</option>
            <option value="TABLES AND CHAIRS">TABLES AND CHAIRS</option>
            <option value="OVER NIGHT WORKERS">OVER NIGHT WORKERS</option>
            <option value="VENDO SALES">VENDO SALES</option>
            <option value="ZUMBA">ZUMBA</option>
</select>

<!-- Add this div after the charges dropdown to dynamically display input text for "OTHERS" -->
<div required class="entry" id="${containerId}OtherInput" style="display: none;"></div>`;
            cell4.innerHTML = `<input style="text-align:center;" type="text" class="entry input" required name="displayedAmount[]" placeholder="Enter Amount" oninput="formatAmountInput(this); calculateTotal();">`;
            cell5.innerHTML = `<button type="button" class="delete-button " onclick="removeFields('${containerId}'); updateContainerHeight();">  <svg class="delete-svgIcon" viewBox="0 0 448 512">
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
        function showInput(selectElement) {
            var containerId = selectElement.parentNode.parentNode.id;
            var otherInput = document.getElementById(containerId + 'OtherInput');

            if (selectElement.value === 'OTHERS') {
                replaceWithInputText(selectElement);
                otherInput.style.display = 'block';
            } else {
                otherInput.innerHTML = ''; // Clear the content of the otherInput
                otherInput.style.display = 'none';
            }
        }

        function replaceWithInputText(selectElement) {
            var containerId = selectElement.parentNode.parentNode.id;
            var chargesDropdown = document.getElementById(containerId + 'ChargesDropdown');
            var otherInput = document.getElementById(containerId + 'OtherInput');

            // Replace the charges dropdown with an input text field
            otherInput.innerHTML = '<input type="text" class="input" id="otherOption" name="displayedCharges[]" placeholder="Enter Other Charge">';
            chargesDropdown.parentNode.replaceChild(otherInput.firstChild, chargesDropdown);
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
        document.addEventListener("DOMContentLoaded", function () {
            // Clear the flag indicating that the form has been submitted
            localStorage.removeItem("formSubmitted");

            // Your existing JavaScript code here

            // Function to show the confirmation alert
            function confirmSubmission() {
                return confirm("Are you sure you want to submit?");
            }

            // Attach the confirmSubmission function to the form submit event
            document.querySelector("form").addEventListener("submit", function (event) {
                // Show the alert and prevent form submission if the user clicks Cancel
                if (!confirmSubmission()) {
                    event.preventDefault();
                } else {
                    // Set the flag in localStorage to indicate that the form has been submitted
                    localStorage.setItem("formSubmitted", "true");
                }
            });
        });

    </script>


    <script>

        //For Date JS -->
        function updateDateTime() {
            var dateElement = document.getElementById("date");
            var currentDateTimeElement = document.getElementById("currentDateTime");

            // Get the current date and time
            var currentDate = new Date();

            // Use toLocaleString to format the date and time
            var formattedDateTime = currentDate.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
                hour12: true,
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
        function showPaymentInput(selectedValue, containerId) {
            var paymentDropdown = document.getElementById(`payment_${containerId}`);
            var checkInputRow = document.getElementById(`checkInputRow_${containerId}`);
            var bankInputRow = document.getElementById(`bankkInputRow_${containerId}`); // corrected typo

            // Hide the Check and Bank input rows initially
            checkInputRow.style.display = "none";
            bankInputRow.style.display = "none";

            if (selectedValue === "Check" || selectedValue === "Bank Transfer") {
                // If "Check" or "Bank Transfer" is selected, hide the dropdown and display the Check/Bank input field
                paymentDropdown.style.display = "none";

                if (selectedValue === "Check") {
                    checkInputRow.style.display = "block";
                    checkInputRow.innerHTML = '<input type="text" style="margin-top:2%;" class="input" name="checknumber" placeholder="Check number">';
                } else if (selectedValue === "Bank Transfer") {
                    bankInputRow.style.display = "block";
                    bankInputRow.innerHTML = '<input type="text" style="margin-top:2%;" class="input" name="bank" placeholder="Bank Transfer Information">';
                }
            } else {
                // If any other payment type is selected, show the dropdown and hide the Check/Bank input fields
                paymentDropdown.style.display = "block";
                checkInputRow.style.display = "none";
                bankInputRow.style.display = "none";

                // Clear the content of the Check/Bank input rows
                checkInputRow.innerHTML = '';
                bankInputRow.innerHTML = '';
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

    <script>
        // Wait for the document to be fully loaded
        $(document).ready(function () {
            // Add a click event listener to each row in the Tenant List table
            $('#example tbody tr').css('cursor', 'pointer').click(function () {
                // Retrieve data from the clicked row
                var company = $(this).find('td:eq(0)').text().trim();
                var contract = $(this).find('td:eq(1)').text().trim();
                var stall = $(this).find('td:eq(2)').text().trim();

                // Fill up the contract, stall, and tenant fields in the main form
                $('#contract').val(contract).prop('readonly', true);
                $('#stall').val(stall).prop('readonly', true);
                $('#company').val(company).prop('readonly', true);

                // Close the Tenant List modal
                $('#open-modal').modal('hide');
            });
        });
    </script>
    <script>
        function openModal() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('modaltenant').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('modaltenant').style.display = 'none';
        }
    </script>



    <script src="https://code.jquery.com/jquery-3.7.0.js"> </script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"> </script>
    <script src=" https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src=" assets/Responsive/css/dataTables.responsive.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="script.js"></script>

</body>

</html>