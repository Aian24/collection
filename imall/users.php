<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();

date_default_timezone_set('Asia/Manila');

// Check if the user is logged in
if (!isset($_SESSION['admin_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}

$username = $_SESSION['admin_name'];
$result = $conn->query("SELECT id FROM `users` WHERE `username` = '$username'");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['id'];

    // Now, you can update the last login information
    $ip_address = $_SERVER['REMOTE_ADDR']; // Get user IP address
    $current_datetime = date('Y-m-d H:i:s');

    $conn->query("UPDATE `users` SET `last_login` = '$current_datetime', `ip_address` = '$ip_address', `online_status` = 1 WHERE `id` = $user_id");
}

// Check if the Remove button is clicked
if (isset($_POST['remove_user'])) {
    $user_id = $_POST['user_id'];
    // Perform the user removal operation (you should add appropriate validation and error handling here)
    $conn->query("DELETE FROM `users` WHERE `id` = $user_id");
}

// Check if the Delete Selected button is clicked
if (isset($_POST['remove_selected_users'])) {
    // Check if any users are selected for deletion
    if (!empty($_POST['selected_users'])) {
        // Loop through selected users and perform deletion
        foreach ($_POST['selected_users'] as $userId) {
            // Perform the user removal operation (you should add appropriate validation and error handling here)
            $conn->query("DELETE FROM `users` WHERE `id` = $userId");
        }
    }
}

// Fetch user data for the table
$qry = $conn->query("SELECT * from `users` order by (`username`) asc ");
$userData = [];
while ($row = $qry->fetch_assoc()) {
    $userData[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <!----======== CSS ======== -->
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <script src="https://cdn.lordicon.com/lordicon-1.1.0.js"></script>
    <link rel="stylesheet" href="assets//Responsive/css/responsive.bootsrap.css">

</head>

<body>
    <section class="dashboard">
        <h1 class="shine" target="_blank"> <i class="uil uil-user-circle"></i> USERS</h1>

        <center>
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="datatableuser" style="border: 2px solid blue; box-shadow: 5px 10px #888888;">
                            <table id="example" class="table  table-hover table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Username</th>
                                        <th>Fullname</th>
                                        <th>Email</th>
                                        <th>Password</th>
                                        <th>Position</th>
                                        <th>Branch</th>
                                        <th>User Type</th>
                                        <th>Last Login</th>
                                        <th>IP Address</th>
                                        <th>Status</th>
                                        <th>Edit</th>
                                        <th>Delete</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $qry = $conn->query("SELECT * from `users` order by (`username`) asc ");
                                    while ($row = $qry->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td>
                                                <?php echo $row['username'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['fullname'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['email'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['password'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['position'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['branch'] ?>
                                            </td>
                                            <td>
                                                <?php echo $row['user_type'] ?>
                                            </td>
                                            <td>
                                                <?php echo date('F j, Y g:i A', strtotime($row['last_login'])); ?>
                                            </td>

                                            <td>
                                                <?php echo $row['ip_address']; ?>
                                            </td>
                                            <td>
                                                <span style="color: <?php echo $row['online_status'] ? 'green' : 'red'; ?>">
                                                    <?php echo $row['online_status'] ? 'Online' : 'Offline'; ?>
                                                </span>
                                            </td>



                                            <td>

                                                <form action="users.php" method="post">
                                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                    <a href="edituser.php?user_id=<?php echo $row['id']; ?>"
                                                        class="editBtn">
                                                        <svg height="1em" viewBox="0 0 512 512">
                                                            <path
                                                                d="M410.3 231l11.3-11.3-33.9-33.9-62.1-62.1L291.7 89.8l-11.3 11.3-22.6 22.6L58.6 322.9c-10.4 10.4-18 23.3-22.2 37.4L1 480.7c-2.5 8.4-.2 17.5 6.1 23.7s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L387.7 253.7 410.3 231zM160 399.4l-9.1 22.7c-4 3.1-8.5 5.4-13.3 6.9L59.4 452l23-78.1c1.4-4.9 3.8-9.4 6.9-13.3l22.7-9.1v32c0 8.8 7.2 16 16 16h32zM362.7 18.7L348.3 33.2 325.7 55.8 314.3 67.1l33.9 33.9 62.1 62.1 33.9 33.9 11.3-11.3 22.6-22.6 14.5-14.5c25-25 25-65.5 0-90.5L453.3 18.7c-25-25-65.5-25-90.5 0zm-47.4 168l-144 144c-6.2 6.2-16.4 6.2-22.6 0s-6.2-16.4 0-22.6l144-144c6.2-6.2 16.4-6.2 22.6 0s6.2 16.4 0 22.6z">
                                                            </path>
                                                        </svg>
                                                        </button>
                                                    </a>
                                            </td>
                                            <td>
                                                <button class="bin-button" type="submit" name="remove_user"
                                                    onclick="return confirm('Are you sure you want to remove this user?')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 39 7"
                                                        class="bin-top">
                                                        <line stroke-width="4" stroke="white" y2="5" x2="39" y1="5">
                                                        </line>
                                                        <line stroke-width="3" stroke="white" y2="1.5" x2="26.0357" y1="1.5"
                                                            x1="12"></line>
                                                    </svg>
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 33 39"
                                                        class="bin-bottom">
                                                        <mask fill="white" id="path-1-inside-1_8_19">
                                                            <path
                                                                d="M0 0H33V35C33 37.2091 31.2091 39 29 39H4C1.79086 39 0 37.2091 0 35V0Z">
                                                            </path>
                                                        </mask>
                                                        <path mask="url(#path-1-inside-1_8_19)" fill="white"
                                                            d="M0 0H33H0ZM37 35C37 39.4183 33.4183 43 29 43H4C-0.418278 43 -4 39.4183 -4 35H4H29H37ZM4 43C-0.418278 43 -4 39.4183 -4 35V0H4V35V43ZM37 0V35C37 39.4183 33.4183 43 29 43V35V0H37Z">
                                                        </path>
                                                        <path stroke-width="4" stroke="white" d="M12 6L12 29"></path>
                                                        <path stroke-width="4" stroke="white" d="M21 6V29"></path>
                                                    </svg>
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 89 80"
                                                        class="garbage">
                                                        <path fill="white"
                                                            d="M20.5 10.5L37.5 15.5L42.5 11.5L51.5 12.5L68.75 0L72 11.5L79.5 12.5H88.5L87 22L68.75 31.5L75.5066 25L86 26L87 35.5L77.5 48L70.5 49.5L80 50L77.5 71.5L63.5 58.5L53.5 68.5L65.5 70.5L45.5 73L35.5 79.5L28 67L16 63L12 51.5L0 48L16 25L22.5 17L20.5 10.5Z">
                                                        </path>
                                                    </svg>
                                                </button>
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
        <script src="https://code.jquery.com/jquery-3.7.0.js"> </script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"> </script>
        <script src=" https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script src=" assets/Responsive/css/dataTables.responsive.js"></script>


        <script>
    $(document).ready(function () {
        // Initialize DataTable with lengthMenu option
        $('#example').DataTable({
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

        // Function to update user data using AJAX
        function updateUserStatus(userId, status) {
            $.ajax({
                type: "POST",
                url: "update_user_status.php", // Create a new PHP file to handle the update logic
                data: { user_id: userId, online_status: status },
                success: function (response) {
                    // Handle the response if needed
                    console.log(response);
                },
                error: function (error) {
                    console.error(error);
                }
            });
        }

        // Update the online status every 5 seconds (adjust as needed)
        setInterval(function () {
            <?php foreach ($userData as $row): ?>
                var userId = <?php echo $row['id']; ?>;
                var onlineStatus = <?php echo $row['online_status']; ?>;
                updateUserStatus(userId, onlineStatus);
            <?php endforeach; ?>
        }, 5000); // 5000 milliseconds = 5 seconds
    });
</script>
</body>

</html>