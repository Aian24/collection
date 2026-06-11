<?php
// Set the default timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get the current default timezone
$timezone = date_default_timezone_get();

// Output the timezone
echo "Current default timezone: " . $timezone;
?>
