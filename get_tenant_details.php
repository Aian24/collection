<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['spacecode']) && isset($_POST['branch'])) {
    $spacecode = $_POST['spacecode'];
    $branch = $_POST['branch'];

    // Determine the table based on branch
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
        default:
            echo json_encode(['success' => false]);
            exit();
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare("SELECT tenantname, daily, rentbal, runningbal, tenantcode FROM $table WHERE spacecode = ?");
    $stmt->bind_param("s", $spacecode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Extract first name or last name
        $tenantname = $row['tenantname'] ?? 'N/A';
        $tenantcode = $row['tenantcode'] ?? '';
        
        // If tenantcode is blank, use the first name or last name
        if (empty($tenantcode)) {
            $names = explode(' ', $tenantname);
            $tenantcode = !empty($names[0]) ? $names[0] : (!empty($names[1]) ? $names[1] : 'N/A');
        }

        // Check if already paid on the selected date
        $collectedTable = ($branch === 'Sanko Market') ? 'collected' : (($branch === 'Nova Market') ? 'collectednova' : 'collectedapm');
        $checkDate = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $paidToday = false;
        
        $paidStmt = $conn->prepare("SELECT COUNT(*) FROM $collectedTable WHERE spacecode = ? AND DATE(collected_date) = ?");
        $paidStmt->bind_param("ss", $spacecode, $checkDate);
        $paidStmt->execute();
        $paidStmt->bind_result($count);
        $paidStmt->fetch();
        if ($count > 0) {
            $paidToday = true;
        }
        $paidStmt->close();

        // Format the response
        $response = [
            'success' => true,
            'tenantcode' => $tenantcode,
            'tenantname' => $tenantname,
            'dailyRent' => number_format($row['daily'], 2, '.', ',') ?? '0.00',
            'rentbal' => !empty($row['rentbal']) ? number_format($row['rentbal'], 2, '.', ',') : '0.00',
            'runningbal' => !empty($row['runningbal']) ? number_format($row['runningbal'], 2, '.', ',') : '0.00',
            'editable' => ($spacecode === 'Ambulant'),
            'paidToday' => $paidToday
        ];
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false]);
}
?>
