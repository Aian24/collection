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
    <script src="https://cdn.lordicon.com/lordicon-1.1.0.js"></script>
    <!----===== Iconscout CSS ===== -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">



</head>

<body>

    <h1 class="shinedash" style="margin-left:7%;" target="_blank"> <i class="uil uil-exchange-alt"></i>UPDATE TENANTS
    </h1>
    <center>
        <div class="container">
            <div class="row">

            </div>
            <div id="tenantListContainer" class="row">
                <div class="col-12">
                    <div class="datatableupdate"  style="border: 2px solid blue; box-shadow: 5px 10px #888888; margin-top:7%;">
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
                        <br>
                        <table id="tenantListTable" class="table table-hover table-bordered">
                            <thead class="table-primary">
                                <tr>
                                    <th>Company</th>
                                    <th>Contract</th>
                                    <th>Stall</th>
                                    <th>Trade</th>
                                    <th>Edit</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody id="tenantListBody">
                                <?php
                                $qry = $conn->query("SELECT * FROM `imallantipolotenants` ORDER BY `contract` DESC");
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
                                        <td>
                                            <form action="edit_tenant.php" method="post" style="display: inline;">
                                                <input type="hidden" name="contract"
                                                    value="<?php echo $row['contract']; ?>">
                                                <input type="hidden" name="branch" value="imallantipolotenants">
                                                <button type="submit" class="btn btn-primary">Edit</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form action="delete_tenant.php" method="post"
                                                onsubmit="return confirm('Are you sure you want to delete?');"
                                                style="display: inline;">
                                                <input type="hidden" name="contract"
                                                    value="<?php echo $row['contract']; ?>">
                                                <input type="hidden" name="branch" value="imallantipolotenants">
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </center>
    </section>
    <!-- END OF MODAL -->

    <script src="https://code.jquery.com/jquery-3.7.0.js"> </script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"> </script>
    <script src=" https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src=" assets/Responsive/css/dataTables.responsive.js"></script>
    <script src="script.js"></script>
    <script src=" assets/js/app.js"></script>
    <script>
       

        
        function editUser(contract) {
            // Implement the edit action here
            alert("Edit user with contract: " + contract);
        }

        function deleteUser(contract) {
            // Implement the delete action here
            alert("Delete user with contract: " + contract);
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

    <script>

        
        // Add an event listener to the location dropdown
        document.getElementById('locationDropdown').addEventListener('change', function () {
            // Get the selected location value and its branch name
            var selectedLocation = this.value;
            var selectedBranch = this.options[this.selectedIndex].getAttribute('data-branch');

            // Destroy the existing DataTable instance
            $('#tenantListTable').DataTable().destroy();

            // Fetch and display the corresponding tenant list
            fetchTenantList(selectedLocation, selectedBranch);
        });

        // Function to fetch and display the tenant list based on the selected location
        function fetchTenantList(location, branch) {
            // Make an AJAX request to fetch the tenant list based on the selected location
            $.ajax({
                url: 'load_tenants.php', // Change this to your actual script for loading tenants
                data: { location: location },
                type: 'POST',
                success: function (data) {
                    // Update the tenant list table body with the fetched data
                    document.getElementById('tenantListBody').innerHTML = data;

                    // Reinitialize the DataTable plugin
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
            });

                    // Reattach the event listeners for tenant clicks
                    attachTenantClickListeners();
                },
                error: function (xhr, status, error) {
                    console.error("Error loading tenants:", error);
                }
            });
        }

        // Function to attach event listeners for tenant clicks
        function attachTenantClickListeners() {
            // You can add your event listeners here if needed
            // For example, if you have buttons inside each row, you can attach click listeners to them
        }

        // Initial load on document ready
        fetchTenantList('imallantipolotenants', 'Imall Antipolo'); // Adjust the default values accordingly
    </script>



    <script>
        function performLiveSearch() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("example");
            tr = table.getElementsByTagName("tr");

            for (i = 0; i < tr.length; i++) {
                td = tr[i].getElementsByTagName("td")[0]; // Assuming the company name is in the first column
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>

</body>

</html>