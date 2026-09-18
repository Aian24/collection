<?php
header('Content-Type: application/json');

date_default_timezone_set('Asia/Manila');

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$isCli = (php_sapi_name() === 'cli');
$hostHeader = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocal = in_array($hostHeader, ['localhost', '127.0.0.1', '::1'])
           || (isset($_SERVER['DOCUMENT_ROOT']) && (strpos($_SERVER['DOCUMENT_ROOT'], 'xampp') !== false || strpos($_SERVER['DOCUMENT_ROOT'], 'wamp') !== false))
           || ($isCli && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

if ($isLocal) {
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "app";
    $fallbackUser = "wqxgzpmy_app";
    $fallbackPass = "R4styL0p3z";
    $fallbackName = "wqxgzpmy_app";
} else {
    $servername = "localhost";
    $username   = "wqxgzpmy_app";
    $password   = "R4styL0p3z";
    $dbname     = "wqxgzpmy_app";
    $fallbackUser = "root";
    $fallbackPass = "";
    $fallbackName = "app";
}

$conn = @new mysqli($servername, $username, $password, $dbname);

if (!$conn || $conn->connect_error) {
    $conn = @new mysqli($servername, $fallbackUser, $fallbackPass, $fallbackName);
}

if (!$conn || $conn->connect_error) {
    die(json_encode(array("error" => "Connection failed: " . ($conn ? $conn->connect_error : "Server busy"))));
}

@$conn->set_charset("utf8mb4");
register_shutdown_function(function() use (&$conn) {
    if ($conn instanceof mysqli && @$conn->ping()) {
        @$conn->close();
    }
});

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
$sql_select_all = "SELECT * FROM items";
$result_all = $conn->query($sql_select_all);

echo "[";
if ($result_all && $result_all->num_rows > 0) {
    $first = true;
    while ($row = $result_all->fetch_assoc()) {
        if (!$first) {
            echo ",";
        }
        echo json_encode($row);
        $first = false;
    }
}
echo "]";

$conn->close();
?>