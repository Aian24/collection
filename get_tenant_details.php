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
            $conn->close();
            exit();
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare("SELECT tenantname, daily, rentbal, runningbal, tenantcode, elecbal, waterbal, elecarrear, waterarrear FROM $table WHERE spacecode = ?");
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
        $startOfDay = $checkDate . ' 00:00:00';
        $endOfDay = $checkDate . ' 23:59:59';
        $paidToday = false;
        
        $paidStmt = $conn->prepare("SELECT COUNT(*) FROM $collectedTable WHERE spacecode = ? AND collected_date BETWEEN ? AND ?");
        $paidStmt->bind_param("sss", $spacecode, $startOfDay, $endOfDay);
        $paidStmt->execute();
        $paidStmt->bind_result($count);
        $paidStmt->fetch();
        if ($count > 0) {
            $paidToday = true;
        }
        $paidStmt->close();

        // Parse numerical values for Elec and Water
        $elecArrearVal = floatval(str_replace(',', '', $row['elecarrear'] ?? '0'));
        $elecBalVal = floatval(str_replace(',', '', $row['elecbal'] ?? '0'));
        $elecTotalVal = $elecArrearVal + $elecBalVal;

        $waterArrearVal = floatval(str_replace(',', '', $row['waterarrear'] ?? '0'));
        $waterBalVal = floatval(str_replace(',', '', $row['waterbal'] ?? '0'));
        $waterTotalVal = $waterArrearVal + $waterBalVal;

        $rentBalVal = floatval(str_replace(',', '', $row['rentbal'] ?? '0'));
        $runningBalVal = floatval(str_replace(',', '', $row['runningbal'] ?? '0'));

        // Format the response (keep natural values as in DB)
        $response = [
            'success' => true,
            'tenantcode' => $tenantcode,
            'tenantname' => $tenantname,
            'dailyRent' => number_format((float)($row['daily'] ?? 0), 2, '.', ','),
            'rentbal' => number_format($rentBalVal, 2, '.', ','),
            'runningbal' => number_format($runningBalVal, 2, '.', ','),
            'elecarrear' => number_format($elecArrearVal, 2, '.', ','),
            'elecbal' => number_format($elecBalVal, 2, '.', ','),
            'electotal' => number_format($elecTotalVal, 2, '.', ','),
            'waterarrear' => number_format($waterArrearVal, 2, '.', ','),
            'waterbal' => number_format($waterBalVal, 2, '.', ','),
            'watertotal' => number_format($waterTotalVal, 2, '.', ','),
            'editable' => ($spacecode === 'Ambulant'),
            'paidToday' => $paidToday
        ];
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false]);
}
if (isset($conn) && $conn) {
    $conn->close();
}
?>
