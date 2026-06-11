<?php

// Include your database connection file
ob_start(); // Start output buffering
session_start();
// Check if the user is logged in
if (!isset($_SESSION['user_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}
// Establish database connection
$servername = "localhost";
$username = "wqxgzpmy_imall";
$password = "R4styL0p3z";
$dbname = "wqxgzpmy_imall";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$data = json_decode(file_get_contents("php://input"), true);

// Prepare and execute SQL query to insert data into the contactusform table
$stmt = $conn->prepare("INSERT INTO contactusform (full_name, branch,email, contact_number, subject, message, created_at) VALUES (?, ?, ?, ?, ?,?, NOW())");
$stmt->bind_param("ssssss", $data['full_name'], $data['branch'], $data['email'], $data['contact_number'], $data['subject'], $data['message']);

if ($stmt->execute()) {
    echo "Form data inserted successfully";
}


// Close the database connection
$stmt->close();
$conn->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Contact Us Form</title>
</head>

<body>


    <!-- nav -->
    <nav class="topnav" id="myTopnav">
        <a href="" style="font-weight: 600; font-size: 26px; padding: 5px; margin-left: 10px;">IMALL</a>
        <?php if (isset($_SESSION['user_name'])): ?>
            <a href="index.php?logout" style="float: right;">Log Out</a>
        <?php endif; ?>
        <a href="contactus.php" style="float: right;">Contact Us </a>
        <a href="CreateAR.php" style="float: right;">Create AR </a>
        <a href="user_page.php" style="float: right;">Transaction Report</a>
        <a href="javascript:void(0);" class="icon" onclick="myFunction()">
            <i class="fa fa-bars"></i>
        </a>
    </nav>

    <center><br><br>
        <h4>Feel free to reach out to us by filling out the contact form below. We look forward to hearing from you.</h4><br><br>
        <div class="wrapper centered">
            <article class="letter">
                <div class="side">
                    <h1>Contact us</h1>
                    <p>
                        <textarea id="message" name="message" placeholder="Your message" required></textarea>
                    </p>
                </div>
                <div class="side">
                    <p>
                        <input class="contact" type="text" id="full_name" name="full_name" placeholder="Your name" required>
                    </p>
                    <p>
                        <input class="contact"  type="email" id="email" name="email" placeholder="Your email" required>
                    </p>

                    <p>
                        <input class="contact"  type="text" id="branch" name="branch" placeholder="Your branch" required>
                    </p>
                    <p>
                        <input class="contact"  type="tel" id="contact_number" name="contact_number" placeholder="Your contact number"
                            required>
                    </p>
                    <p>
                        <input class="contact"  type="text" id="subject" name="subject" placeholder="Subject" required>
                    </p>
                    <p>
                        <button id="sendLetter">Send</button>
                    </p>
                </div>
            </article>
            <div class="envelope front"></div>
            <div class="envelope back"></div>
        </div>
        <p class="result-message centered">Thank you for your message</p>
    </center>
</body>

</html>
<script>
    function addClass() {
        document.body.classList.add("sent");
    }

    document.getElementById("sendLetter").addEventListener("click", function () {
        if (validateForm()) {
            addClass();
            sendData();
        }
    });

    function validateForm() {
        var fullName = document.getElementById("full_name").value;
        var email = document.getElementById("email").value;
        var branch = document.getElementById("branch").value;
        var contactNumber = document.getElementById("contact_number").value;
        var subject = document.getElementById("subject").value;

        if (fullName === "" || email === "" || branch === "" || contactNumber === "" || subject === "") {
            alert("All fields are required");
            return false;
        }

        return true;
    }

    function sendData() {
        // Collect form data
        var formData = {
            full_name: document.getElementById("full_name").value,
            email: document.getElementById("email").value,
            branch: document.getElementById("branch").value,
            contact_number: document.getElementById("contact_number").value,
            subject: document.getElementById("subject").value,
            message: document.getElementById("message").value
        };

        // Make an AJAX request to your PHP file
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "contactus.php", true);
        xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                console.log(xhr.responseText); // You can log the response for debugging
            }
        };
        xhr.send(JSON.stringify(formData));
    }
</script>
