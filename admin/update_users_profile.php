<?php
include '../config.php';

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM $table LIKE '$column'";
    $result = $conn->query($sql);
    return $result->num_rows > 0;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Array of SQL statements to execute
    $sql_statements = [];

    // Add new profile columns if they don't exist
    $new_columns = [
        'profile_photo' => 'VARCHAR(255) DEFAULT NULL',
        'profile_photo_updated_at' => 'TIMESTAMP NULL DEFAULT NULL',
        'phone_number' => 'VARCHAR(20) DEFAULT NULL',
        'address' => 'TEXT DEFAULT NULL',
        'bio' => 'TEXT DEFAULT NULL',
        'position' => 'VARCHAR(100) DEFAULT NULL',
        'hire_date' => 'DATE DEFAULT NULL',
        'last_login' => 'TIMESTAMP NULL DEFAULT NULL',
        'status' => "ENUM('active', 'inactive') DEFAULT 'active'",
        'theme_preference' => "ENUM('light', 'dark') DEFAULT 'light'",
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];

    foreach ($new_columns as $column => $definition) {
        if (!columnExists($conn, 'users', $column)) {
            $sql_statements[] = "ALTER TABLE users ADD COLUMN $column $definition";
        }
    }

    // Modify existing columns to ensure proper types and constraints
    $sql_statements[] = "ALTER TABLE users
        MODIFY id INT AUTO_INCREMENT PRIMARY KEY,
        MODIFY user_type VARCHAR(50) NOT NULL,
        MODIFY username VARCHAR(50) NOT NULL UNIQUE,
        MODIFY fname VARCHAR(100) NOT NULL,
        MODIFY lname VARCHAR(100) NOT NULL,
        MODIFY email VARCHAR(255) NOT NULL UNIQUE,
        MODIFY branch VARCHAR(100) NOT NULL,
        MODIFY password VARCHAR(255) NOT NULL";

    // Add indexes for better performance
    $indexes = [
        'ADD INDEX idx_username (username)',
        'ADD INDEX idx_email (email)',
        'ADD INDEX idx_user_type (user_type)',
        'ADD INDEX idx_branch (branch)',
        'ADD INDEX idx_status (status)',
        'ADD INDEX idx_created_at (created_at)'
    ];

    foreach ($indexes as $index) {
        // Check if index exists before adding
        $sql_statements[] = "ALTER TABLE users $index";
    }

    // Execute all SQL statements
    foreach ($sql_statements as $sql) {
        try {
            $conn->query($sql);
        } catch (mysqli_sql_exception $e) {
            // Skip if index already exists
            if (!strpos($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
        }
    }

    // Update existing admin user with default values
    $update_admin = "UPDATE users SET 
        position = 'System Administrator',
        status = 'active',
        theme_preference = 'light',
        created_at = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
        WHERE username = 'admin' AND user_type = 'admin'";
    
    $conn->query($update_admin);

    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/profile_photos';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception("Failed to create uploads directory");
        }
        
        // Create .htaccess to protect against direct file access
        $htaccess_content = "Options -Indexes\nDeny from all\n\n# Allow only image files\n<FilesMatch \"\.(jpg|jpeg|png|gif)$\">\nOrder Allow,Deny\nAllow from all\n</FilesMatch>";
        if (!file_put_contents($upload_dir . '/.htaccess', $htaccess_content)) {
            throw new Exception("Failed to create .htaccess file");
        }
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database and file system updated successfully',
        'details' => [
            'sql_statements_executed' => count($sql_statements),
            'upload_dir_created' => true,
            'htaccess_created' => true,
            'admin_updated' => true
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'details' => [
            'error_code' => $e->getCode(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]
    ]);
}

// Close connection
$conn->close();
?> 