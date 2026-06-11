
<center>
<br><br>
<?php
$email = $_POST["email"];

$token = bin2hex(random_bytes(16));

$token_hash = hash("sha256", $token);

$expiry = date("Y-m-d H:i:s", time() + 60 * 30);

// Establish a database connection
$mysqli = new mysqli("localhost", "wqxgzpmy_imall", "R4styL0p3z", "wqxgzpmy_imall");

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$sql = "UPDATE users
        SET reset_token_hash = ?,
            reset_token_expires_at = ?
        WHERE email = ?";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("Error in preparing statement: " . $mysqli->error);
}

$stmt->bind_param("sss", $token_hash, $expiry, $email);

$stmt->execute();

if ($stmt->affected_rows) {

    $mail = require __DIR__ . "/mailer.php";

    $mail->setFrom("noreply@gmail.com");
    $mail->addAddress($email);
    $mail->Subject = "Password Reset";
    $mail->Body = <<<END

    Click <a href="lclopezresources.com/imall/reset-password.php?token=$token">here</a> 
    to reset your password.

    END;

    try {

        $mail->send();

    } catch (Exception $e) {

        echo "Message could not be sent. Mailer error: {$mail->ErrorInfo}";

    }

}

echo "Message sent, please check your inbox.";

$mysqli->close();
?>
<br><br>
<button class="add"> <a href=index.php> Login Agin</a> </button>
<link rel="stylesheet" href="style.css">

</center>