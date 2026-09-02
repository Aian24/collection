<?php
/**
 * Database Migration Utility
 * Use this script on live/production servers to safely check and add new columns without phpMyAdmin/cPanel.
 */
include 'config.php';

$tables_schema = [
    'sanko' => [
        'elecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'elecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
    ],
    'nova' => [
        'elecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'elecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
    ],
    'apm' => [
        'elecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'elecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
    ],
    'collected' => [
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
        'paidwaterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
    ],
    'collectednova' => [
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
        'paidwaterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
    ],
    'collectedapm' => [
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
        'paidwaterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
    ],
    'void' => [
        'elecbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'paidelec' => 'VARCHAR(100) NULL DEFAULT "0"',
        'paidelecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterbal' => 'VARCHAR(100) NULL DEFAULT "0"',
        'paidwater' => 'VARCHAR(100) NULL DEFAULT "0"',
        'paidwaterarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
        'elecarrear' => 'VARCHAR(100) NULL DEFAULT "0"',
        'waterarrear' => 'VARCHAR(100) NULL DEFAULT "0"'
    ]
];

$results = [];
$action_performed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $action_performed = true;
    foreach ($tables_schema as $table => $columns) {
        // Verify table exists
        $check_table = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$check_table || $check_table->num_rows === 0) {
            $results[$table][] = [
                'column' => '*',
                'status' => 'error',
                'message' => "Table `$table` does not exist in the database."
            ];
            continue;
        }

        foreach ($columns as $col => $definition) {
            $check_col = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($check_col && $check_col->num_rows > 0) {
                $results[$table][] = [
                    'column' => $col,
                    'status' => 'exists',
                    'message' => "Column `$col` already exists."
                ];
            } else {
                $alter_sql = "ALTER TABLE `$table` ADD `$col` $definition";
                if ($conn->query($alter_sql)) {
                    $results[$table][] = [
                        'column' => $col,
                        'status' => 'added',
                        'message' => "Successfully added column `$col` ($definition)."
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
    <title>Database Migration Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 py-10 px-4">
    <div class="max-w-4xl mx-auto">
        
        <!-- Header Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200 mb-8">
            <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-800 p-6 sm:p-8 text-white">
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-database text-xl"></i>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Database Migration Tool</h1>
                </div>
                <p class="text-blue-100 text-sm sm:text-base max-w-2xl">
                    Safely update your live database schema with all required columns for Electricity and Water Arrears, Balances, and Payments.
                </p>
            </div>

            <!-- Action Bar -->
            <div class="p-6 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-sm text-slate-600">
                    <span class="font-semibold text-slate-900">Database Status:</span> 
                    <?php if ($conn->ping()): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span> Connected
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span> Disconnected
                        </span>
                    <?php endif; ?>
                </div>

                <form method="POST" onsubmit="return confirm('Do you want to run the database migration now?');">
                    <button type="submit" name="run_migration" value="1"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-md hover:shadow-lg transition-all">
                        <i class="fas fa-play-circle mr-2"></i> Run Database Migration
                    </button>
                </form>
            </div>

            <!-- Content Area -->
            <div class="p-6 sm:p-8">
                <?php if ($action_performed): ?>
                    <div class="mb-6 p-4 rounded-xl bg-blue-50 border border-blue-200 flex items-center text-blue-800 text-sm font-semibold">
                        <i class="fas fa-check-circle text-blue-600 text-lg mr-2.5"></i>
                        Migration completed! See the table breakdown below for details.
                    </div>

                    <div class="space-y-6">
                        <?php foreach ($results as $tbl => $cols): ?>
                            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm bg-white">
                                <div class="bg-slate-100 px-4 py-3 border-b border-slate-200 flex justify-between items-center">
                                    <span class="font-bold text-slate-800 text-sm flex items-center">
                                        <i class="fas fa-table text-slate-500 mr-2"></i> Table: <code class="ml-1 px-2 py-0.5 bg-white border border-slate-300 rounded text-blue-600 font-mono text-xs"><?php echo htmlspecialchars($tbl); ?></code>
                                    </span>
                                    <span class="text-xs text-slate-500 font-medium"><?php echo count($cols); ?> columns checked</span>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    <?php foreach ($cols as $c): ?>
                                        <div class="px-4 py-2.5 flex items-center justify-between text-xs sm:text-sm">
                                            <div class="flex items-center space-x-2 font-mono font-semibold text-slate-700">
                                                <span><?php echo htmlspecialchars($c['column']); ?></span>
                                            </div>
                                            <div>
                                                <?php if ($c['status'] === 'added'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-green-100 text-green-700">
                                                        <i class="fas fa-plus-circle mr-1"></i> Added
                                                    </span>
                                                <?php elseif ($c['status'] === 'exists'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-600">
                                                        <i class="fas fa-check mr-1 text-slate-400"></i> Already Exists
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-red-100 text-red-700">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo htmlspecialchars($c['message']); ?>
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
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-1">Ready to Migrate</h3>
                        <p class="text-sm text-slate-500 max-w-md mx-auto mb-6">
                            Click the <strong>Run Database Migration</strong> button above to check your database and automatically add any missing columns for electricity, water, and arrears.
                        </p>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-left max-w-xl mx-auto">
                            <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider mb-2">Tables Covered by this tool:</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs font-mono font-medium text-slate-700">
                                <span class="bg-white px-2 py-1 rounded border border-slate-200">sanko</span>
                                <span class="bg-white px-2 py-1 rounded border border-slate-200">nova</span>
                                <span class="bg-white px-2 py-1 rounded border border-slate-200">apm</span>
                                <span class="bg-white px-2 py-1 rounded border border-slate-200">collected</span>
                                <span class="bg-white px-2 py-1 rounded border border-slate-200">collectednova</span>
                                <span class="bg-white px-2 py-1 rounded border border-slate-200">collectedapm</span>
                                <span class="bg-white px-2 py-1 rounded border border-slate-200">void</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-slate-50 p-4 border-t border-slate-200 text-center text-xs text-slate-500">
                LCLopez Resources Collection System &bull; Database Migration Utility
            </div>
        </div>

    </div>
</body>
</html>
