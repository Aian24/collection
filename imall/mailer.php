<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/vendor/autoload.php";

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->SMTPAuth = true;

    $mail->Host = "smtp.gmail.com";
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Username = "imalltest592@gmail.com";
    $mail->Password = "uuqlhfzsmtkqnaor"; // Use the App Password generated in your Google Account

    $mail->isHtml(true);

    // Rest of your email configuration here...

    return $mail;
} catch (Exception $e) {
    echo "Mailer Error: " . $e->getMessage();
}


?>