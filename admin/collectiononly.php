<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();
include '../config.php'; // Make sure this file correctly establishes $conn
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

$lname = isset($_SESSION["lname"]) ? $_SESSION["lname"] : "Admin";
date_default_timezone_set('Asia/Manila');

if (!$conn) {
    // Return an error for AJAX requests, or show an error page for non-AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
        exit();
    } else {
        die("Connection failed: " . mysqli_connect_error());
    }
}

// Determine if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Get filter values from GET request (either initial load or AJAX)
// For initial load, these might be empty or defaults. For AJAX, they come from JS.
// --- Default branch is Sanko Market ---
$selectedBranch = isset($_GET['branchFilter']) ? $_GET['branchFilter'] : "APM";
// --- END Default ---

// Set default dates if not provided
$fromDate = isset($_GET['fromDate']) && !empty($_GET['fromDate']) ? $_GET['fromDate'] : date('Y-m-d');
$toDate = isset($_GET['toDate']) && !empty($_GET['toDate']) ? $_GET['toDate'] : date('Y-m-d');

// IMPORTANT: Get selectedCharges, selectedTenants, and selectedSpaces arrays from GET
$selectedCharges = isset($_GET['selectedCharges']) && is_array($_GET['selectedCharges']) ? $_GET['selectedCharges'] : [];
$selectedTenants = isset($_GET['selectedTenants']) && is_array($_GET['selectedTenants']) ? $_GET['selectedTenants'] : [];
$selectedSpaces = isset($_GET['selectedSpaces']) && is_array($_GET['selectedSpaces']) ? $_GET['selectedSpaces'] : [];


// Determine the table based on the selected branch
if ($selectedBranch == 'Sanko Market') {
    $table = 'collected';
} elseif ($selectedBranch == 'Nova Market') {
    $table = 'collectednova';
} elseif ($selectedBranch == 'APM') {
    $table = 'collectedapm';
} else {
    // Default table if no branch is selected or branch is invalid
    // Default table is Sanko Market again
    $table = 'collectedapm'; // Default to APM table
    $selectedBranch = 'APM';
}

// Base SQL query
$sqlBase = "SELECT * FROM $table";
$whereClauses = [];

// Add date range filter
if (!empty($fromDate)) {
    $whereClauses[] = "collected_date >= '" . mysqli_real_escape_string($conn, $fromDate) . " 00:00:00'";
}
if (!empty($toDate)) {
    $whereClauses[] = "collected_date <= '" . mysqli_real_escape_string($conn, $toDate) . " 23:59:59'";
}

// Combine WHERE clauses for SQL query
if (!empty($whereClauses)) {
    $sql = $sqlBase . " WHERE " . implode(' AND ', $whereClauses);
} else {
    $sql = $sqlBase; // No date/branch filters applied
}

// Add ordering
$sql .= " ORDER BY collected_date DESC";

// --- Data Fetching and Processing ---
$result = mysqli_query($conn, $sql);

if (!$result) {
     // Return an error for AJAX requests, or show an error page for non-AJAX
     if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database query failed: ' . mysqli_error($conn)]); // Use mysqli_error for query errors
        exit();
     } else {
        die("Error fetching data: " . mysqli_error($conn));
     }
}

$dataForTable = [];
// Initialize individual charge totals
$total_aircon = 0;
$total_cusa = 0;
$total_electricity = 0;
$total_water = 0;
$total_table_tennis = 0;
$total_pay_toilet = 0;
$total_pay_parking = 0;
$total_ice_water = 0;
$total_ulam_vendor = 0;
$total_gas = 0;
$total_famylihan = 0;
$total_garbage_haul = 0;
$total_photocopy = 0;
$total_tenant_id = 0;
$total_function_room = 0;
$total_tables_chairs = 0;
$total_overnight_works = 0;
$total_vendo_sale = 0;
$total_zumba = 0;
$total_secdep = 0;
$total_utilitydep = 0;
$total_meterdep = 0;
$total_miscellaneous = 0;
$total_forfeited_items = 0;
$total_paid_rent = 0;
$total_paid_bal = 0;
$total_combined = 0; // This seems to be the sum of all collected amounts per transaction
$total_balance = 0;

$processedTransactionNumbers = []; // To avoid double counting totals for the same transaction

// Fetch all rows into an array first to apply PHP-side filters and calculate counts/totals
$allRows = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result); // Free the result set memory

// Initialize count arrays for badges (based on initial SQL results)
$tenantCounts = [];
$chargeCounts = [];
$spaceCounts = [];

// Process the fetched data to calculate counts for badges (counts from initial SQL results)
foreach ($allRows as $row) {
    // Count for tenants
    $tenantCode = $row['tenantcode'];
    $tenantCounts[$tenantCode] = isset($tenantCounts[$tenantCode]) ? $tenantCounts[$tenantCode] + 1 : 1;

    // Count for space codes
    $spaceCode = $row['spacecode'];
    $spaceCounts[$spaceCode] = isset($spaceCounts[$spaceCode]) ? $spaceCounts[$spaceCode] + 1 : 1;

    // Count for charges (need to parse 'charges' column)
    $charges = explode(', ', $row['charges']);
    foreach ($charges as $charge) {
        preg_match('/^([^:]+):\s*(\d+\.?\d*)$/', trim($charge), $matches);
        if (count($matches) === 3) {
            $chargeName = trim($matches[1]);
            $chargeCounts[$chargeName] = isset($chargeCounts[$chargeName]) ? $chargeCounts[$chargeName] + 1 : 1;
        }
    }
    // Also count Paid Rent and Paid Balance if they appear in the data
    if ((float)$row['paidrent'] > 0) {
        $chargeCounts['Paid Rent'] = isset($chargeCounts['Paid Rent']) ? $chargeCounts['Paid Rent'] + 1 : 1;
    }
    if ((float)$row['paidbal'] > 0) {
        $chargeCounts['Paid Balance'] = isset($chargeCounts['Paid Balance']) ? $chargeCounts['Paid Balance'] + 1 : 1;
    }
}


// Process the fetched data AGAIN to apply PHP-side filtering and calculate DISPLAYED totals
// (totals for the *filtered* data)
foreach ($allRows as $row) {
     // Re-extract charge values for calculation for this specific row
     $charges = explode(', ', $row['charges']);
    $aircon = $cusa = $electricity = $water = $table_tennis = $pay_toilet = $pay_parking = $ice_water =
        $ulam_vendor = $gas = $famylihan = $garbage_haul = $photocopy = $tenant_id = $function_room =
        $tables_chairs = $overnight_works = $vendo_sale = $zumba = $secdep = $utilitydep = $meterdep = $miscellaneous = $forfeited_items = 0;

    foreach ($charges as $charge) {
        preg_match('/^([^:]+):\s*(\d+\.?\d*)$/', trim($charge), $matches);
        if (count($matches) === 3) {
             $chargeName = trim($matches[1]);
             $chargeValue = (float)$matches[2];

             switch ($chargeName) {
                 case 'Aircon': $aircon += $chargeValue; break;
                 case 'Cusa': $cusa += $chargeValue; break;
                 case 'Electricity': $electricity += $chargeValue; break;
                 case 'Water': $water += $chargeValue; break;
                 case 'Table Tennis': $table_tennis += $chargeValue; break;
                 case 'Pay Toilet': $pay_toilet += $chargeValue; break;
                 case 'Pay Parking': $pay_parking += $chargeValue; break;
                 case 'Ice & Water': $ice_water += $chargeValue; break;
                 case 'Ulam Vendor': $ulam_vendor += $chargeValue; break;
                 case 'Gas': $gas += $chargeValue; break;
                 case 'Famylihan': $famylihan += $chargeValue; break;
                 case 'Garbage Haul': $garbage_haul += $chargeValue; break;
                 case 'Photocopy': $photocopy += $chargeValue; break;
                 case 'Tenant ID': $tenant_id += $chargeValue; break;
                 case 'Function Room': $function_room += $chargeValue; break;
                 case 'Tables & Chairs': $tables_chairs += $chargeValue; break;
                 case 'Overnight Works': $overnight_works += $chargeValue; break;
                 case 'Vendo Sale': $vendo_sale += $chargeValue; break;
                 case 'Zumba': $zumba += $chargeValue; break;
                 case 'Sec Dep': $secdep += $chargeValue; break;
                 case 'Utility Dep': $utilitydep += $chargeValue; break;
                 case 'Meter Dep': $meterdep += $chargeValue; break;
                 case 'Miscellaneous': $miscellaneous += $chargeValue; break;
                 case 'Forfeited Items': $forfeited_items += $chargeValue; break;
             }
        }
    }


    // --- Apply PHP-side filtering based on selected charges, tenants, and spaces ---
    $includeRow = true;

    // Filter by selected charges if any are selected
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

    // Filter by selected tenants if any are selected
    if ($includeRow && !empty($selectedTenants)) {
        if (!in_array($row['tenantcode'], $selectedTenants)) {
            $includeRow = false;
        }
    }

    // Filter by selected spaces if any are selected
    if ($includeRow && !empty($selectedSpaces)) {
        if (!in_array($row['spacecode'], $selectedSpaces)) {
            $includeRow = false;
        }
    }
    // --- End PHP-side filtering ---


    if ($includeRow) {
        $rent_balance = (float)$row['newrentbalance'];
        $arrear_balance = (float)$row['newbalance'];
        $row_total = (float)$row['paidrent'] + (float)$row['paidbal'] + $aircon + $cusa + $electricity + $water + $table_tennis + $pay_toilet + $pay_parking +
            $ice_water + $ulam_vendor + $gas + $famylihan + $garbage_haul + $photocopy +
            $tenant_id + $function_room + $tables_chairs + $overnight_works +
            $vendo_sale + $zumba + $secdep + $utilitydep + $meterdep + $miscellaneous + $forfeited_items; // Sum of all collected amounts for this transaction

        $dataForTable[] = [
            'branch' => $row['branch'],
            'collected_date' => (new DateTime($row['collected_date']))->format('m/d/y g:i A'),
            'transaction_number' => $row['transaction_number'],
            'spacecode' => $row['spacecode'], // This value is sent to DataTables
            'collector' => $row['collector'],
            'tenantcode' => $row['tenantcode'],
            'tenantname' => $row['tenantname'],
            // Store raw values for calculation, formatted for display
            // Note: DataTables will use the raw values if columns.data points to raw keys for sorting/filtering
            'paidrent' => number_format((float)$row['paidrent'], 2),
            'paidbal' => number_format((float)$row['paidbal'], 2),
            'aircon' => number_format($aircon, 2),
            'cusa' => number_format($cusa, 2),
            'electricity' => number_format($electricity, 2),
            'water' => number_format($water, 2),
            'table_tennis' => number_format($table_tennis, 2),
            'pay_toilet' => number_format($pay_toilet, 2),
            'pay_parking' => number_format($pay_parking, 2),
            'ice_water' => number_format($ice_water, 2),
            'ulam_vendor' => number_format($ulam_vendor, 2),
            'gas' => number_format($gas, 2),
            'famylihan' => number_format($famylihan, 2),
            'garbage_haul' => number_format($garbage_haul, 2),
            'photocopy' => number_format($photocopy, 2),
            'tenant_id' => number_format($tenant_id, 2),
            'function_room' => number_format($function_room, 2),
            'tables_chairs' => number_format($tables_chairs, 2),
            'overnight_works' => number_format($overnight_works, 2),
            'vendo_sale' => number_format($vendo_sale, 2),
            'zumba' => number_format($zumba, 2),
            'secdep' => number_format($secdep, 2),
            'utilitydep' => number_format($utilitydep, 2),
            'meterdep' => number_format($meterdep, 2),
            'miscellaneous' => number_format($miscellaneous, 2),
            'forfeited_items' => number_format($forfeited_items, 2),
            'total' => number_format($row_total, 2), // Total collected for this transaction
            'rent_balance' => number_format($rent_balance, 2), // Rent Balance for this transaction
            'arrear_balance' => number_format($arrear_balance, 2), // Arrear Balance for this transaction

            // Include raw values for potential client-side re-calculation or sorting if needed
            'raw_paidrent' => (float)$row['paidrent'],
            'raw_paidbal' => (float)$row['paidbal'],
            'raw_aircon' => $aircon,
            'raw_cusa' => $cusa,
            'raw_electricity' => $electricity,
            'raw_water' => $water,
            'raw_table_tennis' => $table_tennis,
            'raw_pay_toilet' => $pay_toilet,
            'raw_pay_parking' => $pay_parking,
            'raw_ice_water' => $ice_water,
            'raw_ulam_vendor' => $ulam_vendor,
            'raw_gas' => $gas,
            'raw_famylihan' => $famylihan,
            'raw_garbage_haul' => $garbage_haul,
            'raw_photocopy' => $photocopy,
            'raw_tenant_id' => $tenant_id,
            'raw_function_room' => $function_room,
            'raw_tables_chairs' => $tables_chairs,
            'raw_overnight_works' => $overnight_works,
            'raw_vendo_sale' => $vendo_sale,
            'raw_zumba' => $zumba,
            'raw_secdep' => $secdep,
            'raw_utilitydep' => $utilitydep,
            'raw_meterdep' => $meterdep,
            'raw_miscellaneous' => $miscellaneous,
            'raw_forfeited_items' => $forfeited_items,
            'raw_total' => $row_total,
            'raw_rent_balance' => $rent_balance,
            'raw_arrear_balance' => $arrear_balance,
        ];

        // Calculate grand totals only once per unique transaction number within the filtered set
        // This logic might need refinement depending on exactly how you define grand totals
        // (e.g., per row vs. per transaction). The current code sums row values,
        // and also tries to sum transaction totals via processedTransactionNumbers.
        // Let's keep the per-row sum for display and add the transaction sum for clarity.
        // Accumulate individual charge totals for the *filtered* rows
        $total_paid_rent += (float)$row['paidrent'];
        $total_paid_bal += (float)$row['paidbal'];
        $total_aircon += $aircon;
        $total_cusa += $cusa;
        $total_electricity += $electricity;
        $total_water += $water;
        $total_table_tennis += $table_tennis;
        $total_pay_toilet += $pay_toilet;
        $total_pay_parking += $pay_parking;
        $total_ice_water += $ice_water;
        $total_ulam_vendor += $ulam_vendor;
        $total_gas += $gas;
        $total_famylihan += $famylihan;
        $total_garbage_haul += $garbage_haul;
        $total_photocopy += $photocopy;
        $total_tenant_id += $tenant_id;
        $total_function_room += $function_room;
        $total_tables_chairs += $tables_chairs;
        $total_overnight_works += $overnight_works;
        $total_vendo_sale += $vendo_sale;
        $total_zumba += $zumba;
        $total_secdep += $secdep;
        $total_utilitydep += $utilitydep;
        $total_meterdep += $meterdep;
        $total_miscellaneous += $miscellaneous;
        $total_forfeited_items += $forfeited_items;

        // Sum combined total and balance for the filtered rows
         $total_combined += $row_total;
         $total_balance += $rent_balance + $arrear_balance;

    }
}


// Fetch distinct tenant codes for the filter modal for the CURRENTLY selected table
$tenantcode_codes = [];
// Fetch tenant codes from the currently selected table
$sql_tenantcode_codes = "SELECT DISTINCT tenantcode FROM $table ORDER BY tenantcode ASC";
$result_tenantcode_codes = mysqli_query($conn, $sql_tenantcode_codes);
if ($result_tenantcode_codes) {
    while ($row_tenantcode = mysqli_fetch_assoc($result_tenantcode_codes)) {
        $tenantcode_codes[] = $row_tenantcode['tenantcode'];
    }
    mysqli_free_result($result_tenantcode_codes);
} else {
    // Log error or handle appropriately
    error_log("Error fetching tenant codes for table $table: " . mysqli_error($conn));
}

// Fetch distinct space codes for the filter modal
$spaceCodes = [];
$sql_space_codes = "SELECT DISTINCT spacecode FROM $table ORDER BY spacecode ASC";
$result_space_codes = mysqli_query($conn, $sql_space_codes);
if ($result_space_codes) {
    while ($row_space = mysqli_fetch_assoc($result_space_codes)) {
        $spaceCodes[] = $row_space['spacecode'];
    }
    mysqli_free_result($result_space_codes);
} else {
    error_log("Error fetching space codes for table $table: " . mysqli_error($conn));
}

// --- Handle AJAX Request ---
// This part is executed when DataTables makes an AJAX request
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $dataForTable, // Data for the table body
        'totals' => [ // Totals calculated for the filtered data
            'paidrent' => number_format($total_paid_rent, 2),
            'paidbal' => number_format($total_paid_bal, 2),
            'aircon' => number_format($total_aircon, 2),
            'cusa' => number_format($total_cusa, 2),
            'electricity' => number_format($total_electricity, 2),
            'water' => number_format($total_water, 2),
            'table_tennis' => number_format($total_table_tennis, 2),
            'pay_toilet' => number_format($total_pay_toilet, 2),
            'pay_parking' => number_format($total_pay_parking, 2),
            'ice_water' => number_format($total_ice_water, 2),
            'ulam_vendor' => number_format($total_ulam_vendor, 2),
            'gas' => number_format($total_gas, 2),
            'famylihan' => number_format($total_famylihan, 2),
            'garbage_haul' => number_format($total_garbage_haul, 2),
            'photocopy' => number_format($total_photocopy, 2),
            'tenant_id' => number_format($total_tenant_id, 2),
            'function_room' => number_format($total_function_room, 2),
            'tables_chairs' => number_format($tables_chairs, 2),
            'overnight_works' => number_format($overnight_works, 2),
            'vendo_sale' => number_format($vendo_sale, 2),
            'zumba' => number_format($zumba, 2),
             'secdep' => number_format($total_secdep, 2),
             'utilitydep' => number_format($total_utilitydep, 2),
             'meterdep' => number_format($total_meterdep, 2),
             'miscellaneous' => number_format($total_miscellaneous, 2),
             'forfeited_items' => number_format($total_forfeited_items, 2),
            'combined' => number_format($total_combined, 2), // Total of 'total' column
            'balance' => number_format($total_balance, 2), // Total of 'balance' column
        ],
        'totalRecords' => count($dataForTable), // Count records in the current data set
        'tenantCodes' => $tenantcode_codes, // Include tenant codes for the current branch
        'spaceCodes' => $spaceCodes, // Add space codes to the response
        'tenantCounts' => $tenantCounts, // Include tenant counts (based on initial SQL)
        'spaceCounts' => $spaceCounts, // Add space counts to the response
        'chargeCounts' => $chargeCounts // Include charge counts (based on initial SQL)
    ]);
    exit(); // Stop script execution after sending JSON
}

// --- HTML Rendering (for initial load) ---
// This part is executed when the page is first loaded (not via AJAX)
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Collection Report</title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/modern-dashboard.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <style>
        /* DataTables Custom Overrides */
        .table-container {
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            background-color: var(--card-bg);
            border: 1px solid #e5e7eb;
        }

        .table thead th {
            background-color: #1e293b !important;
            color: #fff !important;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            border-bottom: none !important;
            padding: 12px 30px 12px 15px !important;
        }

        #dataTable tbody td {
            font-size: 0.85rem;
            vertical-align: middle;
            color: var(--text-secondary);
        }

        .table tbody tr:hover {
            background-color: rgba(99, 102, 241, 0.04);
        }

        /* Filter Modal Checkboxes */
        .checkbox-container {
            display: block;
            position: relative;
            padding-left: 30px;
            margin-bottom: 8px;
            cursor: pointer;
            font-size: 14px;
            user-select: none;
            color: var(--text-primary);
        }
        .checkbox-container input {
            position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0;
        }
        .checkmark {
            position: absolute; top: 0; left: 0; height: 20px; width: 20px;
            background-color: #e2e8f0; border-radius: 4px; transition: all 0.2s;
        }
        .checkbox-container:hover input ~ .checkmark { background-color: #cbd5e1; }
        .checkbox-container input:checked ~ .checkmark { background-color: var(--accent-indigo); }
        .checkmark:after { content: ""; position: absolute; display: none; }
        .checkbox-container input:checked ~ .checkmark:after { display: block; }
        .checkbox-container .checkmark:after {
            left: 7px; top: 3px; width: 5px; height: 10px;
            border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg);
        }
        
        /* Badges */
        .count-badge {
            background-color: var(--accent-rose);
            color: white; border-radius: 10px; padding: 2px 6px;
            font-size: 12px; margin-left: 5px;
        }

        .filter-columns, .filter-columns-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px; max-height: 300px; overflow-y: auto; padding-right: 10px;
        }

        /* Tooltip */
        .spacecode-tooltip {
            position: absolute;
            background: #1e293b; color: white;
            padding: 15px; border-radius: var(--radius-md); font-size: 13px;
            z-index: 10000; box-shadow: var(--shadow-hover);
            min-width: 280px; display: none; pointer-events: none;
        }
        .spacecode-tooltip .tooltip-header { font-weight: 600; color: #60a5fa; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px; margin-bottom: 10px; }
        .spacecode-tooltip .tooltip-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .spacecode-tooltip .tooltip-label { color: #94a3b8; }
        .spacecode-tooltip .tooltip-value { font-weight: 600; }
        .spacecode-cell { cursor: pointer; color: var(--accent-indigo); font-weight: 500; }
        .spacecode-cell:hover { text-decoration: underline; }

        /* DataTables Styling Overrides */
        .dt-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 0;
        }
        .dt-buttons .btn {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-md) !important; /* Override btn-group flat edges */
            border: 1px solid #e2e8f0;
            background-color: white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .dt-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
            background-color: #f8fafc;
        }
        
        /* Specific Button Colors */
        .dt-buttons .btn-copy { color: #64748b; }
        .dt-buttons .btn-csv { color: #10b981; }
        .dt-buttons .btn-excel { color: #0ea5e9; }
        .dt-buttons .btn-pdf { color: #ef4444; }
        .dt-buttons .btn-print { color: #8b5cf6; }
        .dt-buttons .btn-colvis { color: #f59e0b; }
        .dataTables_length select { 
            border-radius: var(--radius-sm) !important; 
            border: 1px solid #e2e8f0 !important; 
            padding: 4px 28px 4px 12px !important; 
            margin: 0 6px !important;
            outline: none !important;
            width: auto !important;
            display: inline-block !important;
        }
        .dataTables_filter input { 
            border-radius: var(--radius-sm) !important; 
            border: 1px solid #e2e8f0 !important; 
            padding: 4px 12px !important; 
            margin-left: 8px !important;
            outline: none !important;
            display: inline-block !important;
            width: auto !important;
        }
        
        /* Custom Dropdown Overrides */
        .dropdown-item.active, .dropdown-item:active { background-color: var(--accent-indigo) !important; color: white !important; }
        .dropdown-item:hover { background-color: #f1f5f9; color: var(--accent-indigo); font-weight: 600; }
        
        /* Datepicker Overrides */
        .ui-datepicker { z-index: 10000 !important; font-family: 'Inter', sans-serif; box-shadow: var(--shadow-card); border-radius: var(--radius-md); border: 1px solid #e2e8f0; }
        .ui-datepicker-header { background: var(--card-bg); border: none; border-bottom: 1px solid #e2e8f0; font-weight: 600; }
        .ui-datepicker-title { color: var(--text-primary); }
    </style>
</head>

<body id="page-top">
    <!-- Modern Page Loader -->
    <div id="loader-overlay">
        <div class="modern-spinner">
            <div></div><div></div><div></div><div></div>
        </div>
        <div class="loader-text">Loading Collection...</div>
    </div>

    <div id="wrapper" class="blur-when-loading">

        
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

            <a class="sidebar-brand d-flex align-items-center" href="#">
                <div class="sidebar-brand-icon">
                    <img src="../images/lc.png" alt="logo" class="brand-image">
                </div>
                <div class="sidebar-brand-text mx-3">
                    <span class="brand-text-main">LCLopez</span>
                    <span class="brand-text-sub">Resources</span>
                </div>
            </a>

            <hr class="sidebar-divider my-0">

            <div class="sidebar-heading">
                Menu
            </div>

            <li class="nav-item active">
                <a class="nav-link" href="collectiononly.php">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Collection</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-fw fa-sign-in-alt"></i>
                    <span>Login Page</span>
                </a>
            </li>

            <hr class="sidebar-divider d-none d-md-block">
        </ul>

        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <ul class="navbar-nav ml-auto">

                        
                        <!-- Auto Update Toggle -->
                        <li class="nav-item dropdown no-arrow mx-1 d-flex align-items-center">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="dashboardAutoUpdate" checked>
                                <label class="custom-control-label text-gray-600" for="dashboardAutoUpdate">Auto Update</label>
                                <span class="update-status ml-2"></span>
                            </div>
                        </li>

                        <?php include 'notification_bell.php'; ?>
                        <div class="topbar-divider d-none d-sm-block"></div>

                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($lname); ?></span>
                                <img class="img-profile rounded-circle" 
                                    src="<?php echo isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo']) ? 
                                        htmlspecialchars($_SESSION['profile_photo']) : 
                                        'img/undraw_profile.svg'; ?>">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profileModal">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#settingsModal">
                                    <i class="fas fa-cog fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <div class="container-fluid dashboard-container" id="datatable-container">
        
        <!-- Title Removed per request -->
        <div class="filter-bar w-100 mb-4 d-flex flex-wrap align-items-center justify-content-between" style="background: white; padding: 1.25rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-card); border: 1px solid #e5e7eb; gap: 1rem;">
            
            <div class="d-flex flex-wrap align-items-center" style="gap: 1rem;">
                <!-- Branch Filter -->
                <div class="filter-pill d-flex align-items-center dropdown" style="background: #f8fafc; padding: 4px 12px; border-radius: var(--radius-md); border: 1px solid #e2e8f0; gap: 12px; cursor: pointer;" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-store text-gray-500"></i>
                    <div class="d-flex flex-column">
                        <label class="mb-0 text-xs text-gray-500 font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase;">Branch</label>
                        <div class="d-flex align-items-center" style="gap: 4px;">
                            <span id="selectedBranchText" style="font-weight: 600; font-size: 0.9rem; color: #1e293b;"><?php echo htmlspecialchars($selectedBranch); ?></span>
                            <i class="fas fa-chevron-down text-gray-400" style="font-size: 0.7rem; margin-top: 2px;"></i>
                        </div>
                    </div>
                    <!-- Custom Dropdown Menu -->
                    <div class="dropdown-menu shadow" style="border: 1px solid #e2e8f0; border-radius: var(--radius-md); min-width: 180px; padding: 8px 0; margin-top: 10px;">
                        <a class="dropdown-item branch-option <?php echo ($selectedBranch == 'Sanko Market') ? 'text-primary font-weight-bold bg-light' : ''; ?>" href="#" data-value="Sanko Market" style="padding: 8px 16px; font-weight: 500;">Sanko Market</a>
                        <a class="dropdown-item branch-option <?php echo ($selectedBranch == 'Nova Market') ? 'text-primary font-weight-bold bg-light' : ''; ?>" href="#" data-value="Nova Market" style="padding: 8px 16px; font-weight: 500;">Nova Market</a>
                        <a class="dropdown-item branch-option <?php echo ($selectedBranch == 'APM') ? 'text-primary font-weight-bold bg-light' : ''; ?>" href="#" data-value="APM" style="padding: 8px 16px; font-weight: 500;">APM</a>
                    </div>
                </div>
                <!-- Hidden Input for DataTables AJAX -->
                <input type="hidden" id="branchFilter" value="<?php echo htmlspecialchars($selectedBranch); ?>">

                <!-- From Date -->
                <div class="filter-pill d-flex align-items-center" onclick="document.getElementById('fromDate').focus();" style="background: #f8fafc; padding: 4px 12px; border-radius: var(--radius-md); border: 1px solid #e2e8f0; gap: 12px; cursor: pointer;">
                    <i class="fas fa-calendar-alt text-gray-500"></i>
                    <div class="d-flex flex-column">
                        <label for="fromDate" class="mb-0 text-xs text-gray-500 font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase;">From Date</label>
                        <input type="text" id="fromDate" class="datepicker border-0 p-0" readonly style="background: transparent; outline: none; box-shadow: none; font-weight: 600; font-size: 0.9rem; color: #1e293b; width: 90px;" value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                </div>

                <!-- To Date -->
                <div class="filter-pill d-flex align-items-center" onclick="document.getElementById('toDate').focus();" style="background: #f8fafc; padding: 4px 12px; border-radius: var(--radius-md); border: 1px solid #e2e8f0; gap: 12px; cursor: pointer;">
                    <i class="fas fa-calendar-alt text-gray-500"></i>
                    <div class="d-flex flex-column">
                        <label for="toDate" class="mb-0 text-xs text-gray-500 font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase;">To Date</label>
                        <input type="text" id="toDate" class="datepicker border-0 p-0" readonly style="background: transparent; outline: none; box-shadow: none; font-weight: 600; font-size: 0.9rem; color: #1e293b; width: 90px;" value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <button type="button" class="btn btn-info btn-sm font-weight-bold" data-toggle="modal" data-target="#spaceFilterModal" style="border-radius: var(--radius-sm); padding: 8px 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-building"></i> Spaces (<span id="selectedSpaceCount">0</span>)
                    </button>

                    <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-toggle="modal" data-target="#tenantFilterModal" style="border-radius: var(--radius-sm); background-color: var(--accent-indigo); border-color: var(--accent-indigo); padding: 8px 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-users"></i> Tenants (<span id="selectedTenantCount">0</span>)
                    </button>
                </div>
            </div>

            <div class="d-flex ml-auto" style="gap: 8px;">
                <button id="applyFilters" class="btn btn-primary btn-sm font-weight-bold" style="border-radius: var(--radius-sm); background-color: var(--accent-indigo); border-color: var(--accent-indigo); padding: 8px 16px;">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <button id="resetFilters" class="btn btn-light btn-sm text-secondary font-weight-bold" style="border-radius: var(--radius-sm); padding: 8px 16px; border: 1px solid #e5e7eb;">
                    <i class="fas fa-sync-alt"></i> Reset
                </button>
            </div>
        </div>

        <div class="table-container" id="table-wrapper">
            <div class="d-flex flex-wrap justify-content-between align-items-center p-3" style="background-color: #1e293b; border-bottom: 1px solid rgba(255,255,255,0.05); border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <h6 class="m-0 text-white font-weight-bold"><i class="fas fa-list mr-2"></i>Collection Records</h6>
                <div class="d-flex flex-wrap" style="gap: 1.5rem;">
                    <span style="color: #cbd5e1; font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                        Total Collected: <span id="grandTotal" class="text-white font-weight-bold" style="background: rgba(16, 185, 129, 0.2); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(16, 185, 129, 0.5);">0.00</span>
                    </span>
                    <span style="color: #cbd5e1; font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                        Total Balance: <span id="grandBalance" class="text-white font-weight-bold" style="background: rgba(239, 68, 68, 0.2); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(239, 68, 68, 0.5);">0.00</span>
                    </span>
                    <span style="color: #cbd5e1; font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                        Records: <span id="totalRecordsCount" class="text-white font-weight-bold">0</span>
                    </span>
                </div>
            </div>
            <div class="table-responsive p-3">
                <table id="dataTable" class="table table-striped table-bordered dt-responsive" style="width:100%">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Date</th>
                            <th>Trans #</th>
                            <th>Space Code</th> <th>Collector</th>
                            <th>Tenant Code</th>
                            <th>Tenant Name</th>
                            <th>Paid Rent</th>
                            <th>Paid Bal</th>
                            <th>Aircon</th>
                            <th>Cusa</th>
                            <th>Electricity</th>
                            <th>Water</th>
                            <th>Table Tennis</th>
                            <th>Pay Toilet</th>
                            <th>Pay Parking</th>
                            <th>Ice & Water</th>
                            <th>Ulam Vendor</th>
                            <th>Gas</th>
                            <th>Famylihan</th>
                            <th>Garbage Haul</th>
                            <th>Photocopy</th>
                            <th>Tenant ID</th>
                            <th>Function Room</th>
                            <th>Tables & Chairs</th>
                            <th>Overnight Works</th>
                            <th>Vendo Sale</th>
                            <th>Zumba</th>
                             <th>Sec Dep</th>
                             <th>Utility Dep</th>
                             <th>Meter Dep</th>
                             <th>Miscellaneous</th>
                             <th>Forfeited Items</th>
                            <th>Row Total</th>
                            <th>Rent Balance</th>
                            <th>Arrear Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                    </table>
            </div>
        </div> 
    </div>

    </div> <!-- End of Main Content -->
    </div> <!-- End of Content Wrapper -->
</div> <!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<div class="modal fade" id="tenantFilterModal" tabindex="-1" role="dialog" aria-labelledby="tenantFilterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tenantFilterModalLabel">Select Tenants</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="tenantFilterBody">
                    <div class="filter-search">
                        <input type="text" class="form-control" id="tenantSearch" placeholder="Search tenants...">
                    </div>
                    <div id="tenantCheckboxes" class="filter-columns-2">Loading tenants...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="selectAllTenants" class="btn btn-outline-primary">Select All</button>
                    <button type="button" id="deselectAllTenants" class="btn btn-outline-warning">Deselect All</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Charge Filter Modal -->
    <div class="modal fade" id="chargeFilterModal" tabindex="-1" role="dialog" aria-labelledby="chargeFilterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chargeFilterModalLabel">Select Charges</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="chargeFilterBody">
                    <div class="filter-search">
                        <input type="text" class="form-control" id="chargeSearch" placeholder="Search charges...">
                    </div>
                    <div id="chargeCheckboxes" class="filter-columns-2">
                        <!-- Initial charge list -->
                        <label class="checkbox-container">Paid Rent <span class="count-badge" data-charge="Paid Rent">0</span>
                            <input type="checkbox" class="charge-filter" value="Paid Rent">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Paid Balance <span class="count-badge" data-charge="Paid Balance">0</span>
                            <input type="checkbox" class="charge-filter" value="Paid Balance">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Aircon <span class="count-badge" data-charge="Aircon">0</span>
                            <input type="checkbox" class="charge-filter" value="Aircon">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Cusa <span class="count-badge" data-charge="Cusa">0</span>
                            <input type="checkbox" class="charge-filter" value="Cusa">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Electricity <span class="count-badge" data-charge="Electricity">0</span>
                            <input type="checkbox" class="charge-filter" value="Electricity">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Water <span class="count-badge" data-charge="Water">0</span>
                            <input type="checkbox" class="charge-filter" value="Water">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Table Tennis <span class="count-badge" data-charge="Table Tennis">0</span>
                            <input type="checkbox" class="charge-filter" value="Table Tennis">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Pay Toilet <span class="count-badge" data-charge="Pay Toilet">0</span>
                            <input type="checkbox" class="charge-filter" value="Pay Toilet">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Pay Parking <span class="count-badge" data-charge="Pay Parking">0</span>
                            <input type="checkbox" class="charge-filter" value="Pay Parking">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Ice & Water <span class="count-badge" data-charge="Ice & Water">0</span>
                            <input type="checkbox" class="charge-filter" value="Ice & Water">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Ulam Vendor <span class="count-badge" data-charge="Ulam Vendor">0</span>
                            <input type="checkbox" class="charge-filter" value="Ulam Vendor">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Gas <span class="count-badge" data-charge="Gas">0</span>
                            <input type="checkbox" class="charge-filter" value="Gas">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Famylihan <span class="count-badge" data-charge="Famylihan">0</span>
                            <input type="checkbox" class="charge-filter" value="Famylihan">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Garbage Haul <span class="count-badge" data-charge="Garbage Haul">0</span>
                            <input type="checkbox" class="charge-filter" value="Garbage Haul">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Photocopy <span class="count-badge" data-charge="Photocopy">0</span>
                            <input type="checkbox" class="charge-filter" value="Photocopy">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Tenant ID <span class="count-badge" data-charge="Tenant ID">0</span>
                            <input type="checkbox" class="charge-filter" value="Tenant ID">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Function Room <span class="count-badge" data-charge="Function Room">0</span>
                            <input type="checkbox" class="charge-filter" value="Function Room">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Tables & Chairs <span class="count-badge" data-charge="Tables & Chairs">0</span>
                            <input type="checkbox" class="charge-filter" value="Tables & Chairs">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Overnight Works <span class="count-badge" data-charge="Overnight Works">0</span>
                            <input type="checkbox" class="charge-filter" value="Overnight Works">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Vendo Sale <span class="count-badge" data-charge="Vendo Sale">0</span>
                            <input type="checkbox" class="charge-filter" value="Vendo Sale">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Zumba <span class="count-badge" data-charge="Zumba">0</span>
                            <input type="checkbox" class="charge-filter" value="Zumba">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Sec Dep <span class="count-badge" data-charge="Sec Dep">0</span>
                            <input type="checkbox" class="charge-filter" value="Sec Dep">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Utility Dep <span class="count-badge" data-charge="Utility Dep">0</span>
                            <input type="checkbox" class="charge-filter" value="Utility Dep">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Meter Dep <span class="count-badge" data-charge="Meter Dep">0</span>
                            <input type="checkbox" class="charge-filter" value="Meter Dep">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Miscellaneous <span class="count-badge" data-charge="Miscellaneous">0</span>
                            <input type="checkbox" class="charge-filter" value="Miscellaneous">
                            <span class="checkmark"></span>
                        </label>
                        <label class="checkbox-container">Forfeited Items <span class="count-badge" data-charge="Forfeited Items">0</span>
                            <input type="checkbox" class="charge-filter" value="Forfeited Items">
                            <span class="checkmark"></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="selectAllCharges" class="btn btn-outline-primary">Select All</button>
                    <button type="button" id="deselectAllCharges" class="btn btn-outline-warning">Deselect All</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Space Filter Modal -->
    <div class="modal fade" id="spaceFilterModal" tabindex="-1" role="dialog" aria-labelledby="spaceFilterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="spaceFilterModalLabel">Select Space Codes</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="spaceFilterBody">
                    <div class="filter-search">
                        <input type="text" class="form-control" id="spaceSearch" placeholder="Search space codes...">
                    </div>
                    <div id="spaceCheckboxes" class="filter-columns">Loading space codes...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="selectAllSpaces" class="btn btn-outline-primary">Select All</button>
                    <button type="button" id="deselectAllSpaces" class="btn btn-outline-warning">Deselect All</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Space Code Tooltip -->
    <div id="spacecodeTooltip" class="spacecode-tooltip">
        <div class="tooltip-content"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>


    <script>
        // Loader logic
        function hideLoader() {
            const loader = document.getElementById('loader-overlay');
            if (loader) loader.style.display = 'none';
            document.getElementById('wrapper').classList.remove('blur-when-loading','hide-when-loading');
            
            // Also try to remove from datatable-container just in case it was applied there
            const dtContainer = document.getElementById('datatable-container');
            if (dtContainer) dtContainer.classList.remove('blur-when-loading', 'hide-when-loading');
            
            const tableWrapper = document.getElementById('table-wrapper');
            if (tableWrapper) tableWrapper.classList.remove('hide-when-loading');
        }
        
        function showLoader() {
            const loader = document.getElementById('loader-overlay');
            if (loader) loader.style.display = 'flex';
            document.getElementById('wrapper').classList.add('blur-when-loading');
        }
        
        // Show loader immediately on page load
        document.addEventListener('DOMContentLoaded', function() {
            showLoader();
        });
        
        // Ensure loader hides after page is fully loaded, as a fallback
        window.addEventListener('load', function() {
            setTimeout(hideLoader, 500); 
        });

        $(document).ready(function() {
            // Add these variables at the top of the script to store selected filters
            var selectedTenantFilters = [];
            var selectedChargeFilters = [];
            var selectedSpaceFilters = []; // Add space filters array

            // Initialize Datepickers
            $(".datepicker").datepicker({
                dateFormat: 'yy-mm-dd' // Standard format for database
            });

            var dataTable; // Declare dataTable variable accessible globally or within this scope

            // Function to get selected space codes from the modal
            function getSelectedSpaces() {
                return selectedSpaceFilters;
            }

            // Function to get selected tenant codes from the modal
            function getSelectedTenants() {
                return selectedTenantFilters;
            }

            // Function to get selected charge names from the modal
            function getSelectedCharges() {
                return selectedChargeFilters;
            }

            // Function to update the filter badge counts
            function updateFilterCounts() {
                var selectedTenants = selectedTenantFilters.length;
                var selectedCharges = selectedChargeFilters.length;
                var selectedSpaces = selectedSpaceFilters.length;

                $('#selectedTenantCount').text(selectedTenants);
                $('#selectedChargeCount').text(selectedCharges);
                $('#selectedSpaceCount').text(selectedSpaces);

                // Optional: Change button appearance if filters are applied
                if (selectedTenants > 0 || selectedCharges > 0 || selectedSpaces > 0) {
                    $('#applyFilters').removeClass('btn-primary').addClass('btn-success');
                    $('#resetFilters').show(); // Show reset button
                } else {
                    $('#applyFilters').removeClass('btn-success').addClass('btn-primary');
                    $('#resetFilters').hide(); // Hide reset button
                }
            }

            // Function to filter checkboxes based on search input
            function filterCheckboxes(searchInput, container) {
                var searchText = searchInput.val().toLowerCase();
                container.find('.checkbox-container').each(function() {
                    var text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(searchText));
                });
            }

            // Initialize search functionality for each filter
            $('#spaceSearch').on('input', function() {
                filterCheckboxes($(this), $('#spaceCheckboxes'));
            });

            $('#tenantSearch').on('input', function() {
                filterCheckboxes($(this), $('#tenantCheckboxes'));
            });

            $('#chargeSearch').on('input', function() {
                filterCheckboxes($(this), $('#chargeCheckboxes'));
            });

            // Function to populate the space filter modal with checkboxes and counts
            function populateSpaceFilter(spaceCodes, spaceCounts) {
                var spaceCheckboxesHtml = '';
                
                // Get currently visible data from the table
                var tableData = dataTable.rows({search:'applied'}).data().toArray();
                
                // Filter space codes to only show those present in current data
                var filteredSpaceCodes = [];
                if (spaceCodes && spaceCodes.length > 0) {
                    if (tableData && tableData.length > 0) {
                        // Create a set of space codes present in current data
                        var currentSpaceCodes = new Set();
                        tableData.forEach(function(row) {
                            if (row.spacecode) {
                                currentSpaceCodes.add(row.spacecode);
                            }
                        });
                        
                        // Filter space codes to only those present in current data
                        filteredSpaceCodes = spaceCodes.filter(function(spaceCode) {
                            return currentSpaceCodes.has(spaceCode);
                        });
                    } else {
                        filteredSpaceCodes = spaceCodes;
                    }
                    
                    filteredSpaceCodes.forEach(function(spaceCode) {
                        var count = spaceCounts[spaceCode] || 0;
                        var isChecked = selectedSpaceFilters.includes(spaceCode) ? 'checked' : '';
                        spaceCheckboxesHtml += `
                            <label class="checkbox-container">${spaceCode} <span class="count-badge">${count}</span>
                                <input type="checkbox" class="space-filter" value="${spaceCode}" ${isChecked}>
                                <span class="checkmark"></span>
                            </label>`;
                    });
                } else {
                    spaceCheckboxesHtml = '<p>No space codes found for the selected branch and date range.</p>';
                }
                $('#spaceCheckboxes').html(spaceCheckboxesHtml);
            }

            // Function to populate the tenant filter modal with checkboxes and counts
            function populateTenantFilter(tenantCodes, tenantCounts) {
                var tenantCheckboxesHtml = '';
                
                // Get currently visible data from the table
                var tableData = dataTable.rows({search:'applied'}).data().toArray();
                
                // Filter tenant codes to only show those present in current data
                var filteredTenantCodes = [];
                if (tenantCodes && tenantCodes.length > 0) {
                    if (tableData && tableData.length > 0) {
                        // Create a set of tenant codes present in current data
                        var currentTenantCodes = new Set();
                        tableData.forEach(function(row) {
                            if (row.tenantcode) {
                                currentTenantCodes.add(row.tenantcode);
                            }
                        });
                        
                        // Filter tenant codes to only those present in current data
                        filteredTenantCodes = tenantCodes.filter(function(tenantCode) {
                            return currentTenantCodes.has(tenantCode);
                        });
                    } else {
                        filteredTenantCodes = tenantCodes;
                    }
                    
                    filteredTenantCodes.forEach(function(tenantCode) {
                        var count = tenantCounts[tenantCode] || 0;
                        var isChecked = selectedTenantFilters.includes(tenantCode) ? 'checked' : '';
                        tenantCheckboxesHtml += `
                            <label class="checkbox-container">${tenantCode} <span class="count-badge">${count}</span>
                                <input type="checkbox" class="tenant-filter" value="${tenantCode}" ${isChecked}>
                                <span class="checkmark"></span>
                            </label>`;
                    });
                } else {
                    tenantCheckboxesHtml = '<p>No tenants found for the selected branch and date range.</p>';
                }
                $('#tenantCheckboxes').html(tenantCheckboxesHtml);
            }

            // Function to update charge counts in the charge filter modal
            function updateChargeCounts(chargeCounts) {
                // Get currently visible data from the table
                var tableData = dataTable.rows({search:'applied'}).data().toArray();
                
                // Filter charges to only show those present in current data
                var filteredChargeCounts = {};
                if (tableData && tableData.length > 0) {
                    // Create a set of charges present in current data
                    var currentCharges = new Set();
                    tableData.forEach(function(row) {
                        // Check paid rent and paid balance
                        if (parseFloat(row.raw_paidrent) > 0) {
                            currentCharges.add('Paid Rent');
                        }
                        if (parseFloat(row.raw_paidbal) > 0) {
                            currentCharges.add('Paid Balance');
                        }
                        
                        // Check other charges from the charges string
                        if (row.charges) {
                            var charges = row.charges.split(', ');
                            charges.forEach(function(charge) {
                                var chargeName = charge.split(':')[0].trim();
                                if (chargeName) {
                                    currentCharges.add(chargeName);
                                }
                            });
                        }
                    });
                    
                    // Filter charge counts to only those present in current data
                    Object.keys(chargeCounts).forEach(function(chargeName) {
                        if (currentCharges.has(chargeName)) {
                            filteredChargeCounts[chargeName] = chargeCounts[chargeName];
                        }
                    });
                } else {
                    filteredChargeCounts = chargeCounts;
                }
                
                // Update the count badges only for charges that are present
                $('#chargeCheckboxes .count-badge').each(function() {
                    var chargeName = $(this).closest('.checkbox-container').text().split(' ')[0];
                    if (filteredChargeCounts.hasOwnProperty(chargeName)) {
                        var count = filteredChargeCounts[chargeName] || 0;
                        $(this).text(count);
                        $(this).closest('.checkbox-container').show();
                    } else {
                        $(this).closest('.checkbox-container').hide();
                    }
                });

                // Update checked state of charge checkboxes based on selectedChargeFilters
                $('#chargeCheckboxes input[type="checkbox"]').each(function() {
                    var chargeValue = $(this).val();
                    $(this).prop('checked', selectedChargeFilters.includes(chargeValue));
                });
            }

            // --- Helper function to format date for filename ---
            function formatDateForFilename(date) {
                var d = new Date(date),
                    month = '' + (d.getMonth() + 1),
                    day = '' + d.getDate(),
                    year = d.getFullYear();

                if (month.length < 2)
                    month = '0' + month;
                if (day.length < 2)
                    day = '0' + day;

                return [year, month, day].join('-');
            }
            // --- End Helper function ---


            // Function to check if all values in a column are zero or empty
            function isColumnAllZeroOrEmpty(columnIndex, data) {
                // Map column index to raw data field name
                var rawFieldMap = {
                    7: 'raw_paidrent',
                    8: 'raw_paidbal',
                    9: 'raw_aircon',
                    10: 'raw_cusa',
                    11: 'raw_electricity',
                    12: 'raw_water',
                    13: 'raw_table_tennis',
                    14: 'raw_pay_toilet',
                    15: 'raw_pay_parking',
                    16: 'raw_ice_water',
                    17: 'raw_ulam_vendor',
                    18: 'raw_gas',
                    19: 'raw_famylihan',
                    20: 'raw_garbage_haul',
                    21: 'raw_photocopy',
                    22: 'raw_tenant_id',
                    23: 'raw_function_room',
                    24: 'raw_tables_chairs',
                    25: 'raw_overnight_works',
                    26: 'raw_vendo_sale',
                    27: 'raw_zumba',
                    28: 'raw_secdep',
                    29: 'raw_utilitydep',
                    30: 'raw_meterdep',
                    31: 'raw_miscellaneous',
                    32: 'raw_forfeited_items',
                    33: 'raw_total',
                    34: 'raw_rent_balance',
                    35: 'raw_arrear_balance'
                };
                
                // If we have a raw field mapping, use raw values for checking
                if (rawFieldMap[columnIndex]) {
                    for (var i = 0; i < data.length; i++) {
                        var rawValue = data[i][rawFieldMap[columnIndex]];
                        // Check if raw value is null, undefined, or zero
                        if (rawValue !== null && rawValue !== undefined && rawValue !== 0) {
                            return false; // Found a non-zero value
                        }
                    }
                    return true; // All values were zero
                } else {
                    // Fallback to original logic for non-mapped columns
                    for (var i = 0; i < data.length; i++) {
                        var value = data[i][columnIndex];
                        // Check if value is null, undefined, empty string, or zero
                        var stringValue = (value === null || value === undefined) ? '' : String(value).trim();
                        var numericValue = parseFloat(value);
                        
                        var isZeroOrEmpty = stringValue === '' || (numericValue === 0 && !isNaN(numericValue));
                        
                        if (!isZeroOrEmpty) {
                            return false; // Found a non-zero, non-empty value
                        }
                    }
                    return true; // All values were zero or empty
                }
            }

            // Initialize DataTables
            dataTable = $('#dataTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: window.location.href,
                    type: 'GET',
                    data: function (d) {
                        d.branchFilter = $('#branchFilter').val();
                        d.fromDate = $('#fromDate').val();
                        d.toDate = $('#toDate').val();
                        d.selectedTenants = getSelectedTenants();
                        d.selectedCharges = getSelectedCharges();
                        d.selectedSpaces = getSelectedSpaces(); // Add space filters
                    },
                    dataSrc: function(json) {
                        $('#grandTotal').text(json.totals.combined);
                        $('#grandBalance').text(json.totals.balance);
                        $('#totalRecordsCount').text(json.totalRecords);

                        populateTenantFilter(json.tenantCodes, json.tenantCounts);
                        populateSpaceFilter(json.spaceCodes, json.spaceCounts); // Add space filter population
                        updateChargeCounts(json.chargeCounts);
                        updateFilterCounts();

                        // Hide columns with all zero or empty values
                        setTimeout(function() {
                            var table = $('#dataTable').DataTable();
                            var data = json.data;
                            
                            if (data && data.length > 0) {
                                // Check each column (skip first 7 columns: branch, date, transaction_number, spacecode, collector, tenantcode, tenantname)
                                for (var colIndex = 7; colIndex < table.columns().header().length; colIndex++) {
                                    var allZeroOrEmpty = isColumnAllZeroOrEmpty(colIndex, data);
                                    table.column(colIndex).visible(!allZeroOrEmpty, false);
                                }
                                table.columns.adjust().draw(false);
                            }
                        }, 100);

                        return json.data;
                    }
                },
                columns: [
                    { data: 'branch' },
                    { data: 'collected_date',
                      render: function(data, type, row) {
                          // For sorting and filtering, return a sortable format
                          if (type === 'sort' || type === 'type') {
                              // Extract original date parts from the formatted string and convert back to sortable format
                              var dateParts = data.split(' ')[0].split('/');
                              var month = parseInt(dateParts[0]) - 1; // JS months are 0-indexed
                              var day = parseInt(dateParts[1]);
                              var year = parseInt("20" + dateParts[2]); // Assuming 2-digit year, prepend "20"
                              
                              // Get time parts if available
                              var timePart = data.split(' ')[1];
                              var hour = 0, minute = 0;
                              if (timePart) {
                                  var timeComponents = timePart.split(':');
                                  hour = parseInt(timeComponents[0]);
                                  minute = parseInt(timeComponents[1]);
                                  
                                  // Handle AM/PM
                                  var period = data.split(' ')[2];
                                  if (period === "PM" && hour < 12) hour += 12;
                                  if (period === "AM" && hour === 12) hour = 0;
                              }
                              
                              return new Date(year, month, day, hour, minute).getTime();
                          }
                          // For display, return the formatted date
                          return data;
                      }
                    },
                    { data: 'transaction_number' },
                    { 
                        data: 'spacecode',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                return '<span class="spacecode-cell" data-spacecode="' + data + '" data-branch="' + row.branch + '">' + data + '</span>';
                            }
                            return data;
                        }
                    },
                    { data: 'collector' },
                    { data: 'tenantcode' },
                    { data: 'tenantname' },
                    { data: 'paidrent' },
                    { data: 'paidbal' },
                    { data: 'aircon' },
                    { data: 'cusa' },
                    { data: 'electricity' },
                    { data: 'water' },
                    { data: 'table_tennis' },
                    { data: 'pay_toilet' },
                    { data: 'pay_parking' },
                    { data: 'ice_water' },
                    { data: 'ulam_vendor' },
                    { data: 'gas' },
                    { data: 'famylihan' },
                    { data: 'garbage_haul' },
                    { data: 'photocopy' },
                    { data: 'tenant_id' },
                    { data: 'function_room' },
                    { data: 'tables_chairs' },
                    { data: 'overnight_works' },
                    { data: 'vendo_sale' },
                    { data: 'zumba' },
                     { data: 'secdep' },
                     { data: 'utilitydep' },
                     { data: 'meterdep' },
                     { data: 'miscellaneous' },
                     { data: 'forfeited_items' },
                    { data: 'total' }, // Column for Row Total
                    { data: 'rent_balance' }, // Column for Rent Balance
                    { data: 'arrear_balance' } // Column for Arrear Balance
                    // Make sure the order and data keys match your PHP's $dataForTable structure
                ],
                dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-3"l B f>rt<"d-flex flex-wrap align-items-center justify-content-between mt-3"i p>', // Placed l, B, f side by side
                buttons: [
                    {
                        extend: 'copyHtml5',
                        text: '<i class="fas fa-copy"></i> Copy',
                        titleAttr: 'Copy to clipboard',
                        className: 'btn btn-copy'
                    },
                     {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        titleAttr: 'Export to CSV',
                        className: 'btn btn-csv',
                        exportOptions: {
                            // --- ADDED Logic to hide zero/empty columns ---
                             columns: function (idx, data, node) {
                                 // Only check body columns (skip header, footer etc.)
                                 if (node && node.parentNode && node.parentNode.tagName === 'THEAD') {
                                     return true; // Always include header columns (Branch, Date, etc.)
                                 }
                                 if (data.length === 0) {
                                      return true; // If no data rows, maybe include all columns or none? Let's include none for now. Or perhaps hide based on header text? Hiding none seems safest.
                                      // Alternatively, if you want to show header even if no data: return true;
                                 }

                                 var allZeroOrEmpty = true;
                                 // Check all rows for this column index
                                 for (var i = 0; i < data.length; i++) {
                                     var value = data[i];
                                     // Refined check for zero or empty (handles strings like "0.00")
                                     var stringValue = (value === null || value === undefined) ? '' : String(value).trim();
                                     var numericValue = parseFloat(value);

                                     var isZeroOrEmpty = stringValue === '' || (numericValue === 0 && !isNaN(numericValue));

                                     if (!isZeroOrEmpty) {
                                         allZeroOrEmpty = false; // Found a non-zero, non-empty value
                                         break; // No need to check further rows for this column
                                     }
                                 }
                                 // Include the column in the export ONLY if not all values were zero/empty
                                 return !allZeroOrEmpty;
                             }
                             // --- END ADDED Logic ---
                        },
                        // --- ADDED FILENAME OPTION (kept this) ---
                        filename: function () {
                            // Get today's date formatted as YYYY-MM-DD
                            var today = new Date();
                            var dateString = formatDateForFilename(today); // Use the helper function
                            return 'collectionreport(' + dateString + ')';
                        },
                        // Set title to null to ensure filename is used directly
                        title: null
                        // --- END ADDED FILENAME OPTION ---
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        titleAttr: 'Export to Excel',
                        className: 'btn btn-excel',
                         exportOptions: {
                             // --- ADDED Logic to hide zero/empty columns ---
                              columns: function (idx, data, node) {
                                  // Only check body columns (skip header, footer etc.)
                                  if (node && node.parentNode && node.parentNode.tagName === 'THEAD') {
                                      return true; // Always include header columns (Branch, Date, etc.)
                                  }
                                  if (data.length === 0) {
                                       return true; // If no data rows, maybe include all columns or none? Let's include none for now. Or perhaps hide based on header text? Hiding none seems safest.
                                       // Alternatively, if you want to show header even if no data: return true;
                                  }

                                  var allZeroOrEmpty = true;
                                  // Check all rows for this column index
                                  for (var i = 0; i < data.length; i++) {
                                      var value = data[i];
                                       // Refined check for zero or empty (handles strings like "0.00")
                                      var stringValue = (value === null || value === undefined) ? '' : String(value).trim();
                                      var numericValue = parseFloat(value);

                                      var isZeroOrEmpty = stringValue === '' || (numericValue === 0 && !isNaN(numericValue));


                                      if (!isZeroOrEmpty) {
                                          allZeroOrEmpty = false; // Found a non-zero, non-empty value
                                          break; // No need to check further rows for this column
                                      }
                                  }
                                  // Include the column in the export ONLY if not all values were zero/empty
                                  return !allZeroOrEmpty;
                              }
                             // --- END ADDED Logic ---
                         },
                        customizeData: function( data ) {
                            // Find the index of the 'spacecode' column dynamically
                            // This is safer than hardcoding if column order changes
                            var spacecodeColIndex = -1;
                            // Assuming the first row in data.header matches your columns order
                            var headers = data.header; // Get the header row data

                            // Loop through headers to find the index of 'Space Code'
                            for (var i = 0; i < headers.length; i++) {
                                if (headers[i] === 'Space Code') {
                                    spacecodeColIndex = i;
                                    break;
                                }
                            }

                            // If the spacecode column was found and is included in the export
                            if (spacecodeColIndex !== -1) {
                                // Iterate through each row in the body data
                                for (var i = 0; i < data.body.length; i++) {
                                    // Get the cell data for the spacecode column (index relative to the *exported* columns)
                                    // NOTE: Since we dynamically hide columns *before* customizeData,
                                    // the index might change. customizeData's `data` already reflects
                                    // the columns being exported. We need to find the *new* index
                                    // of 'Space Code' within the exported columns.
                                     // Re-finding the index within the *exported* headers passed to customizeData
                                     var exportedSpacecodeIndex = -1;
                                     for(var k = 0; k < data.header.length; k++){
                                         if(data.header[k] === 'Space Code'){
                                             exportedSpacecodeIndex = k;
                                             break;
                                         }
                                     }

                                    if (exportedSpacecodeIndex !== -1) {
                                        var spacecodeValue = data.body[i][exportedSpacecodeIndex];
                                        // Check if the value exists and is not null/undefined
                                        if (spacecodeValue !== undefined && spacecodeValue !== null) {
                                            // For codes that could be interpreted as scientific notation (like 2E018),
                                            // we need to ensure they're treated as text
                                            var strValue = String(spacecodeValue);
                                            
                                            // Modify the column format in Excel to TEXT format
                                            // This is a hack that uses a zero-width character at the start
                                            // The character is invisible but forces Excel to treat it as text
                                            data.body[i][exportedSpacecodeIndex] = "\u200B" + strValue;
                                        }
                                    } else {
                                         // This case means Space Code column was hidden because all values were zero/empty
                                         // No need to apply the fix as the column isn't exported.
                                    }
                                }
                            } else {
                                // This case means Space Code column was hidden because all values were zero/empty
                                // Or the column header didn't match.
                                console.warn("Space Code column not found in export data for customizeData. Maybe all values were zero/empty or header mismatch.");
                            }
                        },
                        filename: function () {
                            // Get today's date formatted as YYYY-MM-DD
                            var today = new Date();
                            var dateString = formatDateForFilename(today); // Use the helper function
                            return 'collectionreport(' + dateString + ')';
                        },
                        title: null
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        titleAttr: 'Export to PDF',
                        className: 'btn btn-pdf',
                        filename: function () {
                            var today = new Date();
                            var dateString = formatDateForFilename(today);
                            return 'collectionreport(' + dateString + ')';
                        },
                        title: null,
                        exportOptions: {
                              // --- ADDED Logic to hide zero/empty columns for PDF ---
                               columns: function (idx, data, node) {
                                   // Only check body columns (skip header, footer etc.)
                                   if (node && node.parentNode && node.parentNode.tagName === 'THEAD') {
                                       return true; // Always include header columns (Branch, Date, etc.)
                                   }
                                   if (data.length === 0) {
                                        return true; // If no data rows, include all columns or none? Include none? Include header? Let's include header for PDF.
                                        // If you want to include nothing for empty tables: return false;
                                   }

                                   var allZeroOrEmpty = true;
                                   // Check all rows for this column index
                                   for (var i = 0; i < data.length; i++) {
                                       var value = data[i];
                                        // Refined check for zero or empty (handles strings like "0.00")
                                       var stringValue = (value === null || value === undefined) ? '' : String(value).trim();
                                       var numericValue = parseFloat(value);

                                       var isZeroOrEmpty = stringValue === '' || (numericValue === 0 && !isNaN(numericValue));

                                       if (!isZeroOrEmpty) {
                                           allZeroOrEmpty = false; // Found a non-zero, non-empty value
                                           break; // No need to check further rows for this column
                                       }
                                   }
                                   // Include the column in the export ONLY if not all values were zero/empty
                                   return !allZeroOrEmpty;
                               }
                              // --- END ADDED Logic ---
                         }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        titleAttr: 'Print Table',
                        className: 'btn btn-print'
                        // Print usually just prints what's on screen, no need for complex exportOptions
                    },
                     {
                        extend: 'colvis',
                        text: '<i class="fas fa-columns"></i> Columns',
                        titleAttr: 'Show/Hide Columns',
                        className: 'btn btn-colvis'
                     }
                ],
                // Add other DataTables options as needed (e.g., order, paging, language)
                 order: [[ 1, 'desc' ]], // Order by 'Date' column (index 1) descending by default
                 lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]], // Show entries options
            });

            // --- Event Listeners ---

            // Save selected filters when modal is closed
            $('#tenantFilterModal').on('hide.bs.modal', function() {
                selectedTenantFilters = [];
                $('#tenantFilterBody input:checked').each(function() {
                    selectedTenantFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            $('#chargeFilterModal').on('hide.bs.modal', function() {
                selectedChargeFilters = [];
                $('#chargeFilterBody input:checked').each(function() {
                    selectedChargeFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            // Custom Branch Dropdown Logic
            $('.branch-option').on('click', function(e) {
                e.preventDefault();
                var selectedVal = $(this).data('value');
                
                // Update UI Text
                $('#selectedBranchText').text(selectedVal);
                
                // Update Hidden Input
                $('#branchFilter').val(selectedVal);
                
                // Update active styling
                $('.branch-option').removeClass('text-primary font-weight-bold bg-light');
                $(this).addClass('text-primary font-weight-bold bg-light');
            });

            // Apply Filters Button Click
            $('#applyFilters').on('click', function() {
                dataTable.ajax.reload(function(json) {
                    // Hide columns with all zero or empty values after reload
                    setTimeout(function() {
                        var table = $('#dataTable').DataTable();
                        var data = json.data;
                        
                        if (data && data.length > 0) {
                            // Check each column (skip first 7 columns: branch, date, transaction_number, spacecode, collector, tenantcode, tenantname)
                            for (var colIndex = 7; colIndex < table.columns().header().length; colIndex++) {
                                var allZeroOrEmpty = isColumnAllZeroOrEmpty(colIndex, data);
                                table.column(colIndex).visible(!allZeroOrEmpty, false);
                            }
                            table.columns.adjust().draw(false);
                        }
                        
                        // Update filter options based on filtered data
                        if (json) {
                            populateTenantFilter(json.tenantCodes, json.tenantCounts);
                            populateSpaceFilter(json.spaceCodes, json.spaceCounts);
                            updateChargeCounts(json.chargeCounts);
                        }
                    }, 100);
                });
            });

            // Reset Filters Button Click
            $('#resetFilters').on('click', function() {
                // Reset date inputs
                $('#fromDate').val('<?php echo date('Y-m-d'); ?>');
                $('#toDate').val('<?php echo date('Y-m-d'); ?>');

                // Clear selected filters arrays
                selectedTenantFilters = [];
                selectedChargeFilters = [];
                selectedSpaceFilters = [];

                // Deselect all checkboxes
                $('#tenantFilterBody input[type="checkbox"]').prop('checked', false);
                $('#chargeFilterBody input[type="checkbox"]').prop('checked', false);
                $('#spaceFilterBody input[type="checkbox"]').prop('checked', false);

                // Update badge counts
                updateFilterCounts();

                // Reload DataTables and show all columns
                dataTable.ajax.reload(function(json) {
                    // Show all columns after reset
                    setTimeout(function() {
                        var table = $('#dataTable').DataTable();
                        // Show all columns (skip first 7 columns: branch, date, transaction_number, spacecode, collector, tenantcode, tenantname)
                        for (var colIndex = 7; colIndex < table.columns().header().length; colIndex++) {
                            table.column(colIndex).visible(true, false);
                        }
                        table.columns.adjust().draw(false);
                        
                        // Update filter options to show all available options
                        if (json) {
                            populateTenantFilter(json.tenantCodes, json.tenantCounts);
                            populateSpaceFilter(json.spaceCodes, json.spaceCounts);
                            updateChargeCounts(json.chargeCounts);
                        }
                    }, 100);
                });
            });

            // Handle Select All Tenants button
            $('#selectAllTenants').on('click', function() {
                $('#tenantFilterBody input[type="checkbox"]').prop('checked', true);
                selectedTenantFilters = [];
                $('#tenantFilterBody input:checked').each(function() {
                    selectedTenantFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            // Handle Deselect All Tenants button
            $('#deselectAllTenants').on('click', function() {
                $('#tenantFilterBody input[type="checkbox"]').prop('checked', false);
                selectedTenantFilters = [];
                updateFilterCounts();
            });

            // Handle Select All Charges button
            $('#selectAllCharges').on('click', function() {
                $('#chargeFilterBody input[type="checkbox"]').prop('checked', true);
                selectedChargeFilters = [];
                $('#chargeFilterBody input:checked').each(function() {
                    selectedChargeFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            // Handle Deselect All Charges button
            $('#deselectAllCharges').on('click', function() {
                $('#chargeFilterBody input[type="checkbox"]').prop('checked', false);
                selectedChargeFilters = [];
                updateFilterCounts();
            });

            // Update filter counts whenever a checkbox state changes
            $('#tenantFilterBody').on('change', 'input[type="checkbox"]', function() {
                selectedTenantFilters = [];
                $('#tenantFilterBody input:checked').each(function() {
                    selectedTenantFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            $('#chargeFilterBody').on('change', 'input[type="checkbox"]', function() {
                selectedChargeFilters = [];
                $('#chargeFilterBody input:checked').each(function() {
                    selectedChargeFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            // Initial state setup
            if ('<?php echo json_encode($selectedTenants); ?>' !== '[]') {
                selectedTenantFilters = JSON.parse('<?php echo json_encode($selectedTenants); ?>');
            }
            if ('<?php echo json_encode($selectedCharges); ?>' !== '[]') {
                selectedChargeFilters = JSON.parse('<?php echo json_encode($selectedCharges); ?>');
            }
            updateFilterCounts();

            // Save selected filters when modal is closed
            $('#spaceFilterModal').on('hide.bs.modal', function() {
                selectedSpaceFilters = [];
                $('#spaceFilterBody input:checked').each(function() {
                    selectedSpaceFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            // Handle Select All Spaces button
            $('#selectAllSpaces').on('click', function() {
                $('#spaceFilterBody input[type="checkbox"]').prop('checked', true);
                selectedSpaceFilters = [];
                $('#spaceFilterBody input:checked').each(function() {
                    selectedSpaceFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            // Handle Deselect All Spaces button
            $('#deselectAllSpaces').on('click', function() {
                $('#spaceFilterBody input[type="checkbox"]').prop('checked', false);
                selectedSpaceFilters = [];
                updateFilterCounts();
            });

            // Update filter counts whenever a space checkbox state changes
            $('#spaceFilterBody').on('change', 'input[type="checkbox"]', function() {
                selectedSpaceFilters = [];
                $('#spaceFilterBody input:checked').each(function() {
                    selectedSpaceFilters.push($(this).val());
                });
                updateFilterCounts();
            });

            // Space Code Tooltip Functionality
            var tooltip = $('#spacecodeTooltip');
            var tooltipTimeout;
            var currentRequest = null;

            // Show tooltip on hover
            $(document).on('mouseenter', '.spacecode-cell', function(e) {
                var $this = $(this);
                var spacecode = $this.data('spacecode');
                var branch = $this.data('branch');
                
                // Clear any existing timeout
                clearTimeout(tooltipTimeout);
                
                // Cancel any existing request
                if (currentRequest) {
                    currentRequest.abort();
                }
                
                // Show loading state
                tooltip.find('.tooltip-content').html(`
                    <div class="tooltip-header">Space Code: ${spacecode}</div>
                    <div class="tooltip-loading">Loading tenant details...</div>
                `);
                
                // Position and show tooltip
                var offset = $this.offset();
                tooltip.css({
                    left: offset.left + $this.outerWidth() + 10,
                    top: offset.top - 10,
                    display: 'block'
                });
                
                // Make AJAX request to get space code details
                currentRequest = $.ajax({
                    url: 'get_spacecode_details.php',
                    method: 'GET',
                    data: {
                        spacecode: spacecode,
                        branch: branch
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            var data = response.data;
                            tooltip.find('.tooltip-content').html(`
                                <div class="tooltip-header">Space Code: ${data.space_code}</div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Tenant Name:</span>
                                    <span class="tooltip-value">${data.tenant_name}</span>
                                </div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Tenant Code:</span>
                                    <span class="tooltip-value">${data.tenant_code}</span>
                                </div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Daily Rent:</span>
                                    <span class="tooltip-value">₱${data.daily_rent}</span>
                                </div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Rent Balance: <span style="background-color: #3498db; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 4px;">CURRENT</span></span>
                                    <span class="tooltip-value">₱${data.rent_balance}</span>
                                </div>
                                <div class="tooltip-row">
                                    <span class="tooltip-label">Arrear Balance: <span style="background-color: #e74c3c; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 4px;">CURRENT</span></span>
                                    <span class="tooltip-value">₱${data.arrear_balance}</span>
                                </div>
                            `);
                        } else {
                            tooltip.find('.tooltip-content').html(`
                                <div class="tooltip-header">Space Code: ${spacecode}</div>
                                <div class="tooltip-error">${response.error || 'No data found'}</div>
                            `);
                        }
                        currentRequest = null;
                    },
                    error: function(xhr, status, error) {
                        if (status !== 'abort') {
                            tooltip.find('.tooltip-content').html(`
                                <div class="tooltip-header">Space Code: ${spacecode}</div>
                                <div class="tooltip-error">Error loading data</div>
                            `);
                        }
                        currentRequest = null;
                    }
                });
            });

            // Hide tooltip on mouse leave
            $(document).on('mouseleave', '.spacecode-cell', function() {
                tooltipTimeout = setTimeout(function() {
                    tooltip.hide();
                    if (currentRequest) {
                        currentRequest.abort();
                        currentRequest = null;
                    }
                }, 300); // Small delay to prevent flickering
            });

            // Keep tooltip visible when hovering over it
            tooltip.on('mouseenter', function() {
                clearTimeout(tooltipTimeout);
            });

            // Hide tooltip when leaving tooltip area
            tooltip.on('mouseleave', function() {
                tooltip.hide();
                if (currentRequest) {
                    currentRequest.abort();
                    currentRequest = null;
                }
            });

            // Adjust tooltip position if it goes off screen
            function adjustTooltipPosition() {
                var windowWidth = $(window).width();
                var tooltipWidth = tooltip.outerWidth();
                var tooltipLeft = parseInt(tooltip.css('left'));
                
                if (tooltipLeft + tooltipWidth > windowWidth) {
                    tooltip.css('left', windowWidth - tooltipWidth - 20);
                }
            }

            // Hide loader after DataTable is initialized and data is loaded
            $('#dataTable').on('xhr.dt', function() {
                setTimeout(hideLoader, 300); // slight delay for smoothness
            });
        
            // Auto Update Functions
            let autoUpdateInterval;
            let countdownInterval;
            let lastUpdateTime;
            const UPDATE_INTERVAL = 59000; // 59 seconds

            function updateTimeRemaining() {
                if ($('#dashboardAutoUpdate').is(':checked') && lastUpdateTime) {
                    const now = new Date().getTime();
                    const nextUpdate = lastUpdateTime + UPDATE_INTERVAL;
                    const remaining = Math.max(0, Math.ceil((nextUpdate - now) / 1000));
                    
                    if (remaining > 0) {
                        $('.update-status').html(`<span class="badge badge-info" style="font-size: 0.75rem; background-color: var(--accent-indigo); color: white;">Next update in ${remaining}s</span>`);
                    } else {
                        $('.update-status').empty();
                    }
                } else {
                    $('.update-status').empty();
                }
            }

            function performUpdate() {
                // Prevent update if a modal is open
                if ($('.modal.show').length > 0) {
                    lastUpdateTime = new Date().getTime(); // Reset timer without interrupting user
                    return;
                }
                
                lastUpdateTime = new Date().getTime();
                if (window.location.pathname.includes('collection.php')) {
                    if (typeof dataTable !== 'undefined') {
                        dataTable.ajax.reload(null, false);
                    }
                } else {
                    location.reload();
                }
            }

            $('#dashboardAutoUpdate').change(function() {
                if ($(this).is(':checked')) {
                    lastUpdateTime = new Date().getTime();
                    autoUpdateInterval = setInterval(performUpdate, UPDATE_INTERVAL);
                    countdownInterval = setInterval(updateTimeRemaining, 1000);
                    updateTimeRemaining();
                } else {
                    clearInterval(autoUpdateInterval);
                    clearInterval(countdownInterval);
                    $('.update-status').empty();
                }
            });

            // Initialize Auto Update if checked
            if ($('#dashboardAutoUpdate').is(':checked')) {
                lastUpdateTime = new Date().getTime();
                autoUpdateInterval = setInterval(performUpdate, UPDATE_INTERVAL);
                countdownInterval = setInterval(updateTimeRemaining, 1000);
                updateTimeRemaining();
            }
}); // End $(document).ready
    </script>

<?php include 'notification_script.php'; ?>
</body>
</html>