<?php
// Display errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config.php';

echo "<h2>Database Migration: Payment Methods</h2>";
echo "<ul>";

$tables = ['collected', 'collectednova', 'collectedapm'];

foreach ($tables as $table) {
    echo "<li><strong>Processing table: $table</strong>";
    echo "<ul>";
    
    // 1. Check and add payment_method column
    $sql = "SHOW COLUMNS FROM `$table` LIKE 'payment_method'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        $add_col = "ALTER TABLE `$table` ADD `payment_method` VARCHAR(50) NULL DEFAULT NULL";
        if ($conn->query($add_col) === TRUE) {
            echo "<li>Added `payment_method` column successfully.</li>";
        } else {
            echo "<li>Error adding `payment_method`: " . $conn->error . "</li>";
        }
    } else {
        // If it exists, ensure it allows NULL and has no default 'Cash'
        $modify_col = "ALTER TABLE `$table` MODIFY `payment_method` VARCHAR(50) NULL DEFAULT NULL";
        if ($conn->query($modify_col) === TRUE) {
            echo "<li>Modified `payment_method` to allow NULL values.</li>";
        } else {
            echo "<li>Error modifying `payment_method`: " . $conn->error . "</li>";
        }
    }
    
    // 2. Check and add cheque_number column
    $sql = "SHOW COLUMNS FROM `$table` LIKE 'cheque_number'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        $add_col = "ALTER TABLE `$table` ADD `cheque_number` VARCHAR(100) NULL DEFAULT NULL";
        if ($conn->query($add_col) === TRUE) {
            echo "<li>Added `cheque_number` column successfully.</li>";
        } else {
            echo "<li>Error adding `cheque_number`: " . $conn->error . "</li>";
        }
    } else {
        echo "<li>`cheque_number` column already exists.</li>";
    }
    
    // 2.5. Check and add cheque_payee column
    $sql = "SHOW COLUMNS FROM `$table` LIKE 'cheque_payee'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        $add_col = "ALTER TABLE `$table` ADD `cheque_payee` VARCHAR(255) NULL DEFAULT NULL";
        if ($conn->query($add_col) === TRUE) {
            echo "<li>Added `cheque_payee` column successfully.</li>";
        } else {
            echo "<li>Error adding `cheque_payee`: " . $conn->error . "</li>";
        }
    } else {
        echo "<li>`cheque_payee` column already exists.</li>";
    }
    
    // 3. Clean up any accidental 'Cash' defaults for older transactions
    // Since this feature is new, any 'Cash' set in the past by default should be cleared
    $currentDate = date('Y-m-d');
    $cleanup = "UPDATE `$table` SET `payment_method` = NULL WHERE `payment_method` = 'Cash' AND DATE(collected_date) <= '$currentDate'";
    if ($conn->query($cleanup) === TRUE) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            echo "<li>Cleaned up default 'Cash' values for $affected old records.</li>";
        }
    } else {
        echo "<li>Error cleaning up old records: " . $conn->error . "</li>";
    }
    
    echo "</ul></li><br>";
}

echo "</ul>";
echo "<h3>Migration Complete!</h3>";
echo "<p>You can now safely delete this file.</p>";

$conn->close();
?>
