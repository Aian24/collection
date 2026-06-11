<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'navadmin.php';
session_start();

// Establish database connection
$servername = "localhost";
$username = "wqxgzpmy_imall";
$password = "R4styL0p3z";
$dbname = "wqxgzpmy_imall";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch data from the contactusform table with formatted date and time
$sql = "SELECT full_name, branch, email, contact_number, subject, message, DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') as formatted_created_at FROM contactusform";
$result = $conn->query($sql);

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/datatables.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets//Responsive/css/responsive.bootsrap.css">
    <link rel="stylesheet" href="modalHistory.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>Contact Us Form Submissions</title>
</head>

<body>
    <center>
        <div class="container">
            <h2>Contact Us Form Submissions</h2>
            <div class="data_table" style="border: 2px solid blue; box-shadow: 5px 10px #888888;">
                <table id="example" class="table  table-hover table-bordered">
                    <thead class="table-primary">
                        <tr>
                            <th>Full Name</th>
                            <th>Branch</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                <td>{$row['full_name']}</td>
                                <td>{$row['branch']}</td>
                                <td><a href='mailto:{$row['email']}'>{$row['email']}</a></td>
                                <td>{$row['contact_number']}</td>
                                <td>{$row['subject']}</td>
                                <td>{$row['message']}</td>
                                <td>{$row['formatted_created_at']}</td>
                                </tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <center>
                <script src="https://code.jquery.com/jquery-3.7.0.js"> </script>
                <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"> </script>
                <script src=" https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
                <script src=" assets/Responsive/css/dataTables.responsive.js"></script>
                <script src="script.js"></script>
                <script src=" assets/js/app.js"></script>

                <script>
                    $(document).ready(function () {
                        // Initialize DataTable
                        $('#contactusTable').DataTable({
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
                    });
                </script>

</body>

</html>