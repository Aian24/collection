<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['admin_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}

if (isset($_SESSION['admin_name']) && !isset($_SESSION['welcome_alert_shown'])) {
    $admin_name = $_SESSION['admin_name'];
    echo "<script>alert('Welcome, $admin_name!');</script>";
    $_SESSION['welcome_alert_shown'] = true; // Set the flag to true
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!----======== CSS ======== -->
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets//Responsive/css/responsive.bootsrap.css">
    <link rel="stylesheet" href="modalHistory.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.lordicon.com/lordicon-1.3.0.js"></script>
    <!----===== Iconscout CSS ===== -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">

</head>

<body>


    <section class="dashboard">

        <h1 class="shinedash" target="_blank"> <i class="uil uil-dashboard"></i> DASHBOARD</h1>
        <div class="dash-content">
            <div class="overview"><br>
                <div class="boxes">
                    <div class="box box1">
                        <lord-icon src="https://cdn.lordicon.com/epietrpn.json" trigger="hover"
                            colors="primary:#104891,secondary:#4bb3fd,tertiary:#66a1ee,quaternary:#104891,quinary:#2516c7,senary:#1b1091,septenary:#ebe6ef"
                            style="width:70px;height:70px;"></lord-icon>
                        <span class="text">Total Tenants</span>
                        <?php


                        $tableNames = array('imallantipolotenants', 'imallcanlubangtenants', 'imallcamarintenants','imallfamytenats', 'cogeotownplazatenants', 'apmcommercialtenats', 'citicentretenats');
                        $totalTenants = 0;

                        foreach ($tableNames as $tableName) {
                            $sql = "SELECT COUNT(*) AS count FROM $tableName";
                            $result = $conn->query($sql);

                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $count = $row['count'];
                                $totalTenants += $count;
                                mysqli_free_result($result);
                            }
                        }

                        echo "Total: " . $totalTenants;
                        ?>

                    </div>

                    <div class="box box2">
                        <lord-icon src="https://cdn.lordicon.com/swuwvhnk.json" trigger="hover"
                            colors="primary:#121331,secondary:#1663c7,tertiary:#ebe6ef"
                            style="width:70px;height:70px;"></lord-icon>
                        <span class="text">Total AR </span>
                        <?php
                        // Array of table names
                        $tables = ['imallantipolo', 'imallcamarin', 'imallcanlubang','imallfamy', 'cogeotownplaza', 'apmcommercial', 'citicentre'];

                        // Initialize total count
                        $totalCount = 0;

                        // Loop through tables and get counts
                        foreach ($tables as $table) {
                            $sql = "SELECT COUNT(*) as count FROM $table";
                            $result = $conn->query($sql);

                            // Check if the query was successful
                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $totalCount += $row['count'];
                            } else {
                                echo "Error in query: " . mysqli_error($conn);
                            }
                        }

                        // Display the total count
                        echo "Total: " . $totalCount;
                        ?>
                    </div>


                    <div class="box box3">
                        <lord-icon src="https://cdn.lordicon.com/rymzvwiu.json" trigger="hover"
                            colors="primary:#121331,secondary:#1663c7,tertiary:#ebe6ef,quaternary:#ffc738,quinary:#4bb3fd,senary:#f24c00,septenary:#08a88a,octonary:#f28ba8,nonary:#92140c"
                            style="width:70px;height:70px;"></lord-icon>
                        <span class="text">Total Users</span>
                        <?php
                        $sql = "SELECT count(*) FROM users";
                        $result = $conn->query($sql);
                        while ($row = mysqli_fetch_array($result)) {
                            echo "Total: " . $row['count(*)'];
                        }
                        ?>
                    </div>
                </div>
            </div>
            <br><br>

            <!-- modal to display user's per transaction -->
            <div id="transactionHistoryModal" class="modal" style="   background-color: rgb(0,0,0); 
                                            background-color: rgba(0,0,0,0.3);">
                <div class="modal_content">
                <span class="close" id="closeTransactionModal">&times;</span>
                    <h2 class="text-center border-bottom">Transaction History</h2>
                    <div class="container-input">
                        <input style="margin-left:1.5%;" type="text" id="searchTransaction" class="input"
                            placeholder="Search...">
                        <svg fill="#000000" width="20px" height="20px" viewBox="0 0 1920 1920"
                            xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M790.588 1468.235c-373.722 0-677.647-303.924-677.647-677.647 0-373.722 303.925-677.647 677.647-677.647 373.723 0 677.647 303.925 677.647 677.647 0 373.723-303.924 677.647-677.647 677.647Zm596.781-160.715c120.396-138.692 193.807-319.285 193.807-516.932C1581.176 354.748 1226.428 0 790.588 0S0 354.748 0 790.588s354.748 790.588 790.588 790.588c197.647 0 378.24-73.411 516.932-193.807l516.028 516.142 79.963-79.963-516.142-516.028Z"
                                fill-rule="evenodd"></path>
                        </svg>
                    </div>

                    <div id="transaction-details" class="transactionDetails text-center"></div>
                </div>
            </div>
            <div class="container">
                <div class="row">
                </div>
                <div id="tenantListContainer" class="row">
                    <div class="col-12">
                        <div class="datatable p-3" style="border: 2px solid blue; box-shadow: 5px 10px #888888;">
                            <!-- Dropdown list for Selection of Branches-->
                            <center>
                                <select id="locationDropdown" class="tenant">
                                    <option selected disabled>Select Branch</option>
                                    <option value="imallantipolotenants" data-branch="Imall Antipolo">Imall Antipolo
                                    </option>
                                    <option value="imallcanlubangtenants" data-branch="Imall Canlubang">Imall Canlubang
                                    </option>
                                    <option value="imallcamarintenants" data-branch="Imall Camarin">Imall Camarin
                                    </option>
                                    <option value="imallfamytenats" data-branch="Imall Famy">Imall Famy
                                    </option>
                                    <option value="cogeotownplazatenants" data-branch="Cogeo Town Plaza">Cogeo Town
                                        Plaza
                                    </option>
                                    <option value="apmcommercialtenats" data-branch="APM Commercial">APM Commercial
                                    </option>
                                    <option value="citicentretenats" data-branch="CITI Centre">CITI Centre
                                    </option>
                                </select>
                            </center>
                            <br><br>
                            <table id="tenantListTable" class="table table-hover table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Company</th>
                                        <th>Contract</th>
                                        <th>Stall</th>
                                        <th>Trade</th>
                                    </tr>
                                </thead>
                                <tbody id="tenantListBody">
                                    <?php
                                   $qry = ''; // Initialize an empty string to store the concatenated queries

                                   foreach ($tableNames as $tableName) {
                                       $qry .= "SELECT * FROM `$tableName` UNION ALL ";
                                   }
                                   
                                   // Remove the trailing 'UNION ALL ' from the final query
                                   $qry = rtrim($qry, ' UNION ALL ');
                                   
                                   // Add the ORDER BY clause to the final query
                                   $qry .= " ORDER BY `contract` DESC";
                                   
                                   $result = $conn->query($qry);
                                   
                                   while ($row = $result->fetch_assoc()):
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
            </div>


    <script>
         $(document).ready(function () {
            // Initialize DataTable with lengthMenu option
            $('#tenantListTable').DataTable({
                "paging": true,
                "searching": true,
                "info": true,
                "lengthChange": true,
                "responsive": true,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                // Add other DataTables options as needed
            });
        });

        // Add an event listener to the location dropdown
        document.getElementById('locationDropdown').addEventListener('change', function () {
            // Get the selected location value and its branch name
            var selectedLocation = this.value;
            var selectedBranch = this.options[this.selectedIndex].getAttribute('data-branch');

            // Destroy the existing DataTable instance
            $('#tenantListTable').DataTable().destroy();

            // Fetch and display the corresponding tenant list
            updateTenantList(selectedLocation, selectedBranch);
        });


        // Function to fetch and display the tenant list based on the selected location
        function updateTenantList(selectedLocation, selectedBranch) {
            // Make an AJAX request to fetch the updated tenant list
            $.ajax({
                url: 'get_table_data.php',
                data: { location: selectedLocation },
                type: 'POST',
                success: function (response) {
                    // Update the tenant list table body with the fetched data
                    document.getElementById('tenantListBody').innerHTML = response;

                    // Reattach the event listeners for tenant clicks
                    attachTenantClickListeners();

                    // Initialize DataTable again after updating the content
                    $('#tenantListTable').DataTable({
                        "paging": true,
                        "searching": true,
                        "info": true,
                        "lengthChange": true,
                        "responsive": true,
                        "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                        // Add other DataTables options as needed
                    });
                }
            });
        }


        // Function to fetch and display transaction details
        function fetchAndDisplayTransactionDetails(contract) {
            $.ajax({
                url: 'get_transaction_details.php',
                data: { contract: contract },
                type: 'POST',
                success: function (response) {
                    document.getElementById('transaction-details').innerHTML = response;
                    // Show the modal after fetching data
                    $('#transactionHistoryModal').show();
                }
            });
        }

         // Attach click event listeners to tenant rows
        function attachTenantClickListeners() {
            const userRows = document.querySelectorAll(".active-row");

            userRows.forEach((userRow) => {
                userRow.addEventListener("click", function () {
                    const contract = userRow.getAttribute("data-contract");
                    // Fetch and display transaction details when a row is clicked
                    fetchAndDisplayTransactionDetails(contract);
                });
            });
        }

        //Close the modal when the close button is clicked
        document.getElementById('closeTransactionModal').addEventListener('click', function () {
            $('#transactionHistoryModal').hide();
        });

        // Close the modal when clicking outside the modal
        window.addEventListener("click", function (event) {
            if (event.target === $('#transactionHistoryModal')[0]) {
                $('#transactionHistoryModal').hide();
            }
        });

        // Function to fetch and display transaction details
    function fetchAndDisplayTransactionDetails(contract) {
        $.ajax({
            url: 'get_transaction_details.php',
            data: { contract: contract },
            type: 'POST',
            success: function (response) {
                document.getElementById('transaction-details').innerHTML = response;

                // Show the modal after fetching data
                $('#transactionHistoryModal').show();

                // Add live ID 'searchTransaction' to the input field inside the modal
                $('#searchTransaction').attr('live', 'true');

                // Get the search input element
                const searchInput = document.getElementById("searchTransaction");

                // Add an event listener for input changes
                searchInput.addEventListener('input', function () {
                    // Call the filterTransactionDetails function when there's an input change
                    filterTransactionDetails();
                });
            }
        });
    }

    // Custom function to handle the search logic
    function filterTransactionDetails() {
        const searchInput = document.getElementById("searchTransaction");
        const searchTerm = searchInput.value.toLowerCase();
        const rows = document.getElementById('transaction-details').querySelectorAll("tr");

        rows.forEach(function (row, index) {
            if (index === 0) {
                // Skip the first row (header)
                return;
            }
            const cells = row.getElementsByTagName("td");
            let found = false;

            for (let i = 0; i < cells.length; i++) {
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    found = true;
                    break;
                }
            }

            if (found) {
                row.style.display = "table-row";
            } else {
                row.style.display = "none";
            }
        });
    }


        
    </script>



    <script>
        const shinedash = document.querySelector('.shinedash');

        // Function to hide the element when scrolling down
        function handleScroll() {
            if (window.scrollY > 0) {
                shinedash.style.display = 'none';
            } else {
                shinedash.style.display = 'block';
            }
        }

        // Attach the scroll event listener
        window.addEventListener('scroll', handleScroll);

        // Initial check in case the page is loaded already scrolled
        handleScroll();
    </script>




    <script src="https://code.jquery.com/jquery-3.7.0.js"> </script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"> </script>
    <script src=" https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src=" assets/Responsive/css/dataTables.responsive.js"></script>
    <script src="script.js"></script>
    <script src=" assets/js/app.js"></script>

</body>

</html>