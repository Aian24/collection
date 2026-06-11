
<center>

<?php
$token = $_POST["token"];
$token_hash = hash("sha256", $token);

// Establish a database connection
$mysqli = new mysqli("localhost", "wqxgzpmy_imall", "R4styL0p3z", "wqxgzpmy_imall");

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user === null) {
    die("Token not found");
}

if (strtotime($user["reset_token_expires_at"]) <= time()) {
    die("Token has expired");
}


$newPassword = $_POST["password"];

$sql = "UPDATE users
        SET password = ?,
            reset_token_hash = NULL,
            reset_token_expires_at = NULL
        WHERE id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("si", $newPassword, $user["id"]);

if ($stmt->execute()) {
    echo "Password updated. You can now login.";
} else {
    echo "Error updating password: " . $stmt->error;
}
?>

<br><br>


</center>