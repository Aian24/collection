// Functions For Registration Page

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    } else {
        input.type = "password";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    }
}

function validateForm() {
    const password = document.getElementById("password").value;
    const confirm_password = document.getElementById("confirm_password").value;

    // Check if passwords match
    if (password !== confirm_password) {
        document.getElementById("password_match_error").innerHTML = "Passwords do not match";
        return false;
    } else {
        document.getElementById("password_match_error").innerHTML = "";
    }

    // Check password strength
    const passwordStrength = calculatePasswordStrength(password);
    const passwordStrengthElement = document.getElementById("password_strength");
    passwordStrengthElement.innerHTML = `Password Strength: ${passwordStrength}`;

    // You can set your own criteria for strong password here
    if (passwordStrength < 3) {
        alert("Password is weak. Please use a stronger password.");
        return false;
    }

    return true;
}

function calculatePasswordStrength(password) {
    // You can implement your own password strength logic here
    // This is just a simple example
    const length = password.length;
    const hasLowerCase = /[a-z]/.test(password);
    const hasUpperCase = /[A-Z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasSymbols = /[!@#$%^&*(),.?":{}|<>]/.test(password);

    let strength = 0;

    if (length >= 8) strength += 1;
    if (hasLowerCase) strength += 1;
    if (hasUpperCase) strength += 1;
    if (hasNumbers) strength += 1;
    if (hasSymbols) strength += 1;

    return strength;
}

// End of functions for Registration page






// Function For userpage

// JavaScript to handle burger menu and side navigation
const burgerMenuBtn = document.getElementById('burger-menu-btn');
const sideNav = document.getElementById('side-nav');
const closeBtn = document.getElementById('close-btn');

// Open side navigation when burger menu is clicked
if (burgerMenuBtn) {
    burgerMenuBtn.addEventListener('click', () => {
        if (sideNav) sideNav.classList.remove('hidden');
    });
}

// Close side navigation when close button is clicked
if (closeBtn) {
    closeBtn.addEventListener('click', () => {
        if (sideNav) sideNav.classList.add('hidden');
    });
}






// End of function for userpage




// For index / loginpage toggle eye password



function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    } else {
        input.type = "password";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    }
}

// End for login page


