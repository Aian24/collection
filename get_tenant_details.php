<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($_POST['spacecode']) || empty($_POST['branch'])) {
    echo json_encode(['success' => false]);
    exit();
}

$spacecode = trim($_POST['spacecode']);
$branch = trim($_POST['branch']);

// Determine the table based on branch
$table = '';
$collectedTable = '';
switch ($branch) {
    case 'Nova Market':
        $table = 'nova';
        $collectedTable = 'collectednova';
        break;
    case 'Sanko Market':
        $table = 'sanko';
        $collectedTable = 'collected';
        break;
    case 'APM':
        $table = 'apm';
        $collectedTable = 'collectedapm';
        break;
    case 'ACC':
    case 'Ambulant':
        $table = 'acc';
        $collectedTable = 'collectedacc';
        break;
    default:
        echo json_encode(['success' => false]);
        exit();
}

include 'config.php';

// Prepare the SQL statement
$stmt = $conn->prepare("SELECT tenantname, daily, rentbal, runningbal, tenantcode, elecbal, waterbal, elecarrear, waterarrear FROM `$table` WHERE spacecode = ?");

if (!$stmt) {
    echo json_encode(['success' => false]);
    if (isset($conn) && $conn) $conn->close();
    exit();
}

$stmt->bind_param("s", $spacecode);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
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
    $checkDate = isset($_POST['date']) && !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    $startOfDay = $checkDate . ' 00:00:00';
    $endOfDay = $checkDate . ' 23:59:59';
    $paidToday = false;
    
    $paidStmt = $conn->prepare("SELECT COUNT(*) FROM `$collectedTable` WHERE spacecode = ? AND collected_date BETWEEN ? AND ?");
    if ($paidStmt) {
        $paidStmt->bind_param("sss", $spacecode, $startOfDay, $endOfDay);
        $paidStmt->execute();
        $paidStmt->bind_result($count);
        if ($paidStmt->fetch() && $count > 0) {
            $paidToday = true;
        }
        $paidStmt->close();
    }

    // Parse numerical values for Elec and Water
    $elecArrearVal = floatval(str_replace(',', '', $row['elecarrear'] ?? '0'));
    $elecBalVal = floatval(str_replace(',', '', $row['elecbal'] ?? '0'));
    $elecTotalVal = $elecArrearVal + $elecBalVal;

    $waterArrearVal = floatval(str_replace(',', '', $row['waterarrear'] ?? '0'));
    $waterBalVal = floatval(str_replace(',', '', $row['waterbal'] ?? '0'));
    $waterTotalVal = $waterArrearVal + $waterBalVal;

    $rentBalVal = floatval(str_replace(',', '', $row['rentbal'] ?? '0'));
    $runningBalVal = floatval(str_replace(',', '', $row['runningbal'] ?? '0'));

    // Format the response
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

if (isset($conn) && $conn) {
    $conn->close();
}
