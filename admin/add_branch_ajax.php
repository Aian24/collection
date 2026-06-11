<?php
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request with the required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branch_name']) && isset($_POST['branch_code'])) {
    $branch_name = trim($_POST['branch_name']);
    $branch_code = strtolower(trim(str_replace(' ', '_', $_POST['branch_code'])));

    // Basic validation
    if (empty($branch_name) || empty($branch_code)) {
        echo json_encode(['success' => false, 'message' => 'Branch name and code are required']);
        exit();
    }

    // Check if branch already exists
    $check_stmt = $conn->prepare("SELECT id FROM branches WHERE branch_code = ?");
    $check_stmt->bind_param("s", $branch_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Branch code already exists']);
        exit();
    }

    // Insert new branch
    $insert_stmt = $conn->prepare("INSERT INTO branches (branch_name, branch_code) VALUES (?, ?)");
    $insert_stmt->bind_param("ss", $branch_name, $branch_code);

    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Branch added successfully',
            'branch' => [
                'name' => $branch_name,
                'code' => $branch_code
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating branch: ' . $conn->error]);
    }

    $insert_stmt->close();
    $check_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 