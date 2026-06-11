<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            /* Add padding to body to prevent content from being hidden by fixed navbar */
            padding-top: 70px; /* Adjust based on your navbar height */
        }

        .navbar {
            background: linear-gradient(to right, #007bff, #00c6fb); /* Gradient background */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Soft shadow */
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }

        .navbar-brand {
            color: #ffffff !important; /* White text for brand */
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            transition: color 0.3s ease;
        }

        .navbar-brand:hover {
            color: #e9ecef !important; /* Light grey on hover */
        }

        .navbar-brand i {
            margin-right: 8px;
            font-size: 1.2rem;
        }

        .nav-link {
            color: #ffffff !important; /* White text for links */
            font-weight: 500;
            margin-right: 15px;
            transition: color 0.3s ease, transform 0.2s ease;
            position: relative; /* Needed for underline effect */
        }

        .nav-link:hover {
            color: #e9ecef !important; /* Light grey on hover */
            transform: translateY(-2px); /* Slight lift effect */
        }

         /* Underline effect on hover */
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #ffffff; /* White underline */
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.5); /* Lighter toggler border */
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e"); /* White toggler icon */
        }

        /* Dropdown menu styling (if you add one later) */
        .dropdown-menu {
            background-color: #007bff; /* Match navbar background */
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .dropdown-item {
            color: #ffffff !important; /* White text for dropdown items */
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .dropdown-item:hover {
            background-color: #0056b3; /* Darker blue on hover */
            color: #ffffff !important;
        }

        /* Active link styling */
        .nav-link.active {
            font-weight: 600;
             /* Add a distinct style for the active link if desired */
             /* Example: border-bottom: 2px solid #ffffff; */
        }

    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-home"></i> LCLopez Resources
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav ms-auto"> <li class="nav-item">
                    <a class="nav-link" href="collectionapm.php"><i class="fas fa-money-check-alt me-1"></i> Collection</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

</body>
</html>
