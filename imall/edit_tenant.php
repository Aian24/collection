<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();


if (isset($_POST['contract']) && isset($_POST['branch'])) {
    $contract = $_POST['contract'];
    $branch = $_POST['branch'];

    // Fetch the tenant record based on the contract
    $qry = $conn->query("SELECT * FROM `$branch` WHERE `contract` = '$contract'");
    $row = $qry->fetch_assoc();

    if ($row) {
        // Display the form with the existing data
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <!-- custom css file link  -->
            <link rel="stylesheet" href="admin.css">
            <title>Edit Tenant</title>
        </head>
        <body>
        <center>
    <div class="form-container">
            <form action="update_tenant.php" method="post">
            <h2>Edit Tenant</h2>
                <input type="hidden" name="contract" value="<?= $row['contract'] ?>">
                <input type="hidden" name="branch" value="<?= $branch ?>">
                <label for="company">Company:</label>
                <input type="text" name="company" id="company" value="<?= $row['company'] ?>"><br>
                <label for="stall">Stall:</label>
                <input type="text" name="stall" id="stall" value="<?= $row['stall'] ?>"><br>
                <label for="trade">Trade:</label>
                <input type="text" name="trade" id="trade" value="<?= $row['trade'] ?>"><br>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </body>
        </html>
    </div>
</center>
        <?php
    } else {
        echo 'Tenant not found.';
    }
}
?>
