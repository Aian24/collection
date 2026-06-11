<?php
include 'config.php';

// Get the selected branch and tenant from the GET request
$selectedBranch = isset($_GET['branch']) ? $_GET['branch'] : '';
$selectedTenant = isset($_GET['tenant']) ? $_GET['tenant'] : '';

// Define the table based on the branch filter
if ($selectedBranch == 'Sanko Market') {
    $table = 'collected';
} elseif ($selectedBranch == 'Nova Market') {
    $table = 'collectednova';
} elseif ($selectedBranch == 'APM') {
    $table = 'collectedapm';
} else {
    $table = ''; // If no branch is selected, return no spacecodes
}

// If a valid branch and tenant are selected, fetch spacecodes
if ($table && $selectedTenant) {
    // Fetch spacecodes from the database based on the selected branch and tenant
    $sql = "SELECT DISTINCT spacecode FROM $table WHERE tenantname = '$selectedTenant'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Output spacecodes as options
        while ($row = $result->fetch_assoc()) {
            echo "<option value='" . $row['spacecode'] . "'>" . $row['spacecode'] . "</option>";
        }
    } else {
        echo "<option value=''>No spacecodes found</option>";
    }
} else {
    echo "<option value=''>Select a branch and tenant first</option>";
}

$conn->close();
?>