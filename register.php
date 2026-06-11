<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../src/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
    <title>Register</title>
</head>

<body>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-700 to-teal-300">
        <div class="w-full sm:w-10/12 lg:w-8/12 xl:w-6/12 bg-white shadow-lg overflow-hidden">
            <div class="lg:flex">
                <div class="w-full lg:w-1/2 bg-no-repeat bg-cover bg-center"
                    style="background-image: url('images/register.jpg'); min-height: 400px;">
                </div>
                <div class="w-full lg:w-1/2 py-8 lg:py-16 px-4 sm:px-8">
                    <h2 class="text-3xl mb-4">Register</h2>
                    <p class="mb-4">
                        Create your account. It's free and only takes a minute
                    </p>
                    <?php
                    @include 'config.php';

                    if ($_SERVER["REQUEST_METHOD"] == "POST") {
                        // Collect form data
                        $fname = $_POST['fname'];
                        $lname = $_POST['lname'];
                        $email = $_POST['email'];
                        $branch = $_POST['branch'];
                        $password = $_POST['password'];
                        $length = $_POST['length'];
                        $width = $_POST['width'];
                        $date = $_POST['date'];
                        $rent = $_POST['rent'];
                        $type = $_POST['type'];
                        $spacecode = $_POST['spacecode'];
                        $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : 'normal_user';

                        // Check connection
                        if ($conn->connect_error) {
                            die("Connection failed: " . $conn->connect_error);
                        }

                        // Check if user already exists
                        $checkUserQuery = "SELECT * FROM users WHERE email='$email'";
                        $result = $conn->query($checkUserQuery);

                        if ($result->num_rows > 0) {
                            echo "<script>alert('User already exists! Please use a different email.'); window.location.href = 'register.php';</script>";
                            exit;
                        }

                        // Insert data into the database
                        $sql = "INSERT INTO users (fname, lname, email,branch,length,width,date,rent,type, password,spacecode, user_type) VALUES ('$fname', '$lname', '$email','$branch','$length','$width','$date','$rent','$type', '$password','$spacecode','normal_user')";

                        if ($conn->query($sql) === TRUE) {
                            echo "<script>alert('Registration successful!'); window.location.href = 'index.php';</script>";
                        } else {
                            echo "Error: " . $sql . "<br>" . $conn->error;
                        }

                        // Close the connection
                        $conn->close();
                    }
                    ?>

                    <form method="post" action="#">
                        <!-- Add a hidden field for user type -->
                        <input required type="hidden" name="user_type" value="normal_user">

                        <div class="grid grid-cols-2 gap-5">
                            <input required type="text" name="fname" placeholder="Firstname"
                                class="border border-gray-400 py-1 px-2">
                            <input type="text" name="lname" placeholder="Surname"
                                class="border border-gray-400 py-1 px-2">
                        </div>
                        <div class="mt-5">
                            <input required type="text" name="email" placeholder="Email"
                                class="border border-gray-400 py-1 px-2 w-full">
                        </div>

                        <div class="mt-5">
                            <select required name="branch" class="border border-gray-400 py-1 px-2 w-full">
                                <option value="-1">- Select Branch -</option>
                                <option value="Sanko Market">Sanko Market</option>
                                <option value="Nova Marktet">Nova Marktet</option>
                                <option value="Ambulant">Ambulant</option>
                                <!-- Add more options if needed -->
                            </select>
                        
                        <div class="mt-5">
                            <input required type="text" name="spacecode" placeholder="Space Code"
                                class="border border-gray-400 py-1 px-2 w-full">
                        </div>
                        <div class="mt-5">
                            <input required type="text" name="length" placeholder="Size(Length)"
                                class="border border-gray-400 py-1 px-2 w-full">
                        </div>
                        <div class="mt-5">
                            <input required type="text" name="width" placeholder="Size(Width)"
                                class="border border-gray-400 py-1 px-2 w-full">
                        </div>
                        <div class="mt-5">
                            <label for="date" class="block text-gray-500">Date Started</label>
                            <div class="relative">
                                <input required type="date" name="date" placeholder=" "
                                    class="border border-gray-400 py-1 px-2 w-full">
                            </div>
                        </div>

                        <div class="mt-5">
                            <input required type="text" name="rent" placeholder="Daily Rent"
                                class="border border-gray-400 py-1 px-2 w-full">
                        </div>
                        <div class="mt-5">
                            <select required name="type" class="border border-gray-400 py-1 px-2 w-full">
                                <option value="-1">- Select Collection Type -</option>
                                <option value="daily">Daily</option>
                                <option value="monthly">Monthly</option>
                                <!-- Add more options if needed -->
                            </select>
                        </div>
                        <div class="mt-5 password-container">
                            <input required type="password" name="password" id="password" placeholder="Password"
                                class="border border-gray-400 py-1 px-2 w-full">
                            <!-- Add close and open eyes icons -->
                            <span class="password-toggle" onclick="togglePassword('password')"><i
                                    class="fas fa-eye-slash"></i></span>
                        </div>
                        <div class="mt-2 password-container">
                            <input required type="password" name="confirm_password" id="confirm_password"
                                placeholder="Confirm Password" class="border border-gray-400 py-1 px-2 w-full">
                            <!-- Add close and open eyes icons -->
                            <span class="password-toggle" onclick="togglePassword('confirm_password')"><i
                                    class="fas fa-eye-slash"></i></span>
                        </div>
                        <span class="block text-gray-400 mt-1">Password should be a combination of letters and
                            numbers</span>
                        <div id="password_strength" class="mt-2"></div>
                        <div class="mt-2" id="password_match_error" style="color: red;"></div>
                        <div class="mt-5">
                            <button type="submit" class="w-full bg-purple-500 py-3 text-center text-white"
                                onclick="return validateForm()">Register Now</button>
                        </div>

                    </form>
                    <div class="mt-5">
                        <span class="text-center font-bold"> Already have an account? <a href="index.php"
                                class="text-blue-600">Login</a></span>
                    </div>
                </div>
            </div>
        </div>

        <script src=function.js> </script>
    
</body>

</html>