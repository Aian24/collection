<?php
include 'config.php';

echo "<h3>Database Tables:</h3>";
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    echo $row[0] . "<br>";
}

echo "<h3>Structure of tenant tables:</h3>";
$tenant_tables = ['apm', 'nova', 'sanko'];
foreach ($tenant_tables as $table) {
    if ($conn->query("DESCRIBE $table")) {
        echo "<strong>$table structure:</strong><br>";
        $desc = $conn->query("DESCRIBE $table");
        while ($row = $desc->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "<br>";
        }
        echo "<br>";
    } else {
        echo "Table $table doesn't exist<br>";
    }
}

echo "<h3>Structure of collection tables:</h3>";
$collection_tables = ['collected', 'collectednova', 'collectedapm'];
foreach ($collection_tables as $table) {
    if ($conn->query("DESCRIBE $table")) {
        echo "<strong>$table structure:</strong><br>";
        $desc = $conn->query("DESCRIBE $table");
        while ($row = $desc->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "<br>";
        }
        echo "<br>";
    } else {
        echo "Table $table doesn't exist<br>";
    }
}

$conn->close();
?> 