<?php
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get user details from session
$username = $_SESSION["username"];
$lname = $_SESSION["lname"] ?? '';
$branch = $_SESSION["branch"] ?? '';

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get the current date and time in the Philippines timezone
$currentDateTime = date('Y-m-d\TH:i:s');

// Determine the latest transaction number based on branch
$latestTransactionNumber = 0;
$tableName = '';

// Check if the branch is Sanko Market, Nova Market, APM, or ACC
if ($branch === 'Sanko Market') {
    $tableName = 'collected';
} elseif ($branch === 'Nova Market') {
    $tableName = 'collectednova';
} elseif ($branch === 'APM') {
    $tableName = 'collectedapm';
} elseif ($branch === 'ACC' || $branch === 'Ambulant') {
    $tableName = 'collectedacc';
} else {
    echo "Invalid branch selection: " . htmlspecialchars($branch);
    if (isset($conn) && $conn) $conn->close();
    exit();
}

$latestTransactionQuery = $conn->query("SELECT MAX(transaction_number) AS max_transaction FROM `$tableName`");
if ($latestTransactionQuery) {
    $latestTransactionRow = $latestTransactionQuery->fetch_assoc();
    $latestTransactionNumber = (int)($latestTransactionRow['max_transaction'] ?? 0);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $collector = $_POST['collector'] ?? '';
    $tenantcode = $_POST['tenantcode'] ?? '';
    $spacecode = $_POST['spacecode'] ?? '';
    $tenantname = $_POST['tenantname'] ?? '';
    $collected_date = $_POST['collected_date'] ?? '';
    $collected_date_part = $_POST['collected_date_part'] ?? '';
    $collected_time_part = $_POST['collected_time_part'] ?? '';

    // Robust backdate handling: ensure selected date is properly saved on mobile & desktop
    if (!empty($collected_date_part)) {
        $timeStr = !empty($collected_time_part) ? trim($collected_time_part) : date('H:i:s');
        if (strlen($timeStr) === 5) {
            $timeStr .= ':00'; // Mobile HTML5 time picker sends HH:mm
        }
        $collected_date = $collected_date_part . ' ' . $timeStr;
    } elseif (!empty($collected_date)) {
        $collected_date = str_replace('T', ' ', $collected_date);
    } else {
        $collected_date = date('Y-m-d H:i:s');
    }

    if (empty($collector) || empty($spacecode)) {
        echo "Error: Required fields are missing. Please submit the form correctly.";
        if (isset($conn) && $conn) $conn->close();
        exit();
    }
    $payment_method = !empty($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash';
    $cheque_number = $_POST['cheque_number'] ?? null;
    $cheque_payee = $_POST['cheque_payee'] ?? null;

    // Remove commas and format numeric values properly
    $rent = !empty($_POST['rent']) ? floatval(str_replace(',', '', $_POST['rent'])) : 0;
    $rentbal = !empty($_POST['rentbal']) ? floatval(str_replace(',', '', $_POST['rentbal'])) : 0;
    $runningBal = !empty($_POST['runningbal']) ? floatval(str_replace(',', '', $_POST['runningbal'])) : 0;
    $paidrent = !empty($_POST['paidrent']) ? floatval(str_replace(',', '', $_POST['paidrent'])) : 0;
    $paidbal = !empty($_POST['paidbal']) ? floatval(str_replace(',', '', $_POST['paidbal'])) : 0;
    $paidelec = !empty($_POST['paidelec']) ? floatval(str_replace(',', '', $_POST['paidelec'])) : 0;
    $paidelecarrear = !empty($_POST['paidelecarrear']) ? floatval(str_replace(',', '', $_POST['paidelecarrear'])) : 0;
    $paidwater = !empty($_POST['paidwater']) ? floatval(str_replace(',', '', $_POST['paidwater'])) : 0;
    $paidwaterarrear = !empty($_POST['paidwaterarrear']) ? floatval(str_replace(',', '', $_POST['paidwaterarrear'])) : 0;
    $elecbal = !empty($_POST['elecbal']) ? floatval(str_replace(',', '', $_POST['elecbal'])) : 0;
    $elecarrear = !empty($_POST['elecarrear']) ? floatval(str_replace(',', '', $_POST['elecarrear'])) : 0;
    $newelecbal = !empty($_POST['newelecbal']) ? floatval(str_replace(',', '', $_POST['newelecbal'])) : 0;
    $newelecarrear = !empty($_POST['newelecarrear']) ? floatval(str_replace(',', '', $_POST['newelecarrear'])) : 0;
    $waterbal = !empty($_POST['waterbal']) ? floatval(str_replace(',', '', $_POST['waterbal'])) : 0;
    $waterarrear = !empty($_POST['waterarrear']) ? floatval(str_replace(',', '', $_POST['waterarrear'])) : 0;
    $newwaterbal = !empty($_POST['newwaterbal']) ? floatval(str_replace(',', '', $_POST['newwaterbal'])) : 0;
    $newwaterarrear = !empty($_POST['newwaterarrear']) ? floatval(str_replace(',', '', $_POST['newwaterarrear'])) : 0;
    $total = !empty($_POST['total']) ? floatval(str_replace(',', '', $_POST['total'])) : 0;
    $newbalance = !empty($_POST['newbalance']) ? floatval(str_replace(',', '', $_POST['newbalance'])) : 0;
    $newrentbalance = !empty($_POST['newrentbalance']) ? floatval(str_replace(',', '', $_POST['newrentbalance'])) : 0;

    // Use the latest transaction number to generate the next one
    $nextTransactionNumber = $latestTransactionNumber + 1;

    // Accumulate charges
    $charges = [];
    if (!empty($_POST['chargecusa'])) {
        $charges[] = "Cusa: " . floatval(str_replace(',', '', $_POST['chargecusa']));
    }
    if (!empty($_POST['chargeac'])) {
        $charges[] = "Aircon: " . floatval(str_replace(',', '', $_POST['chargeac']));
    }

    // Handle multiple 'chargeothers' and 'otheramount' entries
    if (isset($_POST['chargeothers']) && isset($_POST['otheramount'])) {
        foreach ($_POST['chargeothers'] as $index => $chargeType) {
            $amount = isset($_POST['otheramount'][$index]) ? floatval(str_replace(',', '', $_POST['otheramount'][$index])) : 0;
            if ($amount > 0) {
                $charges[] = "$chargeType: " . $amount;
            }
        }
    }

    $chargesString = implode(', ', $charges);

    // Prepare the INSERT statement for the main table
    $query = "INSERT INTO `$tableName` (transaction_number, collector, branch, tenantcode, spacecode, tenantname, rent, rentbal, runningbal, paidrent, paidbal, total, newbalance, newrentbalance, username, collected_date, payment_method, cheque_number, cheque_payee, elecbal, paidelec, newelecbal, waterbal, paidwater, newwaterbal, elecarrear, waterarrear, newelecarrear, newwaterarrear, paidelecarrear, paidwaterarrear" .
        (!empty($chargesString) ? ", charges" : "") . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" .
        (!empty($chargesString) ? ", ?" : "") . ")";

    $insertStmt = $conn->prepare($query);

    if (!$insertStmt) {
        error_log("Prepare collection failed: " . $conn->error);
        echo "<div style='background:#fee2e2;border:1px solid #ef4444;color:#b91c1c;padding:16px;border-radius:8px;margin:20px;font-family:sans-serif;'>";
        echo "<strong>Database Schema Notice:</strong> Table `{$tableName}` is missing one or more required columns.<br>";
        echo "Please open <a href='migrate.php?run=1' style='color:#1d4ed8;font-weight:bold;text-decoration:underline;'>migrate.php?run=1</a> to automatically update your database tables.";
        echo "</div>";
        if (isset($conn) && $conn) $conn->close();
        exit();
    }

    // Bind parameters for insertion
    if (!empty($chargesString)) {
        $insertStmt->bind_param("dsssssddddddddsssssdddddddddddds", 
            $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, 
            $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, 
            $username, $collected_date, $payment_method, $cheque_number, $cheque_payee,
            $elecbal, $paidelec, $newelecbal, $waterbal, $paidwater, $newwaterbal, $elecarrear, $waterarrear, $newelecarrear, $newwaterarrear, $paidelecarrear, $paidwaterarrear,
            $chargesString);
    } else {
        $insertStmt->bind_param("dsssssddddddddsssssdddddddddddd", 
            $nextTransactionNumber, $collector, $branch, $tenantcode, $spacecode, $tenantname, 
            $rent, $rentbal, $runningBal, $paidrent, $paidbal, $total, $newbalance, $newrentbalance, 
            $username, $collected_date, $payment_method, $cheque_number, $cheque_payee,
            $elecbal, $paidelec, $newelecbal, $waterbal, $paidwater, $newwaterbal, $elecarrear, $waterarrear, $newelecarrear, $newwaterarrear, $paidelecarrear, $paidwaterarrear);
    }

    // Execute the INSERT statement
    if ($insertStmt->execute()) {
        $updateTable = '';
        if ($branch === 'Sanko Market') {
            $updateTable = 'sanko';
        } elseif ($branch === 'Nova Market') {
            $updateTable = 'nova';
        } elseif ($branch === 'APM') {
            $updateTable = 'apm';
        } elseif ($branch === 'ACC' || $branch === 'Ambulant') {
            $updateTable = 'acc';
        }

        if (!empty($updateTable)) {
            $updateStmt = $conn->prepare("UPDATE `$updateTable` SET runningbal = ?, rentbal = ?, elecbal = ?, waterbal = ?, elecarrear = ?, waterarrear = ? WHERE spacecode = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("dddddds", $newbalance, $newrentbalance, $newelecbal, $newwaterbal, $newelecarrear, $newwaterarrear, $spacecode);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
        include 'modalsuccess.php';
    } else {
        echo "Error inserting collection: " . $conn->error;
    }

    $insertStmt->close();
}

if (isset($conn) && $conn) {
    $conn->close();
}
$nextReceiptDisplay = $latestTransactionNumber + 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Collection POS — <?php echo htmlspecialchars($branch); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        :root {
            --primary: #4f46e5;
            --primary-blue: #2563eb;
            --primary-dark: #1d4ed8;
            --gradient: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            --surface: #ffffff;
            --surface-alt: #f8fafc;
            --bg: #f1f5f9;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --emerald: #059669;
            --amber: #d97706;
            --sky: #0284c7;
            --rose: #e11d48;
            --violet: #7c3aed;
            --nav-h: 58px;
            --radius-lg: 18px;
            --radius-md: 12px;
            --shadow-card: 0 2px 12px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.04);
            --shadow-hover: 0 10px 26px rgba(37, 99, 235, 0.12);
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            background-image:
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.06) 0px, transparent 48%),
                radial-gradient(at 100% 0%, rgba(79, 70, 229, 0.05) 0px, transparent 42%);
            color: var(--text);
            margin: 0;
            padding: 0;
            padding-top: calc(var(--nav-h) + 10px);
            padding-bottom: calc(102px + env(safe-area-inset-bottom, 0px)) !important;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── MOBILE TOP BAR ── */
        .top-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--nav-h);
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #4f46e5 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 12px;
            box-shadow: 0 4px 16px rgba(30, 58, 138, 0.25);
            z-index: 100;
            gap: 8px;
        }
        .top-bar .brand {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #fff;
            min-width: 0;
        }
        .top-bar .brand-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .top-bar .brand-name {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: -0.01em;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .top-bar .brand-sub {
            font-size: 10.5px;
            font-weight: 600;
            opacity: 0.85;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-shrink: 0;
        }
        .mobile-live-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255, 255, 255, 0.96);
            color: #1e3a8a;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.16);
        }
        .top-bar .user-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            padding: 5px 10px 5px 7px;
            cursor: pointer;
            color: #fff;
            font-family: inherit;
        }
        .top-bar .user-pill i { font-size: 15px; }
        .top-bar .user-pill span { font-size: 11.5px; font-weight: 700; max-width: 72px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── SHELL & WORKSPACE GRID ── */
        .pos-page-wrap {
            padding: 0 12px;
            max-width: 1360px;
            margin: 0 auto;
        }

        .pos-desktop-header {
            display: none;
        }

        .pos-workspace-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 560px;
            margin: 0 auto;
        }

        .pos-main-col {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
        }

        .pos-summary-col {
            min-width: 0;
        }

        @media (min-width: 1024px) {
            body {
                padding-top: 20px !important;
                padding-bottom: 32px !important;
                padding-left: 260px !important;
            }
            .top-bar {
                display: none !important;
            }
            .pos-page-wrap {
                padding: 0 26px;
            }
            .pos-desktop-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-lg);
                padding: 14px 20px;
                margin-bottom: 18px;
                box-shadow: var(--shadow-card);
            }
            .pos-workspace-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.55fr) 390px;
                gap: 20px;
                align-items: start;
                max-width: 100%;
            }
            .pos-summary-col {
                position: sticky;
                top: 20px;
            }
        }

        @media (min-width: 1320px) {
            .pos-workspace-grid {
                grid-template-columns: minmax(0, 1.65fr) 415px;
            }
        }

        /* ── DESKTOP HEADER ELEMENTS ── */
        .dh-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .dh-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            background: var(--gradient);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.25);
        }
        .dh-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text);
            margin: 0;
            line-height: 1.2;
        }
        .dh-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 3px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .dh-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #eef2ff;
            color: #4338ca;
        }
        .dh-chip-slate {
            background: #f1f5f9;
            color: #475569;
        }
        .dh-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .progress-widget {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 8px 14px;
        }
        .progress-widget-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }
        .progress-widget-val {
            font-size: 13px;
            font-weight: 800;
            color: var(--text);
            font-variant-numeric: tabular-nums;
        }
        .progress-bar-track {
            width: 84px;
            height: 6px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin-top: 4px;
        }
        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #10b981, #2563eb);
            border-radius: 999px;
            transition: width 0.4s ease;
        }
        .btn-ghost-reset {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 14px;
            border-radius: 11px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-secondary);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-ghost-reset:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: var(--rose);
        }

        /* ── POS SECTION CARDS ── */
        .pos-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            padding: 16px;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        @media (min-width: 1024px) {
            .pos-panel {
                padding: 18px 20px;
            }
        }
        .pos-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .pos-panel-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.01em;
        }
        .pos-panel-icon {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }
        .icon-indigo { background: #eef2ff; color: #4f46e5; }
        .icon-emerald { background: #ecfdf5; color: #059669; }
        .icon-amber { background: #fffbeb; color: #d97706; }
        .icon-sky { background: #f0f9ff; color: #0284c7; }

        /* ── DATE & SHIFT STRIP ── */
        .date-bar {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #4f46e5 100%);
            border-radius: var(--radius-lg);
            padding: 12px 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            color: #fff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.22);
        }
        @media (min-width: 1024px) {
            .date-bar {
                grid-template-columns: 1fr 1fr auto;
                align-items: end;
                padding: 14px 18px;
                gap: 14px;
            }
        }
        .date-bar label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.8);
            display: block;
            margin-bottom: 4px;
        }
        .date-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .date-bar input {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            padding: 8px 32px 8px 11px;
            font-family: inherit;
            outline: none;
            width: 100%;
            min-height: 40px;
            transition: background 0.15s, border-color 0.15s;
        }
        .date-bar input:focus {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.65);
        }
        .date-bar input[type="date"],
        .date-bar input[type="time"] {
            color-scheme: dark;
            cursor: pointer;
        }
        .date-bar input[type="date"]::-webkit-calendar-picker-indicator,
        .date-bar input[type="time"]::-webkit-calendar-picker-indicator {
            position: absolute;
            right: 0; top: 0;
            width: 100%; height: 100%;
            margin: 0; padding: 0;
            opacity: 0; cursor: pointer;
        }
        .date-bar-meta {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.88);
            padding-top: 2px;
        }
        @media (min-width: 1024px) {
            .date-bar-meta {
                grid-column: auto;
                flex-direction: column;
                align-items: flex-end;
                justify-content: center;
                padding-top: 0;
                min-width: 120px;
            }
        }

        /* ── FORM FIELDS & INPUTS ── */
        .pos-field {
            position: relative;
            margin-top: 4px;
        }
        .field-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-bottom: 5px;
        }
        .field-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0;
        }
        .input-shell {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-prefix-icon {
            position: absolute;
            left: 12px;
            font-size: 13px;
            color: #94a3b8;
            pointer-events: none;
            z-index: 2;
        }
        .input-currency-prefix {
            position: absolute;
            left: 12px;
            font-size: 13.5px;
            font-weight: 800;
            color: #64748b;
            pointer-events: none;
            z-index: 2;
        }
        .pos-input {
            width: 100%;
            height: 46px;
            border: 1.5px solid var(--border);
            border-radius: 11px;
            padding: 10px 13px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            background: var(--surface);
            color: var(--text);
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            outline: none;
            -webkit-appearance: none;
            font-variant-numeric: tabular-nums;
        }
        .pos-input.has-icon {
            padding-left: 35px;
        }
        .pos-input.has-currency {
            padding-left: 28px;
        }
        .pos-input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.14);
            background: #ffffff;
        }
        .pos-input[readonly]:not(.is-select-trigger) {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #334155;
            cursor: default;
        }
        .pos-input[readonly]:not(.is-select-trigger):focus {
            box-shadow: none;
            border-color: #cbd5e1;
        }
        .pos-input::placeholder {
            color: #94a3b8;
            font-weight: 500;
            font-size: 13px;
        }

        /* Ledger Mini Tiles inside Tenant Panel */
        .ledger-group {
            border-radius: 13px;
            padding: 11px;
            border: 1px solid var(--border);
            background: #f8fafc;
        }
        .ledger-group-amber {
            background: #fffbeb;
            border-color: #fde68a;
        }
        .ledger-group-sky {
            background: #f0f9ff;
            border-color: #bae6fd;
        }
        .ledger-group-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #475569;
        }
        .ledger-group-amber .ledger-group-title { color: #92400e; }
        .ledger-group-sky .ledger-group-title { color: #075985; }
        .ledger-group .pos-input {
            height: 40px;
            font-size: 14px;
            padding: 8px 10px;
            border-radius: 9px;
        }

        /* Quick Fill Chips */
        .quick-fill-chip {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .quick-fill-chip:hover {
            background: #dbeafe;
            transform: translateY(-1px);
        }
        .quick-fill-chip:active { transform: scale(0.95); }
        .quick-fill-chip.hidden { display: none; }

        .btn-fill-all {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-fill-all:hover { background: #d1fae5; }
        .btn-fill-all.hidden { display: none; }

        /* Status Bubble */
        .status-bubble {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.02em;
            margin-top: 8px;
        }
        .status-bubble.hidden { display: none !important; }
        .status-paid {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .status-unpaid {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        /* ── SPACECODE AUTOCOMPLETE ── */
        #spacecode-suggestions {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid #93c5fd;
            border-radius: 13px;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
            z-index: 60;
            max-height: 240px;
            overflow-y: auto;
        }
        .space-suggestion-item {
            padding: 11px 14px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.12s;
        }
        .space-suggestion-item:last-child { border-bottom: none; }
        .space-suggestion-item:hover,
        .space-suggestion-item:active {
            background: #eff6ff;
            color: #1d4ed8;
        }

        /* ── SEGMENTED PAYMENT METHOD TOGGLE ── */
        .method-segment {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .method-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 40px;
            border-radius: 9px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.18s ease;
            font-family: inherit;
        }
        .method-btn.active {
            background: #ffffff;
            color: var(--primary-blue);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }
        .sr-only-select {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            width: 1px;
            height: 1px;
        }

        /* ── CUSTOM SEARCHABLE DROPDOWN (CHARGES) ── */
        .custom-select {
            position: relative;
        }
        .custom-select .dropdown-menu {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            width: 100%;
            min-width: 220px;
            background: #ffffff;
            border: 1.5px solid #93c5fd;
            border-radius: 13px;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.18);
            z-index: 65 !important;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .custom-select .dropdown-menu.hidden {
            display: none !important;
        }
        .custom-select .search-box-wrap {
            padding: 8px 10px;
            background: #eff6ff;
            border-bottom: 1px solid #dbeafe;
        }
        .custom-select .search-input {
            width: 100%;
            padding: 7px 10px;
            font-size: 13px;
            background: #ffffff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            outline: none;
            color: #1e293b;
            font-family: inherit;
        }
        .custom-select .search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18);
        }
        .custom-select .dropdown-options {
            max-height: 210px;
            overflow-y: auto;
            background: #ffffff;
        }
        .custom-select .dropdown-option {
            padding: 10px 13px;
            font-size: 13px;
            color: #334155;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f8fafc;
            transition: background 0.12s;
        }
        .custom-select .dropdown-option:hover {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
        }
        .custom-select .dropdown-option.selected {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
        }
        .custom-select .dropdown-clear {
            padding: 9px 13px;
            font-size: 12.5px;
            font-weight: 700;
            color: #e11d48;
            cursor: pointer;
            background: #fff1f2;
            border-bottom: 1px solid #ffe4e6;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── STICKY LIVE RECEIPT / SUMMARY PANEL ── */
        .receipt-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .receipt-head {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .receipt-head-title {
            font-size: 13.5px;
            font-weight: 800;
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .receipt-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.25);
            border: 1px solid rgba(96, 165, 250, 0.4);
            color: #93c5fd;
            font-variant-numeric: tabular-nums;
        }
        .receipt-body {
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .receipt-tenant-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 11px 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .receipt-tenant-name {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
        }
        .receipt-tenant-sub {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 2px;
        }
        .receipt-lines {
            display: flex;
            flex-direction: column;
            gap: 7px;
            max-height: 220px;
            overflow-y: auto;
        }
        .receipt-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .receipt-line:last-child { border-bottom: none; padding-bottom: 0; }
        .receipt-line-label { color: var(--text-secondary); font-weight: 600; }
        .receipt-line-val { font-weight: 800; color: var(--text); font-variant-numeric: tabular-nums; }

        .projected-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .projected-tile {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 10px;
        }
        .projected-tile-lbl {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        .projected-tile-val {
            font-size: 13px;
            font-weight: 800;
            margin-top: 2px;
            font-variant-numeric: tabular-nums;
        }

        .total-hero-box {
            background: var(--gradient);
            border-radius: 14px;
            padding: 14px 16px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
        }
        .total-hero-lbl {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: 0.85;
        }
        .total-hero-amt {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
            margin-top: 2px;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            border: none;
            border-radius: 13px;
            padding: 15px 18px;
            font-size: 15.5px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            box-shadow: 0 6px 18px rgba(5, 150, 105, 0.3);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }
        .submit-btn:hover {
            filter: brightness(1.04);
            box-shadow: 0 8px 22px rgba(5, 150, 105, 0.38);
        }
        .submit-btn:active { transform: scale(0.98); }

        .btn-add-charge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px dashed #93c5fd;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-add-charge:hover {
            background: #dbeafe;
            border-style: solid;
        }

        /* ── MODALS & SIDE DRAWER ── */
        #confirmationModal, #welcomeModal, #logoutConfirmModal, #viewTransactionsModal, #duplicatedTransactionsModal {
            z-index: 99999 !important;
        }
        .mobile-modal {
            max-height: 92vh;
            overflow-y: auto;
            border-radius: 20px;
            animation: slideUp 0.22s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(24px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.32s ease-out; }
        .side-menu {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        }

        /* Custom Confirmation Modal Badges */
        .cm-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .cm-badge-cash { background: #dcfce7; color: #166534; }
        .cm-badge-cheque { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>

    <!-- MOBILE TOP BAR (hidden on desktop >= 1024px via modern-bottom-nav.css) -->
    <nav class="top-bar">
        <div class="brand">
            <div class="brand-icon">
                <i class="fas fa-cash-register"></i>
            </div>
            <div style="min-width:0;">
                <div class="brand-name"><?php echo htmlspecialchars($branch); ?></div>
                <div class="brand-sub">
                    <span>POS</span>
                    <span>&bull;</span>
                    <span>#<?php echo $nextReceiptDisplay; ?></span>
                </div>
            </div>
        </div>
        <div class="top-bar-right">
            <div class="mobile-live-pill" title="Current Total Collection">
                <span style="font-size:10px;opacity:0.65;">TOT</span>
                <span id="mobileHeaderTotal">&#x20B1;0.00</span>
            </div>
            <button class="user-pill" id="burger-menu-btn" type="button">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($username); ?></span>
                <i class="fas fa-chevron-down" style="font-size:9px;opacity:.75;"></i>
            </button>
        </div>
    </nav>

    <!-- MOBILE SIDE DRAWER -->
    <div id="side-nav" class="hidden fixed inset-0 z-50">
        <div class="flex h-full">
            <div id="side-nav-backdrop" class="bg-black opacity-50 flex-1 h-full"></div>
            <div class="side-menu w-72 h-full transform transition-transform duration-300 ease-in-out translate-x-full ml-auto">
                <div class="flex justify-between items-center p-4" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
                    <div class="flex items-center gap-3">
                        <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#4f46e5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="text-white font-bold"><?php echo htmlspecialchars($username); ?></div>
                            <div class="text-blue-200 text-xs"><?php echo htmlspecialchars($branch); ?></div>
                        </div>
                    </div>
                    <button id="close-btn" class="text-white p-2 rounded-full" type="button" style="background:rgba(255,255,255,0.08);">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="flex flex-col mt-4 px-3 gap-1">
                    <a href="user.php" class="text-white text-sm font-semibold py-3 px-4 flex items-center gap-3 rounded-xl" style="background:rgba(59,130,246,0.22);">
                        <i class="fas fa-plus-circle w-5 text-center text-blue-300"></i> New Collection
                    </a>
                    <a href="transactions.php" class="text-gray-200 text-sm font-semibold py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-list-ul w-5 text-center text-blue-300"></i> Transactions
                    </a>
                    <a href="reprint.php" class="text-gray-200 text-sm font-semibold py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-print w-5 text-center text-blue-300"></i> Reprint Receipt
                    </a>
                    <a href="void_transaction.php" class="text-gray-200 text-sm font-semibold py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-ban w-5 text-center text-yellow-300"></i> Void Transaction
                    </a>
                    <a href="monitoring.php" class="text-gray-200 text-sm font-semibold py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-chart-pie w-5 text-center text-indigo-300"></i> Monitoring
                    </a>
                    <a href="downloads/lclopez-collection.apk" download class="text-gray-200 text-sm font-semibold py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-download w-5 text-center text-green-300"></i> Download App
                    </a>
                </div>
                <div class="absolute bottom-0 left-0 right-0 p-4" style="border-top: 1px solid rgba(255,255,255,0.08);">
                    <a href="logout.php" class="text-white text-sm font-bold py-3 px-4 flex items-center gap-3 rounded-xl" style="background:rgba(239,68,68,0.18);color:#fca5a5;">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'modern_bottom_nav.php'; ?>

    <div class="pos-page-wrap animate-fadeIn">
        <!-- DESKTOP WORKSTATION HEADER (visible >= 1024px) -->
        <header class="pos-desktop-header">
            <div class="dh-left">
                <div class="dh-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div>
                    <h1 class="dh-title">Collection POS Terminal</h1>
                    <div class="dh-meta">
                        <span class="dh-chip"><i class="fas fa-store"></i> <?php echo htmlspecialchars($branch); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-hashtag"></i> Next Receipt #<?php echo $nextReceiptDisplay; ?></span>
                    </div>
                </div>
            </div>
            <div class="dh-right">
                <div class="progress-widget" title="Today's Branch Collection Progress">
                    <div>
                        <div class="progress-widget-label">Today's Progress</div>
                        <div class="progress-widget-val" id="desktopProgressText">Loading...</div>
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" id="desktopProgressFill"></div>
                        </div>
                    </div>
                    <a href="monitoring.php" style="font-size:11px;font-weight:700;color:#2563eb;text-decoration:none;padding:5px 9px;background:#eff6ff;border-radius:8px;">
                        View
                    </a>
                </div>
                <button type="button" onclick="resetFullPosForm()" class="btn-ghost-reset" title="Clear all entered fields (Esc)">
                    <i class="fas fa-rotate-left"></i> Reset Form
                </button>
            </div>
        </header>

        <form id="collectionForm" method="post" action="user.php" class="w-full">
            <input readonly hidden type="text" name="collector" id="collector" value="<?php echo htmlspecialchars($lname); ?>">
            <input name="branch" id="branch" type="hidden" value="<?php echo htmlspecialchars($branch); ?>">

            <div class="pos-workspace-grid">
                <!-- LEFT / PRIMARY ENTRY COLUMN -->
                <div class="pos-main-col">

                    <!-- DATE & TIME BAR -->
                    <div class="date-bar">
                        <div>
                            <label for="collected_date_part">Collection Date</label>
                            <div class="date-input-wrapper">
                                <input type="text" id="collected_date_part" name="collected_date_part"
                                    value="<?php echo date('Y-m-d'); ?>"
                                    onchange="updateDateTime(true)" oninput="updateDateTime(true)">
                                <i class="far fa-calendar" style="position:absolute; right:11px; color:#fff; pointer-events:none; font-size:13px;"></i>
                            </div>
                        </div>
                        <div>
                            <label for="collected_time_part">Time</label>
                            <div class="date-input-wrapper">
                                <input type="text" id="collected_time_part" name="collected_time_part"
                                    value="<?php echo date('H:i:s'); ?>" step="1"
                                    onchange="window.isManualTimeSet = true; updateDateTime(false);" oninput="window.isManualTimeSet = true; updateDateTime(false);">
                                <i class="far fa-clock" style="position:absolute; right:11px; color:#fff; pointer-events:none; font-size:13px;"></i>
                            </div>
                        </div>
                        <div class="date-bar-meta">
                            <span id="mobileProgressText" class="lg:hidden"><i class="fas fa-chart-pie mr-1"></i> Today: ...</span>
                            <span style="opacity:0.8;">Asia/Manila</span>
                        </div>
                        <input type="hidden" id="collected_date" name="collected_date" value="<?php echo date('Y-m-d\TH:i:s'); ?>">
                    </div>

                    <!-- CARD 1: TENANT LOOKUP & ACCOUNT LEDGER -->
                    <section class="pos-panel">
                        <div class="pos-panel-header">
                            <div class="pos-panel-title">
                                <span class="pos-panel-icon icon-indigo"><i class="fas fa-user-tag"></i></span>
                                <span>Tenant Lookup &amp; Account Ledger</span>
                            </div>
                            <button type="button" onclick="resetFullPosForm()" class="quick-fill-chip" style="background:#f8fafc;color:#64748b;border-color:#e2e8f0;">
                                <i class="fas fa-eraser"></i> Clear
                            </button>
                        </div>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                            <div class="pos-field col-span-1">
                                <div class="field-top-row">
                                    <label class="field-label" for="spacecode-input">Space Code *</label>
                                </div>
                                <div class="relative">
                                    <div class="input-shell">
                                        <i class="fas fa-search input-prefix-icon"></i>
                                        <input required autocomplete="off"
                                            class="pos-input has-icon"
                                            id="spacecode-input" type="text" name="spacecode" placeholder="Search space..."
                                            oninput="suggestSpaceCode(this.value)">
                                    </div>
                                    <div id="spacecode-suggestions" class="hidden"></div>
                                </div>
                            </div>

                            <div class="pos-field col-span-1">
                                <div class="field-top-row">
                                    <label class="field-label" for="tenantcode">Tenant Code *</label>
                                </div>
                                <input required class="pos-input" id="tenantcode" type="text" name="tenantcode" placeholder="Tenant code">
                            </div>

                            <div class="pos-field col-span-2">
                                <div class="field-top-row">
                                    <label class="field-label" for="tenantname">Tenant Name *</label>
                                </div>
                                <input required class="pos-input font-bold" id="tenantname" type="text" name="tenantname" placeholder="Full name will appear here">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <span id="spacecode-error" class="text-xs font-semibold" style="color:#e11d48;"></span>
                            <div id="payment-status-container" class="hidden status-bubble">
                                <i id="payment-status-icon" class="fas"></i>
                                <span id="payment-status-text"></span>
                            </div>
                        </div>

                        <!-- Balances Grid -->
                        <div class="ledger-group mb-3">
                            <div class="ledger-group-title">
                                <i class="fas fa-coins" style="color:#4f46e5;"></i> Rent Balances
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="field-label mb-1" for="rent" style="font-size:10px;">Daily Rent</label>
                                    <input class="pos-input" id="rent" type="text" name="rent" placeholder="0.00" oninput="calculateNewBalance()">
                                </div>
                                <div>
                                    <label class="field-label mb-1" for="rentbal" style="font-size:10px;">Rent Bal</label>
                                    <input class="pos-input" id="rentbal" type="text" name="rentbal" placeholder="0.00" oninput="calculateNewBalance()">
                                </div>
                                <div>
                                    <label class="field-label mb-1" for="runningbal" style="font-size:10px;">Arrear Bal</label>
                                    <input class="pos-input" id="runningbal" type="text" name="runningbal" placeholder="0.00" oninput="calculateNewBalance()">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="ledger-group ledger-group-amber">
                                <div class="ledger-group-title">
                                    <i class="fas fa-bolt" style="color:#d97706;"></i> Electricity Balances
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="field-label mb-1" for="elecarrear" style="font-size:10px;">Arrears</label>
                                        <input class="pos-input" id="elecarrear" type="text" name="elecarrear" placeholder="0.00" readonly oninput="calculateNewBalance()">
                                    </div>
                                    <div>
                                        <label class="field-label mb-1" for="elecbal" style="font-size:10px;">Current</label>
                                        <input class="pos-input" id="elecbal" type="text" name="elecbal" placeholder="0.00" readonly oninput="calculateNewBalance()">
                                    </div>
                                    <div>
                                        <label class="field-label mb-1" for="electotal" style="font-size:10px;color:#92400e;">Total</label>
                                        <input class="pos-input font-bold" id="electotal" type="text" name="electotal" placeholder="0.00" readonly style="background:#fef3c7 !important;color:#78350f;border-color:#fcd34d !important;">
                                    </div>
                                </div>
                            </div>

                            <div class="ledger-group ledger-group-sky">
                                <div class="ledger-group-title">
                                    <i class="fas fa-tint" style="color:#0284c7;"></i> Water Balances
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="field-label mb-1" for="waterarrear" style="font-size:10px;">Arrears</label>
                                        <input class="pos-input" id="waterarrear" type="text" name="waterarrear" placeholder="0.00" readonly oninput="calculateNewBalance()">
                                    </div>
                                    <div>
                                        <label class="field-label mb-1" for="waterbal" style="font-size:10px;">Current</label>
                                        <input class="pos-input" id="waterbal" type="text" name="waterbal" placeholder="0.00" readonly oninput="calculateNewBalance()">
                                    </div>
                                    <div>
                                        <label class="field-label mb-1" for="watertotal" style="font-size:10px;color:#075985;">Total</label>
                                        <input class="pos-input font-bold" id="watertotal" type="text" name="watertotal" placeholder="0.00" readonly style="background:#e0f2fe !important;color:#0c4a6e;border-color:#7dd3fc !important;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- CARD 2: PAYMENT ENTRY -->
                    <section class="pos-panel">
                        <div class="pos-panel-header">
                            <div class="pos-panel-title">
                                <span class="pos-panel-icon icon-emerald"><i class="fas fa-hand-holding-usd"></i></span>
                                <span>Payment Collection</span>
                            </div>
                            <button type="button" id="fillAllDuesBtn" onclick="fillAllPendingDues()" class="btn-fill-all hidden">
                                <i class="fas fa-magic"></i> Fill Dues
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="paidrent" style="color:#047857;">Rent</label>
                                    <button type="button" id="qf-paidrent" class="quick-fill-chip hidden"></button>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                                        class="pos-input has-currency font-bold" style="color:#047857;"
                                        id="paidrent" type="text" name="paidrent" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>

                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="paidbal" style="color:#6d28d9;">Rent Arrear</label>
                                    <button type="button" id="qf-paidbal" class="quick-fill-chip hidden"></button>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                                        class="pos-input has-currency font-bold" style="color:#6d28d9;"
                                        id="paidbal" type="text" name="paidbal" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>

                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="paidelec" style="color:#b45309;">Electricity</label>
                                    <button type="button" id="qf-paidelec" class="quick-fill-chip hidden"></button>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                                        class="pos-input has-currency font-bold" style="color:#b45309;"
                                        id="paidelec" type="text" name="paidelec" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>

                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="paidelecarrear" style="color:#92400e;">Elec Arrear</label>
                                    <button type="button" id="qf-paidelecarrear" class="quick-fill-chip hidden"></button>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                                        class="pos-input has-currency font-bold" style="color:#92400e;"
                                        id="paidelecarrear" type="text" name="paidelecarrear" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>

                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="paidwater" style="color:#0369a1;">Water</label>
                                    <button type="button" id="qf-paidwater" class="quick-fill-chip hidden"></button>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                                        class="pos-input has-currency font-bold" style="color:#0369a1;"
                                        id="paidwater" type="text" name="paidwater" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>

                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="paidwaterarrear" style="color:#075985;">Water Arrear</label>
                                    <button type="button" id="qf-paidwaterarrear" class="quick-fill-chip hidden"></button>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)"
                                        class="pos-input has-currency font-bold" style="color:#075985;"
                                        id="paidwaterarrear" type="text" name="paidwaterarrear" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method Segmented Switch -->
                        <div class="pos-field pt-1">
                            <div class="field-top-row">
                                <label class="field-label" for="payment_method">Payment Method</label>
                            </div>
                            <div class="method-segment" role="group" aria-label="Payment Method">
                                <button type="button" class="method-btn active" id="methodBtnCash" onclick="setPaymentMethod('Cash')">
                                    <i class="fas fa-money-bill-wave" style="color:#059669;"></i> Cash
                                </button>
                                <button type="button" class="method-btn" id="methodBtnCheque" onclick="setPaymentMethod('Cheque')">
                                    <i class="fas fa-money-check" style="color:#d97706;"></i> Cheque
                                </button>
                            </div>
                            <select class="sr-only-select" id="payment_method" name="payment_method" onchange="toggleChequeInput()" required tabindex="-1">
                                <option value="Cash" selected>Cash</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>

                        <div id="cheque_container" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 pt-3" style="border-top: 1px dashed #e2e8f0;">
                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="cheque_number">Cheque Number *</label>
                                </div>
                                <input class="pos-input" id="cheque_number" type="text" name="cheque_number" placeholder="Enter cheque #">
                            </div>
                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="cheque_payee">Payee Name *</label>
                                </div>
                                <input class="pos-input" id="cheque_payee" type="text" name="cheque_payee" placeholder="Enter payee name">
                            </div>
                        </div>
                    </section>

                    <!-- CARD 3: ADDITIONAL CHARGES -->
                    <section class="pos-panel">
                        <div class="pos-panel-header">
                            <div class="pos-panel-title">
                                <span class="pos-panel-icon icon-sky"><i class="fas fa-file-invoice"></i></span>
                                <span>Additional Charges</span>
                            </div>
                            <button type="button" id="addChargeButton" class="btn-add-charge">
                                <i class="fas fa-plus"></i> Add Row
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="chargecusa">CUSA</label>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input has-currency" id="chargecusa" type="text" name="chargecusa" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>
                            <div class="pos-field">
                                <div class="field-top-row">
                                    <label class="field-label" for="chargeac">Aircon</label>
                                </div>
                                <div class="input-shell">
                                    <span class="input-currency-prefix">&#x20B1;</span>
                                    <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input has-currency" id="chargeac" type="text" name="chargeac" placeholder="0.00" inputmode="decimal">
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3">
                            <div class="grid grid-cols-2 gap-3 items-end">
                                <div class="pos-field">
                                    <div class="field-top-row">
                                        <label class="field-label" for="chargeothers">Other Charge</label>
                                    </div>
                                    <div class="relative custom-select" id="initialChargeSelect">
                                        <input type="text" class="pos-input is-select-trigger cursor-pointer" placeholder="Select charge type" readonly onclick="openCustomSelect(this)" style="padding-right:32px;">
                                        <i class="fas fa-chevron-down" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:11px;color:#94a3b8;pointer-events:none;"></i>
                                        <input type="hidden" id="chargeothers" name="chargeothers[]">
                                        <div class="dropdown-menu hidden">
                                            <div class="search-box-wrap">
                                                <input type="text" class="search-input" placeholder="Search charges...">
                                            </div>
                                            <div class="dropdown-options"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pos-field">
                                    <div class="field-top-row">
                                        <label class="field-label" for="otheramount">Amount</label>
                                    </div>
                                    <div class="input-shell">
                                        <span class="input-currency-prefix">&#x20B1;</span>
                                        <input oninput="formatNumberAndCalculateNewBalance(this)" class="pos-input has-currency" id="otheramount" type="text" name="otheramount[]" placeholder="0.00" inputmode="decimal">
                                    </div>
                                </div>
                            </div>
                            <div id="additionalChargesContainer" class="flex flex-col gap-3"></div>
                        </div>
                    </section>
                </div>

                <!-- RIGHT STICKY POS RECEIPT / CHECKOUT SUMMARY COLUMN -->
                <aside class="pos-summary-col">
                    <div class="receipt-card">
                        <div class="receipt-head">
                            <div class="receipt-head-title">
                                <i class="fas fa-receipt" style="color:#60a5fa;"></i>
                                <span>Live POS Summary</span>
                            </div>
                            <span class="receipt-badge">#<?php echo $nextReceiptDisplay; ?></span>
                        </div>

                        <div class="receipt-body">
                            <!-- Active Tenant Strip -->
                            <div class="receipt-tenant-box">
                                <div style="min-width:0;">
                                    <div class="receipt-tenant-name" id="liveTenantName">No tenant selected</div>
                                    <div class="receipt-tenant-sub" id="liveTenantMeta">Search a space code to begin</div>
                                </div>
                                <span id="liveMethodChip" class="cm-badge cm-badge-cash">Cash</span>
                            </div>

                            <!-- Live Line Items -->
                            <div>
                                <div class="field-label mb-2" style="font-size:10px;">Itemized Collection</div>
                                <div class="receipt-lines" id="liveSummaryItems">
                                    <div style="font-size:12px;color:#94a3b8;font-style:italic;text-align:center;padding:10px 0;">
                                        Enter payment amounts to preview receipt
                                    </div>
                                </div>
                            </div>

                            <!-- Projected Post-Payment Balances -->
                            <div>
                                <div class="field-label mb-2" style="font-size:10px;">Projected Balances After Payment</div>
                                <div class="projected-grid">
                                    <div class="projected-tile">
                                        <div class="projected-tile-lbl">New Rent Bal</div>
                                        <div class="projected-tile-val" id="liveNewRent" style="color:#334155;">&#x20B1;0.00</div>
                                    </div>
                                    <div class="projected-tile">
                                        <div class="projected-tile-lbl">New Arrear Bal</div>
                                        <div class="projected-tile-val" id="liveNewArrear" style="color:#334155;">&#x20B1;0.00</div>
                                    </div>
                                    <div class="projected-tile" style="background:#fffbeb;border-color:#fde68a;">
                                        <div class="projected-tile-lbl" style="color:#92400e;">Elec Remaining</div>
                                        <div class="projected-tile-val" id="liveNewElec" style="color:#78350f;">&#x20B1;0.00</div>
                                    </div>
                                    <div class="projected-tile" style="background:#f0f9ff;border-color:#bae6fd;">
                                        <div class="projected-tile-lbl" style="color:#075985;">Water Remaining</div>
                                        <div class="projected-tile-val" id="liveNewWater" style="color:#0c4a6e;">&#x20B1;0.00</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Grand Total Hero -->
                            <div class="total-hero-box">
                                <div>
                                    <div class="total-hero-lbl">Total Collection</div>
                                    <div class="total-hero-amt" id="liveGrandTotal">&#x20B1;0.00</div>
                                </div>
                                <div style="text-align:right;font-size:11px;font-weight:700;opacity:0.88;">
                                    <div id="liveSummaryDate"><?php echo date('M d, Y'); ?></div>
                                    <div id="liveSummaryCollector"><?php echo htmlspecialchars($username); ?></div>
                                </div>
                            </div>

                            <!-- Submit & Reset CTA -->
                            <div class="flex flex-col gap-2">
                                <button class="submit-btn" type="submit" id="submitButton">
                                    <i class="fas fa-check-circle"></i> Review &amp; Submit Payment
                                </button>
                                <button type="button" onclick="resetFullPosForm()" class="lg:hidden btn-ghost-reset" style="width:100%;justify-content:center;">
                                    <i class="fas fa-rotate-left"></i> Clear Entry
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Hidden Calculated Inputs for Form Submission -->
            <input readonly hidden id="total" type="text" name="total" value="0">
            <input readonly hidden id="newrentbalance" type="text" name="newrentbalance" value="0">
            <input readonly hidden id="newbalance" type="text" name="newbalance" value="0">
            <input readonly hidden id="newelecbal" type="text" name="newelecbal" value="0">
            <input readonly hidden id="newelecarrear" type="text" name="newelecarrear" value="0">
            <input readonly hidden id="newwaterbal" type="text" name="newwaterbal" value="0">
            <input readonly hidden id="newwaterarrear" type="text" name="newwaterarrear" value="0">
        </form>
    </div>

    <!-- Welcome Modal -->
    <div id="welcomeModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="mobile-modal bg-white rounded-2xl p-6 max-w-sm w-full relative z-10 shadow-2xl text-center">
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-2xl mb-4" style="background:#eff6ff;color:#2563eb;">
                    <i class="fas fa-handshake text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Welcome, <?php echo htmlspecialchars($username); ?>!</h3>
                <p class="text-sm text-gray-500 mt-1 mb-5">
                    Ready to process collections for <?php echo htmlspecialchars($branch); ?>.
                </p>
                <button type="button" onclick="document.getElementById('welcomeModal').classList.add('hidden')" class="w-full py-2.5 px-4 rounded-xl text-white font-bold text-sm" style="background:var(--gradient);">
                    Start Collecting
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 flex items-center justify-center hidden p-4" style="z-index: 99999; background: rgba(15, 23, 42, 0.68); backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl p-5 max-w-md w-full flex flex-col shadow-2xl overflow-hidden border border-gray-100 animate-fadeIn" style="max-height: 88vh;">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 flex-shrink-0">
                <h2 class="text-base font-extrabold text-gray-900 flex items-center gap-2 m-0">
                    <span style="width:30px;height:30px;border-radius:9px;background:#eff6ff;color:#2563eb;display:inline-flex;align-items:center;justify-content:center;">
                        <i class="fas fa-receipt"></i>
                    </span>
                    Confirm Payment Summary
                </h2>
                <span style="font-size:11px;font-weight:800;color:#64748b;">#<?php echo $nextReceiptDisplay; ?></span>
            </div>

            <div id="confirmationSummary" class="my-3 overflow-y-auto pr-1 flex-1 space-y-2.5 text-xs"></div>

            <div class="flex justify-between gap-3 pt-3 border-t border-gray-100 flex-shrink-0">
                <button type="button" id="cancelButton" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-4 rounded-xl flex items-center justify-center text-xs transition">
                    <i class="fas fa-arrow-left mr-1.5"></i> Edit
                </button>
                <button type="button" id="confirmButton" class="flex-1 text-white font-bold py-3 px-4 rounded-xl flex items-center justify-center text-xs shadow-lg transition" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-check mr-1.5"></i> Confirm &amp; Submit
                </button>
            </div>
        </div>
    </div>

    <!-- Logout Modern Modal -->
    <div id="logoutConfirmModal" class="fixed inset-0 flex items-center justify-center hidden animate-fadeIn" style="z-index: 99999; background: rgba(15, 23, 42, 0.55);">
        <div class="mobile-modal bg-white rounded-3xl p-8 max-w-xs w-full mx-4 shadow-2xl relative overflow-hidden">
            <div class="text-center relative z-10">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-red-500 mb-5 shadow-lg">
                    <i class="fas fa-sign-out-alt text-white text-2xl"></i>
                </div>
                <h3 class="text-xl font-extrabold text-gray-900 mb-2">Going so soon?</h3>
                <p class="text-gray-500 text-sm mb-6 font-medium">Are you sure you want to exit and end your collection session?</p>
                <div class="flex flex-col gap-3">
                    <a href="logout.php" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3.5 px-6 rounded-2xl shadow-lg flex items-center justify-center gap-2 text-sm">
                        <i class="fas fa-door-open"></i> YES, EXIT
                    </a>
                    <button type="button" onclick="hideLogoutModal()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-3.5 px-6 rounded-2xl text-sm">
                        KEEP WORKING
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="function.js"></script>
    <script src="nav_badge.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        function showLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.remove('hidden');
        }
        function hideLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.add('hidden');
        }

        window.isManualTimeSet = false;

        function updateDateTime(isDateChange = false) {
            const dateInput = document.getElementById('collected_date_part');
            const timeInput = document.getElementById('collected_time_part');
            const hiddenDate = document.getElementById('collected_date');
            if (!dateInput || !timeInput || !hiddenDate) return;

            const datePart = dateInput.value;
            let timePart = timeInput.value;

            if (datePart) {
                if (timePart) {
                    const parts = timePart.split(':');
                    if (parts.length === 2) {
                        timePart = `${timePart}:00`;
                    }
                } else {
                    const now = new Date();
                    timePart = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
                }
                hiddenDate.value = `${datePart}T${timePart}`;

                const liveDateEl = document.getElementById('liveSummaryDate');
                if (liveDateEl) liveDateEl.textContent = datePart;

                if (isDateChange) {
                    var spacecode = document.getElementById("spacecode-input").value;
                    if (spacecode && spacecode !== "") {
                        fetchTenantDetails(spacecode);
                    }
                    loadPosBranchProgress();
                }
            }
        }

        function updateTimeWithSeconds() {
            if (window.isManualTimeSet) return;
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');

            const timeInput = document.getElementById('collected_time_part');
            if (timeInput) {
                if (timeInput.type === 'time') {
                    timeInput.value = `${hours}:${minutes}`;
                } else {
                    timeInput.value = `${hours}:${minutes}:${seconds}`;
                }
            }
            updateDateTime(false);
        }

        let timeUpdateInterval;

        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function formatCurrencyText(value) {
            const num = parseFloat(value || 0);
            return (num < 0 ? '-₱' + numberWithCommas(Math.abs(num).toFixed(2)) : '₱' + numberWithCommas(num.toFixed(2)));
        }

        function formatCurrency(value) {
            const num = parseFloat(value || 0);
            return (num < 0 ? '-&#x20B1;' + numberWithCommas(Math.abs(num).toFixed(2)) : '&#x20B1;' + numberWithCommas(num.toFixed(2)));
        }

        function parseNum(id) {
            const el = document.getElementById(id);
            if (!el) return 0;
            return parseFloat(String(el.value || '').replace(/,/g, '')) || 0;
        }

        function calculateNewBalance() {
            var dailyRent = parseNum("rent");
            var paidRent = parseNum("paidrent");
            var paidBalance = parseNum("paidbal");
            var rentBalance = parseNum("rentbal");
            var runningBalance = parseNum("runningbal");

            var elecarrear = parseNum("elecarrear");
            var elecbal = parseNum("elecbal");
            var paidElec = parseNum("paidelec");
            var paidElecArrear = parseNum("paidelecarrear");

            var waterarrear = parseNum("waterarrear");
            var waterbal = parseNum("waterbal");
            var paidWater = parseNum("paidwater");
            var paidWaterArrear = parseNum("paidwaterarrear");

            // Normalization: Positive initial balances from DB represent owed debt (treated negatively)
            var baseRentBal = (rentBalance > 0) ? -rentBalance : rentBalance;
            var baseRunningBal = (runningBalance > 0) ? -runningBalance : runningBalance;
            var baseElecArrear = (elecarrear > 0) ? -elecarrear : elecarrear;
            var baseElecBal = (elecbal > 0) ? -elecbal : elecbal;
            var baseWaterArrear = (waterarrear > 0) ? -waterarrear : waterarrear;
            var baseWaterBal = (waterbal > 0) ? -waterbal : waterbal;

            var rawElecTotal = (elecarrear < 0 || elecbal < 0) ? (baseElecArrear + baseElecBal) : (elecarrear + elecbal);
            document.getElementById("electotal").value = parseFloat(rawElecTotal.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            var rawWaterTotal = (waterarrear < 0 || waterbal < 0) ? (baseWaterArrear + baseWaterBal) : (waterarrear + waterbal);
            document.getElementById("watertotal").value = parseFloat(rawWaterTotal.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            var chargecusa = parseNum("chargecusa");
            var chargeac = parseNum("chargeac");
            var totalCharges = chargecusa + chargeac;

            var otherAmount = parseNum("otheramount");
            var otherType = document.getElementById("chargeothers").value;
            if (otherType && otherAmount > 0) {
                totalCharges += otherAmount;
            }

            var additionalCharges = document.querySelectorAll('#additionalChargesContainer input[name="otheramount[]"]');
            additionalCharges.forEach(function (input) {
                var amount = parseFloat(input.value.replace(/,/g, '')) || 0;
                totalCharges += amount;
            });

            var totalAmountPaid = paidRent + paidBalance + paidElec + paidElecArrear + paidWater + paidWaterArrear;

            var newRentBalance = baseRentBal + paidRent - dailyRent;
            var newRunningBalance = baseRunningBal + paidBalance;
            var newElecBal = baseElecBal + paidElec;
            var newElecArrear = baseElecArrear + paidElecArrear;
            var newWaterBal = baseWaterBal + paidWater;
            var newWaterArrear = baseWaterArrear + paidWaterArrear;
            var total = totalAmountPaid + totalCharges;

            document.getElementById("newrentbalance").value = parseFloat(newRentBalance.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById("newbalance").value = parseFloat(newRunningBalance.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById("newelecbal").value = parseFloat(newElecBal.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById("newelecarrear").value = parseFloat(newElecArrear.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById("newwaterbal").value = parseFloat(newWaterBal.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById("newwaterarrear").value = parseFloat(newWaterArrear.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById("total").value = parseFloat(total.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            updateLiveSummaryPanel({
                paidRent, paidBalance, paidElec, paidElecArrear, paidWater, paidWaterArrear,
                chargecusa, chargeac,
                newRentBalance, newRunningBalance,
                newElecTotal: newElecBal + newElecArrear,
                newWaterTotal: newWaterBal + newWaterArrear,
                total
            });
        }

        function updateLiveSummaryPanel(calc) {
            const tenantName = (document.getElementById('tenantname').value || '').trim();
            const spaceCode = (document.getElementById('spacecode-input').value || '').trim();
            const tenantCode = (document.getElementById('tenantcode').value || '').trim();
            const method = (document.getElementById('payment_method').value || 'Cash');

            const nameEl = document.getElementById('liveTenantName');
            const metaEl = document.getElementById('liveTenantMeta');
            if (nameEl) nameEl.textContent = tenantName || 'No tenant selected';
            if (metaEl) {
                metaEl.textContent = spaceCode
                    ? `Space ${spaceCode}${tenantCode ? ' • Code ' + tenantCode : ''}`
                    : 'Search a space code to begin';
            }

            const methodChip = document.getElementById('liveMethodChip');
            if (methodChip) {
                methodChip.textContent = method;
                methodChip.className = 'cm-badge ' + (method === 'Cheque' ? 'cm-badge-cheque' : 'cm-badge-cash');
            }

            // Build itemized breakdown
            const lines = [];
            if (calc.paidRent > 0) lines.push({ label: 'Daily Rent', val: calc.paidRent, color: '#047857' });
            if (calc.paidBalance > 0) lines.push({ label: 'Rent Arrear', val: calc.paidBalance, color: '#6d28d9' });
            if (calc.paidElec > 0) lines.push({ label: 'Electricity', val: calc.paidElec, color: '#b45309' });
            if (calc.paidElecArrear > 0) lines.push({ label: 'Elec Arrear', val: calc.paidElecArrear, color: '#92400e' });
            if (calc.paidWater > 0) lines.push({ label: 'Water', val: calc.paidWater, color: '#0369a1' });
            if (calc.paidWaterArrear > 0) lines.push({ label: 'Water Arrear', val: calc.paidWaterArrear, color: '#075985' });
            if (calc.chargecusa > 0) lines.push({ label: 'CUSA', val: calc.chargecusa, color: '#334155' });
            if (calc.chargeac > 0) lines.push({ label: 'Aircon', val: calc.chargeac, color: '#334155' });

            const otherSelects = document.querySelectorAll('input[name="chargeothers[]"]');
            const otherAmounts = document.querySelectorAll('input[name="otheramount[]"]');
            for (let i = 0; i < otherSelects.length; i++) {
                const cName = (otherSelects[i].value || '').trim();
                const cAmt = parseFloat((otherAmounts[i]?.value || '').replace(/,/g, '')) || 0;
                if (cName && cAmt > 0) {
                    lines.push({ label: cName, val: cAmt, color: '#334155' });
                }
            }

            const itemsContainer = document.getElementById('liveSummaryItems');
            if (itemsContainer) {
                if (lines.length === 0) {
                    itemsContainer.innerHTML = '<div style="font-size:12px;color:#94a3b8;font-style:italic;text-align:center;padding:10px 0;">Enter payment amounts to preview receipt</div>';
                } else {
                    itemsContainer.innerHTML = lines.map(item => `
                        <div class="receipt-line">
                            <span class="receipt-line-label">${item.label}</span>
                            <span class="receipt-line-val" style="color:${item.color};">${formatCurrencyText(item.val)}</span>
                        </div>
                    `).join('');
                }
            }

            // Projected balances
            const setProj = (id, val, defaultColor) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.textContent = formatCurrencyText(val);
                el.style.color = val < 0 ? '#dc2626' : defaultColor;
            };
            setProj('liveNewRent', calc.newRentBalance, '#059669');
            setProj('liveNewArrear', calc.newRunningBalance, '#6d28d9');
            setProj('liveNewElec', calc.newElecTotal, '#92400e');
            setProj('liveNewWater', calc.newWaterTotal, '#075985');

            const totalStr = formatCurrencyText(calc.total);
            const heroTotal = document.getElementById('liveGrandTotal');
            const mobTotal = document.getElementById('mobileHeaderTotal');
            if (heroTotal) heroTotal.textContent = totalStr;
            if (mobTotal) mobTotal.textContent = totalStr;
        }

        function formatNumberAndCalculateNewBalance(input) {
            let cleanValue = input.value.replace(/[^\d.-]/g, '');
            let formattedValue = cleanValue.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            input.value = formattedValue;
            calculateNewBalance();
        }

        function quickFillField(fieldId, rawAmount) {
            const input = document.getElementById(fieldId);
            if (!input) return;
            const num = Math.abs(parseFloat(rawAmount) || 0);
            input.value = num > 0 ? parseFloat(num.toFixed(2)).toString() : '';
            formatNumberAndCalculateNewBalance(input);
        }

        function updateQuickFillButtons() {
            const dailyRent = parseNum('rent');
            const runningBal = parseNum('runningbal');
            const elecBal = parseNum('elecbal');
            const elecArrear = parseNum('elecarrear');
            const waterBal = parseNum('waterbal');
            const waterArrear = parseNum('waterarrear');

            const debtVal = (v) => v < 0 ? Math.abs(v) : (v > 0 ? v : 0);

            const configs = [
                { btnId: 'qf-paidrent', fieldId: 'paidrent', label: 'Daily', amt: dailyRent > 0 ? dailyRent : 0 },
                { btnId: 'qf-paidbal', fieldId: 'paidbal', label: 'Due', amt: debtVal(runningBal) },
                { btnId: 'qf-paidelec', fieldId: 'paidelec', label: 'Due', amt: debtVal(elecBal) },
                { btnId: 'qf-paidelecarrear', fieldId: 'paidelecarrear', label: 'Due', amt: debtVal(elecArrear) },
                { btnId: 'qf-paidwater', fieldId: 'paidwater', label: 'Due', amt: debtVal(waterBal) },
                { btnId: 'qf-paidwaterarrear', fieldId: 'paidwaterarrear', label: 'Due', amt: debtVal(waterArrear) }
            ];

            let anyDue = false;
            configs.forEach(cfg => {
                const btn = document.getElementById(cfg.btnId);
                if (!btn) return;
                const targetId = cfg.fieldId;
                if (cfg.amt > 0) {
                    anyDue = true;
                    btn.textContent = `${cfg.label} ₱${numberWithCommas(cfg.amt.toFixed(2))}`;
                    btn.classList.remove('hidden');
                    btn.onclick = () => quickFillField(targetId, cfg.amt);
                } else {
                    btn.classList.add('hidden');
                    btn.onclick = null;
                }
            });

            const fillAllBtn = document.getElementById('fillAllDuesBtn');
            if (fillAllBtn) {
                fillAllBtn.classList.toggle('hidden', !anyDue);
            }
        }

        function fillAllPendingDues() {
            const dailyRent = parseNum('rent');
            const debtVal = (id) => {
                const v = parseNum(id);
                return v < 0 ? Math.abs(v) : (v > 0 ? v : 0);
            };
            if (dailyRent > 0) document.getElementById('paidrent').value = parseFloat(dailyRent.toFixed(2));
            const rb = debtVal('runningbal'); if (rb > 0) document.getElementById('paidbal').value = parseFloat(rb.toFixed(2));
            const eb = debtVal('elecbal'); if (eb > 0) document.getElementById('paidelec').value = parseFloat(eb.toFixed(2));
            const ea = debtVal('elecarrear'); if (ea > 0) document.getElementById('paidelecarrear').value = parseFloat(ea.toFixed(2));
            const wb = debtVal('waterbal'); if (wb > 0) document.getElementById('paidwater').value = parseFloat(wb.toFixed(2));
            const wa = debtVal('waterarrear'); if (wa > 0) document.getElementById('paidwaterarrear').value = parseFloat(wa.toFixed(2));

            ['paidrent', 'paidbal', 'paidelec', 'paidelecarrear', 'paidwater', 'paidwaterarrear'].forEach(id => {
                const el = document.getElementById(id);
                if (el && el.value) {
                    el.value = el.value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
            });
            calculateNewBalance();
        }

        function setPaymentMethod(method) {
            const sel = document.getElementById('payment_method');
            if (sel) sel.value = method;

            const cashBtn = document.getElementById('methodBtnCash');
            const chequeBtn = document.getElementById('methodBtnCheque');
            if (cashBtn) cashBtn.classList.toggle('active', method === 'Cash');
            if (chequeBtn) chequeBtn.classList.toggle('active', method === 'Cheque');

            toggleChequeInput();
            calculateNewBalance();
        }

        function resetFullPosForm() {
            document.getElementById('spacecode-input').value = '';
            document.getElementById('spacecode-error').textContent = '';
            document.getElementById('chargecusa').value = '';
            document.getElementById('chargeac').value = '';
            document.getElementById('otheramount').value = '';
            document.getElementById('chargeothers').value = '';
            const initText = document.querySelector('#initialChargeSelect input[type="text"]');
            if (initText) initText.value = '';
            document.getElementById('additionalChargesContainer').innerHTML = '';
            setPaymentMethod('Cash');
            clearTenantDetails();
            calculateNewBalance();
            document.getElementById('spacecode-input').focus();
        }

        document.getElementById('addChargeButton').addEventListener('click', function () {
            var container = document.getElementById('additionalChargesContainer');
            var newEntry = document.createElement('div');
            newEntry.classList.add('grid', 'grid-cols-2', 'gap-3', 'items-end');
            newEntry.innerHTML = `
                <div class="pos-field custom-select">
                    <div class="field-top-row"><label class="field-label">Other Charge</label></div>
                    <div class="relative">
                        <input type="text" class="pos-input is-select-trigger cursor-pointer" placeholder="Select charge type" readonly onclick="openCustomSelect(this)" style="padding-right:32px;">
                        <i class="fas fa-chevron-down" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:11px;color:#94a3b8;pointer-events:none;"></i>
                        <input type="hidden" name="chargeothers[]">
                        <div class="dropdown-menu hidden">
                            <div class="search-box-wrap">
                                <input type="text" class="search-input" placeholder="Search charges...">
                            </div>
                            <div class="dropdown-options"></div>
                        </div>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <div class="pos-field flex-1">
                        <div class="field-top-row"><label class="field-label">Amount</label></div>
                        <div class="input-shell">
                            <span class="input-currency-prefix">&#x20B1;</span>
                            <input oninput="formatNumberAndCalculateNewBalance(this)"
                                class="pos-input has-currency"
                                type="text" name="otheramount[]" placeholder="0.00" inputmode="decimal">
                        </div>
                    </div>
                    <button type="button" class="remove-charge flex items-center justify-center rounded-xl transition" style="height:46px;width:44px;background:#fff1f2;color:#e11d48;border:1px solid #fecdd3;cursor:pointer;" title="Remove charge">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </div>
            `;

            newEntry.querySelector('.remove-charge').addEventListener('click', function () {
                container.removeChild(newEntry);
                calculateNewBalance();
            });

            container.appendChild(newEntry);
        });

        function showConfirmationModal(event) {
            event.preventDefault();
            updateDateTime();

            const collectionDateVal = document.getElementById('collected_date_part') ? document.getElementById('collected_date_part').value : '';
            const tenantName = document.getElementById('tenantname').value.trim() || 'N/A';
            const spaceCode = document.getElementById('spacecode-input').value.trim() || 'N/A';
            const tenantCode = document.getElementById('tenantcode').value.trim() || 'N/A';
            const paymentMethod = document.getElementById('payment_method').value || 'Cash';
            const chequeNum = (document.getElementById('cheque_number') ? document.getElementById('cheque_number').value.trim() : '');

            const paidrent = parseNum('paidrent');
            const paidbal = parseNum('paidbal');
            const paidelec = parseNum('paidelec');
            const paidelecarrear = parseNum('paidelecarrear');
            const paidwater = parseNum('paidwater');
            const paidwaterarrear = parseNum('paidwaterarrear');
            const totalPaidPayments = paidrent + paidbal + paidelec + paidelecarrear + paidwater + paidwaterarrear;

            const charges = [];
            const chargecusa = parseNum('chargecusa');
            if (chargecusa > 0) charges.push({ name: 'Cusa', amount: chargecusa });
            const chargeac = parseNum('chargeac');
            if (chargeac > 0) charges.push({ name: 'Aircon', amount: chargeac });

            const otherSelects = document.querySelectorAll('input[name="chargeothers[]"]');
            const otherAmounts = document.querySelectorAll('input[name="otheramount[]"]');
            for (let i = 0; i < otherSelects.length; i++) {
                const selectedChargeType = otherSelects[i].value;
                const otherAmount = parseFloat((otherAmounts[i].value || '').replace(/,/g, '')) || 0;
                if (selectedChargeType && otherAmount > 0) {
                    charges.push({ name: selectedChargeType, amount: otherAmount });
                }
            }
            const totalCharges = charges.reduce((sum, c) => sum + c.amount, 0);
            const total = totalPaidPayments + totalCharges;

            const newRentBalance = parseNum('newrentbalance');
            const newArrearBalance = parseNum('newbalance');
            const newElecTotal = parseNum('newelecbal') + parseNum('newelecarrear');
            const newWaterTotal = parseNum('newwaterbal') + parseNum('newwaterarrear');

            let paymentsHtml = '';
            const addRow = (lbl, val, color) => {
                if (val > 0) {
                    paymentsHtml += `
                        <div class="flex justify-between items-center py-1.5 border-b border-gray-100 text-xs">
                            <span style="color:#475569;font-weight:600;">${lbl}</span>
                            <span style="font-weight:800;color:${color};">${formatCurrency(val)}</span>
                        </div>`;
                }
            };
            addRow('Daily Rent', paidrent, '#047857');
            addRow('Rent Arrear', paidbal, '#6d28d9');
            addRow('Electricity', paidelec, '#b45309');
            addRow('Electricity Arrear', paidelecarrear, '#92400e');
            addRow('Water', paidwater, '#0369a1');
            addRow('Water Arrear', paidwaterarrear, '#075985');

            let chargesHtml = charges.map(c => `
                <div class="flex justify-between items-center py-1.5 border-b border-gray-100 text-xs">
                    <span style="color:#475569;font-weight:600;">${c.name}</span>
                    <span style="font-weight:700;color:#1e293b;">${formatCurrency(c.amount)}</span>
                </div>`).join('');

            const summaryHtml = `
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:13px;padding:12px;display:flex;justify-content:space-between;align-items:center;gap:10px;">
                    <div>
                        <div style="font-size:14px;font-weight:800;color:#1e3a8a;">${tenantName}</div>
                        <div style="font-size:11px;color:#475569;font-weight:600;margin-top:2px;">
                            <span style="color:#1d4ed8;font-weight:700;">Space:</span> ${spaceCode} &bull; <span style="color:#1d4ed8;font-weight:700;">Code:</span> ${tenantCode}
                        </div>
                        <div style="font-size:11px;color:#1e40af;font-weight:700;margin-top:4px;">
                            <i class="far fa-calendar-alt mr-1"></i> ${collectionDateVal}
                        </div>
                    </div>
                    <span class="cm-badge ${paymentMethod === 'Cheque' ? 'cm-badge-cheque' : 'cm-badge-cash'}">
                        ${paymentMethod}${paymentMethod === 'Cheque' && chequeNum ? ` #${chequeNum}` : ''}
                    </span>
                </div>

                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:13px;padding:12px;">
                    <div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">
                        Payment Breakdown
                    </div>
                    ${paymentsHtml || '<div style="font-size:11px;color:#94a3b8;font-style:italic;padding:4px 0;">No regular payments entered</div>'}
                    ${chargesHtml}
                </div>

                <div style="background:linear-gradient(135deg,#1d4ed8,#4f46e5);color:#fff;border-radius:13px;padding:13px 15px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;opacity:0.85;">Total Collection</div>
                        <div style="font-size:22px;font-weight:800;letter-spacing:-0.02em;">${formatCurrency(total)}</div>
                    </div>
                    <div style="font-size:11px;font-weight:700;opacity:0.9;text-transform:uppercase;">${paymentMethod}</div>
                </div>

                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:13px;padding:12px;">
                    <div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">
                        Remaining Balances After Payment
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div style="padding:7px 9px;background:#fff;border-radius:9px;border:1px solid #e2e8f0;display:flex;justify-content:space-between;">
                            <span style="color:#64748b;font-weight:600;">Rent:</span>
                            <span style="font-weight:800;color:${newRentBalance < 0 ? '#dc2626' : '#047857'};">${formatCurrency(newRentBalance)}</span>
                        </div>
                        <div style="padding:7px 9px;background:#fff;border-radius:9px;border:1px solid #e2e8f0;display:flex;justify-content:space-between;">
                            <span style="color:#64748b;font-weight:600;">Arrear:</span>
                            <span style="font-weight:800;color:${newArrearBalance < 0 ? '#dc2626' : '#6d28d9'};">${formatCurrency(newArrearBalance)}</span>
                        </div>
                        <div style="padding:7px 9px;background:#fffbeb;border-radius:9px;border:1px solid #fde68a;display:flex;justify-content:space-between;">
                            <span style="color:#92400e;font-weight:600;">Elec:</span>
                            <span style="font-weight:800;color:${newElecTotal < 0 ? '#dc2626' : '#78350f'};">${formatCurrency(newElecTotal)}</span>
                        </div>
                        <div style="padding:7px 9px;background:#f0f9ff;border-radius:9px;border:1px solid #bae6fd;display:flex;justify-content:space-between;">
                            <span style="color:#075985;font-weight:600;">Water:</span>
                            <span style="font-weight:800;color:${newWaterTotal < 0 ? '#dc2626' : '#0c4a6e'};">${formatCurrency(newWaterTotal)}</span>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('confirmationSummary').innerHTML = summaryHtml;
            document.getElementById('confirmationModal').classList.remove('hidden');
        }

        const confBtn = document.getElementById('confirmButton');
        if (confBtn) {
            confBtn.addEventListener('click', function () {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                updateDateTime();
                document.getElementById('collectionForm').submit();
            });
        }

        const canBtn = document.getElementById('cancelButton');
        if (canBtn) {
            canBtn.addEventListener('click', function () {
                document.getElementById('confirmationModal').classList.add('hidden');
            });
        }

        const colForm = document.getElementById('collectionForm');
        if (colForm) {
            colForm.addEventListener('submit', showConfirmationModal);
        }

        function loadPosBranchProgress() {
            const branch = (document.getElementById('branch')?.value || '').trim();
            const datePart = (document.getElementById('collected_date_part')?.value || '').trim();
            if (!branch) return;

            fetch(`fetch_monitoring_stats.php?branch=${encodeURIComponent(branch)}&date=${encodeURIComponent(datePart)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success) return;
                    const collected = Number(data.collected || 0);
                    const total = Number(data.total || 0);
                    const pending = Number(data.pending || 0);
                    const pct = total > 0 ? Math.min(100, Math.round((collected / total) * 100)) : 0;

                    const dpText = document.getElementById('desktopProgressText');
                    const dpFill = document.getElementById('desktopProgressFill');
                    const mpText = document.getElementById('mobileProgressText');

                    if (dpText) dpText.textContent = `${collected}/${total} Paid (${pending} left)`;
                    if (dpFill) dpFill.style.width = `${pct}%`;
                    if (mpText) mpText.innerHTML = `<i class="fas fa-chart-pie mr-1"></i> Today: ${collected}/${total} (${pending} left)`;
                })
                .catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', function () {
            var welcomeModalClosed = localStorage.getItem("welcomeModalClosed");
            if (!welcomeModalClosed) {
                document.getElementById("welcomeModal").classList.remove("hidden");
                setTimeout(function () {
                    document.getElementById("welcomeModal").classList.add("hidden");
                    localStorage.setItem("welcomeModalClosed", "true");
                }, 2000);
            }

            const datePart = document.getElementById('collected_date_part');
            if (datePart && !datePart.value) {
                datePart.value = new Date().toISOString().split('T')[0];
            }

            updateTimeWithSeconds();
            timeUpdateInterval = setInterval(updateTimeWithSeconds, 1000);
            updateDateTime();
            calculateNewBalance();
            loadPosBranchProgress();

            const prefill = localStorage.getItem('prefill_spacecode');
            if (prefill) {
                document.getElementById('spacecode-input').value = prefill;
                fetchTenantDetails(prefill);
                localStorage.removeItem('prefill_spacecode');
            }
        });

        // Mobile Drawer
        document.getElementById('burger-menu-btn').addEventListener('click', function () {
            const sideNav = document.getElementById('side-nav');
            sideNav.classList.remove('hidden');
            setTimeout(function () {
                const sideMenu = sideNav.querySelector('.side-menu');
                sideMenu.classList.add('translate-x-0');
                sideMenu.classList.remove('translate-x-full');
            }, 20);
        });
        document.getElementById('close-btn').addEventListener('click', closeMenu);
        document.getElementById('side-nav-backdrop').addEventListener('click', closeMenu);

        function closeMenu() {
            const sideNav = document.getElementById('side-nav');
            const sideMenu = sideNav.querySelector('.side-menu');
            sideMenu.classList.add('translate-x-full');
            sideMenu.classList.remove('translate-x-0');
            setTimeout(function () { sideNav.classList.add('hidden'); }, 300);
        }

        // Suggest Space Code and Auto Complete
        let suggestTimeout;
        let suggestXHR = null;
        function suggestSpaceCode(value) {
            clearTimeout(suggestTimeout);
            suggestTimeout = setTimeout(function () {
                var branch = document.getElementById("branch").value;
                if (suggestXHR) suggestXHR.abort();
                suggestXHR = new XMLHttpRequest();
                var xhr = suggestXHR;
                xhr.open("POST", "suggest_spacecode.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        var suggestions = JSON.parse(xhr.responseText);
                        var suggestionsContainer = document.getElementById("spacecode-suggestions");
                        var errorSpan = document.getElementById("spacecode-error");
                        suggestionsContainer.innerHTML = "";

                        if (suggestions.length > 0) {
                            suggestions.forEach(function (suggestion) {
                                var option = document.createElement("div");
                                option.className = "space-suggestion-item";
                                option.innerHTML = `<span><i class="fas fa-store-alt mr-2" style="color:#3b82f6;font-size:12px;"></i>${suggestion}</span><i class="fas fa-arrow-right" style="font-size:11px;color:#94a3b8;"></i>`;
                                option.onclick = function () {
                                    document.getElementById("spacecode-input").value = suggestion;
                                    suggestionsContainer.classList.add("hidden");
                                    fetchTenantDetails(suggestion);
                                };
                                suggestionsContainer.appendChild(option);
                            });

                            suggestionsContainer.classList.remove("hidden");
                            errorSpan.textContent = "";
                        } else {
                            suggestionsContainer.classList.add("hidden");
                            errorSpan.textContent = "No matching space code found";
                        }

                        if (suggestions.includes(value)) {
                            fetchTenantDetails(value);
                        } else {
                            clearTenantDetails();
                        }
                    }
                };
                xhr.send("search=" + encodeURIComponent(value) + "&branch=" + encodeURIComponent(branch));
            }, 260);
        }

        document.addEventListener("click", function (event) {
            var suggestionsContainer = document.getElementById("spacecode-suggestions");
            var scInput = document.getElementById("spacecode-input");
            if (event.target !== suggestionsContainer && event.target !== scInput && !suggestionsContainer.contains(event.target)) {
                suggestionsContainer.classList.add("hidden");
            }
        });

        let fetchTenantXHR = null;
        function fetchTenantDetails(spacecode) {
            var branch = document.getElementById("branch").value;
            var selectedDate = document.getElementById("collected_date_part").value;
            if (fetchTenantXHR) fetchTenantXHR.abort();
            fetchTenantXHR = new XMLHttpRequest();
            var xhr = fetchTenantXHR;
            xhr.open("POST", "get_tenant_details.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        document.getElementById("tenantcode").value = response.tenantcode;
                        document.getElementById("tenantname").value = response.tenantname;
                        document.getElementById("rent").value = response.dailyRent;
                        document.getElementById("rentbal").value = response.rentbal;
                        document.getElementById("runningbal").value = response.runningbal;
                        document.getElementById("elecarrear").value = response.elecarrear || "0.00";
                        document.getElementById("elecbal").value = response.elecbal || "0.00";
                        document.getElementById("electotal").value = response.electotal || "0.00";
                        document.getElementById("waterarrear").value = response.waterarrear || "0.00";
                        document.getElementById("waterbal").value = response.waterbal || "0.00";
                        document.getElementById("watertotal").value = response.watertotal || "0.00";

                        var isAmbulant = response.editable;
                        document.getElementById("tenantcode").readOnly = !isAmbulant;
                        document.getElementById("tenantname").readOnly = !isAmbulant;
                        document.getElementById("rent").readOnly = !isAmbulant;
                        document.getElementById("rentbal").readOnly = !isAmbulant;
                        document.getElementById("runningbal").readOnly = !isAmbulant;
                        document.getElementById("elecarrear").readOnly = !isAmbulant;
                        document.getElementById("elecbal").readOnly = !isAmbulant;
                        document.getElementById("waterarrear").readOnly = !isAmbulant;
                        document.getElementById("waterbal").readOnly = !isAmbulant;

                        var statusContainer = document.getElementById("payment-status-container");
                        var statusText = document.getElementById("payment-status-text");
                        var statusIcon = document.getElementById("payment-status-icon");

                        var todayStr = new Date().toISOString().split('T')[0];
                        var dateLabel = selectedDate === todayStr ? "TODAY" : selectedDate;

                        statusContainer.classList.remove("hidden");
                        if (response.paidToday) {
                            statusContainer.className = "status-bubble status-paid";
                            statusText.textContent = "ALREADY PAID ON " + dateLabel;
                            statusIcon.className = "fas fa-check-circle";
                            document.getElementById("submitButton").disabled = false;
                        } else {
                            statusContainer.className = "status-bubble status-unpaid";
                            statusText.textContent = "NOT YET PAID ON " + dateLabel;
                            statusIcon.className = "fas fa-exclamation-circle";
                        }

                        updateQuickFillButtons();
                        calculateNewBalance();
                    } else {
                        clearTenantDetails();
                    }
                }
            };
            xhr.send("spacecode=" + encodeURIComponent(spacecode) + "&branch=" + encodeURIComponent(branch) + "&date=" + encodeURIComponent(selectedDate));
        }

        function clearTenantDetails() {
            document.getElementById("tenantcode").value = "";
            document.getElementById("tenantname").value = "";
            document.getElementById("rent").value = "";
            document.getElementById("rentbal").value = "";
            document.getElementById("runningbal").value = "";
            document.getElementById("elecarrear").value = "";
            document.getElementById("elecbal").value = "";
            document.getElementById("electotal").value = "";
            document.getElementById("waterarrear").value = "";
            document.getElementById("waterbal").value = "";
            document.getElementById("watertotal").value = "";
            document.getElementById("paidrent").value = "";
            document.getElementById("paidbal").value = "";
            document.getElementById("paidelec").value = "";
            if (document.getElementById("paidelecarrear")) document.getElementById("paidelecarrear").value = "";
            document.getElementById("paidwater").value = "";
            if (document.getElementById("paidwaterarrear")) document.getElementById("paidwaterarrear").value = "";

            document.getElementById("tenantcode").readOnly = false;
            document.getElementById("tenantname").readOnly = false;
            document.getElementById("rent").readOnly = false;
            document.getElementById("rentbal").readOnly = false;
            document.getElementById("runningbal").readOnly = false;
            document.getElementById("elecarrear").readOnly = false;
            document.getElementById("elecbal").readOnly = false;
            document.getElementById("waterarrear").readOnly = false;
            document.getElementById("waterbal").readOnly = false;

            if (document.getElementById("total")) document.getElementById("total").value = "0.00";
            if (document.getElementById("newbalance")) document.getElementById("newbalance").value = "0.00";
            if (document.getElementById("newrentbalance")) document.getElementById("newrentbalance").value = "0.00";
            if (document.getElementById("newelecbal")) document.getElementById("newelecbal").value = "0.00";
            if (document.getElementById("newwaterbal")) document.getElementById("newwaterbal").value = "0.00";

            var statusContainer = document.getElementById("payment-status-container");
            if (statusContainer) statusContainer.classList.add("hidden");

            updateQuickFillButtons();
            calculateNewBalance();
        }

        document.getElementById("spacecode-input").addEventListener("input", function (event) {
            var value = event.target.value.trim();
            if (value === "") {
                document.getElementById("spacecode-error").textContent = "";
                clearTenantDetails();
            } else {
                suggestSpaceCode(value);
            }
        });

        const CHARGE_OPTIONS = [
            "Table Tennis", "Pay Toilet", "Pay Parking", "Ice & Water", "Ulam Vendor",
            "Gas", "Famylihan", "Garbage Haul", "Photocopy", "Tenant ID",
            "Function Room", "Tables & Chairs", "Overnight Works", "Vendo Sale",
            "Zumba", "Sec Dep", "Meter Dep", "Utility Dep", "Miscellaneous", "Forfeited Items",
            "Scrap", "Hasang"
        ];

        window.openCustomSelect = function (inputEl) {
            document.querySelectorAll('.custom-select .dropdown-menu').forEach(m => m.classList.add('hidden'));
            const wrapper = inputEl.closest('.custom-select');
            const menu = wrapper.querySelector('.dropdown-menu');
            const search = wrapper.querySelector('.dropdown-menu .search-input');

            menu.classList.remove('hidden');
            search.value = '';
            window.renderCustomSelectOptions(wrapper, '');
            search.focus();

            setTimeout(() => {
                menu.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
        };

        window.renderCustomSelectOptions = function (wrapper, filterText) {
            const optionsContainer = wrapper.querySelector('.dropdown-options');
            const input = wrapper.querySelector('input[type="text"][readonly]');
            const hidden = wrapper.querySelector('input[type="hidden"]');
            const menu = wrapper.querySelector('.dropdown-menu');
            const currentVal = hidden.value;

            optionsContainer.innerHTML = '';

            const clearDiv = document.createElement('div');
            clearDiv.className = 'dropdown-clear';
            clearDiv.innerHTML = '<i class="fas fa-times-circle"></i><span>Clear Selection</span>';
            clearDiv.onclick = (e) => {
                e.stopPropagation();
                input.value = '';
                hidden.value = '';
                menu.classList.add('hidden');
                const rowGrid = wrapper.closest('.grid');
                const amountInput = rowGrid ? rowGrid.querySelector('input[name="otheramount[]"]') : null;
                if (amountInput) amountInput.value = '';
                if (typeof calculateNewBalance === 'function') calculateNewBalance();
            };
            optionsContainer.appendChild(clearDiv);

            const filtered = CHARGE_OPTIONS.filter(opt => opt.toLowerCase().includes(filterText.toLowerCase()));
            filtered.forEach(opt => {
                const div = document.createElement('div');
                const isSelected = (opt === currentVal);
                div.className = 'dropdown-option' + (isSelected ? ' selected' : '');
                div.innerHTML = `<span>${opt}</span>${isSelected ? '<i class="fas fa-check text-xs"></i>' : ''}`;
                div.onclick = (e) => {
                    e.stopPropagation();
                    input.value = opt;
                    hidden.value = opt;
                    menu.classList.add('hidden');
                    if (typeof calculateNewBalance === 'function') calculateNewBalance();
                };
                optionsContainer.appendChild(div);
            });

            if (filtered.length === 0) {
                optionsContainer.innerHTML = '<div class="px-3 py-3 text-xs text-gray-400 text-center font-medium">No matching charges found</div>';
            }
        };

        document.addEventListener('input', function (e) {
            if (e.target.matches('.custom-select .dropdown-menu input')) {
                const wrapper = e.target.closest('.custom-select');
                window.renderCustomSelectOptions(wrapper, e.target.value);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.custom-select .dropdown-menu').forEach(m => m.classList.add('hidden'));
                const scSug = document.getElementById('spacecode-suggestions');
                if (scSug) scSug.classList.add('hidden');
            }
            if (e.target.matches('.custom-select input[readonly]')) {
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    e.preventDefault();
                    e.target.value = '';
                    const hidden = e.target.closest('.custom-select').querySelector('input[type="hidden"]');
                    if (hidden) hidden.value = '';
                    if (typeof calculateNewBalance === 'function') calculateNewBalance();
                }
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('.custom-select .dropdown-menu')) return;
            if (e.target.matches('.custom-select input[readonly]')) return;
            document.querySelectorAll('.custom-select .dropdown-menu').forEach(m => m.classList.add('hidden'));
        });

        function toggleChequeInput() {
            const paymentMethod = document.getElementById('payment_method').value;
            const chequeContainer = document.getElementById('cheque_container');
            const chequeInput = document.getElementById('cheque_number');
            const payeeInput = document.getElementById('cheque_payee');

            if (paymentMethod === 'Cheque') {
                chequeContainer.classList.remove('hidden');
                chequeInput.required = true;
                payeeInput.required = true;
            } else {
                chequeContainer.classList.add('hidden');
                chequeInput.required = false;
                payeeInput.required = false;
                chequeInput.value = '';
                payeeInput.value = '';
            }
        }

        // Date/Time picker initialization (Native Mobile + Flatpickr Desktop)
        document.addEventListener('DOMContentLoaded', function () {
            const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
                || (window.matchMedia && window.matchMedia("(max-width: 768px)").matches && ('ontouchstart' in window || navigator.maxTouchPoints > 0));

            const dateInput = document.getElementById('collected_date_part');
            const timeInput = document.getElementById('collected_time_part');

            if (isMobileDevice) {
                if (dateInput) {
                    dateInput.type = 'date';
                    dateInput.removeAttribute('readonly');
                    dateInput.addEventListener('change', function () { updateDateTime(true); });
                    dateInput.addEventListener('input', function () { updateDateTime(true); });
                    const wrapper = dateInput.closest('.date-input-wrapper');
                    if (wrapper) {
                        wrapper.addEventListener('click', function () {
                            if (dateInput.showPicker) {
                                try { dateInput.showPicker(); } catch (err) { dateInput.focus(); }
                            } else {
                                dateInput.focus();
                            }
                        });
                    }
                }

                if (timeInput) {
                    timeInput.type = 'time';
                    timeInput.removeAttribute('readonly');
                    if (timeInput.value && timeInput.value.length > 5) {
                        timeInput.value = timeInput.value.substring(0, 5);
                    }
                    timeInput.addEventListener('change', function () {
                        window.isManualTimeSet = true;
                        updateDateTime(false);
                    });
                    timeInput.addEventListener('input', function () {
                        window.isManualTimeSet = true;
                        updateDateTime(false);
                    });
                    const wrapper = timeInput.closest('.date-input-wrapper');
                    if (wrapper) {
                        wrapper.addEventListener('click', function () {
                            if (timeInput.showPicker) {
                                try { timeInput.showPicker(); } catch (err) { timeInput.focus(); }
                            } else {
                                timeInput.focus();
                            }
                        });
                    }
                }
            } else {
                if (dateInput && typeof flatpickr !== 'undefined') {
                    dateInput.readOnly = true;
                    const fpDate = flatpickr(dateInput, {
                        dateFormat: "Y-m-d",
                        altInput: true,
                        altFormat: "m/d/Y",
                        allowInput: false,
                        onChange: function () { updateDateTime(true); }
                    });
                    const wrapper = dateInput.closest('.date-input-wrapper');
                    if (wrapper) {
                        wrapper.addEventListener('click', function () {
                            if (fpDate && !fpDate.isOpen) fpDate.open();
                        });
                    }
                }

                if (timeInput && typeof flatpickr !== 'undefined') {
                    timeInput.readOnly = true;
                    const fpTime = flatpickr(timeInput, {
                        enableTime: true,
                        noCalendar: true,
                        dateFormat: "H:i:S",
                        altInput: true,
                        altFormat: "h:i:S K",
                        enableSeconds: true,
                        allowInput: false,
                        onChange: function () {
                            window.isManualTimeSet = true;
                            updateDateTime(false);
                        }
                    });
                    const wrapper = timeInput.closest('.date-input-wrapper');
                    if (wrapper) {
                        wrapper.addEventListener('click', function () {
                            if (fpTime && !fpTime.isOpen) fpTime.open();
                        });
                    }
                }
            }

            updateDateTime(false);
        });
    </script>
</body>

</html>
