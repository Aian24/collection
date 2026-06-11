<?php
include '../config.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $branch = $_GET['branch'];

    $query = "SELECT * FROM $branch WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tenant = $result->fetch_assoc();
    echo json_encode($tenant);
}
?>
