<?php
// Set the timezone to UTC to get the server's timezone setting
date_default_timezone_set('UTC');

// Get the server's timezone
$server_timezone = date_default_timezone_get();

// Set the timezone to the server's timezone
date_default_timezone_set($server_timezone);

// Get the current time in the server's timezone
$current_time = date('Y-m-d H:i:s');

// Output the server's timezone and current time
echo "Server Timezone: $server_timezone<br>";
echo "Current Time (Server Timezone): $current_time<br>";

// Set timezone to Asia/Manila for comparison with Philippines time
date_default_timezone_set('Asia/Manila');
$ph_time = date('Y-m-d H:i:s');

// Output the current time in Manila timezone
echo "Current Time (Manila Timezone): $ph_time<br>";
?>
