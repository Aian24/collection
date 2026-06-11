<?php
// Include your database connection file
ob_start(); // Start output buffering
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    // Redirect to login page if not logged in
    header("Location: ../index.php");
    exit();
}
session_write_close(); // Release session lock to prevent deadlocks

date_default_timezone_set('Asia/Manila'); // timezone


// Fetch created transactions within the day
$today = date("Y-m-d");
$query = "SELECT * FROM collected WHERE DATE(collected_date) = '$today'";
$result = mysqli_query($conn, $query);

// Check if query executed successfully
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Initialize alert HTML
$alerts_html = '';

// Check if there are created transactions
if (mysqli_num_rows($result) > 0) {
    // Loop through each transaction
    while ($row = mysqli_fetch_assoc($result)) {
        // Format the alert content based on your requirements
        $alert_content = $row['tenantname'] . ' successfully paid! Paid Rent: ₱' . $row['paidrent'] . ' Paid Balance: ₱' . $row['paidbal'];

        // Append the alert HTML
        $alerts_html .= '<a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="mr-3">
                                <div class="icon-circle bg-success">
                                    <i class="fas fa-donate text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">' . $row['collected_date'] . '</div>
                                ' . $alert_content . '
                            </div>
                        </a>';
    }
} else {
    // If no transactions created within the day
    $alerts_html = '<a class="dropdown-item text-center small text-gray-500" href="#">No alerts for today</a>';
}

// Close database connection
mysqli_close($conn);

// Output the alerts HTML
echo $alerts_html;
?>
