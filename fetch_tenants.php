<?php
include 'config.php';

// Get the selected branch from the GET request
$selectedBranch = isset($_GET['branch']) ? $_GET['branch'] : '';

// Define the table based on the branch filter
if ($selectedBranch == 'Sanko Market') {
    $table = 'collected';
} elseif ($selectedBranch == 'Nova Market') {
    $table = 'collectednova';
} elseif ($selectedBranch == 'APM') {
    $table = 'collectedapm';
} else {
    $table = ''; // If no branch is selected, return no tenants
}

// If a valid branch is selected, fetch tenants
if ($table) {
    // Fetch tenant names from the database based on the selected branch
    $sql = "SELECT DISTINCT tenantname FROM $table";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Output tenants as options
        while ($row = $result->fetch_assoc()) {
            echo "<option value='" . $row['tenantname'] . "'>" . $row['tenantname'] . "</option>";
        }
    } else {
        echo "<option value=''>No tenants found</option>";
    }
} else {
    echo "<option value=''>Select a branch first</option>";
}

$conn->close();
?>