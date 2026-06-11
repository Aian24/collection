<?php
include 'config.php';

if (isset($_POST['search']) && isset($_POST['branch'])) {
    $search = $_POST['search'];
    $branch = $_POST['branch'];
    
    $table = ''; // Initialize variable to hold the table name
    
    // Determine the table name based on the selected branch
    switch ($branch) {
        case 'Nova Market':
            $table = 'nova';
            break;
        case 'Sanko Market':
            $table = 'sanko';
            break;
        case 'APM':
            $table = 'apm'; // Add case for APM
            break;
        // Add more cases if needed
    }
    
    // If a valid table name is determined
    if ($table !== '') {
        // Modify the SQL query to select from the appropriate table
        $sql = "SELECT spacecode FROM $table WHERE spacecode LIKE '%$search%' LIMIT 5";
        
        $result = $conn->query($sql);
        $suggestions = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row["spacecode"];
            }
        }
        echo json_encode($suggestions);
    } else {
        // Invalid branch selected
        echo json_encode([]); // Return empty array
    }
}
?>
