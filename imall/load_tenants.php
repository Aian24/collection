<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();


// Get the selected location from the AJAX request
$location = $_POST['location'];

// Customize the table name based on the selected location
$tableName = '';
if ($location === 'imallantipolotenants') {
    $tableName = 'imallantipolotenants';
} elseif ($location === 'imallcanlubangtenants') {
    $tableName = 'imallcanlubangtenants';
} elseif ($location === 'imallcamarintenants') {
    $tableName = 'imallcamarintenants';
} elseif ($location === 'imallfamytenats') {
    $tableName = 'imallfamytenats';
} elseif ($location === 'cogeotownplazatenants') {
    $tableName = 'cogeotownplazatenants';
} elseif ($location === 'antipolomarkettenats') {
    $tableName = 'antipolomarkettenats';
} elseif ($location === 'apmcommercialtenats') {
    $tableName = 'apmcommercialtenats';
} elseif ($location === 'citicentretenats') {
    $tableName = 'citicentretenats';
}else {
    // Default to a table name or handle it in another way based on your logic
    $tableName = 'default_table_name';
}

// Fetch and display the tenant list from the corresponding table
$sql = "SELECT * FROM `$tableName` ORDER BY `contract` DESC";
$result = $conn->query($sql);
// Build the HTML for the tenant list
$html = '';
while ($row = $result->fetch_assoc()) {
    $html .= '<tr class="active-row" data-contract="' . $row['contract'] . '">';
    $html .= '<td>' . $row['company'] . '</td>';
    $html .= '<td>' . $row['contract'] . '</td>';
    $html .= '<td>' . $row['stall'] . '</td>';
    $html .= '<td>' . $row['trade'] . '</td>';
    $html .= '<td>
                <form action="edit_tenant.php" method="post" style="display: inline;">
                    <input type="hidden" name="contract" value="' . $row['contract'] . '">
                    <input type="hidden" name="branch" value="' . $location . '">
                    <button type="submit" class="btn btn-primary">Edit</button>
                </form>
              </td>';
    $html .= '<td>
                <form action="delete_tenant.php" method="post" onsubmit="return confirm(\'Are you sure you want to delete?\');" style="display: inline;">
                    <input type="hidden" name="contract" value="' . $row['contract'] . '">
                    <input type="hidden" name="branch" value="' . $location . '">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
              </td>';
    $html .= '</tr>';
}

// Send the HTML response
echo $html;
?>
