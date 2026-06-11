<?php
require_once(__DIR__ . '/../config.php');

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

    // Check if profile_photo column exists
    if (!columnExists($conn, 'users', 'profile_photo')) {
        $sql_statements[] = "ALTER TABLE users 
            ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER password";
    }

    // Check if password column needs to be modified
    $password_check = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'password'");
    $password_column = $password_check->fetch_assoc();
    
    if ($password_column && $password_column['Type'] !== 'VARCHAR(255)') {
        $sql_statements[] = "ALTER TABLE users 
            MODIFY COLUMN password VARCHAR(255) NOT NULL";
    }

    // Execute all SQL statements
    foreach ($sql_statements as $sql) {
        if ($conn->query($sql) !== TRUE) {
            throw new Exception("Error executing SQL: " . $conn->error);
        }
    }

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
            'htaccess_created' => true
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