<?php
include '../config.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized', 'notifications' => [], 'total' => 0]);
    exit();
}
session_write_close();

date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');
$startOfDay = $today . ' 00:00:00';
$endOfDay = $today . ' 23:59:59';

$branch_filter = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$limit_param = isset($_GET['limit']) ? $_GET['limit'] : '20';
$limit = ($limit_param === 'all') ? 250 : max(10, min(100, intval($limit_param)));

$queries = [];
$table_branch_map = [
    'Sanko Market' => 'collected',
    'Nova Market'  => 'collectednova',
    'APM'          => 'collectedapm',
];

if (isset($table_branch_map[$branch_filter])) {
    $tbl = $table_branch_map[$branch_filter];
    $queries[] = "(SELECT transaction_number, tenantname, spacecode, collector, payment_method, paidrent, paidbal, charges, collected_date, branch FROM {$tbl} WHERE collected_date BETWEEN ? AND ?)";
} else {
    $branch_filter = '';
    foreach ($table_branch_map as $tbl) {
        $queries[] = "(SELECT transaction_number, tenantname, spacecode, collector, payment_method, paidrent, paidbal, charges, collected_date, branch FROM {$tbl} WHERE collected_date BETWEEN ? AND ?)";
    }
}

function parse_charges_total($chargesRaw) {
    if (empty($chargesRaw)) return 0.0;
    $sum = 0.0;
    $decoded = json_decode($chargesRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $val) {
            $sum += floatval($val);
        }
        return $sum;
    }
    foreach (explode(',', $chargesRaw) as $entry) {
        $parts = explode(':', $entry);
        if (isset($parts[1])) {
            $sum += floatval(trim($parts[1]));
        }
    }
    return $sum;
}

function format_relative_time($timestamp) {
    if (!$timestamp) return '';
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    }
    return date('h:i A', $timestamp);
}

$all_items = [];
$total_payments = 0;
$total_collected = 0.0;
$branch_counts = [
    'APM'          => 0,
    'Sanko Market' => 0,
    'Nova Market'  => 0,
];

// Fetch today's payment transactions
$sql = implode(' UNION ALL ', $queries) . ' ORDER BY collected_date DESC';
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    $param_count = count($queries) * 2;
    $types = str_repeat('s', $param_count);
    $bind_params = [];
    for ($i = 0; $i < count($queries); $i++) {
        $bind_params[] = $startOfDay;
        $bind_params[] = $endOfDay;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$bind_params);
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $paidrent = floatval($row['paidrent'] ?? 0);
            $paidbal = floatval($row['paidbal'] ?? 0);
            $charges_total = parse_charges_total($row['charges'] ?? '');
            $amount = $paidrent + $paidbal + $charges_total;

            $parts = [];
            if ($paidrent > 0) $parts[] = 'rent';
            if ($paidbal > 0) $parts[] = 'balance';
            if ($charges_total > 0) $parts[] = 'charges';
            $action = empty($parts) ? 'payment recorded' : 'paid ' . implode(' & ', $parts);

            $branch = trim((string)($row['branch'] ?? ''));
            if ($branch !== '') {
                $branch_counts[$branch] = ($branch_counts[$branch] ?? 0) + 1;
            }

            $ts = strtotime($row['collected_date'] ?? '') ?: time();
            $tx = (string)($row['transaction_number'] ?? '');
            $tenant = trim((string)($row['tenantname'] ?? 'Tenant'));

            $total_payments++;
            $total_collected += $amount;

            $all_items[] = [
                'id'                 => 'pay-' . ($branch ?: 'br') . '-' . ($tx ?: $ts . '-' . $total_payments),
                'type'               => 'payment',
                'transaction_number' => $tx,
                'tenantname'         => $tenant,
                'spacecode'          => (string)($row['spacecode'] ?? ''),
                'collector'          => (string)($row['collector'] ?? ''),
                'payment_method'     => (string)($row['payment_method'] ?? ''),
                'note'               => '',
                'paidrent'           => round($paidrent, 2),
                'paidbal'            => round($paidbal, 2),
                'charges_total'      => round($charges_total, 2),
                'amount'             => round($amount, 2),
                'message'            => $tenant . ' ' . $action,
                'branch'             => $branch,
                'time'               => format_relative_time($ts),
                'time_exact'         => date('h:i A', $ts),
                'timestamp'          => $ts,
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
}

// Fetch today's voided transactions for operational alerts
$void_count = 0;
$void_sql = "SELECT transaction_number, branch, spacecode, note, void_date, paidrent, paidbal, charges, collector, tenantname FROM void WHERE void_date BETWEEN ? AND ?";
if ($branch_filter !== '') {
    $void_sql .= " AND branch = ?";
}
$void_sql .= " ORDER BY void_date DESC LIMIT 50";

$void_stmt = mysqli_prepare($conn, $void_sql);
if ($void_stmt) {
    if ($branch_filter !== '') {
        mysqli_stmt_bind_param($void_stmt, 'sss', $startOfDay, $endOfDay, $branch_filter);
    } else {
        mysqli_stmt_bind_param($void_stmt, 'ss', $startOfDay, $endOfDay);
    }
    if (mysqli_stmt_execute($void_stmt)) {
        $vres = mysqli_stmt_get_result($void_stmt);
        while ($vrow = mysqli_fetch_assoc($vres)) {
            $vrent = floatval($vrow['paidrent'] ?? 0);
            $vbal = floatval($vrow['paidbal'] ?? 0);
            $vcharges = parse_charges_total($vrow['charges'] ?? '');
            $vamt = $vrent + $vbal + $vcharges;
            $vts = strtotime($vrow['void_date'] ?? '') ?: time();
            $vtx = (string)($vrow['transaction_number'] ?? '');
            $vbranch = trim((string)($vrow['branch'] ?? ''));
            $vtenant = trim((string)($vrow['tenantname'] ?? 'Transaction'));
            $vnote = trim((string)($vrow['note'] ?? 'Voided receipt'));

            $void_count++;
            $all_items[] = [
                'id'                 => 'void-' . ($vbranch ?: 'br') . '-' . ($vtx ?: $vts . '-' . $void_count),
                'type'               => 'void',
                'transaction_number' => $vtx,
                'tenantname'         => $vtenant,
                'spacecode'          => (string)($vrow['spacecode'] ?? ''),
                'collector'          => (string)($vrow['collector'] ?? ''),
                'payment_method'     => 'Voided',
                'note'               => $vnote,
                'paidrent'           => round($vrent, 2),
                'paidbal'            => round($vbal, 2),
                'charges_total'      => round($vcharges, 2),
                'amount'             => round($vamt, 2),
                'message'            => 'Voided: ' . $vtenant . ($vnote !== '' ? ' (' . $vnote . ')' : ''),
                'branch'             => $vbranch,
                'time'               => format_relative_time($vts),
                'time_exact'         => date('h:i A', $vts),
                'timestamp'          => $vts,
            ];
        }
        mysqli_free_result($vres);
    }
    mysqli_stmt_close($void_stmt);
}

mysqli_close($conn);

usort($all_items, function ($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});

$notifications = array_slice($all_items, 0, $limit);

header('Content-Type: application/json');
echo json_encode([
    'notifications'   => $notifications,
    'total'           => $total_payments,
    'void_count'      => $void_count,
    'total_collected' => round($total_collected, 2),
    'branch_counts'   => $branch_counts,
    'date'            => $today,
]);
