<?php
function updateRunningBal($tableName, $dailyValue)
{
    $servername = "localhost";
    $username = "wqxgzpmy_collection";
    $password = "R4styL0p3z";
    $dbname = "wqxgzpmy_collection";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set default timezone to Manila timezone
    date_default_timezone_set('Asia/Manila');

    // Define the specific time to update (11:59 PM in this example)
    $updateTime = strtotime('23:59:00'); // Adjust this time as needed

    // Get current time in Manila timezone
    $currentTime = strtotime(date('H:i:s'));

    // Check if current time is after update time
    if ($currentTime >= $updateTime) {
        // Set today's date
        $today = date('Y-m-d');

        // Begin transaction
        $conn->begin_transaction();

        // SQL query to update runningbal for space codes with no transactions today
        $sqlUpdate = "UPDATE $tableName AS t
                      LEFT JOIN (
                          SELECT DISTINCT spacecode
                          FROM collected
                          WHERE DATE(collected_date) = '$today'
                      ) AS t2 ON t.spacecode = t2.spacecode
                      SET t.runningbal = t.daily + t.runningbal
                      WHERE t2.spacecode IS NULL";

        if ($conn->query($sqlUpdate) === TRUE) {
            echo "Successfully updated the balance\n";
            $conn->commit(); // Commit transaction
        } else {
            echo "Error updating the balance: " . $conn->error . "\n";
            $conn->rollback(); // Rollback transaction
        }
    } else {
        echo "Not time yet to update the balance\n";
    }

    $conn->close();
}

// Determine $branch based on your application logic
$branch = 'Sanko Market'; // Example branch selection

// Update based on branch
switch ($branch) {
    case 'Sanko Market':
        $tableName = 'sanko';
        break;
    case 'Nova Market':
        $tableName = 'nova';
        break;
    default:
        // Handle invalid branch selection
        exit("Invalid branch selection\n");
}

// Fixed daily value (replace with your actual daily calculation logic if it varies)
$dailyValue = 500; // Example daily value

// Call the function to update runningbal
updateRunningBal($tableName, $dailyValue);
?>
