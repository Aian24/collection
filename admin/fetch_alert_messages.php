<?php
ob_start(); // Start output buffering
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php"); // Redirect to login page if not logged in
    exit();
}
session_write_close(); // Release session lock to prevent deadlocks

date_default_timezone_set('Asia/Manila'); // Set timezone to Asia/Manila

// Fetch transactions created today
$today = date("Y-m-d");
$query = "SELECT * FROM collected WHERE DATE(collected_date) = '$today'";
$result = mysqli_query($conn, $query);

// Initialize alerts counter
$alerts_count = 0;

// Initialize alerts array
$alerts = [];

// Check if query executed successfully
if ($result) {
    // Loop through each transaction
    while ($row = mysqli_fetch_assoc($result)) {
        if ($alerts_count < 5) {
            // Format alert content
            $alert_content = $row['tenantname'] . ' successfully paid! Paid Rent: ₱' . $row['paidrent'] . ' Paid Balance: ₱' . $row['paidbal'];

            // Build alert array
            $alert = [
                'date' => $row['collected_date'],
                'content' => $alert_content
            ];

            // Add alert to alerts array
            $alerts[] = $alert;

            // Increment alerts counter
            $alerts_count++;
        } else {
            break; // Limit reached, exit loop
        }
    }
}

// If no alerts found
if ($alerts_count === 0) {
    $alerts[] = [
        'date' => '',
        'content' => 'No alerts for today'
    ];
}

// Close database connection
mysqli_close($conn);

// Output alerts HTML
foreach ($alerts as $alert) {
    echo '<a class="dropdown-item d-flex align-items-center" href="#">
            <div class="mr-3">
                <div class="icon-circle bg-success">
                    <i class="fas fa-donate text-white"></i>
                </div>
            </div>
            <div>
                <div class="small text-gray-500">' . $alert['date'] . '</div>
                ' . $alert['content'] . '
            </div>
        </a>';
}
?>
