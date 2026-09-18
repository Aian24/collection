<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['search']) || !isset($_POST['branch'])) {
    echo json_encode([]);
    exit;
}

$search = trim($_POST['search']);
$branch = trim($_POST['branch']);

if ($search === '' || $branch === '') {
    echo json_encode([]);
    exit;
}

// Determine the table name based on the selected branch
$table = '';
switch ($branch) {
    case 'Nova Market':
        $table = 'nova';
        break;
    case 'Sanko Market':
        $table = 'sanko';
        break;
    case 'APM':
        $table = 'apm';
        break;
    case 'ACC':
    case 'Ambulant':
        $table = 'acc';
        break;
}

if ($table === '') {
    echo json_encode([]);
    exit;
}

include 'config.php';

$suggestions = [];
$stmt = $conn->prepare("SELECT spacecode FROM `$table` WHERE spacecode LIKE ? LIMIT 5");

if ($stmt) {
    $searchParam = "%" . $search . "%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row["spacecode"];
        }
    }
    $stmt->close();
}

if (isset($conn) && $conn) {
    $conn->close();
}

echo json_encode($suggestions);
?>
