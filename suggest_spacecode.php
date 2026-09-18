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
        // Modify the SQL query to select from the appropriate table using prepared statements
        $stmt = $conn->prepare("SELECT spacecode FROM $table WHERE spacecode LIKE ? LIMIT 5");
        $searchParam = "%" . $search . "%";
        $stmt->bind_param("s", $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $suggestions = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row["spacecode"];
            }
        }
        $stmt->close();
        echo json_encode($suggestions);
    } else {
        // Invalid branch selected
        echo json_encode([]); // Return empty array
    }
}
$conn->close();
?>
