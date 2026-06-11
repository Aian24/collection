<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Start output buffering
include 'config.php'; // Make sure config.php exists and has your database connection
session_start();

$error_message = ""; // Initialize an empty error message

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assuming you have validated and sanitized the input data
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    // Query to fetch user data based on username and password
    // Using prepared statements to prevent SQL injection
    $query = "SELECT id, lname, user_type, branch, profile_photo FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($query);

    // Check if the prepare was successful
    if (!$stmt) {
        // Log the error instead of displaying it directly in production
        error_log("Prepare failed: " . $conn->error);
        $error_message = "An internal error occurred. Please try again.";
    } else {
        // Bind parameters and execute the statement
        $stmt->bind_param("ss", $username, $password);
        $result = $stmt->execute();

        // Check if the execution was successful
        if (!$result) {
            // Log the error instead of displaying it directly in production
            error_log("Execution failed: " . $stmt->error);
             $error_message = "An internal error occurred. Please try again.";
        } else {
            // Bind results
            $stmt->bind_result($id, $lname, $user_type, $branch, $profile_photo);

            if ($stmt->fetch()) {
                $_SESSION["id"] = $id;
                $_SESSION["lname"] = $lname;
                $_SESSION["username"] = $username;
                $_SESSION["branch"] = $branch;
                $_SESSION["user_type"] = $user_type;
                $_SESSION["profile_photo"] = $profile_photo;

                // Close the statement
                $stmt->close();
                // Close the connection before redirecting
                $conn->close();


                // Redirect based on user type
                if ($user_type === "normal_user") {
                    header("Location: user.php");
                    exit();
                } elseif ($user_type === "admin") {
                    header("Location: admin/admin.php");
                    exit();
                } elseif ($user_type === "admin_viewer") {
                    header("Location: admin/adminacc.php");
                    exit();
                } elseif ($user_type === "superuser") {
                    header("Location: superuser.php");
                    exit();
                } elseif ($user_type === "adminapm") {
                    header("Location: admin/collectionapm.php");
                    exit();
                } elseif ($user_type === "collection_viewer") {
                    header("Location: admin/collectiononly.php");
                    exit();
                } else {
                     // Handle unknown user types or set a default redirect
                     header("Location: default_dashboard.php");
                     exit();
                }

            } else {
                $error_message = "Invalid username or password. Please try again.";
            }
             // Close the statement if fetch didn't succeed
            $stmt->close();
        }
    }

    // Close the connection if it wasn't closed during a successful login redirect
    if ($conn) {
        $conn->close();
    }
}
// Ensure output buffering is cleaned and turned off at the end of the script
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Login to POS Daily Collections Workspace">
    <link rel="shortcut icon" type="image/x-icon" href="images/lc.png" />
    
    <!-- Preload Critical Assets for PSI Optimization -->
    <link rel="preload" href="images/header_opt.jpg" as="image" />
    <link rel="preload" href="images/lc.png" as="image" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Login Setup Workspace</title>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Modern floating labels */
        .input-box {
            position: relative;
        }

        .input-box input {
            width: 100%;
            padding: 1.25rem 1rem 0.5rem 2.8rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            outline: none;
            transition: all 0.3s ease;
            background-color: #f9fafb;
            font-size: 1rem;
            color: #111827;
        }

        .input-box input:focus {
            background-color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .input-box label {
            position: absolute;
            left: 2.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
            transition: all 0.2s ease;
            font-size: 1rem;
        }

        .input-box input:focus ~ label,
        .input-box input:not(:placeholder-shown) ~ label {
            top: 0.8rem;
            font-size: 0.75rem;
            color: #3b82f6;
            font-weight: 500;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: color 0.3s;
        }

        .input-box input:focus ~ .input-icon {
            color: #3b82f6;
        }

        .bg-split-image {
            background: linear-gradient(135deg, rgba(30,58,138,0.85) 0%, rgba(37,99,235,0.7) 100%), url('images/header_opt.jpg');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>

<body class="font-sans text-gray-900 antialiased bg-gray-50 flex flex-col lg:flex-row-reverse h-screen overflow-hidden">

    <!-- Right Side: Image / Branding (Hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 bg-split-image relative items-center justify-center p-12">
        <div class="absolute inset-0 bg-blue-900/40 backdrop-blur-[2px]"></div>

        <!-- Stylish Vertical Wave Separator bridging into the Left Panel -->
        <div class="absolute left-0 top-0 h-full w-[120px] pointer-events-none -translate-x-[2px] -scale-x-100 z-20 hidden lg:block">
            <svg class="h-full w-full" preserveAspectRatio="none" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M100,0 L100,100 L10,100 C 60,75 -10,25 10,0 Z" fill="rgba(255, 255, 255, 0.15)" />
                <path d="M100,0 L100,100 L30,100 C 80,75 10,25 30,0 Z" fill="rgba(255, 255, 255, 0.35)" />
                <path d="M100,0 L100,100 L50,100 C 100,75 30,25 50,0 Z" fill="#ffffff" />
            </svg>
        </div>
        
        <div class="relative z-10 w-full max-w-xl text-white text-center flex flex-col items-center">
            
            <h1 class="text-5xl font-bold mb-6 tracking-tight leading-tight drop-shadow-lg">
                Empowering<br/>Your Workflow.
            </h1>
            <p class="text-blue-100 text-lg leading-relaxed max-w-md mb-10 drop-shadow">
                POS for Daily collections of tenants. Seamlessly manage operations, track payments, and optimize your overall efficiency.
            </p>
            
            <!-- Added feature cards for more visual content -->
            <div class="grid grid-cols-2 gap-4 w-full max-w-md">
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 shadow-[0_8px_32px_rgba(0,0,0,0.15)] transform transition-transform hover:-translate-y-1">
                    <i class="fas fa-bolt text-3xl text-blue-300 mb-3 drop-shadow"></i>
                    <h3 class="font-semibold text-white text-lg">Fast & Secure</h3>
                    <p class="text-xs text-blue-100/80 mt-1">Processed instantly.</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 shadow-[0_8px_32px_rgba(0,0,0,0.15)] transform transition-transform hover:-translate-y-1">
                    <i class="fas fa-chart-line text-3xl text-blue-300 mb-3 drop-shadow"></i>
                    <h3 class="font-semibold text-white text-lg">Real-time Data</h3>
                    <p class="text-xs text-blue-100/80 mt-1">Accurate tracking.</p>
                </div>
            </div>

        </div>
        
        <!-- Decorative horizontal waves at bottom -->
        <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none z-0 pointer-events-none">
            <svg class="relative block w-full h-24 lg:h-32" preserveAspectRatio="none" viewBox="0 0 1440 120" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,0L48,16C96,32,192,64,288,69.3C384,75,480,53,576,42.7C672,32,768,32,864,48C960,64,1056,96,1152,90.7C1248,85,1344,43,1392,21.3L1440,0L1440,120L1392,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z" fill="rgba(255,255,255,0.05)"></path>
                <path d="M0,42.7L48,53.3C96,64,192,85,288,85.3C384,85,480,64,576,48C672,32,768,21,864,26.7C960,32,1056,53,1152,64C1248,75,1344,75,1392,74.7L1440,74.7L1440,120L1392,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z" fill="rgba(255,255,255,0.1)"></path>
            </svg>
        </div>
    </div>

    <!-- Left Side: Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-white relative">
        <!-- Mobile Background Pattern (Hidden on Desktop) -->
        <div class="absolute inset-0 z-0 lg:hidden opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#2563eb 1px, transparent 1px); background-size: 20px 20px;"></div>

        <div class="w-full max-w-md z-10">
            
            <!-- Mobile Logo -->
            <div class="lg:hidden text-center mb-8">
                <img src="images/lc.png" alt="Logo" fetchpriority="high" loading="eager" class="h-32 w-auto object-contain mx-auto mb-4 drop-shadow-sm">
                <h2 class="text-2xl font-bold text-gray-900">Welcome Back</h2>
            </div>

            <!-- Desktop Header -->
            <div class="hidden lg:block mb-10 text-center">
                <img src="images/lc.png" alt="Logo" fetchpriority="high" loading="eager" class="h-24 w-auto object-contain mx-auto mb-6 drop-shadow-sm">
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Sign in to your account</h2>
                <p class="text-gray-500">Please enter your credentials below</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-8 flex items-start shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle mt-0.5 mr-3 text-red-500"></i>
                    <p class="text-sm font-medium"><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
                
                <div class="input-box">
                    <input type="text" name="username" id="username" required autocomplete="username" placeholder=" ">
                    <i class="fas fa-user input-icon"></i>
                    <label for="username">Username</label>
                </div>

                <div class="input-box">
                    <input type="password" name="password" id="password" required autocomplete="current-password" placeholder=" ">
                    <i class="fas fa-lock input-icon"></i>
                    <label for="password">Password</label>
                    <button type="button" onclick="togglePassword('password')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary-600 focus:outline-none transition-colors">
                        <i class="fas fa-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="flex items-center justify-between mt-2 mb-6">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded cursor-pointer transition-colors">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-600 cursor-pointer select-none">
                            Remember me
                        </label>
                    </div>
                    <div class="text-sm">
                        <a href="#" class="font-medium text-primary-600 hover:text-primary-500 transition-colors">
                            Forgot password?
                        </a>
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-3.5 px-4 rounded-xl shadow-lg shadow-primary-600/30 transition-all duration-200 hover:shadow-primary-600/40 hover:-translate-y-0.5 active:translate-y-0 flex justify-center items-center gap-2 group">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right text-sm opacity-70 group-hover:translate-x-1 group-hover:opacity-100 transition-all"></i>
                </button>
                
            </form>
            
            <div class="mt-8 pt-8 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">
                    &copy; <?php echo date("Y"); ?> IT Department Workspace.
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleIcon = document.getElementById('toggleIcon');

            if (field.type === "password") {
                field.type = "text";
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                field.type = "password";
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>

</html>