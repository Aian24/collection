<?php
/**
 * Universal Database Migration & Schema Sync Utility
 * 100% compatible with PHP 5.6+, PHP 7.x, PHP 8.x (including PHP 8.1 - 8.4 mysqli strict mode).
 */

// Turn off mysqli exception throwing so it doesn't crash on duplicate columns or existing tables
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Enable error display for diagnostics
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Polyfill for str_starts_with (PHP < 8.0 compatibility)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// Database Connection with graceful fallback
$db_error = null;
$conn = null;

try {
    if (file_exists('config.php')) {
        include_once 'config.php';
    } else {
        throw new Exception("config.php file not found in current directory.");
    }
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

if (!$conn || (isset($conn->connect_error) && $conn->connect_error)) {
    $db_error = $db_error ?? ($conn->connect_error ?? "Unable to establish database connection.");
}

// Determine action
$action_performed = false;
$auto_run = isset($_GET['run']) && $_GET['run'] == '1';
$is_post_run = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration']));
$should_migrate = ($auto_run || $is_post_run) && !$db_error;

// 1. Definition of core tables to create if missing
$core_tables = [
    'branches' => "CREATE TABLE IF NOT EXISTS `branches` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_name` varchar(100) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `branch_name` (`branch_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'void' => "CREATE TABLE IF NOT EXISTS `void` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `transaction_number` varchar(255) NOT NULL,
        `branch` varchar(255) NOT NULL,
        `note` text DEFAULT NULL,
        `void_date` datetime NOT NULL,
        `rent` decimal(10,2) DEFAULT NULL,
        `rentbal` decimal(10,2) DEFAULT NULL,
        `runningbal` decimal(10,2) DEFAULT NULL,
        `paidrent` decimal(10,2) DEFAULT NULL,
        `paidbal` decimal(10,2) DEFAULT NULL,
        `charges` varchar(225) DEFAULT NULL,
        `collector` varchar(255) DEFAULT NULL,
        `tenantname` varchar(255) DEFAULT NULL,
        `spacecode` varchar(255) DEFAULT NULL,
        `elecbal` varchar(100) DEFAULT '0',
        `paidelec` varchar(100) DEFAULT '0',
        `paidelecarrear` varchar(100) DEFAULT '0',
        `waterbal` varchar(100) DEFAULT '0',
        `paidwater` varchar(100) DEFAULT '0',
        `paidwaterarrear` varchar(100) DEFAULT '0',
        `elecarrear` varchar(100) DEFAULT '0',
        `waterarrear` varchar(100) DEFAULT '0',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'tenant_history' => "CREATE TABLE IF NOT EXISTS `tenant_history` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` int(11) DEFAULT NULL,
        `action` enum('created','updated','deleted') NOT NULL,
        `tenant_name` varchar(100) NOT NULL,
        `tenant_code` varchar(100) DEFAULT NULL,
        `space_code` varchar(100) DEFAULT NULL,
        `daily_rent` varchar(100) DEFAULT NULL,
        `rent_balance` varchar(100) DEFAULT NULL,
        `running_balance` varchar(100) DEFAULT NULL,
        `branch` varchar(100) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `user_name` varchar(255) NOT NULL,
        `changes_made` text DEFAULT NULL,
        `date` date NOT NULL,
        `elec_balance` varchar(100) DEFAULT '0',
        `water_balance` varchar(100) DEFAULT '0',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

// Target columns to sync (Electricity, Water, Arrears, Payments)
$tenant_columns = [
    'elecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'elecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'waterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'waterarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'rentbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'runningbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'daily' => 'VARCHAR(100) NULL DEFAULT "0"',
    'started_date' => 'VARCHAR(100) NULL DEFAULT ""'
];

$collected_columns = [
    'elecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidelec' => 'VARCHAR(100) NULL DEFAULT "0"',
    'newelecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'elecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'newelecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidelecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'waterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidwater' => 'VARCHAR(100) NULL DEFAULT "0"',
    'newwaterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'waterarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'newwaterarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidwaterarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'payment_method' => 'VARCHAR(50) NULL DEFAULT "Cash"',
    'cheque_number' => 'VARCHAR(100) NULL',
    'cheque_payee' => 'VARCHAR(255) NULL'
];

$void_columns = [
    'elecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidelec' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidelecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'waterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidwater' => 'VARCHAR(100) NULL DEFAULT "0"',
    'paidwaterarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'elecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
    'waterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
];

$history_columns = [
    'elec_balance' => 'VARCHAR(100) NULL DEFAULT "0"',
    'water_balance' => 'VARCHAR(100) NULL DEFAULT "0"'
];

$system_tables = ['system_settings', 'user_roles', 'users', 'branches'];

// 2. Discover all existing tables in DB
$all_db_tables = [];
$dynamic_schema = [];

if ($conn && !$db_error) {
    try {
        $tables_query = $conn->query("SHOW TABLES");
        if ($tables_query) {
            while ($row = $tables_query->fetch_array()) {
                $all_db_tables[] = $row[0];
            }
        }
    } catch (Throwable $e) {
        $db_error = $e->getMessage();
    }

    // Always include known core tenant tables
    $known_tenants = ['sanko', 'nova', 'apm', 'acc'];
    foreach ($known_tenants as $kt) {
        $dynamic_schema[$kt] = $tenant_columns;
    }

    // Check all discovered tables in DB
    foreach ($all_db_tables as $tbl) {
        if (in_array($tbl, $system_tables)) {
            continue;
        }
        if ($tbl === 'void') {
            $dynamic_schema['void'] = $void_columns;
        } elseif ($tbl === 'tenant_history') {
            $dynamic_schema['tenant_history'] = $history_columns;
        } elseif (strpos($tbl, 'collected') === 0) {
            $dynamic_schema[$tbl] = $collected_columns;
        } else {
            // Discovered tenant branch table
            $dynamic_schema[$tbl] = $tenant_columns;
        }
    }

    // Always ensure standard collection tables exist in schema
    $known_collected = ['collected', 'collectednova', 'collectedapm', 'collectedacc'];
    foreach ($known_collected as $kc) {
        if (!isset($dynamic_schema[$kc])) {
            $dynamic_schema[$kc] = $collected_columns;
        }
    }
}

// 4. Execution & Results Gathering
$results = [];
$table_creations = [];

if ($should_migrate && $conn) {
    $action_performed = true;

    // A. Create core tables if missing
    foreach ($core_tables as $ct_name => $ct_sql) {
        if (!in_array($ct_name, $all_db_tables)) {
            try {
                if ($conn->query($ct_sql)) {
                    $table_creations[$ct_name] = [
                        'status' => 'created',
                        'message' => "Table `$ct_name` created successfully."
                    ];
                    $all_db_tables[] = $ct_name;
                } else {
                    $table_creations[$ct_name] = [
                        'status' => 'error',
                        'message' => "Failed to create table `$ct_name`: " . $conn->error
                    ];
                }
            } catch (Throwable $e) {
                $table_creations[$ct_name] = [
                    'status' => 'error',
                    'message' => "Error: " . $e->getMessage()
                ];
            }
        }
    }

    // B. Check and Add Columns for Each Target Table
    foreach ($dynamic_schema as $table => $columns) {
        // Check if table exists in DB
        $check_tbl = null;
        try {
            $check_tbl = $conn->query("SHOW TABLES LIKE '$table'");
        } catch (Throwable $e) {}

        if (!$check_tbl || $check_tbl->num_rows === 0) {
            $results[$table][] = [
                'column' => '*',
                'status' => 'skipped',
                'message' => "Table `$table` does not exist in database (skipped)."
            ];
            continue;
        }

        // Fetch all existing columns for this table in a single query
        $existing_cols = [];
        try {
            $cols_res = $conn->query("SHOW COLUMNS FROM `$table`");
            if ($cols_res) {
                while ($c_row = $cols_res->fetch_assoc()) {
                    $existing_cols[] = strtolower(trim($c_row['Field']));
                }
            }
        } catch (Throwable $e) {}

        foreach ($columns as $col => $definition) {
            $col_clean = strtolower(trim($col));

            if (in_array($col_clean, $existing_cols)) {
                $results[$table][] = [
                    'column' => $col,
                    'status' => 'exists',
                    'message' => "Column `$col` already exists."
                ];
            } else {
                $alter_sql = "ALTER TABLE `$table` ADD `$col` $definition";
                $success = false;
                $err_msg = '';

                try {
                    $res = $conn->query($alter_sql);
                    if ($res) {
                        $success = true;
                    } else {
                        $err_msg = $conn->error;
                    }
                } catch (Throwable $e) {
                    $err_msg = $e->getMessage();
                }

                // If error is duplicate column (code 1060), treat as exists
                if (!$success && (strpos($err_msg, 'Duplicate column') !== false || ($conn && $conn->errno === 1060))) {
                    $results[$table][] = [
                        'column' => $col,
                        'status' => 'exists',
                        'message' => "Column `$col` already exists."
                    ];
                } elseif ($success) {
                    $results[$table][] = [
                        'column' => $col,
                        'status' => 'added',
                        'message' => "Successfully added column `$col`."
                    ];
                } else {
                    $results[$table][] = [
                        'column' => $col,
                        'status' => 'error',
                        'message' => "Failed to add `$col`: " . $err_msg
                    ];
                }
            }
        }
    }

    // C. Scan and Clean Legacy Utility Charges from `charges` strings in collection tables
    $migrated_utility_records = [];
    foreach ($all_db_tables as $tbl) {
        if (strpos($tbl, 'collected') !== 0) {
            continue;
        }

        // Verify that paidelec, paidwater, and charges exist
        $cols_in_tbl = [];
        $t_cols = $conn->query("SHOW COLUMNS FROM `$tbl`");
        if ($t_cols) {
            while ($tc = $t_cols->fetch_assoc()) {
                $cols_in_tbl[] = strtolower($tc['Field']);
            }
        }

        if (!in_array('charges', $cols_in_tbl) || !in_array('paidelec', $cols_in_tbl) || !in_array('paidwater', $cols_in_tbl)) {
            continue;
        }

        $query_charges = "SELECT id, transaction_number, charges, paidelec, paidwater, paidelecarrear, paidwaterarrear FROM `$tbl` WHERE charges IS NOT NULL AND charges != '' AND (charges LIKE '%Electricity%' OR charges LIKE '%Elec%' OR charges LIKE '%Water%')";
        $charges_res = $conn->query($query_charges);

        $table_cleaned_count = 0;
        if ($charges_res && $charges_res->num_rows > 0) {
            while ($crow = $charges_res->fetch_assoc()) {
                $c_str = $crow['charges'];
                preg_match_all('/([^:,]+):\s*([\d,]+(\.\d{1,2})?)/', $c_str, $matches);
                if (count($matches[0]) === 0) continue;

                $extracted_elec = 0;
                $extracted_water = 0;
                $remaining = [];
                $row_needs_update = false;

                foreach ($matches[1] as $idx => $ctype) {
                    $cval = (float) str_replace(',', '', $matches[2][$idx]);
                    $ctype_trim = trim($ctype);
                    $lower = strtolower($ctype_trim);

                    if (in_array($lower, ['electricity', 'elec', 'electric', 'paidelec', 'electricity bal', 'electricity arrear'])) {
                        $extracted_elec += $cval;
                        $row_needs_update = true;
                    } elseif (in_array($lower, ['water', 'paidwater', 'water bal', 'water arrear']) && strpos($lower, 'ice') === false) {
                        $extracted_water += $cval;
                        $row_needs_update = true;
                    } else {
                        $remaining[] = $ctype_trim . ': ' . number_format($cval, 2, '.', '');
                    }
                }

                if ($row_needs_update) {
                    $cur_elec = (float)($crow['paidelec'] ?? 0);
                    $cur_water = (float)($crow['paidwater'] ?? 0);

                    $new_elec = $cur_elec + $extracted_elec;
                    $new_water = $cur_water + $extracted_water;
                    $new_charges_str = !empty($remaining) ? implode(', ', $remaining) : '';

                    $u_stmt = $conn->prepare("UPDATE `$tbl` SET paidelec = ?, paidwater = ?, charges = ? WHERE id = ?");
                    $elec_val = number_format($new_elec, 2, '.', '');
                    $water_val = number_format($new_water, 2, '.', '');
                    $row_id = (int)$crow['id'];
                    $u_stmt->bind_param('sssi', $elec_val, $water_val, $new_charges_str, $row_id);
                    $u_stmt->execute();
                    $u_stmt->close();

                    $table_cleaned_count++;
                }
            }
        }

        if ($table_cleaned_count > 0) {
            $migrated_utility_records[$tbl] = $table_cleaned_count;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration & Schema Sync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 py-10 px-4">
    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- Header Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">
            <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-800 p-6 sm:p-8 text-white">
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-database text-xl"></i>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Database Migration & Schema Sync</h1>
                </div>
                <p class="text-blue-100 text-sm sm:text-base max-w-2xl">
                    Safely inspect and update all branch tables, collection logs, and tenant records with full support for Electricity, Water, Arrears, and Cheque fields.
                </p>
            </div>

            <!-- Action Bar -->
            <div class="p-6 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-600 font-semibold">Database Status:</span> 
                    <?php if ($conn && !$db_error): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                            <span class="w-2 h-2 rounded-full bg-green-500 mr-1.5 animate-pulse"></span> Connected (PHP <?php echo phpversion(); ?>)
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">
                            <span class="w-2 h-2 rounded-full bg-red-500 mr-1.5"></span> Disconnected
                        </span>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <a href="migration.sql" download class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-100 shadow-sm transition">
                        <i class="fas fa-file-download mr-1.5 text-blue-600"></i> Download migration.sql
                    </a>
                    <?php if ($conn && !$db_error): ?>
                        <form method="POST" class="w-full sm:w-auto" onsubmit="return confirm('Do you want to run the database migration now?');">
                            <button type="submit" name="run_migration" value="1"
                                class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-xs font-extrabold rounded-xl text-white bg-blue-600 hover:bg-blue-700 shadow-md hover:shadow-lg transition active:scale-95">
                                <i class="fas fa-play-circle mr-2 text-sm"></i> Run Database Migration
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Migration Results / Status Section -->
            <div class="p-6 sm:p-8">
                <?php if ($db_error): ?>
                    <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                        <div class="flex items-center font-bold mb-1">
                            <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i> Database Connection Error
                        </div>
                        <div class="text-xs font-mono bg-white p-3 rounded border border-red-200 text-red-700 mt-2">
                            <?php echo htmlspecialchars($db_error); ?>
                        </div>
                        <div class="mt-3 text-xs text-red-700">
                            Please check your <code>config.php</code> settings (database host, username, password, and database name).
                        </div>
                    </div>
                <?php elseif ($action_performed): ?>
                    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center text-emerald-900 text-sm font-semibold">
                        <i class="fas fa-check-circle text-emerald-600 text-xl mr-3"></i>
                        <div>
                            <div>Migration completed successfully!</div>
                            <div class="text-xs font-normal text-emerald-700 mt-0.5">All tables and columns have been verified and synchronized.</div>
                        </div>
                    </div>

                    <?php if (!empty($table_creations)): ?>
                        <div class="mb-6 border border-slate-200 rounded-xl overflow-hidden bg-white">
                            <div class="bg-slate-100 px-4 py-2.5 border-b border-slate-200 font-bold text-xs text-slate-700 uppercase tracking-wider">
                                Core Tables Initialized
                            </div>
                            <div class="p-3 divide-y divide-slate-100 text-xs">
                                <?php foreach ($table_creations as $t_name => $t_info): ?>
                                    <div class="py-1.5 flex justify-between items-center">
                                        <span class="font-mono font-bold text-slate-800"><?php echo htmlspecialchars($t_name); ?></span>
                                        <span class="text-green-700 font-semibold"><?php echo htmlspecialchars($t_info['message']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($migrated_utility_records)): ?>
                        <div class="mb-6 border border-amber-200 bg-amber-50/40 rounded-xl overflow-hidden shadow-xs">
                            <div class="bg-amber-100/70 px-4 py-2.5 border-b border-amber-200 font-bold text-xs text-amber-900 uppercase tracking-wider flex items-center">
                                <i class="fas fa-bolt text-yellow-600 mr-2"></i> Legacy Utility Charges Migrated
                            </div>
                            <div class="p-4 space-y-2 text-xs">
                                <p class="text-slate-700 font-medium">
                                    Cleaned and moved Electricity & Water payments from legacy charge strings into dedicated database columns:
                                </p>
                                <div class="divide-y divide-amber-100 bg-white rounded-lg border border-amber-200 p-2">
                                    <?php foreach ($migrated_utility_records as $m_tbl => $m_count): ?>
                                        <div class="py-1.5 px-2 flex justify-between items-center">
                                            <span class="font-mono font-bold text-slate-800"><?php echo htmlspecialchars($m_tbl); ?></span>
                                            <span class="text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                <i class="fas fa-check mr-1"></i> <?php echo $m_count; ?> transactions migrated
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-4">
                        <?php foreach ($results as $tbl => $cols): ?>
                            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm bg-white">
                                <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center">
                                    <span class="font-bold text-slate-800 text-sm flex items-center">
                                        <i class="fas fa-table text-blue-500 mr-2"></i> Table: <code class="ml-1 px-2 py-0.5 bg-white border border-slate-300 rounded text-blue-600 font-mono text-xs"><?php echo htmlspecialchars($tbl); ?></code>
                                    </span>
                                    <span class="text-xs text-slate-500 font-medium"><?php echo count($cols); ?> columns verified</span>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    <?php foreach ($cols as $c): ?>
                                        <div class="px-4 py-2 flex items-center justify-between text-xs">
                                            <div class="font-mono font-semibold text-slate-700">
                                                <span><?php echo htmlspecialchars($c['column']); ?></span>
                                            </div>
                                            <div>
                                                <?php if ($c['status'] === 'added'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">
                                                        <i class="fas fa-plus mr-1 text-[10px]"></i> Added
                                                    </span>
                                                <?php elseif ($c['status'] === 'exists'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">
                                                        <i class="fas fa-check mr-1 text-[10px] text-slate-400"></i> Already Exists
                                                    </span>
                                                <?php elseif ($c['status'] === 'skipped'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700">
                                                        <i class="fas fa-info-circle mr-1 text-[10px]"></i> Skipped (Table Not in DB)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700">
                                                        <i class="fas fa-times-circle mr-1 text-[10px]"></i> <?php echo htmlspecialchars($c['message']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl shadow-sm">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 mb-1">Ready to Migrate Database</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mb-6">
                            Click <strong>Run Database Migration</strong> above to scan your database and automatically add any missing columns.
                        </p>
                        
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-left max-w-xl mx-auto">
                            <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider mb-2">Discovered Tables to Sync:</h4>
                            <div class="flex flex-wrap gap-1.5 text-xs font-mono font-medium text-slate-700">
                                <?php foreach (array_keys($dynamic_schema) as $s_tbl): ?>
                                    <span class="bg-white px-2 py-1 rounded border border-slate-200 shadow-2xs"><?php echo htmlspecialchars($s_tbl); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-slate-50 p-4 border-t border-slate-200 text-center text-xs text-slate-500">
                Collection POS &bull; Database Migration Utility
            </div>
        </div>

        <!-- SQL Direct Import Preview Box -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-terminal text-slate-700 text-sm"></i>
                    <h2 class="text-sm font-bold text-slate-900">Direct SQL Import (phpMyAdmin / cPanel)</h2>
                </div>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('sqlCodeBlock').innerText); alert('SQL script copied to clipboard!');"
                    class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg border border-slate-300 transition">
                    <i class="fas fa-copy mr-1"></i> Copy SQL
                </button>
            </div>
            <p class="text-xs text-slate-500 mb-3">If you prefer executing the SQL directly inside phpMyAdmin or MySQL console, copy the script below:</p>
            <pre id="sqlCodeBlock" class="p-4 bg-slate-900 text-emerald-400 rounded-xl text-xs font-mono overflow-x-auto max-h-64 border border-slate-800 select-all"><?php 
                if (file_exists('migration.sql')) {
                    echo htmlspecialchars(file_get_contents('migration.sql'));
                } else {
                    echo "-- migration.sql not found";
                }
            ?></pre>
        </div>

    </div>
</body>
</html>
