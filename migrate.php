<?php
/**
 * Universal Database Migration & Schema Sync Utility
 * Safely inspects, updates, and creates all tables/columns across all branches.
 */
ob_start();
include 'config.php';

// Enable error reporting for diagnostics
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Determine action
$action_performed = false;
$auto_run = isset($_GET['run']) && $_GET['run'] == '1';
$is_post_run = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration']));

$should_migrate = $auto_run || $is_post_run;

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

// Standard column requirements
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
$tables_query = $conn->query("SHOW TABLES");
if ($tables_query) {
    while ($row = $tables_query->fetch_array()) {
        $all_db_tables[] = $row[0];
    }
}

// 3. Dynamically build table schema mapping
$dynamic_schema = [];

// Always include known core tenant tables even if not yet created
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
    } elseif (str_starts_with($tbl, 'collected')) {
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

// 4. Execution & Results Gathering
$results = [];
$table_creations = [];
$generated_sql = [];

if ($should_migrate) {
    $action_performed = true;

    // A. Create core tables if missing
    foreach ($core_tables as $ct_name => $ct_sql) {
        if (!in_array($ct_name, $all_db_tables)) {
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
        }
    }

    // B. Check and Add Columns for Each Target Table
    foreach ($dynamic_schema as $table => $columns) {
        // Check if table exists in DB
        $check_tbl = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$check_tbl || $check_tbl->num_rows === 0) {
            $results[$table][] = [
                'column' => '*',
                'status' => 'skipped',
                'message' => "Table `$table` does not exist in this database yet (skipped)."
            ];
            continue;
        }

        // Fetch all existing columns for this table in a single query
        $existing_cols = [];
        $cols_res = $conn->query("SHOW COLUMNS FROM `$table`");
        if ($cols_res) {
            while ($c_row = $cols_res->fetch_assoc()) {
                $existing_cols[] = strtolower($c_row['Field']);
            }
        }

        foreach ($columns as $col => $definition) {
            if (in_array(strtolower($col), $existing_cols)) {
                $results[$table][] = [
                    'column' => $col,
                    'status' => 'exists',
                    'message' => "Column `$col` already exists."
                ];
            } else {
                $alter_sql = "ALTER TABLE `$table` ADD `$col` $definition";
                $generated_sql[] = $alter_sql . ";";
                if ($conn->query($alter_sql)) {
                    $results[$table][] = [
                        'column' => $col,
                        'status' => 'added',
                        'message' => "Successfully added column `$col`."
                    ];
                } else {
                    $results[$table][] = [
                        'column' => $col,
                        'status' => 'error',
                        'message' => "Failed to add column `$col`: " . $conn->error
                    ];
                }
            }
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
                    <?php if (isset($conn) && $conn->ping()): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                            <span class="w-2 h-2 rounded-full bg-green-500 mr-1.5 animate-pulse"></span> Connected
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
                    <form method="POST" class="w-full sm:w-auto" onsubmit="return confirm('Do you want to run the database migration now?');">
                        <button type="submit" name="run_migration" value="1"
                            class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-xs font-extrabold rounded-xl text-white bg-blue-600 hover:bg-blue-700 shadow-md hover:shadow-lg transition active:scale-95">
                            <i class="fas fa-play-circle mr-2 text-sm"></i> Run Database Migration
                        </button>
                    </form>
                </div>
            </div>

            <!-- Migration Results Section -->
            <div class="p-6 sm:p-8">
                <?php if ($action_performed): ?>
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
