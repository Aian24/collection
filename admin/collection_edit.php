<?php
ob_start();
include '../config.php';
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

if (!$conn) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
        exit();
    } else {
        die("Connection failed: " . mysqli_connect_error());
    }
}

// Helper: map branch to table
function mapBranchToTable($branch) {
    if ($branch === 'Sanko Market') return 'collected';
    if ($branch === 'Nova Market') return 'collectednova';
    if ($branch === 'APM') return 'collectedapm';
    return 'collected';
}

// Helper: parse charges string to associative array [ChargeName => float]
function parseCharges($chargesStr) {
    $chargesArr = [];
    if (!is_string($chargesStr) || trim($chargesStr) === '') return $chargesArr;
    $parts = explode(', ', $chargesStr);
    foreach ($parts as $part) {
        if (preg_match('/^([^:]+):\s*(\d+\.?\d*)$/', trim($part), $m)) {
            $name = trim($m[1]);
            $val = (float)$m[2];
            $chargesArr[$name] = $val;
        }
    }
    return $chargesArr;
}

// Helper: rebuild charges string from associative array (only >0)
function buildChargesString($chargesMap) {
    $items = [];
    foreach ($chargesMap as $name => $val) {
        $v = (float)$val;
        if ($v > 0) {
            // keep raw decimal without formatting to stay consistent with storage style
            $items[] = $name . ': ' . $v;
        }
    }
    return implode(', ', $items);
}

// Determine if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// AJAX: Fetch transaction by transaction_number + branch
if ($isAjax && $action === 'getTransaction') {
    header('Content-Type: application/json');

    $branch = isset($_GET['branch']) ? $_GET['branch'] : '';
    $transactionNumber = isset($_GET['transaction_number']) ? $_GET['transaction_number'] : '';

    if ($branch === '' || $transactionNumber === '') {
        echo json_encode(['error' => 'Missing branch or transaction number']);
        exit();
    }

    $table = mapBranchToTable($branch);
    $tnEsc = mysqli_real_escape_string($conn, $transactionNumber);
    $sql = "SELECT * FROM $table WHERE transaction_number = '" . $tnEsc . "' LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        echo json_encode(['error' => 'Query failed: ' . mysqli_error($conn)]);
        exit();
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    if (!$row) {
        echo json_encode(['error' => 'Transaction not found']);
        exit();
    }

    $chargesMap = parseCharges(isset($row['charges']) ? $row['charges'] : '');

    // Build editable fields: include paidrent/paidbal if >0, and any charge >0
    $editable = [];
    if ((float)$row['paidrent'] > 0) $editable['Paid Rent'] = (float)$row['paidrent'];
    if ((float)$row['paidbal'] > 0) $editable['Paid Balance'] = (float)$row['paidbal'];
    foreach ($chargesMap as $name => $val) {
        if ((float)$val > 0) $editable[$name] = (float)$val;
    }

    echo json_encode([
        'transaction' => [
            'branch' => $row['branch'],
            'transaction_number' => $row['transaction_number'],
            'spacecode' => $row['spacecode'],
            'collected_date' => $row['collected_date'],
            'collector' => $row['collector'],
            'tenantcode' => $row['tenantcode'],
            'tenantname' => $row['tenantname'],
            'newbalance' => isset($row['newbalance']) ? (float)$row['newbalance'] : 0,
            'newrentbalance' => isset($row['newrentbalance']) ? (float)$row['newrentbalance'] : 0,
        ],
        'editable' => $editable
    ]);
    exit();
}

// AJAX: Update transaction paid amounts and charges
if ($isAjax && $action === 'updateTransaction') {
    header('Content-Type: application/json');

    $branch = isset($_POST['branch']) ? $_POST['branch'] : '';
    $transactionNumber = isset($_POST['transaction_number']) ? $_POST['transaction_number'] : '';
    $updatesJson = isset($_POST['updates']) ? $_POST['updates'] : '';

    if ($branch === '' || $transactionNumber === '' || $updatesJson === '') {
        echo json_encode(['error' => 'Missing required parameters']);
        exit();
    }

    $updates = json_decode($updatesJson, true);
    if (!is_array($updates)) {
        echo json_encode(['error' => 'Invalid updates payload']);
        exit();
    }

    $table = mapBranchToTable($branch);
    $tnEsc = mysqli_real_escape_string($conn, $transactionNumber);

    // Fetch current row first
    $res = mysqli_query($conn, "SELECT * FROM $table WHERE transaction_number='" . $tnEsc . "' LIMIT 1");
    if (!$res) {
        echo json_encode(['error' => 'Fetch failed: ' . mysqli_error($conn)]);
        exit();
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    if (!$row) {
        echo json_encode(['error' => 'Transaction not found']);
        exit();
    }

    // Current values
    $currentPaidRent = (float)$row['paidrent'];
    $currentPaidBal = (float)$row['paidbal'];
    $chargesMap = parseCharges(isset($row['charges']) ? $row['charges'] : '');

    // Apply updates (keys include Paid Rent, Paid Balance, and named charges)
    $newPaidRent = $currentPaidRent;
    $newPaidBal = $currentPaidBal;

    foreach ($updates as $key => $val) {
        $numVal = is_numeric($val) ? (float)$val : 0;
        if ($key === 'Paid Rent') {
            $newPaidRent = $numVal;
        } elseif ($key === 'Paid Balance') {
            $newPaidBal = $numVal;
        } else {
            // charge name
            $chargesMap[$key] = $numVal; // if 0, will be pruned when rebuilding string
        }
    }

    // Rebuild charges string keeping only > 0 values
    $chargesStr = buildChargesString($chargesMap);

    // Update row
    $sqlUpdate = "UPDATE $table SET paidrent = ?, paidbal = ?, charges = ? WHERE transaction_number = ?";
    $stmt = mysqli_prepare($conn, $sqlUpdate);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . mysqli_error($conn)]);
        exit();
    }
    mysqli_stmt_bind_param($stmt, 'ddss', $newPaidRent, $newPaidBal, $chargesStr, $transactionNumber);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        echo json_encode(['error' => 'Update failed: ' . mysqli_stmt_error($stmt)]);
        mysqli_stmt_close($stmt);
        exit();
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true]);
    exit();
}

// ---- Page variables and initial query (similar to collection.php) ----
$selectedBranch = isset($_GET['branchFilter']) ? $_GET['branchFilter'] : "Sanko Market";
$fromDate = isset($_GET['fromDate']) && !empty($_GET['fromDate']) ? $_GET['fromDate'] : date('Y-m-d');
$toDate = isset($_GET['toDate']) && !empty($_GET['toDate']) ? $_GET['toDate'] : date('Y-m-d');
$selectedCharges = isset($_GET['selectedCharges']) && is_array($_GET['selectedCharges']) ? $_GET['selectedCharges'] : [];
$selectedTenants = isset($_GET['selectedTenants']) && is_array($_GET['selectedTenants']) ? $_GET['selectedTenants'] : [];
$selectedSpaces = isset($_GET['selectedSpaces']) && is_array($_GET['selectedSpaces']) ? $_GET['selectedSpaces'] : [];

$table = mapBranchToTable($selectedBranch);

$sqlBase = "SELECT * FROM $table";
$whereClauses = [];
if (!empty($fromDate)) {
    $whereClauses[] = "collected_date >= '" . mysqli_real_escape_string($conn, $fromDate) . " 00:00:00'";
}
if (!empty($toDate)) {
    $whereClauses[] = "collected_date <= '" . mysqli_real_escape_string($conn, $toDate) . " 23:59:59'";
}
if (!empty($whereClauses)) {
    $sql = $sqlBase . " WHERE " . implode(' AND ', $whereClauses);
} else {
    $sql = $sqlBase;
}
$sql .= " ORDER BY collected_date DESC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database query failed: ' . mysqli_error($conn)]);
        exit();
    } else {
        die("Error fetching data: " . mysqli_error($conn));
    }
}

$dataForTable = [];
$allRows = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

$tenantCounts = [];
$chargeCounts = [];
$spaceCounts = [];

foreach ($allRows as $row) {
    $tenantCode = $row['tenantcode'];
    $tenantCounts[$tenantCode] = isset($tenantCounts[$tenantCode]) ? $tenantCounts[$tenantCode] + 1 : 1;

    $spaceCode = $row['spacecode'];
    $spaceCounts[$spaceCode] = isset($spaceCounts[$spaceCode]) ? $spaceCounts[$spaceCode] + 1 : 1;

    $charges = parseCharges($row['charges']);
    foreach ($charges as $name => $value) {
        if ((float)$value > 0) {
            $chargeCounts[$name] = isset($chargeCounts[$name]) ? $chargeCounts[$name] + 1 : 1;
        }
    }
    if ((float)$row['paidrent'] > 0) {
        $chargeCounts['Paid Rent'] = isset($chargeCounts['Paid Rent']) ? $chargeCounts['Paid Rent'] + 1 : 1;
    }
    if ((float)$row['paidbal'] > 0) {
        $chargeCounts['Paid Balance'] = isset($chargeCounts['Paid Balance']) ? $chargeCounts['Paid Balance'] + 1 : 1;
    }
}

$total_paid_rent = 0;
$total_paid_bal = 0;
$total_combined = 0;
$total_balance = 0;

foreach ($allRows as $row) {
    $charges = parseCharges($row['charges']);

    // PHP-side filters
    $includeRow = true;
    if (!empty($selectedCharges)) {
        $includeRow = false;
        foreach ($selectedCharges as $selectedChargeName) {
            if (stripos($row['charges'], trim($selectedChargeName) . ':') !== false) {
                $includeRow = true;
                break;
            }
        }
        if (!$includeRow) {
            if (in_array('Paid Rent', $selectedCharges) && (float)$row['paidrent'] > 0) $includeRow = true;
            if (in_array('Paid Balance', $selectedCharges) && (float)$row['paidbal'] > 0) $includeRow = true;
        }
    }
    if ($includeRow && !empty($selectedTenants)) {
        if (!in_array($row['tenantcode'], $selectedTenants)) $includeRow = false;
    }
    if ($includeRow && !empty($selectedSpaces)) {
        if (!in_array($row['spacecode'], $selectedSpaces)) $includeRow = false;
    }
    if (!$includeRow) continue;

    // totals
    $row_total = (float)$row['paidrent'] + (float)$row['paidbal'];
    foreach ($charges as $name => $val) { $row_total += (float)$val; }
    $new_balance = (float)$row['newbalance'] + (float)$row['newrentbalance'];

    $total_paid_rent += (float)$row['paidrent'];
    $total_paid_bal += (float)$row['paidbal'];
    $total_combined += $row_total;
    $total_balance += $new_balance;

    // Build display string for non-zero charges (excluding Paid Rent/Bal already shown)
    $chargeParts = [];
    foreach ($charges as $cname => $cval) {
        if ((float)$cval > 0) {
            $chargeParts[] = $cname . ': ' . number_format((float)$cval, 2);
        }
    }
    $charges_display = implode(', ', $chargeParts);

    $dataForTable[] = [
        'branch' => $row['branch'],
        'collected_date' => (new DateTime($row['collected_date']))->format('m/d/y g:i A'),
        'transaction_number' => $row['transaction_number'],
        'spacecode' => $row['spacecode'],
        'collector' => $row['collector'],
        'tenantcode' => $row['tenantcode'],
        'tenantname' => $row['tenantname'],
        'paidrent' => number_format($row['paidrent'], 2),
        'paidbal' => number_format($row['paidbal'], 2),
        'charges_display' => $charges_display,
        'total' => number_format($row_total, 2),
        'balance' => number_format($new_balance, 2)
    ];
}

$tenantcode_codes = [];
$sql_tenantcode_codes = "SELECT DISTINCT tenantcode FROM $table ORDER BY tenantcode ASC";
$result_tenantcode_codes = mysqli_query($conn, $sql_tenantcode_codes);
if ($result_tenantcode_codes) {
    while ($row_tenantcode = mysqli_fetch_assoc($result_tenantcode_codes)) {
        $tenantcode_codes[] = $row_tenantcode['tenantcode'];
    }
    mysqli_free_result($result_tenantcode_codes);
}

$spaceCodes = [];
$sql_space_codes = "SELECT DISTINCT spacecode FROM $table ORDER BY spacecode ASC";
$result_space_codes = mysqli_query($conn, $sql_space_codes);
if ($result_space_codes) {
    while ($row_space = mysqli_fetch_assoc($result_space_codes)) {
        $spaceCodes[] = $row_space['spacecode'];
    }
    mysqli_free_result($result_space_codes);
}

if ($isAjax && $action === 'list') {
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $dataForTable,
        'totals' => [
            'paidrent' => number_format($total_paid_rent, 2),
            'paidbal' => number_format($total_paid_bal, 2),
            'combined' => number_format($total_combined, 2),
            'balance' => number_format($total_balance, 2),
        ],
        'totalRecords' => count($dataForTable),
        'tenantCodes' => $tenantcode_codes,
        'spaceCodes' => $spaceCodes,
        'tenantCounts' => $tenantCounts,
        'spaceCounts' => $spaceCounts,
        'chargeCounts' => $chargeCounts
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <title>Collection Editor</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .form-container { border-radius: 15px; padding: 20px; background-color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin: 20px auto; max-width: 98%; }
        .report-header { color: #2c3e50; font-weight: 600; text-align: center; margin: 20px 0; position: relative; }
        .report-header::after { content: ''; display: block; width: 100px; height: 4px; background: linear-gradient(90deg, #3498db, #2ecc71); margin: 10px auto; border-radius: 2px; }
        .table-container { margin: 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; background-color: white; }
        .table thead th { background-color: #007bff; color: white; font-weight: 500; white-space: nowrap; position: sticky; top: 0; z-index: 10; }
        #dataTable tbody td { font-size: 0.85rem; }
        #dataTable tbody tr { cursor: pointer; }
        .badge-total { background-color: #3498db; color: white; font-size: 14px; font-weight: 500; padding: 5px 10px; border-radius: 10px; }
        .badge-records { background-color: #2ecc71; color: white; font-size: 14px; font-weight: 500; padding: 5px 10px; border-radius: 10px; }
        .modal-readonly input { background-color: #f5f5f5; }
        .editable-row { display: grid; grid-template-columns: 1fr 200px; gap: 10px; align-items: center; }
        /* Ensure jQuery UI datepicker appears above headers/tables/modals */
        .ui-datepicker { z-index: 20000 !important; }
        .modal { z-index: 1050; }
        .modal-backdrop { z-index: 1040; }
        /* Loader overlay */
        #page-loader { position: fixed; inset: 0; background: rgba(255,255,255,0.7); display: none; align-items: center; justify-content: center; z-index: 20050; }
        .spinner { width: 48px; height: 48px; border: 4px solid #cbd5e1; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        /* Remove open animation; keep static appearance */
        .modal.fade .modal-dialog { opacity: 1; }
        /* Center confirmation body */
        #confirmModal .modal-body { text-align: center; }
        /* Dim the edit modal content when confirmation is open */
        .dimmed { filter: grayscale(0.2) brightness(0.9); opacity: 0.5; pointer-events: none; transition: opacity .2s ease, filter .2s ease; }
        /* Inline icon styles and subtle pop animation for icons only */
        .modal-status-icon { font-size: 40px; margin-bottom: 10px; display: inline-block; }
        .animate-pop { animation: icon-pop .35s ease-out; }
        @keyframes icon-pop { 0% { transform: scale(0.8); opacity: 0; } 60% { transform: scale(1.05); opacity: 1; } 100% { transform: scale(1); } }
        /* Strong vertical centering for confirmation and success modals */
        #confirmModal .modal-dialog, #successModal .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    </style>
</head>
<body>
<?php include '../nav.php'; ?>
<div class="container-fluid mt-5" id="datatable-container">
    <div class="form-container">
        <h2 class="report-header">Collection Editor</h2>
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="branchFilter">Branch:</label>
                <select id="branchFilter" class="form-control form-select">
                    <option value="Sanko Market" <?php echo ($selectedBranch == 'Sanko Market') ? 'selected' : ''; ?>>Sanko Market</option>
                    <option value="Nova Market" <?php echo ($selectedBranch == 'Nova Market') ? 'selected' : ''; ?>>Nova Market</option>
                    <option value="APM" <?php echo ($selectedBranch == 'APM') ? 'selected' : ''; ?>>APM</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="fromDate">From Date:</label>
                <input type="text" id="fromDate" class="form-control datepicker" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-4">
                <label for="toDate">To Date:</label>
                <input type="text" id="toDate" class="form-control datepicker" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <span class="badge badge-total">Total Collected: <span id="grandTotal">0.00</span></span>
                <span class="badge badge-total ml-2">Total Balance: <span id="grandBalance">0.00</span></span>
            </div>
            <div class="col-md-6 text-right">
                <span class="badge badge-records">Total Records: <span id="totalRecordsCount">0</span></span>
            </div>
        </div>
    </div>
    <div class="table-container" id="table-wrapper">
        <div class="table-responsive">
            <table id="dataTable" class="table table-striped table-bordered dt-responsive" style="width:100%">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Date</th>
                        <th>Trans #</th>
                        <th>Space Code</th>
                        <th>Collector</th>
                        <th>Tenant Code</th>
                        <th>Tenant Name</th>
                        <th>Paid Rent</th>
                        <th>Paid Bal</th>
                        <th>Charges</th>
                        <th>Row Total</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <small class="text-muted pl-3">Click a row to edit non-zero paid items.</small>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Transaction</h5>
        <button type="button" class="close" id="editModalCloseBtn" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="editForm">
            <div class="modal-readonly">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Trans #</label>
                        <input type="text" class="form-control" id="ro_transaction_number" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Branch</label>
                        <input type="text" class="form-control" id="ro_branch" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Space Code</label>
                        <input type="text" class="form-control" id="ro_spacecode" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Date</label>
                        <input type="text" class="form-control" id="ro_date" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Collector</label>
                        <input type="text" class="form-control" id="ro_collector" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Tenant Name</label>
                        <input type="text" class="form-control" id="ro_tenantname" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Arrear Balance</label>
                        <input type="text" class="form-control" id="ro_arrearbal" readonly>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Rent Balance</label>
                        <input type="text" class="form-control" id="ro_rentbal" readonly>
                    </div>
                </div>
            </div>
            <hr/>
            <h6>Editable Paid Items</h6>
            <div id="editableItems"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="editModalCancel">Close</button>
        <button type="button" class="btn btn-primary" id="saveChanges">Save changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Please Confirm</h5>
        <button type="button" class="close" id="confirmCloseX" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="confirmMessage">
        <div><i class="fas fa-question-circle text-primary modal-status-icon animate-pop"></i></div>
        Are you sure?
      </div>
      <div class="modal-footer d-flex justify-content-center">
        <button type="button" class="btn btn-danger" id="confirmCancel">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmOk">Yes</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Success</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <div><i class="fas fa-check-circle text-success modal-status-icon animate-pop"></i></div>
        Update completed successfully.
      </div>
      <div class="modal-footer d-flex justify-content-center">
        <button type="button" class="btn btn-primary" id="successOk">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Page Loader -->
<div id="page-loader"><div class="spinner"></div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script>
$(function(){
    $(".datepicker").datepicker({ dateFormat: 'yy-mm-dd' });

    function formatDateForFilename(date) {
        var d = new Date(date), m = '' + (d.getMonth()+1), day = '' + d.getDate(), y = d.getFullYear();
        if (m.length < 2) m = '0'+m; if (day.length<2) day = '0'+day; return [y,m,day].join('-');
    }

    function showLoader(){ $('#page-loader').fadeIn(100); }
    function hideLoader(){ $('#page-loader').fadeOut(150); }

    function confirmAction(message, onOk) {
        $('#confirmMessage').contents().filter(function(){ return this.nodeType === 3; }).remove();
        $('#confirmMessage').append(document.createTextNode(message || 'Are you sure?'));
        // Dim edit modal content while confirming
        $('#editModal .modal-content').addClass('dimmed');
        $('#confirmModal').on('hidden.bs.modal', function(){
            $('#editModal .modal-content').removeClass('dimmed');
            $('#confirmModal').off('hidden.bs.modal');
        });
        $('#confirmModal').modal({ backdrop: 'static', keyboard: false }).modal('show');
        $('#confirmOk').off('click');
        $('#confirmCancel').off('click');
        $('#confirmCloseX').off('click');
        $('#confirmOk').on('click', function(){
            $('#confirmModal').modal('hide');
            if (typeof onOk === 'function') onOk();
        });
        $('#confirmCancel').on('click', function(){ $('#confirmModal').modal('hide'); });
        $('#confirmCloseX').on('click', function(){ $('#confirmModal').modal('hide'); });
    }

    var table = $('#dataTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: window.location.pathname + '?action=list',
            type: 'GET',
            data: function(d){
                d.branchFilter = $('#branchFilter').val();
                d.fromDate = $('#fromDate').val();
                d.toDate = $('#toDate').val();
                showLoader();
            },
            dataSrc: function(json){
                $('#grandTotal').text(json.totals.combined);
                $('#grandBalance').text(json.totals.balance);
                $('#totalRecordsCount').text(json.totalRecords);
                return json.data;
            },
            complete: function(){ hideLoader(); }
        },
        columns: [
            { data: 'branch' },
            { data: 'collected_date',
              render: function(data, type) {
                  if (type === 'sort' || type === 'type') {
                      var dateParts = data.split(' ')[0].split('/');
                      var month = parseInt(dateParts[0]) - 1;
                      var day = parseInt(dateParts[1]);
                      var year = parseInt("20" + dateParts[2]);
                      var timePart = data.split(' ')[1];
                      var hour = 0, minute = 0;
                      if (timePart) {
                          var timeComponents = timePart.split(':');
                          hour = parseInt(timeComponents[0]);
                          minute = parseInt(timeComponents[1]);
                          var period = data.split(' ')[2];
                          if (period === "PM" && hour < 12) hour += 12;
                          if (period === "AM" && hour === 12) hour = 0;
                      }
                      return new Date(year, month, day, hour, minute).getTime();
                  }
                  return data;
              }
            },
            { data: 'transaction_number' },
            { data: 'spacecode' },
            { data: 'collector' },
            { data: 'tenantcode' },
            { data: 'tenantname' },
            { data: 'paidrent' },
            { data: 'paidbal' },
            { data: 'charges_display' },
            { data: 'total' },
            { data: 'balance' }
        ],
        order: [[1,'desc']]
    });

    function reloadTable(){ showLoader(); table.ajax.reload(function(){ hideLoader(); }, false); }

    $('#branchFilter, #fromDate, #toDate').on('change', function(){ reloadTable(); });

    // Row click -> open modal
    $('#dataTable tbody').on('click', 'tr', function(){
        var rowData = table.row(this).data();
        if (!rowData) return;
        var branch = rowData.branch;
        var tnum = rowData.transaction_number;
        showLoader();
        $.get(window.location.pathname, { action: 'getTransaction', branch: branch, transaction_number: tnum })
            .done(function(resp){
                if (resp && resp.error) { alert(resp.error); return; }
                if (!resp || !resp.transaction) { alert('No data'); return; }
                var t = resp.transaction;
                $('#ro_transaction_number').val(t.transaction_number);
                $('#ro_branch').val(t.branch);
                $('#ro_spacecode').val(t.spacecode);
                $('#ro_date').val(t.collected_date);
                $('#ro_collector').val(t.collector);
                $('#ro_tenantname').val(t.tenantname);
                $('#ro_arrearbal').val(Number(t.newbalance).toFixed(2));
                $('#ro_rentbal').val(Number(t.newrentbalance).toFixed(2));

                var $box = $('#editableItems');
                $box.empty();
                var items = resp.editable || {};
                var keys = Object.keys(items);
                if (keys.length === 0) {
                    $box.append('<p class="text-muted">No paid items to edit (all are zero).</p>');
                } else {
                    keys.forEach(function(name){
                        var id = 'edit_'+name.replace(/[^a-z0-9]+/gi,'_').toLowerCase();
                        var val = items[name];
                        var row = $('<div class="form-group editable-row"></div>');
                        row.append('<label class="mb-0">'+name+'</label>');
                        row.append('<input type="number" step="0.01" min="0" class="form-control" id="'+id+'" data-name="'+name+'" value="'+Number(val).toFixed(2)+'">');
                        $box.append(row);
                    });
                }

                $('#editModal').modal('show');
            })
            .fail(function(){ alert('Failed to fetch transaction'); })
            .always(function(){ hideLoader(); });
    });

    function formHasChanges(){
        var changed = false;
        $('#editableItems input[type="number"]').each(function(){
            var def = $(this).prop('defaultValue');
            var cur = $(this).val();
            if (String(def) !== String(cur)) { changed = true; return false; }
        });
        return changed;
    }
    function requestCloseEditModal(){
        if (formHasChanges()) {
            confirmAction('Discard changes?', function(){ $('#editModal').modal('hide'); });
        } else {
            $('#editModal').modal('hide');
        }
    }
    $('#editModalCloseBtn').on('click', requestCloseEditModal);
    $('#editModalCancel').on('click', requestCloseEditModal);

    // Save changes (with confirmation and loader)
    $('#saveChanges').on('click', function(){
        confirmAction('Save changes to this transaction?', function(){
            var tnum = $('#ro_transaction_number').val();
            var branch = $('#ro_branch').val();
            var updates = {};
            $('#editableItems input[type="number"]').each(function(){
                var name = $(this).data('name');
                var v = parseFloat($(this).val());
                if (isNaN(v)) v = 0;
                updates[name] = v;
            });
            showLoader();
            $.post(window.location.pathname + '?action=updateTransaction', {
                branch: branch,
                transaction_number: tnum,
                updates: JSON.stringify(updates)
            }).done(function(resp){
                if (resp && resp.error) { alert(resp.error); return; }
                $('#editModal').modal('hide');
                $('#successModal').modal('show');
                reloadTable();
            }).fail(function(){ alert('Update failed'); })
              .always(function(){ hideLoader(); });
        });
    });

    // Ensure success OK closes modal reliably across browsers
    $('#successOk').on('click', function(){
        $('#successModal').modal('hide');
    });
});
</script>
</body>
</html> 