<?php
header('Content-Type: application/json');

// Database credentials (Replace with your Bluehost MySQL credentials)
$servername = "localhost"; // Replace with your actual server name
$username = "wqxgzpmy_app";       // Replace with your actual username
$password = "R4styL0p3z";       // Replace with your actual password
$dbname = "wqxgzpmy_app";    // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(array("error" => "Connection failed: " . $conn->connect_error)));
}

// Get data from request body
$json_data = file_get_contents("php://input");
$items = json_decode($json_data, true);

if ($items === null && json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(array("error" => "Invalid JSON data")));
}

$synced_items = array();

if ($items != null) {
    foreach ($items as $item) {
        $item_number = $conn->real_escape_string($item['itemNumber']);
        $style_code = $conn->real_escape_string($item['styleCode']);
        $style_name = $conn->real_escape_string($item['styleName']);
        $color = $conn->real_escape_string($item['color']);
        $size = $conn->real_escape_string($item['size']);
        $quantity = intval($item['quantity']);
        $srp = floatval($item['srp']);

        // Check if item_number exists
        $check_sql = "SELECT item_number FROM items WHERE item_number = '$item_number'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            // Update existing item
            $sql = "UPDATE items SET style_code='$style_code', style_name='$style_name', color='$color', size='$size', quantity=$quantity, srp=$srp WHERE item_number='$item_number'";
        } else {
            // Insert new item
            $sql = "INSERT INTO items (item_number, style_code, style_name, color, size, quantity, srp) VALUES ('$item_number', '$style_code', '$style_name', '$color', '$size', $quantity, $srp)";
        }

        if ($conn->query($sql) === TRUE) {
            // Fetch the synced item from the online database to return
            $select_sql = "SELECT * FROM items WHERE item_number = '$item_number'";
            $result = $conn->query($select_sql);
            if ($result->num_rows > 0) {
                $synced_items[] = $result->fetch_assoc();
            }
        } else {
            error_log("Error syncing item " . $item_number . ": " . $conn->error);
        }
    }
}

// Fetch all items from online database to send back
$items_from_db = array();
$sql_select_all = "SELECT * FROM items";
$result_all = $conn->query($sql_select_all);

if ($result_all->num_rows > 0) {
    while ($row = $result_all->fetch_assoc()) {
        $items_from_db[] = $row;
    }
}

$conn->close();
echo json_encode($items_from_db);
?>