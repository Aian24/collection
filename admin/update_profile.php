<?php
session_start();
include '../config.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

// Get user_id from database if not in session
if (!isset($_SESSION['user_id'])) {
    $username = $_SESSION['username'];
    $query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $user['id'];
    } else {
        die("Error: User not found in database.");
    }
    $stmt->close();
}

// Function to validate image
function validateImage($file) {
    $errors = [];
    
    // Check file size (5MB maximum)
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = "File size must be less than 5MB";
    }

    // Check if image file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        $errors[] = "File is not an image";
    }

    // Allowed file types
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
    }

    return $errors;
}

// Function to validate phone number
function validatePhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    
    // Start transaction
    $conn->begin_transaction();

    try {
        // Basic validation
        $required_fields = ['username', 'fname', 'lname', 'email'];
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
                throw new Exception(ucfirst($field) . " is required.");
            }
        }

        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Validate phone number if provided
        if (!empty($_POST['phone_number']) && !validatePhoneNumber($_POST['phone_number'])) {
            throw new Exception("Invalid phone number format.");
        }

        // Handle password change if requested
        if (!empty($_POST['current_password'])) {
            // Verify current password
            $verify_query = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($verify_query);
            if ($stmt === false) {
                throw new Exception("Error preparing password verification statement: " . $conn->error);
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            // Direct comparison since password is not hashed in database
            if (!$user || $_POST['current_password'] !== $user['password']) {
                throw new Exception("Current password is incorrect.");
            }

            if (!empty($_POST['new_password'])) {
                // Validate new password
                if (strlen($_POST['new_password']) < 8) {
                    throw new Exception("Password must be at least 8 characters long.");
                }
                if (!preg_match('/[A-Z]/', $_POST['new_password'])) {
                    throw new Exception("Password must contain at least one uppercase letter.");
                }
                if (!preg_match('/[a-z]/', $_POST['new_password'])) {
                    throw new Exception("Password must contain at least one lowercase letter.");
                }
                if (!preg_match('/[0-9]/', $_POST['new_password'])) {
                    throw new Exception("Password must contain at least one number.");
                }
                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    throw new Exception("New passwords do not match.");
                }
            }
        }

        // Build update query
        $update_fields = [];
        $update_types = "";
        $update_values = [];

        // Map POST fields to database columns (only existing columns)
        $field_mappings = [
            'username' => 's',
            'fname' => 's',
            'lname' => 's',
            'email' => 's',
            'branch' => 's'
        ];

        foreach ($field_mappings as $field => $type) {
            if (isset($_POST[$field]) && (!empty($_POST[$field]) || $_POST[$field] === '')) {
                $update_fields[] = "$field = ?";
                $update_types .= $type;
                $update_values[] = $_POST[$field];
            }
        }

        // Add password if changed
        if (!empty($_POST['new_password'])) {
            $update_fields[] = "password = ?";
            $update_types .= "s";
            // Store new password without hashing since database expects plain text
            $update_values[] = $_POST['new_password'];
        }

        // Handle profile photo upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/profile_photos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory.");
                }
            }

            // Generate unique filename
            $file_info = pathinfo($_FILES['profile_photo']['name']);
            $new_filename = time() . '_' . uniqid() . '.' . strtolower($file_info['extension']);
            $upload_path = $upload_dir . $new_filename;

            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                // Delete old profile photo if exists
                if (isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo'])) {
                    $old_photo_path = __DIR__ . '/' . $_SESSION['profile_photo'];
                    if (file_exists($old_photo_path)) {
                        unlink($old_photo_path);
                    }
                }

                // Debug log
                error_log("New profile photo path: " . $upload_path);

                $profile_photo_path = 'uploads/profile_photos/' . $new_filename;
                $update_fields[] = "profile_photo = ?";
                $update_types .= "s";
                $update_values[] = $profile_photo_path;
                $_SESSION['profile_photo'] = $profile_photo_path;

                // Debug log
                error_log("Profile photo path in session: " . $_SESSION['profile_photo']);
            } else {
                error_log("Failed to move uploaded file from " . $_FILES['profile_photo']['tmp_name'] . " to " . $upload_path);
                throw new Exception("Failed to upload profile photo.");
            }
        }

        // Add user_id to values array
        $update_values[] = $user_id;
        $update_types .= "i";

        // Execute update query
        if (!empty($update_fields)) {
            $update_query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            if ($stmt === false) {
                throw new Exception("Error preparing update statement: " . $conn->error);
            }
            
            $stmt->bind_param($update_types, ...$update_values);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update profile: " . $stmt->error);
            }
            $stmt->close();

            // Update session variables
            foreach ($field_mappings as $field => $type) {
                if (isset($_POST[$field])) {
                    $_SESSION[$field] = $_POST[$field];
                }
            }
        }

        // Commit transaction
        $conn->commit();
        $_SESSION['success_message'] = "Profile updated successfully.";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }

    // Redirect back to caller page
    if (!empty($_POST['returnUrl'])) {
        $returnUrl = $_POST['returnUrl'];
        // Basic security check to prevent open redirects (only allow relative paths)
        if (strpos($returnUrl, 'http') !== 0 && strpos($returnUrl, '/') !== 0) {
            header("Location: " . $returnUrl);
            exit();
        }
    }
    
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } else {
        header("Location: admin.php");
    }
    exit();
}
?> 