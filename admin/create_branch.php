<?php
ob_start();
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branchName = strtolower($_POST['branchName']);
    
    // Validate branch name (only lowercase letters)
    if (!preg_match('/^[a-z]+$/', $branchName)) {
        $_SESSION['errorMessage'] = "Branch name must contain only lowercase letters.";
        header("Location: tenants.php");
        exit();
    }

    // Check if branch already exists
    $checkQuery = "SHOW TABLES LIKE '$branchName'";
    $result = $conn->query($checkQuery);
    
    if ($result->num_rows > 0) {
        $_SESSION['errorMessage'] = "Branch '$branchName' already exists.";
        header("Location: tenants.php");
        exit();
    }

    // Create the new branch table
    $createTableQuery = "CREATE TABLE $branchName (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        tenantname VARCHAR(255) NOT NULL,
        tenantcode VARCHAR(50) NOT NULL,
        spacecode VARCHAR(50) NOT NULL,
        daily DECIMAL(10,2) NOT NULL,
        rentbal DECIMAL(10,2) NOT NULL,
        runningbal DECIMAL(10,2) NOT NULL,
        started_date DATE NOT NULL
    )";

    if (!$conn->query($createTableQuery)) {
        $_SESSION['errorMessage'] = "Error creating branch table: " . $conn->error;
        header("Location: tenants.php");
        exit();
    }

    // Create the collection table for the new branch
    $collectionTableName = "collected" . $branchName;
    $createCollectionTableQuery = "CREATE TABLE $collectionTableName (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch VARCHAR(50),
        collected_date DATETIME,
        transaction_number VARCHAR(50),
        spacecode VARCHAR(50),
        collector VARCHAR(50),
        tenantcode VARCHAR(50),
        tenantname VARCHAR(255),
        paidrent DECIMAL(10,2),
        paidbal DECIMAL(10,2),
        rentbal DECIMAL(10,2),
        runningbal DECIMAL(10,2),
        newrentbalance DECIMAL(10,2),
        newbalance DECIMAL(10,2),
        charges TEXT,
        total DECIMAL(10,2)
    )";

    if (!$conn->query($createCollectionTableQuery)) {
        // If collection table creation fails, try to rollback the branch table
        $conn->query("DROP TABLE IF EXISTS $branchName");
        $_SESSION['errorMessage'] = "Error creating collection table: " . $conn->error;
        header("Location: tenants.php");
        exit();
    }

    // Handle CSV file upload and import
    if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] == 0) {
        $csvFile = $_FILES['csvFile']['tmp_name'];
        
        // Set default date to today
        $today = date('Y-m-d');
        
        // Read CSV file
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Validate that we have at least the required columns (excluding started_date)
                if (count($data) < 6) {
                    $_SESSION['errorMessage'] = "CSV file must have at least 6 columns (tenantname, tenantcode, spacecode, daily, rentbal, runningbal).";
                    header("Location: tenants.php");
                    exit();
                }

                // Use provided date if exists, otherwise use today's date
                $started_date = isset($data[6]) && !empty($data[6]) ? $data[6] : $today;

                // Prepare and execute insert statement
                $insertQuery = "INSERT INTO $branchName (tenantname, tenantcode, spacecode, daily, rentbal, runningbal, started_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("sssddds", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $started_date);
                
                if (!$stmt->execute()) {
                    $_SESSION['errorMessage'] = "Error importing data: " . $stmt->error;
                    header("Location: tenants.php");
                    exit();
                }
                $stmt->close();
            }
            fclose($handle);
            
            // Update the allowed tables array
            $updateQuery = "UPDATE system_settings SET value = CONCAT(value, ',$branchName') WHERE setting = 'allowed_tables'";
            $conn->query($updateQuery);

            $_SESSION['successMessage'] = "Branch '$branchName' created successfully with its collection table and data imported. Please update the collection display files and transaction processing as noted in the instructions.";
        } else {
            $_SESSION['errorMessage'] = "Error reading CSV file.";
        }
    } else {
        $_SESSION['errorMessage'] = "Error uploading CSV file.";
    }
} else {
    $_SESSION['errorMessage'] = "Invalid request method.";
}

header("Location: tenants.php");
exit();
?> 